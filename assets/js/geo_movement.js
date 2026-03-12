// ============================================================
// ENVIRONMENT DETECTION
// ============================================================

let isAndroidWebView = false;

(function detectEnvironment(){
    const ua = navigator.userAgent || "";

    if (/Android/i.test(ua) && (/(wv|Version\/)/i.test(ua) || typeof Android !== 'undefined')){
        isAndroidWebView = true;
    }
})();


// ============================================================
// GLOBAL GPS CACHE
// ============================================================

window.__lastGps = null;


// ============================================================
// UTILS
// ============================================================

function haversine(a,b){

    const toRad = v => v * Math.PI / 180;
    const R = 6371000;

    const dLat = toRad(b.lat-a.lat);
    const dLng = toRad(b.lng-a.lng);

    const x =
        Math.sin(dLat/2)*Math.sin(dLat/2) +
        Math.cos(toRad(a.lat)) *
        Math.cos(toRad(b.lat)) *
        Math.sin(dLng/2)*Math.sin(dLng/2);

    return R * 2 * Math.atan2(Math.sqrt(x),Math.sqrt(1-x));
}


// ============================================================
// HTML MARKER
// ============================================================

class HtmlMarker{

    constructor(position,html,map){

        this.map = map;

        this.marker = L.marker(
            [position.lat,position.lng],
            {
                icon: L.divIcon({
                    html: html,
                    className: "html-marker",
                    iconSize:null
                })
            }
        ).addTo(map);

    }

    setPosition(pos){
        this.marker.setLatLng([pos.lat,pos.lng]);
    }

    getLatLng(){
        return this.marker.getLatLng();
    }

    remove(){
        if(this.marker){
            this.map.removeLayer(this.marker);
        }
    }

}


// ============================================================
// MAP INIT
// ============================================================

function initMapUnique(containerId,oLat,oLng,idUser){

    const el = document.getElementById(containerId);
    if(!el) return;

    if(el._map) {
        setTimeout(()=>el._map.invalidateSize(),200);
        return;
    }

    const objectPos = {
        lat:parseFloat(oLat),
        lng:parseFloat(oLng)
    };

    const map = L.map(el,{
        zoomControl:true
    }).setView([objectPos.lat,objectPos.lng],14);

    L.tileLayer(
        'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
        {
            maxZoom:19,
            keepBuffer:8,
            updateWhenIdle:true
        }
    ).addTo(map);

    el._map = map;
    el._objectPos = objectPos;
    el._lastRouteOrigin = null;
    el._lastRouteTs = 0;

    el._routeMinDistance = 80;
    el._routeMinInterval = 60000;


    // OBJECT MARKER

    el._objectMarker = new HtmlMarker(
        objectPos,
        `<i class="fa-solid fa-house-signal" style="font-size:32px;color:#dc3545"></i>`,
        map
    );


    // ROUTING CONTROL

    el._routeControl = L.Routing.control({
        waypoints:[],
        router:L.Routing.osrmv1({
            serviceUrl:'https://router.project-osrm.org/route/v1'
        }),
        show:false,
        addWaypoints:false,
        draggableWaypoints:false,
        fitSelectedRoute:false,
        routeWhileDragging:false,
        createMarker:()=>null
    }).addTo(map);



    // ============================================================
    // CAR UPDATE
    // ============================================================

    el._updateCarPosition = function(lat,lng,opts={}){

        const pos = {
            lat:parseFloat(lat),
            lng:parseFloat(lng)
        };

        if(!el._carMarker){

            el._carMarker = new HtmlMarker(
                pos,
                `<i class="fa-solid fa-car-on" style="font-size:30px;color:#0d6efd"></i>`,
                map
            );

            map.setView([pos.lat,pos.lng],16);

            recalcRoute(pos);

            return;
        }

        animateMarker(el._carMarker,pos,opts.speed);

        map.panTo([pos.lat,pos.lng],{
            animate:true,
            duration:1
        });

        recalcRoute(pos);

    };


    // ============================================================
    // ROUTE RECALC
    // ============================================================

    function recalcRoute(origin){

        const now = Date.now();

        if(
            !el._lastRouteOrigin ||
            haversine(origin,el._lastRouteOrigin) > el._routeMinDistance &&
            (now-el._lastRouteTs) > el._routeMinInterval
        ){

            el._routeControl.setWaypoints([
                L.latLng(origin.lat,origin.lng),
                L.latLng(objectPos.lat,objectPos.lng)
            ]);

            el._lastRouteOrigin = origin;
            el._lastRouteTs = now;

        }

    }



    // ============================================================
    // GPS FALLBACK (WEB)
    // ============================================================

    if(!isAndroidWebView){

        setInterval(function(){

            $.get(
                "includes/get_geo_position.php",
                {idUser:idUser},
                function(resp){

                    if(!resp) return;

                    const p = resp.split(",");

                    const lat = parseFloat(p[0]);
                    const lng = parseFloat(p[1]);

                    if(!isNaN(lat) && !isNaN(lng)){
                        el._updateCarPosition(lat,lng);
                    }

                }
            );

        },20000);

    }


    // INITIAL GPS

    if(window.__lastGps){
        el._updateCarPosition(
            window.__lastGps.lat,
            window.__lastGps.lng,
            {speed:window.__lastGps.speed}
        );
    }


    // FIX FOR BOOTSTRAP MODAL SIZE

    setTimeout(function(){
        map.invalidateSize();
    },300);

}



