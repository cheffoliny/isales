<?php
include_once __DIR__.'/../includes/functions.php';

if (empty($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$db = db_connect('storage');

$period = (int)($_GET['period'] ?? 30);

if (!in_array($period, [7, 30, 90, 180], true)) {
    $period = 30;
}

$sql = "
    SELECT
        DATE(p.dest_date) AS sale_date,
        SUM(pe.single_price * pe.count) AS total_sales

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
    $totalSales
);

$chartLabels = [];
$chartData = [];

$rows = [];

$totalRevenue = 0;
$bestDayRevenue = 0;
$bestDayDate = null;
$daysCount = 0;

while ($stmt->fetch()) {

    $saleDate = (string)$saleDate;
    $totalSales = (float)$totalSales;

    $rows[] = [
        'date' => $saleDate,
        'total' => $totalSales
    ];

    $chartLabels[] = date('d.m', strtotime($saleDate));
    $chartData[] = round($totalSales, 2);

    $totalRevenue += $totalSales;

    if ($totalSales > $bestDayRevenue) {
        $bestDayRevenue = $totalSales;
        $bestDayDate = $saleDate;
    }

    $daysCount++;
}

$averageRevenue = $daysCount > 0
    ? $totalRevenue / $daysCount
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

            <a href="dashboard.php?page=sales_analysis&period=7"
               class="btn btn-sm <?= $period === 7
                   ? 'btn-primary'
                   : 'btn-outline-primary' ?>">
                7 ДНИ
            </a>

            <a href="dashboard.php?page=sales_analysis&period=30"
               class="btn btn-sm <?= $period === 30
                   ? 'btn-primary'
                   : 'btn-outline-primary' ?>">
                30 ДНИ
            </a>

            <a href="dashboard.php?page=sales_analysis&period=90"
               class="btn btn-sm <?= $period === 90
                   ? 'btn-primary'
                   : 'btn-outline-primary' ?>">
                90 ДНИ
            </a>

            <a href="dashboard.php?page=sales_analysis&period=180"
               class="btn btn-sm <?= $period === 180
                   ? 'btn-primary'
                   : 'btn-outline-primary' ?>">
                180 ДНИ
            </a>

        </div>

    </div>

    <div class="card-body">

        <!-- KPI CARDS -->

        <div class="row g-3 mb-4">

            <div class="col-6 col-lg-3">

                <div class="card border-0 bg-success text-white shadow-sm h-100">

                    <div class="card-body">

                        <div class="small opacity-75 mb-1">
                            ОБЩ ОБОРОТ
                        </div>

                        <div class="fs-4 fw-bold">
                            <?= number_format($totalRevenue, 2) ?> €
                        </div>

                    </div>

                </div>

            </div>

            <div class="col-6 col-lg-3">

                <div class="card border-0 bg-primary text-white shadow-sm h-100">

                    <div class="card-body">

                        <div class="small opacity-75 mb-1">
                            СРЕДНО НА ДЕН
                        </div>

                        <div class="fs-4 fw-bold">
                            <?= number_format($averageRevenue, 2) ?> €
                        </div>

                    </div>

                </div>

            </div>

            <div class="col-6 col-lg-3">

                <div class="card border-0 bg-warning text-dark shadow-sm h-100">

                    <div class="card-body">

                        <div class="small opacity-75 mb-1">
                            НАЙ-СИЛЕН ДЕН
                        </div>

                        <div class="fw-bold">
                            <?= $bestDayDate
                                ? date('d.m.Y', strtotime($bestDayDate))
                                : '-' ?>
                        </div>

                        <div class="fs-5 fw-bold">
                            <?= number_format($bestDayRevenue, 2) ?> €
                        </div>

                    </div>

                </div>

            </div>

            <div class="col-6 col-lg-3">

                <div class="card border-0 bg-dark text-white shadow-sm h-100">

                    <div class="card-body">

                        <div class="small opacity-75 mb-1">
                            АКТИВНИ ДНИ
                        </div>

                        <div class="fs-4 fw-bold">
                            <?= number_format($daysCount) ?>
                        </div>

                    </div>

                </div>

            </div>

        </div>

        <?php if (!empty($rows)): ?>

            <!-- CHART -->

            <div class="card border-0 shadow-sm mb-4">

                <div class="card-header bg-white">

                    <div class="fw-semibold">
                        <i class="fa-solid fa-chart-area text-primary"></i>
                        Динамика на продажбите
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
                        <?= count($rows) ?> записа
                    </div>

                </div>

                <div class="table-responsive">

                    <table class="table table-hover align-middle mb-0">

                        <thead class="table-light">

                        <tr>

                            <th>Дата</th>

                            <th class="text-end">
                                Оборот
                            </th>

                        </tr>

                        </thead>

                        <tbody>

                        <?php foreach (array_reverse($rows) as $row): ?>

                            <?php
                            $rowTotal = (float)$row['total'];

                            if ($rowTotal >= 5000) {
                                $badge = 'bg-success';
                            } elseif ($rowTotal >= 2000) {
                                $badge = 'bg-primary';
                            } else {
                                $badge = 'bg-secondary';
                            }
                            ?>

                            <tr>

                                <td>

                                    <div class="fw-semibold">
                                        <?= date('d.m.Y', strtotime($row['date'])) ?>
                                    </div>

                                </td>

                                <td class="text-end">

                                        <span class="badge <?= $badge ?> fs-6">

                                            <?= number_format($rowTotal, 2) ?> лв

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

                    label: 'Продажби',

                    data: chartData,

                    borderColor: '#0d6efd',

                    backgroundColor: 'rgba(13,110,253,0.12)',

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
                                return value + ' лв';
                            }

                        }

                    }

                }

            }

        });

    }

</script>