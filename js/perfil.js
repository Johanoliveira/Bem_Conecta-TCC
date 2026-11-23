function definirTextoSeExistir(id, valor) {
    const elemento = document.getElementById(id);
    if (elemento) {
        elemento.textContent = valor;
    }
}

async function carregarPerfil() {
    try {
        const resposta = await fetch("../php/dadosPerfil.php");

        if (!resposta.ok) {
            throw new Error("Erro ao carregar perfil.");
        }

        const dados = await resposta.json();

        definirTextoSeExistir("nomeUsuario", dados.nome);
        definirTextoSeExistir("nomeCompleto", dados.nome);
        definirTextoSeExistir("telefoneUsuario", dados.telefone);
        definirTextoSeExistir("localizacaoUsuario", dados.localizacao);
        definirTextoSeExistir("dataCriacao", dados.dataCriacao);

        const fotoUsuario = document.getElementById("fotoUsuario");
        if (fotoUsuario) {
            const caminhoFoto = dados.foto || "img/jpg/ftPerfil.jpg";
            fotoUsuario.src = "../" + caminhoFoto;
            fotoUsuario.onerror = function () {
                this.onerror = null;
                this.src = "../img/jpg/ftPerfil.jpg";
            };
        }

        // Estatísticas de doador (só existem na tela quando nivel === 1)
        definirTextoSeExistir("totalDoadoEstatistica", "R$ " + dados.totalDoado);
        definirTextoSeExistir("campanhasEstatistica", dados.campanhasApoiadas);
        definirTextoSeExistir("ongsEstatistica", dados.ongsFavoritas);

        const listaCausas = document.getElementById("listaCausas");
        if (listaCausas) {
            listaCausas.innerHTML = "";
            if (!dados.causas || dados.causas.length === 0) {
                listaCausas.innerHTML = "<span>-</span>";
            } else {
                dados.causas.forEach(causa => {
                    const tag = document.createElement("span");
                    tag.textContent = causa;
                    listaCausas.appendChild(tag);
                });
            }
        }
    } catch (erro) {
        console.error("Erro ao carregar perfil:", erro);
    }
}

function abrirPopupFoto() {
    document.getElementById("popupFoto").classList.add("ativo");
}

function fecharPopupFoto() {
    document.getElementById("popupFoto").classList.remove("ativo");
    document.getElementById("fotoPerfil").value = "";
    document.querySelector(".preview-foto").innerHTML = "<span>+</span>";
}

// Prévia da foto
document.getElementById("fotoPerfil").addEventListener("change", function () {
    const arquivo = this.files[0];
    if (!arquivo) return;

    if (!arquivo.type.startsWith("image/")) {
        alert("Selecione uma imagem válida.");
        this.value = "";
        return;
    }

    const leitor = new FileReader();
    leitor.onload = function (e) {
        document.querySelector(".preview-foto").innerHTML = `
            <img src="${e.target.result}" alt="Prévia da foto">
        `;
    };
    leitor.readAsDataURL(arquivo);
});

// Envio da foto
document.getElementById("formFoto").addEventListener("submit", async function (event) {
    event.preventDefault();

    const arquivo = document.getElementById("fotoPerfil").files[0];
    if (!arquivo) {
        alert("Escolha uma foto primeiro.");
        return;
    }

    const formulario = new FormData();
    formulario.append("fotoPerfil", arquivo);

    const botao = document.querySelector(".salvar-foto");
    botao.disabled = true;
    botao.textContent = "Enviando...";

    try {
        const resposta = await fetch("uploadFoto.php", {
            method: "POST",
            body: formulario
        });

        const texto = await resposta.text();
        let dados;

        try {
            dados = JSON.parse(texto);
        } catch (erro) {
            alert("Erro ao processar resposta do servidor.");
            return;
        }

        if (!dados.sucesso) {
            alert(dados.mensagem);
            return;
        }

        const fotoPerfil = document.querySelector(".foto-perfil img");
        if (fotoPerfil) {
            fotoPerfil.src = "../" + dados.foto + "?v=" + Date.now();
        }

        alert("Foto alterada com sucesso!");
        fecharPopupFoto();
    } catch (erro) {
        alert("Ocorreu um erro ao enviar a foto.");
    } finally {
        botao.disabled = false;
        botao.textContent = "Salvar foto";
    }
});

carregarPerfil();

