<?php
declare(strict_types=1);

function h($s) {
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

// ======================
// CONFIGURATION
// ======================

$host = "intelli.shumen.ddns.bulsat.com";
$db   = "alaska_storage";
$username = "intellisql";
$password = "1nt3ll1dbas3";

$dsn = "mysql:host=$host;dbname=$db;charset=utf8mb4";

$errorLog  = __DIR__ . "/import_errors.log";
$maxFileSize = 5 * 1024 * 1024; // 5MB limit

// ======================
// ERROR HANDLING
// ======================

ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', $errorLog);

function logError(string $message): void {
    global $errorLog;
    file_put_contents($errorLog, date('Y-m-d H:i:s') . " - " . $message . PHP_EOL, FILE_APPEND);
}

// ======================
// DB CONNECTION
// ======================

try {
    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    die("DB Connection failed.");
}

$message = '';
$insertedRows = 0;

// ======================
// PROCESS UPLOAD
// ======================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!isset($_FILES['import_file']) || $_FILES['import_file']['error'] !== UPLOAD_ERR_OK) {
        $message = "Upload error.";
    } elseif ($_FILES['import_file']['size'] > $maxFileSize) {
        $message = "File too large.";
    } else {

        $tmpPath = $_FILES['import_file']['tmp_name'];

        $currentCustomer = '';

        try {

            $pdo->beginTransaction();

            $handle = fopen($tmpPath, "r");

            if (!$handle) {
                throw new RuntimeException("Cannot open uploaded file.");
            }

            while (($line = fgets($handle)) !== false) {

                $line = trim($line);

                if ($line === '') {
                    continue;
                }

                // Detect customer
                if (preg_match('/Купувач\s+\d+\s*(.*)/u', $line, $matches)) {
                    $currentCustomer = trim($matches[1]);
                    if ($currentCustomer === '') {
                        $currentCustomer = '0';
                    }
                    continue;
                }

                // Skip unwanted rows
                if (
                    str_contains($line, '----') ||
                    str_contains($line, 'Общо за') ||
                    str_contains($line, 'Код и наименование') ||
                    str_contains($line, 'стр.')
                ) {
                    continue;
                }

                // Product rows
                if (substr_count($line, '|') >= 2) {

                    $parts = explode('|', $line);

                    if (count($parts) < 3) {
                        logError("Invalid format: " . $line);
                        continue;
                    }

                    $leftPart = trim($parts[0]);
                    $qtyRaw   = trim($parts[1]);
                    $priceRaw = trim($parts[2]);

                    if (!preg_match('/^(\d+)\s+(.*)$/u', $leftPart, $matches)) {
                        logError("Code parsing failed: " . $line);
                        continue;
                    }

                    $nom_code = trim($matches[1]);
                    $rest     = trim($matches[2]);

                    $nom_name = preg_replace('/\s+(БР\.?|БРОЙ|КУТ\.?|БР)\s*$/u', '', $rest);

                    $qty   = (float) str_replace(',', '.', $qtyRaw);
                    $price = (float) str_replace(',', '.', $priceRaw);

                    if ($qty <= 0 || $price < 0) {
                        logError("Invalid numeric values: " . $line);
                        continue;
                    }

                    $stmt = $pdo->prepare("
                        INSERT INTO sales_ppp (customer, nom_code, nom_name, qty, price_dds)
                        VALUES (:customer, :nom_code, :nom_name, :qty, :price_dds)
                    ");

                    $stmt->execute([
                        ':customer'  => $currentCustomer,
                        ':nom_code'  => $nom_code,
                        ':nom_name'  => trim($nom_name),
                        ':qty'       => $qty,
                        ':price_dds' => $price,
                    ]);

                    $insertedRows++;
                }
            }

            fclose($handle);
            $pdo->commit();

            $message = "Import successful. Inserted rows: " . $insertedRows;

        } catch (Throwable $e) {

            $pdo->rollBack();

            echo "<pre>";
            echo $e->getMessage();
            echo "</pre>";
            exit;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="bg">
<head>
<meta charset="UTF-8">
<title>Import Sales PPP</title>
<style>
body { font-family: Arial; padding:40px; background:#f4f4f4; }
.box { background:#fff; padding:30px; border-radius:8px; max-width:500px; }
button { padding:10px 15px; }
.message { margin-top:20px; font-weight:bold; }
</style>
</head>
<body>

<div class="box">
<h2>Upload Sales File</h2>

<form method="post" enctype="multipart/form-data">
    <input type="file" name="import_file" accept=".txt" required>
    <br><br>
    <button type="submit">Import</button>
</form>

<?php if ($message): ?>
    <div class="message"><?= h($message) ?></div>
<?php endif; ?>

</div>

</body>
</html>