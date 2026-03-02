<div class="sidebar">
<nav class="navbar navbar-expand-lg navbar-dark bg-dark shadow-sm">
    <div class="container-fluid">

        <span class="navbar-brand fw-bold">
            iSales
        </span>

        <button class="navbar-toggler" type="button"
                data-bs-toggle="collapse"
                data-bs-target="#navbarScroll">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navbarScroll">

            <ul class="navbar-nav me-auto">

                <a href="dashboard.php?page=routes" class="nav-link">
                    <i class="fa-solid fa-route"></i> Маршрути
                </a>

                <a href="dashboard.php?page=orders" class="nav-link">
                    Reports
                </a>
                 <a href="dashboard.php?page=import_nomenclatures" class="nav-link">
                    Импорт
                </a>
                             <a href="dashboard.php?page=import_sales" class="nav-link">
                                Импорт продажби
                            </a>
            </ul>

            <div class="d-flex align-items-center gap-3">

                <span class="text-light" href="#">
                    <?= htmlspecialchars($_SESSION['username']); ?>
                </span>

                    <a href="logout.php" class="nav-link text-danger">
                        <i class="fa-solid fa-right-from-bracket"></i> Изход
                    </a>

            </div>

        </div>
    </div>
</nav>



</div>