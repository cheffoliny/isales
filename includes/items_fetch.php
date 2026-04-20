<?php
include_once __DIR__.'/functions.php';

$strDisable = 'disabled';
if($_SESSION['is_admin'] == 1) {
    $strDisable = '';
}

$db = db_connect('storage');

$page = (int)($_GET['page'] ?? 0);
$search = trim($_GET['search'] ?? '');
$promo = (int)($_GET['promo'] ?? 0);
$zero = (int)($_GET['zero'] ?? 0);
$image = (int)($_GET['image'] ?? NULL);
$limit = 2000;
$offset = $page * $limit;
$offset = $limit;

$where = "WHERE n.to_arc=0";

// Защитен вход в заявката - ескейпваме
if ($search !== '') {
    $s = $db->real_escape_string($search);
    // Търсим в nom_code и name (case-insensitive по подразбиране)
    $where .= " AND (n.nom_code LIKE '%$s%' OR n.name LIKE '%$s%')";
}
if ($promo) {
    $where .= " AND n.sales_price > 0";
}
if ($zero) {
    $where .= " AND n.is_calc = 0";
} else {
    $where .= " AND n.is_calc > 0";
}
if ($image) {
    $where .= " AND n.image IS NULL ";
}

$sql = "SELECT n.id, n.nom_code, n.name, n.client_price, n.sales_price, n.is_calc, n.image
        FROM nomenclatures n
        $where
        ORDER BY n.nom_code
        LIMIT $limit 
        ";

$res = $db->query($sql);

$html = '';
$grid = '';

while ($r = $res->fetch_assoc()) {
    $hasImage = !empty($r['image']) ? 1 : 0;

    // Thumbnail for table view
    if ($hasImage) {
        $thumb = '<img src="includes/item_image_get.php?id='.$r['id'].'"
                        style="max-height:40px;cursor:pointer"
                        class="item-thumb"
                        data-id="'.$r['id'].'"
                        data-hasimage="1">';
    } else {
        $thumb = '<div class="item-thumb bg-secondary text-white text-center"
                        style="width:40px;height:40px;line-height:40px;cursor:pointer"
                        data-id="'.$r['id'].'"
                        data-hasimage="0">-</div>';
    }

    // Табличен ред
    $html .= '<tr data-id="'.$r['id'].'">
        <td>'.htmlspecialchars($r['nom_code']).'</td>
        <td>'.htmlspecialchars($r['name']).'</td>
        <td>'.(int)$r['is_calc'].'</td>
        <td><input type="number" class="form-control form-control-sm client_price" value="'.number_format((float)$r['client_price'], 2, '.', '').'"  '.$strDisable.'></td>
        <td><input type="number" class="form-control form-control-sm sales_price" value="'.number_format((float)$r['sales_price'], 2, '.', '').'" '.$strDisable.'></td>
        <td>'.$thumb.'</td>
        <td>'.
            ($_SESSION['is_admin'] == 1
                ? '<button class="btn btn-sm btn-success save-item"><i class="fa-solid fa-check"></i></button>'
                : ''
            ).
        '</td>
    </tr>';
    // Grid card

    // Цена и промо текст
    $clientPrice = number_format((float)$r['client_price'], 2, '.', '');
    $salesPrice = (float)$r['sales_price'];
    $promoBadge = '';
    $priceDisplay = '<div class="fw-bold">'.$clientPrice.' лв</div>';
    if ($salesPrice > 0) {
        $salesFormatted = number_format($salesPrice, 2, '.', '');
        $promoBadge = '<span class="badge bg-danger position-absolute top-0 start-0 m-2">ПРОМО</span>';
        $priceDisplay = '<div class="fw-bold text-danger"><del class="text-muted me-2">'.$clientPrice.' лв</del>'.$salesFormatted.' лв</div>';
    }

    // Image HTML for grid
    $imgSrc = $hasImage
        ? 'includes/item_image_get.php?id='.$r['id']
        : 'assets/images/na.jpg';

    // Card HTML
    $grid .= '
    <div class="col-6 col-md-4 col-lg-3">
        <div class="card h-100 shadow-sm position-relative">
            '.$promoBadge.'
            <img src="'.$imgSrc.'" alt="'.htmlspecialchars($r['name']).'" class="card-img-top" style="object-fit:cover; max-height:150px; cursor:pointer;" data-id="'.$r['id'].'" data-hasimage="'.$hasImage.'">
            <div class="card-body p-2">
                <h6 class="card-title mb-1 text-truncate" title="'.htmlspecialchars($r['name']).'">'.htmlspecialchars($r['name']).'</h6>
                '.$priceDisplay.'
            </div>
        </div>
    </div>';
}

echo json_encode(['success'=>true,'html'=>$html,'grid'=>$grid]);