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
| FILTERS
|--------------------------------------------------------------------------
*/

$mode = $_GET['mode'] ?? 'month';

$selectedMonth = $_GET['month'] ?? date('Y-m');

if (!preg_match('/^\d{4}-\d{2}$/', $selectedMonth)) {
    $selectedMonth = date('Y-m');
}

$start = date('Y-m-01 00:00:00', strtotime($selectedMonth . '-01'));
$end   = date('Y-m-01 00:00:00', strtotime($start . ' +1 month'));

/*
|--------------------------------------------------------------------------
| START BALANCE (all before period)
|--------------------------------------------------------------------------
*/

$stmt = $db->prepare("
    SELECT
        SUM(
            CASE 
                WHEN transaction_type = 'payment' THEN -amount
                ELSE amount
            END
        ) AS balance
    FROM objects_obligation_transactions
    WHERE transaction_date < ?
");

$stmt->bind_param('s', $start);
$stmt->execute();
$startBalance = (float)($stmt->get_result()->fetch_assoc()['balance'] ?? 0);
$stmt->close();

/*
|--------------------------------------------------------------------------
| PERIOD DATA (by object)
|--------------------------------------------------------------------------
*/

$sql = "
SELECT
    id_object,

    SUM(CASE WHEN transaction_type='create' THEN amount ELSE 0 END) AS creates_sum,

    SUM(CASE WHEN transaction_type='payment' THEN amount ELSE 0 END) AS payments_sum

FROM objects_obligation_transactions
WHERE transaction_date >= ?
  AND transaction_date < ?
GROUP BY id_object
ORDER BY id_object
";

$stmt = $db->prepare($sql);
$stmt->bind_param('ss', $start, $end);
$stmt->execute();

$res = $stmt->get_result();

/*
|--------------------------------------------------------------------------
| PROCESS
|--------------------------------------------------------------------------
*/

$rows = [];

$totalCreates = 0;
$totalPayments = 0;

while ($r = $res->fetch_assoc()) {

    $idObject = $r['id_object'];

    $creates  = (float)$r['creates_sum'];
    $payments = (float)$r['payments_sum'];

    $net = $creates - $payments;
    $balance = $startBalance + $net;

    $rows[] = [
        'object' => $idObject,
        'creates' => $creates,
        'payments' => $payments,
        'balance' => $balance
    ];

    $totalCreates += $creates;
    $totalPayments += $payments;
}

/*
|--------------------------------------------------------------------------
| FINAL BALANCE
|--------------------------------------------------------------------------
*/

$endBalance = $startBalance + ($totalCreates - $totalPayments);

?>

<div class="card shadow border-0 mb-4">

    <div class="card-header">
        <h5 class="mb-0 text-primary">
            📊 Обектен финансов отчет
        </h5>
        <div class="small text-muted">
            Салдо по обекти (ERP режим)
        </div>
    </div>

    <div class="card-body">

        <!-- KPI -->
        <div class="row g-3 mb-4">

            <div class="col-md-4">
                <div class="card bg-dark text-white">
                    <div class="card-body">
                        <div class="small">Начално салдо</div>
                        <div class="fs-5 fw-bold">
                            <?= number_format($startBalance, 2) ?> €
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card bg-danger text-white">
                    <div class="card-body">
                        <div class="small">Нови задължения</div>
                        <div class="fs-5 fw-bold">
                            <?= number_format($totalCreates, 2) ?> €
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card bg-success text-white">
                    <div class="card-body">
                        <div class="small">Разплащания</div>
                        <div class="fs-5 fw-bold">
                            <?= number_format($totalPayments, 2) ?> €
                        </div>
                    </div>
                </div>
            </div>

        </div>

        <!-- FINAL -->
        <div class="alert alert-primary text-center">
            <h5 class="mb-0">
                Крайно салдо:
                <strong><?= number_format($endBalance, 2) ?> €</strong>
            </h5>
        </div>

        <!-- TABLE -->
        <div class="table-responsive">

            <table class="table table-hover">

                <thead>
                <tr>
                    <th>Обект</th>
                    <th class="text-end">Нови задължения</th>
                    <th class="text-end">Разплащания</th>
                    <th class="text-end">Нетно</th>
                    <th class="text-end">Салдо</th>
                </tr>
                </thead>

                <tbody>

                <?php foreach ($rows as $r): ?>

                    <tr>
                        <td><?= $r['object'] ?></td>

                        <td class="text-end text-danger">
                            <?= number_format($r['creates'], 2) ?>
                        </td>

                        <td class="text-end text-success">
                            <?= number_format($r['payments'], 2) ?>
                        </td>

                        <td class="text-end fw-bold">
                            <?= number_format($r['creates'] - $r['payments'], 2) ?>
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
</div>

<!-- CHART -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>

    const labels = <?= json_encode(array_column($rows, 'object')) ?>;
    const creates = <?= json_encode(array_column($rows, 'creates')) ?>;
    const payments = <?= json_encode(array_column($rows, 'payments')) ?>;

    new Chart(document.getElementById('chart'), {
        type: 'bar',
        data: {
            labels,
            datasets: [
                {
                    label: 'Нови задължения',
                    data: creates,
                    backgroundColor: '#dc3545'
                },
                {
                    label: 'Разплащания',
                    data: payments,
                    backgroundColor: '#198754'
                }
            ]
        },
        options: {
            responsive: true
        }
    });

</script>

<canvas id="chart" style="height:400px"></canvas>