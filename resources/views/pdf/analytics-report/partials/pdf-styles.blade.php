@php
    $primary = $colors['primary'] ?? '#0f766e';
    $secondary = $colors['secondary'] ?? '#4338ca';
    $primaryLight = $colors['primary_light'] ?? '#ccfbf1';
@endphp
<style>
    @page { margin: 78px 40px 72px 40px; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 10.5pt; color: #0f172a; line-height: 1.5; }

    /* Rodapé fixo */
    .pdf-footer {
        position: fixed;
        bottom: -62px;
        left: 0;
        right: 0;
        height: 56px;
        font-size: 7pt;
        color: #475569;
    }
    .pdf-footer__accent { height: 3px; background: linear-gradient(90deg, {{ $primary }} 0%, {{ $secondary }} 100%); }
    .pdf-footer__body { background: #f8fafc; border-top: 1px solid #e2e8f0; padding: 6px 0 4px; }
    .pdf-footer__table { width: 100%; border-collapse: collapse; }
    .pdf-footer__brand-name { font-size: 8.5pt; font-weight: bold; color: {{ $primary }}; }
    .pdf-footer__brand-tag { font-size: 6.5pt; color: #64748b; display: block; margin-top: 1px; }
    .pdf-footer__doc-title { font-size: 7.5pt; font-weight: bold; color: #0f172a; text-align: center; }
    .pdf-footer__doc-meta { font-size: 6.5pt; color: #64748b; text-align: center; display: block; margin-top: 2px; }
    .pdf-footer__legal { font-size: 6pt; color: #94a3b8; text-align: right; line-height: 1.35; }
    .pdf-footer__serventec { font-size: 7pt; font-weight: bold; color: {{ $secondary }}; text-align: right; }
    .pdf-footer__link { color: {{ $primary }}; text-decoration: none; font-size: 6.5pt; }

    /* Capa profissional */
    .cover-page { page-break-after: always; padding: 0; }
    .cover-pro__band { background: {{ $primary }}; color: #fff; padding: 0; }
    .cover-pro__band-inner { padding: 22px 32px 20px; }
    .cover-pro__eyebrow { margin: 0 0 6px; font-size: 7.5pt; letter-spacing: 0.14em; text-transform: uppercase; opacity: 0.9; }
    .cover-pro__type { margin: 0 0 4px; font-size: 10pt; font-weight: bold; color: #99f6e4; }
    .cover-pro__city { margin: 0; font-size: 28pt; line-height: 1.1; font-weight: bold; letter-spacing: -0.02em; }
    .cover-pro__sub { margin: 8px 0 0; font-size: 10pt; color: #ecfdf5; opacity: 0.95; }
    .cover-pro__year-pill {
        display: inline-block;
        background: rgba(255,255,255,0.15);
        border: 1px solid rgba(255,255,255,0.35);
        border-radius: 999px;
        padding: 8px 18px;
        text-align: center;
        min-width: 100px;
    }
    .cover-pro__year-label { display: block; font-size: 7pt; text-transform: uppercase; letter-spacing: 0.08em; }
    .cover-pro__year-value { display: block; font-size: 18pt; font-weight: bold; margin-top: 2px; }
    .cover-pro__body { padding: 20px 32px 16px; background: #fff; }
    .cover-pro__lead { margin: 0 0 16px; font-size: 10pt; line-height: 1.55; color: #334155; border-left: 4px solid {{ $secondary }}; padding: 10px 14px; background: #f8fafc; }
    .cover-pro__metrics { width: 100%; border-collapse: separate; border-spacing: 8px 0; margin: 0 0 16px; }
    .cover-pro__metric { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px 14px; vertical-align: top; width: 25%; }
    .cover-pro__metric-label { display: block; font-size: 7pt; text-transform: uppercase; letter-spacing: 0.06em; color: #64748b; font-weight: bold; }
    .cover-pro__metric-value { display: block; font-size: 13pt; font-weight: bold; color: {{ $primary }}; margin-top: 4px; }
    .cover-pro__map-frame { border: 1px solid #cbd5e1; border-radius: 10px; overflow: hidden; margin: 0 0 14px; background: #f1f5f9; }
    .cover-pro__map-img { width: 100%; display: block; max-height: 220px; }
    .cover-pro__map-caption { margin: 0; padding: 8px 12px; font-size: 8pt; color: #475569; background: #f8fafc; border-top: 1px solid #e2e8f0; }
    .cover-pro__summary { background: {{ $primaryLight }}; border: 1px solid #5eead4; border-radius: 8px; padding: 14px 16px; margin-bottom: 12px; }
    .cover-pro__summary-title { margin: 0 0 8px; font-size: 10pt; font-weight: bold; color: #115e59; }
    .cover-pro__summary-list { margin: 0; padding-left: 16px; font-size: 9.5pt; color: #1e293b; }
    .cover-pro__summary-list li { margin-bottom: 4px; }
    .cover-pro__legal { margin: 0; font-size: 7.5pt; color: #94a3b8; text-align: center; line-height: 1.4; }

    /* Secções ATM */
    .pdf-section { page-break-inside: avoid; margin: 0 0 8px; }
    .pdf-section__header { padding: 10px 14px; border-radius: 8px 8px 0 0; }
    .pdf-section__group { display: block; font-size: 7pt; text-transform: uppercase; letter-spacing: 0.1em; opacity: 0.85; margin-bottom: 2px; }
    .pdf-section__title { margin: 0; font-size: 13pt; font-weight: bold; line-height: 1.25; }
    .pdf-section__body {
        border: 1px solid #e2e8f0;
        border-top: none;
        border-radius: 0 0 8px 8px;
        padding: 14px 16px 16px;
        background: #fff;
    }
    .pdf-section__intro { font-size: 9.5pt; color: #475569; margin: 0 0 12px; line-height: 1.45; }

    /* Território / mapa */
    .territory-block { page-break-inside: avoid; margin: 0 0 14px; }
    .territory-map { width: 100%; border: 1px solid #94a3b8; border-radius: 10px; display: block; margin: 0 auto 10px; }
    .territory-legend { font-size: 9pt; color: #334155; line-height: 1.45; margin: 0 0 10px; padding: 8px 12px; background: #f8fafc; border-left: 4px solid {{ $primary }}; }
    .territory-stats { width: 100%; border-collapse: collapse; font-size: 9pt; margin: 0 0 12px; }
    .territory-stats td { border: 1px solid #cbd5e1; padding: 8px 12px; background: #f8fafc; width: 33%; vertical-align: top; }

    /* Conteúdo geral */
    h1 { color: {{ $primary }}; font-size: 20pt; margin: 0 0 8px; }
    h2 { color: {{ $secondary }}; font-size: 13pt; margin: 22px 0 8px; border-bottom: 2px solid {{ $primary }}; padding-bottom: 4px; page-break-after: avoid; }
    h3 { font-size: 11pt; color: {{ $primary }}; margin: 14px 0 6px; page-break-after: avoid; }
    p { margin: 0 0 8px; }
    .preface-page, .toc-page { page-break-after: always; }
    .appendix-section { page-break-before: always; page-break-inside: avoid; }
    .kpi-row { width: 100%; border-collapse: collapse; margin: 10px 0; page-break-inside: avoid; }
    .kpi-row td { border: 1px solid #cbd5e1; padding: 8px 10px; background: #f8fafc; }
    .kpi-label { font-size: 8pt; color: #475569; text-transform: uppercase; font-weight: bold; }
    .kpi-value { font-size: 13pt; font-weight: bold; color: #115e59; }
    table.data { width: 100%; border-collapse: collapse; font-size: 9.5pt; margin: 8px 0; page-break-inside: avoid; }
    table.data th { background: #115e59; color: #fff; padding: 7px 8px; text-align: left; font-weight: bold; }
    table.data td { border: 1px solid #cbd5e1; padding: 6px 8px; color: #0f172a; }
    table.data tr:nth-child(even) td { background: #f8fafc; }
    .box { background: #ecfdf5; border: 1px solid #99f6e4; border-left: 4px solid {{ $primary }}; padding: 10px 12px; margin: 10px 0; color: #1e293b; page-break-inside: avoid; }
    .chart-block { text-align: center; margin: 14px 0; page-break-inside: avoid; border: 1px solid #cbd5e1; border-radius: 8px; padding: 10px 8px 12px; background: #fff; }
    .chart-block__head { text-align: left; margin: 0 0 8px; padding: 0 6px; }
    .chart-block__section { display: block; font-size: 7.5pt; text-transform: uppercase; letter-spacing: 0.06em; color: {{ $secondary }}; font-weight: bold; }
    .chart-block__title { display: block; font-size: 10pt; font-weight: bold; color: #0f172a; margin-top: 2px; }
    .chart-block__hint { display: block; font-size: 8.5pt; color: #475569; margin-top: 4px; line-height: 1.35; }
    .action-lead { font-size: 10pt; color: #1e293b; margin: 0 0 10px; padding: 8px 10px; background: #f1f5f9; border-left: 3px solid {{ $secondary }}; }
    .decision-box { background: #fffbeb; border: 1px solid #fcd34d; border-left: 4px solid #d97706; padding: 8px 12px; margin: 0 0 12px; font-size: 9.5pt; page-break-inside: avoid; }
    .decision-box__title { margin: 0 0 4px; font-size: 8.5pt; font-weight: bold; text-transform: uppercase; letter-spacing: 0.05em; color: #92400e; }
    .section-purpose { font-size: 9.5pt; color: #475569; margin: 0 0 8px; }
    .muted { color: #475569; font-size: 9pt; }
    .legal-notice { background: #fffbeb; border-left-color: #d97706; }
    .official-tag { display: inline-block; font-size: 7.5pt; text-transform: uppercase; letter-spacing: 0.06em; color: {{ $secondary }}; font-weight: bold; margin-bottom: 4px; }
    ul.compact { margin: 4px 0; padding-left: 18px; }
    ul.compact li { margin-bottom: 4px; }
</style>
