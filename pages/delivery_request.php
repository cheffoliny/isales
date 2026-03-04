<?php
include_once __DIR__ . '/../includes/functions.php';

if (empty($_SESSION['user_id'])) {
    echo '<div class="alert alert-danger">Нямате достъп.</div>';
    exit;
}

$idUser   = (int)$_SESSION['user_id'];
$officeId = isset($_GET['office_id']) ? (int)$_GET['office_id'] : 0;
$objectId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($objectId <= 0) {
    echo '<div class="alert alert-danger">Невалиден обект.</div>';
    exit;
}

$db_storage = db_connect('storage');
$today = date('Y-m-d');

/* ================== CHECK / CREATE PPP ================== */
$checkSql = "SELECT id FROM ppp
             WHERE id_dest = ?
               AND dest_type = 'object'
               AND DATE(source_date) = ?
             LIMIT 1";

$stmt = $db_storage->prepare($checkSql);
$stmt->bind_param("is", $objectId, $today);
$stmt->execute();
$stmt->bind_result($existingID);
$stmt->fetch();
$stmt->close();

if ($existingID) {
    $pppID = (int)$existingID;
} else {
    $sourceUser = trim($_SESSION['first_name'] . ' ' . $_SESSION['last_name']);
    $status = 'open';

    $insertSql = "INSERT INTO ppp
        (`status`, `source_date`, `source_user`, `source_type`, `id_source`, `dest_type`, `id_dest`)
        VALUES (?, NOW(), ?, 'storagehouse', 1, 'object', ?)";

    $stmt = $db_storage->prepare($insertSql);
    $stmt->bind_param("ssi", $status, $sourceUser, $objectId);
    $stmt->execute();
    $pppID = $db_storage->insert_id;
    $stmt->close();
}

if (!$pppID) die('Грешка при определяне на pppID');

$objName = getObjectByID($objectId);
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

    <div class="card-body">

        <!-- SEARCH + PROMO -->
        <div class="d-flex gap-2 mb-3 align-items-center">
            <input type="text"
                   id="deliverySearch"
                   class="form-control form-control-sm flex-grow-1"
                   placeholder="ТЪРСИ ПО КОД ИЛИ ИМЕ...">

            <button id="promoFilter"
                    class="btn btn-sm btn-danger">
                ПРОМОЦИИ
            </button>
        </div>

        <!-- ITEMS -->
        <div class="list-group list-group-flush" id="itemsList">

<?php
$db = db_connect('storage');

$sql = "
SELECT
    DATE_FORMAT(pe.updated_time, '%d.%m.%Y') AS lOrder,
    COALESCE(ROUND(pe.`count`,0),0) AS oQuantity,
    n.id AS nID,
    UPPER(n.nom_code) AS nCode,
    UPPER(n.name) AS nName,
    n.client_price AS cPrice,
    n.sales_price AS sPrice,
    n.is_calc AS nCount
FROM nomenclatures n
LEFT JOIN ppp_elements pe
    ON pe.id_nomenclature = n.id
    AND pe.id_ppp = ?
WHERE n.to_arc = 0
  AND n.is_calc > 0
  AND n.client_price > 0
ORDER BY n.name ASC
LIMIT 20
";

