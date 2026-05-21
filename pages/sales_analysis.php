<?php
include_once __DIR__.'/../includes/functions.php';

if (empty($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$db = db_connect('storage');

/* PERIOD */

$period = (int)($_GET['period'] ?? 30);

if (!in_array($period, [7, 30, 90, 180], true)) {
    $period = 30;
}

/* VAT */

$vat = (float)($_GET['vat'] ?? 20);

if ($vat < 0 || $vat > 50) {
    $vat = 20;
}

/* MARKUP */

$markup = (float)($_GET['markup'] ?? 16);

if ($markup < 0 || $markup > 100) {
    $markup = 16;
}

/* SQL */

$sql = "
    SELECT
        DATE(p.dest_date) AS sale_date,

        SUM(pe.single_price * pe.count) AS gross_sales

    FROM ppp_elements pe

    INNER JOIN ppp p
        ON p.id = pe.id_ppp
       AND p.status = 'confirm'

    WHERE
        pe.to_arc = 0
        AND DATE(p.dest_date) >= DATE_ADD(CURDATE(), INTERVAL -{$period} DAY)

    GROUP BY DATE(p.dest_date)

    ORDER BY DATE(p.dest_date) ASC
";

$stmt = $db->prepare($sql);
$stmt->execute();

$stmt->bind_result(
    $saleDate,
    $grossSales
);

/* DATA */

$rows = [];

$chartLabels = [];
$chartData = [];

$totalGross = 0;
$totalNet = 0;
$totalProfit = 0;

$bestDayNet = 0;
$bestDayDate = null;

$daysCount = 0;

while ($stmt->fetch()) {

    $saleDate = (string)$saleDate;

    $grossSales = (float)$grossSales;

    /* REMOVE VAT */

    $netSales = $grossSales / (1 + ($vat / 100));

    /* PROFIT */

    $profit = $netSales * ($markup / 100);

    $rows[] = [

        'date' => $saleDate,

        'gross' => $grossSales,

        'net' => $netSales,

        'profit' => $profit

    ];

    $chartLabels[] = date('d.m', strtotime($saleDate));

    $chartData[] = round($netSales, 2);

    $totalGross += $grossSales;

    $totalNet += $netSales;

    $totalProfit += $profit;

    if ($netSales > $bestDayNet) {

        $bestDayNet = $netSales;

        $bestDayDate = $saleDate;
    }

    $daysCount++;
}

/* AVERAGES */

$averageNet = $daysCount > 0
    ? $totalNet / $daysCount
    : 0;

$averageProfit = $daysCount > 0
    ? $totalProfit / $daysCount
    : 0;
?>

<div class="card shadow mb-3 border-0">

    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">

        <a href="dashboard.php?page=routes"
           class="btn btn-outline-secondary btn-sm">

            <i class="fa-solid fa-angles-left"></i>

        </a>

        <h5 class="mb-0 text-primary">

            <i class="fa-solid fa-chart-line"></i>

            Анализ на продажбите

        </h5>

        <div class="btn-group">

            <a href="dashboard.php?page=sales_analysis&period=7&vat=<?= $vat ?>&markup=<?= $markup ?>"
               class="btn btn-sm <?= $period === 7
                   ? 'btn-primary'
                   : 'btn-outline-primary' ?>">
                7 ДНИ
            </a>

            <a href="dashboard.php?page=sales_analysis&period=30&vat=<?= $vat ?>&markup=<?= $markup ?>"
               class="btn btn-sm <?= $period === 30
                   ? 'btn-primary'
                   : 'btn-outline-primary' ?>">
                30 ДНИ
            </a>

            <a href="dashboard.php?page=sales_analysis&period=90&vat=<?= $vat ?>&markup=<?= $markup ?>"
               class="btn btn-sm <?= $period === 90
                   ? 'btn-primary'
                   : 'btn-outline-primary' ?>">
                90 ДНИ
            </a>

            <a href="dashboard.php?page=sales_analysis&period=180&vat=<?= $vat ?>&markup=<?= $markup ?>"
               class="btn btn-sm <?= $period === 180
                   ? 'btn-primary'
                   : 'btn-outline-primary' ?>">
                180 ДНИ
            </a>

        </div>

    </div>

    <div class="card-body">

        <!-- SETTINGS -->

        <form method="get" class="mb-4">

            <input type="hidden"
                   name="page"
                   value="sales_analysis">

            <input type="hidden"
                   name="period"
                   value="<?= $period ?>">

            <div class="row g-3 align-items-end">

                <div class="col-6 col-lg-3">

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
                               value="<?= htmlspecialchars($vat) ?>"
                               class="form-control">

                    </div>

                </div>

                <div class="col-6 col-lg-3">

                    <label class="form-label small fw-semibold">
                        Надценка %
                    </label>

                    <div class="input-group">

                        <span class="input-group-text">
                            <i class="fa-solid fa-sack-dollar"></i>
                        </span>

                        <input type="number"
                               step="0.01"
                               min="0"
                               max="100"
                               name="markup"
                               value="<?= htmlspecialchars($markup) ?>"
                               class="form-control">

                    </div>

                </div>

                <div class="col-12 col-lg-auto">

                    <button class="btn btn-primary w-100">

                        <i class="fa-solid fa-rotate"></i>

                        Преизчисли

                    </button>

                </div>

            </div>

        </form>

        <!-- KPI -->

        <div class="row g-3 mb-4">

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

            <div class="col-6 col-lg-3">

                <div class="card border-0 bg-success text-white shadow-sm h-100">

                    <div class="card-body">

                        <div class="small opacity-75 mb-1">
                            ОЧАКВАНА ПЕЧАЛБА
                        </div>

                        <div class="fs-5 fw-bold">

                            <?= number_format($totalProfit, 2) ?> €

                        </div>

                    </div>

                </div>

            </div>

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

                        Динамика на оборота

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

                    <div style="height:350px">

                        <canvas id="salesChart"></canvas>

                    </div>

                </div>

            </div>

            <!-- TABLE -->

            <div class="card border-0 shadow-sm">

                <div class="card-header bg-white d-flex justify-content-between align-items-center">

                    <div class="fw-semibold">

                        <i class="fa-solid fa-table text-secondary"></i>

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

                            <th class="text-end">
                                С ДДС
                            </th>

                            <th class="text-end">
                                Без ДДС
                            </th>

                            <th class="text-end">
                                Печалба
                            </th>

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

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>

    const chartLabels = <?= json_encode($chartLabels) ?>;
    const chartData = <?= json_encode($chartData) ?>;

    if(document.getElementById('salesChart')){

        const ctx = document.getElementById('salesChart');

        new Chart(ctx, {

            type: 'line',

            data: {

                labels: chartLabels,

                datasets: [{

                    label: 'Оборот без ДДС',

                    data: chartData,

                    borderColor: '#0d6efd',

                    backgroundColor: 'rgba(13,110,253,0.10)',

                    fill: true,

                    tension: 0.35,

                    borderWidth: 3,

                    pointRadius: 4,

                    pointHoverRadius: 6

                }]

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
                        display: false
                    }

                },

                scales: {

                    y: {

                        beginAtZero: true,

                        ticks: {

                            callback: function(value){

                                return value + ' €';

                            }

                        }

                    }

                }

            }

        });

    }

</script>