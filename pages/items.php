<?php
include_once __DIR__.'/../includes/functions.php';

if(empty($_SESSION['user_id'])){
    echo '<div class="alert alert-danger">Нямате достъп.</div>';
    exit;
}

$db = db_connect('storage');
?>

<div class="card shadow border-0">
    <div class="card-header d-flex justify-content-between align-items-center">
        <div class="d-flex gap-2">
            <input type="text" id="search" class="form-control form-control-sm" placeholder="КОД / ИМЕ">
            <button id="promoFilter" class="btn btn-sm btn-danger"> ПРОМО </button>
            <button id="zeroFilter" class="btn btn-sm btn-warning"> НУЛЕВИ </button>
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
                        <th class="col-1"></th>
                    </tr>
                </thead>
                <tbody id="itemsTable"></tbody>
            </table>
        </div>
    </div>
    <div class="card-footer text-center">
        <button id="loadMore" class="btn btn-sm btn-outline-secondary"> Зареди още... </button>
    </div>
</div>



<script>

let page=0;
let search='';
let promo=false;
let zero=false;
let loading=false;



function loadItems(reset=false){

if(loading) return;

loading=true;

if(reset){

page=0;
$('#itemsTable').html('');

}

$.get('includes/items_fetch.php',{

page:page,
search:search,
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

search=$(this).val();

loadItems(true);

},400);

});



/* PROMO */

$('#promoFilter').on('click',function(){

promo=!promo;

$(this).toggleClass('btn-danger btn-secondary');

loadItems(true);

});



/* ZERO */

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

setTimeout(()=>{

row.removeClass('table-success');

},800);

}else{

alert('Грешка');

}

},'json');

});

</script>