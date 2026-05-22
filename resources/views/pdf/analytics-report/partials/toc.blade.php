@php
    $toc = is_array($table_of_contents ?? null) ? $table_of_contents : [];
@endphp
@if (count($toc) > 0)
    <div class="toc-page" style="page-break-after: always;">
        <h2 style="font-size:14pt;color:#115e59;border-bottom:2px solid #0f766e;padding-bottom:6px;">{{ __('Sumário') }}</h2>
        <table width="100%" cellpadding="0" cellspacing="0" style="font-size:10pt;margin-top:12px;">
            @foreach ($toc as $entry)
                <tr>
                    <td style="padding:5px 0;border-bottom:1px dotted #cbd5e1;">{{ $entry['title'] ?? '' }}</td>
                    <td style="padding:5px 0;border-bottom:1px dotted #cbd5e1;text-align:right;width:48px;color:#64748b;">{{ $entry['page_hint'] ?? '' }}</td>
                </tr>
            @endforeach
        </table>
    </div>
@endif
