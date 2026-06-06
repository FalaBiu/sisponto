<?php
session_start();
require_once 'conexao.php';

// Segurança: verifica se o usuário está logado
if (!isset($_SESSION['usuario_id'])) {
    header("Location: index.php");
    exit;
}

$id_usuario = $_SESSION['usuario_id'];
$data_atual = date('Y-m-d');
$hora_atual = date('H:i:s');
$dispositivo = $_SERVER['HTTP_USER_AGENT'];

$stmt_usuario = $conn->prepare("SELECT SN_ESTAGIARIO FROM tb_usuarios WHERE id_usuario = :id");
$stmt_usuario->execute(['id' => $id_usuario]);
$sn_estagiario = $stmt_usuario->fetchColumn();
$eh_estagiario = ($sn_estagiario === 'S');

// Definição da ordem oficial
$fluxo = $eh_estagiario
    ? ['entrada', 'saida']
    : ['entrada', 'saida_almoco', 'retorno_almoco', 'saida'];
$max_batidas = count($fluxo);

try {
    // 1. BUSCA QUANTAS BATIDAS JÁ FORAM FEITAS HOJE
    $sql_contagem = "SELECT COUNT(*) FROM tb_pontos WHERE id_usuario = :id AND dt_registro = :data";
    $stmt_contagem = $conn->prepare($sql_contagem);
    $stmt_contagem->execute(['id' => $id_usuario, 'data' => $data_atual]);
    $total_hoje = $stmt_contagem->fetchColumn();

    // 2. VERIFICA SE A JORNADA JÁ FOI CONCLUÍDA
    if ($total_hoje >= $max_batidas) {
        $_SESSION['erro_ponto'] = "✅ <strong>Jornada concluída!</strong><br>Você já realizou as {$max_batidas} marcações de hoje.";
        header("Location: dashboard.php");
        exit;
    }

    // 3. DEFINE O PRÓXIMO TIPO DE REGISTRO COM BASE NA CONTAGEM
    $tipo_correto = $fluxo[$total_hoje];

    // 4. VERIFICAÇÃO DE INTERVALO MÍNIMO (1 HORA)
    $sql_last = "SELECT hr_registro FROM tb_pontos WHERE id_usuario = :id AND dt_registro = :data ORDER BY id_ponto DESC LIMIT 1";
    $stmt_last = $conn->prepare($sql_last);
    $stmt_last->execute(['id' => $id_usuario, 'data' => $data_atual]);
    $ultima_hora = $stmt_last->fetchColumn();

    if ($ultima_hora) {
        $diferenca_segundos = strtotime($hora_atual) - strtotime($ultima_hora);
        $intervalo_minimo = 3600; // 1 hora

        if ($diferenca_segundos < $intervalo_minimo) {
            $minutos_restantes = ceil(($intervalo_minimo - $diferenca_segundos) / 60);
            $_SESSION['erro_ponto'] = "🛑 <strong>Aguarde um momento.</strong><br>Intervalo de segurança ativo. Tente novamente em {$minutos_restantes} minutos.";
            header("Location: dashboard.php");
            exit;
        }
    }

    // 5. INSERÇÃO DO REGISTRO USANDO O TIPO DEFINIDO PELO FLUXO
    $sql = "INSERT INTO tb_pontos (id_usuario, dt_registro, hr_registro, tp_registro, info_dispositivo) 
            VALUES (:id, :data, :hora, :tipo, :disp)";
    $stmt = $conn->prepare($sql);
    $stmt->execute([
        'id'    => $id_usuario,
        'data'  => $data_atual,
        'hora'  => $hora_atual,
        'tipo'  => $tipo_correto, // Aqui entra a lógica do fluxo
        'disp'  => $dispositivo
    ]);

    header("Location: dashboard.php?sucesso=1");
    exit;

} catch (PDOException $e) {
    error_log($e->getMessage());
    die("Erro ao registrar ponto.");
}
?>