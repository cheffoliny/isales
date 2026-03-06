let mapInstance = null;
let mapMarker = null;
let activeObjectID = null;

// Отваряне на модал карта
$(document).on("click", ".openMapBtn", function(){

    let modalID = $(this).data("modal");
    let mapID = $(this).data("map");
    let lat = parseFloat($(this).data("lat")) || 43.2712398;
    let lng = parseFloat($(this).data("lng")) || 26.9361286;
    let objID = $(this).data("id");

    activeObjectID = objID;

    let modalEl = document.getElementById(modalID);
    let modal = new bootstrap.Modal(modalEl);
    modal.show();

    modalEl.addEventListener('shown.bs.modal', function(){

        setTimeout(function(){

            if(mapInstance){
                mapInstance.remove();
                mapInstance = null;
            }

            mapInstance = L.map(mapID).setView([lat, lng], 16);

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19
            }).addTo(mapInstance);

            mapMarker = L.marker([lat, lng], {draggable:true}).addTo(mapInstance);

            setTimeout(function(){
                mapInstance.invalidateSize();
            }, 200);

        }, 200);

    }, {once:true});

});

// Запис на координати
$(document).on("click", ".saveObjectCoords", function(){

    if(!mapMarker) return;

    let coords = mapMarker.getLatLng();

    $.post("includes/update_object_coords.php", {
        id: activeObjectID,
        lat: coords.lat,
        lan: coords.lng
    }, function(resp){
        if(resp.success){
            alert("Координатите са записани");
        } else {
            alert("Грешка при запис");
        }
    }, "json");

});

// Отваряне на модал за редакция
$(document).on("click", ".openEditObject", function(){

    let id = $(this).data("id");
    let name = $(this).data("name");
    let office = $(this).data("office");
    let info = $(this).data("info");

    $("#edit_object_id").val(id);
    $("#edit_object_name").val(name);
    $("#edit_object_office").val(office);
    $("#edit_object_info").val(info);

    let modal = new bootstrap.Modal(document.getElementById("editObjectModal"));
    modal.show();

});

// Запис на редакция
$("#saveObjectBtn").click(function(){

    let id = $("#edit_object_id").val();
    let name = $("#edit_object_name").val();
    let office = $("#edit_object_office").val();
    let info = $("#edit_object_info").val();

    $.post("includes/update_object.php", {
        id: id,
        name: name,
        office: office,
        info: info
    }, function(resp){
        if(resp.success){
            location.reload();
        } else {
            alert("Грешка при запис");
        }
    }, "json");

});