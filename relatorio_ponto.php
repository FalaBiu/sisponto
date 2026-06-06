<?php
session_start();
require_once 'conexao.php';

if (!isset($_SESSION['usuario_id'])) {
    header("Location: index.php");
    exit;
}

$id_usuario = $_SESSION['usuario_id'];

// Define datas padrão (do dia 1 até hoje) se não houver filtro
$data_inicio = $_POST['data_inicio'] ?? date('Y-m-01');
$data_fim    = $_POST['data_fim'] ?? date('Y-m-d');

// Consulta os pontos do usuário logado no período
$sql = "SELECT dt_registro, hr_registro, tp_registro 
        FROM tb_pontos 
        WHERE id_usuario = :id 
        AND dt_registro BETWEEN :inicio AND :fim 
        ORDER BY dt_registro DESC, hr_registro ASC";

$stmt = $conn->prepare($sql);
$stmt->execute([
    'id'     => $id_usuario,
    'inicio' => $data_inicio,
    'fim'    => $data_fim
]);
$registros = $stmt->fetchAll();

// Labels amigáveis para o tipo de ponto
$labels = [
    'entrada'        => 'Entrada',
    'saida_almoco'   => 'S. Almoço',
    'retorno_almoco' => 'R. Almoço',
    'saida'          => 'Saída Final'
];
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <link rel="shortcut icon" href="favicon.ico" type="image/x-icon">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Meus Registros - SisPonto</title>
    <style>
        body { font-family: sans-serif; background: #f4f4f4; margin: 0; padding: 15px; }
        .container { max-width: 600px; margin: auto; background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h2 { text-align: center; color: #333; font-size: 20px; }
        
        .filtro { background: #eee; padding: 15px; border-radius: 5px; margin-bottom: 20px; }
        .filtro form { display: flex; flex-direction: column; gap: 10px; }
        .filtro label { font-size: 12px; font-weight: bold; color: #555; }
        .filtro input { padding: 8px; border: 1px solid #ccc; border-radius: 4px; }
        .btn-filtrar { background: #007bff; color: #fff; border: none; padding: 10px; font-weight: bold; cursor: pointer; border-radius: 4px; }

        table { width: 100%; border-collapse: collapse; margin-top: 10px; font-size: 14px; }
        th { background: #333; color: #fff; padding: 10px; text-align: left; }
        td { padding: 10px; border-bottom: 1px solid #eee; }
        tr:nth-child(even) { background: #fafafa; }
        
        .badge { padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: bold; color: #fff; text-transform: uppercase; }
        .bg-entrada { background: #28a745; }
        .bg-almoco { background: #ffc107; color: #000; }
        .bg-saida { background: #dc3545; }

        .voltar { display: block; text-align: center; margin-top: 20px; text-decoration: none; color: #007bff; font-weight: bold; font-size: 14px; }
    </style>
</head>
<body>

<div class="container">
    <h2>Meus Registros</h2>

    <div class="filtro">
        <form method="POST">
            <div>
                <label>De:</label>
                <input type="date" name="data_inicio" value="<?php echo $data_inicio; ?>">
            </div>
            <div>
                <label>Até:</label>
                <input type="date" name="data_fim" value="<?php echo $data_fim; ?>">
            </div>
            <button type="submit" class="btn-filtrar">FILTRAR PERÍODO</button>
        </form>
    </div>

    <table>
        <thead>
            <tr>
                <th>Data</th>
                <th>Hora</th>
                <th>Tipo</th>
            </tr>
        </thead>
        <tbody>
            <?php if (count($registros) > 0): ?>
                <?php foreach ($registros as $reg): ?>
                    <?php 
                        $classe = 'bg-entrada';
                        if (strpos($reg['tp_registro'], 'almoco') !== false) $classe = 'bg-almoco';
                        if ($reg['tp_registro'] == 'saida') $classe = 'bg-saida';
                    ?>
                    <tr>
                        <td><?php echo date('d/m/y', strtotime($reg['dt_registro'])); ?></td>
                        <td style="font-family: monospace; font-weight: bold;">
                            <?php echo date('H:i', strtotime($reg['hr_registro'])); ?>
                        </td>
                        <td>
                            <span class="badge <?php echo $classe; ?>">
                                <?php echo $labels[$reg['tp_registro']]; ?>
                            </span>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="3" style="text-align:center; color:#999; padding:20px;">Nenhum registro encontrado.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>

    <a href="menu.php" class="voltar">← VOLTAR AO MENU</a>
</div>

</body>
</html>