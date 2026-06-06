<?php
require_once 'conexao.php';

$mensagem = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['btn_cadastrar'])) {
    $nome   = mb_strtoupper($_POST['nome'] ?? '', 'UTF-8');
    $funcao = mb_strtoupper($_POST['funcao'] ?? '', 'UTF-8');
    $login  = mb_strtoupper($_POST['login'] ?? '', 'UTF-8');
    $senha_post = $_POST['senha'] ?? '';
    $nivel  = $_POST['nivel'] ?? 'colaborador';
    $sn_estagiario = isset($_POST['sn_estagiario']) ? 'S' : 'N';
    
    $h_ent   = $_POST['hr_entrada'];
    $h_alm_s = $_POST['hr_saida_almoco'];
    $h_alm_r = $_POST['hr_retorno_almoco'];
    $h_sai   = $_POST['hr_saida'];

    try {
        $conn->beginTransaction();

        // 1. Verifica se o usuário já existe pela matrícula
        $check = $conn->prepare("SELECT id_usuario FROM tb_usuarios WHERE login_usuario = ?");
        $check->execute([$login]);
        $usuario_existente = $check->fetch();

        if ($usuario_existente) {
            // ATUALIZAÇÃO
            $id_u = $usuario_existente['id_usuario'];
            
            // Se a senha foi preenchida, atualiza ela também, senão mantém a antiga
            if (!empty($senha_post)) {
                $senha_hash = password_hash($senha_post, PASSWORD_DEFAULT);
                $sqlU = "UPDATE tb_usuarios SET nome_completo=?, funcao=?, senha_usuario=?, nivel_acesso=?, SN_ESTAGIARIO=? WHERE id_usuario=?";
                $paramsU = [$nome, $funcao, $senha_hash, $nivel, $sn_estagiario, $id_u];
            } else {
                $sqlU = "UPDATE tb_usuarios SET nome_completo=?, funcao=?, nivel_acesso=?, SN_ESTAGIARIO=? WHERE id_usuario=?";
                $paramsU = [$nome, $funcao, $nivel, $sn_estagiario, $id_u];
            }
            $stmtU = $conn->prepare($sqlU);
            $stmtU->execute($paramsU);

            // Atualiza horários (limpa e insere novos para simplificar)
            $conn->prepare("DELETE FROM tb_horarios WHERE id_usuario = ?")->execute([$id_u]);
            $mensagem_texto = "DADOS ATUALIZADOS!";
        } else {
            // NOVO CADASTRO
            $senha_hash = password_hash($senha_post, PASSWORD_DEFAULT);
            $sqlI = "INSERT INTO tb_usuarios (nome_completo, funcao, login_usuario, senha_usuario, nivel_acesso, SN_ESTAGIARIO) VALUES (?, ?, ?, ?, ?, ?)";
            $conn->prepare($sqlI)->execute([$nome, $funcao, $login, $senha_hash, $nivel, $sn_estagiario]);
            $id_u = $conn->lastInsertId();
            $mensagem_texto = "CADASTRADO COM SUCESSO!";
        }

        // 2. Insere/Reinsere a Jornada (Seg a Sex)
        $sqlH = "INSERT INTO tb_horarios (id_usuario, hr_entrada, hr_saida_almoco, hr_retorno_almoco, hr_saida, dia_semana) VALUES (?, ?, ?, ?, ?, ?)";
        $stmtH = $conn->prepare($sqlH);
        for ($i = 1; $i <= 5; $i++) {
            $stmtH->execute([$id_u, $h_ent, $h_alm_s, $h_alm_r, $h_sai, $i]);
        }

        $conn->commit();
        $mensagem = "<div style='color:green; font-weight:bold; font-size:12px; padding:5px; text-align:center;'>$mensagem_texto</div>";
    } catch (Exception $e) {
        $conn->rollBack();
        $mensagem = "<div style='color:red; font-weight:bold; font-size:12px; padding:5px; text-align:center;'>ERRO: ".$e->getMessage()."</div>";
    }
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <link rel="shortcut icon" href="favicon.ico" type="image/x-icon">
    <title>Cadastro/Edição - SisPonto</title>
    <style>
        body { font-family: sans-serif; background: #f4f4f4; padding: 10px; display: flex; justify-content: center; margin: 0; }
        .card { max-width: 400px; width: 100%; background: #fff; padding: 15px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; margin: 8px 0; }
        input, select, button { width: 100%; padding: 10px; margin: 3px 0; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; font-size: 14px; }
        label { font-size: 11px; color: #555; font-weight: bold; display: flex; justify-content: space-between; align-items: center; margin-top: 6px; }
        .btn-busca { color: #007bff; cursor: pointer; text-decoration: underline; font-size: 10px; }
        button { background: #28a745; color: white; border: none; font-size: 15px; font-weight: bold; cursor: pointer; margin-top: 10px; padding: 12px; }
        h2 { text-align: center; margin: 0 0 10px 0; color: #333; font-size: 18px; }
        input[type="text"] { text-transform: uppercase; }
        .modal { display: none; position: fixed; z-index: 100; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); }
        .modal-content { background: #fff; margin: 15% auto; padding: 15px; width: 85%; border-radius: 8px; }
        .lista-busca { max-height: 150px; overflow-y: auto; margin-top: 10px; border: 1px solid #eee; }
        .item-busca { padding: 8px; border-bottom: 1px solid #eee; cursor: pointer; font-size: 13px; }
    </style>
</head>
<body>

<div class="card">
    <h2>Gerenciar Colaborador</h2>
    <?php echo $mensagem; ?>
    
    <form method="POST">
        <label>NOME COMPLETO <span class="btn-busca" onclick="abrirModal('nome')">[BUSCAR]</span></label>
        <input type="text" name="nome" id="input_nome" required oninput="this.value = this.value.toUpperCase()">
        
        <label>FUNÇÃO / CARGO</label>
        <input type="text" name="funcao" id="input_funcao" oninput="this.value = this.value.toUpperCase()">

        <label>MATRÍCULA <span class="btn-busca" onclick="abrirModal('matricula')">[BUSCAR]</span></label>
        <input type="text" name="login" id="input_matricula" required oninput="this.value = this.value.toUpperCase()">
        
        <div class="grid">
            <div>
                <label>SENHA (VAZIO MANTÉM)</label>
                <input type="password" name="senha">
            </div>
            <div>
                <label>NÍVEL ACESSO</label>
                <select name="nivel" id="input_nivel">
                    <option value="colaborador">COLABORADOR</option>
                    <option value="admin">ADMINISTRADOR</option>
                </select>
            </div>
        </div>

        <label style="justify-content: flex-start; gap: 8px;">
            <input type="checkbox" name="sn_estagiario" id="input_estagiario" value="S" style="width:auto; margin:0;">
            Estagiário
        </label>

        <div style="margin-top:10px; border-top: 1px solid #eee; padding-top:5px;">
            <label style="color:#28a745; justify-content: center;">JORNADA (SEG A SEX)</label>
            <div class="grid">
                <div><label>ENTRADA</label><input type="time" name="hr_entrada" id="h1" value="08:00" required></div>
                <div><label>S. ALMOÇO</label><input type="time" name="hr_saida_almoco" id="h2" value="12:00"></div>
                <div><label>R. ALMOÇO</label><input type="time" name="hr_retorno_almoco" id="h3" value="13:00"></div>
                <div><label>SAÍDA FINAL</label><input type="time" name="hr_saida" id="h4" value="18:00" required></div>
            </div>
        </div>
        
        <button type="submit" name="btn_cadastrar">SALVAR</button>
    </form>
    <p style="text-align:center; margin-top:10px;"><a href="menu.php" style="text-decoration:none; color:#007bff; font-size:13px;">Menu Principal</a></p>
</div>

<div id="modalBusca" class="modal">
    <div class="modal-content">
        <h3 style="margin:0; font-size:16px;" id="tituloModal">Buscar</h3>
        <input type="text" id="campoBusca" placeholder="Filtrar..." onkeyup="filtrar()">
        <div id="resultadoBusca" class="lista-busca"></div>
        <button onclick="fecharModal()" style="background:#666; margin-top:10px; padding:8px;">FECHAR</button>
    </div>
</div>

<script>
    let tipoBusca = '';
    // Pegando tudo do usuário, inclusive os horários padrão
    const usuarios = <?php 
        $sql = "SELECT u.*, h.hr_entrada, h.hr_saida_almoco, h.hr_retorno_almoco, h.hr_saida 
                FROM tb_usuarios u 
                LEFT JOIN tb_horarios h ON u.id_usuario = h.id_usuario AND h.dia_semana = 1";
        $q = $conn->query($sql);
        echo json_encode($q->fetchAll(PDO::FETCH_ASSOC));
    ?>;

    function abrirModal(tipo) {
        tipoBusca = tipo;
        document.getElementById('modalBusca').style.display = 'block';
        document.getElementById('campoBusca').value = '';
        filtrar();
    }

    function fecharModal() { document.getElementById('modalBusca').style.display = 'none'; }

    function filtrar() {
        const termo = document.getElementById('campoBusca').value.toUpperCase();
        const lista = document.getElementById('resultadoBusca');
        lista.innerHTML = '';
        usuarios.forEach(u => {
            const valorBusca = tipoBusca === 'nome' ? u.nome_completo : u.login_usuario;
            if (valorBusca.includes(termo)) {
                const div = document.createElement('div');
                div.className = 'item-busca';
                div.innerText = u.nome_completo + ' (' + u.login_usuario + ')';
                div.onclick = () => {
                    document.getElementById('input_nome').value = u.nome_completo;
                    document.getElementById('input_funcao').value = u.funcao || '';
                    document.getElementById('input_matricula').value = u.login_usuario;
                    document.getElementById('input_nivel').value = u.nivel_acesso;
                    document.getElementById('input_estagiario').checked = (u.SN_ESTAGIARIO === 'S');
                    // Preenche os horários
                    document.getElementById('h1').value = u.hr_entrada;
                    document.getElementById('h2').value = u.hr_saida_almoco;
                    document.getElementById('h3').value = u.hr_retorno_almoco;
                    document.getElementById('h4').value = u.hr_saida;
                    fecharModal();
                };
                lista.appendChild(div);
            }
        });
    }
</script>

</body>
</html>