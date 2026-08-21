CREATE DATABASE banco;

USE banco;

-- =========================================
-- ENDEREÇO
-- =========================================

CREATE TABLE endereco (
    idEndereco INT AUTO_INCREMENT PRIMARY KEY,
    logradouro VARCHAR(50) NOT NULL,
    numero INT NOT NULL,
    bairro VARCHAR(50) NOT NULL,
    cidade VARCHAR(50) NOT NULL,
    estado VARCHAR(2) NOT NULL,
    CEP VARCHAR(8) NOT NULL
);


-- =========================================
-- USUÁRIO BASE
-- =========================================

CREATE TABLE MoldeUsuario (
    idMUsuario INT AUTO_INCREMENT PRIMARY KEY,
    idEndereco INT,
    nome VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    senha VARCHAR(255) NOT NULL,
    fotoPerfil VARCHAR(255),
    telefone VARCHAR(11),
    dataCriacao DATETIME DEFAULT CURRENT_TIMESTAMP,
    nivelDeSeguranca int not null DEFAULT 1,

    FOREIGN KEY (idEndereco)
        REFERENCES endereco(idEndereco)
);


-- =========================================
-- ONGs
-- =========================================

CREATE TABLE ONGs (
    idONG INT AUTO_INCREMENT PRIMARY KEY,
    idMoldeUsuario INT NOT NULL,
    descricao VARCHAR(150) NOT NULL,
    CNPJ VARCHAR(14) NOT NULL UNIQUE,
    Website VARCHAR(255),
    instagram VARCHAR(255),
    facebook VARCHAR(255),
    linkX VARCHAR(255),

    FOREIGN KEY (idMoldeUsuario)
        REFERENCES MoldeUsuario(idMUsuario)
);


-- =========================================
-- USUÁRIO COMUM
-- =========================================

CREATE TABLE UsuarioComum (
    idUsuarioComum INT AUTO_INCREMENT PRIMARY KEY,
    idMoldeUsuario INT NOT NULL,
    CPF VARCHAR(11) UNIQUE,
    dataNasc date,

    FOREIGN KEY (idMoldeUsuario)
        REFERENCES MoldeUsuario(idMUsuario)
);


-- =========================================
-- CAUSAS
-- =========================================

CREATE TABLE causas (
    idCausa INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL UNIQUE,
    descricao VARCHAR(255)
);


-- =========================================
-- CAUSAS APOIADAS PELO USUÁRIO
-- =========================================

CREATE TABLE usuarioCausas (
    idUsuarioComum INT NOT NULL,
    idCausa INT NOT NULL,

    PRIMARY KEY (idUsuarioComum, idCausa),

    FOREIGN KEY (idUsuarioComum)
        REFERENCES UsuarioComum(idUsuarioComum),

    FOREIGN KEY (idCausa)
        REFERENCES causas(idCausa)
);


-- =========================================
-- CAMPANHAS
-- =========================================

CREATE TABLE campanhas (
    idCampanha INT AUTO_INCREMENT PRIMARY KEY,
    idONG INT NOT NULL,
    titulo VARCHAR(100) NOT NULL,
    descricao TEXT NOT NULL,
    meta DECIMAL(10,2),
    dataInicio DATETIME DEFAULT CURRENT_TIMESTAMP,
    dataFim DATETIME,
    status VARCHAR(20) DEFAULT 'ativa',
    COUNT(DISTINCT doacoes.idCampanha),

    FOREIGN KEY (idONG)
        REFERENCES ONGs(idONG)
);


-- =========================================
-- CAUSAS DAS CAMPANHAS
-- =========================================

CREATE TABLE campanhaCausas (
    idCampanha INT NOT NULL,
    idCausa INT NOT NULL,

    PRIMARY KEY (idCampanha, idCausa),

    FOREIGN KEY (idCampanha)
        REFERENCES campanhas(idCampanha),

    FOREIGN KEY (idCausa)
        REFERENCES causas(idCausa)
);


