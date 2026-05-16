/* ===============================
   GLOBAL VARIABLES
=============================== */
let mapInstance = null;
let mapMarker = null;
let isSavingPerson = false;

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
//
///* ===============================
//   LOAD OBJECT MODAL
//=============================== */
//$(document).on("click", ".openObjectModal", function(){
//    const btn = $(this);
//
//    const id   = btn.data("id");
//    const name = btn.data("name");
//    const office = btn.data("office");
//    const info   = btn.data("info");
//    const lat    = parseFloat(btn.data("lat")) || 43.2728759;
//    const lng    = parseFloat(btn.data("lng")) || 26.9266601;
//
//    $("#modal_object_id").val(id);
//    $("#modal_object_name").val(name);
//    $("#modal_object_office").val(office);
//    $("#modal_object_info").val(info);
//
//    const modal = new bootstrap.Modal(document.getElementById("objectModal"));
//    modal.show();
//
//    setTimeout(()=>{
//        if(!mapInstance){
//            mapInstance = L.map("objectMapContainer").setView([lat,lng],16);
//            L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png",{maxZoom:19}).addTo(mapInstance);
//        } else {
//            mapInstance.setView([lat,lng],16);
//        }
//
//        if(mapMarker){
//            mapInstance.removeLayer(mapMarker);
//        }
//        mapMarker = L.marker([lat,lng], {draggable:true}).addTo(mapInstance);
//
//        setTimeout(()=>mapInstance.invalidateSize(),200);
//    },200);
//});
//
//// FIX modal backdrop and body class
//$('#objectModal').on('hidden.bs.modal', function () {
//    $('.modal-backdrop').remove();
//    $('body').removeClass('modal-open');
//    $('body').css('padding-right','');
//});
//
///* ===============================
//   SAVE OBJECT (DATA + COORDS)
//=============================== */
//$(document).on("click", "#saveObjectBtnModal", function(){
//    const id     = $("#modal_object_id").val();
//    const name   = $("#modal_object_name").val().trim();
//    const office = $("#modal_object_office").val();
//    const info   = $("#modal_object_info").val().trim();
//
//    if(!name || !office){
//        alert("Попълнете задължителните полета!");
//        return;
//    }
//
//    const lat = mapMarker ? mapMarker.getLatLng().lat : null;
//    const lng = mapMarker ? mapMarker.getLatLng().lng : null;
//
//    $.post("includes/update_objects.php", {
//        id:id,
//        name:name,
//        office:office,
//        info:info,
//        lat:lat,
//        lng:lng
//    }, function(resp){
//        if(resp.success){
//            showToast("Данните са записани успешно","success");
//            // Обнови данните в card
//            const btns = $(`.openObjectModal[data-id='${id}']`);
//            btns.data("name",name).data("office",office).data("info",info).data("lat",lat).data("lng",lng);
//        } else {
//            showToast("Грешка при запис","danger");
//        }
//    },"json");
//});

/* ===============================
   LOAD PERSON MODAL
=============================== */
$(document).on("click", ".openPersonModal", function(){

    const btn = $(this);

    const id     = btn.data("id");
    const name   = btn.data("name");
    const lname  = btn.data("lname");
    const status = btn.data("status");

    $("#modal_person_id").val(id);
    $("#modal_person_name").val(name);
    $("#modal_person_lname").val(lname);
    $("#modal_person_status").val(status);

    const modal = new bootstrap.Modal(
        document.getElementById("personModal")
    );

    modal.show();

});

/* ===============================
   ADD PERSON
=============================== */
$(document).on("click", "#addPerson", function(){

    $("#modal_person_id").val("");
    $("#modal_person_name").val("");
    $("#modal_person_lname").val("");
    $("#modal_person_status").val("active");

    const modal = new bootstrap.Modal(
        document.getElementById("personModal")
    );

    modal.show();
});

