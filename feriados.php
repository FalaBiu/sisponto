<?php
session_start();
require_once 'conexao.php';
if ($_SESSION['usuario_nivel'] !== 'admin') { header("Location: menu.php"); exit; }

// Garantir que o enum possui a opção de meio período
try {
    $conn->exec("ALTER TABLE tb_feriados MODIFY tipo ENUM('federal','estadual','municipal','facultativo','meio_periodo') DEFAULT 'federal'");
} catch (Exception $e) {
    // Se já estiver no formato esperado, ignora
}

// Processamento de POST para inserir/editar
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = $_POST['data_feriado'] ?? '';
    $descricao = trim($_POST['descricao'] ?? '');
    $tipo = $_POST['tipo'] ?? '';
    if (!in_array($tipo, ['feriado','facultativo','meio_periodo'])) {
        // garantido valor válido, padrão feriado
        $tipo = 'feriado';
    }

    if ($data && $descricao) {
        $stmt = $conn->prepare("INSERT INTO tb_feriados (data_feriado, descricao, tipo) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE descricao = ?, tipo = ?, ativo = 1");
        $stmt->execute([$data, $descricao, $tipo, $descricao, $tipo]);
    }
    header('Location: feriados.php');
    exit;
}

// Exclusão
if (isset($_GET['delete'])) {
    $stmt = $conn->prepare("DELETE FROM tb_feriados WHERE id_feriado = ?");
    $stmt->execute([$_GET['delete']]);
    header('Location: feriados.php');
    exit;
}

$feriados = $conn->query("SELECT * FROM tb_feriados ORDER BY data_feriado DESC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Gerenciar Feriados / Abonos</title>
    <style>
        body{font-family:sans-serif;margin:20px;}
        table{border-collapse:collapse;width:100%;margin-top:20px;}
        th,td{border:1px solid #ccc;padding:8px;text-align:left;}
        .btn{padding:6px 12px;background:#007bff;color:#fff;text-decoration:none;border-radius:4px;}
        .btn-danger{background:#dc3545;}
        form input, form select{padding:6px;width:100%;box-sizing:border-box;margin-bottom:10px;}
    </style>
</head>
<body>
    <h2>Cadastro de Feriados / Abonos</h2>
    <form method="POST">
        <label>Data:</label>
        <input type="date" name="data_feriado" required>
        <label>Descrição:</label>
        <input type="text" name="descricao" required>
        <label>Tipo:</label>
        <select name="tipo">
            <option value="feriado" selected>Feriado</option>
            <option value="facultativo">Ponto Facultativo</option>
            <option value="meio_periodo">Abono Meio Período</option>
        </select>
        <button type="submit" class="btn">Salvar</button>
    </form>

    <h3>Feriados Cadastrados</h3>
    <table>
        <thead><tr><th>Data</th><th>Descrição</th><th>Tipo</th><th>Ações</th></tr></thead>
        <tbody>
            <?php foreach($feriados as $f): ?>
                <tr>
                    <td><?=date('d/m/Y', strtotime($f['data_feriado']))?></td>
                    <td><?=htmlspecialchars($f['descricao'])?></td>
                    <td><?=htmlspecialchars($f['tipo'])?></td>
                    <td><a href="?delete=<?=$f['id_feriado']?>" class="btn btn-danger" onclick="return confirm('Remover?')">Excluir</a></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <p><a href="menu.php" class="btn">Voltar ao menu</a></p>
</body>
</html>
