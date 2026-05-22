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
| FILTER MODE
|--------------------------------------------------------------------------
*/

$mode = $_GET['mode'] ?? 'month';

$allowedModes = ['month', 'period'];

if (!in_array($mode, $allowedModes, true)) {
    $mode = 'month';
}

/*
|--------------------------------------------------------------------------
| MONTH FILTER
|--------------------------------------------------------------------------
*/

$selectedMonth = $_GET['month'] ?? date('Y-m');

if (!preg_match('/^\d{4}-\d{2}$/', $selectedMonth)) {
    $selectedMonth = date('Y-m');
}

/*
|--------------------------------------------------------------------------
| MONTH RANGE
|--------------------------------------------------------------------------
*/

$monthStart = date(
    'Y-m-01 00:00:00',
    strtotime($selectedMonth . '-01')
);

$monthEnd = date(
    'Y-m-01 00:00:00',
    strtotime($monthStart . ' +1 month')
);

/*
|--------------------------------------------------------------------------
| MONTH OPTIONS
|--------------------------------------------------------------------------
*/

$months = [];

$monthNames = [
    1 => 'Януари',
    2 => 'Февруари',
    3 => 'Март',
    4 => 'Април',
    5 => 'Май',
    6 => 'Юни',
    7 => 'Юли',
    8 => 'Август',
    9 => 'Септември',
    10 => 'Октомври',
    11 => 'Ноември',
    12 => 'Декември'
];

$currentMonth = new DateTime('first day of this month');

for ($i = 0; $i < 12; $i++) {

    $key = $currentMonth->format('Y-m');

    $monthNumber = (int)$currentMonth->format('n');

    $months[$key] =
        $monthNames[$monthNumber]
        . ' '
        . $currentMonth->format('Y');

    $currentMonth->modify('-1 month');
}

/*
|--------------------------------------------------------------------------
| PERIOD
|--------------------------------------------------------------------------
*/

$period = (int)($_GET['period'] ?? 30);

$allowedPeriods = [30, 90, 180];

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
| EXPENSE %
|--------------------------------------------------------------------------
*/

$expensePercent = (float)($_GET['expense'] ?? 8);

