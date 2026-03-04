document.addEventListener("DOMContentLoaded", function () {

    const html = document.documentElement;
    const toggleBtn = document.getElementById("themeToggle");

    const savedTheme = localStorage.getItem("theme") || "dark";
    html.setAttribute("data-bs-theme", savedTheme);

    if (toggleBtn) {
        toggleBtn.addEventListener("click", function () {
            const current = html.getAttribute("data-bs-theme");
            const newTheme = current === "dark" ? "light" : "dark";

            html.setAttribute("data-bs-theme", newTheme);
            localStorage.setItem("theme", newTheme);

            toggleBtn.innerHTML =
                newTheme === "dark"
                    ? '<i class="fa-solid fa-moon"></i>'
                    : '<i class="fa-solid fa-sun"></i>';
        });
    }
});