<?php
include_once __DIR__ . '/../includes/functions.php';

if (empty($_SESSION['user_id'])) {
    echo '<div class="alert alert-danger m-3">Нямате достъп.</div>';
    return;
}

$idUser   = (int) $_SESSION['user_id'];
$officeId = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($officeId <= 0) {
    echo '<div class="alert alert-danger m-3">Невалиден маршрут!</div>';
    return;
}
?>
<div class="card shadow mb-3 border-0">

    <div class="card-header d-flex justify-content-between align-items-center">
        <a href="dashboard.php?page=routes" class="btn btn-outline-secondary mb-1">
            <i class="fa-solid fa-angles-left"></i>
        </a>
        <?php if($_SESSION['is_admin'] == 1) { ?>
        <div class="btn-group">
            <button id="showRoute" class="btn btn-sm btn-success">
                МАРШРУТ
            </button>
        </div>
        <?php } ?>
    </div>


<?php
$db = db_connect('sod');

$stmt = $db->prepare("
    SELECT
        o.id AS oID,
        o.num AS oNum,
        o.name AS oName,
        COALESCE(o.address, '...') AS oAddress,
        COALESCE(REPLACE(o.operativ_info , '\"', ' '), '...') AS oInfo,
        o.geo_lat AS oLat,
        o.geo_lan AS oLan,
        COALESCE(p.id, 0) AS pppID,
        p.`status` AS order_status,
        p.id_buy_doc AS buy_doc,
        SUM(COALESCE(pe.`count`, 0)) AS ordered_quantity
    FROM objects o
    LEFT JOIN ". DB_NAMES['storage'] .".ppp p ON o.id = p.id_dest AND DATE(p.source_date) = CURDATE()
    LEFT JOIN ". DB_NAMES['storage'] .".ppp_elements pe ON p.id = pe.id_ppp
    WHERE JSON_CONTAINS(o.offices_ids, JSON_ARRAY(?))
      AND o.id_status <> 4
    GROUP BY o.id, p.id
    ORDER BY
        (p.id IS NULL),
        ordered_quantity DESC,
        p.id DESC
    LIMIT 100
");

$stmt->bind_param("i", $officeId);
$stmt->execute();
$result = $stmt->get_result();

if (!$result || $result->num_rows === 0) {
    echo '<div class="alert alert-warning text-center">Няма обекти.</div>';
    return;
}

$routePoints = [];

while ($row = $result->fetch_assoc()):

    $oID      = (int) $row['oID'];
    $pppID    = (int) $row['pppID'];
    $oStatus  = $row['order_status'] ?? 'open';
    $buyDoc = (int) ($row['buy_doc'] ?? 0);
    $oQty = (int)($row['ordered_quantity'] ?? 0);


    $oNum     = htmlspecialchars($row['oNum']);
    $oName    = htmlspecialchars($row['oName']);
    $oInfo    = htmlspecialchars($row['oInfo']);
    $oAddress = htmlspecialchars($row['oAddress']);
    $oLat     = $row['oLat'];
    $oLan     = $row['oLan'];

    $infoModalId = "infoModal{$oID}";
    $mapModalId  = "mapModal{$oID}";

    /* ===== STATUS COLOR ===== */

    $statusClass = 'bg-info';
    $disabled = '';

    if ($oStatus === 'wait') {
        $statusClass = 'bg-warning';
    }

    if ($oStatus === 'confirm') {
        $statusClass = 'bg-success';
        $disabled = 'disabled';
    }

    if ($oStatus === 'cancel') {
        $statusClass = 'bg-danger';
        $disabled = 'disabled';
    }

    if (!empty($oLat) && !empty($oLan)) {
        $routePoints[] = [
            'name' => $oName,
            'lat'  => (float)$oLat,
            'lng'  => (float)$oLan
        ];
    }
?>

<!-- ================= OBJECT CARD ================= -->
<div class="card mb-3 object-card shadow-sm border-0 <?= $oStatus === 'cancel' ? 'alert alert-danger' : '' ?>">
    <div class="card-body d-flex align-items-center justify-content-between p-2">

        <!-- MAP BUTTON -->
        <div>
            <button class="btn btn-primary rounded-circle d-flex align-items-center justify-content-center"
                    style="width:48px;height:48px;"
                    onclick="openMapModal('<?= $mapModalId ?>', '<?= $oLat ?>', '<?= $oLan ?>', <?= $idUser ?>)">
                <i class="fa-solid fa-car"></i>
            </button>
        </div>

        <!-- TEXT -->
        <div class="flex-grow-1 px-2">
            <a href="dashboard.php?page=delivery_request&id=<?= $oID ?>&office_id=<?= $officeId ?>"
               class="text-decoration-none text-body">
                <div class="fw-semibold fs-5"><?= $oName ?></div>
                <div class="text-body-secondary small"><?= $oAddress ?></div>
            </a>
        </div>

        <!-- ACTION BUTTONS -->
        <div class="d-flex gap-2">
            <?php if ($pppID > 0 && $oStatus !== 'cancel'): ?>

                <!-- INVOICE BUTTON -->
                <button class="btn rounded-circle d-flex align-items-center justify-content-center invoice-btn <?= $buyDoc ? 'btn-success' : 'btn-danger' ?>"
                        style="width:42px;height:42px;"
                        data-ppp="<?= $pppID ?>"
                        data-buydoc="<?= $buyDoc ?>">
                    <i class="fa-solid fa-file-invoice"></i>
                </button>
                <?php if($_SESSION['is_admin'] == 1) { ?>
                <!-- CANCEL BUTTON -->
                <button class="btn btn-danger rounded-circle d-flex align-items-center justify-content-center cancel-btn"
                        style="width:42px;height:42px;"
                        data-ppp="<?= $pppID ?>">
                    <i class="fa-solid fa-xmark"></i>
                </button>
                <?php } ?>
            <?php endif; ?>

            <?php if ($pppID > 0): ?>

            <button class="btn text-white rounded-circle d-flex align-items-center justify-content-center status-btn <?= $statusClass ?>"
                    style="width:42px;height:42px; position:relative;"
                    data-ppp="<?= $pppID ?>"
                    data-status="<?= $oStatus ?>"
                    <?= $disabled ?>>

                <i class="fa-solid fa-clock"></i>

                <span class="badge rounded-pill position-absolute top-0 start-100 translate-middle
                    <?= $oQty > 0 ? 'bg-success' : 'bg-danger' ?>">
                    <?= $oQty ?>
                </span>

            </button>

            <?php endif; ?>

            <!-- INFO BUTTON
            <button class="btn btn-outline-secondary rounded-circle d-flex align-items-center justify-content-center"
                    style="width:42px;height:42px;"
                    data-bs-toggle="modal"
                    data-bs-target="#<?= $infoModalId ?>">
                <i class="fa-solid fa-circle-user"></i>
            </button>
 -->
        </div>

    </div>
</div>

<!-- ================= INFO MODAL ================= -->
<div class="modal fade" id="<?= $infoModalId ?>" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">

            <div class="modal-header">
                <h5 class="modal-title"><?= $oNum ?> - <?= $oName ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">
                <?= nl2br($oInfo) ?>
            </div>

        </div>
    </div>
</div>

<!-- ================= MAP MODAL ================= -->
<div class="modal fade" id="<?= $mapModalId ?>" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">

            <div class="modal-body p-0">
                <div id="mapContainer_<?= $oID ?>" style="height:400px;"></div>
            </div>

            <div class="p-3 text-center">
                <button class="btn btn-success"
                    ГЕО КООРДИНАТИ
                </button>
            </div>

        </div>
    </div>
</div>

<?php
endwhile;

$stmt->close();
$db->close();
?>

<?php $routeJson = json_encode($routePoints); ?>

<div class="modal fade" id="routeMapModal" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-centered">

        <div class="modal-content">
            <div class="p-2 text-center fw-bold" id="routeInfo"></div>
            <div class="modal-body p-0">
                <div id="routeMap" style="height:600px;"></div>
            </div>

        </div>
    </div>
</div>

<!-- ================= STATUS TOGGLE SCRIPT ================= -->
<script>
$(document).on('click', '.status-btn', function(){

    const btn = $(this);

    if(btn.prop('disabled')){
        return;
    }

    const pppID = btn.data('ppp');
    let currentStatus = btn.data('status');

    if(currentStatus === 'confirm'){
        return;
    }

    let newStatus = (currentStatus === 'open') ? 'wait' : 'open';

    $.post('includes/update_ppp_status.php', {
        pppID: pppID,
        status: newStatus
    }, function(resp){

        if(resp.success){

            btn.data('status', newStatus);

            btn.removeClass('bg-info bg-warning bg-success');

            if(newStatus === 'wait'){
                btn.addClass('bg-warning');
            } else {
                btn.addClass('bg-info');
            }

        } else {
            alert('Грешка при обновяване!');
        }

    }, 'json');

});

$(document).on('click', '.cancel-btn', function(){

    const btn = $(this);
    const pppID = btn.data('ppp');

    if(!confirm('Сигурни ли сте, че искате да анулирате заявката?')){
        return;
    }

    btn.prop('disabled', true);

    $.post('includes/update_ppp_status.php', {
        pppID: pppID,
        status: 'cancel'
    }, function(resp){

        if(resp.success){

            const card = btn.closest('.object-card');

            // визуално задраскване
            card.addClass('cancelled');

            // махаме бутоните
            card.find('.status-btn, .cancel-btn').remove();

        } else {
            alert('Грешка при анулиране!');
            btn.prop('disabled', false);
        }

    }, 'json');

});

$(document).on('click', '.invoice-btn', function(){

    const btn = $(this);
    const pppID = btn.data('ppp');
    let current = parseInt(btn.data('buydoc')) || 0;

    let newValue = current === 1 ? 0 : 1;

    btn.prop('disabled', true);

    $.post('includes/update_ppp_buydoc.php', {
        pppID: pppID,
        value: newValue
    }, function(resp){

        if(resp.success){

            btn.data('buydoc', newValue);

            btn.removeClass('btn-danger btn-success');

            if(newValue === 1){
                btn.addClass('btn-success');
            } else {
                btn.addClass('btn-danger');
            }

        } else {
            alert('Грешка при обновяване!');
        }

        btn.prop('disabled', false);

    }, 'json');

});


const routePoints = <?= $routeJson ?>;
const officeId = <?= $officeId ?>;

let routeMap = null;
let routingControl = null;

const depot = {
    name: 'Склад',
    lat: 43.2682128,
    lng: 26.9475601
};

$('#showRoute').on('click', function () {

    $('#routeMapModal').modal('show');

    setTimeout(() => {

        // cleanup old map
        if (routeMap) {
            routeMap.remove();
            routeMap = null;
        }

        routeMap = L.map('routeMap').setView([depot.lat, depot.lng], 12);

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap'
        }).addTo(routeMap);

        const maxPoints = 25;

        // valid points filter
        const validPoints = (routePoints || []).filter(p =>
            p &&
            typeof p.lat === 'number' &&
            typeof p.lng === 'number' &&
            !isNaN(p.lat) &&
            !isNaN(p.lng)
        );

        const limitedPoints = validPoints.slice(0, maxPoints);

        if (!limitedPoints.length) {
            document.getElementById('routeInfo').innerHTML = 'Няма точки за маршрут';
            return;
        }

        // TSP + 2-opt
        let optimizedStops = optimizeRoute(limitedPoints, depot);
        optimizedStops = twoOpt(optimizedStops);

        // visual offset (anti overlap)
        const grouped = groupByCoordinates(optimizedStops);
        const visualStops = grouped.flatMap(applyOffset);

        const fullRoute = [depot, ...optimizedStops, depot];
        const visualRoute = [depot, ...visualStops, depot];

        const waypoints = fullRoute.map(p => L.latLng(p.lat, p.lng));

        // initial UI
        document.getElementById('routeInfo').innerHTML =
            `Изчисляване... (${optimizedStops.length} спирки)`;

        // remove previous routing
        if (routingControl) {
            routeMap.removeControl(routingControl);
            routingControl = null;
        }

        routingControl = L.Routing.control({
            waypoints: waypoints,
            routeWhileDragging: false,
            draggableWaypoints: false,
            addWaypoints: false,
            show: false,

            createMarker: function (i, wp) {

                const isDepot = (i === 0 || i === visualRoute.length - 1);
                const label = isDepot ? 'S' : i;

                const point = visualRoute[i] || fullRoute[i];

                return L.marker([point.lat, point.lng], {
                    icon: L.divIcon({
                        className: '',
                        html: `
                            <div style="
                                background:${isDepot ? '#198754' : '#0d6efd'};
                                color:#fff;
                                border-radius:50%;
                                width:30px;
                                height:30px;
                                display:flex;
                                align-items:center;
                                justify-content:center;
                                font-weight:bold;
                                font-size:13px;
                            ">${label}</div>
                        `
                    })
                }).bindPopup(point?.name || 'Точка');
            }

        })

        .on('routesfound', function (e) {

            const route = e.routes?.[0];
            if (!route) return;

            const km = (route.summary.totalDistance / 1000).toFixed(2);
            const time = Math.round(route.summary.totalTime / 60);

            // update UI
            document.getElementById('routeInfo').innerHTML =
                `<b>${km} км</b> | ⏱ ${time} мин | 📍 ${optimizedStops.length} спирки`;

            // backend save (silent fail safe)
            try {
                $.post('includes/update_route_km.php', {
                    officeId: officeId,
                    km: km
                });
            } catch (err) {
                console.warn('KM save failed:', err);
            }
        })

        .on('routingerror', function (err) {

            console.error('Routing error:', err);

            document.getElementById('routeInfo').innerHTML =
                'Грешка при изчисляване на маршрута';
        })

        .addTo(routeMap);

    }, 300);
});