// =========================
// EDIÇÃO DE INFORMAÇÕES DO PERFIL E EXCLUSÃO DE POSTS
// =========================
document.addEventListener("DOMContentLoaded", () => {
    const btnEditar = document.getElementById("btnEditarPerfil");
    const btnCancelar = document.getElementById("btnCancelarEdicao");

    if (btnEditar && btnCancelar) {
        const campoNome = document.getElementById("campoNome");
        const campoTelefone = document.getElementById("campoTelefone");
        const campoEmail = document.getElementById("campoEmail");
        const avisoEmail = document.getElementById("avisoEmail");

        const nomeCompletoP = document.getElementById("nomeCompleto");
        const nomeUsuarioH2 = document.getElementById("nomeUsuario");
        const telefoneP = document.getElementById("telefoneUsuario");

        const inputNome = document.getElementById("inputNome");
        const inputTelefone = document.getElementById("inputTelefone");

        // Campos extras caso seja ONG
        const campoDescricaoOng = document.getElementById("campoDescricaoOng");
        const campoWebsiteOng = document.getElementById("campoWebsiteOng");
        const campoInstagramOng = document.getElementById("campoInstagramOng");

        const descOngP = document.getElementById("descricaoOngTexto");
        const webOngP = document.getElementById("websiteOngTexto");
        const instaOngP = document.getElementById("instagramOngTexto");

        const inputDescOng = document.getElementById("inputDescricaoOng");
        const inputWebOng = document.getElementById("inputWebsiteOng");
        const inputInstaOng = document.getElementById("inputInstagramOng");

        let emEdicao = false;

        function ativarModoEdicao() {
            emEdicao = true;

            inputNome.value = nomeCompletoP.textContent.trim() === "-" ? "" : nomeCompletoP.textContent.trim();
            inputTelefone.value = telefoneP.textContent.trim() === "-" ? "" : telefoneP.textContent.trim();

            nomeCompletoP.style.display = "none";
            telefoneP.style.display = "none";
            inputNome.style.display = "block";
            inputTelefone.style.display = "block";

            campoNome.classList.add("campo-editavel-ativo");
            campoTelefone.classList.add("campo-editavel-ativo");

            if (inputDescOng && descOngP) {
                inputDescOng.value = descOngP.textContent.trim() === "-" ? "" : descOngP.textContent.trim();
                descOngP.style.display = "none";
                inputDescOng.style.display = "block";
                campoDescricaoOng.classList.add("campo-editavel-ativo");
            }

            if (inputWebOng && webOngP) {
                inputWebOng.value = webOngP.textContent.trim() === "-" ? "" : webOngP.textContent.trim();
                webOngP.style.display = "none";
                inputWebOng.style.display = "block";
                campoWebsiteOng.classList.add("campo-editavel-ativo");
            }

            if (inputInstaOng && instaOngP) {
                inputInstaOng.value = instaOngP.textContent.trim() === "-" ? "" : instaOngP.textContent.trim();
                instaOngP.style.display = "none";
                inputInstaOng.style.display = "block";
                campoInstagramOng.classList.add("campo-editavel-ativo");
            }

            campoEmail.classList.add("campo-bloqueado");
            avisoEmail.style.display = "inline";

            btnEditar.innerHTML = '<i class="fa-solid fa-check"></i> Salvar Alterações';
            btnCancelar.style.display = "inline-block";
            inputNome.focus();
        }

        function desativarModoEdicao() {
            emEdicao = false;

            inputNome.style.display = "none";
            inputTelefone.style.display = "none";
            nomeCompletoP.style.display = "block";
            telefoneP.style.display = "block";

            campoNome.classList.remove("campo-editavel-ativo");
            campoTelefone.classList.remove("campo-editavel-ativo");

            if (inputDescOng && descOngP) {
                inputDescOng.style.display = "none";
                descOngP.style.display = "block";
                campoDescricaoOng.classList.remove("campo-editavel-ativo");
            }

            if (inputWebOng && webOngP) {
                inputWebOng.style.display = "none";
                webOngP.style.display = "block";
                campoWebsiteOng.classList.remove("campo-editavel-ativo");
            }

            if (inputInstaOng && instaOngP) {
                inputInstaOng.style.display = "none";
                instaOngP.style.display = "block";
                campoInstagramOng.classList.remove("campo-editavel-ativo");
            }

            campoEmail.classList.remove("campo-bloqueado");
            avisoEmail.style.display = "none";

            btnEditar.innerHTML = '<i class="fa-solid fa-pen"></i> Editar Perfil';
            btnCancelar.style.display = "none";
        }

        btnCancelar.addEventListener("click", desativarModoEdicao);

        btnEditar.addEventListener("click", async () => {
            if (!emEdicao) {
                ativarModoEdicao();
                return;
            }

            btnEditar.disabled = true;
            btnEditar.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Salvando...';

            const formData = new FormData();
            formData.append("acao", "editar_perfil");
            formData.append("nome", inputNome.value.trim());
            formData.append("telefone", inputTelefone.value.trim());

            if (inputDescOng) formData.append("descricaoOng", inputDescOng.value.trim());
            if (inputWebOng) formData.append("websiteOng", inputWebOng.value.trim());
            if (inputInstaOng) formData.append("instagramOng", inputInstaOng.value.trim());

            try {
                const resposta = await fetch("perfil.php", {
                    method: "POST",
                    body: formData
                });

                const resultado = await resposta.json();

                if (!resultado.sucesso) {
                    alert(resultado.mensagem || "Erro ao salvar no banco de dados.");
                    window.location.reload();
                    return;
                }

                const nomeSalvo = inputNome.value.trim() || "-";
                const telSalvo = inputTelefone.value.trim() || "-";

                nomeCompletoP.textContent = nomeSalvo;
                nomeUsuarioH2.textContent = nomeSalvo;
                telefoneP.textContent = telSalvo;

                if (descOngP && inputDescOng) descOngP.textContent = inputDescOng.value.trim() || "-";
                if (webOngP && inputWebOng) webOngP.textContent = inputWebOng.value.trim() || "-";
                if (instaOngP && inputInstaOng) instaOngP.textContent = inputInstaOng.value.trim() || "-";

                desativarModoEdicao();
            } catch (erro) {
                alert("Erro inesperado ao salvar no banco de dados.");
                window.location.reload();
            } finally {
                btnEditar.disabled = false;
            }
        });
    }

    // Exclusão de post diretamente pela página de perfil da ONG
    document.querySelectorAll(".btn-excluir-post-perfil").forEach(botao => {
        botao.addEventListener("click", async () => {
            const idPost = Number(botao.dataset.postId);
            if (!confirm("Tem certeza que deseja excluir esta publicação?")) {
                return;
            }

            botao.disabled = true;
            try {
                const resposta = await fetch("excluirPost.php", {
                    method: "POST",
                    headers: { "Content-Type": "application/json" },
                    body: JSON.stringify({ idPost })
                });
                const dados = await resposta.json();

                if (!dados.sucesso) {
                    alert(dados.erro || "Não foi possível excluir a publicação.");
                    botao.disabled = false;
                    return;
                }

                botao.closest(".item-post-perfil").remove();
            } catch (erro) {
                alert("Erro de conexão ao excluir a publicação.");
                botao.disabled = false;
            }
        });
    });
});

