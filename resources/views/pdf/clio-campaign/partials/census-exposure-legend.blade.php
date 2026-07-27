@if (($table['kind'] ?? '') === \App\Services\Clio\Export\CensusExposurePdfTables::KIND_LEGEND)
    <div class="exp-legend">
        <p class="exp-legend__note">{{ $table['note'] ?? '' }}</p>
        <table class="exp-legend__table">
            <tr>
                @foreach ($table['items'] ?? [] as $item)
                    <td class="exp-legend__item">
                        <span @class([
                            'exp-legend__swatch',
                            'exp-legend__swatch--urb' => ($item['tone'] ?? '') === 'urbana',
                            'exp-legend__swatch--rur' => ($item['tone'] ?? '') === 'rural',
                        ])></span>
                        <span @class([
                            'exp-legend__sample',
                            'exp-urb' => ($item['tone'] ?? '') === 'urbana',
                            'exp-rur' => ($item['tone'] ?? '') === 'rural',
                        ])>{{ $item['sample'] ?? '' }}</span>
                        <strong>{{ $item['label'] ?? '' }}</strong>
                        <span class="exp-legend__hint">— {{ $item['hint'] ?? '' }}</span>
                    </td>
                @endforeach
            </tr>
        </table>
    </div>
@endif
