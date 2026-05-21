{{-- Evita flash de tema errado antes do CSS --}}
<script>
(function () {
    var stored = localStorage.getItem('theme');
    var prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
    var useDark = stored === 'dark' || (stored !== 'light' && prefersDark);
    if (useDark) {
        document.documentElement.classList.add('dark');
        document.documentElement.style.colorScheme = 'dark';
    } else {
        document.documentElement.classList.remove('dark');
        document.documentElement.style.colorScheme = 'light';
    }
    window.setDarkClass = function () {
        var s = localStorage.getItem('theme');
        var d = window.matchMedia('(prefers-color-scheme: dark)').matches;
        var dark = s === 'dark' || (s !== 'light' && d);
        document.documentElement.classList.toggle('dark', dark);
        document.documentElement.style.colorScheme = dark ? 'dark' : 'light';
    };
})();
</script>
