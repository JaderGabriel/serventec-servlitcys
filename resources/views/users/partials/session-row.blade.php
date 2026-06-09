@php
    $isCurrent = (bool) ($isCurrent ?? false);
@endphp
<tr class="{{ $isCurrent ? 'bg-teal-50/80 dark:bg-teal-950/25' : '' }}">
    <td class="px-4 py-3 text-sm text-gray-900 dark:text-gray-100">
        @if ($s->user)
            <span class="font-medium">{{ $s->user->name }}</span>
            <span class="block text-xs text-gray-500 dark:text-gray-400 font-mono">{{ $s->user->username }}</span>
        @else
            —
        @endif
        @if ($isCurrent)
            <span class="mt-1 inline-flex items-center rounded-full bg-teal-100 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-teal-800 dark:bg-teal-900/60 dark:text-teal-100">
                {{ __('Esta sessão') }}
            </span>
        @endif
    </td>
    <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-300 font-mono">{{ $s->ip_address ?? '—' }}</td>
    <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-300 whitespace-nowrap">
        @if ($s->last_activity)
            {{ now()->setTimestamp($s->last_activity)->timezone(config('app.timezone'))->format('d/m/Y H:i:s') }}
        @else
            —
        @endif
    </td>
    <td class="px-4 py-3 text-sm text-right">
        @if ($isCurrent)
            <span class="text-xs text-gray-500 dark:text-gray-400">{{ __('Sessão actual') }}</span>
        @else
            <form method="POST" action="{{ route('users.sessions.destroy', $s) }}" class="inline" onsubmit="return confirm('{{ __('Encerrar esta sessão?') }}');">
                @csrf
                @method('DELETE')
                <button type="submit" class="text-rose-600 dark:text-rose-400 hover:underline font-medium">
                    {{ __('Encerrar') }}
                </button>
            </form>
        @endif
    </td>
</tr>
