<?php
include_once __DIR__.'/../includes/functions.php';

if (empty($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$db = db_connect('storage');

$resTypes = $db->query("
    SELECT
        nt.id,
        nt.name,
        nt.image,
        (
            SELECT COUNT(n.id)
            FROM nomenclatures n
            WHERE n.id_type = nt.id
            AND n.to_arc = 0
        ) AS total_items
    FROM nomenclature_types nt
    WHERE nt.to_arc = 0
    ORDER BY nt.name
");
?>

<div class="container-fluid">

    <div class="d-flex justify-content-between align-items-center mb-3">

        <h4 class="mb-0">
            <i class="fa-solid fa-layer-group"></i>
            Категории
        </h4>

    </div>

    <div class="row g-3">

        <!-- ВСИЧКИ -->
        <div class="col-6 col-md-4 col-lg-3">

            <div class="card h-100 shadow-sm border-0 typeCard"
                 data-id="0"
                 style="cursor:pointer;">

                <img src="assets/images/na.jpg"
                     class="card-img-top"
                     style="height:180px;object-fit:cover;">

                <div class="card-body text-center">

                    <div class="fw-bold">
                        Всички артикули
                    </div>

                    <div class="text-muted small">
                        Покажи всички
                    </div>

                </div>

            </div>

        </div>

        <?php while($t = $resTypes->fetch_assoc()): ?>

            <?php

            $img = !empty($t['image'])
                ? 'includes/type_image_get.php?id='.$t['id']
                : 'assets/images/na.jpg';

            ?>

            <div class="col-6 col-md-4 col-lg-3">

                <div class="card h-100 shadow-sm border-0 position-relative typeCard"
                     data-id="<?= (int)$t['id'] ?>"
                     style="cursor:pointer;">

                    <?php if($_SESSION['is_admin'] == 1): ?>

                        <button class="btn btn-dark btn-sm position-absolute top-0 end-0 m-2 editTypeImage"
                                data-id="<?= (int)$t['id'] ?>"
                                data-name="<?= htmlspecialchars($t['name']) ?>"
                                data-hasimage="<?= !empty($t['image']) ? 1 : 0 ?>">

                            <i class="fa-solid fa-camera"></i>

                        </button>

                    <?php endif; ?>

                    <img src="<?= $img ?>"
                         class="card-img-top"
                         style="height:180px;object-fit:cover;">

                    <div class="card-body text-center">

                        <div class="fw-bold">
                            <?= htmlspecialchars($t['name']) ?>
                        </div>

                        <div class="text-muted small">
                            <?= (int)$t['total_items'] ?> артикула
                        </div>

                    </div>

                </div>

            </div>

        <?php endwhile; ?>

    </div>

</div>


<!-- TYPE IMAGE MODAL -->

<div class="modal fade" id="typeImageModal" tabindex="-1">

    <div class="modal-dialog modal-dialog-centered">

        <div class="modal-content">

            <div class="modal-header">

                <h5 class="modal-title" id="typeTitle">
                    Категория
                </h5>

                <button class="btn-close"
                        data-bs-dismiss="modal"></button>

            </div>

            <div class="modal-body text-center">

                <img id="typeImagePreview"
                     class="img-fluid mb-3 d-none"
                     style="max-height:350px;">

                <div id="typeNoImage"
                     class="text-muted">
                    Няма качена снимка
                </div>

                <input type="file"
                       id="typeImageUpload"
                       class="form-control mt-3">

            </div>

            <div class="modal-footer">

                <button class="btn btn-danger"
                        id="deleteTypeImage">
                    Изтрий
                </button>

                <button class="btn btn-success"
                        id="uploadTypeImage">
                    Качи
                </button>

            </div>

        </div>

    </div>

</div>

<script>

    $(document).on('click', '.typeCard', function(e){

        if($(e.target).closest('.editTypeImage').length){
            return;
        }

        const id = $(this).data('id');

        window.location =
            'dashboard.php?page=items_list&id_type=' + id;
    });

    let currentType = 0;
    let typeModal = null;

    $(document).on('click', '.editTypeImage', function(e){

        e.preventDefault();
        e.stopPropagation();

        currentType = $(this).data('id');

        const hasImage =
            parseInt($(this).data('hasimage'));

        $('#typeTitle').text(
            $(this).data('name')
        );

        $('#typeImagePreview')
            .attr('src','')
            .addClass('d-none');

        $('#typeNoImage')
            .removeClass('d-none');

        if(hasImage){

            $('#typeImagePreview')
                .attr(
                    'src',
                    'includes/type_image_get.php?id='+
                    currentType+'&t='+Date.now()
                )
                .removeClass('d-none');

            $('#typeNoImage')
                .addClass('d-none');
        }

        typeModal = bootstrap.Modal.getOrCreateInstance(
            document.getElementById('typeImageModal')
        );

        typeModal.show();
    });

    $('#uploadTypeImage').on('click', function(){

        const btn = $(this);

        const file =
            $('#typeImageUpload')[0].files[0];

        if(!file){
            alert('Избери снимка');
            return;
        }

        // LOADING STATE
        btn
            .prop('disabled', true)
            .html(
                '<i class="fa-solid fa-spinner fa-spin"></i> Качване...'
            );

        $('#deleteTypeImage').prop('disabled', true);

        let form = new FormData();

        form.append('id', currentType);
        form.append('image', file);

        $.ajax({

            url: 'includes/type_image_upload.php',
            type: 'POST',
            data: form,
            processData: false,
            contentType: false,
            dataType: 'json',

            success: function(resp){

                if(resp.success){

                    btn.html(
                        '<i class="fa-solid fa-check"></i> Запазено'
                    );

                    setTimeout(() => {
                        location.reload();
                    }, 700);

                } else {

                    btn
                        .prop('disabled', false)
                        .html('Качи');

                    $('#deleteTypeImage')
                        .prop('disabled', false);

                    alert('Грешка');
                }
            },

            error:function(){

                btn
                    .prop('disabled', false)
                    .html('Качи');

                $('#deleteTypeImage')
                    .prop('disabled', false);

                alert('Грешка при качване');
            }

        });

    });

    $('#deleteTypeImage').on('click', function(){

        if(!confirm('Сигурен ли си?')){
            return;
        }

        $.post(
            'includes/type_image_delete.php',
            {
                id: currentType
            },
            function(resp){

                if(resp.success){
                    location.reload();
                }

            },
            'json'
        );
    });

</script>