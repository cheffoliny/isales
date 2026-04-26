<?php
include_once __DIR__ . '/../includes/functions.php';

if (empty($_SESSION['user_id'])) {
    echo '<div class="alert alert-danger">Нямате достъп.</div>';
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

    $sourceUser = $firstName.' '. $lastName;
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

        <h5 class="mb-0">
            Заявка за: <?= htmlspecialchars($objName) ?>
        </h5>

    </div>

    <?php if($isConfirmed): ?>

        <div class="alert alert-success text-center mb-0">
            Заявката е потвърдена
        </div>

    <?php endif; ?>

    <div class="card-body">

        <!-- SEARCH + PROMO -->

        <div class="d-flex gap-2 mb-3">

            <input type="text"
                   id="deliverySearch"
                   class="form-control form-control-sm py-2"
                   placeholder="ТЪРСИ ПО КОД ИЛИ ИМЕ...">

            <button id="promoFilter"
                    class="btn btn-sm btn-danger">
                ПРОМОЦИИ
            </button>

        </div>

        <div class="list-group list-group-flush" id="itemsList">

            <?php

            $db = db_connect('storage');

            $sql = "
                SELECT
                    n.id,
                    UPPER(n.nom_code),
                    UPPER(n.name),
                    COALESCE(UPPER(n.promo_note), '...'),
                    n.client_price,
                    n.sales_price,
                    n.is_calc,
                    n.unit,
                    COALESCE(pe.count,0),
                    DATE_FORMAT(pe.updated_time,'%d.%m.%Y'),
                    COALESCE(oldpe.count,0),
                    DATE_FORMAT(oldpe.updated_time,'%d.%m.%Y')
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
                $nPromoNote,
                $cPrice,
                $sPrice,
                $nCount,
                $nUnit,
                $oQuantity,
                $lOrder,
                $oldQty,
                $oldOrderTime
            );

            while($stmt->fetch()):

                $nID=(int)$nID;

                $sCode=htmlspecialchars($nCode);
                $sName=htmlspecialchars($nName);
                $sPromoNote = htmlspecialchars($nPromoNote ?? '', ENT_QUOTES, 'UTF-8');
                $sUnit=htmlspecialchars($nUnit);

                $cPriceRaw=(float)$cPrice;
                $sPriceRaw=(float)$sPrice;

                $isPromo=$sPriceRaw>0?1:0;
                $nPriceRaw=$sPriceRaw>0?$sPriceRaw:$cPriceRaw;

                $inputValue=(int)$oQuantity;

                $btnClass=$inputValue>0?'btn-success':'btn-secondary';

                ?>

                <div class="list-group-item d-flex justify-content-between align-items-center flex-wrap <?= $lockedClass ?>"
                     data-code="<?= $sCode ?>"
                     data-name="<?= $sName ?>"
                     data-promo="<?= $isPromo ?>">

                    <div class="flex-grow-1">

                        <div class="fw-semibold">
                            <?= $sCode ?> - <?= $sName ?>
                        </div>

                        <div class="small text-info">
                            Налично: <?= $nCount.' '.$sUnit ?>
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

            <?php endwhile; ?>

        </div>
    </div>
</div>

<script>

    const deliveryConfirmed = <?= $isConfirmed?'true':'false' ?>;

    let promoActive=false;

    /* FILTER */

    function applyFilters(){

        const search = ($('#deliverySearch').val() || '')
            .trim()
            .toUpperCase();

        $('#itemsList .list-group-item').each(function(){

            const code = ($(this).attr('data-code') || '').toUpperCase();
            const name = ($(this).attr('data-name') || '').toUpperCase();
            const promo = parseInt($(this).attr('data-promo')) || 0;

            let visible = true;

            if(search){
                if(code.indexOf(search) === -1 && name.indexOf(search) === -1)
                    visible=false;
            }

            if(promoActive && promo !== 1)
                visible=false;

            $(this).toggleClass('d-none',!visible);

        });

    }

    let searchTimer;

    $('#deliverySearch').on('input',function(){

        clearTimeout(searchTimer);
        searchTimer=setTimeout(applyFilters,200);

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

        const val=parseInt($(this).val())||0;
        const saved=parseInt($(this).data('saved'))||0;

        if(val!==saved){

            btn.removeClass('btn-success')
                .addClass('btn-secondary');

        }else{

            btn.removeClass('btn-secondary')
                .addClass('btn-success');

        }

    });

    /* SAVE */

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

        if(qty<=0) return;

        $.post('includes/save_ppp_element.php',{

            id_ppp:id_ppp,
            id_nomenclature:id_n,
            count:qty,
            single_price:price

        },function(resp){

            if(resp.success){

                input.data('saved',qty);

                btn.removeClass('btn-secondary')
                    .addClass('btn-success');

            }else{

                alert('Грешка');

            }

        },'json');

    });

</script>