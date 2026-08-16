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

document.addEventListener("DOMContentLoaded", () => {
    carregarPosts();
});

async function carregarPosts() {

    const container = document.getElementById("post-container");

    try {

        const resposta = await fetch("../php1/buscarPosts.php");

        if (!resposta.ok) {
            throw new Error("Erro ao buscar os posts.");
        }

        const posts = await resposta.json();

        container.innerHTML = "";

        if (posts.length === 0) {
            container.innerHTML = `
                <div class="sem-posts">
                    <h3>Nenhuma publicação encontrada.</h3>
                    <p>As ONGs ainda não publicaram nenhum conteúdo.</p>
                </div>
            `;

            return;
        }

        posts.forEach(post => {
            criarPost(post, container);
        });

    } catch (erro) {

        console.error(erro);

        container.innerHTML = `
            <div class="erro-posts">
                <h3>Não foi possível carregar as publicações.</h3>
                <p>Tente novamente mais tarde.</p>
            </div>
        `;
    }
}

function criarPost(post, container) {

    const article = document.createElement("article");

    article.classList.add("post-card");

    const data = formatarData(post.dataPublicacao);

    article.innerHTML = `
        <div class="post-header">
            <div>
                <h3>${escaparHTML(post.nomeONG)}</h3>
                <span>${data}</span>
            </div>
        </div>

        <img
            class="post-image"
            src="${escaparHTML(post.conteudo)}"
            alt="${escaparHTML(post.titulo)}"
        >

        <h2>${escaparHTML(post.titulo)}</h2>

        <p>
            ${escaparHTML(post.descricao)}
        </p>

        <div class="post-actions">

            <button
                class="donate-btn"
                data-post-id="${post.idPost}">
                Doar Agora
            </button>

            <button
                class="details-btn"
                data-post-id="${post.idPost}">
                Ver Detalhes
            </button>

        </div>
    `;

    container.appendChild(article);
}

function formatarData(dataBanco) {

    const data = new Date(dataBanco.replace(" ", "T"));

    if (isNaN(data)) {
        return "Data não disponível";
    }

    return data.toLocaleDateString("pt-BR", {
        day: "2-digit",
        month: "2-digit",
        year: "numeric"
    });
}

function escaparHTML(texto) {

    if (texto === null || texto === undefined) {
        return "";
    }

    const div = document.createElement("div");

    div.textContent = texto;

    return div.innerHTML;
}