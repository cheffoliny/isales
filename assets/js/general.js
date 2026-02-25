$(document).on('click', '.menu-link', function(e){
    e.preventDefault();

    const page = $(this).data('page');

    $('.menu-link').removeClass('active');
    $(this).addClass('active');

    $('#main-content').html(`
        <div class="text-center text-white py-5">
            <div class="spinner-border"></div>
            <div class="mt-2">Зареждане...</div>
        </div>
    `);

    $.ajax({
        url: 'system/load_content.php',
        method: 'GET',
        data: { page: page },
        success: function(response){
            $('#main-content').html(response);
        },
        error: function(){
            $('#main-content').html(`
                <div class="alert alert-danger">
                    Грешка при зареждане на модула.
                </div>
            `);
        }
    });
});


$(document).on('click', '.ajax-office-row', function(){

    const page = $(this).data('page');
    const id   = $(this).data('id');

    $('#main-content').html(`
        <div class="text-center text-white py-5">
            <div class="spinner-border"></div>
            <div class="mt-2">Зареждане...</div>
        </div>
    `);

    $.ajax({
        url: 'system/load_content.php',
        method: 'GET',
        data: { page: page, id: id },
        success: function(response){
            $('#main-content').html(response);
        },
        error: function(){
            $('#main-content').html(`
                <div class="alert alert-danger">
                    Грешка при зареждане.
                </div>
            `);
        }
    });
});

$(document).on('click', '.ajax-delivery-request', function(){

    const page = $(this).data('page');
    const id   = $(this).data('id');

    $('#main-content').html(`
        <div class="text-center text-white py-5">
            <div class="spinner-border"></div>
            <div class="mt-2">Зареждане на заявка...</div>
        </div>
    `);

    $.ajax({
        url: 'system/load_content.php',
        method: 'GET',
        data: { page: page, id: id },
        success: function(response){
            $('#main-content').html(response);
        },
        error: function(){
            $('#main-content').html(`
                <div class="alert alert-danger">
                    Грешка при зареждане на заявката.
                </div>
            `);
        }
    });
});

function showToast(msg, type = "success") {

	let toastEl = document.getElementById('toastMsg');
	let body = toastEl.querySelector('.toast-body');

	body.innerHTML = msg;

	if (type === "error") {
		toastEl.classList.remove('text-bg-dark');
		toastEl.classList.add('text-bg-danger');
	} else {
		toastEl.classList.remove('text-bg-danger');
		toastEl.classList.add('text-bg-dark');
	}

	let toast = new bootstrap.Toast(toastEl);
	toast.show();
}

// Зарежда unknown.php с избрания cityID
// Работи и за динамично заредени елементи
$(document).on('change', '#cities', function () {
	allowAlarmAutoRefresh = false;

	let citieID = this.value;
	//alert(citieID + "ТУК");
	$("#main-content").load('content/unknown.php?citieID=' + citieID);
});

// Оригиналната функция без промяна
function confirmUnknownMap(oID, tVisit) {

    $.post('api/unknown_confirm.php', {
        oID: oID,
        type_visit: tVisit
    })
    .done(function (res) {

        if (res.status === "success") {

            let row = $(".object-row-" + oID);

            row.addClass("fade-out");

            setTimeout(() => row.remove(), 400);

            showToast("Обектът е успешно добавен в опознати!");

        } else {
            showToast("⚠️ Грешка: " + res.message, "error");
        }

    })
    .fail(function () {
        showToast("⚠️ Грешка при заявката към сървъра!", "error");
    });
}

// Функцията за потвърждение на обекта
function confirmUnknown(oID, tVisit) {

    $.post('api/unknown_confirm.php', {
        oID: oID,
        type_visit: tVisit
    })
    .done(function (res) {

        if (res.status === "success") {

            let row = $(".object-row-" + oID);

            row.addClass("fade-out");

            setTimeout(() => row.remove(), 400);

            showToast("Обектът е успешно добавен в опознати!");

        } else {
            showToast("⚠️ Грешка: " + res.message, "error");
        }

    })
    .fail(function () {
        showToast("⚠️ Грешка при заявката към сървъра!", "error");
    });
}