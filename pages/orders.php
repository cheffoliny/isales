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

/* ===== ВАЖНО: взимаме wait И confirm ===== */
$sql = "
        SELECT
            o.id AS oID,
            o.num AS oNum,
            o.name AS oName,
            o.address AS oAddress,
            p.id AS pID,
            p.status,
            p.source_user,
            DATE_FORMAT(p.source_date, '%d.%m.%Y %H:%i') AS sourceDate
        FROM ppp p
        JOIN ". DB_NAMES['sod'] .".objects o
            ON o.id = p.id_dest AND p.dest_type = 'object'
        WHERE
        (
            (p.status = 'confirm'
             AND p.source_date >= CURDATE()
             AND p.source_date < CURDATE() + INTERVAL 1 DAY)
            OR
            (p.status = 'wait'
             AND p.source_date >= CURDATE() - INTERVAL 10 DAY)
        )
        ORDER BY p.`status` DESC, p.source_date, o.name ASC ";

$stmt = $db->prepare($sql);
//$stmt->bind_param("s", $today);
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
            Заявки за потвърждение
        </h5>
    </div>

    <div class="card-body">

        <div class="list-group list-group-flush">

<?php

if ($stmt->num_rows === 0) {

    echo '<div class="alert alert-warning mb-0">Няма заявки за обработка.</div>';

} else {

    $stmt->bind_result(
        $oID,
        $oNum,
        $oName,
        $oAddress,
        $pID,
        $status,
        $sourceUser,
        $sourceDate
    );

    while ($stmt->fetch()):

        $oID   = (int)$oID;
        $pID   = (int)$pID;
        $status = $status ?? 'wait';

        $oNum  = htmlspecialchars($oNum ?? '');
        $oName = htmlspecialchars($oName ?? '');
        $oAddress = htmlspecialchars($oAddress ?? '');
        $sourceUser = htmlspecialchars($sourceUser ?? '');

        /* ===== Цветова логика ===== */
        $statusClass = ($status === 'confirm')
                        ? 'bg-success'
                        : 'bg-warning';
?>

<div class="list-group-item d-flex justify-content-between align-items-center">


    <a href="dashboard.php?page=object_order&preparе=1&pppID=<?= $pID ?>&id=<?= $oID ?>"
       class="text-decoration-none text-body flex-grow-1">

        <div class="fw-semibold">
            [ <?= $oNum ?> ] - <?= $oName ?>
        </div>

        <div class="small text-body-secondary">
            <?= $oAddress ?>
        </div>

        <div class="small text-body-secondary">
            Създадена от: <?= $sourceUser ?> | <?= $sourceDate ?>
        </div>

    </a>

    <!-- STATUS BUTTON -->
    <button class="btn text-white rounded-circle d-flex align-items-center justify-content-center ms-3 order-status-btn <?= $statusClass ?>"
            style="width:42px;height:42px;"
            data-ppp="<?= $pID ?>"
            data-status="<?= $status ?>">
        <i class="fa-solid fa-check"></i>
    </button>

</div>

<?php
    endwhile;
}

$stmt->close();
$db->close();
?>

        </div>
    </div>
</div>

<!-- ================= STATUS TOGGLE SCRIPT ================= -->
<script>
$(document).on('click', '.order-status-btn', function(e){

    e.preventDefault();
    e.stopPropagation();

    const btn = $(this);
    const pppID = btn.data('ppp');
    let currentStatus = btn.data('status');

    let newStatus = (currentStatus === 'wait') ? 'confirm' : 'wait';

    $.post('includes/update_ppp_status.php', {
        pppID: pppID,
        status: newStatus
    }, function(resp){

        if(resp.success){

            btn.data('status', newStatus);

            btn.removeClass('bg-warning bg-success');

            if(newStatus === 'confirm'){
                btn.addClass('bg-success');
            } else {
                btn.addClass('bg-warning');
            }

        } else {
            alert('Грешка при обновяване!');
        }

    }, 'json');

});
</script>