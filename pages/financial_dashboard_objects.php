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
| ПЕРИОД
|--------------------------------------------------------------------------
*/

$selectedMonth = $_GET['month'] ?? date('Y-m');

if (!preg_match('/^\d{4}-\d{2}$/', $selectedMonth)) {
    $selectedMonth = date('Y-m');
}

$start = date('Y-m-01 00:00:00', strtotime($selectedMonth . '-01'));
$end   = date('Y-m-01 00:00:00', strtotime($start . ' +1 month'));

/*
|--------------------------------------------------------------------------
| НАЧАЛНО САЛДО
|--------------------------------------------------------------------------
*/

$stmt = $db->prepare("
    SELECT
        SUM(
            CASE 
                WHEN transaction_type = 'credit' THEN -amount
                WHEN transaction_type = 'debit' THEN amount
                ELSE 0
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
| ДАННИ ПО ОБЕКТИ (ПЕРИОД)
|--------------------------------------------------------------------------
*/

$sql = "
SELECT
    o.`name` AS name_object,

    SUM(CASE WHEN oo.transaction_type='debit' THEN oo.amount ELSE 0 END) AS creates_sum,
    SUM(CASE WHEN oo.transaction_type='credit' THEN oo.amount ELSE 0 END) AS payments_sum

FROM objects_obligation_transactions oo
JOIN ". DB_NAMES['sod'] .".objects o ON o.id = oo.id_object
WHERE oo.transaction_date >= ?
  AND oo.transaction_date < ?
GROUP BY oo.id_object
#HAVING (creates_sum - payments_sum) > 0
ORDER BY oo.id_object
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

$totalCreates = 0.0;
$totalPayments = 0.0;

while ($r = $res->fetch_assoc()) {

    $nObject = $r['name_object'];

    $creates  = (float)($r['creates_sum'] ?? 0);
    $payments = (float)($r['payments_sum'] ?? 0);

    $net = $creates - $payments;

    // баланс без NULL риска
    $balance = $startBalance + $net;

    $rows[] = [
        'object'   => $nObject,
        'creates'  => $creates,
        'payments' => $payments,
        'net'      => $net,
        'balance'  => $balance
    ];

    $totalCreates  += $creates;
    $totalPayments += $payments;
}

$stmt->close();

/*
|--------------------------------------------------------------------------
| КРАЙНО САЛДО
|--------------------------------------------------------------------------
*/

$endBalance = $startBalance + ($totalCreates - $totalPayments);

/*
|--------------------------------------------------------------------------
| SORT TOP DEBTORS
|--------------------------------------------------------------------------
*/

$sorted = $rows;

usort($sorted, function ($a, $b) {
    return $b['net'] <=> $a['net'];
});

?>

<!-- ================= UI ================= -->

<div class="card shadow border-0 mb-4">

    <div class="card-header">
        <h5 class="mb-0"><i class="fa-solid fa-coins"></i> Непогасени задължения (ПО ОБЕКТИ) </h5>
        <div class="small text-muted">
            Начално салдо → Нови задължения → Разплащания → Крайно салдо
        </div>
    </div>

    <div class="card-body">

        <!-- KPI -->
        <div class="row g-3 mb-4">

            <div class="col-md-3">
                <div class="card bg-secondary text-white">
                    <div class="card-body">
                        <div class="fs-5">Начално салдо</div>
                        <div class="small">Всички движения преди избрания период</div>
                        <div class="fs-5"><?= number_format((float)$startBalance, 2) ?> €</div>
                    </div>
                </div>
            </div>

            <div class="col-md-3">
                <div class="card bg-danger text-white">
                    <div class="card-body">
                        <div class="fs-5">Нови задължения</div>
                        <div class="small">Всички натрупани задължения в НАЧАЛОТО на периода</div>
                        <div class="fs-5">-<?= number_format((float)$totalCreates, 2) ?> €</div>
                    </div>
                </div>
            </div>

            <div class="col-md-3">
                <div class="card bg-success text-white">
                    <div class="card-body">
                        <div class="fs-5">Разплащания</div>
                        <div class="small">Всички разплащания за избрания период</div>
                        <div class="fs-5">+<?= number_format((float)$totalPayments, 2) ?> €</div>
                    </div>
                </div>
            </div>

            <div class="col-md-3">
                <div class="card bg-primary text-white">
                    <div class="card-body">
                        <div class="fs-5">Крайно салдо</div>
                        <div class="small">Всички натрупани задължения в КРАЯ на периода</div>
                        <div class="fs-5"><?= number_format((float)$endBalance, 2) ?> €</div>
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
                <canvas id="chart" style="height:400px"></canvas>
            </div>
        </div>

        <!-- TOP DEBTORS -->
        <div class="card shadow border-0 mt-4">

            <div class="card-header">
                <h6 class="mb-0 text-danger">
                    Най-задлъжнели обекти (ТОП 100)
                </h6>
            </div>

            <div class="table-responsive">

                <table class="table table-sm mb-0">

                    <thead>
                    <tr>
                        <th>Обект</th>
                        <th class="text-end">Нови</th>
                        <th class="text-end">Плащания</th>
                        <th class="text-end">Нетно</th>
                        <th class="text-end">Салдо</th>
                    </tr>
                    </thead>

                    <tbody>

                    <?php foreach (array_slice($sorted, 0, 100) as $r): ?>

                        <tr>
                            <td><?= $r['object'] ?></td>

                            <td class="text-end text-danger">
                                <?= number_format((float)$r['creates'], 2) ?>
                            </td>

                            <td class="text-end text-success">
                                <?= number_format((float)$r['payments'], 2) ?>
                            </td>

                            <td class="text-end fw-bold text-danger">
                                <?= number_format((float)$r['net'], 2) ?>
                            </td>

                            <td class="text-end fw-bold text-primary">
                                <?= number_format((float)$r['balance'], 2) ?>
                            </td>
                        </tr>

                    <?php endforeach; ?>

                    </tbody>

                </table>

            </div>
        </div>


    </div>
</div>

<!-- ================= CHART ================= -->

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>

    const labels = <?= json_encode(array_column($rows, 'object')) ?>;
    const creates = <?= json_encode(array_map('floatval', array_column($rows, 'creates'))) ?>;
    const payments = <?= json_encode(array_map('floatval', array_column($rows, 'payments'))) ?>;

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
            responsive: true,
            maintainAspectRatio: false
        }
    });

</script>