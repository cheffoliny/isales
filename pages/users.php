<?php
include_once __DIR__.'/../includes/functions.php';

if (empty($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$db = db_connect('personnel');
$db_sod = db_connect('sod');
/* ===== LOAD OFFICES ===== */
$offices = [];
$resOff = $db_sod->query("SELECT id,name FROM offices WHERE to_arc = 0 ORDER BY name");
while($r = $resOff->fetch_assoc()){
    $offices[] = $r;
}

/* ===== LOAD OBJECTS ===== */
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

                MAX(CASE WHEN aof.id_office = 0 THEN 1 ELSE 0 END) AS has_all_offices,
                CASE
                    WHEN MAX(CASE WHEN aof.id_office = 0 THEN 1 ELSE 0 END) = 1
                    THEN 'ВСИЧКИ'
                    WHEN aof.id_office IS NULL
                    THEN '—'
                    ELSE COALESCE(GROUP_CONCAT(DISTINCT offs.name SEPARATOR ', '), '—')
                END AS office_name

            FROM personnel p

            LEFT JOIN ". DB_NAMES['system'] .".access_account aa
                ON p.id = aa.id_person

            LEFT JOIN ". DB_NAMES['system'] .".account_office aof
                ON aa.id = aof.id_account

            LEFT JOIN ". DB_NAMES['sod'] .".offices offs
                ON aof.id_office = offs.id

            GROUP BY p.id
            ORDER BY p.fname, p.lname ASC
";

$result = $db->query($sql);
?>

<div class="card shadow mb-3 border-0">

    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0"> Клиент / Служител </h5>

        <div class="btn-group">

            <div class="d-flex gap-2 align-items-center">

                <!-- SEARCH -->
                <input type="text"
                       id="personSearch"
                       class="form-control form-control-sm"
                       placeholder="Търсене...">

                <!-- STATUS -->
                <select id="personStatusFilter"
                        class="form-select form-select-sm">

                    <option value="">Всички статуси</option>
                    <option value="active">Активни</option>
                    <option value="vacate">Неактивни</option>

                </select>

                <!-- PROFILE -->
                <select id="personProfileFilter" class="form-select form-select-sm">
                    <option value="">Всички профили</option>
                    <option value="1">Администратор</option>
                    <option value="2">Служител</option>
                    <option value="3">Клиент</option>
                    <option value="0">Без акаунт</option>
                </select>

            </div>

            <button id="addPerson"
                    class="btn btn-success btn-sm mx-2">
                + Добави
            </button>
        </div>
    </div>

<div id="personsContainer">

<?php if (!$result || $result->num_rows === 0): ?>
    <div class="alert alert-warning m-3 text-center">Няма Клиенти / Служители</div>
<?php else:

    $profiles = [
        1 => 'Администратор',
        2 => 'Служител',
        3 => 'Клиент',
        4 => 'Мениджър'
    ];

    while($row = $result->fetch_assoc()):

    $id   = (int)$row['id'];
    $name = htmlspecialchars($row['fname']);
    $lname = htmlspecialchars($row['lname']);
    $officesJson = $row['offices_ids'];


    $officeIdsRaw = array_map('intval', explode(',', $row['offices_ids'] ?? ''));
    $hasAllOffices = (int)($row['has_all_offices'] ?? 0);

    // ако има "ВСИЧКИ"
    if ($hasAllOffices === 1) {
        $officeIds = [0]; // 👈 ключово
    } else {
        $officeIds = array_values(array_filter($officeIdsRaw, fn($v) => $v > 0));
    }
    $hasAllOffices = (int)($row['has_all_offices'] ?? 0);

?>

    <div class="card mb-2 shadow-sm border-0 person-item" data-person-id="<?= $id ?>" data-status="<?= $row['status'] ?>"
         data-profile="<?= isset($row['id_profile']) ? (int)$row['id_profile'] : 0 ?>"
         data-name="<?= strtolower(
            htmlspecialchars(
                $row['fname'].' '.$row['lname']
            )
         ) ?>">

        <div class="card-body py-2 px-3">

            <div class="d-flex align-items-center">

                <!-- AVATAR -->
                <div class="me-3">
                    <div class="rounded-circle bg-primary d-flex align-items-center justify-content-center"
                         style="width:42px;height:42px;">
                        <i class="fa fa-user text-white"></i>
                    </div>
                </div>

                <!-- MAIN INFO -->
                <div class="flex-grow-1">

                    <div class="d-flex align-items-center gap-2">

                        <div class="fw-semibold person-fullname">
                            <?= $name . ' ' . $lname ?>
                        </div>

                        <?php if($row['status'] === 'active'): ?>
                            <span class="badge bg-success">Активен</span>
                        <?php else: ?>
                            <span class="badge bg-danger">Неактивен</span>
                        <?php endif; ?>

                        <?php if(!empty($row['account_id'])): ?>
                            <span class="badge bg-primary">
                                <?= $profiles[$row['id_profile']] ?? 'Потребител' ?>
                            </span>
                        <?php else: ?>
                            <span class="badge bg-secondary">
                                Без акаунт
                            </span>
                        <?php endif; ?>

                    </div>

                    <div class="text-muted small mt-1 person-offices">
                        <?= htmlspecialchars($row['office_name']) ?>
                    </div>

                </div>

                <!-- ACTIONS -->
                <div class="d-flex gap-2">

                    <button class="btn btn-outline-primary btn-sm openPersonModal"
                            data-id="<?= $id ?>"
                            data-name="<?= $name ?>"
                            data-lname="<?= $lname ?>"
                            data-status="<?= $row['status'] ?>"
                            data-all-offices="<?= $hasAllOffices ?>"
                            data-offices='<?= htmlspecialchars(json_encode($officeIds), ENT_QUOTES, "UTF-8") ?>'>
                        <i class="fa fa-pen"></i>
                    </button>

                    <button class="btn btn-outline-danger btn-sm openUserModal"
                            data-person-id="<?= $id ?>"
                            data-account-id="<?= (int)$row['account_id'] ?>"
                            data-username="<?= htmlspecialchars($row['username'] ?? '') ?>"
                            data-profile="<?= (int)$row['id_profile'] ?>"
                            data-all-offices="<?= $hasAllOffices ?>"
                            data-offices='<?= htmlspecialchars(json_encode($officeIds), ENT_QUOTES, "UTF-8") ?>'>
                        <i class="fa fa-key"></i>
                    </button>

                </div>

            </div>

        </div>
    </div>

    <?php endwhile; ?>
    <?php endif; ?>

    </div>
</div>
<!-- MODAL -->
<div class="modal fade" id="personModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">

            <div class="modal-header">
                <h5 class="modal-title">Редакция</h5>
                <button class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">

                <input type="hidden" id="modal_person_id">

                <div class="mb-2">
                    <label>Име / Фирма *</label>
                    <input type="text" id="modal_person_name" class="form-control form-control-sm">
                </div>
                <div class="mb-2">
                    <label>Фамилия / Правна форма *</label>
                    <input type="text" id="modal_person_lname" class="form-control form-control-sm">
                </div>

                <div class="mb-2">
                    <label>Статус</label>
                    <select id="modal_person_status" class="form-select form-select-sm">
                        <option value="active">Активен</option>
                        <option value="vacate">Неактивен</option>
                    </select>
                </div>
            </div>

            <div class="modal-footer">
                <button class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Затвори</button>
                <button class="btn btn-success btn-sm" id="savePersonBtnModal">Запази</button>
            </div>

        </div>
    </div>
</div>


<!-- MODAL -->
<div class="modal fade" id="userLevelModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">

            <div class="modal-header">
                <h5 class="modal-title">Редакция</h5>
                <button class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">

                <input type="hidden" id="modal_user_person_id">
                <input type="hidden" id="modal_account_id">

                <!-- USERNAME -->
                <div class="mb-2">
                    <label>Потребителско име *</label>

                    <input type="text"
                           id="modal_username"
                           class="form-control form-control-sm">
                </div>

                <!-- PASSWORD -->
                <div class="mb-2">
                    <label>Парола</label>

                    <input type="password"
                           id="modal_password"
                           class="form-control form-control-sm">

                    <small class="text-muted">
                        Оставете празно ако не желаете смяна
                    </small>
                </div>

                <!-- PROFILE -->
                <div class="mb-2">

                    <label>Профил *</label>

                    <select id="modal_profile" class="form-select form-select-sm">
                        <option value="1">Администратор</option>
                        <option value="4">Мениджър</option>
                        <option value="2">Служител</option>
                        <option value="3">Клиент</option>
                    </select>

                </div>

                <!-- OFFICES -->
                <div class="mb-2">

                    <label>Офиси</label>

                    <div class="border rounded p-2"
                         style="max-height:200px;overflow:auto;">

                        <div class="row">
                                <div class="col-6 col-md-4">

                                    <div class="form-check">

                                        <input type="checkbox"
                                               class="form-check-input user-office-checkbox"
                                               value="0"
                                               id="user_office_0">

                                        <label class="form-check-label"
                                               for="user_office_0">

                                            ВСИЧКИ

                                        </label>

                                    </div>

                                </div>
                            <?php foreach($offices as $off): ?>

                                <div class="col-6 col-md-4">

                                    <div class="form-check">

                                        <input type="checkbox"
                                               class="form-check-input user-office-checkbox"
                                               value="<?= $off['id'] ?>"
                                               data-name="<?= htmlspecialchars($off['name']) ?>"
                                               id="user_office_<?= $off['id'] ?>">

                                        <label class="form-check-label"
                                               for="user_office_<?= $off['id'] ?>">

                                            <?= htmlspecialchars($off['name']) ?>

                                        </label>

                                    </div>

                                </div>

                            <?php endforeach; ?>

                        </div>

                    </div>
                </div>

            </div>

            <div class="modal-footer">
                <button class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Затвори</button>
                <button class="btn btn-success btn-sm" id="saveUserBtnModal">Запази</button>
            </div>

        </div>
    </div>
</div>

<?php $db->close(); ?>