const searchBtn = document.getElementById("searchBtn");
const searchBox = document.querySelector(".search-box");
const searchInput = document.getElementById("searchInput");

searchBtn.addEventListener("click", () => {
    searchBox.classList.toggle("active");

    if (searchBox.classList.contains("active")) {
        searchInput.focus();
    } else {
        searchInput.value = "";
    }
});