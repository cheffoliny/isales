<?php

declare(strict_types=1);

include_once __DIR__ . '/../includes/functions.php';

date_default_timezone_set('Europe/Sofia');

if (empty($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

/*
|--------------------------------------------------------------------------
| DATABASES
|--------------------------------------------------------------------------
*/

$db_storage = db_connect('storage');
$db = db_connect('sod');

/*
|--------------------------------------------------------------------------
| PERIOD
|--------------------------------------------------------------------------
*/

$period = (int)($_GET['period'] ?? 90);

$allowedPeriods = [30, 90, 180, 365];

if (!in_array($period, $allowedPeriods, true)) {
    $period = 90;
}

/*
|--------------------------------------------------------------------------
| OFFICE FILTER
|--------------------------------------------------------------------------
*/

$selectedOffice = (int)($_GET['office'] ?? 0);

/*
|--------------------------------------------------------------------------
| LOAD OFFICES
|--------------------------------------------------------------------------
*/

$offices = [];

$officesSql = "
    SELECT
        id,
        name
    FROM offices
    WHERE to_arc = 0
    ORDER BY name ASC
";

$officesQuery = $db->query($officesSql);

if ($officesQuery) {

    while ($office = $officesQuery->fetch_assoc()) {

        $offices[] = $office;
    }
}

/*
|--------------------------------------------------------------------------
| SQL
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT

        n.id,
        n.name,
        n.nom_code,

        IFNULL(n.is_calc, 0) AS stock_qty,

        IFNULL(SUM(pe.count), 0) AS qty_sold,

        IFNULL(SUM(pe.single_price * pe.count), 0) AS turnover,

        AVG(pe.single_price) AS avg_price,

        MAX(p.source_date) AS last_sale_date,

        DATEDIFF(
            NOW(),
            MAX(p.source_date)
        ) AS aging_days

    FROM nomenclatures n

    LEFT JOIN ppp_elements pe
        ON pe.id_nomenclature = n.id
       AND pe.to_arc = 0

    LEFT JOIN ppp p
        ON p.id = pe.id_ppp
       AND p.status != 'cancel'
       AND p.source_date >= CURDATE() - INTERVAL ? DAY
";

/*
|--------------------------------------------------------------------------
| OFFICE FILTER
|--------------------------------------------------------------------------
*/

if ($selectedOffice > 0) {

    $sql .= "
        LEFT JOIN " . DB_NAMES['sod'] . ".offices_objects oo
            ON oo.id_object = p.id_dest
    ";
}

/*
|--------------------------------------------------------------------------
| WHERE
|--------------------------------------------------------------------------
*/

$sql .= "
    WHERE
        n.client_price > 0
        AND n.is_calc > 0
        AND LENGTH(n.nom_code) > 6
";

if ($selectedOffice > 0) {

    $sql .= "
        AND oo.id_office = ?
    ";
}

/*
|--------------------------------------------------------------------------
| GROUP
|--------------------------------------------------------------------------
*/

$sql .= "
    GROUP BY n.id

    ORDER BY turnover DESC

    LIMIT 300
";

/*
|--------------------------------------------------------------------------
| PREPARE
|--------------------------------------------------------------------------
*/

$stmt = $db_storage->prepare($sql);

if (!$stmt) {
    die('Prepare Error: ' . $db_storage->error);
}

/*
|--------------------------------------------------------------------------
| BIND
|--------------------------------------------------------------------------
*/

if ($selectedOffice > 0) {

    $stmt->bind_param(
        'ii',
        $period,
        $selectedOffice
    );

} else {

    $stmt->bind_param(
        'i',
        $period
    );
}

/*
|--------------------------------------------------------------------------
| EXECUTE
|--------------------------------------------------------------------------
*/

if (!$stmt->execute()) {
    die('Execute Error: ' . $stmt->error);
}

$result = $stmt->get_result();

/*
|--------------------------------------------------------------------------
| DATA
|--------------------------------------------------------------------------
*/

$rows = [];

$totalTurnover = 0;
$totalQty = 0;
$totalProducts = 0;
$totalSlowProfit = 0;

$chartLabels = [];
$chartData = [];

while ($row = $result->fetch_assoc()) {

    $qtySold = (float)$row['qty_sold'];

    $turnover = (float)$row['turnover'];

    $stockQty = (float)$row['stock_qty'];

    $agingDays = $row['aging_days'] !== null
        ? (int)$row['aging_days']
        : 999;

    $avgPrice = (float)$row['avg_price'];

    /*
    |--------------------------------------------------------------------------
    | HEALTH SCORE
    |--------------------------------------------------------------------------
    */

    $health = 'GOOD';
    $healthClass = 'success';

    if ($qtySold <= 5 && $stockQty > 20) {

        $health = 'RISK';
        $healthClass = 'warning';

        $totalSlowProfit++;

    }

    if ($qtySold <= 0 && $stockQty > 0) {

        $health = 'DEAD';
        $healthClass = 'danger';
    }

    if ($agingDays >= 180) {

        $health = 'CRITICAL';
        $healthClass = 'dark';
    }

    /*
    |--------------------------------------------------------------------------
    | TOTALS
    |--------------------------------------------------------------------------
    */

    $totalTurnover += $turnover;
    $totalQty += $qtySold;
    $totalProducts++;

    /*
    |--------------------------------------------------------------------------
    | CHART
    |--------------------------------------------------------------------------
    */

    if (count($chartLabels) < 15) {

        $chartLabels[] = mb_strimwidth(
            $row['name'],
            0,
            22,
            '...'
        );

        $chartData[] = round($turnover, 2);
    }

    /*
    |--------------------------------------------------------------------------
    | ROWS
    |--------------------------------------------------------------------------
    */

    $rows[] = [

        'id' => (int)$row['id'],

        'name' => $row['name'],

        'nom_code' => $row['nom_code'],

        'qty_sold' => $qtySold,

        'turnover' => $turnover,

        'stock_qty' => $stockQty,

        'avg_price' => $avgPrice,

        'last_sale_date' => $row['last_sale_date'],

        'aging_days' => $agingDays,

        'health' => $health,

        'health_class' => $healthClass
    ];
}

?>

<link rel="stylesheet"
      href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css">

<style>

    .profit-card {
        border: 0;
        border-radius: 1rem;
        overflow: hidden;
    }

    .mobile-product-card {
        border-radius: 1rem;
    }

    @media (max-width: 768px) {

        .desktop-table {
            display: none;
        }
    }

    @media (min-width: 769px) {

        .mobile-cards {
            display: none;
        }
    }

</style>

<div class="card shadow border-0 mb-4">

    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-3">

        <div>

            <h5 class="mb-1">

                <i class="fa-solid fa-chart-line text-success"></i>

                Рентабилност на продуктите

            </h5>

            <div class="small text-body-secondary">

                Анализ на оборот и ефективност на артикули

            </div>

        </div>

        <div class="btn-group">

            <?php foreach ($allowedPeriods as $p): ?>

                <a href="dashboard.php?page=products_profit&period=<?= $p ?>&office=<?= $selectedOffice ?>"
                   class="btn btn-sm <?= $period === $p
                       ? 'btn-success'
                       : 'btn-outline-success' ?>">

                    <?= $p ?> ДНИ

                </a>

            <?php endforeach; ?>

        </div>

    </div>

    <div class="card-body">

        <!-- FILTERS -->

        <form method="get" class="mb-4">

            <input type="hidden"
                   name="page"
                   value="products_profit">

            <input type="hidden"
                   name="period"
                   value="<?= $period ?>">

            <div class="row g-3 align-items-end">

                <div class="col-12 col-lg-4">

                    <label class="form-label fw-semibold small">
                        Офис / Линия
                    </label>

                    <select name="office"
                            class="form-select">

                        <option value="0">
                            ВСИЧКИ
                        </option>

                        <?php foreach ($offices as $office): ?>

                            <option value="<?= (int)$office['id'] ?>"
                                <?= $selectedOffice === (int)$office['id']
                                    ? 'selected'
                                    : '' ?>>

                                <?= htmlspecialchars($office['name']) ?>

                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>

                <div class="col-12 col-lg-auto">

                    <button class="btn btn-success w-100">

                        <i class="fa-solid fa-filter"></i>

                        Филтрирай

                    </button>

                </div>

            </div>

        </form>

        <!-- KPI -->

        <div class="row g-3 mb-4">

            <div class="col-6 col-xl-3">

                <div class="card bg-success text-white profit-card h-100">

                    <div class="card-body">

                        <div class="small opacity-75">
                            ОБЩ ОБОРОТ
                        </div>

                        <div class="fs-4 fw-bold">

                            <?= number_format($totalTurnover, 2) ?> €

                        </div>

                    </div>

                </div>

            </div>

            <div class="col-6 col-xl-3">

                <div class="card bg-primary text-white profit-card h-100">

                    <div class="card-body">

                        <div class="small opacity-75">
                            ПРОДАДЕНИ БРОЙКИ
                        </div>

                        <div class="fs-4 fw-bold">

                            <?= number_format($totalQty, 0) ?>

                        </div>

                    </div>

                </div>

            </div>

            <div class="col-6 col-xl-3">

                <div class="card bg-warning text-dark profit-card h-100">

                    <div class="card-body">

                        <div class="small opacity-75">
                            РИСКОВИ ПРОДУКТИ
                        </div>

                        <div class="fs-4 fw-bold">

                            <?= number_format($totalSlowProfit) ?>

                        </div>

                    </div>

                </div>

            </div>

            <div class="col-6 col-xl-3">

                <div class="card bg-secondary text-white profit-card h-100">

                    <div class="card-body">

                        <div class="small opacity-75">
                            АНАЛИЗИРАНИ
                        </div>

                        <div class="fs-4 fw-bold">

                            <?= number_format($totalProducts) ?>

                        </div>

                    </div>

                </div>

            </div>

        </div>

        <!-- CHART -->

        <div class="card border-0 shadow-sm mb-4">

            <div class="card-header fw-semibold">

                <i class="fa-solid fa-chart-column text-success"></i>

                Top Turnover Products

            </div>

            <div class="card-body">

                <div style="height:350px">

                    <canvas id="profitChart"></canvas>

                </div>

            </div>

        </div>

        <!-- MOBILE CARDS -->

        <div class="mobile-cards">

            <div class="row g-3">

                <?php foreach ($rows as $row): ?>

                    <div class="col-12">

                        <div class="card mobile-product-card shadow-sm border-0">

                            <div class="card-body">

                                <div class="d-flex justify-content-between gap-3 mb-3">

                                    <div>

                                        <div class="fw-bold">

                                            <?= htmlspecialchars($row['name']) ?>

                                        </div>

                                        <div class="small text-body-secondary">

                                            <?= htmlspecialchars($row['nom_code']) ?>

                                        </div>

                                    </div>

                                    <span class="badge bg-<?= $row['health_class'] ?>">

                                        <?= $row['health'] ?>

                                    </span>

                                </div>

                                <div class="row g-2">

                                    <div class="col-6">

                                        <div class="small text-body-secondary">
                                            Продажби
                                        </div>

                                        <div class="fw-semibold">
                                            <?= number_format($row['qty_sold'], 0) ?>
                                        </div>

                                    </div>

                                    <div class="col-6">

                                        <div class="small text-body-secondary">
                                            Оборот
                                        </div>

                                        <div class="fw-semibold">
                                            <?= number_format($row['turnover'], 2) ?> €
                                        </div>

                                    </div>

                                    <div class="col-6">

                                        <div class="small text-body-secondary">
                                            Наличност
                                        </div>

                                        <div class="fw-semibold">
                                            <?= number_format($row['stock_qty'], 0) ?>
                                        </div>

                                    </div>

                                    <div class="col-6">

                                        <div class="small text-body-secondary">
                                            Aging
                                        </div>

                                        <div class="fw-semibold">
                                            <?= number_format($row['aging_days']) ?> дни
                                        </div>

                                    </div>

                                </div>

                            </div>

                        </div>

                    </div>

                <?php endforeach; ?>

            </div>

        </div>

        <!-- DESKTOP TABLE -->

        <div class="desktop-table">

            <div class="table-responsive">

                <table id="profitTable"
                       class="table table-hover align-middle">

                    <thead>

                    <tr>

                        <th>Артикул</th>

                        <th class="text-end">Продажби</th>

                        <th class="text-end">Оборот</th>

                        <th class="text-end">Средна цена</th>

                        <th class="text-end">Наличност</th>

                        <th class="text-center">Aging</th>

                        <th class="text-center">Статус</th>

                    </tr>

                    </thead>

                    <tbody>

                    <?php foreach ($rows as $row): ?>

                        <tr>

                            <td>

                                <div class="fw-semibold">

                                    <?= htmlspecialchars($row['name']) ?>

                                </div>

                                <div class="small text-body-secondary">

                                    <?= htmlspecialchars($row['nom_code']) ?>

                                </div>

                            </td>

                            <td class="text-end">

                                <?= number_format($row['qty_sold'], 0) ?>

                            </td>

                            <td class="text-end fw-bold text-success">

                                <?= number_format($row['turnover'], 2) ?> €

                            </td>

                            <td class="text-end">

                                <?= number_format($row['avg_price'], 2) ?> €

                            </td>

                            <td class="text-end">

                                <?= number_format($row['stock_qty'], 0) ?>

                            </td>

                            <td class="text-center">

                                <span class="badge bg-secondary">

                                    <?= number_format($row['aging_days']) ?> дни

                                </span>

                            </td>

                            <td class="text-center">

                                <span class="badge bg-<?= $row['health_class'] ?>">

                                    <?= $row['health'] ?>

                                </span>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                    </tbody>

                </table>

            </div>

        </div>

    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>

<script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>

<script>

    new DataTable('#profitTable', {

        pageLength: 25,

        responsive: true,

        order: [[2, 'desc']],

        language: {
            url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/bg.json'
        }
    });

    const ctx = document.getElementById('profitChart');

    if (ctx) {

        new Chart(ctx, {

            type: 'bar',

            data: {

                labels: <?= json_encode($chartLabels) ?>,

                datasets: [{

                    label: 'Оборот',

                    data: <?= json_encode($chartData) ?>,

                    borderWidth: 1
                }]
            },

            options: {

                responsive: true,

                maintainAspectRatio: false,

                plugins: {

                    legend: {
                        display: false
                    }
                },

                scales: {

                    y: {

                        beginAtZero: true
                    }
                }
            }
        });
    }

</script>