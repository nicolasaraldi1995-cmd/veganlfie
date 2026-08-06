<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <link rel="icon" href="/images/logo.png" type="image/png">

        {{-- El nombre escrito acá y no leído del servidor: en producción la
             variable quedó como "veganlfie", con la i y la e cambiadas, y así
             salía en la pestaña del navegador. Es una marca, no algo que se
             configure; el panel ya lo tiene escrito igual (brandName). --}}
        <title inertia>VeganLife</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=inter:300,400,500,600,700&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @routes
        @vite(['resources/js/app.js', "resources/js/Pages/{$page['component']}.vue"])
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>