-- =========================================
-- POSTS
-- Um post pode pertencer a uma campanha
-- ou ser um post geral da ONG
-- =========================================

CREATE TABLE posts (
    idPost INT AUTO_INCREMENT PRIMARY KEY,
    idONG INT NOT NULL,
    idCampanha INT NULL,
    titulo VARCHAR(70) NOT NULL,
    conteudo TEXT NOT NULL,
    dataPublicacao DATETIME DEFAULT CURRENT_TIMESTAMP,
    palavrasChave VARCHAR(255),

    FOREIGN KEY (idONG)
        REFERENCES ONGs(idONG),

    FOREIGN KEY (idCampanha)
        REFERENCES campanhas(idCampanha)
);


-- =========================================
-- COMENTÁRIOS
-- =========================================

CREATE TABLE comentarios (
    idComentario INT AUTO_INCREMENT PRIMARY KEY,
    idUsuarioComum INT NOT NULL,
    idPost INT NOT NULL,
    conteudo VARCHAR(500) NOT NULL,
    dataComentario DATETIME DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (idUsuarioComum)
        REFERENCES UsuarioComum(idUsuarioComum),

    FOREIGN KEY (idPost)
        REFERENCES posts(idPost)
);


-- =========================================
-- ONGs FAVORITAS
-- =========================================

CREATE TABLE favoritos (
    idFavorito INT AUTO_INCREMENT PRIMARY KEY,
    idUsuarioComum INT NOT NULL,
    idONG INT NOT NULL,
    dataFavorito DATETIME DEFAULT CURRENT_TIMESTAMP,

    UNIQUE (idUsuarioComum, idONG),

    FOREIGN KEY (idUsuarioComum)
        REFERENCES UsuarioComum(idUsuarioComum),

    FOREIGN KEY (idONG)
        REFERENCES ONGs(idONG)
);


-- =========================================
-- DOAÇÕES
-- =========================================

CREATE TABLE doacoes (
    idDoacao INT AUTO_INCREMENT PRIMARY KEY,
    idUsuarioComum INT NOT NULL,
    idCampanha INT NOT NULL,
    valor DECIMAL(10,2) NOT NULL,
    dataDoacao DATETIME DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (idUsuarioComum)
        REFERENCES UsuarioComum(idUsuarioComum),

    FOREIGN KEY (idCampanha)
        REFERENCES campanhas(idCampanha)
);


-- =========================================
-- ATIVIDADES DE VOLUNTARIADO
-- =========================================

CREATE TABLE atividades (
    idAtividade INT AUTO_INCREMENT PRIMARY KEY,
    idONG INT NOT NULL,
    titulo VARCHAR(100) NOT NULL,
    descricao TEXT,
    dataAtividade DATETIME NOT NULL,
    localizacao VARCHAR(255),

    FOREIGN KEY (idONG)
        REFERENCES ONGs(idONG)
);


-- =========================================
-- PARTICIPAÇÃO EM ATIVIDADES
-- =========================================

CREATE TABLE voluntariado (
    idUsuarioComum INT NOT NULL,
    idAtividade INT NOT NULL,
    dataInscricao DATETIME DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (idUsuarioComum, idAtividade),

    FOREIGN KEY (idUsuarioComum)
        REFERENCES UsuarioComum(idUsuarioComum),

    FOREIGN KEY (idAtividade)
        REFERENCES atividades(idAtividade)
);

-- Curtidas
CREATE TABLE curtidas (
    idMUsuario INT NOT NULL,
    idPost INT NOT NULL,
    dataCurtida DATETIME DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (idMUsuario, idPost),

    FOREIGN KEY (idMUsuario)
        REFERENCES MoldeUsuario(idMUsuario),

    FOREIGN KEY (idPost)
        REFERENCES posts(idPost)
);