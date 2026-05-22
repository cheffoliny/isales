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
        IFNULL(SUM(pe.count), 0) AS qty_sold,
        IFNULL(SUM(pe.single_price * pe.count), 0) AS turnover,
        MAX(p.source_date) AS last_sale_date,
        DATEDIFF(
            NOW(),
            MAX(p.source_date)
        ) AS aging_days,

        IFNULL(n.is_calc, 0) AS stock_qty

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
| OFFICE JOIN
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
    WHERE n.is_calc > 0 AND n.client_price > 0 AND LENGTH(n.nom_code) > 6
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

    ORDER BY
        qty_sold ASC,
        aging_days DESC,
        stock_qty DESC

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

$totalDeadStock = 0;
$totalDeadValue = 0;
$totalSlowMovers = 0;
$totalCritical = 0;

$scatterData = [];

while ($row = $result->fetch_assoc()) {

    $qtySold = (float)$row['qty_sold'];

    $stockQty = (float)$row['stock_qty'];

    $agingDays = $row['aging_days'] !== null
        ? (int)$row['aging_days']
        : 999;

    $turnover = (float)$row['turnover'];

    if ($qtySold <= 0) {
        $totalDeadStock++;
    }

    if ($qtySold <= 5) {
        $totalSlowMovers++;
    }

    if ($agingDays >= 180) {
        $totalCritical++;
    }

    $totalDeadValue += $turnover;

    $rows[] = [

        'id' => (int)$row['id'],

        'name' => (string)$row['name'],

        'qty_sold' => $qtySold,

        'turnover' => $turnover,

        'last_sale_date' => $row['last_sale_date'],

        'aging_days' => $agingDays,

        'stock_qty' => $stockQty
    ];

    $scatterData[] = [
        'x' => $stockQty,
        'y' => $qtySold
    ];
}

?>

<link rel="stylesheet"
      href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css">

