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
if (!in_array($period, [30, 90, 180], true)) {
    $period = 30;
}

/*
|--------------------------------------------------------------------------
| QUERY - DAILY TRANSACTIONS
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT
        DATE(transaction_date) AS tx_date,
        SUM(CASE WHEN transaction_type = 'payment' THEN amount ELSE 0 END) AS payments,
        SUM(CASE WHEN transaction_type != 'payment' THEN amount ELSE 0 END) AS charges
    FROM objects_obligation_transactions
    WHERE 1=1
";

if ($mode === 'month') {
    $sql .= " AND transaction_date >= ? AND transaction_date < ? ";
} else {
    $sql .= " AND transaction_date >= CURDATE() - INTERVAL ? DAY ";
}

$sql .= "
    GROUP BY tx_date
    ORDER BY tx_date ASC
";

$stmt = $db_storage->prepare($sql);

if (!$stmt) {
    die("SQL Error: " . $db_storage->error);
}

if ($mode === 'month') {
    $stmt->bind_param('ss', $monthStart, $monthEnd);
} else {
    $stmt->bind_param('i', $period);
}

$stmt->execute();
$result = $stmt->get_result();

/*
|--------------------------------------------------------------------------
| DATA ARRAYS
|--------------------------------------------------------------------------
*/

$rows = [];

$chartLabels = [];
$chartPayments = [];
$chartCharges = [];
$chartBalance = [];

$totalPayments = 0;
$totalCharges = 0;
$totalBalance = 0;

$bestDay = 0;
$bestDate = null;

/*
|--------------------------------------------------------------------------
| PROCESS
|--------------------------------------------------------------------------
*/

while ($row = $result->fetch_assoc()) {

    $date = $row['tx_date'];

    $payments = (float)$row['payments'];
    $charges  = (float)$row['charges'];

    $balance = $payments - $charges;

    $rows[] = [
        'date' => $date,
        'payments' => $payments,
        'charges' => $charges,
        'balance' => $balance
    ];

    $chartLabels[] = date('d.m', strtotime($date));
    $chartPayments[] = $payments;
    $chartCharges[] = $charges;
    $chartBalance[] = $balance;

    $totalPayments += $payments;
    $totalCharges += $charges;
    $totalBalance += $balance;

    if ($balance > $bestDay) {
        $bestDay = $balance;
        $bestDate = $date;
    }
}

?>

<div class="card shadow border-0 mb-4">

    <!-- HEADER -->
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">

        <div>
            <h5 class="mb-0 text-primary">
                <i class="fa-solid fa-coins"></i>
                Financial Dashboard
            </h5>
            <div class="small text-muted">Плащания, задължения и нетен поток</div>
        </div>

    </div>

    <div class="card-body">

        <!-- FILTER -->
        <form method="get" class="mb-4">

            <input type="hidden" name="page" value="financial_dashboard">

            <div class="row g-3 align-items-end">

                <!-- MONTH -->
                <div class="col-12 col-md-4">

                    <label class="form-label small">Месец</label>

                    <input type="month"
                           name="month"
                           value="<?= htmlspecialchars($selectedMonth) ?>"
                           class="form-control">

                </div>

                <!-- PERIOD -->
                <div class="col-12 col-md-4">

                    <label class="form-label small">Период</label>

                    <select name="period" class="form-select">

                        <option value="30" <?= $period === 30 ? 'selected' : '' ?>>30 дни</option>
                        <option value="90" <?= $period === 90 ? 'selected' : '' ?>>90 дни</option>
                        <option value="180" <?= $period === 180 ? 'selected' : '' ?>>180 дни</option>

                    </select>

                </div>

                <div class="col-12 col-md-4">

                    <input type="hidden" name="mode" value="<?= htmlspecialchars($mode) ?>">

                    <button class="btn btn-primary w-100">
                        Филтрирай
                    </button>

                </div>

            </div>

        </form>

        <!-- KPI -->
        <div class="row g-3 mb-4">

            <div class="col-md-4">
                <div class="card bg-primary text-white shadow-sm">
                    <div class="card-body">
                        <div class="small">Плащания</div>
                        <div class="fs-5 fw-bold"><?= number_format($totalPayments, 2) ?> €</div>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card bg-danger text-white shadow-sm">
                    <div class="card-body">
                        <div class="small">Задължения</div>
                        <div class="fs-5 fw-bold"><?= number_format($totalCharges, 2) ?> €</div>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card bg-success text-white shadow-sm">
                    <div class="card-body">
                        <div class="small">Баланс</div>
                        <div class="fs-5 fw-bold"><?= number_format($totalBalance, 2) ?> €</div>
                    </div>
                </div>
            </div>

        </div>

        <?php if (!empty($rows)): ?>

            <!-- CHART -->
            <div class="card shadow-sm border-0 mb-4">

                <div class="card-header">
                    <strong>Динамика</strong>
                    <span class="float-end small text-muted">
                    Най-добър ден:
                    <?= $bestDate ? date('d.m.Y', strtotime($bestDate)) : '-' ?>
                </span>
                </div>

                <div class="card-body">
                    <div style="height:400px">
                        <canvas id="financeChart"></canvas>
                    </div>
                </div>

            </div>

            <!-- TABLE -->
            <div class="card shadow-sm border-0">

                <div class="card-header">
                    <strong>Детайл</strong>
                </div>

                <div class="table-responsive">

                    <table class="table table-hover mb-0">

                        <thead>
                        <tr>
                            <th>Дата</th>
                            <th class="text-end">Плащания</th>
                            <th class="text-end">Задължения</th>
                            <th class="text-end">Баланс</th>
                        </tr>
                        </thead>

                        <tbody>

                        <?php foreach (array_reverse($rows) as $r): ?>

                            <tr>
                                <td><?= date('d.m.Y', strtotime($r['date'])) ?></td>

                                <td class="text-end text-primary fw-bold">
                                    <?= number_format($r['payments'], 2) ?>
                                </td>

                                <td class="text-end text-danger">
                                    <?= number_format($r['charges'], 2) ?>
                                </td>

                                <td class="text-end fw-bold <?= $r['balance'] >= 0 ? 'text-success' : 'text-danger' ?>">
                                    <?= number_format($r['balance'], 2) ?>
                                </td>
                            </tr>

                        <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>

            </div>

        <?php else: ?>

            <div class="alert alert-warning text-center">
                Няма данни за периода
            </div>

        <?php endif; ?>

    </div>
</div>

<!-- CHART.JS -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>

    const labels = <?= json_encode($chartLabels) ?>;
    const payments = <?= json_encode($chartPayments) ?>;
    const charges = <?= json_encode($chartCharges) ?>;
    const balance = <?= json_encode($chartBalance) ?>;

    const ctx = document.getElementById('financeChart');

    if (ctx) {

        new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: 'Плащания',
                        data: payments,
                        borderColor: '#0d6efd',
                        tension: 0.3
                    },
                    {
                        label: 'Задължения',
                        data: charges,
                        borderColor: '#dc3545',
                        tension: 0.3
                    },
                    {
                        label: 'Баланс',
                        data: balance,
                        borderColor: '#198754',
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