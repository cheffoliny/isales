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

            <button type="button" id="searchObjectsBtn" class="btn btn-primary btn-sm">
                <i class="fa fa-search"></i>
            </button>

            <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#addObjectModal">
                <i class="fa fa-plus"></i>
            </button>
        </div>
    </div>
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

    $oLat = $row['oLat'] ? (float)$row['oLat'] : 43.2728759;
    $oLan = $row['oLan'] ? (float)$row['oLan'] : 26.9266601;
    ?>

    <!-- ================= OBJECT CARD ================= -->
    <div class="card mb-3 object-card shadow-sm border-0">
        <div class="card-body d-flex align-items-center justify-content-between p-2">

            <!-- MAP BUTTON -->
            <div>
                <button type="button"
                        class="btn btn-primary rounded-circle d-flex align-items-center justify-content-center openMapBtn"
                        data-id="<?= $oID ?>"
                        data-lat="<?= $oLat ?>"
                        data-lng="<?= $oLan ?>">
                    <i class="fa-solid fa-home"></i>
                </button>
            </div>

            <!-- TEXT -->
            <div class="flex-grow-1 px-2">
                <button type="button"
                        class="btn p-0 text-start w-100 openEditObject"
                        data-id="<?= $oID ?>"
                        data-name="<?= $oName ?>"
                        data-office="<?= $row['offsID'] ?>"
                        data-info="<?= htmlspecialchars($row['oInfo']) ?>">
                    <div class="fw-semibold fs-5"><?= $oName ?></div>
                    <div class="text-body-secondary small"><?= $offsName ?></div>
                </button>
            </div>

            <!-- INFO BUTTON -->
            <div class="d-flex gap-2">
                <button type="button" class="btn btn-outline-secondary rounded-circle d-flex align-items-center justify-content-center"
                        style="width:42px;height:42px;"
                        data-bs-toggle="modal"
                        data-bs-target="#infoModal<?= $oID ?>">
                    <i class="fa-solid fa-circle-user"></i>
                </button>
            </div>
        </div>
    </div>

    <!-- ================= INFO MODAL ================= -->
    <div class="modal fade" id="infoModal<?= $oID ?>" tabindex="-1">
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

<?php endwhile; ?>

<!-- ================= ADD OBJECT MODAL ================= -->
<div class="modal fade" id="addObjectModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Добавяне на нов обект</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Име на обект <span class="text-danger">*</span></label>
                    <input type="text" class="form-control form-control-sm py-2" id="add_object_name" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Офис <span class="text-danger">*</span></label>
                    <select class="form-select form-select-sm" id="add_object_office" required>
                        <option value="">Избери офис</option>
                        <?php foreach($offices as $off): ?>
                            <option value="<?= $off['id'] ?>"><?= htmlspecialchars($off['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Оперативна информация</label>
                    <textarea class="form-control form-control-sm py-2" rows="4" id="add_object_info"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Затвори</button>
                <button type="button" class="btn btn-success btn-sm" id="saveNewObjectBtn">Добави</button>
            </div>
        </div>
    </div>
</div>

<!-- ================= EDIT OBJECT MODAL ================= -->
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
                    <label class="form-label">Име на обект <span class="text-danger">*</span></label>
                    <input type="text" class="form-control form-control-sm py-2" id="edit_object_name" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Офис <span class="text-danger">*</span></label>
                    <select class="form-select form-select-sm" id="edit_object_office" required>
                        <option value="">Избери офис</option>
                        <?php foreach($offices as $off): ?>
                            <option value="<?= $off['id'] ?>"><?= htmlspecialchars($off['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Оперативна информация</label>
                    <textarea class="form-control form-control-sm py-2" rows="4" id="edit_object_info"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Затвори</button>
                <button type="button" class="btn btn-success btn-sm" id="saveObjectBtn">Запази</button>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('objectSearch').addEventListener('keydown', function(e){
    if(e.key === "Enter"){
        document.getElementById('searchObjectsBtn').click();
    }
});

// SEARCH BUTTON
document.getElementById('searchObjectsBtn').addEventListener('click', function(){
    const office = document.getElementById('objectOfficeFilter').value;
    const search = document.getElementById('objectSearch').value;
    window.location.href = `dashboard.php?page=objects&id=${office}&search=${encodeURIComponent(search)}`;
});

// SAVE NEW OBJECT
document.getElementById('saveNewObjectBtn').addEventListener('click', function() {
    const name = document.getElementById('add_object_name').value.trim();
    const office = document.getElementById('add_object_office').value;
    const info = document.getElementById('add_object_info').value.trim();

    if (!name || !office) {
        alert('Моля, попълнете задължителните полета!');
        return;
    }

    fetch('includes/objects_add.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({name, office, info})
    })
    .then(res => res.json())
    .then(data => {
        if(data.success){
            alert('Обектът е добавен успешно!');
            bootstrap.Modal.getInstance(document.getElementById('addObjectModal')).hide();
            location.reload();
        } else {
            alert('Грешка: ' + data.message);
        }
    })
    .catch(err => {
        console.error(err);
        alert('Възникна грешка при добавянето на обекта.');
    });
});
</script>

<?php
$stmt->close();
$db->close();
?>