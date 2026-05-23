<?php

declare(strict_types=1);

include_once __DIR__ . '/../includes/functions.php';

date_default_timezone_set('Europe/Sofia');

if (empty($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$db_storage = db_connect('storage');
$db = db_connect('sod');

$period = (int)($_GET['period'] ?? 30);

$allowedPeriods = [30, 90, 180];

if (!in_array($period, $allowedPeriods, true)) {
    $period = 30;
}

$limit = (int)($_GET['limit'] ?? 15);

if ($limit < 5 || $limit > 100) {
    $limit = 15;
}

$sql = "
    SELECT
        pe.id_nomenclature,
        a.name AS article_name,
        SUM(pe.count) AS total_qty,
        SUM(pe.single_price * pe.count) AS total_turnover,
        COUNT(*) AS total_sales

    FROM ppp_elements pe

    INNER JOIN ppp p
        ON p.id = pe.id_ppp
       AND p.status != 'cancel'

    INNER JOIN nomenclatures a
        ON a.id = pe.id_nomenclature

    WHERE
        pe.to_arc = 0
        AND p.source_date >= CURDATE() - INTERVAL ? DAY

    GROUP BY pe.id_nomenclature

    ORDER BY total_turnover DESC

    LIMIT ?
";

$stmt = $db_storage->prepare($sql);

$stmt->bind_param('ii', $period, $limit);

$stmt->execute();

$result = $stmt->get_result();

$rows = [];

$chartLabels = [];
$chartTurnover = [];
$chartQty = [];

$totalTurnover = 0;
$totalQty = 0;
$totalSales = 0;

$topProduct = null;

while ($row = $result->fetch_assoc()) {

    $rows[] = $row;

    $chartLabels[] = mb_strimwidth($row['article_name'], 0, 28, '...');

    $chartTurnover[] = round((float)$row['total_turnover'], 2);

    $chartQty[] = (int)$row['total_qty'];

    $totalTurnover += (float)$row['total_turnover'];

    $totalQty += (float)$row['total_qty'];

    $totalSales += (int)$row['total_sales'];

    if ($topProduct === null) {
        $topProduct = $row['article_name'];
    }
}

?>

<div class="card shadow border-0 mb-4">

    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">

        <div>

            <h5 class="mb-0 text-primary">
                <i class="fa-solid fa-fire"></i>
                НАЙ-ПРОДАВАНИ
            </h5>

            <div class="small text-muted mt-1">
                ТОП артикули по оборот и количество
            </div>

        </div>

        <div class="btn-group">

            <?php foreach ($allowedPeriods as $p): ?>

                <a href="dashboard.php?page=products_top&period=<?= $p ?>&limit=<?= $limit ?>"
                   class="btn btn-sm <?= $period === $p
                       ? 'btn-primary'
                       : 'btn-outline-primary' ?>">

                    <?= $p ?> ДНИ

                </a>

            <?php endforeach; ?>

        </div>

    </div>

    <div class="card-body">

        <form method="get" class="mb-4">

            <input type="hidden" name="page" value="products_top">

            <div class="row g-3 align-items-end">

                <div class="col-md-3">

                    <label class="form-label small fw-semibold">
                        TOP LIMIT
                    </label>

                    <select name="limit" class="form-select">

                        <?php foreach ([10,15,20,30,50] as $l): ?>

                            <option value="<?= $l ?>"
                                <?= $limit === $l ? 'selected' : '' ?>>

                                TOP <?= $l ?>

                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>

                <div class="col-md-3">

                    <input type="hidden" name="period" value="<?= $period ?>">

                    <button class="btn btn-primary w-100">

                        <i class="fa-solid fa-filter"></i>
                        Филтрирай

                    </button>

                </div>

            </div>

        </form>

        <div class="row g-3 mb-4">

            <div class="col-md-3">

                <div class="card border-0 bg-primary text-white shadow-sm h-100">

                    <div class="card-body">

                        <div class="small opacity-75 mb-1">
                            ОБЩ ОБОРОТ
                        </div>

                        <div class="fs-5 fw-bold">
                            <?= number_format($totalTurnover, 2) ?> €
                        </div>

                    </div>

                </div>

            </div>

            <div class="col-md-3">

                <div class="card border-0 bg-success text-white shadow-sm h-100">

                    <div class="card-body">

                        <div class="small opacity-75 mb-1">
                            ПРОДАДЕНИ БРОЙКИ
                        </div>

                        <div class="fs-5 fw-bold">
                            <?= number_format($totalQty) ?>
                        </div>

                    </div>

                </div>

            </div>

            <div class="col-md-3">

                <div class="card border-0 bg-dark text-white shadow-sm h-100">

                    <div class="card-body">

                        <div class="small opacity-75 mb-1">
                            ПРОДАЖБИ
                        </div>

                        <div class="fs-5 fw-bold">
                            <?= number_format($totalSales) ?>
                        </div>

                    </div>

                </div>

            </div>

            <div class="col-md-3">

                <div class="card border-0 bg-warning text-dark shadow-sm h-100">

                    <div class="card-body">

                        <div class="small opacity-75 mb-1">
                            ТОП ПРОДУКТ
                        </div>

                        <div class="fw-bold">
                            <?= htmlspecialchars((string)$topProduct) ?>
                        </div>

                    </div>

                </div>

            </div>

        </div>

        <div class="card border-0 shadow-sm mb-4">

            <div class="card-header fw-semibold">
                <i class="fa-solid fa-chart-bar text-primary"></i>
                ТОП продукти по оборот
            </div>

            <div class="card-body">

                <div style="height: 500px">
                    <canvas id="topProductsChart"></canvas>
                </div>

            </div>

        </div>

        <div class="card border-0 shadow-sm">

            <div class="card-header fw-semibold">
                <i class="fa-solid fa-table"></i>
                Детайлна справка
            </div>

            <div class="table-responsive">

                <table class="table table-hover align-middle mb-0">

                    <thead>

                    <tr>

                        <th>#</th>
                        <th>Продукт</th>
                        <th class="text-end">Количество</th>
                        <th class="text-end">Оборот</th>
                        <th class="text-end">Продажби</th>

                    </tr>

                    </thead>

                    <tbody>

                    <?php foreach ($rows as $index => $row): ?>

                        <tr>

                            <td>
                                <?= $index + 1 ?>
                            </td>

                            <td class="fw-semibold">
                                <?= htmlspecialchars($row['article_name']) ?>
                            </td>

                            <td class="text-end text-success fw-semibold">
                                <?= number_format((float)$row['total_qty']) ?>
                            </td>

                            <td class="text-end text-primary fw-semibold">
                                <?= number_format((float)$row['total_turnover'], 2) ?> €
                            </td>

                            <td class="text-end">
                                <?= number_format((int)$row['total_sales']) ?>
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

<script>

const topLabels = <?= json_encode($chartLabels) ?>;
const topTurnover = <?= json_encode($chartTurnover) ?>;

new Chart(document.getElementById('topProductsChart'), {

    type: 'bar',

    data: {

        labels: topLabels,

        datasets: [{

            label: 'Оборот (€)',

            data: topTurnover,

            borderRadius: 8,

            backgroundColor: 'rgba(13,110,253,0.7)',

            borderColor: '#0d6efd',

            borderWidth: 2
        }]
    },

    options: {

        responsive: true,

        maintainAspectRatio: false,

        indexAxis: 'y',

        plugins: {

            legend: {
                display: false
            }
        }
    }
});

</script>