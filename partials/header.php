<?php

include_once __DIR__ . '/../includes/functions.php';

$currentPage = $_GET['page'] ?? 'routes';
$hasLowStock = hasLowStockWarnings();

$username  = htmlspecialchars($_SESSION['username'] ?? '');
$firstName = htmlspecialchars($_SESSION['first_name'] ?? '');

?>

<nav class="navbar navbar-dark bg-primary app-header shadow-sm">

    <div class="container-fluid position-relative d-flex justify-content-between align-items-center">

        <!-- LEFT -->
        <div class="d-flex align-items-center gap-2">

            <span class="fw-semibold"
                  style="cursor:pointer"
                  onclick="window.open('https://isales.daga2020.store')">
                iSales
            </span>

        </div>

        <!-- CENTER ALERT -->
        <?php if($_SESSION['is_admin'] == 1): ?>

            <div class="position-absolute top-50 start-50 translate-middle">

                <a href="dashboard.php?page=low_stock"
                   class="text-decoration-none">
                    <?php if($hasLowStock): ?>
                    <span class="badge bg-danger pulse-badge d-flex align-items-center gap-1 px-3 py-2">
                    <?php else: ?>
                    <span class="badge bg-secondary d-flex align-items-center gap-1 px-3 py-2">
                    <?php endif; ?>
                        <i class="fa-solid fa-triangle-exclamation"></i>
                        <span>
                            Ниска наличност
                        </span>
                    </span>
                </a>

            </div>

        <?php endif; ?>

        <!-- RIGHT -->
        <div class="d-flex align-items-center gap-2">

            <button id="themeToggle"
                    class="btn btn-sm btn-outline-light">
                <i class="fa-solid fa-moon"></i>
            </button>

            <div class="dropdown">

                <button class="btn btn-sm btn-outline-light dropdown-toggle"
                        data-bs-toggle="dropdown"
                        aria-expanded="false">

                    <i class="fa-solid fa-user me-1"></i>
                </button>

                <ul class="dropdown-menu dropdown-menu-end shadow border-0">

                    <li class="px-3 py-2 small text-secondary">

                        <div class="fw-semibold">
                            <?= $firstName ?>
                        </div>

                        <div>
                            [<?= $username ?>]
                        </div>

                    </li>

                    <li><hr class="dropdown-divider"></li>

                    <li>

                        <button type="button"
                                class="dropdown-item"
                                id="openChangePasswordModal">

                            <i class="fa-solid fa-user-gear me-2"></i>
                            Смяна на парола

                        </button>

                    </li>

                    <li>

                        <a class="dropdown-item text-danger"
                           href="logout.php">

                            <i class="fa-solid fa-right-from-bracket me-2"></i>
                            Изход

                        </a>

                    </li>

                </ul>

            </div>

        </div>

    </div>

</nav>


<!-- CHANGE PASSWORD MODAL -->

<div class="modal fade"
     id="changePasswordModal"
     tabindex="-1"
     aria-hidden="true">

    <div class="modal-dialog modal-dialog-centered">

        <div class="modal-content border-0 shadow">

            <div class="modal-header">

                <h5 class="modal-title">
                    Смяна на парола
                </h5>

                <button type="button"
                        class="btn-close"
                        data-bs-dismiss="modal">
                </button>

            </div>

            <div class="modal-body">

                <div class="mb-3">

                    <label class="form-label">
                        Текуща парола
                    </label>

                    <input type="password"
                           id="currentPassword"
                           class="form-control">

                </div>

                <div class="mb-3">

                    <label class="form-label">
                        Нова парола
                    </label>

                    <input type="password"
                           id="newPassword"
                           class="form-control">

                </div>

                <div class="mb-0">

                    <label class="form-label">
                        Повтори новата парола
                    </label>

                    <input type="password"
                           id="confirmPassword"
                           class="form-control">

                </div>

            </div>

            <div class="modal-footer">

                <button class="btn btn-secondary"
                        data-bs-dismiss="modal">

                    Затвори

                </button>

                <button class="btn btn-primary"
                        id="savePasswordBtn">

                    Запази

                </button>

            </div>

        </div>

    </div>

</div>