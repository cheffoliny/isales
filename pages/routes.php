<?php
if (empty($_SESSION['user_id'])) {
    echo '<div class="alert alert-danger m-3">Нямате достъп.</div>';
    exit;
}

$db = db_connect('sod');

// Взимаме всички офиси и броя на обектите и посетените
$stmt = $db->prepare("
    SELECT
        offs.id AS offs_id,
        offs.name AS offs_name,
        COUNT(o.id) AS obj_count,
        SUM(IF(pe.id > 0, 1, 0)) AS obj_visited
    FROM objects o
    INNER JOIN offices offs ON offs.id = o.id_office
    LEFT JOIN alaska_storage.ppp p
        ON p.id_dest = o.id
        AND DATE(p.source_date) = CURDATE()
    LEFT JOIN alaska_storage.ppp_elements pe
        ON pe.id_ppp = p.id
        AND pe.count > 1
    WHERE o.id_status = 1
    GROUP BY offs.id, offs.name
    ORDER BY offs.name ASC
");

$stmt->execute();
$result = $stmt->get_result();

if (!$result || $result->num_rows === 0) {
    echo '<div class="alert alert-warning text-center m-3">Няма намерени маршрути!</div>';
    exit;
}
?>

<div class="container-fluid px-2">
    <div class="list-group list-group-flush route-list">

        <?php while ($row = $result->fetch_assoc()):
            $officeId = (int)$row['offs_id'];
            $officeName = htmlspecialchars($row['offs_name']);
            $objectCount = (int)$row['obj_count'];
            $objectVisited = (int)$row['obj_visited'];

            $percentage = $objectCount > 0 ? round(($objectVisited / $objectCount) * 100) : 0;

            // Цветове
            if ($objectVisited === 0) {
                $statusClass = 'route-danger';
                $badgeClass = 'bg-danger';
                $iconClass = 'text-danger';
                $progressClass = 'bg-danger';
            } elseif ($percentage < 50) {
                $statusClass = 'route-warning';
                $badgeClass = 'bg-warning text-dark';
                $iconClass = 'text-warning';
                $progressClass = 'bg-warning';
            } else {
                $statusClass = 'route-success';
                $badgeClass = 'bg-success';
                $iconClass = 'text-success';
                $progressClass = 'bg-success';
            }
            ?>

            <div class="list-group-item d-flex flex-column route-card <?= $statusClass ?>">

                <div class="d-flex align-items-center justify-content-between mb-2">

                    <div class="d-flex align-items-center gap-3">

                        <!-- Иконата: бутон само за администратори -->
                        <?php if (!empty($_SESSION['is_admin']) && $_SESSION['is_admin'] == 1): ?>
                            <button type="button"
                                    class="route-icon <?= $iconClass ?> openEditOffice"
                                    data-id="<?= $officeId ?>"
                                    data-name="<?= $officeName ?>"
                                    style="border:none; background:none; padding:0;">
                                <i class="fa-solid fa-route"></i>
                            </button>
                        <?php else: ?>
                            <div class="route-icon <?= $iconClass ?>">
                                <i class="fa-solid fa-route"></i>
                            </div>
                        <?php endif; ?>

                        <!-- Име на офиса -->
                        <a href="dashboard.php?page=route_objects&id=<?= $officeId ?>"
                           class="fw-semibold fs-5 text-decoration-none">
                            <?= $officeName ?>
                        </a>

                    </div>

                    <span class="badge rounded-pill <?= $badgeClass ?> fs-6">
                    <?= $objectVisited ?> / <?= $objectCount ?>
                </span>

                </div>

                <!-- Progress bar -->
                <div class="d-flex align-items-center gap-2">
                    <div class="progress flex-grow-1" style="height: 8px;">
                        <div class="progress-bar <?= $progressClass ?>" role="progressbar"
                             style="width: <?= $percentage ?>%;"
                             aria-valuenow="<?= $percentage ?>" aria-valuemin="0" aria-valuemax="100">
                        </div>
                    </div>
                    <span class="fw-semibold fs-6 text-nowrap"><?= $percentage ?>%</span>
                </div>

            </div>

        <?php endwhile; ?>
    </div>
</div>

<!-- ================= MODAL EDIT OFFICE ================= -->
<div class="modal fade" id="editOfficeModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Редакция на офис</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="edit_office_id">
                <div class="mb-3">
                    <label class="form-label">Име на офис</label>
                    <input type="text" class="form-control" id="edit_office_name">
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal">Затвори</button>
                <button class="btn btn-success" id="saveOfficeBtn">Запиши</button>
            </div>
        </div>
    </div>
</div>

<script>
    $(document).ready(function(){
        $(document).on("click", ".openEditOffice", function(e){
            e.preventDefault();
            let id = $(this).data("id");
            let name = $(this).data("name");
            $("#edit_office_id").val(id);
            $("#edit_office_name").val(name);
            let modal = new bootstrap.Modal(document.getElementById("editOfficeModal"));
            modal.show();
        });

        $("#saveOfficeBtn").click(function(){
            let id = $("#edit_office_id").val();
            let name = $("#edit_office_name").val();
            $.post("includes/update_offices.php", {id:id, name:name}, function(resp){
                if(resp.success){ location.reload(); }
                else { alert("Грешка при запис на офис!"); }
            }, "json");
        });
    });
</script>