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


// Carrega o perfil quando a página abre
carregarPerfil();