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
$selectedMonth = $_GET['month'] ?? date('Y-m');

if (!preg_match('/^\d{4}-\d{2}$/', $selectedMonth)) {
    $selectedMonth = date('Y-m');
}

$monthStart = date('Y-m-01 00:00:00', strtotime($selectedMonth . '-01'));
$monthEnd   = date('Y-m-01 00:00:00', strtotime($monthStart . ' +1 month'));

/*
|--------------------------------------------------------------------------
| NORMALИЗАЦИЯ НА ТИПОВЕ (ВАЖНО)
|--------------------------------------------------------------------------
*/

function normalizeType(string $type): string
{
    return match ($type) {
        'debit', 'create', 'initial' => 'create',
        'credit', 'payment' => 'payment',
        default => $type
    };
}

/*
|--------------------------------------------------------------------------
| НАЧАЛНО САЛДО
|--------------------------------------------------------------------------
*/

$openingBalance = 0.0;

$stmtOpen = $db->prepare("
    SELECT COALESCE(SUM(
        CASE
            WHEN transaction_type IN ('create','debit','initial') THEN amount
            WHEN transaction_type IN ('payment','credit') THEN -ABS(amount)
            ELSE 0
        END
    ), 0) AS balance
    FROM objects_obligation_transactions
    WHERE transaction_date < ?
");

$stmtOpen->bind_param('s', $monthStart);
$stmtOpen->execute();
$openingBalance = (float)($stmtOpen->get_result()->fetch_assoc()['balance'] ?? 0);
$stmtOpen->close();

/*
|--------------------------------------------------------------------------
| ДАННИ ЗА ПЕРИОД
|--------------------------------------------------------------------------
*/

$stmt = $db->prepare("
    SELECT
        DATE(transaction_date) AS tx_date,
        transaction_type,
        SUM(amount) AS amount
    FROM objects_obligation_transactions
    WHERE transaction_date >= ?
      AND transaction_date < ?
    GROUP BY tx_date, transaction_type
    ORDER BY tx_date ASC
");

$stmt->bind_param('ss', $monthStart, $monthEnd);
$stmt->execute();
$result = $stmt->get_result();

/*
|--------------------------------------------------------------------------
| STRUCTURE
|--------------------------------------------------------------------------
*/

$daily = [];

while ($row = $result->fetch_assoc()) {

    $date = $row['tx_date'];
    $type = normalizeType($row['transaction_type']);
    $amount = (float)$row['amount'];

    if (!isset($daily[$date])) {
        $daily[$date] = [
            'creates' => 0,
            'payments' => 0
        ];
    }

    if ($type === 'create') {
        $daily[$date]['creates'] += abs($amount);
    }

    if ($type === 'payment') {
        $daily[$date]['payments'] += abs($amount);
    }
}

$stmt->close();

/*
|--------------------------------------------------------------------------
| FINAL BUILD
|--------------------------------------------------------------------------
*/

$rows = [];

$chartLabels = [];
$chartCreates = [];
$chartPayments = [];
$chartBalance = [];

$runningBalance = $openingBalance;

$totalCreates = 0;
$totalPayments = 0;

$bestDate = null;
$bestValue = -INF;

foreach ($daily as $date => $data) {

    $creates = $data['creates'];
    $payments = $data['payments'];

    $net = $creates - $payments;
    $runningBalance += $net;

    $rows[] = [
        'date' => $date,
        'creates' => $creates,
        'payments' => $payments,
        'net' => $net,
        'balance' => $runningBalance
    ];

    $chartLabels[] = date('d.m', strtotime($date));
    $chartCreates[] = $creates;
    $chartPayments[] = $payments;
    $chartBalance[] = $runningBalance;

    $totalCreates += $creates;
    $totalPayments += $payments;

    if ($runningBalance > $bestValue) {
        $bestValue = $runningBalance;
        $bestDate = $date;
    }
}

$closingBalance = $runningBalance;

?>

<div class="card shadow border-0 mb-4">

    <div class="card-header">
        <h5 class="mb-0">📊 Финансова справка V6.1 (ERP Engine)</h5>
        <div class="small text-muted">
            Реален анализ на задължения и разплащания
        </div>
    </div>

    <div class="card-body">

        <!-- FILTER -->
        <form method="get" class="mb-4">

            <input type="hidden" name="page" value="financial_dashboard">

            <div class="row g-2">

                <div class="col-md-4">
                    <input type="month" name="month"
                           value="<?= htmlspecialchars($selectedMonth) ?>"
                           class="form-control">
                </div>

                <div class="col-md-4">
                    <button class="btn btn-primary w-100">
                        Филтрирай
                    </button>
                </div>

            </div>

        </form>

        <!-- KPI -->
        <div class="row g-3 mb-4">

            <div class="col-md-3">
                <div class="card bg-secondary text-white">
                    <div class="card-body">
                        <div class="fs-5">Начално салдо</div>
                        <div class="small">Всички движения преди избрания период</div>
                        <div class="fs-5"><?= number_format($openingBalance, 2) ?> €</div>
                    </div>
                </div>
            </div>

            <div class="col-md-3">
                <div class="card bg-danger text-white">
                    <div class="card-body">
                        <div class="fs-5">Нови задължения</div>
                        <div class="small">Всички натрупани задължения в НАЧАЛОТО на периода</div>
                        <div class="fs-5">-<?= number_format($totalCreates, 2) ?> €</div>
                    </div>
                </div>
            </div>

            <div class="col-md-3">
                <div class="card bg-success text-white">
                    <div class="card-body">
                        <div class="fs-5">Разплащания</div>
                        <div class="small">Всички разплащания за избрания период</div>
                        <div class="fs-5">+<?= number_format($totalPayments, 2) ?> €</div>
                    </div>
                </div>
            </div>

            <div class="col-md-3">
                <div class="card bg-primary text-white">
                    <div class="card-body">
                        <div class="fs-5">Крайно салдо</div>
                        <div class="small">Всички натрупани задължения в КРАЯ на периода</div>
                        <div class="fs-5"><?= number_format($closingBalance, 2) ?> €</div>
                    </div>
                </div>
            </div>

        </div>

        <!-- CHART -->
        <div class="card mb-4">
            <div class="card-header">
                Графика – ERP движение
            </div>
            <div class="card-body">
                <div style="height:420px">
                    <canvas id="chart"></canvas>
                </div>
            </div>
        </div>

        <!-- TABLE -->
        <div class="table-responsive">

            <table class="table table-hover">

                <thead>
                <tr>
                    <th>Дата</th>
                    <th class="text-end">Задължения</th>
                    <th class="text-end">Разплащания</th>
                    <th class="text-end">Промяна</th>
                    <th class="text-end">Баланс</th>
                </tr>
                </thead>

                <tbody>

                <?php foreach (array_reverse($rows) as $r): ?>

                    <tr>
                        <td><?= date('d.m.Y', strtotime($r['date'])) ?></td>
                        <td class="text-end text-danger">-<?= number_format($r['creates'], 2) ?></td>
                        <td class="text-end text-success">+<?= number_format($r['payments'], 2) ?></td>
                        <td class="text-end"><?= number_format($r['net'], 2) ?></td>
                        <td class="text-end fw-bold"><?= number_format($r['balance'], 2) ?></td>
                    </tr>

                <?php endforeach; ?>

                </tbody>

            </table>

        </div>

    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>

    new Chart(document.getElementById('chart'), {
        type: 'line',
        data: {
            labels: <?= json_encode($chartLabels) ?>,
            datasets: [
                {
                    label: 'Нови задължения',
                    data: <?= json_encode($chartCreates) ?>,
                    borderColor: '#dc3545',
                    tension: 0.3
                },
                {
                    label: 'Разплащания',
                    data: <?= json_encode($chartPayments) ?>,
                    borderColor: '#198754',
                    tension: 0.3
                },
                {
                    label: 'Общо салдо',
                    data: <?= json_encode($chartBalance) ?>,
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