<x-guest-layout>
    <div class="mb-4 text-sm text-gray-600 dark:text-gray-400">
        Obrigado por se cadastrar! Antes de começar, confirme seu e-mail pelo link que enviamos. Se não recebeu, podemos enviar outro.
    </div>

    @if (session('status') == 'verification-link-sent')
        <div class="mb-4 font-medium text-sm text-green-600 dark:text-green-400">
            Um novo link de verificação foi enviado para o e-mail informado no cadastro.
        </div>
    @endif

    <div class="mt-4 flex items-center justify-between">
        <form method="POST" action="{{ route('verification.send') }}">
            @csrf

            <div>
                <x-primary-button>
                    Reenviar e-mail de verificação
                </x-primary-button>
            </div>
        </form>

        <form method="POST" action="{{ route('logout') }}">
            @csrf

            <button type="submit" class="serv-link text-sm rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-teal-500/40 dark:focus:ring-offset-slate-900">
                Sair
            </button>
        </form>
    </div>
</x-guest-layout>