// =========================
    // EDITAR E EXCLUIR CAMPANHAS DA ONG
    // =========================
    const btnEditarCampanhas = document.getElementById("btnEditarCampanhas");
    let editandoCampanhas = false;

    if (btnEditarCampanhas) {
        btnEditarCampanhas.addEventListener("click", () => {
            editandoCampanhas = !editandoCampanhas;
            btnEditarCampanhas.classList.toggle("ativo", editandoCampanhas);
            btnEditarCampanhas.innerHTML = editandoCampanhas
                ? '<i class="fa-solid fa-check"></i> Concluir'
                : '<i class="fa-solid fa-pen"></i> Editar';

            document.querySelectorAll(".btn-excluir-campanha").forEach(btn => {
                btn.style.display = editandoCampanhas ? "block" : "none";
            });
        });
    }

    document.querySelectorAll(".btn-excluir-campanha").forEach(botao => {
        botao.addEventListener("click", async () => {
            const idCampanha = Number(botao.dataset.campanhaId);

            if (!confirm("Você quer mesmo excluir esta campanha? Todos os posts associados a ela seram excluidos!")) {
                return;
            }

            botao.disabled = true;
            botao.textContent = "Excluindo...";

            try {
                const resposta = await fetch("excluirCampanha.php", {
                    method: "POST",
                    headers: { "Content-Type": "application/json" },
                    body: JSON.stringify({ idCampanha })
                });

                const dados = await resposta.json();

                if (!dados.sucesso) {
                    alert(dados.erro || "Não foi possível excluir a campanha.");
                    botao.disabled = false;
                    botao.innerHTML = '<i class="fa-solid fa-trash"></i> Excluir Campanha';
                    return;
                }

                // Remove o card da campanha da tela
                const cardCampanha = botao.closest(".card-campanha-perfil");
                if (cardCampanha) {
                    cardCampanha.remove();
                }

                // Remove também os posts associados a essa campanha na seção Meus Posts
                document.querySelectorAll(`.item-post-perfil[data-campanha-id="${idCampanha}"]`).forEach(postEl => {
                    postEl.remove();
                });

            } catch (erro) {
                alert("Erro de conexão ao excluir a campanha.");
                botao.disabled = false;
                botao.innerHTML = '<i class="fa-solid fa-trash"></i> Excluir Campanha';
            }
        });
    });