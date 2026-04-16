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

                $rawLine = $line; // пазим оригинала

                if (trim($line) === '') {
                    continue;
                }

                $line = convertToUtf8($line);

                if (strpos($line, '010267') !== false) {

                    echo "<pre style='background:#111;color:#0f0;padding:10px;font-size:12px;'>";
                    echo "================ DEBUG START ================\n";

                    echo "RAW LINE:\n";
                    var_dump($rawLine);

                    echo "\nUTF8 LINE:\n";
                    var_dump($line);

                    echo "\nHEX:\n";
                    echo bin2hex($line) . "\n";

                    echo "\nMB CHECK UTF-8:\n";
                    var_dump(mb_check_encoding($line, 'UTF-8'));

                    echo "\nNORMALIZED (spaces):\n";
                    $norm = preg_replace('/[ \t]+/u', ' ', trim($line));
                    var_dump($norm);

                    echo "\nSPLIT RESULT:\n";
                    $cols = preg_split('/\s+/u', trim($norm));
                    var_dump($cols);

                    if (!is_array($cols)) {
                        echo "❌ SPLIT FAILED (FALSE returned)\n";
                    } else {
                        echo "COUNT: " . count($cols) . "\n";

                        echo "\nINDEX CHECK:\n";
                        foreach ($cols as $i => $c) {
                            echo "[$i] => " . $c . " (" . bin2hex($c) . ")\n";
                        }
                    }

                    echo "================ DEBUG END ================\n</pre>";
                }

                // ако split е счупен → пропускаме безопасно
                $line = preg_replace('/[ \t]+/u', ' ', trim($line));

                $cols = preg_split('/\s+/u', $line);

                if (!is_array($cols) || count($cols) < 5) {
                    $skipped++;
                    continue;
                }

                $nom_code_raw = $cols[0];

                if (!ctype_digit($nom_code_raw)) {
                    $skipped++;
                    continue;
                }

                $id = 1000000000 + (int)$nom_code_raw;

                $client_price = str_replace(',', '.', array_pop($cols));
                $is_calc      = str_replace(',', '.', array_pop($cols));

                if (!is_numeric($client_price) || !is_numeric($is_calc)) {
                    $skipped++;
                    continue;
                }

                $unit = array_pop($cols);
                $name = implode(' ', array_slice($cols, 1));

                if ($name === '' || $unit === '') {
                    $skipped++;
                    continue;
                }

                $batchData[] = [
                    $id,
                    $nom_code_raw,
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

                $line = convertToUtf8($line);
                $line = trim($line);

                if ($line === '') continue;

                if (
                    str_contains($line, 'РЕГИСТЪР') ||
                    str_contains($line, 'Дата') ||
                    str_contains($line, 'стр.') ||
                    preg_match('/^-+$/', $line)
                ) {
                    continue;
                }

                if (!preg_match('/^(.*?)\s+(\d+)$/u', $line, $matches)) {
                    $skipped++;
                    continue;
                }

                $nameRaw = trim($matches[1]);
                $num     = (int)$matches[2];

                $name = preg_replace('/^\d+\s+/', '', $nameRaw);

                if ($name === '' || !$num) {
                    $skipped++;
                    continue;
                }

                $id = 100000 + $num;

                $batchData[] = [
                    $id,
                    $name,
                    $num,
                    1,
                    $officeCounter
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