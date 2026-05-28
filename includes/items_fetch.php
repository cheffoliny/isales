<?php
include_once __DIR__.'/../includes/functions.php';

if (empty($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$idType = (int)($_GET['id_type'] ?? 0);

$db = db_connect('storage');

$typeName = 'Всички артикули';

if($idType > 0){

    $stmt = $db->prepare("
        SELECT name
        FROM nomenclature_types
        WHERE id=?
    ");

    $stmt->bind_param("i",$idType);
    $stmt->execute();

    $stmt->bind_result($typeName);
    $stmt->fetch();

    $stmt->close();
}
?>

<div class="card shadow border-0">

    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">

        <div class="d-flex align-items-center gap-2">

            <button class="btn btn-outline-secondary btn-sm"
                    id="backToCategories">

                <i class="fa-solid fa-angles-left"></i>

            </button>

            <h5 class="mb-0">
                <?= htmlspecialchars($typeName) ?>
            </h5>

        </div>

        <div class="d-flex gap-2 flex-wrap align-items-center">

            <input type="text"
                   id="search"
                   class="form-control form-control-sm py-2"
                   placeholder="КОД / ИМЕ">

            <button id="newFilter" class="btn btn-sm btn-success">
                <i class="fa-solid fa-file-circle-plus"></i> НОВО
            </button>

            <button id="promoFilter" class="btn btn-sm btn-danger">
                <i class="fa-solid fa-percent"></i> ПРОМО
            </button>

            <?php if($_SESSION['is_admin'] == 1) { ?>

                <button id="zeroFilter"
                        class="btn btn-sm btn-warning text-white">

                    <i class="fa-brands fa-creative-commons-zero"></i>
                    НУЛЕВИ

                </button>

                <button id="zeroImage"
                        class="btn btn-sm btn-primary text-white">

                    <i class="fa-solid fa-image"></i>
                    БЕЗ СНИМКА

                </button>

                <button id="zeroOrder"
                        class="btn btn-sm bg-primary text-white">

                    <i class="fa-solid fa-ban"></i>
                    НЕ КУПУВАНИ

                </button>

            <?php } ?>

            <div class="btn-group btn-group-sm">

                <button type="button"
                        class="btn btn-primary active"
                        id="viewListBtn">

                    <i class="fa-solid fa-list"></i>

                </button>

                <button type="button"
                        class="btn btn-outline-primary"
                        id="viewGridBtn">

                    <i class="fa-solid fa-table-cells"></i>

                </button>

            </div>

        </div>

    </div>

    <div class="card-body p-0">

        <div id="listView">

            <div class="table-responsive">

                <table class="table table-sm table-hover align-middle mb-0">

                    <thead class="table-light">
                    <tr>
                        <th class="text-center col-1">Код</th>
                        <th class="col">Име</th>
                        <th class="text-center col-1">Кол.</th>
                        <th class="col-1">Промо</th>
                        <th class="col-1">Клиент</th>
                        <th class="text-center col-1">IMG</th>
                    </tr>
                    </thead>

                    <tbody id="itemsTable"></tbody>

                </table>

            </div>

        </div>

        <div id="gridView"
             class="row g-3 px-3 py-2"
             style="display:none;"></div>

    </div>
</div>

<!-- IMAGE MODAL -->
<div class="modal fade" id="imageModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">

            <div class="modal-header">
                <h5 class="modal-title">Снимка</h5>
                <button class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body text-center">

                <img id="itemImagePreview"
                     class="img-fluid mb-3 d-none"
                     style="max-height:80vh">

                <div id="noImageText"
                     class="text-muted">

                    Няма качена снимка

                </div>

                <?php if($_SESSION['is_admin'] == 1): ?>

                    <input type="file"
                           id="imageUpload"
                           class="form-control mt-3">

                <?php endif; ?>

            </div>

            <?php if($_SESSION['is_admin'] == 1): ?>

                <div class="modal-footer">

                    <button class="btn btn-danger btn-sm"
                            id="deleteImage">

                        Изтрий

                    </button>

                    <button class="btn btn-success btn-sm"
                            id="uploadImage">

                        Качи

                    </button>

                </div>

            <?php endif; ?>

        </div>
    </div>
</div>

<script>

    let page = 0;
    let searchVal = '';
    let newp = false;
    let promo = false;
    let zero = false;
    let image = false;
    let norder = false;
    let loading = false;
    let endReached = false;
    let viewMode = 'list';

    const selectedType = <?= $idType ?>;

    // LOAD ITEMS
    function loadItems(reset=false){

        if(reset){
            page = 0;
            $('#itemsTable').html('');
            $('#gridView').html('');
            endReached = false;
        }

        if(loading || endReached) return;

        loading = true;

        $.get('includes/items_fetch.php', {
            page: page,
            search: searchVal,
            newp: newp ? 1 : 0,
            promo: promo ? 1 : 0,
            zero: zero ? 1 : 0,
            image: image ? 1 : 0,
            norder: norder ? 1 : 0,
            id_type: selectedType
        }, function(resp){

            if(resp.success){

                if(resp.html.trim() === '' && resp.grid.trim() === '') {

                    if(page === 0){

                        $('#itemsTable').html(
                            '<tr><td colspan="7" class="text-center text-muted">Няма резултати</td></tr>'
                        );

                        $('#gridView').html(
                            '<div class="text-center text-muted w-100">Няма резултати</div>'
                        );

                    }

                    endReached = true;

                } else {

                    if(viewMode === 'list'){
                        $('#itemsTable').append(resp.html);
                    } else {
                        $('#gridView').append(resp.grid);
                    }

                    page++;
                }
            }

            loading = false;

        }, 'json');
    }

    loadItems();

    $('#backToCategories').on('click', function(){

        window.location =
            'dashboard.php?page=items';

    });

</script>