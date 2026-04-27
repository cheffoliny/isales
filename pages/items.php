<?php
include_once __DIR__.'/../includes/functions.php';

if(empty($_SESSION['user_id'])){
    echo '<div class="alert alert-danger">Нямате достъп.</div>';
    exit;
}
?>

<div class="card shadow border-0">

    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div class="d-flex gap-2 w-100 flex-wrap align-items-center">
            <input type="text" id="search" class="form-control form-control-sm py-2" placeholder="КОД / ИМЕ">

            <button id="promoFilter" class="btn btn-sm btn-danger">ПРОМО</button>
            <button id="zeroFilter" class="btn btn-sm btn-warning">НУЛЕВИ</button>
            <button id="zeroImage" class="btn btn-sm btn-info">БЕЗ СНИМКА</button>

            <div class="btn-group btn-group-sm ms-auto">
                <button type="button" class="btn btn-primary active" id="viewListBtn">
                    <i class="fa-solid fa-list"></i>
                </button>
                <button type="button" class="btn btn-outline-primary" id="viewGridBtn">
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

        <div id="gridView" class="row g-3 px-3 py-2" style="display:none;"></div>

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
                <?php if($_SESSION['is_admin'] == 1) { ?>
                    <input type="file" id="imageUpload" class="form-control mt-3">
                <?php } ?>
            </div>
            <?php if($_SESSION['is_admin'] == 1) { ?>
            <div class="modal-footer">
                <button class="btn btn-danger btn-sm" id="deleteImage">Изтрий</button>
                <button class="btn btn-success btn-sm" id="uploadImage">Качи</button>
            </div>
            <?php } ?>
        </div>
    </div>
</div>

<script>
let page = 0;
let searchVal = '';
let promo = false;
let zero = false;
let image = false;
let loading = false;
let endReached = false;
let viewMode = 'list';

// LOAD ITEMS
function loadItems(reset=false){

    // ✅ ПЪРВО reset логиката
    if(reset){
        page = 0;
        $('#itemsTable').html('');
        $('#gridView').html('');
        endReached = false; // 🔥 преместено тук
    }

    // ✅ СЛЕД това проверката
    if(loading || endReached) return;

    loading = true;

    $.get('includes/items_fetch.php', {
        page: page,
        search: searchVal,
        promo: promo ? 1 : 0,
        zero: zero ? 1 : 0,
        image: image ? 1 : 0
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

// SEARCH
let searchTimer;
$('#search').on('input', function(){
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
        searchVal = $(this).val();
        loadItems(true);
    }, 400);
});

// FILTERS
$('#promoFilter').on('click', function(){
    promo = !promo;
    $(this).toggleClass('btn-danger btn-secondary');
    loadItems(true);
});

$('#zeroFilter').on('click', function(){
    zero = !zero;
    $(this).toggleClass('btn-warning btn-secondary');
    loadItems(true);
});

$('#zeroImage').on('click', function(){
    image = !image;
    $(this).toggleClass('btn-info btn-secondary');
    loadItems(true);
});

// ✅ VIEW SWITCH (върнато)
$('#viewListBtn').on('click', function(){
    if(viewMode !== 'list'){
        viewMode = 'list';

        $('#viewListBtn')
            .addClass('btn-primary active')
            .removeClass('btn-outline-primary');

        $('#viewGridBtn')
            .removeClass('btn-primary active')
            .addClass('btn-outline-primary');

        $('#listView').show();
        $('#gridView').hide();

        if($('#itemsTable').children().length === 0){
            loadItems(true);
        }
    }
});

$('#viewGridBtn').on('click', function(){
    if(viewMode !== 'grid'){
        viewMode = 'grid';

        $('#viewGridBtn')
            .addClass('btn-primary active')
            .removeClass('btn-outline-primary');

        $('#viewListBtn')
            .removeClass('btn-primary active')
            .addClass('btn-outline-primary');

        $('#gridView').show();
        $('#listView').hide();

        if($('#gridView').children().length === 0){
            loadItems(true);
        }
    }
});

// SCROLL LOAD
$(window).on('scroll', function(){
    if($(window).scrollTop() + $(window).height() > $(document).height() - 200){
        loadItems();
    }
});


$(document).on('click', '.save-item', function(){

    const btn = $(this);
    const container = btn.closest('[data-id]');

    const id = container.data('id');

    // ✅ FIX: локален scope
    const scope = btn.closest('tr, .card');

    const client = scope.find('input.client_price').first().val();
    const sales = scope.find('input.sales_price').first().val();
    const promoNote = scope.find('input.promo_note').first().val();

    btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i>');

    $.post('includes/item_save.php', {
        id: id,
        client_price: client,
        sales_price: sales,
        promo_note: promoNote
    }, function(resp){

        btn.prop('disabled', false).html('<i class="fa fa-save"></i>');

        if(resp.success){

            container.addClass('table-success');

            setTimeout(() => {
                container.removeClass('table-success');
            }, 2000);

            const status = container.find('.save-status');
            status.removeClass('d-none').hide().fadeIn(150);

            setTimeout(() => {
                status.fadeOut(300);
            }, 2000);

        } else {
            alert('Грешка при запис!');
        }

    }, 'json');

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

    console.log('OPEN MODAL', { currentItem, raw });

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

// =============================
// UPLOAD IMAGE
// =============================
$('#uploadImage').on('click', function(){

    const file = $('#imageUpload')[0].files[0];
    if(!file){
        alert('Избери файл');
        return;
    }

    const reader = new FileReader();

    reader.onload = function(e){

        const img = new Image();
        img.src = e.target.result;

        img.onload = function(){

            const canvas = document.createElement('canvas');
            const maxDim = 500;

            let w = img.width,
                h = img.height;

            if(w > h && w > maxDim){
                h *= maxDim / w;
                w = maxDim;
            }

            if(h >= w && h > maxDim){
                w *= maxDim / h;
                h = maxDim;
            }

            canvas.width = w;
            canvas.height = h;

            const ctx = canvas.getContext('2d');
            ctx.drawImage(img, 0, 0, w, h);

            canvas.toBlob(function(blob){

                let form = new FormData();
                form.append('id', currentItem);
                form.append('image', blob, 'image.jpg');

                $.ajax({
                    url: 'includes/item_image_upload.php',
                    type: 'POST',
                    data: form,
                    processData: false,
                    contentType: false,
                    dataType: 'json',

                    success: function(resp){

                        if(resp.success){

                            // =========================
                            // ✅ FIX: clean modal close
                            // =========================
                            if(imageModalInstance){
                                imageModalInstance.hide();
                            }

                            setTimeout(() => {
                                location.reload();
                            }, 300);

                        } else {
                            alert('Грешка при качване на снимката!');
                        }
                    }
                });

            }, 'image/jpeg', 0.7);
        };
    };

    reader.readAsDataURL(file);
});

$(document).on('click', '#deleteImage', function(){

    if(!currentItem){
        alert('Няма избран артикул');
        return;
    }

    if(!confirm('Сигурен ли си, че искаш да изтриеш снимката?')){
        return;
    }

    $.post('includes/item_image_delete.php', {
        id: currentItem
    }, function(resp){

        if(resp.success){

            // hide modal image state
            $('#itemImagePreview')
                .attr('src','')
                .addClass('d-none');

            $('#deleteImage').addClass('d-none');
            $('#noImageText').removeClass('d-none');

            // optional: refresh list/grid visuals
            $('[data-id="'+currentItem+'"]').attr('data-hasimage', 0);

            console.log('IMAGE DELETED OK');

        } else {
            alert('Грешка при изтриване!');
        }

    }, 'json');

});
</script>