if ($expensePercent < 0 || $expensePercent > 100) {
    $expensePercent = 8;
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
        DATE(p.source_date) AS sale_date,
        SUM(pe.single_price * pe.count) AS gross_sales

    FROM ppp_elements pe

    INNER JOIN ppp p
        ON p.id = pe.id_ppp
       AND p.status != 'cancel'
";

/*
|--------------------------------------------------------------------------
| OFFICE JOIN
|--------------------------------------------------------------------------
*/

if ($selectedOffice > 0) {

    $sql .= "
        INNER JOIN " . DB_NAMES['sod'] . ".offices_objects oo
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
        pe.to_arc = 0
";

if ($mode === 'month') {

    $sql .= "
        AND p.source_date >= ?
        AND p.source_date < ?
    ";

} else {

    $sql .= "
        AND p.source_date >= CURDATE() - INTERVAL ? DAY
    ";
}

/*
|--------------------------------------------------------------------------
| OFFICE FILTER
|--------------------------------------------------------------------------
*/

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

    if ($mode === 'month') {

        $stmt->bind_param(
            'ssi',
            $monthStart,
            $monthEnd,
            $selectedOffice
        );

    } else {

        $stmt->bind_param(
            'ii',
            $period,
            $selectedOffice
        );
    }

} else {

    if ($mode === 'month') {

        $stmt->bind_param(
            'ss',
            $monthStart,
            $monthEnd
        );

    } else {

        $stmt->bind_param(
            'i',
            $period
        );
    }
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
$chartExpenseData = [];
$chartBalanceData = [];

$totalNet = 0;
$totalProfit = 0;
$totalExpenses = 0;
$totalBalance = 0;

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
    | CALCULATIONS
    |--------------------------------------------------------------------------
    */

    $profit = $netSales * ($profitPercent / 100);

    $expenses = $netSales * ($expensePercent / 100);

    $balance = $profit - $expenses;

    $rows[] = [

        'date' => $saleDate,
        'net' => $netSales,
        'profit' => $profit,
        'expenses' => $expenses,
        'balance' => $balance
    ];

    /*
    |--------------------------------------------------------------------------
    | CHART
    |--------------------------------------------------------------------------
    */

    $chartLabels[] = date('d.m', strtotime($saleDate));

    $chartNetData[] = round($netSales, 2);

    $chartProfitData[] = round($profit, 2);

    $chartExpenseData[] = round($expenses, 2);

    $chartBalanceData[] = round($balance, 2);

    /*
    |--------------------------------------------------------------------------
    | TOTALS
    |--------------------------------------------------------------------------
    */

    $totalNet += $netSales;

    $totalProfit += $profit;

    $totalExpenses += $expenses;

    $totalBalance += $balance;

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

$averageExpenses = $daysCount > 0
    ? $totalExpenses / $daysCount
    : 0;

$averageBalance = $daysCount > 0
    ? $totalBalance / $daysCount
    : 0;

?>

<div class="card shadow border-0 mb-4">

    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">

        <div>

            <h5 class="mb-0 text-primary">
                <i class="fa-solid fa-chart-line"></i>
                Анализ на продажби
            </h5>

            <div class="small text-muted mt-1">
                Оборот, марж, разходи и баланс
            </div>

        </div>

        <div class="btn-group">

            <?php foreach ($allowedPeriods as $p): ?>

                <a href="dashboard.php?page=sales_analysis&mode=period&period=<?= $p ?>&month=<?= urlencode($selectedMonth) ?>&vat=<?= $vat ?>&profit=<?= $profitPercent ?>&expense=<?= $expensePercent ?>&office=<?= $selectedOffice ?>"
                   class="btn btn-sm <?= ($mode === 'period' && $period === $p)
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

            <div class="row g-3 align-items-end">

                <!-- OFFICE -->

                <div class="col-12 col-xl-3">

                    <label class="form-label small fw-semibold">
                        Линия / Офис
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

                <!-- MONTH -->

                <div class="col-12 col-xl-3">

                    <label class="form-label small fw-semibold">
                        Месец
                    </label>

                    <select name="month"
                            class="form-select"
                            onchange="this.form.mode.value='month'">

                        <?php foreach ($months as $key => $label): ?>

                            <option value="<?= $key ?>"
                                <?= $selectedMonth === $key
                                    ? 'selected'
                                    : '' ?>>

                                <?= htmlspecialchars($label) ?>

                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>

                <!-- VAT -->

                <div class="col-6 col-md-3 col-xl-1">

                    <label class="form-label small fw-semibold">
                        ДДС %
                    </label>

                    <input type="number"
                           step="0.01"
                           min="0"
                           max="50"
                           name="vat"
                           value="<?= htmlspecialchars((string)$vat) ?>"
                           class="form-control">

                </div>

                <!-- PROFIT -->

                <div class="col-6 col-md-3 col-xl-1">

                    <label class="form-label small fw-semibold">
                        Марж %
                    </label>

                    <input type="number"
                           step="0.01"
                           min="0"
                           max="100"
                           name="profit"
                           value="<?= htmlspecialchars((string)$profitPercent) ?>"
                           class="form-control">

                </div>

                <!-- EXPENSE -->

                <div class="col-6 col-md-3 col-xl-1">

                    <label class="form-label small fw-semibold">
                        Разход %
                    </label>

                    <input type="number"
                           step="0.01"
                           min="0"
                           max="100"
                           name="expense"
                           value="<?= htmlspecialchars((string)$expensePercent) ?>"
                           class="form-control">

                </div>

                <!-- BUTTON -->

                <div class="col-6 col-md-3 col-xl-2">

                    <input type="hidden"
                           name="mode"
                           value="<?= htmlspecialchars($mode) ?>">

                    <input type="hidden"
                           name="period"
                           value="<?= $period ?>">

                    <button class="btn btn-primary w-100">

                        <i class="fa-solid fa-filter"></i>

                        Филтрирай

                    </button>

                </div>

            </div>

        </form>

        <!-- KPI -->

        <div class="row g-3 mb-4">

            <!-- NET -->

            <div class="col-6 col-md-4 col-xl">

                <div class="card border-0 bg-primary text-white shadow-sm h-100">

                    <div class="card-body">

                        <div class="small opacity-75 mb-1">
                            ОБОРОТ
                        </div>

                        <div class="fs-5 fw-bold">
                            <?= number_format($totalNet, 2) ?> €
                        </div>

                    </div>

                </div>

            </div>

            <!-- PROFIT -->

            <div class="col-6 col-md-4 col-xl">

                <div class="card border-0 bg-info text-white shadow-sm h-100">

                    <div class="card-body">

                        <div class="small opacity-75 mb-1">
                            МАРЖ
                        </div>

                        <div class="fs-5 fw-bold">
                            <?= number_format($totalProfit, 2) ?> €
                        </div>

                    </div>

                </div>

            </div>

            <!-- EXPENSES -->

            <div class="col-6 col-md-4 col-xl">

                <div class="card border-0 bg-danger text-white shadow-sm h-100">

                    <div class="card-body">

                        <div class="small opacity-75 mb-1">
                            РАЗХОДИ
                        </div>

                        <div class="fs-5 fw-bold">
                            <?= number_format($totalExpenses, 2) ?> €
                        </div>

                    </div>

                </div>

            </div>

            <!-- BALANCE -->

            <div class="col-6 col-md-6 col-xl">

                <div class="card border-0 bg-success text-white shadow-sm h-100">

                    <div class="card-body">

                        <div class="small opacity-75 mb-1">
                            БАЛАНС
                        </div>

                        <div class="fs-5 fw-bold">
                            <?= number_format($totalBalance, 2) ?> €
                        </div>

                    </div>

                </div>

            </div>

            <!-- AVERAGE -->

            <div class="col-12 col-md-6 col-xl">

                <div class="card border-0 bg-warning text-dark shadow-sm h-100">

                    <div class="card-body">

                        <div class="small opacity-75 mb-1">
                            СРЕДНО НА ДЕН
                        </div>

                        <div class="fw-bold">
                            <?= number_format($averageNet, 2) ?> €
                        </div>

                        <div class="small mt-1">
                            Баланс:
                            <?= number_format($averageBalance, 2) ?> €
                        </div>

                    </div>

                </div>

            </div>

        </div>

        <?php if (!empty($rows)): ?>

            <!-- CHART -->

            <div class="card border-0 shadow-sm mb-4">

                <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">

                    <div class="fw-semibold">

                        <i class="fa-solid fa-chart-area text-primary"></i>

                        Динамика

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

                <div class="card-header d-flex justify-content-between align-items-center">

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

                        <thead>

                        <tr>

                            <th>Дата</th>

                            <th class="text-end">Оборот</th>

                            <th class="text-end">Марж</th>

                            <th class="text-end">Разходи</th>

                            <th class="text-end">Баланс</th>

                        </tr>

                        </thead>

                        <tbody>

                        <?php foreach (array_reverse($rows) as $row): ?>

                            <?php

                            $balanceClass = $row['balance'] >= 0
                                ? 'text-success'
                                : 'text-danger';

                            ?>

                            <tr>

                                <td>

                                    <div class="fw-semibold">

                                        <?= date('d.m.Y', strtotime($row['date'])) ?>

                                    </div>

                                </td>

                                <td class="text-end text-primary fw-semibold">

                                    <?= number_format($row['net'], 2) ?> €

                                </td>

                                <td class="text-end text-info fw-semibold">

                                    <?= number_format($row['profit'], 2) ?> €

                                </td>

                                <td class="text-end text-danger">

                                    <?= number_format($row['expenses'], 2) ?> €

                                </td>

                                <td class="text-end">

                                    <span class="fw-bold <?= $balanceClass ?>">

                                        <?= number_format($row['balance'], 2) ?> €

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

    const chartExpenseData = <?= json_encode($chartExpenseData) ?>;

    const chartBalanceData = <?= json_encode($chartBalanceData) ?>;

    const canvas = document.getElementById('salesChart');

    if (canvas) {

        const ctx = canvas.getContext('2d');

        const gradientBlue = ctx.createLinearGradient(0, 0, 0, 400);

        gradientBlue.addColorStop(0, 'rgba(13,110,253,0.35)');

        gradientBlue.addColorStop(1, 'rgba(13,110,253,0.02)');

        new Chart(ctx, {

            type: 'line',

            data: {

                labels: chartLabels,

                datasets: [

                    {
                        label: 'Оборот',

                        data: chartNetData,

                        borderColor: '#0d6efd',

                        backgroundColor: gradientBlue,

                        fill: true,

                        tension: 0.35,

                        borderWidth: 3,

                        pointRadius: 4
                    },

                    {
                        label: 'Марж',

                        data: chartProfitData,

                        borderColor: '#0dcaf0',

                        tension: 0.35,

                        borderWidth: 3,

                        borderDash: [6, 6],

                        pointRadius: 3
                    },

                    {
                        label: 'Разходи',

                        data: chartExpenseData,

                        borderColor: '#dc3545',

                        tension: 0.35,

                        borderWidth: 2,

                        borderDash: [4, 4],

                        pointRadius: 2
                    },

                    {
                        label: 'Баланс',

                        data: chartBalanceData,

                        borderColor: '#198754',

                        tension: 0.35,

                        borderWidth: 3,

                        pointRadius: 3
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