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
| MODE SWITCH
|--------------------------------------------------------------------------
*/
$mode = $_GET['mode'] ?? 'overview';

$allowedModes = ['overview', 'objects'];

if (!in_array($mode, $allowedModes, true)) {
    $mode = 'overview';
}

/*
|--------------------------------------------------------------------------
| PERIOD FILTER
|--------------------------------------------------------------------------
*/
$period = (int)($_GET['period'] ?? 30);

if (!in_array($period, [30, 90, 180], true)) {
    $period = 30;
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

$monthStart = date('Y-m-01 00:00:00', strtotime($selectedMonth . '-01'));
$monthEnd   = date('Y-m-01 00:00:00', strtotime($monthStart . ' +1 month'));

/*
|--------------------------------------------------------------------------
| KPI SNAPSHOT (OBLIGATIONS)
|--------------------------------------------------------------------------
*/
$kpiSql = "
    SELECT
        COUNT(*) AS obligations,
        SUM(total_sum) AS total_debt,
        SUM(paid_sum) AS total_paid,
        SUM(total_sum - paid_sum) AS total_remaining
    FROM objects_obligations
";

$kpi = $db_storage->query($kpiSql)->fetch_assoc();

/*
|--------------------------------------------------------------------------
| OVERVIEW MODE (Company Ledger Flow)
|--------------------------------------------------------------------------
*/
$labels = [];
$payments = [];
$charges = [];
$net = [];

$totalPayments = 0;
$totalCharges = 0;
$totalNet = 0;

if ($mode === 'overview') {

    $sql = "
        SELECT
            DATE(transaction_date) AS tx_date,

            SUM(CASE WHEN transaction_type = 'payment'
                THEN amount ELSE 0 END) AS payments,

            SUM(CASE WHEN transaction_type IN ('create','initial')
                THEN amount ELSE 0 END) AS charges

        FROM objects_obligation_transactions
        WHERE transaction_date >= ?
          AND transaction_date < ?
        GROUP BY tx_date
        ORDER BY tx_date ASC
    ";

    $stmt = $db_storage->prepare($sql);
    $stmt->bind_param('ss', $monthStart, $monthEnd);
    $stmt->execute();
    $res = $stmt->get_result();

    while ($r = $res->fetch_assoc()) {

        $p = (float)$r['payments'];
        $c = (float)$r['charges'];

        $n = $p - $c;

        $labels[] = date('d.m', strtotime($r['tx_date']));
        $payments[] = $p;
        $charges[] = $c;
        $net[] = $n;

        $totalPayments += $p;
        $totalCharges += $c;
        $totalNet += $n;
    }
}

/*
|--------------------------------------------------------------------------
| OBJECT MODE (DEBTORS ONLY)
|--------------------------------------------------------------------------
*/
$objects = [];

if ($mode === 'objects') {

    $objSql = "
        SELECT
            id_object,
            SUM(total_sum - paid_sum) AS balance
        FROM objects_obligations
        GROUP BY id_object
        HAVING balance < 0
        ORDER BY balance ASC
        LIMIT 50
    ";

    $res = $db_storage->query($objSql);

    while ($r = $res->fetch_assoc()) {
        $objects[] = $r;
    }
}

?>

    <!-- ================= UI ================= -->

    <div class="card shadow border-0 mb-4">

        <!-- HEADER -->
        <div class="card-header d-flex justify-content-between align-items-center">

            <div>
                <h5 class="mb-0 text-primary">
                    <i class="fa-solid fa-coins"></i>
                    Financial ERP Dashboard V4
                </h5>
                <div class="small text-muted">
                    Overview + Debtors Engine
                </div>
            </div>

            <!-- MODE SWITCH -->
            <div class="btn-group">

                <a href="?page=financial_dashboard&mode=overview"
                   class="btn btn-sm <?= $mode === 'overview' ? 'btn-primary' : 'btn-outline-primary' ?>">
                    Обобщена
                </a>

                <a href="?page=financial_dashboard&mode=objects"
                   class="btn btn-sm <?= $mode === 'objects' ? 'btn-primary' : 'btn-outline-primary' ?>">
                    По обекти
                </a>

            </div>

        </div>

        <div class="card-body">

            <!-- KPI -->
            <div class="row g-3 mb-4">

                <div class="col-md-3">
                    <div class="card bg-primary text-white">
                        <div class="card-body">
                            <div class="small">OBLIGATIONS</div>
                            <div class="fs-5 fw-bold"><?= (int)$kpi['obligations'] ?></div>
                        </div>
                    </div>
                </div>

                <div class="col-md-3">
                    <div class="card bg-danger text-white">
                        <div class="card-body">
                            <div class="small">DEBT</div>
                            <div class="fs-5 fw-bold">
                                <?= number_format((float)$kpi['total_debt'], 2) ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-3">
                    <div class="card bg-success text-white">
                        <div class="card-body">
                            <div class="small">PAID</div>
                            <div class="fs-5 fw-bold">
                                <?= number_format((float)$kpi['total_paid'], 2) ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-3">
                    <div class="card bg-dark text-white">
                        <div class="card-body">
                            <div class="small">REMAINING</div>
                            <div class="fs-5 fw-bold">
                                <?= number_format((float)$kpi['total_remaining'], 2) ?>
                            </div>
                        </div>
                    </div>
                </div>

            </div>

            <!-- OVERVIEW CHART -->
            <?php if ($mode === 'overview'): ?>

                <div class="card shadow-sm border-0">

                    <div class="card-header">
                        Cashflow Overview
                    </div>

                    <div class="card-body">
                        <div style="height:400px">
                            <canvas id="chart"></canvas>
                        </div>
                    </div>

                </div>

            <?php endif; ?>

            <!-- OBJECTS TABLE -->
            <?php if ($mode === 'objects'): ?>

                <div class="card shadow-sm border-0">

                    <div class="card-header">
                        Debtors (only negative balance)
                    </div>

                    <div class="table-responsive">

                        <table class="table mb-0">

                            <thead>
                            <tr>
                                <th>Object</th>
                                <th class="text-end">Balance</th>
                            </tr>
                            </thead>

                            <tbody>

                            <?php foreach ($objects as $o): ?>
                                <tr>
                                    <td><?= (int)$o['id_object'] ?></td>
                                    <td class="text-end text-danger fw-bold">
                                        <?= number_format((float)$o['balance'], 2) ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>

                            </tbody>

                        </table>

                    </div>

                </div>

            <?php endif; ?>

        </div>
    </div>

<?php if ($mode === 'overview'): ?>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <script>

        const labels = <?= json_encode($labels) ?>;
        const payments = <?= json_encode($payments) ?>;
        const charges = <?= json_encode($charges) ?>;
        const net = <?= json_encode($net) ?>;

        new Chart(document.getElementById('chart'), {
            type: 'line',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: 'Payments (+)',
                        data: payments,
                        borderColor: '#198754',
                        tension: 0.3
                    },
                    {
                        label: 'Charges (-)',
                        data: charges,
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

    </script>

<?php endif; ?>