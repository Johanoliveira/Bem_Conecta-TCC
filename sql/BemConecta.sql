CREATE DATABASE banco;
USE banco;

CREATE TABLE endereco (
    idEndereco INT AUTO_INCREMENT PRIMARY KEY,
    logradouro VARCHAR(50) NOT NULL,
    numero INT NOT NULL,
    bairro VARCHAR(50) NOT NULL,
    cidade VARCHAR(50) NOT NULL,
    estado VARCHAR(2) NOT NULL,
    CEP VARCHAR(8) NOT NULL
);

CREATE TABLE MoldeUsuario (
    idMUsuario INT AUTO_INCREMENT PRIMARY KEY,
    idEndereco INT NOT NULL,
    nome VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    senha VARCHAR(255) NOT NULL,
    fotoPerfil VARCHAR(50),
    telefone VARCHAR(11) NOT NULL,
    FOREIGN KEY (idEndereco) REFERENCES endereco(idEndereco)
);

CREATE TABLE ONGs (
    idONG INT AUTO_INCREMENT PRIMARY KEY,
    idMoldeUsuario INT NOT NULL,
    descricao VARCHAR(150) NOT NULL,
    CNPJ VARCHAR(14) NOT NULL UNIQUE,
    Website VARCHAR(255) UNIQUE,
    instagram VARCHAR(255),
    facebook VARCHAR(255),
    X VARCHAR(255),
    FOREIGN KEY (idMoldeUsuario) REFERENCES MoldeUsuario(idMUsuario)
);

CREATE TABLE posts (
    idPost INT AUTO_INCREMENT PRIMARY KEY,
    idONG INT NOT NULL,
    titulo VARCHAR(70) NOT NULL,
    conteudo VARCHAR(100) NOT NULL,
    dataPublicacao DATETIME DEFAULT CURRENT_TIMESTAMP,
    palavrasChave VARCHAR(255) NOT NULL,
    FOREIGN KEY (idONG) REFERENCES ONGs(idONG)
);

CREATE TABLE UsuarioComum (
    idUsuarioComum INT AUTO_INCREMENT PRIMARY KEY,
    idMoldeUsuario INT NOT NULL,
    CPF VARCHAR(11) NOT NULL UNIQUE,
    FOREIGN KEY (idMoldeUsuario) REFERENCES MoldeUsuario(idMUsuario)
);

CREATE TABLE comentarios (
    idComentario INT AUTO_INCREMENT PRIMARY KEY,
    idUsuarioComum INT NOT NULL,
    idONG INT NOT NULL,
    conteudo VARCHAR(100) NOT NULL,
    dataComentario DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (idUsuarioComum) REFERENCES UsuarioComum(idUsuarioComum),
    FOREIGN KEY (idONG) REFERENCES ONGs(idONG)
);