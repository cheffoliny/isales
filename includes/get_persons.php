<?php

include_once __DIR__ . '/../includes/functions.php';

if (empty($_SESSION['user_id'])) {
    echo json_encode(['success' => false]);
    exit;
}

$db = db_connect('personnel');
$db_sod = db_connect('sod');

$sql = "
        SELECT
            p.id,
            p.fname,
            p.lname,
            p.status,
            aa.id AS account_id,
            aa.username,
            aa.id_profile,
            GROUP_CONCAT(DISTINCT aof.id_office) AS offices_ids,
            COALESCE(GROUP_CONCAT(DISTINCT offs.name SEPARATOR ', '),'—') AS office_name
        FROM personnel p
        LEFT JOIN ".DB_NAMES['system'].".access_account aa
            ON p.id = aa.id_person
        LEFT JOIN ".DB_NAMES['system'].".account_office aof
            ON aa.id = aof.id_account
        LEFT JOIN ".DB_NAMES['sod'].".offices offs
            ON aof.id_office = offs.id
        GROUP BY p.id
        ORDER BY p.fname, p.lname ASC
        ";

$result = $db->query($sql);

$html = '';

while($row = $result->fetch_assoc()){

    $id = (int)$row['id'];
    $officesArr = array_filter(explode(',', $row['offices_ids'] ?? ''));
    $offices = json_encode(array_values($officesArr));

    $html .= '
    <div class="card mb-2 shadow-sm border-0 person-item"
         data-person-id="'.$id.'"
         data-status="'.$row['status'].'"
         data-profile="'.(int)($row['id_profile'] ?? 0).'"
         data-name="'.strtolower($row['fname'].' '.$row['lname']).'">

        <div class="card-body d-flex align-items-center justify-content-between p-2">

            <button class="btn btn-primary openPersonModal"
                    data-id="'.$id.'"
                    data-name="'.htmlspecialchars($row['fname']).'"
                    data-lname="'.htmlspecialchars($row['lname']).'"
                    data-status="'.$row['status'].'">
                <i class="fa fa-user"></i>
            </button>

            <div class="flex-grow-1 px-2">
                <div class="fw-bold person-fullname">
                    '.htmlspecialchars($row['fname'].' '.$row['lname']).'
                </div>
            </div>


            <button class="btn btn-sm btn-outline-primary openUserModal"
                    data-person-id="'.$id.'"
                    data-account-id="'.(int)$row['account_id'].'"
                    data-username="'.htmlspecialchars($row['username'] ?? '').'"
                    data-profile="'.(int)$row['id_profile'].'"
                    data-offices=\''.$offices.'\'>
                <i class="fa fa-key"></i>
            </button>

        </div>
    </div>';
}

echo json_encode([
    'success' => true,
    'html' => $html
]);