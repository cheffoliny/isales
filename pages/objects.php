<?php
include_once __DIR__ . '/../includes/functions.php';

if (empty($_SESSION['user_id'])) {
    echo '<div class="alert alert-danger m-3">Нямате достъп.</div>';
    return;
}

$idUser   = (int) $_SESSION['user_id'];
$officeId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$search   = trim($_GET['search'] ?? '');

$db = db_connect('sod');

/* ===== LOAD OFFICES ===== */
$offices = [];
$resOff = $db->query("SELECT id,name FROM offices WHERE to_arc = 0 ORDER BY name");
while($r = $resOff->fetch_assoc()){
    $offices[] = $r;
}
?>

<div class="card shadow mb-3 border-0">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div class="d-flex gap-2 w-100">
            <select id="objectOfficeFilter" class="form-select form-select-sm">
                <option value="0">Всички офиси</option>
                <?php foreach($offices as $off): ?>
                    <option value="<?= $off['id'] ?>" <?= ($officeId == $off['id']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($off['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <input type="text"
                   id="objectSearch"
                   class="form-control form-control-sm py-2"
                   placeholder="Търси обект..."
                   value="<?= htmlspecialchars($search) ?>">
        </div>
    </div>
</div>

<?php
/* ===== BUILD QUERY ===== */
function getObjects($db, $officeId, $search){
    $sql = "
    SELECT
        o.id AS oID,
        o.id_office AS offsID,
        o.num AS oNum,
        o.name AS oName,
        COALESCE(o.address,'...') AS oAddress,
        COALESCE(REPLACE(o.operativ_info,'\"',' '),'...') AS oInfo,
        o.geo_lat AS oLat,
        o.geo_lan AS oLan,
        offs.name AS offs_name
    FROM objects o
    LEFT JOIN offices offs ON offs.id = o.id_office
    WHERE o.id_status <> 4
    ";

    $params = [];
    $types  = "";

    if ($officeId > 0) {
        $sql .= " AND o.id_office = ?";
        $params[] = $officeId;
        $types .= "i";
    }

    if ($search !== '') {
        $sql .= " AND o.name LIKE ?";
        $params[] = "%{$search}%";
        $types .= "s";
    }

    $sql .= " ORDER BY o.name ASC LIMIT 1000";

    $stmt = $db->prepare($sql);
    if(!empty($params)){
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    return $stmt->get_result();
}

$result = getObjects($db, $officeId, $search);
?>

<div id="objectsContainer">
<?php if (!$result || $result->num_rows === 0): ?>
    <div class="alert alert-warning text-center m-3">Няма обекти.</div>
<?php else: ?>
    <?php while ($row = $result->fetch_assoc()):
        $oID      = (int)$row['oID'];
        $offsID   = (int)$row['offsID'];

        $oNum     = htmlspecialchars($row['oNum']);
        $oName    = htmlspecialchars($row['oName']);
        $oInfo    = htmlspecialchars($row['oInfo']);
        $oAddress = htmlspecialchars($row['oAddress']);
        $offsName = htmlspecialchars($row['offs_name']);

        $oLat = $row['oLat'] ? (float)$row['oLat'] : 43.2728759;
        $oLan = $row['oLan'] ? (float)$row['oLan'] : 26.9266601;
    ?>

    <div class="card mb-3 object-card shadow-sm border-0">
        <div class="card-body d-flex align-items-center justify-content-between p-2">
            <div>
                <button type="button"
                        class="btn btn-primary rounded-circle d-flex align-items-center justify-content-center openObjectModal"
                        data-id="<?= $oID ?>"
                        data-name="<?= $oName ?>"
                        data-office="<?= $offsID ?>"
                        data-info="<?= $oInfo ?>"
                        data-lat="<?= $oLat ?>"
                        data-lng="<?= $oLan ?>">
                    <i class="fa-solid fa-home"></i>
                </button>
            </div>

            <div class="flex-grow-1 px-2">
                <button type="button"
                        class="btn p-0 text-start w-100 openObjectModal"
                        data-id="<?= $oID ?>"
                        data-name="<?= $oName ?>"
                        data-office="<?= $offsID ?>"
                        data-info="<?= $oInfo ?>"
                        data-lat="<?= $oLat ?>"
                        data-lng="<?= $oLan ?>">
                    <div class="fw-semibold fs-5"><?= $oName ?></div>
                    <div class="text-body-secondary small"><?= $offsName ?></div>
                </button>
            </div>
        </div>
    </div>

    <?php endwhile; ?>
<?php endif; ?>
</div>

<!-- ================= SINGLE OBJECT MODAL ================= -->
<div class="modal fade" id="objectModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Редакция на обект</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="modal_object_id">

                <div class="mb-1">
                    <label class="form-label">Име на обект <span class="text-danger">*</span></label>
                    <input type="text" class="form-control form-control-sm py-1" id="modal_object_name">
                </div>

                <div class="mb-1">
                    <label class="form-label">Маршрут <span class="text-danger">*</span></label>
                    <select class="form-select form-select-sm" id="modal_object_office">
                        <option value="">Избери маршрут</option>
                        <?php foreach($offices as $off): ?>
                            <option value="<?= $off['id'] ?>"><?= htmlspecialchars($off['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-1">
                    <label class="form-label">Оперативна информация</label>
                    <textarea class="form-control form-control-sm py-2" rows="2" id="modal_object_info"></textarea>
                </div>

                <div class="mb-1">
                    <div id="objectMapContainer" style="height:400px;width:100%"></div>
                </div>

            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Затвори</button>
                <button type="button" class="btn btn-success btn-sm" id="saveObjectBtnModal">Запази</button>
            </div>
        </div>
    </div>
</div>

<?php
$db->close();
?>