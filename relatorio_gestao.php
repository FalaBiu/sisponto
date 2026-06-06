<?php
session_start();
require_once 'conexao.php';
if ($_SESSION['usuario_nivel'] !== 'admin') { header("Location: menu.php"); exit; }

$id_busca = $_GET['id_usuario'] ?? '';
$dt_ini   = $_GET['data_ini'] ?? date('Y-m-01');
$dt_fim   = $_GET['data_fim'] ?? date('Y-m-d');

$colaborador = null;
$pontos_agrupados = [];
$feriados = [];

if ($id_busca) {
    // Busca dados do colaborador
    $stmt = $conn->prepare("SELECT * FROM tb_usuarios WHERE id_usuario = ?");
    $stmt->execute([$id_busca]);
    $colaborador = $stmt->fetch();

    // Busca horários configurados
    $stmtH = $conn->prepare("SELECT * FROM tb_horarios WHERE id_usuario = ? AND dia_semana = 1");
    $stmtH->execute([$id_busca]);
    $jornada = $stmtH->fetch();

    // Busca pontos no período
    $stmtP = $conn->prepare("SELECT * FROM tb_pontos WHERE id_usuario = ? AND dt_registro BETWEEN ? AND ? ORDER BY dt_registro ASC, hr_registro ASC");
    $stmtP->execute([$id_busca, $dt_ini, $dt_fim]);
    while ($row = $stmtP->fetch()) {
        $pontos_agrupados[$row['dt_registro']][] = $row;
    }
}

$eh_estagiario = ($colaborador && (($colaborador['SN_ESTAGIARIO'] ?? 'N') === 'S'));

// Busca feriados / abonos no período
$stmtF = $conn->prepare("SELECT data_feriado, descricao, tipo FROM tb_feriados WHERE data_feriado BETWEEN ? AND ? AND ativo = 1");
$stmtF->execute([$dt_ini, $dt_fim]);
while ($row = $stmtF->fetch()) {
    $feriados[$row['data_feriado']] = ['descricao' => $row['descricao'], 'tipo' => $row['tipo']];
}

// Lista de usuários para o select
$usuarios = $conn->query("SELECT id_usuario, nome_completo FROM tb_usuarios ORDER BY nome_completo ASC")->fetchAll();

