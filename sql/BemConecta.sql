create database banco;
use banco;

create table usuario(
    id int auto_increment primary key,
    nome varchar(255) not null,
    email varchar(255) not null unique,
    senha varchar(255) not null,
    cpf varchar(15) not null unique,
    telefone varchar(20) not null
);

create table ONG(
    id int auto_increment primary key,
    nome varchar(255) not null,
    email varchar(255) not null unique,
    senha varchar(255) not null,
    cnpj varchar(20) not null unique,
    telefone varchar(20) not null
);
