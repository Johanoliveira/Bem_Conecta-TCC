<?php

session_start();

require_once "conexao.php";

// Verifica se existe usuário logado
if (!isset($_SESSION["usuario_id"])) {
    header("Location: login.html");
    exit;
}

$idUsuario = $_SESSION["usuario_id"];


// Busca os dados do usuário
$sql = "SELECT idMUsuario, nome, email, telefone, fotoPerfil
        FROM MoldeUsuario
        WHERE idMUsuario = ?";

$stmt = $conexao->prepare($sql);

if (!$stmt) {
    die("Erro ao preparar consulta: " . $conexao->error);
}

$stmt->bind_param("i", $idUsuario);
$stmt->execute();

$resultado = $stmt->get_result();

$usuario = $resultado->fetch_assoc();

$stmt->close();


// Verifica se o usuário existe
if (!$usuario) {
    session_destroy();

    header("Location: login.html");
    exit;
}


// Dados
$nome = $usuario["nome"] ?: "-";
$email = $usuario["email"] ?: "-";
$telefone = $usuario["telefone"] ?: "-";
$foto = $usuario["fotoPerfil"] ?: "img/jpg/ftPerfil.jpg";

?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Meu Perfil - Bem Conecta</title>

    <link rel="stylesheet" href="../css/perfil.css">

    <!-- Ícones -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>

