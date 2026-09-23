<!DOCTYPE html>
<html lang="ru" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#0a111f">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700|jetbrains-mono:400,600|oswald:500,600" rel="stylesheet">
    {{-- Тему ставим до первой отрисовки, иначе тёмный экран моргнёт светлым. --}}
    <script>
        (function () {
            try {
                var saved = localStorage.getItem('theme');
                var dark = saved ? saved === 'dark' : window.matchMedia('(prefers-color-scheme: dark)').matches;
                if (dark) document.documentElement.classList.add('dark');
            } catch (e) {}
        })();
    </script>
    @inertiaHead
    @vite('resources/js/app.js')
</head>
<body class="h-full antialiased">
    @inertia
</body>
</html>