/* ===============================
   SAVE PERSON
=============================== */
$(document).on("click", "#savePersonBtnModal", function(){

    if(isSavingPerson) return;

    const btn = $(this);

    const id     = $("#modal_person_id").val();
    const fname  = $("#modal_person_name").val().trim();
    const lname  = $("#modal_person_lname").val().trim();
    const status = $("#modal_person_status").val();

    if(!fname || !lname){
        showToast("Попълнете име и фамилия", "danger");
        return;
    }

    isSavingPerson = true;

    // 🔵 UI LOCK
    btn.prop("disabled", true).html(`
        <span class="spinner-border spinner-border-sm me-2"></span>
        Запис...
    `);

    $.post("includes/save_person.php", {
        id: id,
        fname: fname,
        lname: lname,
        status: status
    }, function(resp){

        if(!resp.success){
            showToast(resp.message || "Грешка при запис", "danger");
            return;
        }

        const fullName = fname + " " + lname;

        const statusBadge =
            status === "active"
                ? `<span class="badge person-status-badge bg-success">Активен</span>`
                : `<span class="badge person-status-badge bg-danger">Неактивен</span>`;

        // =========================
        // UPDATE OR INSERT
        // =========================

        if(resp.mode === "insert"){

            const newCard = `
            <div class="card mb-2 shadow-sm border-0 person-item"
                 data-person-id="${resp.id}"
                 data-status="${status}"
                 data-profile="0"
                 data-name="${fullName.toLowerCase()}">

                <div class="card-body d-flex align-items-center justify-content-between p-2">

                    <button class="btn btn-primary openPersonModal"
                            data-id="${resp.id}"
                            data-name="${fname}"
                            data-lname="${lname}"
                            data-status="${status}">
                        <i class="fa fa-user"></i>
                    </button>

                    <div class="flex-grow-1 px-2">
                        <div class="fw-bold d-flex align-items-center gap-2 person-fullname">
                            ${fullName}
                            ${statusBadge}
                        </div>
                    </div>

                    <button class="btn btn-sm btn-outline-primary openUserModal"
                            data-person-id="${resp.id}"
                            data-account-id="0"
                            data-username=""
                            data-profile="0"
                            data-offices='[]'>
                        <i class="fa fa-key"></i>
                    </button>

                </div>
            </div>`;

            $("#personsContainer").prepend(newCard);

        } else {

            const row = $(`.person-item[data-person-id='${resp.id}']`);

            row.attr("data-name", fullName.toLowerCase());
            row.attr("data-status", status);

            row.find(".person-fullname").html(`
                ${fullName}
                ${statusBadge}
            `);

            row.find(".openPersonModal")
                .data("name", fname)
                .data("lname", lname)
                .data("status", status);
        }

        // =========================
        // SUCCESS UX
        // =========================
        showToast(
            resp.mode === "insert"
                ? "Добавен служител"
                : "Обновен служител",
            "success"
        );

        // close modal
        const modalEl = document.getElementById("personModal");
        const modal = bootstrap.Modal.getInstance(modalEl);
        if(modal) modal.hide();

    }, "json")
    .fail(function(){

        showToast("Мрежова грешка", "danger");

    })
    .always(function(){

        // 🔵 UNLOCK UI
        isSavingPerson = false;

        btn.prop("disabled", false).html("Запази");

    });

});

/* ===============================
   OPEN CHANGE PASSWORD MODAL
=============================== */
$(document).on("click", "#openChangePasswordModal", function(){

    $("#currentPassword").val("");
    $("#newPassword").val("");
    $("#confirmPassword").val("");

    const modal = new bootstrap.Modal(
        document.getElementById("changePasswordModal")
    );

    modal.show();

});

