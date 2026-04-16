<?php
require_once __DIR__ . '/../config/config.php';

$conn = db_connect('storage');

function h($s) {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

/**
 * Разпознаване и конвертиране към UTF-8
 */
// function convertToUtf8($string) {
//
//     $string = preg_replace('/^\xEF\xBB\xBF/', '', $string);
//
//     $encoding = mb_detect_encoding(
//         $string,
//         ['UTF-8', 'Windows-1251', 'ISO-8859-1'],
//         true
//     );
//
//     if ($encoding === false) {
//         $encoding = 'Windows-1251';
//     }
//
//     if ($encoding !== 'UTF-8') {
//         $string = mb_convert_encoding($string, 'UTF-8', $encoding);
//     }
//
//     return $string;
// }
/**
 * Стабилно разпознаване и конвертиране към UTF-8
 */
function convertToUtf8($string) {

    if (!is_string($string)) {
        return '';
    }

    // маха BOM
    $string = preg_replace('/^\xEF\xBB\xBF/', '', $string);

    // бърза проверка - ако вече е UTF-8 OK, не пипаме
    if (mb_check_encoding($string, 'UTF-8')) {
        return $string;
    }

    // fallback конверсия (без "auto")
    $converted = @mb_convert_encoding(
        $string,
        'UTF-8',
        'Windows-1251, ISO-8859-1, CP1252'
    );

    if ($converted === false) {
        return '';
    }

    // чистим control chars (SAFE)
    $converted = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $converted);

    return trim($converted);
}


