<?php
include_once __DIR__.'/functions.php';
$db=db_connect('storage');

$page=(int)($_GET['page']??0);
$search=trim($_GET['search']??'');
$promo=(int)($_GET['promo']??0);
$zero=(int)($_GET['zero']??0);

$limit=150;
$offset=$page*$limit;

$where=" WHERE n.to_arc=0 ";
//if($search!==''){
 //   $s=$db->real_escape_string($search);
  //  $where.=" AND (n.nom_code LIKE '%$s%' OR n.name LIKE '%$s%') ";
//}
if($promo){ $where.=" AND n.sales_price>0 "; }
if($zero){ $where.=" AND n.is_calc=0 "; }

$sql="SELECT n.id,n.nom_code,n.name,n.client_price,n.sales_price,n.is_calc,image FROM nomenclatures n $where ORDER BY n.nom_code LIMIT $limit OFFSET $offset";
$res=$db->query($sql);
$html='';

while($r=$res->fetch_assoc()){
    $imgClass = !empty($r['image']) ? 'btn-success' : 'btn-secondary';
    $hasImage = !empty($r['image']) ? 1 : 0;

    $html.='<tr data-id="'.$r['id'].'">
        <td>'.$r['nom_code'].'</td>
        <td>'.$r['name'].'</td>
        <td>'.$r['is_calc'].'</td>
        <td><input type="number" class="form-control form-control-sm client_price" value="'.$r['client_price'].'"></td>
        <td><input type="number" class="form-control form-control-sm sales_price" value="'.$r['sales_price'].'"></td>
        <td><button class="btn btn-sm '.$imgClass.' item-image" data-id="'.$r['id'].'" data-hasimage="'.$hasImage.'"><i class="fa-solid fa-image"></i></button></td>
        <td><button class="btn btn-sm btn-success save-item"><i class="fa-solid fa-check"></i></button></td>
    </tr>';
}

echo json_encode(['success'=>true,'html'=>$html]);