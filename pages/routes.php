<?php
if (empty($_SESSION['user_id'])) {
    echo '<div class="alert alert-danger m-3">Нямате достъп.</div>';
    exit;
}

$db = db_connect('sod');

$stmt = $db->prepare("
    SELECT
        offs.id   AS offs_id,
        offs.name AS offs_name,
        COUNT(o.id) AS obj_count,
        SUM(IF(pe.id > 0, 1, 0)) AS obj_visited
    FROM objects o
    INNER JOIN offices offs ON offs.id = o.id_office
    LEFT JOIN alaska_storage.ppp p
        ON p.id_dest = o.id
        AND DATE(p.source_date) = CURDATE()
    LEFT JOIN alaska_storage.ppp_elements pe
        ON pe.id_ppp = p.id
        AND pe.count > 1
    WHERE o.id_status = 1
    GROUP BY offs.id, offs.name
    ORDER BY offs.name ASC
");

if (!$stmt) {
    echo '<div class="alert alert-danger m-3">Грешка при заявката.</div>';
    exit;
}

$stmt->execute();
$result = $stmt->get_result();

if (!$result || $result->num_rows === 0) {
    echo '<div class="alert alert-warning text-center m-3">
            Няма намерени маршрути!
          </div>';
    exit;
}
?>

<div class="container-fluid px-2">

    <div class="list-group list-group-flush route-list">

        <?php while ($row = $result->fetch_assoc()):
            $officeId      = (int)$row['offs_id'];
            $officeName    = htmlspecialchars($row['offs_name']);
            $objectCount   = (int)$row['obj_count'];
            $objectVisited = (int)$row['obj_visited'];

            $percentage = $objectCount > 0
                ? round(($objectVisited / $objectCount) * 100)
                : 0;

            // Определяне на статус
            if ($objectVisited === 0) {
                $statusClass = 'route-danger';
                $badgeClass  = 'bg-danger';
                $iconClass   = 'text-danger';
                $progressClass = 'bg-danger';
            } elseif ($percentage < 50) {
                $statusClass = 'route-warning';
                $badgeClass  = 'bg-warning text-dark';
                $iconClass   = 'text-warning';
                $progressClass = 'bg-warning';
            } else {
                $statusClass = 'route-success';
                $badgeClass  = 'bg-success';
                $iconClass   = 'text-success';
                $progressClass = 'bg-success';
            }
        ?>

        <a href="dashboard.php?page=route_objects&id=<?= $officeId ?>"
           class="list-group-item list-group-item-action d-flex flex-column route-card <?= $statusClass ?>">

            <div class="d-flex align-items-center justify-content-between mb-2">

                <div class="d-flex align-items-center gap-3">

                    <div class="route-icon <?= $iconClass ?>">
                        <i class="fa-solid fa-route"></i>
                    </div>

                    <div>
                        <div class="fw-semibold fs-5">
                            <?= $officeName ?>
                        </div>
                    </div>

                </div>

                <span class="badge rounded-pill <?= $badgeClass ?> fs-6">
                    <?= $objectVisited ?> / <?= $objectCount ?>
                </span>

            </div>

            <!-- Progress bar -->
            <div class="d-flex align-items-center gap-2">
                <div class="progress flex-grow-1" style="height: 8px;">
                    <div class="progress-bar <?= $progressClass ?>" role="progressbar"
                         style="width: <?= $percentage ?>%;"
                         aria-valuenow="<?= $percentage ?>" aria-valuemin="0" aria-valuemax="100">
                    </div>
                </div>
                <span class="fw-semibold fs-6 text-nowrap"><?= $percentage ?>%</span>
            </div>

        </a>

        <?php endwhile; ?>

    </div>

</div>