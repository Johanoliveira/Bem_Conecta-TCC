document.addEventListener("DOMContentLoaded", () => {
    const sidebar = document.getElementById("sidebarLateral");
    const btnMinimizar = document.getElementById("btnMinimizarSidebar");

    if (!sidebar || !btnMinimizar || btnMinimizar.dataset.bound === "true") return;
    btnMinimizar.dataset.bound = "true";

    function atualizarEstadoVisual(minimizada) {
        sidebar.classList.toggle("minimizada", minimizada);
        btnMinimizar.textContent = minimizada ? "☰" : "◀";
        btnMinimizar.title = minimizada ? "Abrir menu" : "Minimizar menu";
    }

    // Restaura a escolha anterior do usuário
    const estadoSalvo = localStorage.getItem("bemconecta_sidebar_minimizada") === "true";
    atualizarEstadoVisual(estadoSalvo);

    btnMinimizar.addEventListener("click", () => {
        const novoEstado = !sidebar.classList.contains("minimizada");
        atualizarEstadoVisual(novoEstado);
        localStorage.setItem("bemconecta_sidebar_minimizada", String(novoEstado));
    });
});