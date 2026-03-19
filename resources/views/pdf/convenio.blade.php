@php
    use Carbon\Carbon;

    $formatDate = function ($data) {
        if ($data === null || $data === '') {
            return null;
        }
        try {
            return Carbon::parse($data)->format('d/m/Y');
        } catch (\Throwable $e) {
            return $data;
        }
    };

    $formatMoney = function ($value) {
        $num = (float) ($value ?? 0);
        return 'R$ ' . number_format($num, 2, ',', '.');
    };

    $formatPercent = function ($value) {
        $num = (float) ($value ?? 0);
        return number_format($num, 2, ',', '.') . '%';
    };

    $placeholder = function ($value, $fallback = 'Não informado') {
        return ($value !== null && $value !== '') ? $value : $fallback;
    };

    $municipioNome = $placeholder(data_get($convenio, 'municipio.nome'));
    $orgaoNome = $placeholder(data_get($convenio, 'orgao.sigla') ?: data_get($convenio, 'orgao.nome'));
@endphp
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Convênio {{ $placeholder($convenio['numero_convenio'] ?? null, (string) ($convenio['id'] ?? '')) }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10pt; line-height: 1.5; color: #1a202c; padding: 24px; }
        .header { border-bottom: 3px solid #1e3a5f; padding-bottom: 16px; margin-bottom: 20px; }
        .header h1 { font-size: 16pt; color: #1e3a5f; font-weight: 700; }
        .header .subtitle { font-size: 9pt; color: #4a5568; margin-top: 4px; }
        h2 { font-size: 11pt; color: #2d3748; font-weight: 600; margin: 20px 0 10px; padding-bottom: 4px; border-bottom: 1px solid #e2e8f0; }
        .info-grid { margin: 8px 0; }
        .info-row { display: table; width: 100%; margin-bottom: 6px; }
        .info-label { display: table-cell; width: 32%; padding: 4px 12px 4px 0; font-weight: 600; color: #4a5568; font-size: 9pt; }
        .info-value { display: table-cell; padding: 4px 0; }
        .metrics { display: table; width: 100%; margin: 12px 0; border: 1px solid #e2e8f0; border-radius: 4px; overflow: hidden; }
        .metric-cell { display: table-cell; width: 25%; padding: 14px 16px; text-align: center; border-right: 1px solid #e2e8f0; background: #f8fafc; }
        .metric-cell:last-child { border-right: none; }
        .metric-label { font-size: 8pt; color: #718096; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 4px; }
        .metric-value { font-size: 12pt; font-weight: 700; color: #1a202c; }
        .metric-value.valor { font-family: 'DejaVu Sans Mono', monospace; }
        table { width: 100%; border-collapse: collapse; margin: 12px 0; font-size: 9pt; }
        th, td { border: 1px solid #e2e8f0; padding: 8px 10px; text-align: left; }
        th { background: #1e3a5f; color: #fff; font-weight: 600; }
        td.valor { text-align: right; font-family: 'DejaVu Sans Mono', monospace; }
        .pi-list { padding: 8px 0; }
        .pi-badge { display: inline-block; background: #edf2f7; border: 1px solid #e2e8f0; padding: 4px 10px; margin: 2px 4px 2px 0; border-radius: 4px; font-size: 9pt; }
        .footer { margin-top: 28px; padding-top: 12px; border-top: 1px solid #e2e8f0; font-size: 8pt; color: #718096; }
        .empty-msg { color: #718096; font-style: italic; padding: 8px 0; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Detalhes do Convênio {{ $placeholder($convenio['numero_convenio'] ?? null, (string) ($convenio['id'] ?? '')) }}</h1>
        <p class="subtitle">Documento gerado em {{ now()->format('d/m/Y \à\s H:i') }} — Seplad Convênios</p>
    </div>

    <h2>Informações básicas</h2>
    <div class="info-grid">
        <div class="info-row">
            <div class="info-label">Número do convênio</div>
            <div class="info-value">{{ $placeholder($convenio['numero_convenio'] ?? null) }}</div>
        </div>
        <div class="info-row">
            <div class="info-label">Município</div>
            <div class="info-value">{{ $municipioNome }}</div>
        </div>
        <div class="info-row">
            <div class="info-label">Órgão</div>
            <div class="info-value">{{ $orgaoNome }}</div>
        </div>
        <div class="info-row">
            <div class="info-label">Objeto</div>
            <div class="info-value">{{ $placeholder($convenio['objeto'] ?? null) }}</div>
        </div>
        <div class="info-row">
            <div class="info-label">Data início</div>
            <div class="info-value">{{ $formatDate($convenio['data_inicio'] ?? null) ?? 'Não informado' }}</div>
        </div>
        <div class="info-row">
            <div class="info-label">Data fim</div>
            <div class="info-value">{{ $formatDate($convenio['data_fim'] ?? null) ?? 'Não informado' }}</div>
        </div>
        <div class="info-row">
            <div class="info-label">Convenente</div>
            <div class="info-value">{{ $placeholder($convenio['convenente_nome'] ?? null) }}</div>
        </div>
    </div>

    <h2>Valores e execução</h2>
    <div class="metrics">
        <div class="metric-cell">
            <div class="metric-label">Valor total</div>
            <div class="metric-value valor">{{ $formatMoney($agregados['valor_total_parcelas'] ?? 0) }}</div>
        </div>
        <div class="metric-cell">
            <div class="metric-label">Valor pago</div>
            <div class="metric-value valor">{{ $formatMoney($agregados['valor_pago'] ?? 0) }}</div>
        </div>
        <div class="metric-cell">
            <div class="metric-label">Valor em aberto</div>
            <div class="metric-value valor">{{ $formatMoney($agregados['valor_em_aberto'] ?? 0) }}</div>
        </div>
        <div class="metric-cell">
            <div class="metric-label">% de execução</div>
            <div class="metric-value">{{ $formatPercent($agregados['percentual_execucao'] ?? 0) }}</div>
        </div>
    </div>

    <h2>Plano interno vinculado</h2>
    @if(!empty($convenio['planos_internos_detalhes']) && count($convenio['planos_internos_detalhes']) > 0)
        <div class="pi-list">
            @foreach($convenio['planos_internos_detalhes'] as $pi)
                <span class="pi-badge">{{ $pi['codigo'] ?? '-' }}</span>
            @endforeach
        </div>
    @else
        <p class="empty-msg">Nenhum plano interno vinculado.</p>
    @endif

    <h2>Parcelas</h2>
    @if(!empty($parcelas) && count($parcelas) > 0)
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Status</th>
                    <th>Valor previsto</th>
                    <th>Valor pago</th>
                    <th>Data pagamento</th>
                </tr>
            </thead>
            <tbody>
                @foreach($parcelas as $p)
                <tr>
                    <td>{{ $p['numero'] ?? '-' }}</td>
                    <td>{{ $placeholder($p['situacao'] ?? null, '-') }}</td>
                    <td class="valor">{{ $formatMoney($p['valor_previsto'] ?? 0) }}</td>
                    <td class="valor">{{ $formatMoney($p['valor_pago'] ?? 0) }}</td>
                    <td>{{ $formatDate($p['data_pagamento'] ?? null) ?? '-' }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <p class="empty-msg">Nenhuma parcela cadastrada.</p>
    @endif

    <div class="footer">
        Gerado em {{ now()->format('d/m/Y H:i') }} — Seplad Convênios
    </div>
</body>
</html>