/* ===============================
   CHANGE PASSWORD
=============================== */
$(document).on("click", "#savePasswordBtn", function(){

    const btn = $(this);

    const currentPassword = $("#currentPassword").val().trim();
    const newPassword = $("#newPassword").val().trim();
    const confirmPassword = $("#confirmPassword").val().trim();

    if(
        !currentPassword ||
        !newPassword ||
        !confirmPassword
    ){
        showToast("Попълнете всички полета", "danger");
        return;
    }

    if(newPassword !== confirmPassword){
        showToast("Паролите не съвпадат", "danger");
        return;
    }

    btn.prop("disabled", true).html(`
        <span class="spinner-border spinner-border-sm me-2"></span>
        Запис...
    `);

    $.post("includes/change_password.php", {

        current_password: currentPassword,
        new_password: newPassword,
        confirm_password: confirmPassword

    }, function(resp){

        if(resp.success){

            showToast(
                "Паролата е сменена успешно",
                "success"
            );

            const modal = bootstrap.Modal.getInstance(
                document.getElementById("changePasswordModal")
            );

            if(modal) modal.hide();

        } else {

            showToast(
                resp.message || "Грешка",
                "danger"
            );
        }

    }, "json")
    .fail(function(){

        showToast("Мрежова грешка", "danger");

    })
    .always(function(){

        btn.prop("disabled", false).html("Запази");

    });

});

/* ===============================
   OPEN USER MODAL
=============================== */
$(document).on("click", ".openUserModal", function(){

    const btn = $(this);

    const personId  = btn.data("person-id");
    const accountId = btn.data("account-id");
    const username  = btn.data("username");
    const profile   = btn.data("profile");

    let offices = btn.data("offices");

    if(typeof offices === "string"){

        try{
            offices = JSON.parse(offices);
        }catch(e){
            offices = [];
        }
    }

    if(!Array.isArray(offices)){
        offices = [];
    }

    /* RESET */

    $("#modal_user_person_id").val(personId);
    $("#modal_account_id").val(accountId || "");

    $("#modal_username").val(username || "");
    $("#modal_password").val("");

    $("#modal_profile").val(profile || 2);

    $(".user-office-checkbox")
        .prop("checked", false);

    /* SELECT OFFICES */
    const allOffices = btn.data("all-offices") == 1;

    if(allOffices || offices.includes(0)){

        $("#user_office_0").prop("checked", true);
        toggleAllOfficesMode();

    } else {

        offices.forEach(function(id){
            $("#user_office_" + id).prop("checked", true);
        });

        toggleAllOfficesMode();
    }

    /* APPLY ALL MODE */
    toggleAllOfficesMode();

    const modal = new bootstrap.Modal(
        document.getElementById("userLevelModal")
    );

    modal.show();

});

/* ===============================
   ALL OFFICES LOGIC
=============================== */
function toggleAllOfficesMode(){

    const allChecked = $("#user_office_0").is(":checked");

    $(".user-office-checkbox").each(function(){

        const val = parseInt($(this).val());

        if(val === 0) return;

        if(allChecked){
            $(this).prop("checked", false).prop("disabled", true);
        } else {
            $(this).prop("disabled", false);
        }
    });
}

/* CHANGE */
$(document).on(
    "change",
    "#user_office_0",
    toggleAllOfficesMode
);

/* ===============================
   SAVE USER ACCOUNT
=============================== */
$(document).on("click", "#saveUserBtnModal", function(){

    const personId  = $("#modal_user_person_id").val();
    let accountId = $("#modal_account_id").val();

    const username = $("#modal_username")
        .val()
        .trim();

    const password = $("#modal_password")
        .val()
        .trim();

    const profile = $("#modal_profile")
        .val();

    let offices = [];

    $(".user-office-checkbox:checked")
        .each(function(){

            offices.push(
                parseInt($(this).val())
            );

        });

    if(!username){

        showToast(
            "Въведете username",
            "danger"
        );

        return;
    }

    $.post(
        "includes/save_user_account.php",
        {

            person_id: personId,
            account_id: accountId,

            username: username,
            password: password,

            profile: profile,

            offices: JSON.stringify(offices)

        },
        function(resp){

            if(resp.success){

                showToast("Потребителят е записан успешно", "success");

                const modalEl = document.getElementById("userLevelModal");
                const modal = bootstrap.Modal.getInstance(modalEl);
                if(modal) modal.hide();

                // FULL REFRESH
                $.get("includes/get_persons.php", function(data){

                    if(data.success){
                        $("#personsContainer").html(data.html);
                        showToast("Списъкът е обновен", "success");
                    }

                }, "json");

            } else {
                showToast(resp.message || "Грешка при запис", "danger");
            }

        },
        "json"
    );

});

