document.addEventListener("DOMContentLoaded", () => {
    const searchBtn = document.getElementById("searchBtn");
    const searchBox = document.querySelector(".topbar .search-box");
    const searchInput = document.getElementById("searchInput");
    const mobileMenuBtn = document.getElementById("mobileMenuBtn");
    const topMenu = document.getElementById("topMenu");

    if (mobileMenuBtn && topMenu) {
        mobileMenuBtn.addEventListener("click", () => {
            topMenu.classList.toggle("aberto");
        });
    }

    function aplicarFiltroNaPagina(termo) {
        const seletores = [".post-card", ".campanha-card", ".ong-box", ".item-doacao"];
        let encontrouAlvo = false;

        seletores.forEach(seletor => {
            const itens = document.querySelectorAll(seletor);
            if (itens.length > 0) {
                encontrouAlvo = true;
                itens.forEach(item => {
                    const texto = item.textContent.toLowerCase();
                    item.style.display = texto.includes(termo) ? "" : "none";
                });
            }
        });

        return encontrouAlvo;
    }

    if (searchBtn && searchBox && searchInput) {
        searchBtn.addEventListener("click", () => {
            searchBox.classList.toggle("active");

            if (searchBox.classList.contains("active")) {
                searchInput.focus();
            } else {
                searchInput.value = "";
                aplicarFiltroNaPagina("");
            }
        });

        searchInput.addEventListener("input", function () {
            const termo = this.value.toLowerCase().trim();
            aplicarFiltroNaPagina(termo);
        });

        searchInput.addEventListener("keydown", function (event) {
            if (event.key === "Enter") {
                const termo = this.value.trim();
                const temListaNaPagina = aplicarFiltroNaPagina(termo.toLowerCase());

                if (!temListaNaPagina && termo !== "") {
                    window.location.href = `ongs.php?busca=${encodeURIComponent(termo)}`;
                }
            }
        });

        // Se vier redirecionado com ?busca=... (ex: vindo do Perfil), aplica o filtro automaticamente
        const params = new URLSearchParams(window.location.search);
        const buscaInicial = params.get("busca");
        if (buscaInicial) {
            searchBox.classList.add("active");
            searchInput.value = buscaInicial;
            setTimeout(() => aplicarFiltroNaPagina(buscaInicial.toLowerCase().trim()), 150);
        }
    }
});