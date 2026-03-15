<?php
include_once __DIR__ . '/../includes/functions.php';

if (empty($_SESSION['user_id'])) {
    echo '<div class="alert alert-danger">Нямате достъп.</div>';
    exit;
}

$idUser   = (int)$_SESSION['user_id'];
$objectId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$pppID    = isset($_GET['pppID']) ? (int)$_GET['pppID'] : 0;
$mode     = $_GET['mode'] ?? 'ordered';

if (!$objectId || !$pppID) {
    echo '<div class="alert alert-danger">Грешни параметри.</div>';
    exit;
}

$showAll = ($mode === 'all');

$db = db_connect('storage');
$objName = getObjectByID($objectId);
?>

<div class="card shadow mb-3 border-0">

<div class="card-header d-flex justify-content-between align-items-center">

<a href="dashboard.php?page=orders"
class="btn btn-outline-secondary btn-sm">
<i class="fa-solid fa-angles-left"></i>
</a>

<h5 class="mb-0">
Заявка за: <?= htmlspecialchars($objName) ?>
</h5>

<div class="btn-group">

<a href="dashboard.php?page=object_order&id=<?= $objectId ?>&pppID=<?= $pppID ?>"
class="btn btn-sm <?= !$showAll?'btn-primary':'btn-outline-primary' ?>">
ЗАЯВЕНО
</a>

<a href="dashboard.php?page=object_order&id=<?= $objectId ?>&pppID=<?= $pppID ?>&mode=all"
class="btn btn-sm <?= $showAll?'btn-primary':'btn-outline-primary' ?>">
ВСИЧКИ
</a>

</div>

</div>

<div class="card-body">

<div class="mb-3">

<input type="text"
id="deliverySearch"
class="form-control form-control-sm"
placeholder="ТЪРСИ ПО КОД ИЛИ ИМЕ...">

</div>

<div class="list-group list-group-flush" id="itemsList">

<?php

if(!$showAll){

$sql="
SELECT
n.id,
UPPER(n.nom_code),
UPPER(n.name),
n.client_price,
n.sales_price,
n.is_calc,
COALESCE(pe.count,0)

FROM ppp_elements pe
JOIN nomenclatures n ON n.id=pe.id_nomenclature

WHERE
pe.id_ppp=?
AND pe.to_arc=0

ORDER BY n.name
";

}else{

$sql="
SELECT
n.id,
UPPER(n.nom_code),
UPPER(n.name),
n.client_price,
n.sales_price,
n.is_calc,
COALESCE(pe.count,0)

FROM nomenclatures n

LEFT JOIN ppp_elements pe
ON pe.id_nomenclature=n.id
AND pe.id_ppp=?
AND pe.to_arc=0

WHERE
n.client_price>0
AND n.is_calc>0

ORDER BY
(pe.count>0) DESC,
n.name
";

}

$stmt=$db->prepare($sql);
$stmt->bind_param("i",$pppID);
$stmt->execute();

$stmt->bind_result(
$nID,
$nCode,
$nName,
$cPrice,
$sPrice,
$nCount,
$oQuantity
);

while($stmt->fetch()):

$nID=(int)$nID;

$sCode=htmlspecialchars($nCode);
$sName=htmlspecialchars($nName);

$cPriceRaw=(float)$cPrice;
$sPriceRaw=(float)$sPrice;

$nPriceRaw=$sPriceRaw>0?$sPriceRaw:$cPriceRaw;

$isPromo=$sPriceRaw>0?1:0;

$oQuantity=(int)$oQuantity;

$hasSaved=$oQuantity>0;

$maxQty=max(1,min(100,$nCount));

?>

<div class="list-group-item d-flex justify-content-between align-items-center flex-wrap"
data-code="<?= $sCode ?>"
data-name="<?= $sName ?>">

<div class="flex-grow-1">

<div class="fw-semibold">
<?= $sCode ?> - <?= $sName ?>
</div>

<div class="small text-info">

Налично: <?= $nCount ?>

/ Цена: <?= number_format($cPriceRaw,2) ?>

<?php if($isPromo): ?>

<span class="badge bg-danger">
ПРОМО <?= number_format($sPriceRaw,2) ?>
</span>

<?php endif; ?>

</div>

</div>

<div class="d-flex align-items-center gap-2">

<button class="btn btn-sm btn-outline-secondary qty-minus">
<i class="fa-solid fa-minus"></i>
</button>

<input type="number"
class="form-control form-control-sm qty-input"
value="<?= $oQuantity ?>"
min="0"
max="<?= $maxQty ?>">

<button class="btn btn-sm btn-outline-secondary qty-plus">
<i class="fa-solid fa-plus"></i>
</button>

<button class="btn btn-sm <?= $hasSaved?'btn-success':'btn-secondary' ?> save-delivery"
data-ppp="<?= $pppID ?>"
data-id="<?= $nID ?>"
data-price="<?= $nPriceRaw ?>">

<i class="fa-solid <?= $hasSaved?'fa-check':'fa-circle-check' ?>"></i>

</button>

</div>

</div>

<?php endwhile; ?>

</div>
</div>
</div>

<script>

/* ================= FILTER (като delivery_request) ================= */

function applyFilters(){

const search = ($('#deliverySearch').val() || '').toUpperCase();

$('#itemsList .list-group-item').each(function(){

    const code = ($(this).attr('data-code') || '').toUpperCase();
    const name = ($(this).attr('data-name') || '').toUpperCase();

    let visible = true;

    if(search && !code.includes(search) && !name.includes(search)){
        visible = false;
    }

    $(this).toggleClass('d-none', !visible);

});

}

let searchTimer;

$(document)
.off('input','#deliverySearch')
.on('input','#deliverySearch',function(){

clearTimeout(searchTimer);

const value = $(this).val();

searchTimer = setTimeout(function(){
    applyFilters();
},120);

});

$(document)
.off('input','#deliverySearch')
.on('input','#deliverySearch',applyFilters);


/* ================= QTY + ================= */

$(document).on('click','.qty-plus',function(){

const input=$(this).siblings('.qty-input');

let val=parseInt(input.val())||0;

const max=parseInt(input.attr('max'));

if(val<max) input.val(val+1);

});


/* ================= QTY - ================= */

$(document).on('click','.qty-minus',function(){

const input=$(this).siblings('.qty-input');

let val=parseInt(input.val())||0;

if(val>0) input.val(val-1);

});


/* ================= SAVE ================= */

$(document).on('click','.save-delivery',function(){

const btn=$(this);

const row=btn.closest('.list-group-item');

const qty=parseInt(row.find('.qty-input').val())||0;

const id_ppp=btn.data('ppp');
const id_n=btn.data('id');
const price=btn.data('price');

if(qty<=0) return;

$.post('includes/save_ppp_element.php',{

id_ppp:id_ppp,
id_nomenclature:id_n,
count:qty,
single_price:price

},function(resp){

if(resp.success){

btn.removeClass('btn-secondary')
.addClass('btn-success');

btn.find('i')
.removeClass('fa-circle-check')
.addClass('fa-check');

}

},'json');

});

</script>