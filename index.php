<?php
session_start();
require_once __DIR__ . '/config/config.php';

/*
|--------------------------------------------------------------------------
| Ако вече е логнат → директно към dashboard
|--------------------------------------------------------------------------
*/
if (!empty($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit;
}

$error = '';

/*
|--------------------------------------------------------------------------
| Login обработка
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($username === '' || $password === '') {
        $error = "Моля попълнете всички полета.";
    } else {

        // Връзка към alaska_system
        $db = db_connect('system');

        $stmt = $db->prepare("
            SELECT p.id AS id,
                   sa.id AS user_id,
                   sa.username AS username,
                   p.fname AS first_name,
                   p.lname AS last_name,
                   sa.id_profile AS admin
            FROM access_account sa
            LEFT JOIN ". DB_NAMES['personnel'] .".personnel p ON p.id = sa.id_person
            WHERE sa.to_arc = 0
              AND p.status = 'active'
              AND sa.username = ?
              AND sa.password = MD5(?)
            LIMIT 1
        ");

        if (!$stmt) {
            die("SQL Error: " . $db->error);
        }

        $stmt->bind_param("ss", $username, $password);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($user = $result->fetch_assoc()) {

            // Успешен login
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['is_admin'] = $user['admin'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['first_name'] = $user['first_name'];
            $_SESSION['last_name']  = $user['last_name'];
            header("Location: dashboard.php");
            exit;

        } else {
            $error = "Невалидно потребителско име или парола.";
        }

        $stmt->close();
        $db->close();
    }
}
?>
<!DOCTYPE html>
<html lang="bg" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <title>Login | iSales</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">

    <style>
        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--bs-body-bg);
        }

        .login-card {
            width: 420px;
            border-radius: 16px;
        }

        .login-logo {
            max-width: 220px;
        }
    </style>
</head>

<body>

<div class="card shadow-lg login-card border-0">

    <div class="card-body p-4">

        <div class="text-center mb-4">
            <img src="./assets/images/isales_logo.svg" class="login-logo mb-3">
<!--            <h5 class="fw-semibold">iSales System</h5>-->
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST">

            <div class="mb-3">
                <label class="form-label">Потребител</label>
                <div class="input-group">
                    <span class="input-group-text">
                        <i class="fa-solid fa-user"></i>
                    </span>
                    <input type="text" name="username" class="form-control" required>
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label">Парола</label>
                <div class="input-group">
                    <span class="input-group-text">
                        <i class="fa-solid fa-lock"></i>
                    </span>
                    <input type="password" name="password" class="form-control" required>
                </div>
            </div>

            <button class="btn btn-primary w-100">
                <i class="fa-solid fa-right-to-bracket me-2"></i> Вход
            </button>

        </form>

        <div class="text-center small text-muted mt-4">
            © <?= date('Y') ?> iSales
        </div>

    </div>
</div>

</body>
</html>