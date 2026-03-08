/* ===============================
   GLOBAL VARIABLES
=============================== */
let mapInstance = null;
let mapMarker = null;

/* ===============================
   DEBOUNCE FUNCTION
=============================== */
function debounce(func, wait) {
    let timeout;
    return function(...args) {
        clearTimeout(timeout);
        timeout = setTimeout(() => func.apply(this, args), wait);
    };
}

/* ===============================
   LOAD OBJECT MODAL
=============================== */
$(document).on("click", ".openObjectModal", function(){
    const btn = $(this);

    const id   = btn.data("id");
    const name = btn.data("name");
    const office = btn.data("office");
    const info   = btn.data("info");
    const lat    = parseFloat(btn.data("lat")) || 43.2728759;
    const lng    = parseFloat(btn.data("lng")) || 26.9266601;

    $("#modal_object_id").val(id);
    $("#modal_object_name").val(name);
    $("#modal_object_office").val(office);
    $("#modal_object_info").val(info);

    const modal = new bootstrap.Modal(document.getElementById("objectModal"));
    modal.show();

    setTimeout(()=>{
        if(!mapInstance){
            mapInstance = L.map("objectMapContainer").setView([lat,lng],16);
            L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png",{maxZoom:19}).addTo(mapInstance);
        } else {
            mapInstance.setView([lat,lng],16);
        }

        if(mapMarker){
            mapInstance.removeLayer(mapMarker);
        }
        mapMarker = L.marker([lat,lng], {draggable:true}).addTo(mapInstance);

        setTimeout(()=>mapInstance.invalidateSize(),200);
    },200);
});

// FIX modal backdrop and body class
$('#objectModal').on('hidden.bs.modal', function () {
    $('.modal-backdrop').remove();
    $('body').removeClass('modal-open');
    $('body').css('padding-right','');
});

/* ===============================
   SAVE OBJECT (DATA + COORDS)
=============================== */
$(document).on("click", "#saveObjectBtnModal", function(){
    const id     = $("#modal_object_id").val();
    const name   = $("#modal_object_name").val().trim();
    const office = $("#modal_object_office").val();
    const info   = $("#modal_object_info").val().trim();

    if(!name || !office){
        alert("Попълнете задължителните полета!");
        return;
    }

    const lat = mapMarker ? mapMarker.getLatLng().lat : null;
    const lng = mapMarker ? mapMarker.getLatLng().lng : null;

    $.post("includes/update_objects.php", {
        id:id,
        name:name,
        office:office,
        info:info,
        lat:lat,
        lng:lng
    }, function(resp){
        if(resp.success){
            showToast("Данните са записани успешно","success");
            // Обнови данните в card
            const btns = $(`.openObjectModal[data-id='${id}']`);
            btns.data("name",name).data("office",office).data("info",info).data("lat",lat).data("lng",lng);
        } else {
            showToast("Грешка при запис","danger");
        }
    },"json");
});

/* ===============================
   TOAST MESSAGE
=============================== */
function showToast(message,type="success"){
    const toast = $(`
        <div class="toast align-items-center text-bg-${type} border-0 position-fixed bottom-0 end-0 m-3" style="z-index:9999">
            <div class="d-flex">
                <div class="toast-body">${message}</div>
                <button class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        </div>
    `);
    $("body").append(toast);
    const t = new bootstrap.Toast(toast[0],{delay:2500});
    t.show();
    toast.on("hidden.bs.toast",()=>toast.remove());
}

/* ===============================
   SEARCH FILTER
=============================== */
function reloadObjects(){
    const office = $("#objectOfficeFilter").val();
    const search = $("#objectSearch").val().trim();
    window.location.href = `dashboard.php?page=objects&id=${office}&search=${encodeURIComponent(search)}`;
}

$("#objectOfficeFilter").on("change", reloadObjects);

$("#objectSearch").on("keyup", debounce(function(){
    if($(this).val().trim().length>=2 || $(this).val().trim().length===0){
        reloadObjects();
    }
},500));