if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    /**
     * =========================
     * ИМПОРТ НА АРТИКУЛИ
     * =========================
     */
    if (!isset($_POST['import_objects'])) {

        if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            echo "<div class='alert alert-danger'>Грешка при качване на файла.</div>";
            return;
        }

        $tmpPath = $_FILES['file']['tmp_name'];

        $handle = fopen($tmpPath, "r");
        if (!$handle) {
            echo "<div class='alert alert-danger'>Не може да се отвори файлът.</div>";
            return;
        }

        $conn->begin_transaction();

        $batchSize = 500;
        $batchData = [];
        $imported = 0;
        $skipped  = 0;

        try {

            while (($line = fgets($handle)) !== false) {

                $rawLine = $line;

                if (trim($line) === '') {
                    continue;
                }

                $line = convertToUtf8($line);
                $line = preg_replace('/[ \t]+/u', ' ', trim($line));

                /**
                 * =========================
                 * DEBUG ONLY
                 * =========================
                 */
                if (strpos($line, '010267') !== false) {

                    echo "<pre style='background:#111;color:#0f0;padding:10px;font-size:12px;'>";
                    echo "================ DEBUG START ================\n";
                    echo "RAW:\n";
                    var_dump($rawLine);

                    echo "\nUTF8:\n";
                    var_dump($line);

                    echo "\nHEX:\n";
                    echo bin2hex($line) . "\n";

                    echo "\nLEN:\n" . strlen($line) . "\n";

                    echo "================ DEBUG END ================\n";
                    echo "</pre>";
                }

                /**
                 * =====================================================
                 * 🔥 1. FIXED WIDTH PARSER (PRIMARY)
                 * =====================================================
                 */

                $code = trim(substr($line, 0, 10));
                $rest = trim(substr($line, 10));

                if (!ctype_digit($code)) {
                    $skipped++;
                    continue;
                }

                $id = 1000000000 + (int)$code;

                /**
                 * split remainder
                 */
                $parts = preg_split('/\s+/u', $rest, -1, PREG_SPLIT_NO_EMPTY);

                if (count($parts) < 4) {
                    $skipped++;
                    continue;
                }

                /**
                 * last 2 are numeric
                 */
                $client_price = str_replace(',', '.', array_pop($parts));
                $is_calc      = str_replace(',', '.', array_pop($parts));

                if (!is_numeric($client_price) || !is_numeric($is_calc)) {

                    /**
                     * =====================================================
                     * 🔥 2. FALLBACK PARSER (token alignment fix)
                     * =====================================================
                     */

                    $cols = preg_split('/\s+/u', $line, -1, PREG_SPLIT_NO_EMPTY);

                    if (count($cols) < 6) {
                        $skipped++;
                        continue;
                    }

                    $code = $cols[0];

                    if (!ctype_digit($code)) {
                        $skipped++;
                        continue;
                    }

                    $id = 1000000000 + (int)$code;

                    $client_price = str_replace(',', '.', array_pop($cols));
                    $is_calc      = str_replace(',', '.', array_pop($cols));

                    $unit = array_pop($cols);
                    $name = implode(' ', array_slice($cols, 1));

                } else {

                    /**
                     * normal fixed-width path
                     */
                    $unit = array_pop($parts);
                    $name = implode(' ', $parts);
                }

                if ($name === '' || $unit === '') {
                    $skipped++;
                    continue;
                }

                $batchData[] = [
                    $id,
                    $code,
                    $name,
                    $unit,
                    (float)$is_calc,
                    (float)$client_price
                ];

                if (count($batchData) >= $batchSize) {
                    insertBatch($conn, $batchData);
                    $imported += count($batchData);
                    $batchData = [];
                }
            }

            if (!empty($batchData)) {
                insertBatch($conn, $batchData);
                $imported += count($batchData);
            }

            fclose($handle);
            $conn->commit();

            echo "<div class='alert alert-success'>";
            echo "<h5>Импортът завърши успешно!</h5>";
            echo "Импортирани: <b>" . h($imported) . "</b><br>";
            echo "Пропуснати: <b>" . h($skipped) . "</b>";
            echo "</div>";

        } catch (Exception $e) {
            $conn->rollback();
            echo "<div class='alert alert-danger'>Грешка: " . h($e->getMessage()) . "</div>";
        }
    }

    /**
     * =========================
     * ИМПОРТ НА ОБЕКТИ
     * =========================
     */
    if (isset($_POST['import_objects'])) {

        if (!isset($_FILES['objects_file']) || $_FILES['objects_file']['error'] !== UPLOAD_ERR_OK) {
            echo "<div class='alert alert-danger'>Грешка при качване на файла.</div>";
            return;
        }

        $handle = fopen($_FILES['objects_file']['tmp_name'], "r");

        if (!$handle) {
            echo "<div class='alert alert-danger'>Не може да се отвори файлът.</div>";
            return;
        }

        $conn->begin_transaction();

        $batchSize = 500;
        $batchData = [];

        $imported = 0;
        $skipped  = 0;

        $officeCounter = 1;

        try {

            while (($line = fgets($handle)) !== false) {

                // normalize
                $line = convertToUtf8($line);
                $line = preg_replace('/[ \t]+/u', ' ', trim($line));

                $cols = preg_split('/\s+/u', $line, -1, PREG_SPLIT_NO_EMPTY);

                if (!is_array($cols) || count($cols) < 6) {
                    $skipped++;
                    continue;
                }

                // CODE
                $code = array_shift($cols);
                if (!ctype_digit($code)) {
                    $skipped++;
                    continue;
                }

                // =======================
                // FIXED ANCHOR PARSING
                // =======================

                // винаги последните 3 са стабилни
                $total   = array_pop($cols);   // игнорираме
                $price   = array_pop($cols);
                $qty     = array_pop($cols);
                $unit    = array_pop($cols);

                // NAME = всичко между code и unit
                $name = trim(implode(' ', $cols));

                if ($name === '' || $unit === '') {
                    $skipped++;
                    continue;
                }

                $price = str_replace(',', '.', $price);
                $qty   = str_replace(',', '.', $qty);

                if (!is_numeric($price) || !is_numeric($qty)) {
                    $skipped++;
                    continue;
                }

                $id = 1000000000 + (int)$code;

                $batchData[] = [
                    $id,
                    $code,
                    $name,
                    $unit,
                    (float)$qty,
                    (float)$price
                ];

                $officeCounter++;
                if ($officeCounter > 10) {
                    $officeCounter = 1;
                }

                if (count($batchData) >= $batchSize) {
                    insertObjectsBatch($conn, $batchData);
                    $imported += count($batchData);
                    $batchData = [];
                }
            }

            if (!empty($batchData)) {
                insertObjectsBatch($conn, $batchData);
                $imported += count($batchData);
            }

            fclose($handle);
            $conn->commit();

            echo "<div class='alert alert-success'>";
            echo "<h5>Импортът на обекти завърши успешно!</h5>";
            echo "Импортирани: <b>" . h($imported) . "</b><br>";
            echo "Пропуснати: <b>" . h($skipped) . "</b>";
            echo "</div>";

        } catch (Exception $e) {
            $conn->rollback();
            echo "<div class='alert alert-danger'>Грешка: " . h($e->getMessage()) . "</div>";
        }
    }
}

