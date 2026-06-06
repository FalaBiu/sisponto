<?php
session_start();
require_once 'conexao.php';

if (!isset($_SESSION['usuario_id'])) {
    header("Location: index.php");
    exit;
}

$id_usuario = $_SESSION['usuario_id'];
$hoje = date('Y-m-d');

$stmtUsuario = $conn->prepare("SELECT SN_ESTAGIARIO FROM tb_usuarios WHERE id_usuario = :id");
$stmtUsuario->execute(['id' => $id_usuario]);
$sn_estagiario = $stmtUsuario->fetchColumn();
$eh_estagiario = ($sn_estagiario === 'S');

// 1. Busca as batidas de hoje
$sql = "SELECT tp_registro, hr_registro FROM tb_pontos WHERE id_usuario = :id AND dt_registro = :hoje ORDER BY id_ponto ASC";
$stmt = $conn->prepare($sql);
$stmt->execute(['id' => $id_usuario, 'hoje' => $hoje]);
$batidas_feitas = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 2. Fluxo e Labels
$fluxo = $eh_estagiario
    ? ['entrada', 'saida']
    : ['entrada', 'saida_almoco', 'retorno_almoco', 'saida'];

$labels = $eh_estagiario
    ? [
        'entrada' => 'Entrada',
        'saida'   => 'Saída Final'
    ]
    : [
        'entrada'         => 'Entrada',
        'saida_almoco'    => 'Saída Almoço',
        'retorno_almoco'  => 'Retorno Almoço',
        'saida'           => 'Saída Final'
    ];

$proximo_indice = count($batidas_feitas);
$proximo_passo = $fluxo[$proximo_indice] ?? null;

// 3. Lógica de Cálculo de Saldo (Meta: 8h ou 4h para estagiário)
$saldo_segundos = 0;
$total_trabalhado_texto = "00h 00m";
$cor_saldo = "#666";
$label_status = "Pendente";
$meta_horas = $eh_estagiario ? 4 : 8;

