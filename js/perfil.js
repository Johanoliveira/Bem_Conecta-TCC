async function carregarPerfil() {

    try {

        const resposta = await fetch("../php/dadosPerfil.php");

        if (!resposta.ok) {
            throw new Error("Erro ao carregar perfil.");
        }

        const dados = await resposta.json();


        // =========================
        // INFORMAÇÕES PESSOAIS
        // =========================

        document.getElementById("nomeUsuario").textContent =
            dados.nome;

        document.getElementById("nomeCompleto").textContent =
            dados.nome;

        document.getElementById("emailUsuario").textContent =
            dados.email;

        document.getElementById("emailCompleto").textContent =
            dados.email;

        document.getElementById("telefoneUsuario").textContent =
            dados.telefone;

        document.getElementById("localizacaoUsuario").textContent =
            dados.localizacao;

        document.getElementById("dataCriacao").textContent =
            dados.dataCriacao;


        // =========================
        // FOTO
        // =========================

        document.getElementById("fotoUsuario").src =
            "../img/perfil/" + dados.foto;


        // =========================
        // IMPACTO
        // =========================

        document.getElementById("totalDoado").textContent =
            "R$ " + dados.totalDoado;

        document.getElementById("campanhasApoiadas").textContent =
            dados.campanhasApoiadas;

        document.getElementById("ongsFavoritas").textContent =
            dados.ongsFavoritas;


        // =========================
        // ESTATÍSTICAS
        // =========================

        document.getElementById("totalDoadoEstatistica").textContent =
            "R$ " + dados.totalDoado;

        document.getElementById("campanhasEstatistica").textContent =
            dados.campanhasApoiadas;

        document.getElementById("ongsEstatistica").textContent =
            dados.ongsFavoritas;


        // =========================
        // CAUSAS
        // =========================

        const listaCausas =
            document.getElementById("listaCausas");

        listaCausas.innerHTML = "";


        if (dados.causas.length === 0) {

            listaCausas.innerHTML =
                "<span>-</span>";

        } else {

            dados.causas.forEach(causa => {

                const tag = document.createElement("span");

                tag.textContent = causa;

                listaCausas.appendChild(tag);

            });

        }

    } catch (erro) {

        console.error("Erro ao carregar perfil:", erro);

    }

}

//função para abrir foto perfil e editar ela
function abrirPopupFoto() {
    document.getElementById("popupFoto").classList.add("ativo");
}

function fecharPopupFoto() {
    document.getElementById("popupFoto").classList.remove("ativo");

    document.getElementById("fotoPerfil").value = "";

    document.querySelector(".preview-foto").innerHTML = "<span>+</span>";
}


// =========================
// PRÉVIA DA FOTO
// =========================

document.getElementById("fotoPerfil").addEventListener("change", function () {

    const arquivo = this.files[0];

    if (!arquivo) {
        return;
    }

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


// =========================
// ENVIO DA FOTO
// =========================

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

        console.log("Resposta do PHP:", texto);

        let dados;

        try {
            dados = JSON.parse(texto);
        } catch (erro) {
            console.error("O PHP não retornou JSON válido.");
            console.error(texto);

            alert("O PHP retornou um erro. Abra o F12 e veja o Console.");
            return;
        }

        if (!dados.sucesso) {
            alert(dados.mensagem);
            return;
        }

        // Atualiza a foto exibida no perfil
        const fotoPerfil = document.querySelector(".foto-perfil img");

        if (fotoPerfil) {
            fotoPerfil.src = "../" + dados.foto + "?v=" + Date.now();
        }

        // Atualiza também a foto da topbar, se existir
        const fotoTopo = document.querySelector(".perfil-mini img");

        if (fotoTopo) {
            fotoTopo.src = dados.foto + "?v=" + Date.now();
        }

        alert("Foto alterada com sucesso!");

        fecharPopupFoto();

    } catch (erro) {

        console.error(erro);

        alert("Ocorreu um erro ao enviar a foto.");

    } finally {

        botao.disabled = false;
        botao.textContent = "Salvar foto";

    }

});

// Carrega o perfil quando a página abre
carregarPerfil();

// =========================
// EDIÇÃO DE INFORMAÇÕES DO PERFIL
// =========================

document.addEventListener("DOMContentLoaded", () => {
    const btnEditar = document.getElementById("btnEditarPerfil");
    const btnCancelar = document.getElementById("btnCancelarEdicao");

    if (!btnEditar || !btnCancelar) {
        return;
    }

    const campoNome = document.getElementById("campoNome");
    const campoTelefone = document.getElementById("campoTelefone");
    const campoEmail = document.getElementById("campoEmail");
    const avisoEmail = document.getElementById("avisoEmail");

    const nomeCompletoP = document.getElementById("nomeCompleto");
    const nomeUsuarioH2 = document.getElementById("nomeUsuario");
    const telefoneP = document.getElementById("telefoneUsuario");

    const inputNome = document.getElementById("inputNome");
    const inputTelefone = document.getElementById("inputTelefone");

    let emEdicao = false;

    function ativarModoEdicao() {
        emEdicao = true;

        const nomeAtual = nomeCompletoP.textContent.trim();
        const telAtual = telefoneP.textContent.trim();

        inputNome.value = nomeAtual === "-" ? "" : nomeAtual;
        inputTelefone.value = telAtual === "-" ? "" : telAtual;

        nomeCompletoP.style.display = "none";
        telefoneP.style.display = "none";

        inputNome.style.display = "block";
        inputTelefone.style.display = "block";

        campoNome.classList.add("campo-editavel-ativo");
        campoTelefone.classList.add("campo-editavel-ativo");

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

        campoEmail.classList.remove("campo-bloqueado");
        avisoEmail.style.display = "none";

        btnEditar.innerHTML = '<i class="fa-solid fa-pen"></i> Editar Perfil';
        btnCancelar.style.display = "none";
    }

    btnCancelar.addEventListener("click", () => {
        desativarModoEdicao();
    });

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

            desativarModoEdicao();
        } catch (erro) {
            alert("Erro inesperado ao salvar no banco de dados.");
            window.location.reload();
        } finally {
            btnEditar.disabled = false;
        }
    });
});