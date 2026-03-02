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
                    <div class="flex items-start justify-between">
                        <div class="p-3 inline rounded-xl transition-all duration-300 group-hover:scale-110" style="background-color: rgba(6, 182, 212, 0.125); box-shadow: rgba(6, 182, 212, 0.19) 0px 0px 20px;">
                            <svg width="24px" height="24px" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path fill-rule="evenodd" clip-rule="evenodd" d="M6.96 4.13a.75.75 0 0 1 .369-1.264l4.767-1.045a.75.75 0 0 1 .893.893l-1.046 4.767a.75.75 0 0 1-1.262.37L6.959 4.129zm6.737 18.465a3.1 3.1 0 1 0 0-6.2 3.1 3.1 0 0 0 0 6.2zM7.407 7.403a1 1 0 0 0-1.414 0L3.69 9.705a4.246 4.246 0 0 0 0 6.005l.004.003a4.253 4.253 0 0 0 6.01-.003l6.005-6.005c.88-.88 2.305-.88 3.185-.002.878.876.879 2.298.003 3.176l-.002.001-1.77 1.77a1 1 0 0 0 1.414 1.415l1.77-1.77.004-.004a4.246 4.246 0 0 0-.007-6.004 4.253 4.253 0 0 0-6.01.003L8.29 14.295c-.879.88-2.304.88-3.185 0a2.246 2.246 0 0 1 0-3.175l2.302-2.303a1 1 0 0 0 0-1.414z" fill="#ffffff"/>
                            </svg>
                        </div>
                        <span class="font-semibold text-white fs-1"><?= $officeName ?></span>
                        <span class="fs-2 font-mono px-2 py-1 rounded-md" style="background-color: rgba(6, 182, 212, 0.082); color: rgb(6, 182, 212);"><?= $objectCount ?></span>
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