if (count($batidas_feitas) >= 2) {
    $p = [];
    foreach ($batidas_feitas as $b) { $p[$b['tp_registro']] = strtotime($b['hr_registro']); }

    if ($eh_estagiario) {
        $total_segundos = 0;
        if (isset($p['entrada']) && isset($p['saida'])) {
            $total_segundos = $p['saida'] - $p['entrada'];
        }
    } else {
        $segundos_manha = 0;
        $segundos_tarde = 0;

        if (isset($p['entrada']) && isset($p['saida_almoco'])) {
            $segundos_manha = $p['saida_almoco'] - $p['entrada'];
        }

        if (isset($p['retorno_almoco']) && isset($p['saida'])) {
            $segundos_tarde = $p['saida'] - $p['retorno_almoco'];
        }

        $total_segundos = $segundos_manha + $segundos_tarde;
    }

    if ($total_segundos < 0) {
        $total_segundos = 0;
    }
    
    $h = floor($total_segundos / 3600);
    $m = floor(($total_segundos % 3600) / 60);
    $total_trabalhado_texto = sprintf('%02dh %02dm', $h, $m);

    $meta = $meta_horas * 3600;
    $diferenca = $total_segundos - $meta;
    
    $saldo_h = floor(abs($diferenca) / 3600);
    $saldo_m = floor((abs($diferenca) % 3600) / 60);
    $saldo_formatado = sprintf('%02dh %02dm', $saldo_h, $saldo_m);

    if ($diferenca < 0) {
        $cor_saldo = "#dc3545";
        $label_status = "Faltam " . $saldo_formatado;
    } else {
        $cor_saldo = "#28a745";
        $label_status = "Extra " . $saldo_formatado;
    }
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="shortcut icon" href="favicon.ico" type="image/x-icon">
    <title>Painel - SisPonto</title>
    <style>
        body { font-family: sans-serif; background: #f4f4f4; margin: 0; display: flex; flex-direction: column; align-items: center; }
        .header { width: 100%; background: #333; color: white; padding: 15px; text-align: center; box-sizing: border-box; }
        .container { width: 90%; max-width: 400px; margin-top: 20px; text-align: center; }
        .relogio { font-size: 32px; font-weight: bold; color: #333; margin-bottom: 20px; }
        
        .alert { padding: 15px; border-radius: 8px; margin-bottom: 20px; font-size: 14px; line-height: 1.4; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        .alert-error { background: #fff3cd; color: #856404; border: 1px solid #ffeeba; border-left: 6px solid #ffc107; }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; border-left: 6px solid #28a745; }

        .btn-ponto { 
            width: 200px; height: 200px; border-radius: 50%; border: 8px solid #fff;
            background: #28a745; color: white; font-size: 18px; font-weight: bold;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2); cursor: pointer; text-transform: uppercase;
            transition: transform 0.1s; margin-bottom: 20px;
        }
        .btn-ponto:active { transform: scale(0.95); }
        .btn-ponto:disabled { background: #ccc; cursor: not-allowed; box-shadow: none; }
        
        .card-saldo {
            background: white; margin-bottom: 20px; padding: 15px; border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05); display: flex; justify-content: space-around; align-items: center;
        }
        .total-lab { font-size: 11px; color: #888; display: block; text-transform: uppercase; }
        .total-val { font-size: 16px; font-weight: bold; color: #333; }

        .status-hoje { background: white; padding: 15px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); text-align: left; }
        .status-hoje strong { display: block; margin-bottom: 10px; border-bottom: 1px solid #eee; padding-bottom: 5px; }
        .linha-ponto { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #f9f9f9; font-size: 14px; }
        .hr-label { color: #28a745; font-weight: bold; font-family: monospace; }
        
        .btn-action {
            display: block; width: 100%; padding: 15px; margin-top: 10px;
            border: 3px solid #fff; border-radius: 12px; background: #333;
            color: white; font-size: 14px; font-weight: bold; text-decoration: none;
            text-transform: uppercase; box-shadow: 0 4px 6px rgba(0,0,0,0.1); box-sizing: border-box;
        }
    </style>
</head>
<body>

<div class="header">
    <strong><?php echo $_SESSION['usuario_nome']; ?></strong>
</div>

<div class="container">
    
    <?php if (isset($_SESSION['erro_ponto'])): ?>
        <div class="alert alert-error" id="msg-alert"><?php echo $_SESSION['erro_ponto']; unset($_SESSION['erro_ponto']); ?></div>
    <?php endif; ?>

    <?php if (isset($_GET['sucesso'])): ?>
        <div class="alert alert-success" id="msg-alert">✅ <strong>Marcação realizada!</strong></div>
    <?php endif; ?>

    <div class="relogio" id="timer">00:00:00</div>

    <?php if ($proximo_passo): ?>
        <form action="registrar_ponto.php" method="POST">
            <input type="hidden" name="tipo" value="<?php echo $proximo_passo; ?>">
            <button type="submit" class="btn-ponto"><?php echo $labels[$proximo_passo]; ?></button>
        </form>
    <?php else: ?>
        <button class="btn-ponto" disabled>Jornada<br>Concluída</button>
    <?php endif; ?>

    <div class="card-saldo">
        <div>
            <span class="total-lab">Trabalhado</span>
            <span class="total-val"><?php echo $total_trabalhado_texto; ?></span>
        </div>
        <div style="border-left: 1px solid #eee; height: 30px;"></div>
        <div>
            <span class="total-lab">Status (Meta <?php echo $meta_horas; ?>h)</span>
            <span class="total-val" style="color: <?php echo $cor_saldo; ?>;"><?php echo $label_status; ?></span>
        </div>
    </div>

    <div class="status-hoje">
        <strong>Registros de Hoje:</strong>
        <?php if (empty($batidas_feitas)): ?>
            <p style="text-align: center; color: #999; font-size: 12px;">Nenhuma marcação realizada.</p>
        <?php else: ?>
            <?php foreach ($batidas_feitas as $b): ?>
                <div class="linha-ponto">
                    <span>✅ <?php echo strtoupper(str_replace('_', ' ', $b['tp_registro'])); ?></span>
                    <span class="hr-label"><?php echo date('H:i', strtotime($b['hr_registro'])); ?></span>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    
    <a href="menu.php" class="btn-action">Menu Principal</a>
</div>

<script>
    function atualizarRelogio() {
        document.getElementById('timer').innerText = new Date().toLocaleTimeString('pt-br');
    }
    setInterval(atualizarRelogio, 1000);
    atualizarRelogio();

    setTimeout(() => {
        const alert = document.getElementById('msg-alert');
        if (alert) {
            alert.style.transition = "opacity 0.5s";
            alert.style.opacity = "0";
            setTimeout(() => alert.remove(), 500);
        }
    }, 5000);
</script>

</body>
</html>