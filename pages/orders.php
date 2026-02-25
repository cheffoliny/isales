<?php
$idUser   = (int) $_SESSION['user_id'];
$officeId = isset($_GET['id']) ? (int) $_GET['id'] : 0;

// if ($officeId <= 0) {
//     echo '<div class="alert alert-danger">Невалиден офис.</div>';
//     return;
// }
?>

<a href="dashboard.php?page=routes" class="btn btn-outline-light mb-3">
    ← BACK
</a>

<?php

$db = db_connect('storage');

$stmt = $db->prepare("
    SELECT
        o.id AS oID,
        o.num AS oNum,
        o.name AS oName,
        o.address AS oAddress,
        p.id AS pID,
        p.`source_user` AS sUserName,
        p.source_date
    FROM ppp p
    JOIN ". DB_NAMES['sod'] .".objects o ON o.id = p.id_dest AND p.dest_type = 'object'
    WHERE
      p.`status` = 'open'
      AND DATE(p.source_date) = DATE(NOW())
");

//$stmt->bind_param("i", $officeId);
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
    $oAddress = htmlspecialchars($row['oAddress']);

    $pID   = (int) $row['pID'];
    $sUserName = htmlspecialchars($row['sUserName']);

?>

<!-- ================= OBJECT CARD ================= -->
    <div data-slot="card" role="button" data-page="route_objects" class="text-card-foreground flex flex-col pt-3 gap-3 rounded-xl mb-1 shadow-sm relative overflow-hidden border-0 bg-zinc-900/50 backdrop-blur-sm transition-all duration-300 hover:scale-[1.02] hover:bg-zinc-900/70 cursor-pointer h-full"
        style="box-shadow: rgba(6, 182, 212, 0.125) 0px 0px 0px 1px, rgba(6, 182, 212, 0.063) 0px 4px 24px;">
        <div class="absolute inset-0 opacity-0 group-hover:opacity-100 transition-opacity duration-500"
        style="background: radial-gradient(circle at 50% 0%, rgba(6, 182, 212, 0.082) 0%, transparent 70%);"></div>
        <div data-slot="card-header" class="@container/card-header grid auto-rows-min grid-rows-[auto_auto] items-start gap-2 px-3 has-data-[slot=card-action]:grid-cols-[1fr_auto] [.border-b]:pb-1 relative z-10">
            <div class="flex items-start">
                <span class="m-1 p-3 inline rounded-xl transition-all duration-300 group-hover:scale-110" style="background-color: rgba(6, 182, 212, 0.125); box-shadow: rgba(6, 182, 212, 0.19) 0px 0px 20px;">
                    <i class="fa-solid fa-list fa-lg text-white"></i>
                </span>
                <div class="m-1 ms-3 p-0 text-start">
                    <a href="dashboard.php?page=object_order&preparе=1&pppID=<?= $pID ?>&id=<?= $oID ?>"
                       class="d-block text-decoration-none text-white">

                        <div class="fw-semibold">
                            [ <?= $oNum ?> ] - <?= $oName ?>
                        </div>

                        <div class="text-secondary small">
                            <?= $oAddress ?>
                        </div>
                        <div class="text-secondary small">

                        </div>
                    </a>
                </div>

                <div class="ms-auto me-3 my-auto">

                </div>
                <span class="my-1 p-3 inline rounded-xl save-delivery">
                      <i class="fa-solid fa-circle-user fa-lg text-white m-1"></i>

                </span>
            </div>
        </div>
    </div>

<?php
}
$stmt->close();
$db->close();
?>