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
                        <th class="col-1">Код</th>
                        <th class="col">Име</th>
                        <th class="col-1">Кол.</th>
                        <th class="col-1">Клиент</th>
                        <th class="col-1">Промо</th>
                        <th class="col-1">IMG</th>
                        <th class="col-1 text-end">✔</th>
                    </tr>
                    </thead>
                    <tbody id="itemsTable"></tbody>
                </table>
            </div>
        </div>

        <div id="gridView" class="row g-3 px-3 py-2" style="display:none;"></div>

    </div>
</div>

<script>
let page = 0;
let searchVal = '';
let promo = false;
let zero = false;
let loading = false;
let endReached = false;
let viewMode = 'list';

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

loadItems();

let searchTimer;
$('#search').on('input', function(){
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
        searchVal = $(this).val();
        loadItems(true);
    }, 400);
});

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

$(window).on('scroll', function(){
    if($(window).scrollTop() + $(window).height() > $(document).height() - 200){
        loadItems();
    }
});

// ✅ SAVE
$(document).on('click', '.save-item', function(){

    const btn = $(this);
    const row = btn.closest('tr');

    const id = row.data('id');
    const client = row.find('.client_price').val();
    const sales = row.find('.sales_price').val();

    // loading
    btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i>');

    $.post('includes/item_save.php', {
        id: id,
        client_price: client,
        sales_price: sales
    }, function(resp){

        console.log(resp);

        btn.prop('disabled', false).html('<i class="fa fa-save"></i>');

        if(resp.success){

            // highlight
            row.addClass('table-success');

            setTimeout(() => {
                row.removeClass('table-success');
            }, 2000);

            // ✔ иконка
            const status = row.find('.save-status');
            status.removeClass('d-none').hide().fadeIn(150);

            setTimeout(() => {
                status.fadeOut(300);
            }, 2000);

        } else {
            alert('Грешка при запис!');
        }

    }, 'json');

});
</script>