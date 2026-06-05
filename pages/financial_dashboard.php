<?php

declare(strict_types=1);

include_once __DIR__ . '/../includes/functions.php';

date_default_timezone_set('Europe/Sofia');

if (empty($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$db = db_connect('storage');

/*
|--------------------------------------------------------------------------
| ФИЛТРИ
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
| НАЧАЛНО САЛДО (преди периода)
|--------------------------------------------------------------------------
*/

$openingBalance = 0.0;

$stmtOpen = $db->prepare("
    SELECT COALESCE(SUM(
        CASE
            WHEN transaction_type = 'create' THEN amount
            WHEN transaction_type = 'payment' THEN -amount
            ELSE 0
        END
    ), 0) AS balance
    FROM objects_obligation_transactions
    WHERE transaction_date < ?
");

$stmtOpen->bind_param('s', $monthStart);
$stmtOpen->execute();
$resOpen = $stmtOpen->get_result();
$openingBalance = (float)($resOpen->fetch_assoc()['balance'] ?? 0);
$stmtOpen->close();

/*
|--------------------------------------------------------------------------
| ДВИЖЕНИЕ ЗА ПЕРИОДА
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT
        DATE(transaction_date) AS tx_date,

        SUM(CASE WHEN transaction_type = 'create' THEN amount ELSE 0 END) AS creates,
        SUM(CASE WHEN transaction_type = 'payment' THEN amount ELSE 0 END) AS payments

    FROM objects_obligation_transactions
    WHERE transaction_date >= ? AND transaction_date < ?
    GROUP BY tx_date
    ORDER BY tx_date ASC
";

$stmt = $db->prepare($sql);
$stmt->bind_param('ss', $monthStart, $monthEnd);
$stmt->execute();
$result = $stmt->get_result();

/*
|--------------------------------------------------------------------------
| ДАННИ
|--------------------------------------------------------------------------
*/

$rows = [];

$chartLabels = [];
$chartCreates = [];
$chartPayments = [];
$chartBalance = [];

$totalCreates = 0;
$totalPayments = 0;

$runningBalance = $openingBalance;

$bestDay = 0;
$bestDate = null;

/*
|--------------------------------------------------------------------------
| ОБРАБОТКА
|--------------------------------------------------------------------------
*/

while ($row = $result->fetch_assoc()) {

    $date = $row['tx_date'];

    $creates  = (float)$row['creates'];
    $payments = (float)$row['payments'];

    $netChange = $creates - $payments;
    $runningBalance += $netChange;

    $rows[] = [
        'date' => $date,
        'creates' => $creates,
        'payments' => $payments,
        'net' => $netChange,
        'balance' => $runningBalance
    ];

    $chartLabels[] = date('d.m', strtotime($date));
    $chartCreates[] = $creates;
    $chartPayments[] = $payments;
    $chartBalance[] = $runningBalance;

    $totalCreates += $creates;
    $totalPayments += $payments;

    if ($runningBalance > $bestDay) {
        $bestDay = $runningBalance;
        $bestDate = $date;
    }
}

$closingBalance = $runningBalance;
?>

<div class="card shadow border-0 mb-4">

    <!-- HEADER -->
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">

        <div>
            <h5 class="mb-0 text-primary">
                📊 Финансова справка (V6 ERP)
            </h5>
            <div class="small text-muted">
                Анализ на задължения, разплащания и общо вземане
            </div>
        </div>

    </div>

    <div class="card-body">

        <!-- FILTER -->
        <form method="get" class="mb-4">

            <input type="hidden" name="page" value="financial_dashboard">

            <div class="row g-3 align-items-end">

                <div class="col-md-4">
                    <label class="form-label small">Месец</label>
                    <input type="month" name="month"
                           value="<?= htmlspecialchars($selectedMonth) ?>"
                           class="form-control">
                </div>

                <div class="col-md-4">
                    <label class="form-label small">Период</label>
                    <select name="period" class="form-select">
                        <option value="30" <?= $period === 30 ? 'selected' : '' ?>>30 дни</option>
                        <option value="90" <?= $period === 90 ? 'selected' : '' ?>>90 дни</option>
                        <option value="180" <?= $period === 180 ? 'selected' : '' ?>>180 дни</option>
                    </select>
                </div>

                <div class="col-md-4">
                    <input type="hidden" name="mode" value="<?= htmlspecialchars($mode) ?>">
                    <button class="btn btn-primary w-100">Филтрирай</button>
                </div>

            </div>

        </form>

        <!-- KPI -->
        <div class="row g-3 mb-4">

            <div class="col-md-3">
                <div class="card bg-secondary text-white">
                    <div class="card-body">
                        <div class="small">Начално салдо</div>
                        <div class="fs-5 fw-bold"><?= number_format($openingBalance, 2) ?> €</div>
                    </div>
                </div>
            </div>

            <div class="col-md-3">
                <div class="card bg-danger text-white">
                    <div class="card-body">
                        <div class="small">Нови задължения</div>
                        <div class="fs-5 fw-bold">-<?= number_format($totalCreates, 2) ?> €</div>
                    </div>
                </div>
            </div>

            <div class="col-md-3">
                <div class="card bg-success text-white">
                    <div class="card-body">
                        <div class="small">Разплащания</div>
                        <div class="fs-5 fw-bold">+<?= number_format($totalPayments, 2) ?> €</div>
                    </div>
                </div>
            </div>

            <div class="col-md-3">
                <div class="card bg-primary text-white">
                    <div class="card-body">
                        <div class="small">Крайно салдо</div>
                        <div class="fs-5 fw-bold"><?= number_format($closingBalance, 2) ?> €</div>
                    </div>
                </div>
            </div>

        </div>

        <?php if (!empty($rows)): ?>

            <!-- CHART -->
            <div class="card shadow-sm border-0 mb-4">

                <div class="card-header">
                    <strong>Графика – ERP анализ</strong>
                    <span class="float-end small text-muted">
                    Най-високо салдо: <?= $bestDate ? date('d.m.Y', strtotime($bestDate)) : '-' ?>
                </span>
                </div>

                <div class="card-body">
                    <div style="height:420px">
                        <canvas id="financeChart"></canvas>
                    </div>
                </div>

            </div>

            <!-- TABLE -->
            <div class="card shadow-sm border-0">

                <div class="card-header">
                    <strong>Детайлен отчет</strong>
                </div>

                <div class="table-responsive">

                    <table class="table table-hover mb-0">

                        <thead>
                        <tr>
                            <th>Дата</th>
                            <th class="text-end">Нови задължения</th>
                            <th class="text-end">Разплащания</th>
                            <th class="text-end">Промяна</th>
                            <th class="text-end">Салдо</th>
                        </tr>
                        </thead>

                        <tbody>

                        <?php foreach (array_reverse($rows) as $r): ?>

                            <tr>
                                <td><?= date('d.m.Y', strtotime($r['date'])) ?></td>

                                <td class="text-end text-danger">
                                    -<?= number_format($r['creates'], 2) ?>
                                </td>

                                <td class="text-end text-success">
                                    +<?= number_format($r['payments'], 2) ?>
                                </td>

                                <td class="text-end fw-bold">
                                    <?= number_format($r['net'], 2) ?>
                                </td>

                                <td class="text-end fw-bold">
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
    const creates = <?= json_encode($chartCreates) ?>;
    const payments = <?= json_encode($chartPayments) ?>;
    const balance = <?= json_encode($chartBalance) ?>;

    new Chart(document.getElementById('financeChart'), {
        type: 'line',
        data: {
            labels: labels,
            datasets: [
                {
                    label: 'Нови задължения',
                    data: creates,
                    borderColor: '#dc3545',
                    tension: 0.3
                },
                {
                    label: 'Разплащания',
                    data: payments,
                    borderColor: '#198754',
                    tension: 0.3
                },
                {
                    label: 'Общо вземане',
                    data: balance,
                    borderColor: '#0d6efd',
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