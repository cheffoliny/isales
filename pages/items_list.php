<?php
include_once __DIR__.'/../includes/functions.php';

if (empty($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$id_type = (int)($_GET['id_type'] ?? 0);
$isAdmin = $_SESSION['is_admin'] == 1;
?>

<div class="card shadow border-0">

    <!-- HEADER -->
    <div class="card-header d-flex flex-wrap gap-2 align-items-center">

        <a href="index.php?page=items" class="btn btn-dark btn-sm">
            <i class="fa-solid fa-arrow-left"></i>
        </a>

        <input type="text" id="search" class="form-control form-control-sm" placeholder="КОД / ИМЕ">

        <button id="newFilter" class="btn btn-sm btn-success">НОВО</button>
        <button id="promoFilter" class="btn btn-sm btn-danger">ПРОМО</button>

        <?php if($isAdmin): ?>
            <button id="zeroFilter" class="btn btn-sm btn-warning text-white">НУЛЕВИ</button>
            <button id="zeroImage" class="btn btn-sm btn-primary text-white">БЕЗ СНИМКА</button>
            <button id="zeroOrder" class="btn btn-sm bg-primary text-white">НЕ КУПУВАНИ</button>
        <?php endif; ?>

        <div class="btn-group btn-group-sm ms-auto">
            <button id="viewListBtn" class="btn btn-primary active">
                <i class="fa-solid fa-list"></i>
            </button>
            <button id="viewGridBtn" class="btn btn-outline-primary">
                <i class="fa-solid fa-table-cells"></i>
            </button>
        </div>

    </div>

    <!-- BODY -->
    <div class="card-body p-0">

        <div id="listView">
            <div class="table-responsive">
                <table class="table table-sm table-hover">
                    <thead class="table-light">
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

<!-- ================= MODAL EDITOR ================= -->
<div class="modal fade" id="itemModal" tabindex="-1">

    <div class="modal-dialog modal-lg modal-dialog-centered">

        <div class="modal-content">

            <div class="modal-header">
                <h5 class="modal-title">Редакция на артикул</h5>
                <button class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">

                <input type="hidden" id="item_id">

                <div class="row g-2">

                    <div class="col-6">
                        <label>Клиент цена</label>
                        <input type="number" id="client_price" class="form-control">
                    </div>

                    <div class="col-6">
                        <label>Продажна цена</label>
                        <input type="number" id="sales_price" class="form-control">
                    </div>

                    <div class="col-12">
                        <label>Промо текст</label>
                        <input type="text" id="promo_note" class="form-control">
                    </div>

                    <div class="col-12">
                        <label>
                            <input type="checkbox" id="is_new">
                            НОВО
                        </label>
                    </div>

                    <div class="col-12 mt-3">
                        <img id="item_image" class="img-fluid d-none" style="max-height:250px;">
                        <input type="file" id="imageUpload" class="form-control mt-2">
                        <div class="d-flex gap-2 mt-2">
                            <button id="uploadImage" class="btn btn-success btn-sm">Качи</button>
                            <button id="deleteImage" class="btn btn-danger btn-sm">Изтрий</button>
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

<script>

    let page=0, loading=0, end=0;
    let view='list';
    let search='';
    let newp=0, promo=0, zero=0, image=0, norder=0;

    let currentItem=0;
    const typeId = <?= $id_type ?>;
    const isAdmin = <?= $isAdmin ? 1 : 0 ?>;

    // ================= LOAD =================
    function load(reset=false){

        if(reset){
            page=0;
            $('#itemsTable').html('');
            $('#gridView').html('');
            end=0;
        }

        if(loading || end) return;
        loading=1;

        $.get('includes/items_fetch.php',{
            page, search, newp, promo, zero, image, norder, id_type:typeId
        },function(r){

            if(r.success){

                if(!r.html.trim() && !r.grid.trim()){
                    end=1;
                } else {
                    view==='list'
                        ? $('#itemsTable').append(r.html)
                        : $('#gridView').append(r.grid);

                    page++;
                }
            }

            loading=0;
        },'json');
    }

    load(true);

    // ================= CLICK ITEM =================
    $(document).on('click','.item-thumb, .card-img-top, .item-card',function(){

        const el = $(this).closest('[data-id]');

        currentItem = el.data('id') || 0;

        // =========================
        // SAFE VALUE EXTRACTION
        // =========================
        const client = el.data('client');
        const sales  = el.data('sales');
        const note   = el.data('note');
        const isNew  = el.data('new');

        // =========================
        // POPULATE MODAL INPUTS
        // =========================
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

        // =========================
        // IMAGE HANDLING
        // =========================
        const img = $('#item_image');
        const imgUrl = 'includes/item_image_get.php?id=' + currentItem;

        img.attr('src', imgUrl).removeClass('d-none');

        // =========================
        // OPEN MODAL
        // =========================
        bootstrap.Modal.getOrCreateInstance(
            document.getElementById('itemModal')
        ).show();

    });

    // ================= SAVE =================
    $('#saveItem').click(function(){

        $.post('includes/item_save.php',{
            id: currentItem,
            client_price: $('#client_price').val(),
            sales_price: $('#sales_price').val(),
            promo_note: $('#promo_note').val(),
            newp: $('#is_new').is(':checked')?1:0
        },function(r){

            if(r.success){
                location.reload();
            } else {
                alert('Грешка');
            }

        },'json');

    });

    $('#item_image').on('error', function(){
        $(this).addClass('d-none');
    });

</script>