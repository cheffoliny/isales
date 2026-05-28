<?php

include_once __DIR__.'/../includes/functions.php';

if (empty($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$id_type = $_GET['id_type'] ?? 0;

?>

<div class="card shadow border-0">

    <div class="card-header">

        <div class="d-flex gap-2 flex-wrap align-items-center">

            <a href="index.php?page=items" class="btn btn-dark btn-sm">
                <i class="fa-solid fa-arrow-left"></i> Категории
            </a>

            <input type="text"
                   id="search"
                   class="form-control form-control-sm py-2"
                   placeholder="КОД / ИМЕ">

            <button id="newFilter"
                    class="btn btn-sm btn-success">

                НОВО

            </button>

            <button id="promoFilter"
                    class="btn btn-sm btn-danger">

                ПРОМО

            </button>

            <div class="btn-group btn-group-sm ms-auto">

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

        <div id="gridView"
             class="row g-3 p-3"
             style="display:none;"></div>

    </div>

</div>

<script>

    let page = 0;
    let loading = false;
    let endReached = false;

    let viewMode = 'list';

    let searchVal = '';

    let newp = false;
    let promo = false;

    const selectedType =
        <?= (int)$id_type ?>;

    function loadItems(reset=false){

        if(reset){

            page = 0;

            $('#itemsTable').html('');
            $('#gridView').html('');

            endReached = false;
        }

        if(loading || endReached){
            return;
        }

        loading = true;

        $.get(
            'includes/items_fetch.php',
            {

                page: page,
                search: searchVal,
                newp: newp ? 1 : 0,
                promo: promo ? 1 : 0,
                id_type: selectedType

            },
            function(resp){

                if(resp.success){

                    if(
                        resp.html.trim() === '' &&
                        resp.grid.trim() === ''
                    ){

                        endReached = true;

                    } else {

                        if(viewMode === 'list'){
                            $('#itemsTable')
                                .append(resp.html);
                        } else {
                            $('#gridView')
                                .append(resp.grid);
                        }

                        page++;
                    }
                }

                loading = false;

            },
            'json'
        );
    }

    loadItems(true);

    $('#search').on('input', function(){

        searchVal = $(this).val();

        loadItems(true);
    });

    $('#newFilter').on('click', function(){

        newp = !newp;

        $(this).toggleClass(
            'btn-success btn-secondary'
        );

        loadItems(true);
    });

    $('#promoFilter').on('click', function(){

        promo = !promo;

        $(this).toggleClass(
            'btn-danger btn-secondary'
        );

        loadItems(true);
    });

    $('#viewListBtn').on('click', function(){

        viewMode = 'list';

        $('#listView').show();
        $('#gridView').hide();

        $('#viewListBtn')
            .addClass('btn-primary active')
            .removeClass('btn-outline-primary');

        $('#viewGridBtn')
            .removeClass('btn-primary active')
            .addClass('btn-outline-primary');

        loadItems(true);
    });

    $('#viewGridBtn').on('click', function(){

        viewMode = 'grid';

        $('#gridView').show();
        $('#listView').hide();

        $('#viewGridBtn')
            .addClass('btn-primary active')
            .removeClass('btn-outline-primary');

        $('#viewListBtn')
            .removeClass('btn-primary active')
            .addClass('btn-outline-primary');

        loadItems(true);
    });

    $(window).on('scroll', function(){

        if(
            $(window).scrollTop() +
            $(window).height()
            >
            $(document).height() - 200
        ){
            loadItems();
        }
    });

</script>