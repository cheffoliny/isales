<?php
include_once __DIR__.'/../includes/functions.php';

if(empty($_SESSION['user_id'])){
    echo '<div class="alert alert-danger">Нямате достъп.</div>';
    exit;
}

$db = db_connect('storage');
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

    <div class="card-footer text-center">
        <button id="loadMore" class="btn btn-sm btn-outline-secondary">Зареди още...</button>
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

                <img id="itemImagePreview" class="img-fluid mb-3 d-none" style="max-height:300px">

                <div id="noImageText" class="text-muted">
                    Няма качена снимка
                </div>

                <input type="file" id="imageUpload" class="form-control mt-3">

            </div>

            <div class="modal-footer">

                <button class="btn btn-danger btn-sm d-none" id="deleteImage">
                    Изтрий
                </button>

                <button class="btn btn-success btn-sm" id="uploadImage">
                    Качи
                </button>

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

    function loadItems(reset=false){

        if(loading) return;
        loading = true;

        if(reset){
            page=0;
            $('#itemsTable').html('');
        }

        $.get('includes/items_fetch.php',{
            page:page,
            search:searchVal,
            promo:promo?1:0,
            zero:zero?1:0
        },function(resp){

            if(resp.success){

                $('#itemsTable').append(resp.html);
                page++;

            }

            loading=false;

        },'json');

    }

    loadItems();

    /* SEARCH */

    let searchTimer;

    $('#search').on('input',function(){

        clearTimeout(searchTimer);

        searchTimer=setTimeout(()=>{

            searchVal=$(this).val();
            loadItems(true);

        },400);

    });

    /* FILTERS */

    $('#promoFilter').on('click',function(){

        promo=!promo;
        $(this).toggleClass('btn-danger btn-secondary');

        loadItems(true);

    });

    $('#zeroFilter').on('click',function(){

        zero=!zero;
        $(this).toggleClass('btn-warning btn-secondary');

        loadItems(true);

    });

    /* LOAD MORE */

    $('#loadMore').on('click',function(){

        loadItems();

    });

    /* SAVE */

    $(document).on('click','.save-item',function(){

        const row=$(this).closest('tr');

        const id=row.data('id');
        const client=row.find('.client_price').val();
        const sales=row.find('.sales_price').val();
        const calc=row.find('.is_calc').val();

        $.post('includes/item_save.php',{

            id:id,
            client_price:client,
            sales_price:sales,
            is_calc:calc

        },function(resp){

            if(resp.success){

                row.addClass('table-success');
                setTimeout(()=>row.removeClass('table-success'),800);

            }else{

                alert('Грешка при запазване на данни!');

            }

        },'json');

    });

    /* IMAGE MODAL */

    let currentItem=0;

    $(document).on('click','.item-image',function(){

        currentItem=$(this).data('id');
        const img=$(this).data('image');

        if(img){

            $('#itemImagePreview')
                .attr('src','data:image/jpeg;base64,'+img)
                .removeClass('d-none');

            $('#deleteImage').removeClass('d-none');
            $('#noImageText').addClass('d-none');

        }else{

            $('#itemImagePreview').addClass('d-none');
            $('#deleteImage').addClass('d-none');
            $('#noImageText').removeClass('d-none');

        }

        new bootstrap.Modal('#imageModal').show();

    });

    /* UPLOAD */

    $('#uploadImage').on('click',function(){

        const file=$('#imageUpload')[0].files[0];

        if(!file){

            alert('Избери файл');
            return;

        }

        let form=new FormData();

        form.append('id',currentItem);
        form.append('image',file);

        $.ajax({

            url:'includes/item_image_upload.php',
            type:'POST',
            data:form,
            processData:false,
            contentType:false,
            dataType:'json',

            success:function(resp){

                if(resp.success){

                    location.reload();

                }else{

                    alert('Грешка при качване на снимката!');

                }

            }

        });

    });

    /* DELETE */

    $('#deleteImage').on('click',function(){

        if(!confirm('Изтриване на снимката?')) return;

        $.post('includes/item_image_delete.php',{id:currentItem},function(resp){

            if(resp.success){

                location.reload();

            }else{

                alert('Грешка при изтриване!');

            }

        },'json');

    });

</script>