/* ===============================
   PERSON FILTER ENGINE
=============================== */
function filterPersons(){

    const search = $("#personSearch")
        .val()
        .toLowerCase()
        .trim();

    const status = $("#personStatusFilter")
        .val();

    const profile = $("#personProfileFilter")
        .val();

    $(".person-item").each(function(){

        const row = $(this);

        const rowName = row.data("name") || "";

        const rowStatus = row.data("status") || "";

        const rowProfile = String(row.data("profile") || "0");

        let visible = true;

        /* SEARCH */
        if(search.length > 0){

            if(!rowName.includes(search)){
                visible = false;
            }
        }

        /* STATUS */
        if(status !== ""){

            if(rowStatus !== status){
                visible = false;
            }
        }

        /* PROFILE */
        if(profile !== ""){

            if(rowProfile !== profile){
                visible = false;
            }
        }

        row.toggle(visible);

    });

}

/* ===============================
   FILTER EVENTS
=============================== */
$(document).on(
    "keyup",
    "#personSearch",
    debounce(filterPersons, 200)
);

$(document).on(
    "change",
    "#personStatusFilter",
    filterPersons
);

$(document).on(
    "change",
    "#personProfileFilter",
    filterPersons
);

/* ===============================
   LOAD OBJECT MODAL
=============================== */
$(document).on("click", ".openObjectModal", function(){

    const btn = $(this);

    const id   = btn.data("id");
    const name = btn.data("name");
    const info = btn.data("info");

    // 👇 НОВО - JSON offices
    let offices = btn.data("offices");

    if(typeof offices === "string"){
        try {
            offices = JSON.parse(offices);
        } catch(e){
            offices = [];
        }
    }

    if(!Array.isArray(offices)){
        offices = [];
    }

    const lat = parseFloat(btn.data("lat")) || 43.2728759;
    const lng = parseFloat(btn.data("lng")) || 26.9266601;

    $("#modal_object_id").val(id);
    $("#modal_object_name").val(name);
    $("#modal_object_info").val(info);

    // ✅ reset чекбоксове
    $(".object-office-checkbox").prop("checked", false);

    // ✅ маркираме избраните
    offices.forEach(function(id){
        $("#office_" + id).prop("checked", true);
    });

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


/* ===============================
   FIX modal backdrop
=============================== */
$('#objectModal').on('hidden.bs.modal', function () {
    $('.modal-backdrop').remove();
    $('body').removeClass('modal-open');
    $('body').css('padding-right','');
});


/* ===============================
   SAVE OBJECT (DATA + COORDS)
=============================== */
$(document).on("click", "#saveObjectBtnModal", function(){

    const id   = $("#modal_object_id").val();
    const name = $("#modal_object_name").val().trim();
    const info = $("#modal_object_info").val().trim();

    // ✅ събиране на чекбоксове
    let offices = [];
    $(".object-office-checkbox:checked").each(function(){
        offices.push(parseInt($(this).val()));
    });

    // ❗ само name е задължително
    if(!name){
        alert("Попълнете име!");
        return;
    }

    const lat = mapMarker ? mapMarker.getLatLng().lat : null;
    const lng = mapMarker ? mapMarker.getLatLng().lng : null;

    $.post("includes/update_objects.php", {
        id: id,
        name: name,
        info: info,
        offices_ids: JSON.stringify(offices), // 👈 ключово
        lat: lat,
        lng: lng
    }, function(resp){

        if(resp.success){

            showToast("Данните са записани успешно","success");

            // ✅ update на бутоните
            const btns = $(`.openObjectModal[data-id='${id}']`);

            btns
                .data("name", name)
                .data("info", info)
                .data("offices", JSON.stringify(offices))
                .data("lat", lat)
                .data("lng", lng);

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