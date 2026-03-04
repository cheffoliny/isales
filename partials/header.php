<?php
$currentPage = $_GET['page'] ?? 'routes';
?>

<nav class="navbar navbar-dark bg-primary app-header shadow-sm">

    <div class="container-fluid d-flex justify-content-between">

        <span class="fw-semibold">
            iSales
        </span>

        <div class="d-flex align-items-center gap-2">

            <button id="themeToggle"
                    class="btn btn-sm btn-outline-light">
                <i class="fa-solid fa-moon"></i>
            </button>

            <div class="dropdown">
                <button class="btn btn-sm btn-outline-light dropdown-toggle"
                        data-bs-toggle="dropdown">
                    <i class="fa-solid fa-user"></i>
                </button>

                <ul class="dropdown-menu dropdown-menu-end shadow">

                    <li class="dropdown-item-text small text-muted">
                        <?= htmlspecialchars($_SESSION['username']); ?>
                    </li>

                    <li><hr class="dropdown-divider"></li>

                    <li>
                        <a class="dropdown-item text-danger" href="logout.php">
                            <i class="fa-solid fa-right-from-bracket me-2"></i>
                            Изход
                        </a>
                    </li>

                </ul>
            </div>

        </div>

    </div>

</nav>