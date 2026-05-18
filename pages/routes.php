<?php

if (empty($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$no_dds = 1.2;
$markup_percentage = 16;
$markup_percentage_100 = 1.16;
$salary_percentage = 1.05;

$where_offices = '';
if( $_SESSION['offices_ids' ] != '0' ) {
    $where_offices = ' WHERE offs.id IN('.$_SESSION['offices_ids'].') ';
}

$db = db_connect('sod');

$stmt = $db->prepare("
                SELECT
                    offs.id AS offs_id,
                    offs.name AS offs_name,
                    offs.km_per_roadlist AS km_per_route,

                    sys.fuel_price,
                    sys.salary_per_day,

                    COUNT(DISTINCT o.id) AS obj_count,

                    COUNT(DISTINCT CASE
                        WHEN obj_sum.id_dest IS NOT NULL
                             AND (
                                 CAST(RIGHT(offs.code, 1) AS UNSIGNED) = WEEKDAY(NOW()) + 1
                                 OR offs.code = 99
                             )
                        THEN o.id
                    END) AS obj_visited,

                    SUM(CASE
                        WHEN obj_sum.id_dest IS NOT NULL
                             AND (
                                 CAST(RIGHT(offs.code, 1) AS UNSIGNED) = WEEKDAY(NOW()) + 1
                                 OR offs.code = 99
                             )
                        THEN obj_sum.total_sum
                        ELSE 0
                    END) AS total_sum,

                    SUM(CASE
                        WHEN obj_sum.id_dest IS NOT NULL
                             AND (
                                 CAST(RIGHT(offs.code, 1) AS UNSIGNED) = WEEKDAY(NOW()) + 1
                                 OR offs.code = 99
                             )
                        THEN obj_sum.total_qty
                        ELSE 0
                    END) AS total_qty

                FROM offices offs
                LEFT JOIN ". DB_NAMES['system'] .".`system` sys ON 1 = 1
                LEFT JOIN objects o ON JSON_CONTAINS(o.offices_ids, CONCAT(offs.id), '$') AND o.id_status = 1
                LEFT JOIN (
                    SELECT
                        p.id_dest,
                        SUM(pe_sum.total_qty) AS total_qty,
                        SUM(pe_sum.total_sum) AS total_sum
                    FROM ". DB_NAMES['storage'] .".ppp p
                    LEFT JOIN (
                        SELECT
                            pe.id_ppp,
                            SUM(pe.`count`) AS total_qty,
                            SUM(pe.`count` * pe.single_price) AS total_sum
                        FROM ". DB_NAMES['storage'] .".ppp_elements pe
                        WHERE pe.count > 0
                        GROUP BY pe.id_ppp
                    ) pe_sum ON pe_sum.id_ppp = p.id AND p.`status` != 'cancel'
                    WHERE p.source_date >= CURDATE()

                      AND p.source_date < CURDATE() + INTERVAL 1 DAY
                    GROUP BY p.id_dest
                ) obj_sum ON obj_sum.id_dest = o.id
                ". $where_offices ."
                GROUP BY offs.id, offs.name
                ORDER BY offs.name ASC
            ");

$stmt->execute();
$result = $stmt->get_result();

if (!$result || $result->num_rows === 0) {
    echo '<div class="alert alert-warning text-center m-3">Няма намерени маршрути!</div>';
    exit;
}
?>

<div class="container-fluid px-2">
    <div id="officeMsg"></div>

        <div class="list-group list-group-flush route-list">

        <?php while ($row = $result->fetch_assoc()):

                $officeId = (int)$row['offs_id'];
                $officeName = htmlspecialchars($row['offs_name']);

                $kmRerRoute = (int)($row['km_per_route']);

                $fuelPrice = (float)$row['fuel_price'];
                $salaryPerDay = (float)$row['salary_per_day'];

                $objectCount = (int)$row['obj_count'];
                $objectVisited = (int)$row['obj_visited'];
                $oTotalSum = (float)$row['total_sum'];

                $percentage = $objectCount > 0 ? round(($objectVisited / $objectCount) * 100) : 0;

                if ($objectVisited === 0) {
                    $statusClass='route-danger';
                    $badgeClass='bg-danger';
                    $iconClass='text-danger';
                    $progressClass='bg-danger';
                } elseif ($percentage < 50) {
                    $statusClass='route-warning';
                    $badgeClass='bg-warning text-dark';
                    $iconClass='text-warning';
                    $progressClass='bg-warning';
                } else {
                    $statusClass='route-success';
                    $badgeClass='bg-success';
                    $iconClass='text-success';
                    $progressClass='bg-success';
                }

                $sumNoDDS   = ROUND(($oTotalSum / $no_dds), 2);
                $sumEarning = ROUND($sumNoDDS - ($sumNoDDS / $markup_percentage_100), 2);
                $sumExpense = ROUND((( 2 * (($kmRerRoute / $objectCount) * $objectVisited) / 10) * ($fuelPrice/$no_dds)) + ($sumNoDDS - ($sumNoDDS/$salary_percentage)), 2);
                $sumBalance = ROUND($sumEarning - $sumExpense, 2);
        ?>

<div class="list-group-item d-flex flex-column route-card <?= $statusClass ?>">
    <div class="d-flex align-items-center justify-content-between mb-2">
        <div class="d-flex align-items-center gap-3">

        <?php if (!empty($_SESSION['is_admin']) && $_SESSION['is_admin']==1): ?>

            <button type="button"
                    class="route-icon <?= $iconClass ?> openEditOffice"
                    data-id="<?= $officeId ?>"
                    data-name="<?= $officeName ?>"
                    style="border:none;background:none;padding:0">
                    <i class="fa-solid fa-route"></i>
            </button>

        <?php else: ?>

            <div class="route-icon <?= $iconClass ?>">
                <i class="fa-solid fa-route"></i>
            </div>

        <?php endif; ?>

        <a href="dashboard.php?page=route_objects&id=<?= $officeId ?>"
            class="fw-semibold fs-5 text-decoration-none">
            <?= $officeName .' / '. $_SESSION['offices_ids' ]?>
        </a>
    </div>
    <?php if($_SESSION['is_admin'] == 1 && $objectVisited > 0   ) { ?>
    <span >
        <span class="badge rounded-pill <?= $badgeClass ?> fs-6">
            <i class="fa-solid fa-cash-register"></i> <?= $oTotalSum ?> /<?= $sumNoDDS ?>/
        </span>
        <span class="badge rounded-pill bg-success fs-6">
            <i class="fa-solid fa-plus-circle"></i> <?= $sumEarning ?> (<?= $markup_percentage?>%)
         </span>
        <span class="badge rounded-pill bg-danger fs-6">
            <i class="fa-solid fa-minus-circle"></i> <?= $sumExpense ?>
        </span>
        <span class="badge rounded-pill bg-info fs-6">
            <i class="fa-solid fa-scale-balanced"></i> <?= $sumBalance ?>
        </span>
    </span>
    <?php } ?>

    <span class="badge rounded-pill <?= $badgeClass ?> fs-6">
        <?= $objectVisited ?> / <?= $objectCount ?>
    </span>

</div>

<div class="d-flex align-items-center gap-2">
    <div class="progress flex-grow-1" style="height:8px">
        <div class="progress-bar <?= $progressClass ?>" style="width:<?= $percentage ?>%"></div>
    </div>

<span class="fw-semibold fs-6 text-nowrap">
<?= $percentage ?>%
</span>

</div>

</div>

<?php endwhile; ?>

</div>
</div>


<!-- MODAL -->

<div class="modal fade" id="editOfficeModal" tabindex="-1">

<div class="modal-dialog">

<div class="modal-content">

<div class="modal-header">

<h5 class="modal-title">Редакция на офис</h5>

<button type="button" class="btn-close" data-bs-dismiss="modal"></button>

</div>

<div class="modal-body">

<input type="hidden" id="edit_office_id">

<div class="mb-3">

<label class="form-label">Име на офис</label>

<input type="text"
class="form-control"
id="edit_office_name">

</div>

</div>

<div class="modal-footer">

<button type="button"
class="btn btn-secondary"
data-bs-dismiss="modal">

Затвори

</button>

<button type="button"
class="btn btn-success"
id="saveOfficeBtn">

<span id="saveText">Запиши</span>

<span id="saveSpinner"
class="spinner-border spinner-border-sm d-none"></span>

</button>

</div>

</div>
</div>
</div>


<script>

let savingOffice=false;

$(document).ready(function(){

$(document).on("click",".openEditOffice",function(e){

e.preventDefault();

let id=$(this).data("id");
let name=$(this).data("name");

$("#edit_office_id").val(id);
$("#edit_office_name").val(name);

let modal=new bootstrap.Modal(document.getElementById("editOfficeModal"));
modal.show();

});


$("#saveOfficeBtn").on("click",function(e){

e.preventDefault();

if(savingOffice===true){
return;
}

savingOffice=true;

let id=$("#edit_office_id").val();
let name=$("#edit_office_name").val();

$("#saveSpinner").removeClass("d-none");
$("#saveOfficeBtn").prop("disabled",true);

$.ajax({

url:"includes/update_offices.php",
method:"POST",
data:{id:id,name:name},
dataType:"json",

success:function(resp){

savingOffice=false;

$("#saveSpinner").addClass("d-none");
$("#saveOfficeBtn").prop("disabled",false);

if(resp.success){

let modal=bootstrap.Modal.getInstance(document.getElementById("editOfficeModal"));
modal.hide();

$("#officeMsg").html(
'<div class="alert alert-success alert-dismissible fade show mt-3">'+
'Офисът беше успешно записан.'+
'<button type="button" class="btn-close" data-bs-dismiss="alert"></button>'+
'</div>'
);

setTimeout(function(){
location.reload();
},1200);

}else{

$("#officeMsg").html(
'<div class="alert alert-danger alert-dismissible fade show mt-3">'+
resp.message+
'<button type="button" class="btn-close" data-bs-dismiss="alert"></button>'+
'</div>'
);

}

},

error:function(){

savingOffice=false;

$("#saveSpinner").addClass("d-none");
$("#saveOfficeBtn").prop("disabled",false);

$("#officeMsg").html(
'<div class="alert alert-danger mt-3">Сървърна грешка при запис.</div>'
);

}

});

});

});

</script>