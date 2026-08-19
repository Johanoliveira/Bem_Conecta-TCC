async function carregarPerfil() {

    try {

        const resposta = await fetch("../php/dadosPerfil.php");

        if (!resposta.ok) {
            throw new Error("Erro ao buscar perfil.");
        }

        const dados = await resposta.json();

        document.getElementById("nomeUsuario").textContent = dados.nome;
        document.getElementById("nomeCompleto").textContent = dados.nome;
        document.getElementById("emailUsuario").innerHTML =
            `<i class="fa-solid fa-envelope"></i> ${dados.email}`;

        document.getElementById("telefoneUsuario").textContent =
            dados.telefone;

        document.getElementById("fotoUsuario").src =
            "../img/perfil/" + dados.foto;

    } catch (erro) {

        console.error("Erro:", erro);

    }

}


// Carrega quando a página abrir
carregarPerfil();