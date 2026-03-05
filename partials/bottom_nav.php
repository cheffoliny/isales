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
        <span>Reports</span>
    </a>

    <a href="dashboard.php?page=import_nomenclatures"
       class="nav-item <?= $currentPage === 'import_nomenclatures' ? 'active' : '' ?>">
        <i class="fa-solid fa-file-import"></i>
        <span>Импорт</span>
    </a>

    <a href="dashboard.php?page=items"
       class="nav-item <?= $currentPage === 'items' ? 'active' : '' ?>">
        <i class="fa-solid fa-tags"></i>
        <span>Артикули</span>
    </a>
<!--
    <a href="dashboard.php?page=import_sales"
       class="nav-item <?= $currentPage === 'import_sales' ? 'active' : '' ?>">
        <i class="fa-solid fa-cart-plus"></i>
        <span>Продажби</span>
    </a>
-->
</nav>