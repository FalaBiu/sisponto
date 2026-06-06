<?php
// 1. Define o fuso horário no PHP para funções de data/hora
date_default_timezone_set('America/Sao_Paulo');

// Configurações do Banco de Dados
$host    = 'localhost';
$db_name = 'sisponto';
$user    = 'dbasisponto';
$pass    = 'sisponto1000'; 

try {
    // Cria a conexão
    $conn = new PDO("mysql:host=$host;dbname=$db_name;charset=utf8mb4", $user, $pass);
    
    // 2. Define o fuso horário no MySQL (Brasília é -03:00)
    $conn->exec("SET time_zone = '-03:00'");

    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    // Log de erro objetivo
    error_log($e->getMessage());
    die("Erro técnico na conexão.");
}
?>