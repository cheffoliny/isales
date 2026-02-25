// get_geo_data.js
// Отговорник: получаване на GPS от Android WebView и подаване към alarms.js
(function(){
    // кеш за последна известна позиция (ако картата още не е заредена)
    window.__lastGps = null;

    // Изпраща заявка към Android WebView (ако има)
    function requestGPS() {
        if (typeof Android !== "undefined" && Android && typeof Android.requestGPS === 'function') {
            try {
                Android.requestGPS();
            } catch (e) {
                console.warn('Android.requestGPS error', e);
            }
        } else {
            // няма WebView интерфейс
            // silent
        }
    }

    // Callback от Android WebView -> JSON string
    window.receiveGPSJSON = function(jsonStr) {
        if (!jsonStr) return;
        let data;
        try {
            data = JSON.parse(jsonStr);
        } catch (e) {
            console.error("Invalid JSON from Android:", e, jsonStr);
            return;
        }

        const lat = parseFloat(data.lat);
        const lng = parseFloat(data.lng);
        const speed = parseFloat(data.speed) || 0;
        const bearing = parseFloat(data.bearing) || 0;
        const accuracy = typeof data.accuracy !== 'undefined' ? parseFloat(data.accuracy) : -1;
        const altitude = typeof data.altitude !== 'undefined' ? parseFloat(data.altitude) : null;

        // log в бекенд (не чакаме отговор)
        try {
            $.post("includes/log_gps.php", {
                lat: lat,
                lng: lng,
                accuracy: accuracy,
                speed: speed,
                bearing: bearing,
                altitude: altitude
            });
        } catch (e) {
            // ignore
        }

        // ако има глобална фунцкия за ъпдейт — използваме я
        if (typeof window.updateCarFromWebView === 'function') {
            try {
                window.updateCarFromWebView(lat, lng, speed, bearing, accuracy, altitude);
            } catch (e) {
                console.error('updateCarFromWebView error', e);
            }
        } else {
            // ако не е дефинирана — кешираме за после
            window.__lastGps = { lat, lng, speed, bearing, accuracy, altitude, ts: Date.now() };
        }
    };

    // Fallback/heartbeat: опитваме да поискаме GPS от WebView на 5s интервали
    try {
        setInterval(requestGPS, 5000);
        requestGPS();
    } catch (e) {
        console.warn('requestGPS interval error', e);
    }

})();
