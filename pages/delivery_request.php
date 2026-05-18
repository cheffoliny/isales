<?php
include_once __DIR__.'/../includes/functions.php';

if (empty($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$idUser   = (int)$_SESSION['user_id'];
$firstName = trim($_SESSION['first_name'] ?? '');
$lastName  = trim($_SESSION['last_name'] ?? '');
$officeId = isset($_GET['office_id']) ? (int)$_GET['office_id'] : 0;
$objectId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($objectId <= 0) {
    echo '<div class="alert alert-danger">Невалиден обект.</div>';
    exit;
}

$db_storage = db_connect('storage');
$today = date('Y-m-d');

/* ================= CHECK / CREATE PPP ================= */

$checkSql = "SELECT id, status
             FROM ppp
             WHERE id_dest = ?
               AND dest_type = 'object'
               AND DATE(source_date) = ?
             LIMIT 1";

$stmt = $db_storage->prepare($checkSql);
$stmt->bind_param("is", $objectId, $today);
$stmt->execute();
$stmt->bind_result($existingID, $pppStatus);
$stmt->fetch();
$stmt->close();

if ($existingID) {

    $pppID = (int)$existingID;
    $pppStatus = $pppStatus ?: 'open';

} else {

    $sourceUser = $firstName.' '. $lastName. ''.$idUser;
    $status = 'open';
    $pppStatus = $status;

    $insertSql = "INSERT INTO ppp
        (`status`,`source_date`,`source_user`,`source_type`,`id_source`,`dest_type`,`id_dest`)
        VALUES (?,NOW(),?,'storagehouse',1,'object',?)";

    $stmt = $db_storage->prepare($insertSql);
    $stmt->bind_param("ssi",$status,$sourceUser,$objectId);
    $stmt->execute();

    $pppID = $db_storage->insert_id;

    $stmt->close();
}

if(!$pppID) die('PPP error');

$objName = getObjectByID($objectId);

/* ================= LOCK LOGIC ================= */

$isConfirmed = ($pppStatus === 'confirm');

$disabledAttr = $isConfirmed ? 'disabled' : '';
$lockedClass  = $isConfirmed ? 'opacity-50' : '';
?>

<div class="card shadow mb-3 border-0">

    <div class="card-header d-flex justify-content-between align-items-center">
        <a href="dashboard.php?page=route_objects&id=<?= $officeId ?>"
           class="btn btn-outline-secondary btn-sm">
            <i class="fa-solid fa-angles-left"></i>
        </a>

        <h5 class="mb-0"> Заявка: <?= htmlspecialchars($objName) ?></h5>

        <div class="btn-group">
            <button id="filterOrdered" class="btn btn-sm btn-outline-primary">
                ЗАЯВЕНИ
            </button>
            <button id="filterAll" class="btn btn-sm btn-primary">
                ВСИЧКИ
            </button>
        </div>

    </div>

    <?php if($isConfirmed): ?>

        <div class="alert alert-success text-center mb-0">
            Заявката е потвърдена
        </div>

    <?php endif; ?>

    <div class="card-body">

        <!-- SEARCH + PROMO -->
        <div class="d-flex gap-2 mb-1">

            <input type="text"
                   id="deliverySearch"
                   class="form-control form-control-sm py-2"
                   placeholder="ТЪРСИ ПО КОД ИЛИ ИМЕ...">


            <button id="newFilter"
                    class="btn btn-success d-inline-flex align-items-center gap-1">

                <i class="fa-solid fa-file-circle-plus"></i>
                <span>НОВО</span>

            </button>

            <button id="promoFilter"
                    class="btn btn-danger d-inline-flex align-items-center gap-1">
                <i class="fa-solid fa-percent"></i>
                <span>ПРОМО</span>
            </button>
        </div>

        <div class="text-center my-1">
            <div id="totalPriceBox" class="fw-bold fs-5 bg-success text-white py-1">
                Обща сума: 0.00 €
            </div>
        </div>

        <div class="list-group list-group-flush" id="itemsList">

            <?php

            $db = db_connect('storage');

            $sql = "
                SELECT
                    n.id,
                    UPPER(n.nom_code),
                    UPPER(n.name),
                    n.image,
                    COALESCE(UPPER(n.promo_note), '...'),
                    n.client_price,
                    n.sales_price,
                    n.is_calc,
                    n.unit,
                    n.is_new,
                    COALESCE(pe.count,0),
                    DATE_FORMAT(pe.updated_time,'%d.%m.%Y'),
                    COALESCE(oldpe.count,0),
                    DATE_FORMAT(oldpe.updated_time,'%d.%m.%Y'),
                    COALESCE(
                        (
                            SELECT SUM(pppe.`count`)
                            FROM ppp_elements pppe
                            INNER JOIN ppp ppp
                                ON ppp.id = pppe.id_ppp
                            WHERE pppe.id_nomenclature = n.id
                              AND DATE(ppp.source_date) = CURDATE()
                              AND ppp.status != 'cancel'
                        ),
                        0
                    )
                    AS qty_ordered
                FROM nomenclatures n
                LEFT JOIN ppp_elements pe ON pe.id_nomenclature = n.id AND pe.id_ppp = ?
                LEFT JOIN (
                        SELECT
                            pe1.id_nomenclature,
                            pe1.count,
                            pe1.updated_time
                        FROM ppp_elements pe1
                        JOIN ppp p1 ON p1.id = pe1.id_ppp
                        WHERE p1.id_dest = ?
                            AND pe1.updated_time =
                            (
                                SELECT MAX(pe2.updated_time)
                                FROM ppp_elements pe2
                                JOIN ppp p2 ON p2.id = pe2.id_ppp
                                WHERE p2.id_dest = ? AND pe2.id_nomenclature = pe1.id_nomenclature
                            )
                ) oldpe ON oldpe.id_nomenclature = n.id
                WHERE
                    n.to_arc = 0 AND n.is_calc > 0 AND n.client_price > 0
                ORDER BY n.name
                LIMIT 3000
";

            $stmt = $db->prepare($sql);
            $stmt->bind_param("iii",$pppID,$objectId,$objectId);
            $stmt->execute();

            $stmt->bind_result(
                $nID,
                $nCode,
                $nName,
                $nImage,
                $nPromoNote,
                $cPrice,
                $sPrice,
                $nCount,
                $nUnit,
                $nIsNew,
                $oQuantity,
                $lOrder,
                $oldQty,
                $oldOrderTime,
                $qtyOrdered
            );

            while($stmt->fetch()):

                $nID=(int)$nID;

                $sCode=htmlspecialchars($nCode);
                $sName=htmlspecialchars($nName);
                $hasImage = !empty($nImage) ? 1 : 0;

                $sPromoNote = htmlspecialchars($nPromoNote ?? '', ENT_QUOTES, 'UTF-8');
                $sUnit=htmlspecialchars($nUnit);
                $isNew = ((int)$nIsNew > 0) ? 1 : 0;

                $cPriceRaw=(float)$cPrice;
                $sPriceRaw=(float)$sPrice;

                $isPromo=$sPriceRaw>0?1:0;
                $nPriceRaw=$sPriceRaw>0?$sPriceRaw:$cPriceRaw;

                $inputValue=(int)$oQuantity;

                $qtyWarning = $nCount - $qtyOrdered;

                $strOrdered = '';
                if( $qtyWarning < 6 ) {
                    $strOrdered = '<span class="bg-warning fw-semibold text-dark rounded- px-1"> Поръчано: '. $qtyOrdered .' </span>';
                }

                $btnClass=$inputValue>0?'btn-success':'btn-danger';

                // Thumbnail for table view
                if ($hasImage) {
                    $thumb = '<div class="item-thumb bg-white text-danger text-center">
                                    <img src="includes/item_image_get.php?id='.$nID.'"
                                        style="max-height:40px;cursor:pointer"
                                        class="item-thumb"
                                        data-id="'.$nID.'"
                                        data-hasimage="1">
                              </div>';
                } else {
                    $thumb = '<div class="item-thumb bg-white text-danger text-center"
                                    style="width:auto;height:40px;line-height:40px;cursor:pointer"
                                    data-id="'.$nID.'"
                                    data-hasimage="0">-</div>';
                }

                ?>

                <div class="list-group-item d-flex justify-content-between align-items-center flex-wrap <?= $lockedClass ?>"
                     data-code="<?= $sCode ?>"
                     data-name="<?= $sName ?>"
                     data-promo="<?= $isPromo ?>"
                     data-new="<?= $isNew ?>"
                     data-ordered="<?= $inputValue > 0 ? 1 : 0 ?>">

                    <div class="flex-grow-1 d-flex align-items-start gap-2">

                        <div class="route-icon me-2"><?= $thumb ?></div>

                        <div class="flex-grow-1">

                            <div class="fw-semibold">
                                <?= $sCode ?> - <?= $sName ?>
                            </div>

                            <div class="small text-info">
                                Налично: <?= $nCount.' '.$sUnit .' '. $strOrdered ?>
                                / Цена: <?= number_format($cPriceRaw,2) ?>

                                <?php if($sPriceRaw>0): ?>
                                    <br/>
                                    <span class="alert bg-alert text-danger fw-semibold px-0">
                                        ПРОМО <?= number_format($sPriceRaw,2) ?> /<?= $sPromoNote ?>/</span>
                                <?php endif; ?>
                            </div>

                            <div class="small text-body-secondary">
                                Последна поръчка:
                                <?= $oldQty.' '.$sUnit ?> - <?= $oldOrderTime ?: '-' ?>
                            </div>

                        </div>

                    </div>

                    <div class="d-flex align-items-center gap-2">

                        <button class="btn btn-sm btn-outline-secondary qty-minus" <?= $disabledAttr ?>>
                            <i class="fa-solid fa-minus"></i>
                        </button>

                        <input type="number"
                               class="form-control form-control-sm py-2 qty-input"
                               value="<?= $inputValue ?>"
                               min="0"
                               max="1000"
                               data-saved="<?= $inputValue ?>"
                               data-price="<?= $nPriceRaw ?>"
                            <?= $disabledAttr ?>>

                        <button class="btn btn-sm btn-outline-secondary qty-plus" <?= $disabledAttr ?>>
                            <i class="fa-solid fa-plus"></i>
                        </button>

                        <button class="btn btn-sm <?= $btnClass ?> save-delivery"
                                data-ppp="<?= $pppID ?>"
                                data-id="<?= $nID ?>"
                                data-price="<?= $nPriceRaw ?>"
                            <?= $disabledAttr ?>>
                            <i class="fa-solid fa-circle-check"></i>
                        </button>

                    </div>

                </div>

            <?php
            endwhile; ?>

        </div>
    </div>
</div>


<!-- IMAGE MODAL -->
<div class="modal fade" id="imageModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">

            <div class="modal-header">
                <h5 class="modal-title">Снимка</h5>
                <button class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body text-center">
                <img id="itemImagePreview" class="img-fluid mb-3 d-none" style="max-height:80vh">
                <div id="noImageText" class="text-muted">Няма качена снимка</div>
            </div>
        </div>
    </div>
</div>
<script>

    const deliveryConfirmed = <?= $isConfirmed?'true':'false' ?>;

    let newActive = false;
    let promoActive = false;
    let showOnlyOrdered = false;

    /* FILTER */
    function applyFilters(){

        const search = ($('#deliverySearch').val() || '')
            .trim()
            .toUpperCase();

        $('#itemsList .list-group-item').each(function(){

            const code = ($(this).attr('data-code') || '').toUpperCase();
            const name = ($(this).attr('data-name') || '').toUpperCase();
            const isNew = parseInt($(this).attr('data-new')) || 0;
            const promo = parseInt($(this).attr('data-promo')) || 0;
            const ordered = parseInt($(this).attr('data-ordered')) || 0;

            let visible = true;

            // SEARCH
            if(search){
                if(code.indexOf(search) === -1 && name.indexOf(search) === -1)
                    visible=false;
            }


            // NEW
            if(newActive && isNew !== 1)
                visible=false;

            // PROMO
            if(promoActive && promo !== 1)
                visible=false;

            // ✅ NEW: ORDERED FILTER
            if(showOnlyOrdered && ordered !== 1)
                visible=false;

            $(this).toggleClass('d-none',!visible);

        });

    }

    $('#filterOrdered').on('click', function(){

        showOnlyOrdered = true;

        $(this)
            .removeClass('btn-outline-primary')
            .addClass('btn-primary');

        $('#filterAll')
            .removeClass('btn-primary')
            .addClass('btn-outline-primary');

        applyFilters();

    });

    $('#filterAll').on('click', function(){

        showOnlyOrdered = false;

        $(this)
            .removeClass('btn-outline-primary')
            .addClass('btn-primary');

        $('#filterOrdered')
            .removeClass('btn-primary')
            .addClass('btn-outline-primary');

        applyFilters();

    });

    let searchTimer;

    $('#deliverySearch').on('input',function(){

        clearTimeout(searchTimer);
        searchTimer=setTimeout(applyFilters,200);

    });

    $('#newFilter').on('click',function(){
        newActive=!newActive;
        $(this)
            .toggleClass('btn-success btn-secondary')
            .text(newActive?'ВСИЧКИ':'НОВО');
        applyFilters();
    });

    $('#promoFilter').on('click',function(){
        promoActive=!promoActive;
        $(this)
            .toggleClass('btn-danger btn-secondary')
            .text(promoActive?'ВСИЧКИ':'ПРОМОЦИИ');
        applyFilters();
    });

    /* QTY + */

    $(document).on('click','.qty-plus',function(){

        if(deliveryConfirmed) return;

        const input=$(this).siblings('.qty-input');

        let val=parseInt(input.val())||0;

        if(val<1000) input.val(val+1).trigger('input');

    });

    /* QTY - */

    $(document).on('click','.qty-minus',function(){

        if(deliveryConfirmed) return;

        const input=$(this).siblings('.qty-input');

        let val=parseInt(input.val())||0;

        if(val>0) input.val(val-1).trigger('input');

    });

    /* INPUT */

    $(document).on('input','.qty-input',function(){

        if(deliveryConfirmed) return;

        const btn=$(this).siblings('.save-delivery');


        const row = $(this).closest('.list-group-item');
        const val = parseInt($(this).val()) || 0;

        row.attr('data-ordered', val > 0 ? 1 : 0);


        const saved=parseInt($(this).data('saved'))||0;

        if(val > 0 && val === saved){
            btn.removeClass('btn-danger')
               .addClass('btn-success');
        } else {
            btn.removeClass('btn-success')
               .addClass('btn-danger');
        }

        calculateTotal();
    });

/* SAVE */
// извън функцията (глобално)
let resetTimer = null;

$(document).on('click','.save-delivery',function(){

    if(deliveryConfirmed){
        alert('Заявката е потвърдена.');
        return;
    }

    const btn=$(this);
    const row=btn.closest('.list-group-item');
    const input=row.find('.qty-input');

    const qty=parseInt(input.val())||0;

    const id_ppp=btn.data('ppp');
    const id_n=btn.data('id');
    const price=btn.data('price');

    //if(qty<=0) return;

    $.post('includes/save_ppp_element.php',{
        id_ppp:id_ppp,
        id_nomenclature:id_n,
        count:qty,
        single_price:price
    },function(resp){

        if(resp.success){

//             if(qty > 0){
//                 btn.removeClass('btn-danger')
//                    .addClass('btn-success');
//             }else{
//                 btn.removeClass('btn-success')
//                    .addClass('btn-danger');
//             }

            input.data('saved',qty);
            input.trigger('input');
            // ✅ RESET SEARCH + FILTERS
            $('#deliverySearch').val('');

            newActive = false;
            promoActive = false;

            $('#newFilter')
                .removeClass('btn-secondary')
                .addClass('btn-success')
                .text('НОВО');

            $('#promoFilter')
                .removeClass('btn-secondary')
                .addClass('btn-danger')
                .text('ПРОМОЦИИ');

            // ✅ DELAY FILTER
            clearTimeout(resetTimer);
            resetTimer = setTimeout(function(){
              //  applyFilters();
            }, 450); // можеш да го направиш 150–400ms

            calculateTotal();
            //row.attr('data-ordered', 1);
            row.attr('data-ordered', qty > 0 ? 1 : 0);
        }else{

            alert('Грешка');

        }

    },'json');

});


// =============================
// IMAGE MODAL STATE
// =============================
let currentItem = 0;
let imageModalInstance = null;


// =============================
// OPEN IMAGE MODAL
// =============================
$(document).on('click', '.item-thumb, .card-img-top', function(e){

    e.preventDefault();
    e.stopPropagation();

    currentItem = $(this).data('id');

    const raw = $(this).attr('data-hasimage');

    const modalEl = document.getElementById('imageModal');
    imageModalInstance = bootstrap.Modal.getOrCreateInstance(modalEl);

    const hasImage = parseInt(raw || 0, 10) === 1;

    //console.log('OPEN MODAL', { currentItem, raw });

    $('#itemImagePreview')
        .attr('src','')
        .addClass('d-none');

    $('#noImageText').addClass('d-none');
    $('#imageUpload').val('');

    imageModalInstance.show();

    if(hasImage){
        $('#itemImagePreview')
            .attr('src','includes/item_image_get.php?id='+currentItem+'&t='+Date.now())
            .on('load.modalFix', function(){
                $(this).removeClass('d-none');
                $('#deleteImage').removeClass('d-none');
            });
    } else {
        $('#noImageText').removeClass('d-none');
        $('#deleteImage').addClass('d-none');

    }
});

$('#imageModal').on('hidden.bs.modal', function () {
    $('#itemImagePreview').off('load.modalFix');
    $('#itemImagePreview').attr('src','');
});

    function calculateTotal(){

        let total = 0;

        $('.qty-input').each(function(){

            const qty = parseInt($(this).val()) || 0;
            const price = parseFloat($(this).data('price')) || 0;

            if(qty > 0){
                total += qty * price;
            }

        });

        $('#totalPriceBox').text(
            'Обща сума: ' + total.toFixed(2) + ' €'
        );
    }

    $(document).ready(function(){
        calculateTotal();
        applyFilters(); // 👈 добави това
    });

</script>