<?php
include_once __DIR__ . '/../includes/functions.php';

if (empty($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$where_offices = '';
if( $_SESSION['offices_ids' ] != '0' ) {
    $where_offices = ' AND offs.id IN('.$_SESSION['offices_ids'].') ';
}


$db = db_connect('sod');

/* ===== LOAD OFFICES ===== */
$offices = [];
$resOff = $db->query("SELECT id,name FROM offices WHERE to_arc = 0 ORDER BY name");
while($r = $resOff->fetch_assoc()){
    $offices[] = $r;
}

/* ===== LOAD OBJECTS ===== */
$sql = "
        SELECT
            o.id,
            o.name,
            COALESCE(o.operativ_info,'') AS info,
            COALESCE(o.offices_ids,'...') AS offices_ids,
            o.geo_lat,
            o.geo_lan,
            COALESCE(GROUP_CONCAT(offs.name SEPARATOR ', '), '—') AS office_name
        FROM objects o
        JOIN offices_objects oo ON oo.id_object = o.id AND oo.to_arc = 0
        JOIN offices offs ON offs.id = oo.id_office
        WHERE o.id_status <> 4
            ". $where_offices ."
        GROUP BY o.id
        ORDER BY o.name ASC
        LIMIT 1000
";

$result = $db->query($sql);
?>

<div class="card shadow mb-3 border-0">

    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0"> Обекти </h5>

        <div class="btn-group">
            <button id="filterNoGeo" class="btn btn-sm btn-outline-primary">
                БЕЗ КООРДИНАТИ
            </button>
            <button id="filterAll" class="btn btn-sm btn-primary">
                ВСИЧКИ
            </button>
        </div>

    </div>

<div id="objectsContainer">

<?php if (!$result || $result->num_rows === 0): ?>
    <div class="alert alert-warning m-3 text-center">Няма обекти</div>
<?php else: ?>

<?php while($row = $result->fetch_assoc()):

    $id   = (int)$row['id'];
    $name = htmlspecialchars($row['name']);
    $info = htmlspecialchars($row['info']);
    $officesJson = htmlspecialchars($row['offices_ids'], ENT_QUOTES);

    $lat = $row['geo_lat'] ?: 43.2728759;
    $lng = $row['geo_lan'] ?: 26.9266601;
?>

    <div class="card mb-2 shadow-sm border-0 object-item"
         data-lat="<?= (float)$row['geo_lat'] ?>"
         data-lng="<?= (float)$row['geo_lan'] ?>">

        <div class="card-body d-flex align-items-center justify-content-between p-2">

            <button class="btn btn-primary openObjectModal"
                    data-id="<?= $id ?>"
                    data-name="<?= $name ?>"
                    data-info="<?= $info ?>"
                    data-offices='<?= $officesJson ?>'
                    data-lat="<?= $lat ?>"
                    data-lng="<?= $lng ?>">
                <i class="fa fa-home"></i>
            </button>

            <div class="flex-grow-1 px-2">
                <button class="btn p-0 text-start w-100 openObjectModal"
                        data-id="<?= $id ?>"
                        data-name="<?= $name ?>"
                        data-info="<?= $info ?>"
                        data-offices='<?= $officesJson ?>'
                        data-lat="<?= $lat ?>"
                        data-lng="<?= $lng ?>">

                    <div class="fw-bold"><?= $name ?></div>
                    <div class="small text-muted"><?= htmlspecialchars($row['office_name']) ?></div>

                </button>
            </div>

        </div>
    </div>

    <?php endwhile; ?>
    <?php endif; ?>

    </div>
</div>
<!-- MODAL -->
<div class="modal fade" id="objectModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">

            <div class="modal-header">
                <h5 class="modal-title">Редакция</h5>
                <button class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">

                <input type="hidden" id="modal_object_id">

                <div class="mb-2">
                    <label>Име *</label>
                    <input type="text" id="modal_object_name" class="form-control form-control-sm">
                </div>

                <div class="mb-2">
                    <label>Маршрути (незадължително)</label>

                    <div class="border rounded p-2" style="max-height:150px;overflow:auto;">
                        <div class="row">
                            <?php foreach($offices as $off): ?>
                                <div class="col-6 col-md-4">
                                    <div class="form-check">
                                        <input type="checkbox"
                                               class="form-check-input object-office-checkbox"
                                               value="<?= $off['id'] ?>"
                                               id="office_<?= $off['id'] ?>">
                                        <label class="form-check-label" for="office_<?= $off['id'] ?>">
                                            <?= htmlspecialchars($off['name']) ?>
                                        </label>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <div class="mb-2">
                    <label>Оперативна информация</label>
                    <textarea id="modal_object_info" class="form-control form-control-sm"></textarea>
                </div>

                <div id="objectMapContainer" style="height:400px;"></div>

            </div>

            <div class="modal-footer">
                <button class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Затвори</button>
                <button class="btn btn-success btn-sm" id="saveObjectBtnModal">Запази</button>
            </div>

        </div>
    </div>
</div>

<?php $db->close(); ?>

<script>
document.addEventListener("DOMContentLoaded", function () {

    const btnNoGeo = document.getElementById("filterNoGeo");
    const btnAll   = document.getElementById("filterAll");
    const items    = document.querySelectorAll(".object-item");

    function showAll() {
        items.forEach(el => el.style.display = "");
        btnAll.classList.add("btn-primary");
        btnAll.classList.remove("btn-outline-primary");

        btnNoGeo.classList.remove("btn-primary");
        btnNoGeo.classList.add("btn-outline-primary");
    }

    function showNoGeo() {
        items.forEach(el => {
            let lat = parseFloat(el.dataset.lat) || 0;
            let lng = parseFloat(el.dataset.lng) || 0;

            if (lat === 0 || lng === 0 || lng === 26.9266601) {
                el.style.display = "";
            } else {
                el.style.display = "none";
            }
        });

        btnNoGeo.classList.add("btn-primary");
        btnNoGeo.classList.remove("btn-outline-primary");

        btnAll.classList.remove("btn-primary");
        btnAll.classList.add("btn-outline-primary");
    }

    btnNoGeo.addEventListener("click", showNoGeo);
    btnAll.addEventListener("click", showAll);

});
</script>