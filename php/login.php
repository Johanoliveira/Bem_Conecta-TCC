<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dados do login</title>
</head>
<body>
    <?php
        //inicializando variáveis com os valores recebidos
        $login = $_POST["login"];
        $senha = $_POST["senha"];

        //Verificar se o login será feito com o email ou o nome usuario
        if (filter_var($login, FILTER_VALIDATE_EMAIL)) {
            // O usuário digitou um e-mail
            echo "Login feito com e-mail: " . $login;
        } 
        else {
            // O usuário digitou um nome de usuário
            echo "Login feito com usuário: " . $login;
        }
    ?>
</body>
</html>