<?php
include_once __DIR__.'/functions.php';

$db = db_connect('storage');

$page=(int)($_GET['page'] ?? 0);
$search=trim($_GET['search'] ?? '');
$promo=(int)($_GET['promo'] ?? 0);
$zero=(int)($_GET['zero'] ?? 0);

$limit=50;
$offset=$page*$limit;

$where="WHERE n.to_arc=0";

if($search!=''){

    $s=$db->real_escape_string($search);

        $where.=" AND (
            n.nom_code LIKE '%$s%'
            OR n.name LIKE '%$s%'
        )";

    }

    if($promo){
        $where.=" AND n.sales_price>0";
    }

    if($zero){
        $where.=" AND n.is_calc=0 OR n.client_price=0";
    }

    $sql="
        SELECT
            n.id,
            n.nom_code,
            n.name,
            n.client_price,
            n.sales_price,
            n.is_calc,
            n.unit,
            COALESCE(
                        (
                        SELECT SUM(pe.`count`)
                        FROM ppp_elements pe
                        JOIN ppp p ON p.id = pe.id_ppp AND DATE(p.source_date) = DATE(NOW())
                        WHERE pe.id_nomenclature = n.id
                        )
            , 0) AS ordered_count
        FROM nomenclatures n
        $where
        ORDER BY n.name
        LIMIT $limit
        OFFSET $offset
    ";

    $res=$db->query($sql);
    $html='';
    while($r=$res->fetch_assoc()){

    $html.='

    <tr data-id="'.$r['id'].'">
    <td>'.$r['nom_code'].'</td>
    <td>'.$r['name'].'</td>
    <td><input type="number" class="form-control form-control-sm is_calc" value="'.$r['is_calc'].'"> / '.$r['ordered_count.']'</td>
    <td><input type="number" step="0.01" class="form-control form-control-sm client_price" value="'.$r['client_price'].'"></td>
    <td><input type="number" step="0.01" class="form-control form-control-sm sales_price" value="'.$r['sales_price'].'"></td>

    <td>

    <button class="btn btn-sm btn-success save-item">

    <i class="fa-solid fa-check"></i>

    </button>

    </td>

    </tr>

    ';

}

echo json_encode([
'success'=>true,
'html'=>$html
]);