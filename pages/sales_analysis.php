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

$period = (int)($_GET['period'] ?? 30);

$allowedPeriods = [7, 30, 90, 180];

if (!in_array($period, $allowedPeriods, true)) {
    $period = 30;
}

/*
|--------------------------------------------------------------------------
| VAT
|--------------------------------------------------------------------------
*/

$vat = (float)($_GET['vat'] ?? 20);

if ($vat < 0 || $vat > 50) {
    $vat = 20;
}

/*
|--------------------------------------------------------------------------
| PROFIT %
|--------------------------------------------------------------------------
*/

$profitPercent = (float)($_GET['profit'] ?? 16);

if ($profitPercent < 0 || $profitPercent > 100) {
    $profitPercent = 16;
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
        DATE(p.dest_date) AS sale_date,
        SUM(pe.single_price * pe.count) AS gross_sales

    FROM ppp_elements pe

    INNER JOIN ppp p
        ON p.id = pe.id_ppp
       AND p.status = 'confirm'
";

/*
|--------------------------------------------------------------------------
| OFFICE JOIN
|--------------------------------------------------------------------------
*/

if ($selectedOffice > 0) {

    $sql .= "
        LEFT JOIN ".$db_storage.".objects o
            ON o.id = p.id_dest
    ";
}

/*
|--------------------------------------------------------------------------
| WHERE
|--------------------------------------------------------------------------
*/

$sql .= "
    WHERE
        pe.to_arc = 0
        AND p.dest_date >= CURDATE() - INTERVAL ? DAY
";

/*
|--------------------------------------------------------------------------
| OFFICE FILTER
|--------------------------------------------------------------------------
| offices_ids examples:
| [2,5]
| Ако колоната е JSON -> JSON_CONTAINS()
|
*/

if ($selectedOffice > 0) {

    $sql .= "
        AND o.offices_ids IS NOT NULL
        AND JSON_VALID(o.offices_ids)
        AND JSON_CONTAINS(
            o.offices_ids,
            CAST(? AS JSON)
        )
    ";
}

/*
|--------------------------------------------------------------------------
| GROUP
|--------------------------------------------------------------------------
*/

$sql .= "
    GROUP BY sale_date
    ORDER BY sale_date ASC
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

    $officeJson = (string)$selectedOffice;

    $stmt->bind_param(
        'is',
        $period,
        $officeJson
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

$chartLabels = [];
$chartNetData = [];
$chartProfitData = [];

$totalGross = 0;
$totalNet = 0;
$totalProfit = 0;

$bestDayNet = 0;
$bestDayDate = null;

$daysCount = 0;

while ($row = $result->fetch_assoc()) {

    $saleDate = (string)$row['sale_date'];

    $grossSales = (float)$row['gross_sales'];

    /*
    |--------------------------------------------------------------------------
    | REMOVE VAT
    |--------------------------------------------------------------------------
    */

    $netSales = $grossSales / (1 + ($vat / 100));

    /*
    |--------------------------------------------------------------------------
    | PROFIT
    |--------------------------------------------------------------------------
    */

    $profit = $netSales * ($profitPercent / 100);

    $rows[] = [

        'date' => $saleDate,

        'gross' => $grossSales,

        'net' => $netSales,

        'profit' => $profit
    ];

    /*
    |--------------------------------------------------------------------------
    | CHART
    |--------------------------------------------------------------------------
    */

    $chartLabels[] = date('d.m', strtotime($saleDate));

    $chartNetData[] = round($netSales, 2);

    $chartProfitData[] = round($profit, 2);

    /*
    |--------------------------------------------------------------------------
    | TOTALS
    |--------------------------------------------------------------------------
    */

    $totalGross += $grossSales;

    $totalNet += $netSales;

    $totalProfit += $profit;

    /*
    |--------------------------------------------------------------------------
    | BEST DAY
    |--------------------------------------------------------------------------
    */

    if ($netSales > $bestDayNet) {

        $bestDayNet = $netSales;

        $bestDayDate = $saleDate;
    }

    $daysCount++;
}

/*
|--------------------------------------------------------------------------
| AVERAGES
|--------------------------------------------------------------------------
*/

$averageNet = $daysCount > 0
    ? $totalNet / $daysCount
    : 0;

$averageProfit = $daysCount > 0
    ? $totalProfit / $daysCount
    : 0;

?>

<div class="card shadow border-0 mb-4">

    <div class="card-header bg-white d-flex justify-content-between align-items-center flex-wrap gap-2">

        <div>

            <h5 class="mb-0 text-primary">

                <i class="fa-solid fa-chart-line"></i>

                Анализ на продажбите

            </h5>

            <div class="small text-muted mt-1">

                Анализ по период, офис и печалба

            </div>

        </div>

        <div class="btn-group">

            <?php foreach ($allowedPeriods as $p): ?>

                <a href="dashboard.php?page=sales_analysis&period=<?= $p ?>&vat=<?= $vat ?>&profit=<?= $profitPercent ?>&office=<?= $selectedOffice ?>"
                   class="btn btn-sm <?= $period === $p
                       ? 'btn-primary'
                       : 'btn-outline-primary' ?>">

                    <?= $p ?> ДНИ

                </a>

            <?php endforeach; ?>

        </div>

    </div>

    <div class="card-body">

        <!-- FILTER -->

        <form method="get" class="mb-4">

            <input type="hidden"
                   name="page"
                   value="sales_analysis">

            <input type="hidden"
                   name="period"
                   value="<?= $period ?>">

            <div class="row g-3 align-items-end">

                <!-- OFFICE -->

                <div class="col-12 col-lg-4">

                    <label class="form-label small fw-semibold">

                        Офис

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

                <!-- VAT -->

                <div class="col-6 col-lg-2">

                    <label class="form-label small fw-semibold">

                        ДДС %

                    </label>

                    <div class="input-group">

                        <span class="input-group-text">

                            <i class="fa-solid fa-percent"></i>

                        </span>

                        <input type="number"
                               step="0.01"
                               min="0"
                               max="50"
                               name="vat"
                               value="<?= htmlspecialchars((string)$vat) ?>"
                               class="form-control">

                    </div>

                </div>

                <!-- PROFIT -->

                <div class="col-6 col-lg-2">

                    <label class="form-label small fw-semibold">

                        Печалба %

                    </label>

                    <div class="input-group">

                        <span class="input-group-text">

                            <i class="fa-solid fa-sack-dollar"></i>

                        </span>

                        <input type="number"
                               step="0.01"
                               min="0"
                               max="100"
                               name="profit"
                               value="<?= htmlspecialchars((string)$profitPercent) ?>"
                               class="form-control">

                    </div>

                </div>

                <!-- BUTTON -->

                <div class="col-12 col-lg-auto">

                    <button class="btn btn-primary w-100">

                        <i class="fa-solid fa-filter"></i>

                        Филтрирай

                    </button>

                </div>

            </div>

        </form>

        <!-- KPI -->

        <div class="row g-3 mb-4">

            <!-- GROSS -->

            <div class="col-6 col-lg-3">

                <div class="card border-0 bg-dark text-white shadow-sm h-100">

                    <div class="card-body">

                        <div class="small opacity-75 mb-1">

                            ПРОДАЖБИ С ДДС

                        </div>

                        <div class="fs-5 fw-bold">

                            <?= number_format($totalGross, 2) ?> €

                        </div>

                    </div>

                </div>

            </div>

            <!-- NET -->

            <div class="col-6 col-lg-3">

                <div class="card border-0 bg-primary text-white shadow-sm h-100">

                    <div class="card-body">

                        <div class="small opacity-75 mb-1">

                            ОБОРОТ БЕЗ ДДС

                        </div>

                        <div class="fs-5 fw-bold">

                            <?= number_format($totalNet, 2) ?> €

                        </div>

                    </div>

                </div>

            </div>

            <!-- PROFIT -->

            <div class="col-6 col-lg-3">

                <div class="card border-0 bg-success text-white shadow-sm h-100">

                    <div class="card-body">

                        <div class="small opacity-75 mb-1">

                            ПЕЧАЛБА

                        </div>

                        <div class="fs-5 fw-bold">

                            <?= number_format($totalProfit, 2) ?> €

                        </div>

                    </div>

                </div>

            </div>

            <!-- AVERAGE -->

            <div class="col-6 col-lg-3">

                <div class="card border-0 bg-warning text-dark shadow-sm h-100">

                    <div class="card-body">

                        <div class="small opacity-75 mb-1">

                            СРЕДНО НА ДЕН

                        </div>

                        <div class="fw-bold">

                            <?= number_format($averageNet, 2) ?> €

                        </div>

                        <div class="small">

                            Печалба:
                            <?= number_format($averageProfit, 2) ?> €

                        </div>

                    </div>

                </div>

            </div>

        </div>

        <?php if (!empty($rows)): ?>

            <!-- CHART -->

            <div class="card border-0 shadow-sm mb-4">

                <div class="card-header bg-white d-flex justify-content-between align-items-center flex-wrap gap-2">

                    <div class="fw-semibold">

                        <i class="fa-solid fa-chart-area text-primary"></i>

                        Динамика на оборот и печалба

                    </div>

                    <div class="small text-muted">

                        Най-силен ден:

                        <strong>

                            <?= $bestDayDate
                                ? date('d.m.Y', strtotime($bestDayDate))
                                : '-' ?>

                        </strong>

                    </div>

                </div>

                <div class="card-body">

                    <div style="height:400px">

                        <canvas id="salesChart"></canvas>

                    </div>

                </div>

            </div>

            <!-- TABLE -->

            <div class="card border-0 shadow-sm">

                <div class="card-header bg-white d-flex justify-content-between align-items-center">

                    <div class="fw-semibold">

                        <i class="fa-solid fa-table"></i>

                        Детайлна справка

                    </div>

                    <div class="small text-muted">

                        <?= count($rows) ?> дни

                    </div>

                </div>

                <div class="table-responsive">

                    <table class="table table-hover align-middle mb-0">

                        <thead class="table-light">

                        <tr>

                            <th>Дата</th>

                            <th class="text-end">С ДДС</th>

                            <th class="text-end">Без ДДС</th>

                            <th class="text-end">Печалба</th>

                        </tr>

                        </thead>

                        <tbody>

                        <?php foreach (array_reverse($rows) as $row): ?>

                            <?php

                            $profitClass = 'bg-secondary';

                            if ($row['profit'] >= 2000) {
                                $profitClass = 'bg-success';
                            } elseif ($row['profit'] >= 1000) {
                                $profitClass = 'bg-primary';
                            }

                            ?>

                            <tr>

                                <td>

                                    <div class="fw-semibold">

                                        <?= date('d.m.Y', strtotime($row['date'])) ?>

                                    </div>

                                </td>

                                <td class="text-end">

                                    <?= number_format($row['gross'], 2) ?> €

                                </td>

                                <td class="text-end">

                                    <span class="fw-semibold text-primary">

                                        <?= number_format($row['net'], 2) ?> €

                                    </span>

                                </td>

                                <td class="text-end">

                                    <span class="badge <?= $profitClass ?> fs-6">

                                        <?= number_format($row['profit'], 2) ?> €

                                    </span>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>

            </div>

        <?php else: ?>

            <div class="alert alert-warning text-center mb-0">

                <i class="fa-solid fa-circle-info"></i>

                Няма намерени продажби за избрания период.

            </div>

        <?php endif; ?>

    </div>

</div>

<!-- CHART.JS -->

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>

<script>

    const chartLabels = <?= json_encode($chartLabels) ?>;

    const chartNetData = <?= json_encode($chartNetData) ?>;

    const chartProfitData = <?= json_encode($chartProfitData) ?>;

    const canvas = document.getElementById('salesChart');

    if (canvas) {

        const ctx = canvas.getContext('2d');

        /*
        |--------------------------------------------------------------------------
        | BLUE GRADIENT
        |--------------------------------------------------------------------------
        */

        const gradientBlue = ctx.createLinearGradient(0, 0, 0, 400);

        gradientBlue.addColorStop(0, 'rgba(13,110,253,0.35)');
        gradientBlue.addColorStop(1, 'rgba(13,110,253,0.02)');

        /*
        |--------------------------------------------------------------------------
        | CHART
        |--------------------------------------------------------------------------
        */

        new Chart(ctx, {

            type: 'line',

            data: {

                labels: chartLabels,

                datasets: [

                    {
                        label: 'Оборот без ДДС',

                        data: chartNetData,

                        borderColor: '#0d6efd',

                        backgroundColor: gradientBlue,

                        fill: true,

                        tension: 0.35,

                        borderWidth: 3,

                        pointRadius: 4,

                        pointHoverRadius: 6
                    },

                    {
                        label: 'Печалба',

                        data: chartProfitData,

                        borderColor: '#198754',

                        backgroundColor: 'rgba(25,135,84,0.08)',

                        fill: false,

                        tension: 0.35,

                        borderWidth: 3,

                        borderDash: [6, 6],

                        pointRadius: 3,

                        pointHoverRadius: 5
                    }
                ]
            },

            options: {

                responsive: true,

                maintainAspectRatio: false,

                interaction: {

                    intersect: false,

                    mode: 'index'
                },

                plugins: {

                    legend: {

                        display: true,

                        position: 'top'
                    },

                    tooltip: {

                        callbacks: {

                            label: function(context) {

                                return context.dataset.label
                                    + ': '
                                    + Number(context.raw).toFixed(2)
                                    + ' €';
                            }
                        }
                    }
                },

                scales: {

                    y: {

                        beginAtZero: true,

                        ticks: {

                            callback: function(value) {

                                return value + ' €';
                            }
                        }
                    }
                }
            }
        });
    }

</script>