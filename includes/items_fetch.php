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

$idType = (int)($_GET['id_type'] ?? 0);
$page = (int)($_GET['page'] ?? 0);

$search = trim($_GET['search'] ?? '');
$newp = (int)($_GET['newp'] ?? 0);
$promo = (int)($_GET['promo'] ?? 0);

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

$sql = "
SELECT
    n.id,
    n.nom_code,
    n.name,
    n.client_price,
    n.sales_price,
    n.is_calc,
    n.image,
    n.is_new,
    n.promo_note
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

    $promoNote = htmlspecialchars($r['promo_note'] ?? '');

    // LIST VIEW
    $html .= "
    <tr>
        <td class='text-center'>{$r['nom_code']}</td>
        <td>{$r['name']} <span class='text-danger'>{$promoNote}</span></td>
        <td class='text-center'>{$r['is_calc']}</td>
        <td class='text-danger'>{$r['sales_price']}</td>
        <td>{$r['client_price']}</td>
        <td>
            <img src='includes/item_image_get.php?id={$id}'
                 style='max-height:40px;cursor:pointer'
                 class='item-thumb'
                 data-id='{$id}'
                 data-hasimage='".($hasImage ? 1 : 0)."'>
        </td>
    </tr>
    ";

    // GRID VIEW
    $img = $hasImage
        ? "includes/item_image_get.php?id={$id}"
        : "assets/images/na.jpg";

    $grid .= "
    <div class='col-6 col-md-4 col-lg-3'>
        <div class='card h-100 shadow-sm' data-id='{$id}'>
            <img src='{$img}'
                 class='card-img-top'
                 style='height:140px;object-fit:cover;cursor:pointer'
                 data-id='{$id}'
                 data-hasimage='".($hasImage ? 1 : 0)."'>

            <div class='card-body'>
                <div class='fw-bold small'>{$r['nom_code']} {$r['name']}</div>
                <div class='text-danger small'>{$promoNote}</div>
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