function distance(a, b) {
    const R = 6371; // km
    const dLat = (b.lat - a.lat) * Math.PI / 180;
    const dLng = (b.lng - a.lng) * Math.PI / 180;

    const lat1 = a.lat * Math.PI / 180;
    const lat2 = b.lat * Math.PI / 180;

    const x = Math.sin(dLat/2)**2 +
              Math.sin(dLng/2)**2 * Math.cos(lat1) * Math.cos(lat2);

    return 2 * R * Math.atan2(Math.sqrt(x), Math.sqrt(1-x));
}

function optimizeRoute(points, startPoint) {

    let remaining = [...points];
    let route = [];

    let current = startPoint;

    while(remaining.length > 0){

        let nearestIndex = 0;
        let minDist = Infinity;

        remaining.forEach((p, i) => {
            const d = distance(current, p);
            if(d < minDist){
                minDist = d;
                nearestIndex = i;
            }
        });

        current = remaining.splice(nearestIndex, 1)[0];
        route.push(current);
    }

    return route;
}


function calculateTotalDistance(route){

    let total = 0;

    for(let i = 0; i < route.length - 1; i++){
        total += distance(route[i], route[i+1]);
    }

    return total;
}

function twoOpt(route) {

    let improved = true;

    while(improved){
        improved = false;

        for(let i = 1; i < route.length - 2; i++){
            for(let j = i + 1; j < route.length - 1; j++){

                const A = route[i - 1];
                const B = route[i];
                const C = route[j];
                const D = route[j + 1];

                const currentDist =
                    distance(A, B) + distance(C, D);

                const newDist =
                    distance(A, C) + distance(B, D);

                if(newDist < currentDist){

                    // обръщаме сегмента
                    const reversed = route.slice(i, j + 1).reverse();

                    route.splice(i, j - i + 1, ...reversed);

                    improved = true;
                }
            }
        }
    }

    return route;
}

function groupByCoordinates(points) {

    const map = {};

    points.forEach(p => {
        const key = `${p.lat.toFixed(6)}_${p.lng.toFixed(6)}`;

        if(!map[key]) {
            map[key] = [];
        }

        map[key].push(p);
    });

    return Object.values(map);
}

function applyOffset(group) {

    if(group.length === 1) return group;

    const radius = 0.0001 + (group.length * 0.00002);

    return group.map((p, i) => {

        const angle = (2 * Math.PI / group.length) * i;

        return {
            ...p,
            lat: p.lat + radius * Math.cos(angle),
            lng: p.lng + radius * Math.sin(angle)
        };
    });
}
</script>