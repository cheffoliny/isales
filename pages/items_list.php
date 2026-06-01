<?php
include_once __DIR__.'/../includes/functions.php';

if (empty($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$id_type = (int)($_GET['id_type'] ?? 0);
$isAdmin = ($_SESSION['is_admin'] ?? 0) == 1;

$db_storage = db_connect('storage');

$nomenclatureTypes = [];

if ($isAdmin) {

    $typesSql = "
        SELECT
            id,
            name
        FROM nomenclature_types
        ORDER BY name ASC
    ";

    $typesQuery = $db_storage->query($typesSql);

    if ($typesQuery) {
        while ($type = $typesQuery->fetch_assoc()) {
            $nomenclatureTypes[] = $type;
        }
    }
}
?>

<div class="card shadow border-0">

    <div class="card-header d-flex flex-wrap gap-2 align-items-center">
        <input type="text" id="search" class="form-control form-control-sm" placeholder="КОД / ИМЕ">


        <a href="dashboard.php?page=items" class="btn btn-outline-secondary btn-sm">
            <i class="fa-solid fa-angles-left"></i>
        </a>
        <button id="newFilter" class="btn btn-sm btn-success">НОВО</button>
        <button id="promoFilter" class="btn btn-sm btn-danger">ПРОМО</button>

        <?php if ($isAdmin): ?>
            <button id="zeroFilter" class="btn btn-sm btn-warning text-white">НУЛЕВИ</button>
            <button id="zeroImage" class="btn btn-sm btn-primary text-white">БЕЗ СНИМКА</button>
            <button id="zeroOrder" class="btn btn-sm bg-primary text-white">НЕ КУПУВАНИ</button>
            <button id="noCategory" class="btn btn-sm btn-dark">БЕЗ КАТЕГОРИЯ</button>
        <?php endif; ?>
        <div class="btn-group btn-group-sm ms-auto">
            <button id="viewListBtn" type="button" class="btn btn-primary active">
                <i class="fa-solid fa-list"></i>
            </button>

            <button id="viewGridBtn" type="button" class="btn btn-outline-primary">
                <i class="fa-solid fa-table-cells"></i>
            </button>
        </div>

    </div>

    <div class="card-body p-0">

        <div id="listView">
            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0">
                    <thead class="table-dark">
                    <tr>
                        <th>Код</th>
                        <th>Име</th>
                        <th>Кол.</th>
                        <th>Промо</th>
                        <th>Клиент</th>
                        <th>IMG</th>
                    </tr>
                    </thead>
                    <tbody id="itemsTable"></tbody>
                </table>
            </div>
        </div>

        <div id="gridView" class="row g-3 p-3" style="display:none;"></div>

    </div>

</div>

<?php if ($isAdmin): ?>

<div class="modal fade" id="itemModal" tabindex="-1">

    <div class="modal-dialog modal-lg modal-dialog-centered">

        <div class="modal-content">

            <div class="modal-header">
                <h5 class="modal-title">Редакция на артикул</h5>
                <button class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">

                <input type="hidden" id="item_id">

                <div class="row g-3">

                    <div class="col-12">
                        <label class="form-label fw-semibold">Тип артикул</label>

                        <select id="id_type" class="form-select">
                            <option value="0">-- Без тип --</option>

                            <?php foreach ($nomenclatureTypes as $type): ?>
                                <option value="<?= (int)$type['id'] ?>">
                                    <?= htmlspecialchars($type['name']) ?>
                                </option>
                            <?php endforeach; ?>

                        </select>
                    </div>

                    <div class="col-12">

                    </div>

                    <div class="col-4">
                        <label class="form-label fw-semibold text-success" for="is_new">Нов артикул</label>
                        <div class="form-check form-switch">
                            <input class="form-check-input"
                                   type="checkbox"
                                   id="is_new">
                        </div>
                    </div>

                    <div class="col-4">
                        <label class="form-label fw-semibold">Клиент цена</label>
                        <input type="number"
                               step="0.01"
                               id="client_price"
                               class="form-control" disabled="disabled">
                    </div>
                    <div class="col-4">

                        <label class="form-label fw-semibold text-danger">Промо цена</label>
                        <input type="number"
                               step="0.01"
                               id="sales_price"
                               class="form-control">
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semibold">Промо текст</label>
                        <input type="text"
                               id="promo_note"
                               class="form-control">
                    </div>

                    <div class="col-12 mt-2">
                        <label class="form-label fw-semibold">Снимка</label>

                        <div class="text-center bg-light rounded p-2 mb-2">
                            <img id="item_image"
                                 class="img-fluid d-none"
                                 style="max-height:250px;">
                        </div>

                        <input type="file"
                               id="imageUpload"
                               class="form-control"
                               accept="image/*">

                        <div class="d-flex gap-2 mt-2">
                            <button id="uploadImage"
                                    type="button"
                                    class="btn btn-success btn-sm">
                                Качи
                            </button>

                            <button id="deleteImage"
                                    type="button"
                                    class="btn btn-danger btn-sm">
                                Изтрий
                            </button>
                        </div>
                    </div>

                </div>

            </div>

            <div class="modal-footer">
                <button id="saveItem" class="btn btn-success">Запази</button>
            </div>

        </div>

    </div>

</div>

<?php endif; ?>

<script>

    let page = 0;
    let loading = 0;
    let end = 0;

    let view = 'list';
    let search = '';
    let newp = 0;
    let promo = 0;
    let zero = 0;
    let image = 0;
    let norder = 0;
    let noCategory = 0;

    let currentItem = 0;

    const typeId = <?= $id_type ?>;
    const isAdmin = <?= $isAdmin ? 1 : 0 ?>;

    function load(reset = false) {

        if (reset) {
            page = 0;
            end = 0;
            $('#itemsTable').html('');
            $('#gridView').html('');
        }

        if (loading || end) {
            return;
        }

        loading = 1;

        $.get('includes/items_fetch.php', {
            page: page,
            search: search,
            newp: newp,
            promo: promo,
            zero: zero,
            image: image,
            norder: norder,
            no_category: noCategory,
            id_type: typeId
        }, function (r) {

            if (r.success) {

                if (!r.html.trim() && !r.grid.trim()) {
                    end = 1;
                } else {

                    $('#itemsTable').append(r.html);
                    $('#gridView').append(r.grid);

                    page++;
                }

            } else {
                alert(r.error || 'Грешка при зареждане');
            }

            loading = 0;

        }, 'json');
    }

    function setView(nextView) {

        view = nextView;

        if (view === 'grid') {

            $('#listView').hide();
            $('#gridView').show();

            $('#viewGridBtn')
                .removeClass('btn-outline-primary')
                .addClass('btn-primary active');

            $('#viewListBtn')
                .removeClass('btn-primary active')
                .addClass('btn-outline-primary');

        } else {

            $('#gridView').hide();
            $('#listView').show();

            $('#viewListBtn')
                .removeClass('btn-outline-primary')
                .addClass('btn-primary active');

            $('#viewGridBtn')
                .removeClass('btn-primary active')
                .addClass('btn-outline-primary');
        }
    }

    $('#viewGridBtn').on('click', function () {
        setView('grid');
    });

    $('#viewListBtn').on('click', function () {
        setView('list');
    });

    $('#search').on('keyup', function () {
        search = $(this).val().trim();
        load(true);
    });

    $('#newFilter').on('click', function () {
        newp = newp ? 0 : 1;
        $(this).toggleClass('btn-success btn-outline-success');
        load(true);
    });

    $('#promoFilter').on('click', function () {
        promo = promo ? 0 : 1;
        $(this).toggleClass('btn-danger btn-outline-danger');
        load(true);
    });

    <?php if ($isAdmin): ?>

    $('#zeroFilter').on('click', function () {
        zero = zero ? 0 : 1;
        $(this).toggleClass('btn-warning btn-outline-warning');
        load(true);
    });

    $('#zeroImage').on('click', function () {
        image = image ? 0 : 1;
        $(this).toggleClass('btn-primary btn-outline-primary');
        load(true);
    });

    $('#noCategory').on('click', function () {

        noCategory = noCategory ? 0 : 1;

        $(this).toggleClass(
            'btn-primary btn-outline-primary'
        );
        load(true);
    });

    $('#zeroOrder').on('click', function () {
        norder = norder ? 0 : 1;
        $(this).toggleClass('bg-primary btn-outline-primary');
        load(true);
    });

    $(document).on('click', '.item-row, .item-thumb, .card-img-top, .item-card', function () {

        const el = $(this).closest('[data-id]');

        currentItem = el.data('id') || 0;

        const client = el.data('client');
        const sales = el.data('sales');
        const note = el.data('note');
        const isNew = el.data('new');
        const idType = el.data('type');

        $('#item_id').val(currentItem);

        $('#client_price').val(
            (client !== undefined && client !== null) ? client : ''
        );

        $('#sales_price').val(
            (sales !== undefined && sales !== null) ? sales : ''
        );

        $('#promo_note').val(
            (note !== undefined && note !== null) ? note : ''
        );

        $('#is_new').prop(
            'checked',
            parseInt(isNew || 0, 10) === 1
        );

        $('#id_type').val(
            (idType !== undefined && idType !== null) ? idType : 0
        );

        $('#item_image')
            .attr('src', 'includes/item_image_get.php?id=' + currentItem)
            .removeClass('d-none');

        bootstrap.Modal.getOrCreateInstance(
            document.getElementById('itemModal')
        ).show();

    });

    $('#saveItem').on('click', function () {

        $.post('includes/item_save.php', {
            id: currentItem,
            client_price: $('#client_price').val(),
            sales_price: $('#sales_price').val(),
            promo_note: $('#promo_note').val(),
            newp: $('#is_new').is(':checked') ? 1 : 0,
            id_type: $('#id_type').val()
        }, function (r) {

            if (r.success) {
                location.reload();
            } else {
                alert(r.error || 'Грешка');
            }

        }, 'json');

    });

    $('#item_image').on('error', function () {
        $(this).addClass('d-none');
    });

    $('#uploadImage').on('click', function () {

        const fileInput = $('#imageUpload')[0];

        if (!currentItem) {
            alert('Няма избран артикул');
            return;
        }

        if (!fileInput.files.length) {
            alert('Изберете снимка');
            return;
        }

        const formData = new FormData();
        formData.append('id', currentItem);
        formData.append('image', fileInput.files[0]);

        $.ajax({
            url: 'includes/item_image_upload.php',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
            success: function (r) {
                if (r.success) {
                    $('#item_image')
                        .attr('src', 'includes/item_image_get.php?id=' + currentItem + '&t=' + Date.now())
                        .removeClass('d-none');

                    alert('Снимката е качена');
                    load(true);
                } else {
                    alert(r.error || 'Грешка при качване');
                }
            },
            error: function () {
                alert('AJAX грешка при качване');
            }
        });
    });

    <?php endif; ?>

    $(window).on('scroll', function () {

        if (
            $(window).scrollTop() + $(window).height()
            >= $(document).height() - 300
        ) {
            load(false);
        }
    });

    load(true);

</script>