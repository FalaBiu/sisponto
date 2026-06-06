<?php
session_start();
require_once 'conexao.php';

if ($_SESSION['usuario_nivel'] !== 'admin') { die("Acesso negado."); }

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id_u   = $_POST['id_usuario'];
    $data   = $_POST['data_ponto'];
    $motivo = $_POST['motivo'];
    $id_adm = $_SESSION['usuario_id'];

    // Ordem enviada pelo Sortable: ['entrada', 'saida_almoco', ...]
    $ordem_tipos = $_POST['tipos']; 
    $valores_hrs = $_POST['horas'];

    try {
        $conn->beginTransaction();

        // 1. Remove registros antigos do dia
        $del = $conn->prepare("DELETE FROM tb_pontos WHERE id_usuario = ? AND dt_registro = ?");
        $del->execute([$id_u, $data]);

        // 2. Reinsere na nova ordem (apenas se houver hora preenchida)
        $ins = $conn->prepare("INSERT INTO tb_pontos (id_usuario, dt_registro, hr_registro, tp_registro, info_dispositivo) VALUES (?, ?, ?, ?, ?)");
        
        for ($i = 0; $i < count($ordem_tipos); $i++) {
            if (!empty($valores_hrs[$i])) {
                $ins->execute([$id_u, $data, $valores_hrs[$i], $ordem_tipos[$i], "Ajustado por Admin"]);
            }
        }

        // 3. Grava o Log de Ajuste
        $log = $conn->prepare("INSERT INTO tb_ajustes (id_usuario, dt_ponto, motivo, id_admin) VALUES (?, ?, ?, ?)");
        $log->execute([$id_u, $data, $motivo, $id_adm]);

        $conn->commit();
        echo "sucesso";
    } catch (Exception $e) {
        $conn->rollBack();
        echo "erro: " . $e->getMessage();
    }
}