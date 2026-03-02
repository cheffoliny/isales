<?php
$idUser   = (int) $_SESSION['user_id'];
$officeId = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($officeId <= 0) {
    echo '<div class="alert alert-danger">Невалиден офис.</div>';
    return;
}
?>

<a href="dashboard.php?page=routes" class="btn btn-outline-light mb-3">
    ← BACK
</a>

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
        o.geo_lan AS oLan
    FROM objects o
    WHERE o.id_office = ?
      AND o.id_status <> 4
    LIMIT 50
");

$stmt->bind_param("i", $officeId);
$stmt->execute();
$result = $stmt->get_result();

if (!$result || $result->num_rows === 0) {
    echo '<div class="alert alert-warning">Няма обекти.</div>';
    return;
}

while ($row = $result->fetch_assoc()) {

    $oID   = (int) $row['oID'];
    $oNum  = htmlspecialchars($row['oNum']);
    $oName = htmlspecialchars($row['oName']);
    $oInfo = htmlspecialchars($row['oInfo']);
    $oAddress = htmlspecialchars($row['oAddress']);
    $oLat  = $row['oLat'];
    $oLan  = $row['oLan'];

    $infoModalId = "infoModal{$oID}";
  //  $mapModalId  = "mapModal{$oID}";
    $strMapModal = 'modalMap'.$oID;
?>

<!-- ================= OBJECT CARD ================= -->
    <div data-slot="card" role="button" data-page="route_objects" data-id="<?= $officeId ?>"
        class="text-card-foreground flex flex-col pt-3 gap-3 rounded-xl mb-1 shadow-sm relative overflow-hidden border-0 bg-zinc-900/50 backdrop-blur-sm transition-all duration-300 hover:scale-[1.02] hover:bg-zinc-900/70 cursor-pointer h-full"
        style="box-shadow: rgba(6, 182, 212, 0.125) 0px 0px 0px 1px, rgba(6, 182, 212, 0.063) 0px 4px 24px;"
        data-search="['.$sCode.'] - '.$sName.'">
        <div class="absolute inset-0 opacity-0 group-hover:opacity-100 transition-opacity duration-500"
        style="background: radial-gradient(circle at 50% 0%, rgba(6, 182, 212, 0.082) 0%, transparent 70%);"></div>
        <div data-slot="card-header" class="@container/card-header grid auto-rows-min grid-rows-[auto_auto] items-start gap-2 px-3 has-data-[slot=card-action]:grid-cols-[1fr_auto] [.border-b]:pb-1 relative z-10">
            <div class="container-fluid px-2">
                <div class="row align-items-center g-2 py-2 flex-nowrap flex-md-wrap">

                    <!-- LEFT ICON -->
                    <div class="col-1">
                        <span class="p-3 d-inline-flex align-items-center justify-content-center rounded-3 bg-primary
                                     transition"
                              style="cursor:pointer;"
                              data-oid="<?= $oID ?>"
                              data-type="familiar"
                              onclick="openMapModal('<?= $strMapModal ?>', '<?= $oLat ?>', '<?= $oLan ?>', <?= $idUser ?>)">
                            <i class="fa-solid fa-car text-white fa-3x"></i>
                        </span>
                    </div>

                    <!-- TEXT BLOCK -->
                    <div class="col text-start text-break">

                        <a href="dashboard.php?page=delivery_request&id=<?= $oID ?>&office_id=<?= $officeId ?>"
                           class="text-decoration-none text-white">

                            <div class="fw-semibold fs-2">
                                [ <?= $oNum ?> ] - <?= $oName ?>
                            </div>

                            <div class="text-secondary small">
                                <?= $oAddress ?>
                            </div>

                        </a>
                    </div>

                    <!-- RIGHT ICON -->
                    <div class="col-1">
                        <span class="p-3 d-inline-flex align-items-center justify-content-center rounded-3 bg-dark save-delivery"
                              style="cursor:pointer;"
                              data-bs-toggle="modal"
                              data-bs-target="#<?= $infoModalId ?>">
                            <i class="fa-solid fa-circle-user text-white fa-3x"></i>
                        </span>
                    </div>

                </div>
            </div>
        </div>
    </div>



<!-- ================= INFO MODAL ================= -->
<div class="modal fade" id="<?= $infoModalId ?>" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content bg-dark text-white">
            <div class="modal-header">
                <h5 class="modal-title"><?= $oNum ?> - <?= $oName ?></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <?= nl2br($oInfo) ?>
            </div>
        </div>
    </div>
</div>

<!-- ================= MAP MODAL ================= -->

<div class="modal fade" id="<?= $strMapModal ?>" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content bg-dark text-white">

            <div class="modal-body p-0">
                <div id="mapContainer_<?= $oID ?>" style="height:500px;"></div>
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
}
$stmt->close();
$db->close();
?>