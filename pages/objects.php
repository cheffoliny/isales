<?php
include_once __DIR__ . '/../includes/functions.php';

if (empty($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$where_offices = '';
if ($_SESSION['offices_ids'] != '0') {
    $where_offices = ' AND offs.id IN(' . $_SESSION['offices_ids'] . ') ';
}

$db = db_connect('sod');

/* ===== LOAD OFFICES ===== */
$offices = [];
$resOff = $db->query("SELECT id,name FROM offices WHERE to_arc = 0 ORDER BY name");
while ($r = $resOff->fetch_assoc()) {
    $offices[] = $r;
}

/* ===== LOAD OBJECTS ===== */
$sql = "
    SELECT
        o.id,
        o.name,
        COALESCE(o.operativ_info,'') AS info,
        COALESCE(o.offices_ids,'...') AS offices_ids,
        o.geo_lat,
        o.geo_lan,
        COALESCE(GROUP_CONCAT(offs.name SEPARATOR ', '), '—') AS office_name
    FROM objects o
    JOIN offices_objects oo ON oo.id_object = o.id AND oo.to_arc = 0
    JOIN offices offs ON offs.id = oo.id_office
    WHERE o.id_status <> 4
        " . $where_offices . "
    GROUP BY o.id
    ORDER BY o.name ASC
    LIMIT 1000
";

$result = $db->query($sql);
?>

<div class="card shadow mb-3 border-0">

    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0"> Обекти </h5>

        <div class="btn-group">
            <button id="filterNoGeo" class="btn btn-sm btn-outline-primary">
                БЕЗ КООРДИНАТИ
            </button>
            <button id="filterAll" class="btn btn-sm btn-primary">
                ВСИЧКИ
            </button>
        </div>
    </div>

    <div id="objectsContainer">

        <?php if (!$result || $result->num_rows === 0): ?>
            <div class="alert alert-warning m-3 text-center">Няма обекти</div>
        <?php else: ?>

            <?php while ($row = $result->fetch_assoc()):

                $id   = (int)$row['id'];
                $name = htmlspecialchars($row['name']);
                $info = htmlspecialchars($row['info']);
                $officesJson = htmlspecialchars($row['offices_ids'], ENT_QUOTES);

                $lat = $row['geo_lat'] ?: 43.2728759;
                $lng = $row['geo_lan'] ?: 26.9266601;
            ?>

                <div class="card mb-2 shadow-sm border-0 object-item"
                     data-lat="<?= (float)$row['geo_lat'] ?>"
                     data-lng="<?= (float)$row['geo_lan'] ?>">

                    <div class="card-body d-flex align-items-center justify-content-between p-2">

                        <button class="btn btn-primary openObjectModal"
                                data-id="<?= $id ?>"
                                data-name="<?= $name ?>"
                                data-info="<?= $info ?>"
                                data-offices='<?= $officesJson ?>'
                                data-lat="<?= $lat ?>"
                                data-lng="<?= $lng ?>">
                            <i class="fa fa-home"></i>
                        </button>

                        <div class="flex-grow-1 px-2">
                            <button class="btn p-0 text-start w-100 openObjectModal"
                                    data-id="<?= $id ?>"
                                    data-name="<?= $name ?>"
                                    data-info="<?= $info ?>"
                                    data-offices='<?= $officesJson ?>'
                                    data-lat="<?= $lat ?>"
                                    data-lng="<?= $lng ?>">
                                <div class="fw-bold"><?= $name ?></div>
                                <div class="small text-muted"><?= htmlspecialchars($row['office_name']) ?></div>
                            </button>
                        </div>

                        <?php if ($_SESSION['is_admin'] == 1) { ?>
                            <button class="btn btn-secondary mx-1"
                                   >
                                <i class="fa-solid fa-coins fa-lg"></i>
                            </button>
                            <button class="btn btn-warning openObligationModal ms-auto"
                                    data-id="<?= $id ?>"
                                    data-name="<?= $name ?>">
                                <i class="fa-solid fa-coins fa-lg"></i>
                            </button>
                        <?php } ?>

                    </div>
                </div>

            <?php endwhile; ?>
        <?php endif; ?>

    </div>
</div>

<!-- OBJECT MODAL -->
<div class="modal fade" id="objectModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">

            <div class="modal-header">
                <h5 class="modal-title">Редакция</h5>
                <button class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">

                <input type="hidden" id="modal_object_id">

                <div class="mb-2">
                    <label>Име *</label>
                    <input type="text" id="modal_object_name" class="form-control form-control-sm">
                </div>

                <div class="mb-2">
                    <label>Маршрути (незадължително)</label>

                    <div class="border rounded p-2" style="max-height:150px;overflow:auto;">
                        <div class="row">
                            <?php foreach ($offices as $off): ?>
                                <div class="col-6 col-md-4">
                                    <div class="form-check">
                                        <input type="checkbox"
                                               class="form-check-input object-office-checkbox"
                                               value="<?= $off['id'] ?>"
                                               id="office_<?= $off['id'] ?>">
                                        <label class="form-check-label" for="office_<?= $off['id'] ?>">
                                            <?= htmlspecialchars($off['name']) ?>
                                        </label>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <div class="mb-2">
                    <label>Оперативна информация</label>
                    <textarea id="modal_object_info" class="form-control form-control-sm"></textarea>
                </div>

                <div id="objectMapContainer" style="height:400px;"></div>

            </div>

            <div class="modal-footer">
                <button class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Затвори</button>
                <button class="btn btn-success btn-sm" id="saveObjectBtnModal">Запази</button>
            </div>

        </div>
    </div>
</div>

<!-- OBLIGATION MODAL -->
<div class="modal fade" id="obligationModal" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content">

            <div class="modal-header py-2">
                <h5 class="modal-title mb-0">
                    <span id="obligation_object_name_title"></span>
                </h5>
                <button class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body py-2">

                <input type="hidden" id="obligation_object_id">
                <input type="hidden" id="obligation_object_name">

                <div class="d-flex justify-content-between align-items-center border-bottom pb-1 mb-2">
                    <h6 class="fw-bold mb-0 text-danger">
                        Стари непогасени задължения
                    </h6>
                    <div class="fw-bold text-danger">
                        Общо остатък: <span id="oldObligationsTotal">0.00</span> €
                    </div>
                </div>
                <div id="oldObligationsList" class="mb-3 small">
                    <div class="text-muted">Зареждане...</div>
                </div>

                <div class="d-flex justify-content-between align-items-center border-bottom pb-1 mb-2">
                    <h6 class="fw-bold mb-0 text-success">
                        Погасяване
                    </h6>
                </div>

                <div class="row g-2 align-items-end mb-3">
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold mb-1">Сума за погасяване</label>
                        <input type="number"
                               step="0.01"
                               min="0"
                               id="payment_sum"
                               class="form-control form-control-sm text-end"
                               placeholder="0.00">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label small fw-semibold mb-1">Разнесена сума</label>
                        <input type="text"
                               id="payment_distributed_sum"
                               class="form-control form-control-sm text-end"
                               value="0.00"
                               readonly>
                    </div>

                    <div class="col-md-4">
                        <button class="btn btn-success btn-sm w-100"
                                id="payObligationsBtn">
                            <i class="fa-solid fa-money-bill-wave"></i>
                            Плати
                        </button>
                    </div>
                </div>

                <div class="d-flex justify-content-between align-items-center border-bottom pb-1 mb-2">
                    <h6 class="fw-bold mb-0 text-primary">
                        ППП без добавено задължение
                    </h6>
                </div>

                <div id="pppWithoutObligationList" class="mb-3 small">
                    <div class="text-muted">Зареждане...</div>
                </div>

                <div class="d-flex justify-content-between align-items-center border-bottom pb-1 mb-2">
                    <h6 class="fw-bold mb-0 text-secondary">
                        Ръчно добавяне на задължение
                    </h6>
                </div>

                <div class="row g-2 align-items-end mb-2">
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold mb-1">Дата *</label>
                        <input type="text"
                               id="obligation_from_date"
                               class="form-control form-control-sm"
                               value="<?= date('d.m.Y') ?>"
                               placeholder="дд.мм.гггг">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label small fw-semibold mb-1">Сума *</label>
                        <input type="number"
                               step="0.01"
                               min="0"
                               id="obligation_total_sum"
                               class="form-control form-control-sm text-end"
                               placeholder="0.00">
                    </div>

                    <div class="col-md-4">
                        <button class="btn btn-warning btn-sm w-100"
                                id="saveObligationBtn">
                            <i class="fa-solid fa-save"></i>
                            Запази ръчно
                        </button>
                    </div>
                </div>

                <div id="obligationError"
                     class="alert alert-danger d-none mb-0 py-2"></div>

            </div>

            <div class="modal-footer py-2">
                <button class="btn btn-secondary btn-sm"
                        data-bs-dismiss="modal">
                    Затвори
                </button>
            </div>

        </div>
    </div>
</div>

<?php $db->close(); ?>

<script>
document.addEventListener("DOMContentLoaded", function () {

    const btnNoGeo = document.getElementById("filterNoGeo");
    const btnAll   = document.getElementById("filterAll");
    const items    = document.querySelectorAll(".object-item");

    function showAll() {
        items.forEach(el => el.style.display = "");
        btnAll.classList.add("btn-primary");
        btnAll.classList.remove("btn-outline-primary");

        btnNoGeo.classList.remove("btn-primary");
        btnNoGeo.classList.add("btn-outline-primary");
    }

    function showNoGeo() {
        items.forEach(el => {
            let lat = parseFloat(el.dataset.lat) || 0;
            let lng = parseFloat(el.dataset.lng) || 0;

            if (lat === 0 || lng === 0 || lng === 26.9266601) {
                el.style.display = "";
            } else {
                el.style.display = "none";
            }
        });

        btnNoGeo.classList.add("btn-primary");
        btnNoGeo.classList.remove("btn-outline-primary");

        btnAll.classList.remove("btn-primary");
        btnAll.classList.add("btn-outline-primary");
    }

    btnNoGeo.addEventListener("click", showNoGeo);
    btnAll.addEventListener("click", showAll);

});

function formatBgDate(mysqlDate) {
    if (!mysqlDate) {
        return "";
    }

    const p = mysqlDate.split("-");

    if (p.length !== 3) {
        return mysqlDate;
    }

    return p[2] + "." + p[1] + "." + p[0];
}

function escapeHtml(value) {
    return $("<div>").text(value).html();
}

function loadObligationData(idObject) {

    $("#oldObligationsList").html('<div class="text-muted">Зареждане...</div>');
    $("#pppWithoutObligationList").html('<div class="text-muted">Зареждане...</div>');
    $("#oldObligationsTotal").text("0.00");

    $.post("includes/object_obligation_load.php", {
        id_object: idObject
    }, function (r) {

        if (!r.success) {
            $("#oldObligationsList").html(
                '<div class="alert alert-danger mb-2 py-2">' + escapeHtml(r.error || "Грешка при зареждане.") + '</div>'
            );
            $("#pppWithoutObligationList").html("");
            return;
        }

        let oldHtml = "";
        let oldTotal = 0;

        if (!r.old_obligations || r.old_obligations.length === 0) {
            oldHtml = '<div class="text-muted">Няма стари непогасени задължения.</div>';
        } else {
            oldHtml += `
                <div class="table-responsive">
                    <table class="table table-sm table-striped table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Дата</th>
                                <th>ППП</th>
                                <th class="text-end">Общо</th>
                                <th class="text-end">Платено</th>
                                <th class="text-end">Погасяване</th>
                                <th class="text-end">Остатък</th>
                            </tr>
                        </thead>
                        <tbody>
            `;

            r.old_obligations.forEach(function (row) {
                const diff = parseFloat(row.diff) || 0;
                oldTotal += diff;

                oldHtml += `
                    <tr class="old-obligation-row"
                        data-id="${escapeHtml(row.id)}"
                        data-paid="${parseFloat(row.paid_sum).toFixed(2)}"
                        data-diff="${diff.toFixed(2)}">
                        <td class="text-nowrap">${formatBgDate(row.from_date)}</td>
                        <td class="text-nowrap">${row.id_ppp ? '#' + escapeHtml(row.id_ppp) : '—'}</td>
                        <td class="text-end text-nowrap">${parseFloat(row.total_sum).toFixed(2)} €</td>
                        <td class="text-end text-nowrap">
                            <span class="paid-current">${parseFloat(row.paid_sum).toFixed(2)}</span> €
                        </td>
                        <td class="text-end text-nowrap text-success fw-bold">
                            +<span class="pay-add">0.00</span> €
                        </td>
                        <td class="text-end text-nowrap fw-bold text-danger">
                            <span class="diff-after">${diff.toFixed(2)}</span> €
                        </td>
                    </tr>
                `;
            });

            oldHtml += `
                        </tbody>
                    </table>
                </div>
            `;
        }

        $("#oldObligationsTotal").text(oldTotal.toFixed(2));
        $("#oldObligationsList").html(oldHtml);

        let pppHtml = "";

        if (!r.ppp_without_obligation || r.ppp_without_obligation.length === 0) {
            pppHtml = '<div class="text-muted">Няма ППП без добавено задължение.</div>';
        } else {
            pppHtml += `
                <div class="table-responsive">
                    <table class="table table-sm table-striped table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Дата</th>
                                <th>ППП</th>
                                <th class="text-end">Сума</th>
                                <th style="width:120px;"></th>
                            </tr>
                        </thead>
                        <tbody>
            `;

            r.ppp_without_obligation.forEach(function (row) {
                pppHtml += `
                    <tr class="ppp-row" data-id-ppp="${escapeHtml(row.id_ppp)}">
                        <td class="text-nowrap">${formatBgDate(row.dest_date)}</td>
                        <td class="text-nowrap">#${escapeHtml(row.id_ppp)}</td>
                        <td>
                            <input type="number"
                                   step="0.01"
                                   min="0"
                                   class="form-control form-control-sm text-end ppp-sum"
                                   value="${parseFloat(row.ppp_sum).toFixed(2)}">
                        </td>
                        <td class="text-end text-nowrap">
                            <button class="btn btn-success btn-sm addPppObligationBtn"
                                    data-id-ppp="${escapeHtml(row.id_ppp)}"
                                    data-date="${escapeHtml(row.dest_date)}">
                                Добави
                            </button>
                        </td>
                    </tr>
                `;
            });

            pppHtml += `
                        </tbody>
                    </table>
                </div>
            `;
        }

        $("#pppWithoutObligationList").html(pppHtml);

    }, "json").fail(function (xhr) {

        $("#oldObligationsList").html(
            '<div class="alert alert-danger mb-2 py-2">Грешка при заявката: ' + xhr.status + '</div>'
        );
        $("#pppWithoutObligationList").html("");

    });
}

function distributePaymentPreview() {
    let payment = parseFloat($("#payment_sum").val()) || 0;
    let distributed = 0;

    $(".old-obligation-row").each(function () {
        const row = $(this);

        const paid = parseFloat(row.data("paid")) || 0;
        const diff = parseFloat(row.data("diff")) || 0;

        let add = 0;

        if (payment > 0 && diff > 0) {
            add = Math.min(payment, diff);
            payment -= add;
            distributed += add;
        }

        row.find(".pay-add").text(add.toFixed(2));
        row.find(".paid-current").text((paid + add).toFixed(2));
        row.find(".diff-after").text((diff - add).toFixed(2));
    });

    $("#payment_distributed_sum").val(distributed.toFixed(2));
}

$(document).on("input", "#payment_sum", function () {
    distributePaymentPreview();
});

$(document).on("click", "#payObligationsBtn", function () {

    const idObject = $("#obligation_object_id").val();
    let allocations = [];

    $(".old-obligation-row").each(function () {
        const row = $(this);
        const amount = parseFloat(row.find(".pay-add").text()) || 0;

        if (amount > 0) {
            allocations.push({
                id: row.data("id"),
                amount: amount
            });
        }
    });

    if (allocations.length === 0) {
        alert("Моля, въведете сума за погасяване.");
        return;
    }

    if (!confirm("Сигурни ли сте, че искате да запишете плащането?")) {
        return;
    }

    const btn = $("#payObligationsBtn");
    btn.prop("disabled", true).text("Запис...");

    $.post("includes/object_obligation_pay.php", {
        id_object: idObject,
        allocations: JSON.stringify(allocations)
    }, function (r) {

        if (r.success) {
            $("#payment_sum").val("");
            $("#payment_distributed_sum").val("0.00");
            loadObligationData(idObject);
            alert("Плащането е записано успешно.");
            return;
        }

        alert(r.error || "Грешка при запис.");
        btn.prop("disabled", false).html('<i class="fa-solid fa-money-bill-wave"></i> Плати');

    }, "json").fail(function () {
        alert("Грешка при заявката.");
        btn.prop("disabled", false).html('<i class="fa-solid fa-money-bill-wave"></i> Плати');
    });
});

document.addEventListener("DOMContentLoaded", function () {

    document.querySelectorAll(".openObligationModal").forEach(function (btn) {

        btn.addEventListener("click", function (e) {

            e.preventDefault();
            e.stopPropagation();

            const idObject = this.dataset.id || 0;

            document.getElementById("obligation_object_id").value = idObject;

            document.getElementById("obligation_object_name").value =
                this.dataset.name || "";

            document.getElementById("obligation_object_name_title").innerHTML =
                this.dataset.name || "";

            document.getElementById("obligation_total_sum").value = "";

            document.getElementById("obligationError")
                .classList.add("d-none");

            loadObligationData(idObject);

            bootstrap.Modal.getOrCreateInstance(
                document.getElementById("obligationModal")
            ).show();
        });
    });

    document.getElementById("saveObligationBtn")
        .addEventListener("click", function () {

            const errorBox = document.getElementById("obligationError");

            const idObject = document.getElementById("obligation_object_id").value;
            const fromDate = document.getElementById("obligation_from_date").value;
            const totalSum = document.getElementById("obligation_total_sum").value;

            errorBox.classList.add("d-none");
            errorBox.innerHTML = "";

            $.post("includes/object_obligation_save.php", {
                id_object: idObject,
                id_ppp: 0,
                from_date: fromDate,
                total_sum: totalSum
            }, function (r) {

                if (r.success) {
                    document.getElementById("obligation_total_sum").value = "";
                    loadObligationData(idObject);
                    alert("Задължението е добавено успешно.");
                    return;
                }

                errorBox.innerHTML = r.error || "Грешка при запис.";
                errorBox.classList.remove("d-none");

            }, "json").fail(function (xhr) {

                errorBox.innerHTML =
                    "Грешка при заявката: " + xhr.status;

                errorBox.classList.remove("d-none");
            });
        });

});

$(document).on("click", ".addPppObligationBtn", function () {

    if (!confirm("Сигурни ли сте, че искате да добавите задължение по този ППП?")) {
        return;
    }

    const btn = $(this);
    const row = btn.closest(".ppp-row");

    const idObject = $("#obligation_object_id").val();
    const idPpp = btn.data("id-ppp");
    const fromDate = btn.data("date");
    const totalSum = row.find(".ppp-sum").val();

    btn.prop("disabled", true).text("Добавяне...");

    $.post("includes/object_obligation_save.php", {
        id_object: idObject,
        id_ppp: idPpp,
        from_date: fromDate,
        total_sum: totalSum
    }, function (r) {

        if (r.success) {
            row.remove();
            loadObligationData(idObject);
            return;
        }

        alert(r.error || "Грешка при запис.");
        btn.prop("disabled", false).text("Добави");

    }, "json").fail(function () {
        alert("Грешка при заявката.");
        btn.prop("disabled", false).text("Добави");
    });
});
</script>