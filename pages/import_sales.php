<?php
declare(strict_types=1);

function h($s) {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

// ======================
// CONFIGURATION
// ======================
$host = "intelli.shumen.ddns.bulsat.com";
$db   = "alaska_storage";
$username = "intellisql";
$password = "1nt3ll1dbas3";

$dsn = "mysql:host=$host;dbname=$db;charset=utf8mb4";


$inputFile = __DIR__ . "/file.txt";
$errorLog  = __DIR__ . "/import_errors.log";

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
// VALIDATIONS
// ======================

if (!file_exists($inputFile)) {
    die("Input file not found.");
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
    die("DB Connection failed: " . $e->getMessage());
}

// ======================
// PREPARE INSERT
// ======================

$insertStmt = $pdo->prepare("
    INSERT INTO sales_ppp (customer, nom_code, nom_name, qty, price_dds)
    VALUES (:customer, :nom_code, :nom_name, :qty, :price_dds)
");

// ======================
// IMPORT PROCESS
// ======================

$currentCustomer = '';
$insertedRows = 0;

try {

    $pdo->beginTransaction();

    $handle = fopen($inputFile, "r");

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

        // Process product rows
        if (substr_count($line, '|') >= 2) {

            $parts = explode('|', $line);

            if (count($parts) < 3) {
                logError("Invalid format: " . $line);
                continue;
            }

            $leftPart = trim($parts[0]);
            $qtyRaw   = trim($parts[1]);
            $priceRaw = trim($parts[2]);

            // Extract nom_code and rest
            if (!preg_match('/^(\d+)\s+(.*)$/u', $leftPart, $matches)) {
                logError("Code parsing failed: " . $line);
                continue;
            }

            $nom_code = trim($matches[1]);
            $rest     = trim($matches[2]);

            // Remove measurement units from end
            $nom_name = preg_replace('/\s+(БР\.?|БРОЙ|КУТ\.?|БР)\s*$/u', '', $rest);

            // Normalize numeric values
            $qty   = (float) str_replace(',', '.', $qtyRaw);
            $price = (float) str_replace(',', '.', $priceRaw);

            if ($qty <= 0 || $price < 0) {
                logError("Invalid numeric values: " . $line);
                continue;
            }

            // Insert
            $insertStmt->execute([
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

    echo "Import completed successfully. Inserted rows: {$insertedRows}" . PHP_EOL;

} catch (Throwable $e) {

    $pdo->rollBack();
    logError("Transaction rolled back: " . $e->getMessage());
    die("Import failed. Check error log.");
}