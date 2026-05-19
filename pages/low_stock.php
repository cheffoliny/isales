<?php
include_once __DIR__.'/../includes/functions.php';

if (empty($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$db = db_connect('storage');
$filter = $_GET['filter'] ?? 'all';

$havingExtra = '';

if ($filter === 'ordered') {
    $havingExtra = ' AND qty_ordered > 0 ';
}

$sql = "
    SELECT
        n.id,
        UPPER(n.nom_code) AS nom_code,
        UPPER(n.name) AS name,
        n.image,
        n.is_calc,
        n.unit,
        n.client_price,

        COALESCE(
            (
                SELECT SUM(pe.count)
                FROM ppp_elements pe
                INNER JOIN ppp p ON p.id = pe.id_ppp
                WHERE pe.id_nomenclature = n.id
                  AND DATE(p.source_date) = CURDATE()
                  AND n.updated_time != '0000-00-00 00:00:00'
                  AND pe.updated_time > n.updated_time
                  AND p.status != 'cancel'
            ),
            0
        ) AS qty_ordered

    FROM nomenclatures n

    WHERE n.to_arc = 0
      AND n.is_calc > 0
      AND n.client_price > 0

    HAVING (is_calc - qty_ordered) < 6
    {$havingExtra}

    ORDER BY (is_calc - qty_ordered) ASC, name ASC
";

$stmt = $db->prepare($sql);
$stmt->execute();

$stmt->bind_result(
    $nID,
    $nCode,
    $nName,
    $nImage,
    $nCount,
    $nUnit,
    $clientPrice,
    $qtyOrdered
);
?>

<div class="card shadow mb-3 border-0">

    <div class="card-header d-flex justify-content-between align-items-center">

        <a href="dashboard.php?page=routes"
           class="btn btn-outline-secondary btn-sm">
            <i class="fa-solid fa-angles-left"></i>
        </a>

        <h5 class="mb-0 text-danger">
            <i class="fa-solid fa-triangle-exclamation"></i>
            Критична наличност
        </h5>

        <div class="btn-group">

            <a href="dashboard.php?page=low_stock&filter=ordered"
               class="btn btn-sm <?= $filter === 'ordered'
                   ? 'btn-danger'
                   : 'btn-outline-danger' ?>">
                ЗАЯВЕНО
            </a>

            <a href="dashboard.php?page=low_stock&filter=all"
               class="btn btn-sm <?= $filter === 'all'
                   ? 'btn-primary'
                   : 'btn-outline-primary' ?>">
                ВСИЧКИ
            </a>

        </div>
    </div>

    <div class="card-body">

        <div class="mb-2">
            <input type="text"
                   id="stockSearch"
                   class="form-control form-control-sm py-2"
                   placeholder="ТЪРСИ ПО КОД ИЛИ ИМЕ...">
        </div>

        <div class="list-group list-group-flush" id="itemsList">

            <?php
            $hasRows = false;

            while ($stmt->fetch()):

                $hasRows = true;

                $nID = (int)$nID;

                $sCode = htmlspecialchars($nCode);
                $sName = htmlspecialchars($nName);
                $sUnit = htmlspecialchars($nUnit);

                $hasImage = !empty($nImage);

                $qtyOrdered = (float)$qtyOrdered;
                $nCount = (float)$nCount;

                $remaining = $nCount - $qtyOrdered;

                if ($remaining <= 0) {
                    $badgeClass = 'bg-danger';
                } elseif ($remaining <= 2) {
                    $badgeClass = 'bg-warning text-dark';
                } else {
                    $badgeClass = 'bg-info text-dark';
                }

                if ($hasImage) {

                    $thumb = '
                        <img src="includes/item_image_get.php?id=' . $nID . '"
                             style="max-height:40px;cursor:pointer"
                             class="item-thumb rounded"
                             data-id="' . $nID . '"
                             data-hasimage="1">
                    ';

                } else {

                    $thumb = '
                        <div class="bg-light border rounded text-center"
                             style="width:40px;height:40px;line-height:40px;">
                            -
                        </div>
                    ';
                }
            ?>

                <div class="list-group-item"
                     data-code="<?= $sCode ?>"
                     data-name="<?= $sName ?>">

                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">

                        <div class="d-flex align-items-start gap-3 flex-grow-1">

                            <div>
                                <?= $thumb ?>
                            </div>

                            <div>

                                <div class="fw-semibold">
                                    <?= $sCode ?> - <?= $sName ?>
                                </div>

                                <div class="small text-secondary">

                                    Наличност:
                                    <span class="fw-semibold">
                                        <?= number_format($nCount, 0) . ' ' . $sUnit ?>
                                    </span>

                                    |

                                    Поръчано днес:
                                    <span class="fw-semibold text-danger">
                                        <?= number_format($qtyOrdered, 0) . ' ' . $sUnit ?>
                                    </span>

                                </div>

                            </div>

                        </div>

                        <div>

                            <span class="badge <?= $badgeClass ?>">
                                Остатък: <?= number_format($remaining, 0) ?>
                            </span>

                        </div>

                    </div>

                </div>

            <?php endwhile; ?>

            <?php if (!$hasRows): ?>

                <div class="alert alert-success text-center mb-0">
                    Няма артикули с критична наличност.
                </div>

            <?php endif; ?>

        </div>

    </div>

</div>

<!-- IMAGE MODAL -->
<div class="modal fade" id="imageModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">

            <div class="modal-header">
                <h5 class="modal-title">Снимка</h5>

                <button class="btn-close"
                        data-bs-dismiss="modal"
                        aria-label="Close"></button>
            </div>

            <div class="modal-body text-center">

                <img id="itemImagePreview"
                     class="img-fluid d-none"
                     style="max-height:80vh">

                <div id="noImageText"
                     class="text-muted">
                    Няма качена снимка
                </div>

            </div>

        </div>
    </div>
</div>

<script>

let searchTimer = null;

/* SEARCH */

function applyFilters(){

    const search = ($('#stockSearch').val() || '')
        .trim()
        .toUpperCase();

    $('#itemsList .list-group-item').each(function(){

        const code = ($(this).attr('data-code') || '').toUpperCase();
        const name = ($(this).attr('data-name') || '').toUpperCase();

        let visible = true;

        if(search){

            if(
                code.indexOf(search) === -1 &&
                name.indexOf(search) === -1
            ){
                visible = false;
            }
        }

        $(this).toggleClass('d-none', !visible);

    });

}

$('#stockSearch').on('input', function(){

    clearTimeout(searchTimer);

    searchTimer = setTimeout(function(){
        applyFilters();
    }, 200);

});


/* IMAGE MODAL */

$(document).on('click', '.item-thumb', function(e){

    e.preventDefault();
    e.stopPropagation();

    const itemID = $(this).data('id');
    const hasImage = parseInt($(this).attr('data-hasimage')) === 1;

    const modalEl = document.getElementById('imageModal');
    const modal = bootstrap.Modal.getOrCreateInstance(modalEl);

    $('#itemImagePreview')
        .attr('src', '')
        .addClass('d-none');

    $('#noImageText').addClass('d-none');

    modal.show();

    if(hasImage){

        $('#itemImagePreview')
            .attr(
                'src',
                'includes/item_image_get.php?id=' + itemID + '&t=' + Date.now()
            )
            .on('load.modalFix', function(){

                $(this).removeClass('d-none');

            });

    } else {

        $('#noImageText').removeClass('d-none');

    }

});

$('#imageModal').on('hidden.bs.modal', function () {

    $('#itemImagePreview')
        .off('load.modalFix')
        .attr('src','');

});

</script>