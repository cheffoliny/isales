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
| DB
|--------------------------------------------------------------------------
*/

$db_storage = db_connect('storage');
$db = db_connect('sod');

/*
|--------------------------------------------------------------------------
| FILTERS
|--------------------------------------------------------------------------
*/

$mode = $_GET['mode'] ?? 'month';
$allowedModes = ['month', 'period'];

if (!in_array($mode, $allowedModes, true)) {
    $mode = 'month';
}

$selectedMonth = $_GET['month'] ?? date('Y-m');

if (!preg_match('/^\d{4}-\d{2}$/', $selectedMonth)) {
    $selectedMonth = date('Y-m');
}

$monthStart = date('Y-m-01 00:00:00', strtotime($selectedMonth . '-01'));
$monthEnd   = date('Y-m-01 00:00:00', strtotime($monthStart . ' +1 month'));

$period = (int)($_GET['period'] ?? 30);
$allowedPeriods = [30, 90, 180];

if (!in_array($period, $allowedPeriods, true)) {
    $period = 30;
}

/*
|--------------------------------------------------------------------------
| KPI SNAPSHOT (OBLIGATIONS)
|--------------------------------------------------------------------------
*/

$kpiSql = "
    SELECT
        COUNT(*) AS obligations,
        SUM(total_sum) AS total_obligations,
        SUM(paid_sum) AS total_paid,
        SUM(total_sum - paid_sum) AS total_remaining
    FROM objects_obligations
";

$kpi = $db_storage->query($kpiSql)->fetch_assoc();

/*
|--------------------------------------------------------------------------
| CASHFLOW (TRANSACTIONS by DATE)
|--------------------------------------------------------------------------
*/

$cashSql = "
    SELECT
        DATE(transaction_date) AS tx_date,
        SUM(CASE WHEN transaction_type = 'payment' THEN ABS(amount) ELSE 0 END) AS payments,
        SUM(CASE WHEN transaction_type != 'payment' THEN amount ELSE 0 END) AS adjustments
    FROM objects_obligation_transactions
";

if ($mode === 'month') {

    $cashSql .= "
        WHERE transaction_date >= ?
        AND transaction_date < ?
    ";
} else {

    $cashSql .= "
        WHERE transaction_date >= CURDATE() - INTERVAL ? DAY
    ";
}

$cashSql .= "
    GROUP BY tx_date
    ORDER BY tx_date ASC
";

$stmt = $db_storage->prepare($cashSql);

if (!$stmt) {
    die("Prepare Error: " . $db_storage->error);
}

if ($mode === 'month') {
    $stmt->bind_param('ss', $monthStart, $monthEnd);
} else {
    $stmt->bind_param('i', $period);
}

$stmt->execute();
$res = $stmt->get_result();

/*
|--------------------------------------------------------------------------
| DATA ARRAYS
|--------------------------------------------------------------------------
*/

$labels = [];
$paymentsData = [];
$adjustmentsData = [];
$netFlowData = [];

$totalPayments = 0;
$totalAdjustments = 0;

while ($row = $res->fetch_assoc()) {

    $date = $row['tx_date'];

    $payments = (float)$row['payments'];
    $adjustments = (float)$row['adjustments'];

    $net = $payments - $adjustments;

    $labels[] = date('d.m', strtotime($date));
    $paymentsData[] = $payments;
    $adjustmentsData[] = $adjustments;
    $netFlowData[] = $net;

    $totalPayments += $payments;
    $totalAdjustments += $adjustments;
}

/*
|--------------------------------------------------------------------------
| TOP DEBT OBJECTS
|--------------------------------------------------------------------------
*/

$topSql = "
    SELECT
        id_object,
        SUM(total_sum - paid_sum) AS debt
    FROM objects_obligations
    GROUP BY id_object
    ORDER BY debt DESC
    LIMIT 10
";

$topRes = $db_storage->query($topSql);

$topObjects = [];

while ($r = $topRes->fetch_assoc()) {
    $topObjects[] = $r;
}

?>

<!-- ========================= DASHBOARD UI ========================= -->

<div class="card shadow border-0 mb-4">

    <div class="card-header d-flex justify-content-between">

        <div>
            <h5 class="mb-0 text-primary">
                <i class="fa-solid fa-coins"></i>
                Financial ERP Dashboard V3
            </h5>
            <div class="small text-muted">Obligations + Cashflow + Debt structure</div>
        </div>

    </div>

    <div class="card-body">

        <!-- KPI -->
        <div class="row g-3 mb-4">

            <div class="col-md-3">
                <div class="card bg-primary text-white shadow-sm">
                    <div class="card-body">
                        <div class="small">OBLIGATIONS</div>
                        <div class="fs-4 fw-bold"><?= (int)$kpi['obligations'] ?></div>
                    </div>
                </div>
            </div>

            <div class="col-md-3">
                <div class="card bg-info text-white shadow-sm">
                    <div class="card-body">
                        <div class="small">TOTAL DUE</div>
                        <div class="fs-4 fw-bold"><?= number_format((float)$kpi['total_obligations'], 2) ?></div>
                    </div>
                </div>
            </div>

            <div class="col-md-3">
                <div class="card bg-success text-white shadow-sm">
                    <div class="card-body">
                        <div class="small">PAID</div>
                        <div class="fs-4 fw-bold"><?= number_format((float)$kpi['total_paid'], 2) ?></div>
                    </div>
                </div>
            </div>

            <div class="col-md-3">
                <div class="card bg-danger text-white shadow-sm">
                    <div class="card-body">
                        <div class="small">REMAINING</div>
                        <div class="fs-4 fw-bold"><?= number_format((float)$kpi['total_remaining'], 2) ?></div>
                    </div>
                </div>
            </div>

        </div>

        <!-- CASHFLOW CHART -->
        <div class="card shadow-sm border-0 mb-4">

            <div class="card-header fw-semibold">
                Cashflow (transactions)
            </div>

            <div class="card-body">
                <div style="height:380px">
                    <canvas id="cashChart"></canvas>
                </div>
            </div>

        </div>

        <!-- TOP DEBT -->
        <div class="card shadow-sm border-0">

            <div class="card-header fw-semibold">
                Top Debtors (by object)
            </div>

            <div class="table-responsive">

                <table class="table mb-0">

                    <thead>
                    <tr>
                        <th>Object ID</th>
                        <th class="text-end">Debt</th>
                    </tr>
                    </thead>

                    <tbody>
                    <?php foreach ($topObjects as $obj): ?>
                        <tr>
                            <td><?= (int)$obj['id_object'] ?></td>
                            <td class="text-end text-danger fw-bold">
                                <?= number_format((float)$obj['debt'], 2) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>

                </table>

            </div>

        </div>

    </div>
</div>

<!-- CHART -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>

    const labels = <?= json_encode($labels) ?>;
    const payments = <?= json_encode($paymentsData) ?>;
    const adjustments = <?= json_encode($adjustmentsData) ?>;
    const net = <?= json_encode($netFlowData) ?>;

    const ctx = document.getElementById('cashChart');

    if (ctx) {

        new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: 'Payments',
                        data: payments,
                        borderColor: '#198754',
                        tension: 0.3
                    },
                    {
                        label: 'Adjustments',
                        data: adjustments,
                        borderColor: '#dc3545',
                        tension: 0.3
                    },
                    {
                        label: 'Net Flow',
                        data: net,
                        borderColor: '#0d6efd',
                        borderWidth: 3,
                        tension: 0.3
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false
            }
        });
    }

</script>