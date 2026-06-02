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

<?php if($_SESSION['is_admin'] == 1 || $_SESSION['is_admin'] == 4) { ?>

 <!-- CENTER FLOAT BUTTON -->
    <div class="dropup center-action">
        <button class="btn action-btn dropdown-toggle"
                type="button"
                data-bs-toggle="dropdown"
                aria-expanded="false">
            <i class="fa-solid fa-gear"></i>
        </button>

        <ul class="dropdown-menu text-center shadow">
            <?php if($_SESSION['is_admin'] == 1)  { ?>
            <li>
                <a class="dropdown-item <?= $currentPage === 'products_top' ? 'active' : '' ?>" href="dashboard.php?page=products_top">
                    <i class="fa-solid fa-ranking-star text-success me-2"></i> НАЙ-ПРОДАВАНИ
                </a>
            </li>
            <li>
                <a class="dropdown-item <?= $currentPage === 'products_slow' ? 'active' : '' ?>" href="dashboard.php?page=products_slow">
                    <i class="fa-regular fa-star text-danger me-2"></i> НЕПРОДАВАНИ
                </a>
            </li>
            <li>
                <a class="dropdown-item <?= $currentPage === 'products_profit' ? 'active' : '' ?>" href="dashboard.php?page=products_profit">
                    <i class="fa-solid fa-cloud-sun text-warning  me-2"></i> ПРОГНОЗA
                </a>
            </li>
            <li>
                <a class="dropdown-item <?= $currentPage === 'sales_analysis' ? 'active' : '' ?>" href="dashboard.php?page=sales_analysis">
                    <i class="fa-solid fa-chart-line me-2"></i> АНАЛИЗ ПРОДАЖБИ
                </a>
            </li>
            <li>
                <a class="dropdown-item" href="dashboard.php?page=users">
                    <i class="fa-solid fa-users me-2"></i> ПОТРЕБИТЕЛИ
                </a>
            </li>
            <?php } ?>
            <li>
                <a class="dropdown-item <?= $currentPage === 'import_nomenclatures' ? 'active' : '' ?>" href="dashboard.php?page=import_nomenclatures">
                    <i class="fa-solid fa-file-import me-2"></i> ИМПОРТ ДАННИ
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