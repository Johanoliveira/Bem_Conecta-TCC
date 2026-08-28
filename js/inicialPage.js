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


// POST PARA TESTE

const modoTeste = true;

const postsTeste = [
    {
        idPost: 1,
        idONG: 1,
        nomeONG: "Instituto Sementes do Futuro",
        titulo: "Ajude nossa campanha de materiais escolares",
        conteudo: "../img/jpg/holder.jpg",
        descricao: "Estamos arrecadando recursos para distribuir materiais escolares para crianças em situação de vulnerabilidade. Nossa meta é atender 500 famílias até o próximo mês.",
        dataPublicacao: "2026-08-16 12:30:00",
        palavrasChave: "educação, crianças, doação"
    }
];


// CARREGA POSTS

async function carregarPosts() {

    const container = document.getElementById("posts-container");

    try {

        let posts;

        if (modoTeste) {

            posts = postsTeste;

        } else {

            const resposta = await fetch("../php/buscarPosts.php");

            if (!resposta.ok) {
                throw new Error("Erro ao buscar os posts.");
            }

            posts = await resposta.json();
        }

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

                <div class="erro-icon">
                    !
                </div>

                <h3>Não foi possível carregar as publicações.</h3>

                <p>
                    Parece que estamos com problemas de conexão.
                    <br>
                    Tente novamente mais tarde.
                </p>

                <button class="retry-posts-btn">
                    ↻ Tentar novamente
                </button>

            </div>
        `;
    }
}


// CRIA POST

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
            loading="lazy"
        >


        <h2>${escaparHTML(post.titulo)}</h2>


        <p class="post-description">
            ${escaparHTML(post.descricao)}
        </p>


        <div class="post-actions">

            <button
                class="like-btn"
                data-post-id="${post.idPost}">
                Curtir <span class="like-count">0</span>
            </button>


            <button
                class="comment-btn"
                data-post-id="${post.idPost}">
                Comentar
            </button>


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


        <div
            class="comments-section"
            id="comments-${post.idPost}"
        >

            <div class="comments-list"></div>


            <div class="comment-form">

                <input
                    type="text"
                    class="comment-input"
                    placeholder="Escreva um comentário..."
                >

                <button class="send-comment">
                    Enviar
                </button>

            </div>

        </div>
    `;

    container.appendChild(article);
}


// FORMATA DATA

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


// ESCAPA HTML

function escaparHTML(texto) {

    if (texto === null || texto === undefined) {
        return "";
    }

    const div = document.createElement("div");

    div.textContent = texto;

    return div.innerHTML;
}


// CLIQUES

document.addEventListener("click", function(event) {


    // CURTIR

    const likeButton = event.target.closest(".like-btn");

    if (likeButton) {

        const countElement =
            likeButton.querySelector(".like-count");

        let curtidas =
            Number(countElement.textContent);


        if (likeButton.classList.contains("liked")) {

            curtidas--;

            likeButton.classList.remove("liked");

            likeButton.firstChild.textContent = "Curtir ";

        } else {

            curtidas++;

            likeButton.classList.add("liked");

            likeButton.firstChild.textContent = "Curtido ";

        }

        countElement.textContent = curtidas;
    }


    // ABRIR COMENTÁRIOS

    const commentButton =
        event.target.closest(".comment-btn");

    if (commentButton) {

        const postId =
            commentButton.dataset.postId;

        const commentsSection =
            document.getElementById(
                `comments-${postId}`
            );

        commentsSection.classList.toggle("active");
    }


    // ENVIAR COMENTÁRIO

    const sendButton =
        event.target.closest(".send-comment");

    if (sendButton) {

        const section =
            sendButton.closest(".comments-section");

        const input =
            section.querySelector(".comment-input");

        const list =
            section.querySelector(".comments-list");

        const texto =
            input.value.trim();


        if (texto === "") {
            return;
        }


        const comentario =
            document.createElement("div");

        comentario.classList.add("comment");


        comentario.innerHTML = `
            <strong>Pedro</strong>
            <p>${escaparHTML(texto)}</p>
        `;


        list.appendChild(comentario);

        input.value = "";
    }


    // TENTAR NOVAMENTE

    const retryButton =
        event.target.closest(".retry-posts-btn");

    if (retryButton) {

        carregarPosts();
    }

});


// ENTER PARA ENVIAR COMENTÁRIO

document.addEventListener("keydown", function(event) {

    if (event.key !== "Enter") {
        return;
    }

    if (!event.target.classList.contains("comment-input")) {
        return;
    }

    const section =
        event.target.closest(".comments-section");

    const sendButton =
        section.querySelector(".send-comment");

    sendButton.click();
});


// INICIA OS POSTS

carregarPosts();