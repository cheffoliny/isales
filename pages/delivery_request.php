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

/* =====================================================
   1. Проверка за съществуващ запис днес
===================================================== */

$checkSql = "
    SELECT id
    FROM ppp
    WHERE id_dest = ?
      AND dest_type = 'object'
      AND DATE(source_date) = ?
    LIMIT 1
";

$stmt = $db_storage->prepare($checkSql);
$stmt->bind_param("is", $objectId, $today);
$stmt->execute();
$stmt->bind_result($existingID);
$stmt->fetch();
$stmt->close();

if ($existingID) {
    $pppID = (int)$existingID;
} else {

    /* =====================================================
       2. Създаване на нов запис
    ===================================================== */

    $sourceUser = trim($_SESSION['first_name'] . ' ' . $_SESSION['last_name']);
    $status = 'open';

    $insertSql = "
        INSERT INTO ppp
        (`status`, `source_date`, `source_user`, `source_type`, `id_source`, `dest_type`, `id_dest`)
        VALUES (?, NOW(), ?, 'storagehouse', 1, 'object', ?)
    ";

    $stmt = $db_storage->prepare($insertSql);

    if (!$stmt) {
        die('Prepare error: ' . $db_storage->error);
    }

    $stmt->bind_param("ssi", $status, $sourceUser, $objectId);

    if (!$stmt->execute()) {
        die('Insert error: ' . $stmt->error);
    }

    $pppID = $db_storage->insert_id;
    $stmt->close();
}

if (!$pppID) {
    die('Грешка при определяне на pppID');
}
//$objName->getObjectByID();
?>

<div class="card bg-dark text-white shadow">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Заявка за обект #<?= getObjectByID($objectId); ?></h5>
    </div>

    <div class="card-body">

        <!-- SEARCH -->
        <div class="row mb-3">
            <div class="col-12 d-flex gap-2">
                <a href="dashboard.php?page=route_objects&id=<?= $officeId ?>"
                   class="btn btn-outline-light btn-sm">
                    <i class="fa-solid fa-arrow-left me-1"></i> BACK
                </a>
                <input type="text"
                       id="deliverySearch"
                       class="form-control form-control-sm"
                       style="text-transform: uppercase"
                       placeholder="ТЪРСИ ПО КОД ИЛИ ИМЕ...">

                <button id="promoFilter" class="btn btn-sm btn-danger">
                    ПРОМОЦИИ
                </button>


            </div>
        </div>

<?php
/* =====================================================
   3. Заявка за артикули
===================================================== */

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
    LEFT JOIN ppp_elements pe ON pe.id_nomenclature = n.id AND pe.id_ppp = ?
    WHERE n.to_arc = 0
      AND n.is_calc > 0 AND n.client_price > 0
    ORDER BY n.name ASC
    LIMIT 500
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

    while ($stmt->fetch()) {

        $nID       = (int)$nID;
        $sCode     = htmlspecialchars($nCode ?? '');
        $sName     = htmlspecialchars($nName ?? '');

        $cPriceRaw = (float)$cPrice;
        $sPriceRaw = (float)$sPrice;
        $isPromo   = $sPriceRaw > 0 ? 1 : 0;

        $nPriceRaw = $sPriceRaw > 0 ? $sPriceRaw : $cPriceRaw;

        $cPriceFormatted = number_format($cPriceRaw, 2);
        $sPriceFormatted = number_format($sPriceRaw, 2);

        $nCount    = (int)$nCount;
        $lOrder    = $lOrder ? date('d.m.Y', strtotime($lOrder)) : '-';

        $oQuantity = (int)$oQuantity;
        $hasSaved  = $oQuantity > 0;

        $inputValue = $hasSaved ? $oQuantity : 0;

        $iconClass  = $hasSaved
            ? 'fa-check text-success'
            : 'fa-circle-check text-white';

        $maxQty = max(1, min(100, $nCount));

?>
    <div data-slot="card" data-promo="<?= $isPromo ?>" role="button" data-page="route_objects" data-id="<?= $officeId ?>"
        class="text-card-foreground flex flex-col pt-3 gap-3 rounded-xl mb-1 shadow-sm relative overflow-hidden border-0 bg-zinc-900/50 backdrop-blur-sm"

        data-code="<?= $sCode ?>"
        data-name="<?= $sName ?>">

        <div data-slot="card-header" class="@container/card-header grid auto-rows-min grid-rows-[auto_auto] items-start gap-2 px-3 has-data-[slot=card-action]:grid-cols-[1fr_auto] [.border-b]:pb-1 relative z-10">
            <div class="row align-items-center">

                <!-- ЛЯВА ЧАСТ -->
                <div class="col-12 col-lg-8 d-flex">

                    <div class="m-1 ms-3 p-0 text-start text-decoration-none text-white">
                        <div class="fs-6 fw-semibold">
                            [ <?= $sCode ?> ] - <?= $sName ?>
                        </div>

                        <div class="text-info">
                            Налично (<?= $nCount ?>) / Цена: <?= $cPriceFormatted ?>
                        <?php
                            if($sPriceFormatted > 0) {
                                echo ' / <span class="fs-6 text-white bg-danger ps-1 pe-4 mx-0">Промо: '.$sPriceFormatted.'</span>';
                            }
                        ?>
                        </div>
                        <div class="text-dark bg-info">
                            Последна поръчка [<?= $oQuantity ?>] - <?= $lOrder ?>
                        </div>
                    </div>
                </div>

                <!-- ДЯСНА ЧАСТ -->
                <div class="col-12 col-lg-4">

                    <div class="d-flex justify-content-lg-end align-items-center flex-wrap gap-2">

                        <div class="qty-wrapper d-flex align-items-center">

                            <button type="button" class="qty-btn btn-minus">
                                <i class="fa-solid fa-minus"></i>
                            </button>

                            <input type="number"
                                   class="qty-input fs-4"
                                   value="<?= $inputValue ?>"
                                   data-saved="<?= $inputValue ?>"
                                   min="1"
                                   max="<?= $maxQty ?>">

                            <button type="button" class="qty-btn btn-plus">
                                <i class="fa-solid fa-plus"></i>
                            </button>

                        </div>

                        <div class="my-1 p-3 rounded-xl save-delivery <?= $hasSaved ? 'saved' : 'not-saved' ?>"
                              data-ppp="<?= $pppID ?>"
                              data-id="<?= $nID ?>"
                              data-price="<?= $nPriceRaw ?>">

                            <i class="fa-solid <?= $iconClass ?> fa-lg text-white m-1"></i>

                        </div>
                    </div>
            </div>
        </div>


    </div>
<?php

    }
}