<div class="card shadow border-0 mb-4">

    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">

        <div>

            <h5 class="mb-0 text-danger">

                <i class="fa-solid fa-triangle-exclamation"></i>

                Dead Stock / Slow Movers

            </h5>

            <div class="small text-muted mt-1">

                Анализ на залежали и слабо продаваеми артикули

            </div>

        </div>

        <div class="btn-group">

            <?php foreach ($allowedPeriods as $p): ?>

                <a href="dashboard.php?page=products_slow&period=<?= $p ?>&office=<?= $selectedOffice ?>"
                   class="btn btn-sm <?= $period === $p
                       ? 'btn-danger'
                       : 'btn-outline-danger' ?>">

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
                   value="products_slow">

            <input type="hidden"
                   name="period"
                   value="<?= $period ?>">

            <div class="row g-3 align-items-end">

                <div class="col-12 col-lg-4">

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

                <div class="col-12 col-lg-auto">

                    <button class="btn btn-danger w-100">

                        <i class="fa-solid fa-filter"></i>

                        Филтрирай

                    </button>

                </div>

            </div>

        </form>

        <!-- KPI -->

        <div class="row g-3 mb-4">

            <div class="col-6 col-xl-3">

                <div class="card border-0 bg-danger text-white shadow-sm h-100">

                    <div class="card-body">

                        <div class="small opacity-75 mb-1">
                            DEAD STOCK
                        </div>

                        <div class="fs-4 fw-bold">
                            <?= number_format($totalDeadStock) ?>
                        </div>

                    </div>

                </div>

            </div>

            <div class="col-6 col-xl-3">

                <div class="card border-0 bg-warning text-dark shadow-sm h-100">

                    <div class="card-body">

                        <div class="small opacity-75 mb-1">
                            SLOW MOVERS
                        </div>

                        <div class="fs-4 fw-bold">
                            <?= number_format($totalSlowMovers) ?>
                        </div>

                    </div>

                </div>

            </div>

            <div class="col-6 col-xl-3">

                <div class="card border-0 bg-dark text-white shadow-sm h-100">

                    <div class="card-body">

                        <div class="small opacity-75 mb-1">
                            КРИТИЧНИ > 180 ДНИ
                        </div>

                        <div class="fs-4 fw-bold">
                            <?= number_format($totalCritical) ?>
                        </div>

                    </div>

                </div>

            </div>

            <div class="col-6 col-xl-3">

                <div class="card border-0 bg-secondary text-white shadow-sm h-100">

                    <div class="card-body">

                        <div class="small opacity-75 mb-1">
                            АНАЛИЗИРАНИ АРТИКУЛИ
                        </div>

                        <div class="fs-4 fw-bold">
                            <?= number_format(count($rows)) ?>
                        </div>

                    </div>

                </div>

            </div>

        </div>

        <!-- STATUS CARDS -->

        <div class="row g-3 mb-4">

            <div class="col-md-4">

                <div class="card border-danger shadow-sm h-100">

                    <div class="card-body">

                        <div class="d-flex align-items-center gap-3">

                            <div class="bg-danger text-white rounded-circle d-flex align-items-center justify-content-center"
                                 style="width:60px;height:60px;">

                                <i class="fa-solid fa-skull fs-4"></i>

                            </div>

                            <div>

                                <div class="fw-bold text-danger">
                                    Dead Stock
                                </div>

                                <div class="small text-muted">
                                    Без продажба в периода
                                </div>

                            </div>

                        </div>

                    </div>

                </div>

            </div>

            <div class="col-md-4">

                <div class="card border-warning shadow-sm h-100">

                    <div class="card-body">

                        <div class="d-flex align-items-center gap-3">

                            <div class="bg-warning text-dark rounded-circle d-flex align-items-center justify-content-center"
                                 style="width:60px;height:60px;">

                                <i class="fa-solid fa-hourglass-half fs-4"></i>

                            </div>

                            <div>

                                <div class="fw-bold text-warning">
                                    Aging Products
                                </div>

                                <div class="small text-muted">
                                    Риск от залежаване
                                </div>

                            </div>

                        </div>

                    </div>

                </div>

            </div>

            <div class="col-md-4">

                <div class="card border-dark shadow-sm h-100">

                    <div class="card-body">

                        <div class="d-flex align-items-center gap-3">

                            <div class="bg-dark text-white rounded-circle d-flex align-items-center justify-content-center"
                                 style="width:60px;height:60px;">

                                <i class="fa-solid fa-box-open fs-4"></i>

                            </div>

                            <div>

                                <div class="fw-bold text-dark">
                                    Overstock Risk
                                </div>

                                <div class="small text-muted">
                                    Висока наличност / ниски продажби
                                </div>

                            </div>

                        </div>

                    </div>

                </div>

            </div>

        </div>

        <!-- SCATTER CHART -->

        <div class="card border-0 shadow-sm mb-4">

            <div class="card-header fw-semibold">

                <i class="fa-solid fa-chart-scatter text-danger"></i>

                Stock vs Sales Scatter

            </div>

            <div class="card-body">

                <div style="height:450px">

                    <canvas id="scatterChart"></canvas>

                </div>

            </div>

        </div>

        <!-- TABLE -->

        <div class="card border-0 shadow-sm">

            <div class="card-header d-flex justify-content-between align-items-center">

                <div class="fw-semibold">

                    <i class="fa-solid fa-table"></i>

                    Aging Analysis

                </div>

                <div class="small text-muted">

                    <?= count($rows) ?> артикула

                </div>

            </div>

            <div class="table-responsive p-3">

                <table id="slowProductsTable"
                       class="table table-hover align-middle">

                    <thead>

                    <tr>

                        <th>Артикул</th>

                        <th class="text-end">Продажби</th>

                        <th class="text-end">Оборот</th>

                        <th class="text-end">Наличност</th>

                        <th class="text-center">Aging</th>

                        <th class="text-center">Последна продажба</th>

                        <th class="text-center">Статус</th>

                    </tr>

                    </thead>

                    <tbody>

                    <?php foreach ($rows as $row): ?>

                        <?php

                        $badgeClass = 'bg-success';

                        $statusText = 'OK';

                        if ($row['qty_sold'] <= 0) {

                            $badgeClass = 'bg-danger';

                            $statusText = 'DEAD';

                        } elseif ($row['aging_days'] >= 180) {

                            $badgeClass = 'bg-dark';

                            $statusText = 'CRITICAL';

                        } elseif ($row['aging_days'] >= 90) {

                            $badgeClass = 'bg-warning text-dark';

                            $statusText = 'AGING';
                        }

                        ?>

                        <tr>

                            <td>

                                <div class="fw-semibold">
                                    <?= htmlspecialchars($row['name']) ?>
                                </div>

                                <div class="small text-muted">
                                    #<?= (int)$row['id'] ?>
                                </div>

                            </td>

                            <td class="text-end">

                                <span class="fw-bold text-danger">
                                    <?= number_format($row['qty_sold'], 0) ?>
                                </span>

                            </td>

                            <td class="text-end">

                                <?= number_format($row['turnover'], 2) ?> €

                            </td>

                            <td class="text-end">

                                <span class="badge bg-secondary fs-6">
                                    <?= number_format($row['stock_qty'], 0) ?>
                                </span>

                            </td>

                            <td class="text-center">

                                <span class="badge <?= $badgeClass ?> fs-6">

                                    <?= number_format($row['aging_days']) ?> дни

                                </span>

                            </td>

                            <td class="text-center">

                                <?php if ($row['last_sale_date']): ?>

                                    <?= date('d.m.Y', strtotime($row['last_sale_date'])) ?>

                                <?php else: ?>

                                    <span class="badge bg-danger">
                                        НЯМА
                                    </span>

                                <?php endif; ?>

                            </td>

                            <td class="text-center">

                                <span class="badge <?= $badgeClass ?>">
                                    <?= $statusText ?>
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

    new DataTable('#slowProductsTable', {

        pageLength: 25,

        order: [[4, 'desc']],

        language: {
            url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/bg.json'
        }
    });

    const scatterData = <?= json_encode($scatterData) ?>;

    const scatterCanvas = document.getElementById('scatterChart');

    if (scatterCanvas) {

        new Chart(scatterCanvas, {

            type: 'scatter',

            data: {

                datasets: [

                    {
                        label: 'Stock vs Sales',

                        data: scatterData,

                        backgroundColor: 'rgba(220,53,69,0.5)',

                        borderColor: '#dc3545'
                    }
                ]
            },

            options: {

                responsive: true,

                maintainAspectRatio: false,

                plugins: {

                    legend: {
                        display: true
                    },

                    tooltip: {

                        callbacks: {

                            label: function(context) {

                                return 'Наличност: '
                                    + context.raw.x
                                    + ' | Продажби: '
                                    + context.raw.y;
                            }
                        }
                    }
                },

                scales: {

                    x: {

                        title: {
                            display: true,
                            text: 'Наличност'
                        }
                    },

                    y: {

                        title: {
                            display: true,
                            text: 'Продажби'
                        },

                        beginAtZero: true
                    }
                }
            }
        });
    }

</script>