// ============================================================
// MARKER ANIMATION
// ============================================================

function animateMarker(marker,toPos,speed){

    const from = marker.getLatLng();

    const dist = haversine(
        {lat:from.lat,lng:from.lng},
        toPos
    );

    let duration = 1000;

    if(speed){
        duration = Math.min(5000,(dist/speed)*1000);
    }

    const start = performance.now();

    function frame(now){

        const t = Math.min(1,(now-start)/duration);

        const lat = from.lat + (toPos.lat-from.lat)*t;
        const lng = from.lng + (toPos.lng-from.lng)*t;

        marker.setPosition({lat,lng});

        if(t<1){
            requestAnimationFrame(frame);
        }

    }

    requestAnimationFrame(frame);

}



// ============================================================
// MODAL MAP OPEN
// ============================================================

window.openMapModal = function(modalId,oLat,oLng,idUser){

    const modalEl = document.getElementById(modalId);

    const suffix = modalId.replace(/^mapModal/i,'');

    const containerId = "mapContainer_"+suffix;

    const modal = new bootstrap.Modal(modalEl);

    modalEl.addEventListener("shown.bs.modal",function(){

        initMapUnique(containerId,oLat,oLng,idUser);

        const el = document.getElementById(containerId);

        if(el && el._map){
            setTimeout(()=>{
                el._map.invalidateSize();
            },200);
        }

    });

    modal.show();

};



// ============================================================
// SCREEN ROTATION FIX
// ============================================================

window.addEventListener("orientationchange",function(){

    document.querySelectorAll('[id^="mapContainer_"]').forEach(function(el){

        if(el._map){
            setTimeout(function(){
                el._map.invalidateSize();
            },400);
        }

    });

});



// ============================================================
// WEBVIEW GPS FUNCTION (НЕ ПИПАМЕ ИМЕТО)
// ============================================================

window.updateCarFromWebView = function(lat,lng,speed,bearing,accuracy,altitude){

    const gps = {
        lat,
        lng,
        speed,
        bearing,
        accuracy,
        altitude,
        ts:Date.now()
    };

    window.__lastGps = gps;

    document.querySelectorAll('[id^="mapContainer_"]').forEach(function(el){

        if(typeof el._updateCarPosition === "function"){

            el._updateCarPosition(
                gps.lat,
                gps.lng,
                {speed:gps.speed}
            );

        }

    });

};