$stmt->close();
$db->close();
?>

    </div>
</div>

<script>
// ===================== SEARCH =====================
// ===================== FILTER SYSTEM =====================
let promoActive = false;

function applyFilters() {

    const searchValue = $('#deliverySearch').val().toUpperCase().trim();

    $('.text-card-foreground').each(function() {

        const searchText = ($(this).data('search') || '').toUpperCase();
        const isPromo = $(this).data('promo') == 1;

        let visible = true;

        // search filter
        if (searchValue && !searchText.includes(searchValue)) {
            visible = false;
        }

        // promo filter
        if (promoActive && !isPromo) {
            visible = false;
        }

        $(this).toggle(visible);
    });
}

// SEARCH
$(document).off('input', '#deliverySearch').on('input', '#deliverySearch', function() {
    this.value = this.value.toUpperCase();
    applyFilters();
});

// PROMO BUTTON
$(document).off('click', '#promoFilter').on('click', '#promoFilter', function() {

    promoActive = !promoActive;

    $(this).toggleClass('btn-danger btn-info')
           .text(promoActive ? 'ВСИЧКИ' : 'ПРОМОЦИИ');

    applyFilters();
});
// $(document).off('input', '#deliverySearch').on('input', '#deliverySearch', function() {
//     const value = this.value.toUpperCase().trim();
//
//     // запазваме във формата на input
//     this.value = value;
//
//     // филтрираме всички карти
//     $('.text-card-foreground').each(function() {
//         const searchText = $(this).data('search')?.toUpperCase() || '';
//         if (searchText.includes(value)) {
//             $(this).show();
//         } else {
//             $(this).hide();
//         }
//     });
// });
function markAsSaved(btn) {

    btn.removeClass('not-saved').addClass('saved');

    btn.find('i')
       .removeClass('fa-circle-check')
       .addClass('fa-check');
}

function markAsUnsaved(input) {

    const wrapper = input.closest('.flex');
    const btn = wrapper.find('.save-delivery');

    btn.removeClass('saved').addClass('not-saved');

    btn.find('i')
       .removeClass('fa-check')
       .addClass('fa-circle-check');
}

// ===================== QTY PLUS =====================
$(document).off('click', '.btn-plus').on('click', '.btn-plus', function() {

    const input = $(this).closest('.qty-wrapper').find('.qty-input');
    const max = parseInt(input.attr('max'));
    let value = parseInt(input.val()) || 0;

    if (value < max) {
        value++;
        input.val(value);
    }

    const saved = parseInt(input.data('saved')) || 0;

    if (value !== saved) {
        markAsUnsaved(input);
    }
});

// ===================== QTY MINUS =====================
$(document).off('click', '.btn-minus').on('click', '.btn-minus', function() {

    const input = $(this).closest('.qty-wrapper').find('.qty-input');
    let value = parseInt(input.val()) || 0;

    if (value > 0) {
        value--;
        input.val(value);
    }

    const saved = parseInt(input.data('saved')) || 0;

    if (value !== saved) {
        markAsUnsaved(input);
    }
});

// ===================== MANUAL LIMIT =====================
$(document).off('input', '.qty-input').on('input', '.qty-input', function() {

    const max = parseInt($(this).attr('max'));
    let value = parseInt($(this).val()) || 1;

    if (value < 1) value = 1;
    if (value > max) value = max;

    $(this).val(value);

    const saved = parseInt($(this).data('saved')) || 0;

    if (value !== saved) {
        markAsUnsaved($(this));
    }
});


$(document).on('click', '.save-delivery', function () {

    const btn = $(this);

    // намираме най-близкия qty-wrapper
    const wrapper = btn.closest('.flex').find('.qty-wrapper');
    const qty = wrapper.find('.qty-input').val();

    const id_ppp = btn.data('ppp');
    const id_nomenclature = btn.data('id');
    const single_price = btn.data('price');

    console.log({
        id_ppp,
        id_nomenclature,
        qty,
        single_price
    });

    if (!id_ppp || !id_nomenclature || !qty || qty <= 0) {
        alert('Липсват данни!');
        return;
    }

    $.ajax({
        url: 'includes/save_ppp_element.php',
        method: 'POST',
        dataType: 'json',
        data: {
            id_ppp: id_ppp,
            id_nomenclature: id_nomenclature,
            count: qty,
            single_price: single_price
        },
        success: function (response) {

            if (response.success) {

                wrapper.find('.qty-input').data('saved', parseInt(qty));

                markAsSaved(btn);

            } else {
                alert('Грешка: ' + response.message);
            }
        }
    });

});
</script>