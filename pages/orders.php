<?php
include_once __DIR__ . '/../includes/functions.php';

if (empty($_SESSION['user_id'])) {
    echo '<div class="alert alert-danger">Нямате достъп.</div>';
    exit;
}

$idUser   = (int)$_SESSION['user_id'];
$officeId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$db = db_connect('storage');
$today = date('Y-m-d');

$sql = "
SELECT
    o.id AS oID,
    o.num AS oNum,
    o.name AS oName,
    o.address AS oAddress,
    p.id AS pID,
    p.source_user,
    DATE_FORMAT(p.source_date, '%d.%m.%Y %H:%i') AS sourceDate
FROM ppp p
JOIN ". DB_NAMES['sod'] .".objects o 
    ON o.id = p.id_dest 
    AND p.dest_type = 'object'
WHERE p.status = 'open'
  AND DATE(p.source_date) = ?
ORDER BY o.name ASC
";

$stmt = $db->prepare($sql);
$stmt->bind_param("s", $today);
$stmt->execute();
$stmt->store_result();

?>

<div class="card shadow mb-3 border-0">

    <div class="card-header d-flex justify-content-between align-items-center">
        <a href="dashboard.php?page=routes"
           class="btn btn-outline-secondary btn-sm">
            <i class="fa-solid fa-angles-left"></i>
        </a>

        <h5 class="mb-0">
            Активни заявки за днес
        </h5>
    </div>

    <div class="card-body">

        <div class="list-group list-group-flush">

            <?php

            if ($stmt->num_rows === 0) {

                echo '<div class="alert alert-warning mb-0">Няма активни заявки за днес.</div>';

            } else {

                $stmt->bind_result(
                    $oID,
                    $oNum,
                    $oName,
                    $oAddress,
                    $pID,
                    $sourceUser,
                    $sourceDate
                );

                while ($stmt->fetch()):

                    $oID   = (int)$oID;
                    $pID   = (int)$pID;
                    $oNum  = htmlspecialchars($oNum ?? '');
                    $oName = htmlspecialchars($oName ?? '');
                    $oAddress = htmlspecialchars($oAddress ?? '');
                    $sourceUser = htmlspecialchars($sourceUser ?? '');
                    ?>

                    <a href="dashboard.php?page=delivery_request&id=<?= $oID ?>&office_id=<?= $officeId ?>"
                       class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">

                        <div>

                            <div class="fw-semibold">
                                [ <?= $oNum ?> ] - <?= $oName ?>
                            </div>

                            <div class="small text-body-secondary">
                                <?= $oAddress ?>
                            </div>

                            <div class="small text-body-secondary">
                                Създадена от: <?= $sourceUser ?> | <?= $sourceDate ?>
                            </div>

                        </div>

                        <span class="badge bg-primary">
                    <i class="fa-solid fa-clipboard-list"></i>
                </span>

                    </a>

                <?php
                endwhile;
            }

            $stmt->close();
            $db->close();
            ?>

        </div>
    </div>
</div>