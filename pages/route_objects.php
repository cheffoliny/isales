<?php
include_once __DIR__ . '/../includes/functions.php';

if (empty($_SESSION['user_id'])) {
    echo '<div class="alert alert-danger m-3">Нямате достъп.</div>';
    return;
}

$idUser   = (int) $_SESSION['user_id'];
$officeId = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($officeId <= 0) {
    echo '<div class="alert alert-danger m-3">Невалиден офис.</div>';
    return;
}
?>

<div class="card-header d-flex justify-content-between align-items-center">
    <a href="dashboard.php?page=routes" class="btn btn-outline-secondary mb-3">
        <i class="fa-solid fa-angles-left"></i>
    </a>
</div>

<?php
$db = db_connect('sod');

$stmt = $db->prepare("
    SELECT
        o.id AS oID,
        o.num AS oNum,
        o.name AS oName,
        COALESCE(o.address, '...') AS oAddress,
        COALESCE(REPLACE(o.operativ_info , '\"', ' '), '...') AS oInfo,
        o.geo_lat AS oLat,
        o.geo_lan AS oLan,
        p.id AS pppID,
        p.`status` AS order_status
    FROM objects o
    LEFT JOIN alaska_storage.ppp p
        ON o.id = p.id_dest
        AND DATE(p.source_date) = CURDATE()
    WHERE o.id_office = ?
      AND o.id_status <> 4
    ORDER BY o.name ASC
    LIMIT 50
");

$stmt->bind_param("i", $officeId);
$stmt->execute();
$result = $stmt->get_result();

if (!$result || $result->num_rows === 0) {
    echo '<div class="alert alert-warning text-center">Няма обекти.</div>';
    return;
}

while ($row = $result->fetch_assoc()):

    $oID      = (int) $row['oID'];
    $pppID    = (int) $row['pppID'];
    $oStatus  = $row['order_status'] ?? 'open';

    $oNum     = htmlspecialchars($row['oNum']);
    $oName    = htmlspecialchars($row['oName']);
    $oInfo    = htmlspecialchars($row['oInfo']);
    $oAddress = htmlspecialchars($row['oAddress']);
    $oLat     = $row['oLat'];
    $oLan     = $row['oLan'];

    $infoModalId = "infoModal{$oID}";
    $mapModalId  = "mapModal{$oID}";

    /* ===== STATUS COLOR ===== */

    $statusClass = 'bg-info';
    $disabled = '';

    if ($oStatus === 'wait') {
        $statusClass = 'bg-warning';
    }

    if ($oStatus === 'confirm') {
        $statusClass = 'bg-success';
        $disabled = 'disabled';
    }

?>

<!-- ================= OBJECT CARD ================= -->
<div class="card mb-3 object-card shadow-sm border-0">
    <div class="card-body d-flex align-items-center justify-content-between p-2">

        <!-- MAP BUTTON -->
        <div>
            <button class="btn btn-primary rounded-circle d-flex align-items-center justify-content-center"
                    style="width:48px;height:48px;"
                    onclick="openMapModal('<?= $mapModalId ?>', '<?= $oLat ?>', '<?= $oLan ?>', <?= $idUser ?>)">
                <i class="fa-solid fa-car"></i>
            </button>
        </div>

        <!-- TEXT -->
        <div class="flex-grow-1 px-2">
            <a href="dashboard.php?page=delivery_request&id=<?= $oID ?>&office_id=<?= $officeId ?>"
               class="text-decoration-none text-body">
                <div class="fw-semibold fs-5"><?= $oName ?></div>
                <div class="text-body-secondary small"><?= $oAddress ?></div>
            </a>
        </div>

        <!-- ACTION BUTTONS -->
        <div class="d-flex gap-2">

            <?php if ($pppID > 0): ?>

                <!-- STATUS BUTTON -->
                <button class="btn text-white rounded-circle d-flex align-items-center justify-content-center status-btn <?= $statusClass ?>"
                        style="width:42px;height:42px;"
                        data-ppp="<?= $pppID ?>"
                        data-status="<?= $oStatus ?>"
                        <?= $disabled ?>>
                    <i class="fa-solid fa-clock"></i>
                </button>

            <?php endif; ?>

            <!-- INFO BUTTON -->
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

            <div class="modal-body">
                <?= nl2br($oInfo) ?>
            </div>

        </div>
    </div>
</div>

<!-- ================= MAP MODAL ================= -->
<div class="modal fade" id="<?= $mapModalId ?>" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">

            <div class="modal-body p-0">
                <div id="mapContainer_<?= $oID ?>" style="height:400px;"></div>
            </div>

            <div class="p-3 text-center">
                <button class="btn btn-success"
                        onclick="openMapModal('mapContainer_<?= $oID ?>', '<?= $oLat ?>', '<?= $oLan ?>')">
                    Познавам
                </button>
            </div>

        </div>
    </div>
</div>

<?php
endwhile;

$stmt->close();
$db->close();
?>

<!-- ================= STATUS TOGGLE SCRIPT ================= -->
<script>
$(document).on('click', '.status-btn', function(){

    const btn = $(this);

    if(btn.prop('disabled')){
        return;
    }

    const pppID = btn.data('ppp');
    let currentStatus = btn.data('status');

    if(currentStatus === 'confirm'){
        return;
    }

    let newStatus = (currentStatus === 'open') ? 'wait' : 'open';

    $.post('includes/update_ppp_status.php', {
        pppID: pppID,
        status: newStatus
    }, function(resp){

        if(resp.success){

            btn.data('status', newStatus);

            btn.removeClass('bg-info bg-warning bg-success');

            if(newStatus === 'wait'){
                btn.addClass('bg-warning');
            } else {
                btn.addClass('bg-info');
            }

        } else {
            alert('Грешка при обновяване!');
        }

    }, 'json');

});
</script>