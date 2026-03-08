/* ===============================
   GLOBAL MAP VARIABLES
=============================== */

let mapInstance = null;
let mapMarker = null;
let activeObjectID = null;
let activeMapBtn = null;


/* ===============================
   OPEN MAP MODAL
=============================== */

$(document).on("click", ".openMapBtn", function(){

    const btn = $(this);

    activeObjectID = btn.data("id");
    activeMapBtn = btn;

    let lat = parseFloat(btn.attr("data-lat")) || 43.2712398;
    let lng = parseFloat(btn.attr("data-lng")) || 26.9361286;

    const modalEl = document.getElementById("objectMapModal");
    const modal = new bootstrap.Modal(modalEl);

    modal.show();

    setTimeout(function(){

        if(!mapInstance){

            mapInstance = L.map("objectMapContainer").setView([lat, lng], 16);

            L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
                maxZoom: 19
            }).addTo(mapInstance);

        } else {

            mapInstance.setView([lat, lng], 16);

        }

        if(mapMarker){
            mapInstance.removeLayer(mapMarker);
        }

        mapMarker = L.marker([lat, lng], {draggable:true}).addTo(mapInstance);

        setTimeout(function(){
            mapInstance.invalidateSize();
        },200);

    },200);

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

            if(activeMapBtn){

                /* UPDATE BUTTON DATA + CACHE */

                activeMapBtn
                    .attr("data-lat", coords.lat)
                    .attr("data-lng", coords.lng)
                    .data("lat", coords.lat)
                    .data("lng", coords.lng);

            }

            /* затваряне на модала */
            const modalEl = document.getElementById("objectMapModal");
            const modal = bootstrap.Modal.getInstance(modalEl);

            if(modal){
                modal.hide();
            }

            /* FIX BOOTSTRAP BACKDROP BUG */

            setTimeout(function(){

                $('.modal-backdrop').remove();
                $('body').removeClass('modal-open');
                $('body').css('padding-right','');

            },200);

        }else{

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
        <div class="toast align-items-center text-bg-${type} border-0 position-fixed bottom-0 end-0 m-3"
             style="z-index:9999">
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
        }else{
            showToast("Грешка при запис", "danger");
        }

    }, "json");

});