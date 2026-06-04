<?php
include_once __DIR__.'/../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['user_id'])) {
    echo json_encode([
        'success' => false,
        'error' => 'unauthorized'
    ]);
    exit;
}

$isAdmin = ($_SESSION['is_admin'] ?? 0) == 1;

$idType = (int)($_GET['id_type'] ?? 0);
$page = (int)($_GET['page'] ?? 0);

$search = trim($_GET['search'] ?? '');
$newp = (int)($_GET['newp'] ?? 0);
$promo = (int)($_GET['promo'] ?? 0);

$zero = (int)($_GET['zero'] ?? 0);
$image = (int)($_GET['image'] ?? 0);
$norder = (int)($_GET['norder'] ?? 0);
$noCategory = (int)($_GET['no_category'] ?? 0);

$limit = 20;
$offset = $page * $limit;

$db = db_connect('storage');

$where = " WHERE n.to_arc = 0 ";

if ($idType > 0) {
    $where .= " AND n.id_type = $idType ";
}

if ($search !== '') {
    $s = $db->real_escape_string($search);
    $where .= " AND (n.nom_code LIKE '%$s%' OR n.name LIKE '%$s%') ";
}

if ($newp) {
    $where .= " AND n.is_new = 1 ";
}

if ($promo) {
    $where .= " AND n.sales_price > 0 ";
}

if ($zero && $isAdmin) {
    $where .= " AND n.is_calc <= 0 ";
} else {
    $where .= " AND n.is_calc > 0 ";
}

if ($image && $isAdmin) {
    $where .= " AND (n.image IS NULL OR n.image = '') ";
}

if ($noCategory && $isAdmin) {
    $where .= " AND n.id_type = 0 AND LENGTH(n.nom_code) > 3 ";
}
/*
|--------------------------------------------------------------------------
| norder placeholder
|--------------------------------------------------------------------------
| Оставям го безопасно без SQL условие, защото в дадения код няма таблица
| или поле за покупки/поръчки.
|--------------------------------------------------------------------------
*/

$sql = "
SELECT
    n.id,
    n.id_type,
    n.nom_code,
    n.name,
    n.client_price,
    n.sales_price,
    n.is_calc,
    n.image,
    n.is_new,
    n.promo_note,
    n.unit
FROM nomenclatures n
$where
ORDER BY n.nom_code
LIMIT $limit OFFSET $offset
";

$res = $db->query($sql);

if (!$res) {
    echo json_encode([
        'success' => false,
        'error' => $db->error
    ]);
    exit;
}

$html = '';
$grid = '';

while ($r = $res->fetch_assoc()) {

    $hasImage = !empty($r['image']);

    $id = (int)$r['id'];
    $itemType = (int)$r['id_type'];

    $nomCode = htmlspecialchars((string)$r['nom_code']);
    $name = htmlspecialchars((string)$r['name']);
    $unit = htmlspecialchars((string)$r['unit']);
    $promoNote = htmlspecialchars((string)($r['promo_note'] ?? ''));

    $clientPrice = htmlspecialchars((string)$r['client_price']);
    $salesPrice = htmlspecialchars((string)$r['sales_price']);
    $isCalc = htmlspecialchars((string)$r['is_calc']);
    $isNew = (int)$r['is_new'];



    $textColor = $isNew > 0 ? ' text-success ' : '';

    $adminRowClass = $isAdmin ? 'item-row' : '';
    $adminCardClass = $isAdmin ? 'item-card' : '';

    $cursor = $isAdmin ? 'cursor:pointer' : 'cursor:default';

    $img = $hasImage
        ? "includes/item_image_get.php?id={$id}"
        : "assets/images/na.jpg";

    $listImg = $hasImage
        ? "<img src='includes/item_image_get.php?id={$id}'
                 style='max-height:40px;cursor:pointer'
                 class='item-thumb'
                 data-id='{$id}'
                 data-hasimage='1'>"
        : "<img src='assets/images/na.jpg'
                 style='max-height:40px'
                 class='item-thumb'
                 data-hasimage='0'>";

    $html .= "
        <tr class='{$adminRowClass}'
            data-id='{$id}'
            data-client='{$clientPrice}'
            data-sales='{$salesPrice}'
            data-note='{$promoNote}'
            data-new='{$isNew}'
            data-type='{$itemType}'
            style='{$cursor}'>
            <td class='text-center {$textColor}'>{$nomCode}</td>
            <td>{$name} <span class='text-danger'>{$promoNote}</span></td>
            <td class='text-center'>{$isCalc} <small class='badge badge-secondary'>{$unit}</small></td>
            <td class='text-danger'>{$salesPrice}</td>
            <td>{$clientPrice}</td>
            <td class='bg-light text-center'>
                {$listImg}
            </td>
        </tr>
    ";

    $grid .= "
        <div class='col-6 col-md-4 col-lg-3 col-xl-2'>
            <div class='card h-100 shadow-sm {$adminCardClass}'
                 data-id='{$id}'
                 data-client='{$clientPrice}'
                 data-sales='{$salesPrice}'
                 data-note='{$promoNote}'
                 data-new='{$isNew}'
                 data-type='{$itemType}'
                 style='{$cursor}'>

                <img src='{$img}'
                     class='card-img-top'
                     style='height:220px;object-fit:cover;background:#f8f9fa;cursor:pointer'
                     onerror=\"this.onerror=null;this.src='assets/images/na.jpg';\"
                     data-id='{$id}'
                     data-hasimage='".($hasImage ? 1 : 0)."'>

                <div class='card-body p-2 d-flex flex-column'>
                    <div class='small'
                         style='min-height:38px;line-height:1.15;overflow:hidden'>
                        {$name}
                    </div>

                    ".($promoNote !== '' ? "
                        <div class='text-danger small mt-1'
                             style='line-height:1.15'>
                            {$promoNote}
                        </div>
                    " : "")."

                    <div class='mt-auto pt-2 d-flex justify-content-between align-items-center gap-2 small'>
                        <span class='btn btn-sm bg-success text-white py-0 px-2'>
                            {$isCalc} <small class='badge bg-success'>/ {$unit} /</small>
                        </span>

                        <span class='ms-auto fw-semibold ".($salesPrice > 0 ? "text-decoration-line-through text-muted" : "")."'>
                            {$clientPrice}€
                        </span>

                        ".($salesPrice > 0 ? "
                            <span class='badge bg-danger fw-semibold fs-6'>
                                {$salesPrice}€
                            </span>
                        " : "")."
                    </div>
                </div>
            </div>
        </div>
    ";
}

echo json_encode([
    'success' => true,
    'html' => $html,
    'grid' => $grid
]);