// Nomes dos dias da semana
$dias_semana = ['Domingo', 'Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado'];
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <link rel="shortcut icon" href="favicon.ico" type="image/x-icon">
    <title>Gestão de Pontos - Almoxarifado Central</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
    <style>
        body { font-family: 'Segoe UI', sans-serif; background: #f0f2f5; margin: 0; padding: 20px; line-height: 1.2; }
        .no-print { background: #fff; padding: 20px; border-radius: 8px; max-width: 1000px; margin: 0 auto 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        
        /* Estilo do Espelho de Ponto (Impressão) */
        .espelho-container { background: #fff; padding: 15px 20px; max-width: 1000px; margin: auto; border: 1px solid #ddd; }
        .header-relatorio { text-align: center; border-bottom: 2px solid #000; padding-bottom: 5px; margin-bottom: 10px; }
        .header-relatorio h2 { margin: 0; font-size: 14px; }
        .header-relatorio p { margin: 2px 0; font-size: 11px; }
        .info-colaborador { display: grid; grid-template-columns: 1fr 1fr; gap: 5px; margin-bottom: 10px; font-size: 10px; line-height: 1.3; }
        
        table { width: 100%; border-collapse: collapse; margin-top: 5px; }
        th, td { border: 1px solid #333; padding: 4px 3px; text-align: center; font-size: 9px; line-height: 1.2; }
        th { background: #f2f2f2; font-weight: bold; }
        th:nth-child(7), td:nth-child(7) { width: 45px; }
        tfoot td { border: 1px solid #333; padding: 4px 3px; font-size: 9px; font-weight: bold; }
        
        /* Estilo para linhas de meio período */
        tr.meio-periodo td { background-color: #fffacd; }
        
        /* Estilo para feriados */
        tr.feriado td { background-color: #e8f5e9; }
        tr.feriado td { color: #2e7d32; }

        /* Acordeon / Ajuste */
        .dia-item { border: 1px solid #ccc; margin-bottom: 5px; background: #fff; border-radius: 4px; }
        .dia-header { padding: 10px; cursor: pointer; display: flex; justify-content: space-between; background: #e9ecef; }
        .dia-corpo { padding: 15px; display: none; border-top: 1px solid #ccc; }
        .drag-item { background: #f8f9fa; border: 1px dashed #007bff; padding: 10px; margin: 5px 0; cursor: move; display: flex; align-items: center; gap: 10px; border-radius: 4px; }
        
        .btn { padding: 8px 15px; cursor: pointer; border: none; border-radius: 4px; font-weight: bold; }
        .btn-save { background: #28a745; color: #fff; }
        .btn-print { background: #17a2b8; color: #fff; margin-bottom: 10px; }

        @media print {
            .no-print, .btn-ajuste, .btn-print, footer { display: none !important; }
            body { background: #fff; padding: 0; margin: 0; line-height: 1.2; }
            .espelho-container { border: none; width: 100%; padding: 5px; margin: 0; box-sizing: border-box; }
            .header-relatorio { padding-bottom: 3px; margin-bottom: 8px; }
            th, td { padding: 3px 2px; font-size: 9px; }
            table { width: 100%; }
            tr.meio-periodo td { background-color: #fffacd; }
        }

        @media (max-width: 600px) {
            .info-colaborador { grid-template-columns: 1fr; }
            .no-print { padding: 10px; }
            
            /* Ajuste responsivo para filtros no celular */
            .filtros-wrapper { flex-wrap: wrap; }
            .filtros-wrapper input { flex: 1 1 100%; } /* Datas ocupam 100% da largura */
            .filtros-wrapper .btn { flex: 1; } /* Botões dividem a linha de baixo */

            /* Tabela responsiva */
            table thead { display: none; }
            table tbody, table tr, table td { display: block; }
            table tr { 
                margin-bottom: 15px; 
                border: 1px solid #ddd;
                border-radius: 4px;
            }
            table tr[id^="row-ajuste-"] { border: none; margin-bottom: 5px; }
            table td {
                padding: 10px 10px 10px 45%;
                text-align: right;
                position: relative;
                border-bottom: 1px solid #f0f0f0;
            }
            table tr:not([id^="row-ajuste-"]) td:last-of-type { border-bottom: none; }
            table td::before {
                content: attr(data-label);
                position: absolute;
                left: 10px;
                text-align: left;
                font-weight: bold;
                color: #555;
            }
        }

        .filtros-wrapper { display: flex; gap: 10px; }
        .filtros-wrapper input { flex: 1; padding: 8px; }
        .filtros-wrapper .btn-custom { background: #333; color: #fff; text-decoration: none; display: flex; align-items: center; justify-content: center; }
    </style>
</head>
<body>

<div class="no-print">
    <h3>🔍 Filtro de Gestão</h3>
    <form method="GET">
        <select name="id_usuario" required style="padding: 8px; width: 100%; margin-bottom:10px;">
            <option value="">Selecione o Colaborador</option>
            <?php foreach($usuarios as $u): ?>
                <option value="<?=$u['id_usuario']?>" <?=$id_busca==$u['id_usuario']?'selected':''?>><?=$u['nome_completo']?></option>
            <?php endforeach; ?>
        </select>
        <div class="filtros-wrapper">
            <input type="date" name="data_ini" value="<?=$dt_ini?>">
            <input type="date" name="data_fim" value="<?=$dt_fim?>">
            <button type="submit" class="btn btn-custom">BUSCAR</button>
            <a href="menu.php" class="btn btn-custom">RETORNAR AO MENU</a>
        </div>
    </form>
</div>

<?php if ($colaborador): ?>
<div style="text-align: center;" class="no-print">
    <button class="btn btn-print" onclick="window.print()">🖨️ IMPRIMIR ESPELHO DE PONTO</button>
</div>

<div class="espelho-container">
    <div class="header-relatorio">
        <h2 style="margin:0;">ALMOXARIFADO CENTRAL</h2>
        <p style="margin:5px 0;">ESPELHO DE REGISTRO DE PONTO</p>
    </div>

    <div class="info-colaborador">
        <div><strong>Colaborador:</strong> <?=$colaborador['nome_completo']?></div>
        <div><strong>Função:</strong> <?=$colaborador['funcao']?></div>
        <div><strong>Matrícula:</strong> <?=$colaborador['login_usuario']?></div>
        <div><strong>Jornada:</strong> <?=$jornada['hr_entrada']?> às <?=$jornada['hr_saida']?> (<?=$eh_estagiario ? '4h / Estagiário' : '8h / Padrão'?>)</div>
    </div>

    <div style="background: #fff9e6; border-left: 4px solid #ff9800; padding: 10px; margin-bottom: 10px; font-size: 10px; border-radius: 3px;">
        <strong>ℹ️ Legenda:</strong> 
        <ul style="margin: 5px 0; padding-left: 20px;">
            <li>🎉 <span style="color: #2e7d32;">Feriados</span> (federal, estadual, municipal etc – fundo VERDE) - Não descontam horas do saldo; aparecem em <strong>Observação</strong></li>
            <li>💼 <span style="color: #2e7d32;">Ponto facultativo</span> (fundo VERDE) - igual feriado</li>
            <li>⚠️ <span style="color: #ff9800;">Abono meio período</span> (fundo AMARELO) - funcionário deve 4h, saldo calculado sobre 4h; observação mostra o tipo</li>
            <li>🎓 <span style="color: #333;">Estagiário</span> - saldo calculado com jornada padrão de 4h/dia e sem intervalo de almoço</li>
            <li><span style="color: red;">Saldo Negativo</span> - Colaborador deve essas horas</li>
            <li><span style="color: green;">Saldo Positivo</span> - Colaborador tem horas positivas</li>
        </ul>
    </div>

    <table>
        <thead>
            <tr>
                <th>DATA</th>
                <th>DIA</th>
                <th>ENTRADA</th>
                <th>S. ALMOÇO</th>
                <th>R. ALMOÇO</th>
                <th>SAÍDA FINAL</th>
                <th>SALDO</th>
                <th>OBSERVAÇÃO</th>
                <th class="no-print">AÇÕES</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $periodo = new DatePeriod(new DateTime($dt_ini), new DateInterval('P1D'), (new DateTime($dt_fim))->modify('+1 day'));
            $saldo_total_minutos = 0;
            
            foreach ($periodo as $data):
                $d = $data->format('Y-m-d');
                $pts = $pontos_agrupados[$d] ?? [];
                
                // Obtém dia da semana (0=domingo, 6=sábado)
                $dia_semana = $data->format('w');
                $nome_dia = $dias_semana[$dia_semana];
                
                // Verifica se há registro de feriado/abono para a data
                $eh_feriado = isset($feriados[$d]);
                $feriado_tipo = $eh_feriado ? $feriados[$d]['tipo'] : null;
                $feriado_desc = $eh_feriado ? $feriados[$d]['descricao'] : null;
                
                // Verifica apenas final de semana (0=Dom, 6=Sab)
                $eh_fim_semana = ($dia_semana == 0 || $dia_semana == 6);
                // dia não útil inclui fim de semana ou feriado/facultativo (mas não abono meio período)
                $eh_dia_nao_util = ($eh_fim_semana || ($eh_feriado && $feriado_tipo !== 'meio_periodo'));
                
                // Mapeia os pontos para as colunas certas
                $colunas = ['entrada'=>'', 'saida_almoco'=>'', 'retorno_almoco'=>'', 'saida'=>''];
                foreach($pts as $p) { $colunas[$p['tp_registro']] = date('H:i', strtotime($p['hr_registro'])); }
                
                // Calcula saldo do dia
                $observacao = '';
                $saldo_dia = '-';
                $minutos_saldo = 0;
                $eh_meio_periodo = false;
                $tolerancia_atraso_min = 10;

                // Se for feriado / facultativo sem abono meio período
                if ($eh_feriado && $feriado_tipo !== 'meio_periodo') {
                    // qualquer tipo diferente de abono virá apenas como "Feriado"
                    $observacao = 'Feriado';
                    // saldo_dia fica vazio para não mostrar texto
                    $saldo_dia = '';
                } else {
                    // cálculo de jornada normal ou abono meio período
                    $jornada_padrao = $eh_estagiario ? 4 : 8;
                    $jornada_esperada = ($feriado_tipo === 'meio_periodo') ? 4 : $jornada_padrao;
                    if ($eh_feriado && $feriado_tipo === 'meio_periodo') {
                        $observacao = 'Abono meio período';
                    }

                    // Se for dia útil sem marcação, considera jornada esperada negativa
                    if (!$eh_dia_nao_util && !$colunas['entrada']) {
                        $saldo_dia = '-' . str_pad($jornada_esperada, 2, '0', STR_PAD_LEFT) . ':00';
                        $minutos_saldo = -($jornada_esperada * 60);
                        $saldo_total_minutos += $minutos_saldo;
                    }
                    // Se tiver entrada e saída, calcula normalmente com jornada custom
                    elseif (!$eh_dia_nao_util && $colunas['entrada'] && $colunas['saida']) {
                        try {
                            $entrada = new DateTime($d . ' ' . $colunas['entrada']);
                            $saida = new DateTime($d . ' ' . $colunas['saida']);
                            $tempo_trabalhado = ($saida->getTimestamp() - $entrada->getTimestamp()) / 3600;
                            // almoço
                            if (!$eh_estagiario && $colunas['saida_almoco'] && $colunas['retorno_almoco']) {
                                $saida_almoco = new DateTime($d . ' ' . $colunas['saida_almoco']);
                                $retorno_almoco = new DateTime($d . ' ' . $colunas['retorno_almoco']);
                                $tempo_almoco = ($retorno_almoco->getTimestamp() - $saida_almoco->getTimestamp()) / 3600;
                                $tempo_trabalhado -= $tempo_almoco;
                            }
                            // meio período flag
                            if ($tempo_trabalhado < ($jornada_esperada / 2)) {
                                $eh_meio_periodo = true;
                            }
                            $saldo_horas = $tempo_trabalhado - $jornada_esperada;
                            $minutos_saldo = (int) round($saldo_horas * 60);
                            if ($minutos_saldo < 0 && abs($minutos_saldo) <= $tolerancia_atraso_min) {
                                $minutos_saldo = 0;
                            }
                            $horas = intdiv(abs($minutos_saldo), 60);
                            $minutos = abs($minutos_saldo) % 60;
                            $sinal = $minutos_saldo >= 0 ? '+' : '-';
                            $saldo_dia = $sinal . str_pad((string)$horas, 2, '0', STR_PAD_LEFT) . ':' . str_pad((string)$minutos, 2, '0', STR_PAD_LEFT);
                            $saldo_total_minutos += $minutos_saldo;
                        } catch (Exception $e) {
                            $saldo_dia = '-';
                        }
                    }
                    // Se tiver apenas entrada
                    elseif (!$eh_dia_nao_util && $colunas['entrada']) {
                        try {
                            $entrada = new DateTime($d . ' ' . $colunas['entrada']);
                            $tempo_trabalhado = 0;
                            $ultimo_ponto = null;
                            if ($colunas['saida']) {
                                $ultimo_ponto = new DateTime($d . ' ' . $colunas['saida']);
                            } elseif ($colunas['retorno_almoco']) {
                                $ultimo_ponto = new DateTime($d . ' ' . $colunas['retorno_almoco']);
                            } elseif ($colunas['saida_almoco']) {
                                $ultimo_ponto = new DateTime($d . ' ' . $colunas['saida_almoco']);
                            }
                            if ($ultimo_ponto) {
                                $tempo_trabalhado = ($ultimo_ponto->getTimestamp() - $entrada->getTimestamp()) / 3600;
                                if (!$eh_estagiario && $colunas['saida_almoco'] && $colunas['retorno_almoco']) {
                                    $saida_almoco = new DateTime($d . ' ' . $colunas['saida_almoco']);
                                    $retorno_almoco = new DateTime($d . ' ' . $colunas['retorno_almoco']);
                                    $tempo_almoco = ($retorno_almoco->getTimestamp() - $saida_almoco->getTimestamp()) / 3600;
                                    $tempo_trabalhado -= $tempo_almoco;
                                }
                            }
                            if ($tempo_trabalhado < ($jornada_esperada / 2)) {
                                $eh_meio_periodo = true;
                            }
                            if ($tempo_trabalhado > 0) {
                                $saldo_horas = $tempo_trabalhado - $jornada_esperada;
                                $minutos_saldo = (int) round($saldo_horas * 60);
                                if ($minutos_saldo < 0 && abs($minutos_saldo) <= $tolerancia_atraso_min) {
                                    $minutos_saldo = 0;
                                }
                                $horas = intdiv(abs($minutos_saldo), 60);
                                $minutos = abs($minutos_saldo) % 60;
                                $sinal = $minutos_saldo >= 0 ? '+' : '-';
                                $saldo_dia = $sinal . str_pad((string)$horas, 2, '0', STR_PAD_LEFT) . ':' . str_pad((string)$minutos, 2, '0', STR_PAD_LEFT);
                                $saldo_total_minutos += $minutos_saldo;
                            }
                        } catch (Exception $e) {
                            $saldo_dia = '-';
                        }
                    }
                }
            ?>
            <?php
                $rowClass = '';
                $saldoLabel = $saldo_dia;
                $titleLabel = $observacao; // show observation by default
                if ($eh_feriado) {
                    if ($feriado_tipo === 'feriado') {
                        $rowClass = 'feriado';
                        $saldoLabel = $saldo_dia; // still empty
                    } elseif ($feriado_tipo === 'facultativo') {
                        $rowClass = 'feriado';
                        $saldoLabel = $saldo_dia;
                    } elseif ($feriado_tipo === 'meio_periodo') {
                        $rowClass = 'meio-periodo';
                    }
                } elseif ($eh_meio_periodo) {
                    $rowClass = 'meio-periodo';
                    $titleLabel = 'Meio período trabalhado';
                }
            ?>
            <tr<?= $rowClass ? ' class="' . $rowClass . '"' : '' ?>>
                <td data-label="DATA:"><?=date('d/m/y', strtotime($d))?></td>
                <td data-label="DIA:"><?=$nome_dia?></td>
                <td data-label="ENTRADA:"><?=$eh_dia_nao_util ? '-' : ($colunas['entrada'] ?: '-')?></td>
                <td data-label="S. ALMOÇO:"><?=$eh_dia_nao_util ? '-' : ($colunas['saida_almoco'] ?: '-')?></td>
                <td data-label="R. ALMOÇO:"><?=$eh_dia_nao_util ? '-' : ($colunas['retorno_almoco'] ?: '-')?></td>
                <td data-label="SAÍDA:"><?=$eh_dia_nao_util ? '-' : ($colunas['saida'] ?: '-')?></td>
                <td data-label="SALDO:" style="<?=$minutos_saldo > 0 ? 'color: green;' : ($minutos_saldo < 0 ? 'color: red;' : '')?>" title="<?=$titleLabel?>">
                    <?=$saldoLabel?>
                </td>
                <td data-label="OBSERVAÇÃO:"><?=$observacao?></td>
                <td data-label="AÇÕES:" class="no-print">
                    <button class="btn" style="padding:4px 8px; font-size:10px; background:#ffc107;" onclick="toggleAjuste('<?=$d?>')">AJUSTAR</button>
                </td>
            </tr>
            
            <tr id="row-ajuste-<?=$d?>" class="no-print" style="display:none; background: #fdfdfe;">
                <td colspan="8">
                    <div style="padding:15px; text-align:left;">
                        <strong>Reordenar e Ajustar (Arraste para organizar):</strong>
                        <div id="sortable-<?=$d?>" class="sort-container">
                            <?php foreach($colunas as $tipo => $valor): ?>
                                <div class="drag-item" data-tipo="<?=$tipo?>">
                                    <span style="width:100px; font-size:11px; color:#666;"><?=strtoupper(str_replace('_',' ',$tipo))?>:</span>
                                    <input type="time" class="input-hora" value="<?=$valor?>" style="width:120px; padding:5px;">
                                    <span style="color:#ccc; margin-left:auto;">☰</span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <textarea id="motivo-<?=$d?>" placeholder="Motivo do ajuste..." style="width:100%; margin-top:10px; padding:8px;"></textarea>
                        <button class="btn btn-save" onclick="salvarAjuste('<?=$d?>', '<?=$id_busca?>')">SALVAR DIA</button>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr style="background: #f2f2f2; font-weight: bold;">
                <td colspan="6" style="text-align: right;">SALDO TOTAL:</td>
                <td style="<?=$saldo_total_minutos > 0 ? 'color: green;' : ($saldo_total_minutos < 0 ? 'color: red;' : '')?>">
                    <?php
                        $minutos_abs = abs($saldo_total_minutos);
                        $horas_total = intval($minutos_abs / 60);
                        $minutos_total = intval($minutos_abs - ($horas_total * 60));
                        $sinal_total = $saldo_total_minutos >= 0 ? '+' : '-';
                        echo $sinal_total . str_pad($horas_total, 2, '0', STR_PAD_LEFT) . ':' . str_pad($minutos_total, 2, '0', STR_PAD_LEFT);
                    ?>
                </td>
                <td class="no-print"></td>
            </tr>
        </tfoot>
    </table>

    <div style="margin-top: 60px; display: flex; justify-content: space-around;" class="print-only">
        <div style="border-top: 1px solid #000; width: 200px; text-align: center; padding-top: 8px; font-size: 9px;">ASSINATURA DO COLABORADOR</div>
        <div style="border-top: 1px solid #000; width: 200px; text-align: center; padding-top: 8px; font-size: 9px;">ALMOXARIFADO CENTRAL</div>
    </div>
</div>
<?php endif; ?>

<script>
function toggleAjuste(data) {
    const row = document.getElementById('row-ajuste-' + data);
    row.style.display = row.style.display === 'none' ? 'table-row' : 'none';
    
    // Inicializa o Sortable apenas se ainda não foi inicializado para evitar duplicidade
    if(row.style.display === 'table-row' && !row.getAttribute('data-init')) {
        new Sortable(document.getElementById('sortable-' + data), { animation: 150, ghostClass: 'blue-background-class' });
        row.setAttribute('data-init', 'true');
    }
}

function salvarAjuste(data, id_u) {
    const container = document.getElementById('sortable-' + data);
    const items = container.querySelectorAll('.drag-item');
    const motivo = document.getElementById('motivo-' + data).value;

    if(!motivo) { alert("Informe o motivo do ajuste!"); return; }

    let fd = new FormData();
    fd.append('id_usuario', id_u);
    fd.append('data_ponto', data);
    fd.append('motivo', motivo);

    items.forEach(item => {
        fd.append('tipos[]', item.getAttribute('data-tipo'));
        fd.append('horas[]', item.querySelector('.input-hora').value);
    });

    fetch('ajustar_ponto_action.php', { method: 'POST', body: fd })
    .then(r => r.text())
    .then(txt => {
        if(txt.trim() === 'sucesso') { alert('Ajustado!'); location.reload(); }
        else { alert('Erro: ' + txt); }
    });
}
</script>

</body>
</html>