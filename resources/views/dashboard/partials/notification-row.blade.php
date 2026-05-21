<p class="font-medium text-slate-900 dark:text-slate-100 line-clamp-1">{{ $notification['title'] ?: __('Notificação') }}</p>
@if (filled($notification['body'] ?? null))
    <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5 line-clamp-2">{{ $notification['body'] }}</p>
@endif
<p class="text-[10px] text-slate-400 dark:text-slate-500 mt-1">{{ $notification['created_label'] }}</p>
