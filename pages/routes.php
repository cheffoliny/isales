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
        COUNT(o.id) AS obj_count
    FROM objects o
    INNER JOIN offices offs ON offs.id = o.id_office
    WHERE o.id_status = 1
    GROUP BY offs.id, offs.name
    ORDER BY offs.name ASC
");

if (!$stmt) {
    die("SQL Error: " . $db->error);
}

//$stmt->bind_param("ss", $username, $password);
$stmt->execute();
$result = $stmt->get_result();

if (!$result) {
    echo '<div class="alert alert-danger m-3">Грешка при заявката.</div>';
    exit;
}

if (mysqli_num_rows($result) === 0) {
    echo '<div class="alert alert-warning text-center m-3">
            Няма намерени маршрути!
          </div>';
    exit;
}
echo "<section class='bg-dark'>";
while ($row = mysqli_fetch_assoc($result)) {

    $officeId    = (int)$row['offs_id'];
    $officeName  = htmlspecialchars($row['offs_name']);
    $objectCount = (int)$row['obj_count'];
    ?>

    <a href="dashboard.php?page=route_objects&id=<?=$officeId?>" class="block group text-description-none">
        <div data-slot="card" role="button" data-page="route_objects" data-id="<?= $officeId ?>" class="text-card-foreground flex flex-col gap-6 rounded-xl py-6 shadow-sm relative overflow-hidden border-0 bg-zinc-900/50 backdrop-blur-sm transition-all duration-300 hover:scale-[1.02] hover:bg-zinc-900/70 cursor-pointer h-full" >
            <div class="absolute inset-0 opacity-0 group-hover:opacity-100 transition-opacity duration-500" style="background: radial-gradient(circle at 50% 0%, rgba(6, 182, 212, 0.082) 0%, transparent 70%);"></div>
                <div data-slot="card-header" class="@container/card-header grid auto-rows-min grid-rows-[auto_auto] items-start gap-2 px-6 has-data-[slot=card-action]:grid-cols-[1fr_auto] [.border-b]:pb-6 relative z-10">
                    <div class="row align-items-center g-2 py-2 flex-nowrap flex-md-wrap">

                        <!-- LEFT ICON -->
                        <div class="col">
                            <div class="p-3 inline rounded-xl transition-all duration-300 group-hover:scale-110" style="background-color: rgba(6, 182, 212, 0.125); box-shadow: rgba(6, 182, 212, 0.19) 0px 0px 20px;">
                                <i class="fa-solid fa-route fa-3x text-white"></i>
                            </div>
                        </div>
                    <!-- TEXT BLOCK -->
                        <div class="col-9 text-start px-1">
                            <div class="font-semibold text-white fs-1">
                                <?= $officeName ?>
                            </div>
                        </div>
                        <div class="col">
                            <span class="fs-1 px-2 py-1 rounded-md" style="background-color: rgba(6, 182, 212, 0.082); color: rgb(6, 182, 212);"><?= $objectCount ?></span>
                        </div>
                    </div>
               <!--
               <div data-slot="card-title" class="font-semibold text-white text-lg"><?= $officeName ?></div>
               <div data-slot="card-description" class="text-zinc-400 text-sm leading-relaxed">
                    Equal parts inhale, hold, exhale, hold. Used by Navy SEALs for focus.
                </div>
                -->
            </div>
        </div>
    </a>

<?php
}
?>
</section>