<body>

    <!-- CABEÇALHO -->
    <header class="topo">

        <div class="logos">
            <div class="logo-foto">
                <img src="../img/logo/logo.webp" loading="lazy" title="logo">
            </div>
            <div class="logo-escrita">
                <img src="../img/logo/escrita.png" loading="lazy" title="logo">
            </div>
        </div>


        <nav class="menu-superior">
            <a href="#">Início</a>
            <a href="#">Projetos</a>
            <a href="#">ONGs</a>
            <a href="#">Doações</a>
        </nav>


        <div class="usuario-topo">

            <button class="botao-pesquisa">
                🔍
            </button>
        </div>

    </header>


    <!-- CONTEÚDO PRINCIPAL -->
    <main class="pagina">


        <!-- MENU LATERAL -->
        <aside class="sidebar">

            <div class="menu-lateral">

                <h2>Menu</h2>

                <a href="#">
                    <span>🏠</span>
                    Página Inicial
                </a>

                <a href="#">
                    <span>💗</span>
                    Minhas Doações
                </a>

                <a href="#">
                    <span>📌</span>
                    ONGs Favoritas
                </a>

                <a href="#">
                    <span>📄</span>
                    Campanhas
                </a>

                <a href="#">
                    <span>🎯</span>
                    Voluntariado
                </a>

                <a href="#" class="ativo">
                    <span>👤</span>
                    Meu Perfil
                </a>

                <a href="#">
                    <span>⚙️</span>
                    Configurações
                </a>

            </div>


            <!-- CARD DE IMPACTO -->
            <div class="impacto">
                <h2>Seu Impacto</h2>
                <p>
                    <strong id="totalDoado">-</strong> doados
                </p>
                <p>
                    <strong id="campanhasApoiadas">-</strong>
                    campanhas apoiadas
                </p>
                <p>
                    <strong id="ongsFavoritas">-</strong>
                    ONGs favoritas
                </p>
            </div>

            <div class="meus-posts">
                <a href="#">
                    Meus Posts
                </a>
            </div>

        </aside>


        <!-- ÁREA DO PERFIL -->
        <section class="conteudo-perfil">


            <!-- TÍTULO -->
            <div class="titulo-pagina">
                <h1>Meu Perfil</h1>
                <p>Gerencie suas informações e acompanhe seu impacto.</p>
            </div>


            <!-- CARD PRINCIPAL -->
            <div class="card-perfil">


                <div class="perfil-cabecalho">

                    <div class="foto-perfil">
                        <img
                            id="fotoUsuario"
                            src="../<?php echo htmlspecialchars($foto); ?>"
                            alt="Foto de perfil"
                        >

                        <button class="editar-foto" type="button" onclick="abrirPopupFoto()">
                            ✎
                        </button>
                    </div>


                    <div class="informacoes-principais">

                        <h2 id="nomeUsuario">
                            <?php echo htmlspecialchars($nome); ?>
                        </h2>

                        <p class="email" id="emailUsuario">
                            <i class="fa-solid fa-envelope"></i>
                            <?php echo htmlspecialchars($email); ?>
                        </p>

                        <p class="membro">
                            <i class="fa-solid fa-calendar"></i>
                            Membro desde
                            <span id="dataCriacao">-</span>
                        </p>

                    </div>


                    <button class="botao-editar">
                        <i class="fa-solid fa-pen"></i>
                        Editar Perfil
                    </button>

                </div>


                <hr>


                <!-- INFORMAÇÕES PESSOAIS -->
                <div class="secao">

                    <h2>Informações Pessoais</h2>

                    <div class="grid-informacoes">

                        <div class="campo">
                            <span>Nome completo</span>
                            <p id="nomeCompleto">
                            <?php echo htmlspecialchars($nome); ?>
                            </p>
                        </div>

                        <div class="campo">
                            <span>E-mail</span>
                            <p class="email" id="emailUsuario">
                            <i class="fa-solid fa-envelope"></i>
                            <?php echo htmlspecialchars($email); ?>
                        </p>
                        </div>

                        <div class="campo">
                            <span>Telefone</span>
                            <p id="telefoneUsuario">
                                <?php echo htmlspecialchars($telefone); ?>
                            </p>
                        </div>

                        <div class="campo">
                            <span>Localização</span>
                            <p>São Paulo, SP</p>
                        </div>

                    </div>

                </div>


                <!-- INTERESSES -->
                <div class="secao interesses">
                    <h2>Causas que você apoia</h2>
                    <div class="tags" id="listaCausas">
                    <span>-</span>
                </div>
                </div>

            </div>


            <!-- ESTATÍSTICAS -->
            <div class="estatisticas">

                <div class="card-estatistica">
                    <div class="icone verde">
                        <i class="fa-solid fa-hand-holding-heart"></i>
                    </div>

                    <div>
                        <span>Total Doado</span>
                        <h2 id="totalDoadoEstatistica">-</h2>
                    </div>
                </div>


                <div class="card-estatistica">
                    <div class="icone">
                        <i class="fa-solid fa-bullseye"></i>
                    </div>

                    <div>
                        <span>Campanhas</span>
                        <h2 id="campanhasEstatistica">-</h2>
                    </div>
                </div>


                <div class="card-estatistica">
                    <div class="icone">
                        <i class="fa-solid fa-heart"></i>
                    </div>

                    <div>
                        <span>ONGs Favoritas</span>
                        <h2 id="ongsEstatistica">-</h2>
                    </div>
                </div>

            </div>


            <!-- ATIVIDADE RECENTE -->
            <div class="atividade">

                <h2>Atividade Recente</h2>


                <div class="atividade-item">

                    <div class="atividade-icone">
                        ❤️
                    </div>

                    <div>
                        <h3>Você realizou uma doação</h3>
                        <p>R$ 50,00 para a campanha Água para Todos</p>
                    </div>

                    <span>Hoje</span>

                </div>


                <div class="atividade-item">

                    <div class="atividade-icone">
                        ⭐
                    </div>

                    <div>
                        <h3>Você adicionou uma ONG aos favoritos</h3>
                        <p>Instituto Educação para Todos</p>
                    </div>

                    <span>2 dias</span>

                </div>


                <div class="atividade-item">

                    <div class="atividade-icone">
                        🎯
                    </div>

                    <div>
                        <h3>Você participou de uma atividade</h3>
                        <p>Projeto Educação que Transforma</p>
                    </div>

                    <span>5 dias</span>

                </div>

            </div>

        </section>

    </main>

    <div id="popupFoto" class="popup-foto">
    <div class="popup-conteudo">

            <button class="fechar-popup" type="button" onclick="fecharPopupFoto()">
                &times;
            </button>

            <h2>Alterar foto de perfil</h2>

            <form id="formFoto" enctype="multipart/form-data">

                <div class="preview-foto">
                    <span>+</span>
                </div>

                <label for="fotoPerfil" class="selecionar-foto">
                    Escolher foto
                </label>

                <input 
                    type="file" 
                    id="fotoPerfil" 
                    name="fotoPerfil"
                    accept="image/jpeg,image/png,image/webp"
                    required
                >

                <div class="botoes-popup">

                    <button 
                        type="button" 
                        class="cancelar-foto" 
                        onclick="fecharPopupFoto()">
                        Cancelar
                    </button>

                    <button 
                        type="submit" 
                        class="salvar-foto">
                        Salvar foto
                    </button>

                </div>

            </form>

        </div>
    </div>

    <script src="../js/perfil.js"></script>
    
</body>

</html>