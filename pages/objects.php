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

<div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
    <form class="d-flex gap-2" method="get" action="dashboard.php">
        <input type="hidden" name="page" value="objects">

        <!-- OFFICE FILTER -->
        <select name="id" class="form-select">
            <option value="0">Всички офиси</option>
            <?php foreach($offices as $off): ?>
                <option value="<?= $off['id'] ?>" <?= ($officeId == $off['id']) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($off['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <!-- SEARCH -->
        <input type="text"
               name="search"
               class="form-control"
               placeholder="Търси обект..."
               value="<?= htmlspecialchars($search) ?>">

        <button class="btn btn-primary">
            <i class="fa fa-search"></i>
        </button>
    </form>
</div>

<?php
/* ===== BUILD QUERY ===== */
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

/* FILTER OFFICE */
if ($officeId > 0) {
    $sql .= " AND o.id_office = ?";
    $params[] = $officeId;
    $types .= "i";
}

/* FILTER NAME */
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
$result = $stmt->get_result();

if (!$result || $result->num_rows === 0) {
    echo '<div class="alert alert-warning text-center m-3">Няма обекти.</div>';
    return;
}

while ($row = $result->fetch_assoc()):

    $oID      = (int)$row['oID'];
    $offsID   = (int)$row['offsID'];

    $oNum     = htmlspecialchars($row['oNum']);
    $oName    = htmlspecialchars($row['oName']);
    $oInfo    = htmlspecialchars($row['oInfo']);
    $oAddress = htmlspecialchars($row['oAddress']);

    $offsName = htmlspecialchars($row['offs_name']);

    $oLat     = htmlspecialchars($row['oLat']);
    $oLan     = htmlspecialchars($row['oLan']);

    $infoModalId = "infoModal{$oID}";
    $mapModalId  = "mapModal{$oID}";
    ?>

    <!-- ================= OBJECT CARD ================= -->
    <div class="card mb-3 object-card shadow-sm border-0">
        <div class="card-body d-flex align-items-center justify-content-between p-2">

            <!-- MAP BUTTON -->
            <div>
                <button
                        class="btn btn-primary rounded-circle d-flex align-items-center justify-content-center openMapBtn"
                        data-modal="<?= $mapModalId ?>"
                        data-map="mapContainer_<?= $oID ?>"
                        data-id="<?= $oID ?>"
                        data-lat="<?= $oLat ?>"
                        data-lng="<?= $oLan ?>">
                    <i class="fa-solid fa-home"></i>
                </button>
            </div>

            <!-- TEXT -->
            <div class="flex-grow-1 px-2">
                <button
                        class="btn p-0 text-start w-100 openEditObject"
                        data-id="<?= $oID ?>"
                        data-name="<?= htmlspecialchars($oName) ?>"
                        data-office="<?= $row['offsID'] ?>"
                        data-info="<?= htmlspecialchars($row['oInfo']) ?>">
                    <div class="fw-semibold fs-5"><?= $oName ?></div>
                    <div class="text-body-secondary small"><?= $offsName ?></div>
                </button>
            </div>

            <!-- ACTION BUTTONS -->
            <div class="d-flex gap-2">
                <button class="btn btn-outline-secondary rounded-circle d-flex align-items-center justify-content-center"
                        style="width:42px;height:42px;"
                        data-bs-toggle="modal"
                        data-bs-target="#<?= $infoModalId ?>">
                    <i class="fa-solid fa-circle-user"></i>
                </button>
            </div>
        </div>
    </div>

    <!-- ================= INFO MODAL ================= -->
    <div class="modal fade" id="<?= $infoModalId ?>" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><?= $oNum ?> - <?= $oName ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body"><?= nl2br($oInfo) ?></div>
            </div>
        </div>
    </div>

    <!-- ================= MAP MODAL ================= -->
    <div class="modal fade" id="<?= $mapModalId ?>" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-body p-0">
                    <div id="mapContainer_<?= $oID ?>" style="height:400px;width:100%"></div>
                </div>
                <div class="p-3 text-center">
                    <button class="btn btn-success saveObjectCoords" data-id="<?= $oID ?>">Запиши координати</button>
                </div>
            </div>
        </div>
    </div>

<?php endwhile; ?>

<!-- ================= ЕДИТ MODAL ================= -->
<div class="modal fade" id="editObjectModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Редакция на обект</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="edit_object_id">
                <div class="mb-3">
                    <label class="form-label">Име на обект</label>
                    <input type="text" class="form-control" id="edit_object_name">
                </div>
                <div class="mb-3">
                    <label class="form-label">Офис</label>
                    <select class="form-select" id="edit_object_office">
                        <?php foreach($offices as $off): ?>
                            <option value="<?= $off['id'] ?>" <?php if($off['id'] == $offsID) echo 'selected'; ?>>
                                <?= htmlspecialchars($off['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Оперативна информация</label>
                    <textarea class="form-control" rows="4" id="edit_object_info"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal">Затвори</button>
                <button class="btn btn-success" id="saveObjectBtn">Запиши</button>
            </div>
        </div>
    </div>
</div>

<?php
$stmt->close();
$db->close();
?>