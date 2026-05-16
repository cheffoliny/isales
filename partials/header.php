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

                    <li>
                        <button type="button"
                                class="dropdown-item"
                                id="openChangePasswordModal">

                            <i class="fa-solid fa-user-gear me-2"></i>

                            <?= '[' . htmlspecialchars($_SESSION['username']) . '] ' . htmlspecialchars($_SESSION['first_name']); ?>

                        </button>
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

<div class="modal fade" id="changePasswordModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
       <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    Смяна на парола
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">
                        Текуща парола
                    </label>
                    <input type="password" id="currentPassword" class="form-control">
                </div>
                <div class="mb-3">
                    <label class="form-label">
                        Нова парола
                    </label>
                    <input type="password" id="newPassword" class="form-control">
                </div>
                <div class="mb-3">
                    <label class="form-label">
                        Повтори новата парола
                    </label>
                    <input type="password" id="confirmPassword" class="form-control">
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal">
                    Затвори
                </button>
                <button class="btn btn-primary" id="savePasswordBtn">
                    Запази
                </button>
            </div>
        </div>
    </div>
</div>