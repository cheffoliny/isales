<?php
include_once __DIR__.'/../includes/functions.php';

if(empty($_SESSION['user_id'])){
    echo '<div class="alert alert-danger">Нямате достъп.</div>';
    exit;
}
?>
<div class="card shadow border-0">

    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div class="d-flex gap-2 w-100">
            <input type="text" id="search" class="form-control form-control-sm py-2" placeholder="КОД / ИМЕ">
            <button id="promoFilter" class="btn btn-sm btn-danger">ПРОМО</button>
            <button id="zeroFilter" class="btn btn-sm btn-warning">НУЛЕВИ</button>
        </div>
    </div>

    <div class="card-body p-0">
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
</div>

<!-- IMAGE MODAL -->
<div class="modal fade" id="imageModal">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">

            <div class="modal-header">
                <h5 class="modal-title">Снимка</h5>
                <button class="btn-close" data-bs-dismiss="modal"></button>
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

    // load items
    function loadItems(reset=false){
        if(loading || endReached) return;
        loading = true;
        if(reset){
            page=0;
            $('#itemsTable').html('');
            endReached=false;
        }

        $.get('includes/items_fetch.php',{
            page:page,
            search:searchVal,
            promo: promo?1:0,
            zero: zero?1:0
        }, function(resp){
            if(resp.success){
                if(resp.html.trim()===''){ endReached=true; }
                else{ $('#itemsTable').append(resp.html); page++; }
            }
            loading=false;
        },'json');
    }

    // initial load
    loadItems();

    // search
    let searchTimer;
    $('#search').on('input', function(){
        clearTimeout(searchTimer);
        searchTimer=setTimeout(()=>{
            searchVal = $(this).val();
            loadItems(true);
        },400);
    });

    // filters
    $('#promoFilter').on('click', function(){
        promo=!promo;
        $(this).toggleClass('btn-danger btn-secondary');
        loadItems(true);
    });
    $('#zeroFilter').on('click', function(){
        zero=!zero;
        $(this).toggleClass('btn-warning btn-secondary');
        loadItems(true);
    });

    // infinite scroll
    $(window).on('scroll', function(){
        if($(window).scrollTop() + $(window).height() > $(document).height() - 200){
            loadItems();
        }
    });

    // save item
    $(document).on('click','.save-item', function(){
        const row=$(this).closest('tr');
        const id=row.data('id');
        const client=row.find('.client_price').val();
        const sales=row.find('.sales_price').val();

        $.post('includes/item_save.php',{
            id:id,
            client_price:client,
            sales_price:sales
        }, function(resp){
            if(resp.success){
                row.addClass('table-success');
                setTimeout(()=>row.removeClass('table-success'),800);
            } else {
                alert('Грешка при запазване на данни!');
            }
        },'json');
    });

    // image modal
    let currentItem=0;

    $(document).on('click','.item-thumb', function(){

        currentItem=$(this).data('id');

        // винаги отваряме модала
        new bootstrap.Modal('#imageModal').show();

        // проверка за картинка
        const hasImage=$(this).data('hasimage');

        if(hasImage){
            $('#itemImagePreview')
                .attr('src','includes/item_image_get.php?id='+currentItem+'&t='+Date.now())
                .removeClass('d-none');
            $('#deleteImage').removeClass('d-none');
            $('#noImageText').addClass('d-none');
        } else {
            $('#itemImagePreview').addClass('d-none');
            $('#deleteImage').addClass('d-none');
            $('#noImageText').removeClass('d-none');
        }

        // ресет на input
        $('#imageUpload').val('');
    });

    // upload with compression
    $('#uploadImage').on('click', function(){
        const file=$('#imageUpload')[0].files[0];
        if(!file){ alert('Избери файл'); return; }

        const reader=new FileReader();
        reader.onload=function(e){
            const img=new Image();
            img.src=e.target.result;
            img.onload=function(){
                const canvas=document.createElement('canvas');
                const maxDim=500;
                let w=img.width, h=img.height;
                if(w>h && w>maxDim){ h*=maxDim/w; w=maxDim; }
                if(h>w && h>maxDim){ w*=maxDim/h; h=maxDim; }
                canvas.width=w; canvas.height=h;
                const ctx=canvas.getContext('2d');
                ctx.drawImage(img,0,0,w,h);
                canvas.toBlob(function(blob){
                    let form=new FormData();
                    form.append('id',currentItem);
                    form.append('image',blob,'image.jpg');

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
                },'image/jpeg',0.7);
            }
        }
        reader.readAsDataURL(file);
    });

    // delete image
    $('#deleteImage').on('click', function(){
        if(!confirm('Изтриване на снимката?')) return;
        $.post('includes/item_image_delete.php',{id:currentItem},function(resp){
            if(resp.success){ location.reload(); }
            else{ alert('Грешка при изтриване!'); }
        },'json');
    });
</script>