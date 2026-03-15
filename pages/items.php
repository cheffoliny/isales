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

            <!-- Бутон за превключване на изглед -->
            <div class="btn-group btn-group-sm ms-auto" role="group" aria-label="View mode">
                <button type="button" class="btn btn-primary active" id="viewListBtn" title="Списъчен вид">
                    <i class="fa-solid fa-list"></i>
                </button>
                <button type="button" class="btn btn-outline-primary" id="viewGridBtn" title="Изглед решетка">
                    <i class="fa-solid fa-table-cells"></i>
                </button>
            </div>
        </div>
    </div>

    <div class="card-body p-0">

        <!-- Списъчен вид - таблица -->
        <div id="listView">
            <div class="table-responsive">
                <table class="table table-sm table-hover align-middle mb-0">
                    <thead class="table-light">
                    <tr>
                        <th class="col-1">Код</th>
                        <th class="col">Име</th>
                        <th class="col-1">Кол.</th>
                        <th class="col-1">Клиент</th>
                        <th class="col-1">Промо</th>
                        <th class="col-1">IMG</th>
                        <th class="col-1"></th>
                    </tr>
                    </thead>
                    <tbody id="itemsTable"></tbody>
                </table>
            </div>
        </div>

        <!-- Изглед решетка -->
        <div id="gridView" class="row row-cols-2 row-cols-md-3 row-cols-lg-4 g-3 px-3 py-2" style="display:none;">
            <!-- Cards ще се зареждат тук -->
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
                <input type="file" id="imageUpload" class="form-control mt-3">
            </div>

            <div class="modal-footer">
                <button class="btn btn-danger btn-sm d-none" id="deleteImage">Изтрий</button>
                <button class="btn btn-success btn-sm" id="uploadImage">Качи</button>
            </div>

        </div>
    </div>
</div>

<script>
    let page = 0;
    let searchVal = '';
    let promo = false;
    let zero = false;
    let loading = false;
    let endReached = false;
    let viewMode = 'list'; // 'list' или 'grid'

    // Зареждане на артикули
    function loadItems(reset=false){
        if(loading || endReached) return;
        loading = true;

        if(reset){
            page = 0;
            $('#itemsTable').html('');
            $('#gridView').html('');
            endReached = false;
        }

        $.get('includes/items_fetch.php', {
            page: page,
            search: searchVal,
            promo: promo ? 1 : 0,
            zero: zero ? 1 : 0
        }, function(resp){
            if(resp.success){
                if(resp.html.trim() === '' && resp.grid.trim() === '') {
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

    // Първоначално зареждане
    loadItems();

    // Търсене с debounce
    let searchTimer;
    $('#search').on('input', function(){
        clearTimeout(searchTimer);
        searchTimer = setTimeout(() => {
            searchVal = $(this).val();
            loadItems(true);
        }, 400);
    });

    // Филтриране ПРОМО
    $('#promoFilter').on('click', function(){
        promo = !promo;
        $(this).toggleClass('btn-danger btn-secondary');
        loadItems(true);
    });

    // Филтриране НУЛЕВИ
    $('#zeroFilter').on('click', function(){
        zero = !zero;
        $(this).toggleClass('btn-warning btn-secondary');
        loadItems(true);
    });

    // Превключване между списъчен и grid изглед
    $('#viewListBtn').on('click', function(){
        if(viewMode !== 'list'){
            viewMode = 'list';
            $('#viewListBtn').addClass('btn-primary active').removeClass('btn-outline-primary');
            $('#viewGridBtn').removeClass('btn-primary active').addClass('btn-outline-primary');
            $('#listView').show();
            $('#gridView').hide();
            // Ако няма заредени данни, зареждаме
            if($('#itemsTable').children().length === 0){
                loadItems(true);
            }
        }
    });

    $('#viewGridBtn').on('click', function(){
        if(viewMode !== 'grid'){
            viewMode = 'grid';
            $('#viewGridBtn').addClass('btn-primary active').removeClass('btn-outline-primary');
            $('#viewListBtn').removeClass('btn-primary active').addClass('btn-outline-primary');
            $('#gridView').show();
            $('#listView').hide();
            // Ако няма заредени данни, зареждаме
            if($('#gridView').children().length === 0){
                loadItems(true);
            }
        }
    });

    // Безкрайно скролване (load more)
    $(window).on('scroll', function(){
        if($(window).scrollTop() + $(window).height() > $(document).height() - 200){
            loadItems();
        }
    });

    // Запазване на артикул (само за списъчен вид)
    $(document).on('click', '.save-item', function(){
        const row = $(this).closest('tr');
        const id = row.data('id');
        const client = row.find('.client_price').val();
        const sales = row.find('.sales_price').val();

        $.post('includes/item_save.php', {
            id: id,
            client_price: client,
            sales_price: sales
        }, function(resp){
            if(resp.success){
                row.addClass('table-success');
                setTimeout(() => row.removeClass('table-success'), 800);
            } else {
                alert('Грешка при запазване на данни!');
            }
        }, 'json');
    });

    // Модал за снимки (и логика остава същата, както беше)
    let currentItem = 0;

    $(document).on('click', '.item-thumb, .card-img-top', function(){
        currentItem = $(this).data('id');

        new bootstrap.Modal('#imageModal').show();

        const hasImage = $(this).data('hasimage');

        if(hasImage){
            $('#itemImagePreview')
                .attr('src', 'includes/item_image_get.php?id=' + currentItem + '&t=' + Date.now())
                .removeClass('d-none');
            $('#deleteImage').removeClass('d-none');
            $('#noImageText').addClass('d-none');
        } else {
            $('#itemImagePreview').addClass('d-none');
            $('#deleteImage').addClass('d-none');
            $('#noImageText').removeClass('d-none');
        }

        $('#imageUpload').val('');
    });

    $('#uploadImage').on('click', function(){
        const file = $('#imageUpload')[0].files[0];
        if(!file){ alert('Избери файл'); return; }

        const reader = new FileReader();
        reader.onload = function(e){
            const img = new Image();
            img.src = e.target.result;
            img.onload = function(){
                const canvas = document.createElement('canvas');
                const maxDim = 500;
                let w = img.width, h = img.height;
                if(w > h && w > maxDim){ h *= maxDim/w; w = maxDim; }
                if(h >= w && h > maxDim){ w *= maxDim/h; h = maxDim; }
                canvas.width = w;
                canvas.height = h;
                const ctx = canvas.getContext('2d');
                ctx.drawImage(img, 0, 0, w, h);
                canvas.toBlob(function(blob){
                    let form = new FormData();
                    form.append('id', currentItem);
                    form.append('image', blob, 'image.jpg');

                    $.ajax({
                        url:'includes/item_image_upload.php',
                        type:'POST',
                        data:form,
                        processData:false,
                        contentType:false,
                        dataType:'json',
                        success:function(resp){
                            if(resp.success){ location.reload(); }
                            else{ alert('Грешка при качване на снимката!'); }
                        }
                    });
                }, 'image/jpeg', 0.7);
            };
        };
        reader.readAsDataURL(file);
    });

    $('#deleteImage').on('click', function(){
        if(!confirm('Изтриване на снимката?')) return;
        $.post('includes/item_image_delete.php', {id: currentItem}, function(resp){
            if(resp.success){ location.reload(); }
            else{ alert('Грешка при изтриване!'); }
        }, 'json');
    });

</script>