$stmt = $db->prepare($sql);
$stmt->bind_param("i", $pppID);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows === 0) {
    echo '<div class="alert alert-info">НЯМА НАМЕРЕНИ АРТИКУЛИ.</div>';
} else {

    $stmt->bind_result(
        $lOrder,
        $oQuantity,
        $nID,
        $nCode,
        $nName,
        $cPrice,
        $sPrice,
        $nCount
    );

    while ($stmt->fetch()):

        $nID       = (int)$nID;
        $sCode     = htmlspecialchars($nCode ?? '');
        $sName     = htmlspecialchars($nName ?? '');
        $cPriceRaw = (float)$cPrice;
        $sPriceRaw = (float)$sPrice;
        $isPromo   = $sPriceRaw > 0 ? 1 : 0;
        $nPriceRaw = $sPriceRaw > 0 ? $sPriceRaw : $cPriceRaw;

        $cPriceFormatted = number_format($cPriceRaw, 2);
        $sPriceFormatted = number_format($sPriceRaw, 2);

        $nCount     = (int)$nCount;
        $lOrder     = $lOrder ? date('d.m.Y', strtotime($lOrder)) : '-';
        $oQuantity  = (int)$oQuantity;

        $hasSaved   = $oQuantity > 0;
        $inputValue = $hasSaved ? $oQuantity : 0;

        $btnClass   = $hasSaved ? 'btn-success' : 'btn-secondary';
?>

            <div class="list-group-item d-flex justify-content-between align-items-center flex-wrap"
                 data-code="<?= $sCode ?>"
                 data-name="<?= $sName ?>"
                 data-promo="<?= $isPromo ?>">

                <div class="flex-grow-1">

                    <div class="fw-semibold">
                        <?= $sCode ?> - <?= $sName ?>
                    </div>

                    <div class="small text-body-secondary">
                        Налично: <?= $nCount ?> / Цена: <?= $cPriceFormatted ?>

                        <?php if($sPriceRaw > 0): ?>
                            <span class="badge bg-danger">
                                ПРОМО: <?= $sPriceFormatted ?>
                            </span>
                        <?php endif; ?>
                    </div>

                    <div class="small text-body-secondary">
                        Последна поръчка: <?= $oQuantity ?> - <?= $lOrder ?>
                    </div>
                </div>

                <div class="d-flex align-items-center gap-2">

                    <button class="btn btn-sm btn-outline-secondary qty-minus">
                        <i class="fa-solid fa-minus"></i>
                    </button>

                    <input type="number"
                           class="form-control form-control-sm qty-input w-50"
                           value="<?= $inputValue ?>"
                           min="0"
                           max="1000"
                           data-saved="<?= $inputValue ?>">

                    <button class="btn btn-sm btn-outline-secondary qty-plus">
                        <i class="fa-solid fa-plus"></i>
                    </button>

                    <button class="btn btn-sm <?= $btnClass ?> save-delivery"
                            data-ppp="<?= $pppID ?>"
                            data-id="<?= $nID ?>"
                            data-price="<?= $nPriceRaw ?>">
                        <i class="fa-solid fa-circle-check fa-1x"></i>
                    </button>

                </div>
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

<script>
let promoActive = false;

/* ================= FILTER ================= */
function applyFilters() {
    const searchValue = ($('#deliverySearch').val() || '').toUpperCase().trim();

    $('#itemsList .list-group-item').each(function() {

        const code = ($(this).data('code') || '').toString().toUpperCase();
        const name = ($(this).data('name') || '').toString().toUpperCase();
        const isPromo = $(this).data('promo') == 1;

        let visible = true;

        if (searchValue &&
            !code.includes(searchValue) &&
            !name.includes(searchValue)) {
            visible = false;
        }

        if (promoActive && !isPromo) {
            visible = false;
        }

        $(this).toggleClass('d-none', !visible);
    });
}

/* SEARCH */
$('#deliverySearch').on('input', function() {
    applyFilters();
});

/* PROMO BUTTON */
$('#promoFilter').on('click', function() {
    promoActive = !promoActive;

    $(this)
        .toggleClass('btn-danger btn-secondary')
        .text(promoActive ? 'ВСИЧКИ' : 'ПРОМОЦИИ');

    applyFilters();
});

/* QTY PLUS */
$(document).on('click', '.qty-plus', function(){
    const input = $(this).siblings('.qty-input');
    let val = parseInt(input.val()) || 1;
    let max = parseInt(input.attr('max')) || 1000;
    if(val < max) input.val(val+1).trigger('input');
});

/* QTY MINUS */
$(document).on('click', '.qty-minus', function(){
    const input = $(this).siblings('.qty-input');
    let val = parseInt(input.val()) || 0;
    if(val > 0) input.val(val-1).trigger('input');
});

/* INPUT CHANGE */
$(document).on('input', '.qty-input', function(){

    let val = parseInt($(this).val()) || 0;
    let max = parseInt($(this).attr('max')) || 1000;

    if(val < 0) val = 0;
    if(val > max) val = max;

    $(this).val(val);

    const btn = $(this).siblings('.save-delivery');
    const saved = parseInt($(this).data('saved') || 0);

    if(val !== saved){
        btn.removeClass('btn-success')
           .addClass('btn-secondary');

//         btn.find('i')
//            .removeClass('fa-check text-success')
//            .addClass('fa-circle-check text-secondary');
    } else {
        btn.removeClass('btn-secondary')
           .addClass('btn-success');

//         btn.find('i')
//            .removeClass('fa-circle-check text-secondary')
//            .addClass('fa-check text-success');
    }
});

/* SAVE */
$(document).on('click', '.save-delivery', function(){

    const btn = $(this);
    const row = btn.closest('.list-group-item');
    const input = row.find('.qty-input');

    const qty = parseInt(input.val()) || 1;
    const id_ppp = btn.data('ppp');
    const id_n = btn.data('id');
    const price = btn.data('price');

    if(!id_ppp || !id_n || qty <= 0){
        alert('Липсват данни!');
        return;
    }

    $.post('includes/save_ppp_element.php', {
        id_ppp: id_ppp,
        id_nomenclature: id_n,
        count: qty,
        single_price: price
    }, function(resp){

        if(resp.success){

            input.data('saved', qty);

            btn.removeClass('btn-secondary')
               .addClass('btn-success');

//             btn.find('i')
//                .removeClass('fa-circle-check text-secondary')
//                .addClass('fa-check text-success');

        } else {
            alert('Грешка: ' + resp.message);
        }

    }, 'json');
});
</script>