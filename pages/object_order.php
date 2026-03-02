<?php
include_once __DIR__ . '/../includes/functions.php';

if (empty($_SESSION['user_id'])) {
    echo '<div class="alert alert-danger">Нямате достъп.</div>';
    exit;
}

$idUser   = (int)$_SESSION['user_id'];
$objectId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$preparе = isset($_GET['preparе']) ? (int)$_GET['preparе'] : 0;
$pppID = isset($_GET['pppID']) ? (int)$_GET['pppID'] : null;


if ($objectId <= 0) {
    echo '<div class="alert alert-danger">Невалиден обект.</div>';
    exit;
}

if (!$pppID) {
    die('Грешка при определяне на pppID');
}

?>

<div class="card bg-dark text-white shadow">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Заявка за обект #<?= getObjectByID($objectId); ?></h5>
    </div>

    <div class="card-body">

        <!-- SEARCH -->
        <div class="row mb-3">
            <div class="col-12 d-flex gap-2">
                <a href="dashboard.php?page=orders"
                   class="btn btn-outline-light btn-sm">
                    <i class="fa-solid fa-arrow-left me-1"></i> BACK
                </a>
                <input type="text"
                       id="deliverySearch"
                       class="form-control form-control-sm"
                       style="text-transform: uppercase"
                       placeholder="ТЪРСИ ПО КОД ИЛИ ИМЕ...">

            </div>
        </div>

<?php
/* =====================================================
   3. Заявка за артикули
===================================================== */

$db = db_connect('storage');

$sql = "
    SELECT
        DATE_FORMAT(p.source_date, '%d.%m.%Y') AS lOrder,
        COALESCE(ROUND(pe.`count`,0),0) AS oQuantity,
        n.id AS nID,
        UPPER(n.nom_code) AS nCode,
        UPPER(n.name) AS nName,
        n.client_price AS cPrice,
        n.sales_price AS sPrice,
        n.is_calc AS nCount
    FROM ppp p
    JOIN ppp_elements pe ON p.id = pe.id_ppp AND pe.to_arc = 0
    LEFT JOIN nomenclatures n ON pe.id_nomenclature = n.id
    WHERE pe.id_ppp = ?
    ORDER BY n.name ASC
";

$stmt = $db->prepare($sql);
$stmt->bind_param("i", $pppID);
$stmt->execute();
$result = $stmt->get_result();

