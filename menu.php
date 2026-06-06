<?php
session_start();
require_once 'conexao.php';

// Proteção: Se não estiver logado, volta para o login
if (!isset($_SESSION['usuario_id'])) {
    header("Location: index.php");
    exit;
}

$nome_usuario = $_SESSION['usuario_nome'];
$nivel_acesso = $_SESSION['usuario_nivel']; // 'admin' ou 'colaborador'
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="shortcut icon" href="favicon.ico" type="image/x-icon">
    <title>Menu Principal - SisPonto</title>
    <style>
        body { font-family: sans-serif; background: #f4f4f4; margin: 0; display: flex; flex-direction: column; align-items: center; }
        .header { width: 100%; background: #333; color: white; padding: 15px; text-align: center; box-sizing: border-box; }
        .container { width: 90%; max-width: 400px; margin-top: 30px; }
        
        .menu-btn {
            display: block;
            width: 100%;
            padding: 20px;
            margin-bottom: 15px;
            border: none;
            border-radius: 8px;
            background: #007bff;
            color: white;
            font-size: 18px;
            font-weight: bold;
            text-decoration: none;
            text-align: center;
            box-sizing: border-box;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }

        .menu-btn:active { transform: scale(0.98); }

        /* Estilo para botão desabilitado */
        .btn-disabled {
            background: #ccc !important;
            color: #888 !important;
            cursor: not-allowed;
            pointer-events: none;
        }

        .btn-ponto { background: #28a745; }
        .btn-admin { background: #5b03ff; }
        .btn-gestao { background: #17a2b8; } /* Cor para o Relatório de Gestão */
        
        .footer { margin-top: 40px; text-align: center; width: 100%; }
        .logout { background: #ff0303; margin-top: 20px; }
        
        .admin-label { 
            font-size: 11px; 
            color: #666; 
            font-weight: bold; 
            text-align: center; 
            display: block; 
            margin-bottom: 10px;
            text-transform: uppercase;
        }
    </style>
</head>
<body>

<div class="header">
    <strong>MENU PRINCIPAL</strong><br>
    <small>Olá, <?php echo $nome_usuario; ?></small>
</div>

<div class="container">
    
    <a href="dashboard.php" class="menu-btn btn-ponto">
        REGISTRAR PONTO
    </a>

    <a href="relatorio_ponto.php" class="menu-btn">
        MEUS REGISTROS
    </a>

    <?php if ($nivel_acesso === 'admin'): ?>
        <hr style="border: 0; border-top: 1px solid #ddd; margin: 25px 0;">
        <span class="admin-label">Administração - Almoxarifado</span>
        
        <a href="cadastro_usuario.php" class="menu-btn btn-admin">
            CADASTRO DE USUÁRIO
        </a>

        <a href="relatorio_gestao.php" class="menu-btn btn-gestao">
            GESTÃO DE PONTOS (ESPELHO)
        </a>

        <!-- link para gestão de feriados/abonos -->
        <a href="feriados.php" class="menu-btn btn-admin">
            GESTÃO DE FERIADOS / ABONOS
        </a>
    <?php else: ?>
        <a href="#" class="menu-btn btn-disabled">
            CADASTRO DE USUÁRIO (BLOQUEADO)
        </a>
        <a href="#" class="menu-btn btn-disabled">
            GESTÃO DE FERIADOS / ABONOS (BLOQUEADO)
        </a>
    <?php endif; ?>

    <div class="footer">
        <a href="logout.php" class="menu-btn logout">SAIR DO SISTEMA</a>
    </div>
</div>

</body>
</html>