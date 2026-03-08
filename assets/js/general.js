/* ===============================
   GLOBAL MAP VARIABLES
=============================== */
let mapInstance = null;
let mapMarker = null;
let activeObjectID = null;

/* ===============================
   OPEN MAP MODAL
=============================== */
$(document).on("click", ".openMapBtn", function(){

    const btn = $(this);
    activeObjectID = btn.data("id");

    const lat = parseFloat(btn.data("lat")) || 43.2728759;
    const lng = parseFloat(btn.data("lng")) || 26.9266601;

    // Създаваме един общ модал за картата
    if($('#objectMapModal').length === 0){
        $('body').append(`
            <div class="modal fade" id="objectMapModal" tabindex="-1">
                <div class="modal-dialog modal-lg modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-body p-0">
                            <div id="objectMapContainer" style="height:400px;width:100%"></div>
                        </div>
                        <div class="p-3 text-center">
                            <button type="button" class="btn btn-success saveObjectCoords">Запиши координати</button>
                        </div>
                    </div>
                </div>
            </div>
        `);
    }

    const modalEl = document.getElementById('objectMapModal');
    const modal = new bootstrap.Modal(modalEl);
    modal.show();

    setTimeout(function(){

        if(!mapInstance){
            mapInstance = L.map("objectMapContainer").setView([lat, lng], 16);
            L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", { maxZoom: 19 }).addTo(mapInstance);
        } else {
            mapInstance.setView([lat, lng], 16);
        }

        if(mapMarker){
            mapInstance.removeLayer(mapMarker);
        }
        mapMarker = L.marker([lat, lng], {draggable:true}).addTo(mapInstance);

        setTimeout(()=>mapInstance.invalidateSize(), 200);

    },200);
});

// FIX backdrop и body класове
$('#objectMapModal').on('hidden.bs.modal', function () {
    $('.modal-backdrop').remove();
    $('body').removeClass('modal-open');
    $('body').css('padding-right','');

    if(mapMarker){
        mapInstance.removeLayer(mapMarker);
        mapMarker = null;
    }
});

/* ===============================
   SAVE OBJECT COORDS
=============================== */
$(document).on("click", ".saveObjectCoords", function(){

    if(!mapMarker) return;

    const coords = mapMarker.getLatLng();
    const btn = $(this);

    btn.prop("disabled", true).text("Запис...");

    $.post("includes/update_object_coords.php", {
        id: activeObjectID,
        lat: coords.lat,
        lan: coords.lng
    }, function(resp){

        if(resp.success){
            showToast("Координатите са записани", "success");

            const mapBtn = $('.openMapBtn[data-id="'+activeObjectID+'"]');
            mapBtn.attr("data-lat", coords.lat);
            mapBtn.attr("data-lng", coords.lng);

        } else {
            showToast("Грешка при запис", "danger");
        }

        btn.prop("disabled", false).text("Запиши координати");

    }, "json");

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

    const t = new bootstrap.Toast(toast[0], {delay:2500});
    t.show();
    toast.on("hidden.bs.toast", ()=>toast.remove());
}

/* ===============================
   OPEN EDIT OBJECT MODAL
=============================== */
$(document).on("click", ".openEditObject", function(){
    const btn = $(this);

    $("#edit_object_id").val(btn.data("id"));
    $("#edit_object_name").val(btn.data("name"));
    $("#edit_object_office").val(btn.data("office"));
    $("#edit_object_info").val(btn.data("info"));

    const modal = new bootstrap.Modal(document.getElementById("editObjectModal"));
    modal.show();
});

/* ===============================
   SAVE EDIT OBJECT
=============================== */
$(document).on("click", "#saveObjectBtn", function(){

    const id = $("#edit_object_id").val();
    const name = $("#edit_object_name").val();
    const office = $("#edit_object_office").val();
    const info = $("#edit_object_info").val();

    $.post("includes/update_object.php", {
        id: id,
        name: name,
        office: office,
        info: info
    }, function(resp){

        if(resp.success){
            location.reload();
        } else {
            showToast("Грешка при запис", "danger");
        }

    }, "json");

});