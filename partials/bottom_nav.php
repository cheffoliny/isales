<?php
$currentPage = $_GET['page'] ?? 'routes';
?>

<nav class="app-bottom-nav border-top bg-body shadow-lg">

    <a href="dashboard.php?page=routes"
       class="nav-item <?= $currentPage === 'routes' ? 'active' : '' ?>">
        <i class="fa-solid fa-route"></i>
        <span>Маршрути</span>
    </a>

    <a href="dashboard.php?page=orders"
       class="nav-item <?= $currentPage === 'orders' ? 'active' : '' ?>">
        <i class="fa-solid fa-file-lines"></i>
        <span>Заявки</span>
    </a>

<?php if($_SESSION['is_admin'] == 1) { ?>

 <!-- CENTER FLOAT BUTTON -->
    <div class="dropup center-action">
        <button class="btn action-btn dropdown-toggle"
                type="button"
                data-bs-toggle="dropdown"
                aria-expanded="false">
            <i class="fa-solid fa-gear"></i>
        </button>

        <ul class="dropdown-menu text-center shadow">
            <li>
                <a class="dropdown-item <?= $currentPage === 'import_nomenclatures' ? 'active' : '' ?>" href="dashboard.php?page=import_nomenclatures">
                    <i class="fa-solid fa-file-import me-2"></i> ИМПОРТ ДАННИ
                </a>
            </li>
            <li>
                <a class="dropdown-item" href="dashboard.php?page=users">
                    <i class="fa-solid fa-users me-2"></i> ПОТРЕБИТЕЛИ
                </a>
            </li>
        </ul>
    </div>

<?php } ?>
    <a href="dashboard.php?page=objects"
       class="nav-item <?= $currentPage === 'objects' ? 'active' : '' ?>">
        <i class="fa-solid fa-home"></i>
        <span>Обекти</span>
    </a>

    <a href="dashboard.php?page=items"
       class="nav-item <?= $currentPage === 'items' ? 'active' : '' ?>">
        <i class="fa-solid fa-tags"></i>
        <span>Артикули</span>
    </a>

</nav>