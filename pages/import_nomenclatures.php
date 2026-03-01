<?php
// ================= НАСТРОЙКИ =================
$host = "intelli.shumen.ddns.bulsat.com";
$db   = "alaska_storage";
$user = "intellisql";
$pass = "1nt3ll1dbas3";

//$delimiter = "|"; // ако е табулация: "\t"
$delimiter = "\t";
// ============================================

function h($s) {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        die("Грешка при качване на файла.");
    }

    $tmpPath = $_FILES['file']['tmp_name'];

    try {

        $pdo = new PDO(
            "mysql:host=$host;dbname=$db;charset=utf8mb4",
            $user,
            $pass,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
            ]
        );

        $handle = fopen($tmpPath, "r");
        if (!$handle) {
            die("Не може да се отвори файлът.");
        }

        $pdo->beginTransaction();

        $batchSize = 500;
        $batchData = [];
        $imported = 0;
        $skipped  = 0;

        while (($line = fgets($handle)) !== false) {

            if (trim($line) === '') continue;

            // Windows-1251 → UTF-8
            $line = mb_convert_encoding($line, "UTF-8", "Windows-1251");
            $line = trim($line);

            // Разделяне по 2 или повече интервала
            $cols = preg_split('/\s{2,}/u', $line);

            if (count($cols) < 5) {
                $skipped++;
                continue;
            }

            $nom_code = trim($cols[0]);
            $name     = trim($cols[1]);
            $unit     = trim($cols[2]);

            $is_calc      = str_replace(",", ".", trim($cols[3]));
            $client_price = str_replace(",", ".", trim($cols[4]));

            if (!is_numeric($client_price)) {
                $skipped++;
                continue;
            }

            $batchData[] = [
                $nom_code,
                $name,
                $unit,
                (float)$is_calc,
                (float)$client_price
            ];

            if (count($batchData) >= $batchSize) {
                insertBatch($pdo, $batchData);
                $imported += count($batchData);
                $batchData = [];
            }
        }

        // Последна партида
        if (!empty($batchData)) {
            insertBatch($pdo, $batchData);
            $imported += count($batchData);
        }

        fclose($handle);
        $pdo->commit();

        echo "<h3>Импортът завърши успешно!</h3>";
        echo "Импортирани записи: <b>" . h($imported) . "</b><br>";
        echo "Пропуснати редове: <b>" . h($skipped) . "</b><br>";
        echo '<br><a href="">Нов импорт</a>';

    } catch (Exception $e) {
        if (isset($pdo)) $pdo->rollBack();
        die("Грешка: " . $e->getMessage());
    }

    exit;
}


// ================= ФУНКЦИЯ ЗА BATCH INSERT =================
function insertBatch($pdo, $data) {

    $placeholders = [];
    $values = [];

    foreach ($data as $row) {
        $placeholders[] = "(?, ?, ?, ?, ?)";
        $values = array_merge($values, $row);
    }

    $sql = "
        INSERT INTO nomenclatures
        (nom_code, name, unit, is_calc, client_price)
        VALUES " . implode(",", $placeholders) . "
        ON DUPLICATE KEY UPDATE
            name = VALUES(name),
            unit = VALUES(unit),
            is_calc = VALUES(is_calc),
            client_price = VALUES(client_price)
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($values);
}
?>


<div class="box">
    <h2>Импорт на TXT файл</h2>
    <form method="post" enctype="multipart/form-data">
        <div class="row mb-3">
            <div class="col-12 d-flex gap-2">
                <input type="file" class="form-control form-control-sm" name="file" accept=".txt" required><br>
                <button type="submit"  class="btn btn-sm btn-danger">
                    <i class="fa-solid fa-plus"></i>
                    Импортирай
                </button>
            </div>
        </div>


    </form>
    <p style="font-size:12px;color:#666;">
        Файлът трябва да е TXT (Windows-1251)<br>
        Колони: nom_code | name | unit | is_calc | client_price | (последната се игнорира)
    </p>
</div>
