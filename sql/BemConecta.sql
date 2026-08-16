create database banco;
use banco;

CREATE TABLE endereco (
    inEndereco INT AUTO_INCREMENT PRIMARY KEY,
    logradouro VARCHAR(50) NOT NULL,
    numero int NOT NULL,
    bairro VARCHAR(50) NOT NULL,
    cidade VARCHAR(50) NOT NULL,
    estado VARCHAR(2) NOT NULL,
    CEP int NOT NULL
);

create table MoldeUsuario(
    idMUsuario int auto_increment primary key,
    nome varchar(255) not null,
    email varchar(255) not null unique,
    senha varchar(255) not null,
    fotoPerfil varchar(50) not null,
    telefone varchar(11) not null,
    FOREIGN KEY (idEndereco) REFERENCES endereco(idEndereco)
);

CREATE TABLE ONGs (
    idONG INT AUTO_INCREMENT PRIMARY KEY,
    descricao VARCHAR(150) NOT NULL,
    CNPJ INT NOT NULL UNIQUE,
    Website INT UNIQUE,
    instagram INT,
    facebook INT,
    X INT,
    FOREIGN KEY (idMoldeUsuario) REFERENCES MoldeUsuario(idMUsuario)
);

CREATE TABLE posts (
    idPost INT AUTO_INCREMENT PRIMARY KEY,
    titulo varchar(70) INT NOT NULL,
    conteudo varchar(100) NOT NULL,
    dataPublicacao datetime DEFAULT,
    palavrasChave varchar(10) not null,
    FOREIGN KEY (idONG) REFERENCES ONG(idONG)
);

CREATE TABLE UsuarioComum (
    idUsuarioComum int AUTO_INCREMENT PRIMARY KEY,
    CPF int not null unique,
    FOREIGN KEY (idMoldeUsuario) REFERENCES MoldeUsuario(idMUsuario)
);

CREATE TABLE comentarios (
    idComentario INT AUTO_INCREMENT PRIMARY KEY,
    conteudo varchar(100) NOT NULL,
    dataComentario datetime DEFAULT,
    FOREIGN KEY (idUsuarioComum) REFERENCES UsuarioComum(idUsuarioComum),
    FOREIGN KEY (idONG) REFERENCES ONG(idONG)
);