if (!$result || $result->num_rows === 0) {
    echo '<div class="alert alert-info">НЯМА НАМЕРЕНИ АРТИКУЛИ.</div>';
} else {

    while ($row = $result->fetch_assoc()) {

        $nID       = (int)$row['nID'];
        $sCode     = htmlspecialchars($row['nCode'] ?? '');
        $sName     = htmlspecialchars($row['nName'] ?? '');

        $cPriceRaw = (float)$row['cPrice'];
        $sPriceRaw = (float)$row['sPrice'];
        $isPromo = $sPriceRaw > 0 ? 1 : 0;

        $nPriceRaw = $sPriceRaw > 0 ? $sPriceRaw : $cPriceRaw;

        $cPriceFormatted = number_format($cPriceRaw, 2);
        $sPriceFormatted = number_format($sPriceRaw, 2);

        $nCount    = (int)$row['nCount'];
        $lOrder    = htmlspecialchars($row['lOrder'] ?? '-');

        $oQuantity = (int)$row['oQuantity'];
        $hasSaved  = $oQuantity > 0;

        $inputValue = $hasSaved ? $oQuantity : 0;

        $iconClass  = $hasSaved
            ? 'fa-check text-success'
            : 'fa-circle-check text-white';

        $btnStyle   = $hasSaved
            ? 'background-color:#16a34a; box-shadow:0 0 15px rgba(22,163,74,0.6);'
            : 'background-color: rgba(6, 182, 212, 0.125); box-shadow: rgba(6, 182, 212, 0.19) 0px 0px 20px;';

        $maxQty = max(1, min(100, $nCount));

    echo '
    <div data-slot="card" data-promo="'.$isPromo.'" role="button" data-page="route_objects" data-id="<?= $officeId ?>"
        class="text-card-foreground flex flex-col pt-3 gap-3 rounded-xl mb-1 shadow-sm relative overflow-hidden border-0 bg-zinc-900/50 backdrop-blur-sm transition-all duration-300 hover:scale-[1.02] hover:bg-zinc-900/70 cursor-pointer h-full"
        style="box-shadow: rgba(6, 182, 212, 0.125) 0px 0px 0px 1px, rgba(6, 182, 212, 0.063) 0px 4px 24px;"
        data-search="['.$sCode.'] - '.$sName.'">
        <div class="absolute inset-0 opacity-0 group-hover:opacity-100 transition-opacity duration-500"
        style="background: radial-gradient(circle at 50% 0%, rgba(6, 182, 212, 0.082) 0%, transparent 70%);"></div>
        <div data-slot="card-header" class="@container/card-header grid auto-rows-min grid-rows-[auto_auto] items-start gap-2 px-3 has-data-[slot=card-action]:grid-cols-[1fr_auto] [.border-b]:pb-1 relative z-10">
            <div class="flex items-start">
                <span class="m-1 p-3 inline rounded-xl transition-all duration-300 group-hover:scale-110" style="background-color: rgba(6, 182, 212, 0.125); box-shadow: rgba(6, 182, 212, 0.19) 0px 0px 20px;">
                    <i class="fa-solid fa-barcode fa-lg text-white"></i>
                </span>
                <div class="m-1 ms-3 p-0 text-start">
                    <a href="dashboard.php?page=delivery_request&id=<?= $oID ?>&office_id=<?= $officeId ?>"
                       class="d-block text-decoration-none text-white">

                        <div class="fw-semibold">
                            [ '.$sCode.' ] - '.$sName.' -
                        </div>

                        <div class="text-secondary small">
                            Налично ('.$nCount.') / Цена: '.$cPriceFormatted.' '.$sPriceFormatted.'
                        </div>
                    </a>
                </div>

                <div class="ms-auto me-3 my-auto">
                   <div class="qty-wrapper d-flex align-items-center">

                       <button type="button" class="qty-btn btn-minus">
                           <i class="fa-solid fa-minus fa-2x"></i>
                       </button>

                       <input type="number"
                              class="qty-input fs-2"
                              value="'.$inputValue.'"
                              min="1"
                              max="">

                       <button type="button" class="qty-btn btn-plus">
                           <i class="fa-solid fa-plus fa-2x"></i>
                       </button>

                   </div>
                </div>
                <span class="my-1 p-3 inline rounded-xl save-delivery"
                      style="'.$btnStyle.'"
                      data-ppp="'. $pppID.'"
                      data-id="'. $nID.'"
                      data-price="'. $nPriceRaw.'">

                    <i class="fa-solid '.$iconClass.' fa-2x text-white m-1"></i>

                </span>
            </div>
        </div>
    </div>';


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

// ===================== QTY PLUS =====================
$(document).off('click', '.btn-plus').on('click', '.btn-plus', function() {
    const input = $(this).closest('.qty-wrapper').find('.qty-input');
    const max = parseInt(input.attr('max'));
    let value = parseInt(input.val()) || 0;
    if (value < max) input.val(value + 1);
});

// ===================== QTY MINUS =====================
$(document).off('click', '.btn-minus').on('click', '.btn-minus', function() {
    const input = $(this).closest('.qty-wrapper').find('.qty-input');
    let value = parseInt(input.val()) || 0;
    if (value > 0) input.val(value - 1);
});

// ===================== MANUAL LIMIT =====================
$(document).off('input', '.qty-input').on('input', '.qty-input', function() {
    const max = parseInt($(this).attr('max'));
    let value = parseInt($(this).val()) || 1;
    if (value < 1) value = 1;
    if (value > max) value = max;
    $(this).val(value);
});

// ===================== SEND QTY =====================
$(document).off('click', '.ajax-delivery-request').on('click', '.ajax-delivery-request', function() {
    const qty = $(this).closest('.row').find('.qty-input').val();
    $(this).attr('data-qty', qty);
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

                btn.css({
                    backgroundColor: '#16a34a',
                    boxShadow: '0 0 15px rgba(22,163,74,0.6)'
                });

                btn.find('i')
                   .removeClass('fa-circle-check')
                   .addClass('fa-check');

            } else {
                alert('Грешка: ' + response.message);
            }
        }
    });

});
</script>