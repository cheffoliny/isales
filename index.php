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
                   sa.has_debug AS admin
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
<html lang="bg">
<head>
    <meta charset="UTF-8">
    <title>Login | iSales</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        body {
            margin: 0;
            height: 100vh;
            background: #0f1117;
            display: flex;
            justify-content: center;
            align-items: center;
            font-family: 'Inter', sans-serif;
            color: #fff;
        }

        .login-card {
            background: #161a23;
            padding: 45px;
            width: 380px;
            border-radius: 20px;
            border: 1px solid rgba(255,255,255,0.05);
            box-shadow: 0 0 40px rgba(0,0,0,0.6);
        }

        .login-title {
            text-align: center;
            margin-bottom: 30px;
            font-size: 22px;
            font-weight: 600;
            color: #4f8cff;
        }

        .form-control {
            background: #0f1117;
            border: 1px solid rgba(255,255,255,0.08);
            color: #fff;
            border-radius: 12px;
            padding: 12px;
        }

        .form-control:focus {
            background: #0f1117;
            border-color: #4f8cff;
            box-shadow: 0 0 10px rgba(79,140,255,0.5);
            color: #fff;
        }

        .btn-login {
            width: 100%;
            padding: 12px;
            border-radius: 12px;
            border: none;
            background: #4f8cff;
            font-weight: 500;
            margin-top: 10px;
        }

        .btn-login:hover {
            box-shadow: 0 0 15px rgba(79,140,255,0.6);
        }

        .error-box {
            background: rgba(255,0,0,0.08);
            border: 1px solid rgba(255,0,0,0.3);
            padding: 10px;
            border-radius: 10px;
            margin-bottom: 15px;
            font-size: 14px;
        }

        .footer-text {
            text-align: center;
            margin-top: 20px;
            font-size: 12px;
            color: #888;
        }
    </style>
</head>
<body>

<div class="login-card">

    <div class="login-title">
        iSales System
    </div>

    <?php if ($error): ?>
        <div class="error-box">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <form method="POST" autocomplete="off">

        <div class="mb-3">
            <input type="text"
                   name="username"
                   class="form-control"
                   placeholder="Потребител"
                   required>
        </div>

        <div class="mb-3">
            <input type="password"
                   name="password"
                   class="form-control"
                   placeholder="Парола"
                   required>
        </div>

        <button type="submit" class="btn-login">
            Вход
        </button>

    </form>

    <div class="footer-text">
        © <?= date('Y') ?> iSales
    </div>

</div>

</body>
</html>