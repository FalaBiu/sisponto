<?php
session_start();
require_once 'conexao.php';

$erro = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $login = $_POST['login'];
    $senha = $_POST['senha'];

    $sql = "SELECT id_usuario, nome_completo, senha_usuario, nivel_acesso, SN_ESTAGIARIO FROM tb_usuarios WHERE login_usuario = :login AND status_usuario = 1";
    $stmt = $conn->prepare($sql);
    $stmt->execute(['login' => $login]);
    $usuario = $stmt->fetch();

    if ($usuario && password_verify($senha, $usuario['senha_usuario'])) {
        $_SESSION['usuario_id'] = $usuario['id_usuario'];
        $_SESSION['usuario_nome'] = $usuario['nome_completo'];
        $_SESSION['usuario_nivel'] = $usuario['nivel_acesso'];
        $_SESSION['usuario_estagiario'] = (($usuario['SN_ESTAGIARIO'] ?? 'N') === 'S') ? 'S' : 'N';
        
        header("Location: dashboard.php");
        exit;
    } else {
        $erro = "Login ou senha inválidos.";
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <link rel="shortcut icon" href="favicon.ico" type="image/x-icon">
    <title>SisPonto - Login</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f4f4f4; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
        .login-container { background: #fff; padding: 30px; border-radius: 8px; box-shadow: 0 4px 10px rgba(0,0,0,0.1); width: 90%; max-width: 400px; }
        h2 { text-align: center; color: #333; margin-bottom: 25px; }
        .input-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; color: #666; }
        input { width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box; font-size: 16px; }
        button { width: 100%; padding: 12px; border: none; border-radius: 4px; background: #28a745; color: white; font-size: 18px; font-weight: bold; cursor: pointer; margin-top: 10px; }
        .erro { color: red; text-align: center; margin-bottom: 15px; font-size: 14px; }
    </style>
</head>
<body>

<div class="login-container">
    <h2>SisPonto - Almoxarifado</h2>
    
    <?php if ($erro): ?>
        <div class="erro"><?php echo $erro; ?></div>
    <?php endif; ?>

    <form method="POST">
        <div class="input-group">
            <label>Login</label>
            <input type="text" name="login" required placeholder="Seu usuário">
        </div>
        <div class="input-group">
            <label>Senha</label>
            <input type="password" name="senha" required placeholder="******">
        </div>
        <button type="submit">ENTRAR</button>
    </form>
</div>

</body>
</html>