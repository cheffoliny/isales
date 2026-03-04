<?php
$idUser   = (int) $_SESSION['user_id'];
$officeId = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($officeId <= 0) {
    echo '<div class="alert alert-danger m-3">Невалиден офис.</div>';
    return;
}
?>
    <div class="card-header d-flex justify-content-between align-items-center">
        <a href="dashboard.php?page=routes" class="btn btn-outline-secondary mb-3">
            <i class="fa-solid fa-angles-left fa-1x"></i>
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
        AND DATE(source_date) = CURDATE()
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
    $oStatus  = $row['order_status'];
    $oNum     = htmlspecialchars($row['oNum']);
    $oName    = htmlspecialchars($row['oName']);
    $oInfo    = htmlspecialchars($row['oInfo']);
    $oAddress = htmlspecialchars($row['oAddress']);
    $oLat     = $row['oLat'];
    $oLan     = $row['oLan'];

    $infoModalId = "infoModal{$oID}";
    $mapModalId  = "mapModal{$oID}";
?>

<!-- ================= OBJECT CARD ================= -->
<div class="card mb-3 object-card shadow-sm">
    <div class="card-body d-flex align-items-center justify-content-between p-2">

        <div>
            <button class="btn btn-primary rounded-circle d-flex align-items-center justify-content-center"
                    onclick="openMapModal('<?= $mapModalId ?>', '<?= $oLat ?>', '<?= $oLan ?>', <?= $idUser ?>)">
                <i class="fa-solid fa-car"></i>
            </button>
        </div>

        <div class="flex-grow-1 px-2">
            <a href="dashboard.php?page=delivery_request&id=<?= $oID ?>&office_id=<?= $officeId ?>"
               class="text-decoration-none text-body">
                <div class="fw-semibold fs-5"><?= $oName ?></div>
                <div class="text-body-secondary small"><?= $oAddress ?></div>
            </a>
        </div>

        <div>
            <button class="btn btn-outline-secondary rounded-circle d-flex align-items-center justify-content-center"
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
<div class="modal fade" id="<?= $mapModalId ?>" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content bg-dark text-white">

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