/**
 * Batch REPLACE за артикули
 */
function insertBatch($conn, $data) {

    $placeholders = [];
    $types = '';
    $values = [];

    foreach ($data as $row) {
        $placeholders[] = "(?, ?, ?, ?, ?, ?)";
        $types .= 'isssdd';
        $values = array_merge($values, $row);
    }

   // $sql = "
     //   REPLACE INTO nomenclatures
       // (id, nom_code, name, unit, is_calc, client_price)
        //VALUES " . implode(',', $placeholders);
        $sql = "
            INSERT INTO nomenclatures
            (id, nom_code, name, unit, is_calc, client_price)
            VALUES " . implode(',', $placeholders) . "
            ON DUPLICATE KEY UPDATE
                nom_code = VALUES(nom_code),
                name = VALUES(name),
                unit = VALUES(unit),
                is_calc = VALUES(is_calc),
                client_price = VALUES(client_price)
        ";

    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        throw new Exception($conn->error);
    }

    $stmt->bind_param($types, ...$values);

    if (!$stmt->execute()) {
        throw new Exception($stmt->error);
    }

    $stmt->close();
}

/**
 * Batch REPLACE за обекти
 */
function insertObjectsBatch($conn, $data) {

    $placeholders = [];
    $types = '';
    $values = [];

    foreach ($data as $row) {
        $placeholders[] = "(?, ?, ?, ?, ?)";
        $types .= 'isiii';
        $values = array_merge($values, $row);
    }

    $sql = "
        REPLACE INTO ". DB_NAMES['sod'] .".objects
        (id, name, num, id_status, id_office)
        VALUES " . implode(',', $placeholders);

    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        throw new Exception($conn->error);
    }

    $stmt->bind_param($types, ...$values);

    if (!$stmt->execute()) {
        throw new Exception($stmt->error);
    }

    $stmt->close();
}
?>

<div class="box">
    <h2>Импорт на TXT файл</h2>

    <form method="post" enctype="multipart/form-data">
        <div class="row mb-3">
            <div class="col-12 d-flex gap-2">
                <input type="file"
                       class="form-control form-control-sm py-2"
                       name="file"
                       accept=".txt"
                       required>

                <button type="submit" class="btn btn-sm btn-danger">
                    Импортирай артикули
                </button>
            </div>
        </div>
    </form>

    <p style="font-size:12px;color:#666;">
        ✔ Поддържа: UTF-8, Windows-1251, ISO-8859-1<br>
        ✔ Формат: nom_code | name | unit | is_calc | client_price
    </p>
</div>

<div class="box mt-4">
    <h2>Импорт на обекти (TXT)</h2>

    <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="import_objects" value="1">

        <div class="row mb-3">
            <div class="col-12 d-flex gap-2">
                <input type="file"
                       class="form-control form-control-sm py-2"
                       name="objects_file"
                       accept=".txt"
                       required>

                <button type="submit" class="btn btn-sm btn-primary">
                    Импортирай обекти
                </button>
            </div>
        </div>
    </form>
</div>