/*
// ============================================================
// ENVIRONMENT DETECTION
// ============================================================

let isAndroidWebView = false;

(function detectEnvironment(){
    const ua = navigator.userAgent || "";

    if (/Android/i.test(ua) && (/(wv|Version\/)/i.test(ua) || typeof Android !== 'undefined')){
        isAndroidWebView = true;
    }

})();


// ============================================================
// GLOBAL GPS CACHE
// ============================================================

window.__lastGps = null;


// ============================================================
// UTILS
// ============================================================

function haversine(a,b){

    const toRad = v => v * Math.PI / 180;

    const R = 6371000;

    const dLat = toRad(b.lat-a.lat);
    const dLng = toRad(b.lng-a.lng);

    const x =
        Math.sin(dLat/2)*Math.sin(dLat/2) +
        Math.cos(toRad(a.lat)) *
        Math.cos(toRad(b.lat)) *
        Math.sin(dLng/2)*Math.sin(dLng/2);

    return R * 2 * Math.atan2(Math.sqrt(x),Math.sqrt(1-x));

}


// ============================================================
// HTML MARKER
// ============================================================

class HtmlMarker{

    constructor(position,html,map){

        this.map = map;

        this.marker = L.marker(
            [position.lat,position.lng],
            {
                icon: L.divIcon({
                    html: html,
                    className: "html-marker",
                    iconSize:null
                })
            }
        ).addTo(map);

    }

    setPosition(pos){

        this.marker.setLatLng([pos.lat,pos.lng]);

    }

    getLatLng(){

        return this.marker.getLatLng();

    }

    remove(){

        if(this.marker){
            this.map.removeLayer(this.marker);
        }

    }

}


// ============================================================
// MAP INIT
// ============================================================

function initMapUnique(containerId,oLat,oLng,idUser){

    const el = document.getElementById(containerId);

    if(!el) return;

    if(el._map) return;


    const objectPos = {
        lat:parseFloat(oLat),
        lng:parseFloat(oLng)
    };


    const map = L.map(el).setView([objectPos.lat,objectPos.lng],14);

    L.tileLayer(
        'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
        {
            maxZoom:19,
            keepBuffer:8,
            updateWhenIdle:true
        }
    ).addTo(map);


    el._map = map;
    el._objectPos = objectPos;
    el._lastRouteOrigin = null;
    el._lastRouteTs = 0;

    el._routeMinDistance = 80;
    el._routeMinInterval = 60000;


    // OBJECT MARKER

    el._objectMarker = new HtmlMarker(
        objectPos,
        `<i class="fa-solid fa-house-signal" style="font-size:32px;color:#dc3545"></i>`,
        map
    );


    // ROUTING CONTROL

    el._routeControl = L.Routing.control({
        waypoints:[],
        router:L.Routing.osrmv1({
            serviceUrl:'https://router.project-osrm.org/route/v1'
        }),
        show:false,
        addWaypoints:false,
        draggableWaypoints:false,
        fitSelectedRoute:false,
        routeWhileDragging:false,
        createMarker:()=>null
    }).addTo(map);



    // ============================================================
    // CAR UPDATE
    // ============================================================

    el._updateCarPosition = function(lat,lng,opts={}){

        const pos = {
            lat:parseFloat(lat),
            lng:parseFloat(lng)
        };


        if(!el._carMarker){

            el._carMarker = new HtmlMarker(
                pos,
                `<i class="fa-solid fa-car-on" style="font-size:30px;color:#0d6efd"></i>`,
                map
            );

            recalcRoute(pos);

            return;

        }


        animateMarker(el._carMarker,pos,opts.speed);

        recalcRoute(pos);

    };



    // ============================================================
    // ROUTE RECALC
    // ============================================================

    function recalcRoute(origin){

        const now = Date.now();

        if(
            !el._lastRouteOrigin ||
            haversine(origin,el._lastRouteOrigin) > el._routeMinDistance &&
            (now-el._lastRouteTs) > el._routeMinInterval
        ){

            el._routeControl.setWaypoints([
                L.latLng(origin.lat,origin.lng),
                L.latLng(objectPos.lat,objectPos.lng)
            ]);

            el._lastRouteOrigin = origin;
            el._lastRouteTs = now;

        }

    }



    // ============================================================
    // GPS FALLBACK
    // ============================================================

    if(!isAndroidWebView){

        setInterval(function(){

            $.get(
                "includes/get_geo_position.php",
                {idUser:idUser},
                function(resp){

                    if(!resp) return;

                    const p = resp.split(",");

                    const lat = parseFloat(p[0]);
                    const lng = parseFloat(p[1]);

                    if(!isNaN(lat) && !isNaN(lng)){
                        el._updateCarPosition(lat,lng);
                    }

                }
            );

        },20000);

    }


    // INITIAL GPS

    if(window.__lastGps){
        el._updateCarPosition(
            window.__lastGps.lat,
            window.__lastGps.lng,
            {speed:window.__lastGps.speed}
        );
    }

}



// ============================================================
// MARKER ANIMATION
// ============================================================

function animateMarker(marker,toPos,speed){

    const from = marker.getLatLng();

    const dist = haversine(
        {lat:from.lat,lng:from.lng},
        toPos
    );

    let duration = 1000;

    if(speed){
        duration = Math.min(5000,(dist/speed)*1000);
    }

    const start = performance.now();

    function frame(now){

        const t = Math.min(1,(now-start)/duration);

        const lat = from.lat + (toPos.lat-from.lat)*t;
        const lng = from.lng + (toPos.lng-from.lng)*t;

        marker.setPosition({lat,lng});

        if(t<1){
            requestAnimationFrame(frame);
        }

    }

    requestAnimationFrame(frame);

}



// ============================================================
// MODAL MAP OPEN
// ============================================================

window.openMapModal = function(modalId,oLat,oLng,idUser){

    const modalEl = document.getElementById(modalId);

    const suffix = modalId.replace(/^mapModal/i,'');

    const containerId = "mapContainer_"+suffix;

    const modal = new bootstrap.Modal(modalEl);

    modalEl.addEventListener("shown.bs.modal",function handler(){

        initMapUnique(containerId,oLat,oLng,idUser);

        modalEl.removeEventListener("shown.bs.modal",handler);

    });

    modal.show();

};



// ============================================================
// WEBVIEW GPS FUNCTION (НЕ ПИПАМЕ ИМЕТО)
// ============================================================

window.updateCarFromWebView = function(lat,lng,speed,bearing,accuracy,altitude){

    const gps = {
        lat,
        lng,
        speed,
        bearing,
        accuracy,
        altitude,
        ts:Date.now()
    };

    window.__lastGps = gps;


    document.querySelectorAll('[id^="mapContainer_"]').forEach(function(el){

        if(typeof el._updateCarPosition === "function"){

            el._updateCarPosition(
                gps.lat,
                gps.lng,
                {speed:gps.speed}
            );

        }

    });

};
*/
//// --- Глобални променливи ---
//let isAndroidWebView = false;
//let isDesktopBrowser = false;
//
//// ============================================================================
////                  PLATFORM DETECTION
//// ============================================================================
//function detectEnvironment() {
//    const ua = navigator.userAgent || navigator.vendor || window.opera;
//
//    // WebView detection + presence of Android JS interface
//    if (/Android/i.test(ua) && (/(wv|Version\/)/i.test(ua) || typeof Android !== 'undefined')) {
//        isAndroidWebView = true;
//      //  console.log('📱 Android WebView detected');
//    } else {
//        isDesktopBrowser = true;
//      //  console.log('💻 Desktop / Mobile Browser detected');
//    }
//}
//detectEnvironment();
//
//
///* =========================
//   HtmlMarker (Leaflet divIcon wrapper) + плавен визуален клас
//   ========================= */
//class HtmlMarker {
//    constructor(position, html, map) {
//        this.map = map;
//        this.position = { lat: parseFloat(position.lat), lng: parseFloat(position.lng) };
//        this.html = html || '';
//        this._icon = L.divIcon({
//            html: this.html,
//            className: 'html-marker',
//            iconSize: null
//        });
//        this._marker = L.marker([this.position.lat, this.position.lng], {
//            icon: this._icon,
//            interactive: true,
//            keyboard: false
//        }).addTo(this.map);
//
//        // за плавна визуална анимация (css transition)
//        const el = this._marker.getElement();
//        if (el) el.classList.add('car-marker-smooth');
//    }
//
//    setPosition(position) {
//        this.position = { lat: parseFloat(position.lat), lng: parseFloat(position.lng) };
//        if (this._marker) {
//            this._marker.setLatLng([this.position.lat, this.position.lng]);
//        }
//    }
//
//    getLatLng() {
//        return this._marker ? this._marker.getLatLng() : L.latLng(this.position.lat, this.position.lng);
//    }
//
//    onRemove() {
//        try { if (this._marker && this.map) this.map.removeLayer(this._marker); } catch (e) {}
//        this._marker = null;
//    }
//}
//
///* ------------------------
//   Utility: Haversine distance (meters)
//   ------------------------ */
//function haversineDistanceMeters(a, b) {
//    const toRad = v => v * Math.PI / 180;
//    const lat1 = (typeof a.lat === 'function') ? a.lat() : a.lat;
//    const lon1 = (typeof a.lng === 'function') ? a.lng() : a.lng;
//    const lat2 = (typeof b.lat === 'function') ? b.lat() : b.lat;
//    const lon2 = (typeof b.lng === 'function') ? b.lng() : b.lng;
//    const R = 6371000;
//    const dLat = toRad(lat2 - lat1);
//    const dLon = toRad(lon2 - lon1);
//    const L = Math.sin(dLat/2) * Math.sin(dLat/2) +
//              Math.cos(toRad(lat1)) * Math.cos(toRad(lat2)) *
//              Math.sin(dLon/2) * Math.sin(dLon/2);
//    const c = 2 * Math.atan2(Math.sqrt(L), Math.sqrt(1-L));
//    return R * c;
//}
//
///* ------------------------
//   cleanupMapContainer(containerId)
//   ------------------------ */
//function cleanupMapContainer(containerId) {
//    const el = document.getElementById(containerId);
//    if (!el) return;
//
//    if (el._fallbackInterval) {
//        clearInterval(el._fallbackInterval);
//        el._fallbackInterval = null;
//    }
//
//    if (el._routeControl) {
//        try {
//            el._routeControl.getPlan && el._routeControl.getPlan().setWaypoints([]);
//            el._localMap.removeControl(el._routeControl);
//        } catch (e) {}
//        el._routeControl = null;
//    }
//
//    if (el._carMarker) {
//        try { el._carMarker.onRemove(); } catch (e) {}
//        el._carMarker = null;
//    }
//
//    if (el._objectMarker) {
//        try { el._objectMarker.onRemove(); } catch (e) {}
//        el._objectMarker = null;
//    }
//
//    if (el._localMap) {
//        try { el._localMap.remove(); } catch (e) {}
//        el._localMap = null;
//    }
//
//    el._lastRouteOrigin = null;
//    el._lastRouteTs = 0;
//    el.classList.remove('ip-map-instance');
//
//    removeDistanceLabel(el);
//}
//
///* ------------------------
//   initMapUnique(containerId, oLat, oLan, idUser)
//   Leaflet + OSM + L.Routing optimised
//   ------------------------ */
//function initMapUnique(containerId, oLat, oLan, idUser) {
//    const el = document.getElementById(containerId);
//    if (!el) return;
//
//    // reuse existing map if available
//    if (el._localMap) {
//        if (el._objectMarker) el._objectMarker.setPosition({ lat: parseFloat(oLat), lng: parseFloat(oLan) });
//        return {
//            containerId: containerId,
//            map: el._localMap,
//            objectMarker: el._objectMarker,
//            carMarker: el._carMarker
//        };
//    }
//
//    el.innerHTML = '';
//
//    const objectPos = { lat: parseFloat(oLat), lng: parseFloat(oLan) };
//    if (Number.isNaN(objectPos.lat) || Number.isNaN(objectPos.lng)) {
//        el.innerHTML = '<div class="p-3 text-center text-danger">Невалидни координати.</div>';
//        console.error('Invalid object coordinates', oLat, oLan);
//        return;
//    }
//
//    // initialize map
//    const map = L.map(el, { preferCanvas: true, zoomControl: true, attributionControl: true }).setView([objectPos.lat, objectPos.lng], 14);
//
//    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
//        maxZoom: 19,
//        updateWhenIdle: true,
//        updateWhenZooming: false,
//        reuseTiles: true
//    }).addTo(map);
//
//    el._localMap = map;
//    el._objectPos = objectPos;
//    el._carMarker = null;
//    el._lastCarLatLng = null;
//    el._routingProvider = 'osrm';
//    el._routeRecalcMinDistance = 30;   // meters
//    el._routeRecalcMinInterval = 30000; // ms
//    el._lastRouteOrigin = null;
//    el._lastRouteTs = 0;
//
//    setTimeout(() => { try { map.invalidateSize(true); } catch (e) {} }, 350);
//
//    // object marker
//    el._objectMarker = new HtmlMarker(
//        objectPos,
//        `<i class="fa-solid fa-house-signal" style="font-size:32px; color:#dc3545; text-shadow:0 1px 3px rgba(0,0,0,0.5)"></i>`,
//        map
//    );
//
//    // Routing control (empty at start, no placeholder)
//    el._routeControl = L.Routing.control({
//        waypoints: [],
//        router: L.Routing.osrmv1({ serviceUrl: 'https://router.project-osrm.org/route/v1' }),
//        show: false,
//        addWaypoints: false,
//        draggableWaypoints: false,
//        fitSelectedRoute: false,
//        routeWhileDragging: false,
//        createMarker: () => null,
//        lineOptions: { styles: [{ color: '#00bcd4', opacity: 0.9, weight: 5 }] }
//    }).addTo(map);
//
//    // Routing error fallback
//    el._routeControl.on('routingerror', async () => {
//        if (el._routingProvider !== 'osrm') return;
//        if (!el._lastCarLatLng) return;
//
//        const from = { ...el._lastCarLatLng };
//        const to = objectPos;
//
//        console.warn('OSRM failed → trying ORS');
//
//        try {
//            const res = await fetchRouteORS(from, to);
//            el._routingProvider = 'ors';
//            drawORSRoute(el, res.coords, res.meters);
//            return;
//        } catch (e) {
//            console.warn('ORS failed → fallback');
//        }
//
//        el._routingProvider = 'fallback';
//        drawFallbackLine(el, L.latLng(from.lat, from.lng), L.latLng(to.lat, to.lng));
//    });
//
//    // remove fallback line on successful route
//    el._routeControl.on('routesfound', (e) => {
//        el._routingProvider = 'osrm';
//        if (!e.routes || !e.routes[0]) return;
//
//        const route = e.routes[0];
//        const meters = route.summary.totalDistance;
//        const midIndex = Math.floor(route.coordinates.length / 2);
//        const midPoint = route.coordinates[midIndex];
//
//        showDistanceLabel(el, L.latLng(midPoint.lat, midPoint.lng), meters);
//
//        if (el._fallbackLine) {
//            el._localMap.removeLayer(el._fallbackLine);
//            el._fallbackLine = null;
//        }
//    });
//
//    // Fit map to show object + car
//    function fitToShowBoth() {
//        try {
//            const bounds = L.latLngBounds([[objectPos.lat, objectPos.lng]]);
//            if (el._carMarker) bounds.extend(el._carMarker.getLatLng());
//            const currentBounds = map.getBounds ? map.getBounds() : null;
//            if (!currentBounds || !currentBounds.contains(bounds)) {
//                map.flyToBounds(bounds.pad(0.2), { animate: true, duration: 0.6 });
//            }
//        } catch (e) {}
//    }
//
//    // Route recalculation guard
//    el._recalcRouteFrom = function(lat, lng) {
//        if (el._routingProvider !== 'osrm') {
//            clearAllRoutes(el);
//            el._routingProvider = 'osrm';
//        }
//
//        const origin = { lat: parseFloat(lat), lng: parseFloat(lng) };
//        const now = Date.now();
//
//        if (!el._lastRouteOrigin || haversineDistanceMeters(origin, el._lastRouteOrigin) > el._routeRecalcMinDistance && (now - el._lastRouteTs) > el._routeRecalcMinInterval) {
//            try {
//                el._routeControl.setWaypoints([
//                    L.latLng(origin.lat, origin.lng),
//                    L.latLng(objectPos.lat, objectPos.lng)
//                ]);
//                el._lastRouteOrigin = origin;
//                el._lastRouteTs = now;
//            } catch (e) { console.warn('Route setWaypoints error', e); }
//        }
//    };
//
//    // Animation helper
//    el._anim = { req: null };
//    function animateMarkerTo(marker, toPos, duration) {
//        if (!marker) return;
//        if (el._anim.req) cancelAnimationFrame(el._anim.req);
//
//        const fromLatLng = marker.getLatLng();
//        const from = { lat: fromLatLng.lat, lng: fromLatLng.lng };
//        const to = { lat: parseFloat(toPos.lat), lng: parseFloat(toPos.lng) };
//        const start = performance.now();
//        const dur = Math.max(200, duration);
//
//        function step(now) {
//            const t = Math.min(1, (now - start) / dur);
//            const tt = t * t * (3 - 2 * t);
//            marker.setPosition({ lat: from.lat + (to.lat - from.lat) * tt, lng: from.lng + (to.lng - from.lng) * tt });
//            if (t < 1) el._anim.req = requestAnimationFrame(step);
//            else el._anim.req = null;
//        }
//        el._anim.req = requestAnimationFrame(step);
//    }
//
//    // Update car position
//    el._updateCarPosition = function(lat, lng, opts = {}) {
//        if (typeof lat === 'undefined' || typeof lng === 'undefined') return;
//        const pos = { lat: parseFloat(lat), lng: parseFloat(lng) };
//
//        if (!el._carMarker) {
//            const carHtml = `<div style="pointer-events:auto;"><i class="fa-solid fa-car-on" style="font-size:30px; color:#0d6efd; text-shadow:0 1px 3px rgba(0,0,0,0.5)"></i></div>`;
//            el._carMarker = new HtmlMarker(pos, carHtml, map);
//            el._lastCarLatLng = pos;
//            try { el._recalcRouteFrom(pos.lat, pos.lng); } catch (e) {}
//            fitToShowBoth();
//            return;
//        }
//
//        const last = el._lastCarLatLng || el._carMarker.getLatLng();
//        const dist = haversineDistanceMeters(last, pos);
//        let duration = 700;
//        if (opts.speed && opts.speed > 0) duration = Math.min(5000, Math.max(200, (dist / opts.speed) * 1000));
//        else duration = Math.min(3000, Math.max(200, dist * 10));
//
//        try { animateMarkerTo(el._carMarker, pos, duration); } catch (e) { el._carMarker.setPosition(pos); }
//
//        el._lastCarLatLng = pos;
//        try { el._recalcRouteFrom(pos.lat, pos.lng); } catch (e) {}
//
//        try {
//            const bounds = L.latLngBounds([[objectPos.lat, objectPos.lng], [pos.lat, pos.lng]]);
//            if (!map.getBounds().contains(bounds)) map.flyToBounds(bounds.pad(0.2), { animate: true, duration: 0.6 });
//        } catch (e) {}
//    };
//
//    // Fallback polling
//    if (el._fallbackInterval) clearInterval(el._fallbackInterval);
//    el._fallbackInterval = setInterval(() => {
//        $.ajax({
//            url: 'includes/get_geo_position.php',
//            method: 'GET',
//            data: { idUser },
//            success: function(resp) {
//                if (!resp) return;
//                try {
//                    const [lat, lng] = resp.trim().split(',').map(parseFloat);
//                    if (!Number.isNaN(lat) && !Number.isNaN(lng)) el._updateCarPosition(lat, lng);
//                } catch (err) { console.warn('Fallback parse error', err); }
//            },
//            error: () => {}
//        });
//    }, 10000);
//
//    // initial fetch
//    $.ajax({
//        url: 'includes/get_geo_position.php',
//        method: 'GET',
//        data: { idUser },
//        success: function(resp) {
//            if (!resp) return;
//            try {
//                const [lat, lng] = resp.trim().split(',').map(parseFloat);
//                if (!Number.isNaN(lat) && !Number.isNaN(lng)) el._updateCarPosition(lat, lng);
//            } catch (err) { console.warn('Initial fallback parse error', err); }
//        }
//    });
//
//    // apply cached GPS if available
//    if (window.__lastGps?.lat && window.__lastGps?.lng) {
//        try { el._updateCarPosition(window.__lastGps.lat, window.__lastGps.lng, { speed: window.__lastGps.speed }); } catch (e) {}
//    }
//
//    el.classList.add('ip-map-instance');
//
//    return { containerId, map, objectMarker: el._objectMarker, carMarker: el._carMarker };
//}
//
///* ------------------------
//    функция за fallback линия
//   ------------------------ */
//function drawFallbackLine(el, fromLatLng, toLatLng) {
//
//    const meters = haversineDistanceMeters(fromLatLng, toLatLng);
//    const mid = L.latLng(
//        (fromLatLng.lat + toLatLng.lat) / 2,
//        (fromLatLng.lng + toLatLng.lng) / 2
//    );
//
//    showDistanceLabel(el, mid, meters, 'fallback');
//
//    if (!el || !el._localMap) return;
//
//    if (el._fallbackLine) {
//        el._fallbackLine.setLatLngs([fromLatLng, toLatLng]);
//        return;
//    }
//
//    el._fallbackLine = L.polyline(
//        [fromLatLng, toLatLng],
//        {
//            color: '#ff9800',
//            weight: 4,
//            opacity: 0.9,
//            dashArray: '6,6'
//        }
//    ).addTo(el._localMap);
//}
//
///* ------------------------
//   openMapModal(modalId, oLat, oLan, idUser)
//   ------------------------ */
//    window.openMapModal = function(modalId, oLat, oLan, idUser) {
//
//        const modalEl = document.getElementById(modalId);
//
//        if (!modalEl) {
//            console.error('Modal not found:', modalId);
//            return;
//        }
//
//        const suffix = modalId.replace(/^mapModal/i, '');
//        const containerId = 'mapContainer_' + suffix;
//
//        const bsModal = new bootstrap.Modal(modalEl);
//
//        modalEl.addEventListener('shown.bs.modal', function handler(){
//            //console.log('Modal opened → init map', containerId);
//            initMapUnique(containerId, oLat, oLan, idUser);
//            modalEl.removeEventListener('shown.bs.modal', handler);
//        });
//
//        bsModal.show();
//
//    };
////function openMapModal(modalId, oLat, oLan, idUser) {
////    const modalEl = document.getElementById(modalId);
////    if (!modalEl) {
////        console.error('openMapModal: modal element not found', modalId);
////        return;
////    }
////
////    const bsModal = new bootstrap.Modal(modalEl);
////    bsModal.show();
////
////    const suffix = modalId.replace(/^modalMap/i, '');
////    const containerId = 'mapContainer_' + suffix;
////
////    // wait a bit for modal animation so container has size
////    setTimeout(() => {
////        initMapUnique(containerId, oLat, oLan, idUser);
////    }, 300);
////
////    const handlerName = '__cleanup_handler_' + modalId;
////    if (modalEl[handlerName]) {
////        modalEl.removeEventListener('hidden.bs.modal', modalEl[handlerName]);
////        modalEl[handlerName] = null;
////    }
////
////    modalEl[handlerName] = function() {
////        const mapEl = document.getElementById(containerId);
////        if (mapEl && mapEl._fallbackLine) {
////            mapEl._fallbackLine.remove();
////            mapEl._fallbackLine = null;
////            removeDistanceLabel(mapEl);
////        }
////
////        cleanupMapContainer(containerId);
////        try {
////            if (typeof updateInterval !== 'undefined') { clearInterval(updateInterval); updateInterval = null; }
////        } catch (e) {}
////        try { modalEl.removeEventListener('hidden.bs.modal', modalEl[handlerName]); } catch (e) {}
////        modalEl[handlerName] = null;
////    };
////
////    modalEl.addEventListener('hidden.bs.modal', modalEl[handlerName]);
////}
//
///* ------------------------
//   Глобална функция за подаване на GPS от WebView
//   ------------------------ */
//window.updateCarFromWebView = function(lat, lng, speed, bearing, accuracy, altitude) {
//    try {
//        const maps = document.querySelectorAll('[id^="mapContainer_"]');
//        maps.forEach(function(mapEl) {
//            if (!mapEl) return;
//            if (typeof mapEl._updateCarPosition === 'function') {
//                try {
//                    mapEl._updateCarPosition(lat, lng, { speed, bearing, accuracy, altitude });
//                } catch (e) {
//                    console.warn('mapEl._updateCarPosition error', e);
//                }
//            }
//        });
//        window.__lastGps = { lat, lng, speed, bearing, accuracy, altitude, ts: Date.now() };
//    } catch (e) {
//        console.error('updateCarFromWebView error', e);
//    }
//};
//
//function showDistanceLabel(el, latlng, meters, source = 'route') {
//    if (!el || !el._localMap) return;
//
//    const text =
//        meters >= 1000
//            ? (meters / 1000).toFixed(2) + ' km'
//            : Math.round(meters) + ' m';
//
//    if (!el._distanceLabel) {
//        el._distanceLabel = L.tooltip({
//            permanent: true,
//            direction: 'center',
//            className: 'distance-label',
//            offset: [0, 0]
//        })
//            .setContent(text)
//            .setLatLng(latlng)
//            .addTo(el._localMap);
//    } else {
//        el._distanceLabel
//            .setContent(text)
//            .setLatLng(latlng);
//    }
//}
//
//function removeDistanceLabel(el) {
//    if (el && el._distanceLabel && el._localMap) {
//        try {
//            el._localMap.removeLayer(el._distanceLabel);
//        } catch (e) {}
//        el._distanceLabel = null;
//    }
//}
//
//function clearAllRoutes(el) {
//    if (!el || !el._localMap) return;
//
//    if (el._fallbackLine) {
//        el._localMap.removeLayer(el._fallbackLine);
//        el._fallbackLine = null;
//    }
//
//    if (el._orsRouteLine) {
//        el._localMap.removeLayer(el._orsRouteLine);
//        el._orsRouteLine = null;
//    }
//
//    removeDistanceLabel(el);
//}
//
//async function fetchRouteORS(from, to) {
//    const r = await fetch(
//        'https://api.openrouteservice.org/v2/directions/driving-car/geojson',
//        {
//            method: 'POST',
//            headers: {
//                'Authorization': 'eyJvcmciOiI1YjNjZTM1OTc4NTExMTAwMDFjZjYyNDgiLCJpZCI6IjllOTBmZGJkMTBiMDQwY2JhN2YxZTlmMzk3NGEwODAxIiwiaCI6Im11cm11cjY0In0=',
//                'Content-Type': 'application/json'
//            },
//            body: JSON.stringify({
//                coordinates: [
//                    [from.lng, from.lat],
//                    [to.lng, to.lat]
//                ]
//            })
//        }
//    );
//
//    if (!r.ok) throw new Error('ORS routing failed');
//
//    const j = await r.json();
//
//    return {
//        coords: j.features[0].geometry.coordinates.map(c => [c[1], c[0]]),
//        meters: j.features[0].properties.summary.distance
//    };
//}
//
//function drawORSRoute(el, coords, meters) {
//    clearAllRoutes(el);
//
//    el._orsRouteLine = L.polyline(coords, {
//        color: '#4caf50',
//        weight: 5,
//        opacity: 0.9
//    }).addTo(el._localMap);
//
//    const mid = coords[Math.floor(coords.length / 2)];
//    showDistanceLabel(el, L.latLng(mid[0], mid[1]), meters);
//}
//
//
///* ------------------------
//   КРАЙ Универсален HtmlMarker (OverlayView) - лек HTML маркер
//   ------------------------ */
//
//
//// Глобални променливи
//window._alarmRefresh = window._alarmRefresh || {
//    intervalId: null,
//    abortController: null,
//    currentContainerId: null,
//    currentURL: null,
//    intervalMs: 5000
//};
