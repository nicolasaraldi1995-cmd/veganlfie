<?php

return [
    /*
     * Ziggy mete el mapa de rutas del sitio en el HTML para que el JavaScript
     * arme los enlaces. Sin filtro publica TODAS las rutas nombradas, así que
     * en cada página, incluso sin estar logueado, viajaban las 38 del panel:
     * admin/caja, admin/resumen-cuenta, admin/configuracion, admin/importador
     * y las de cada recurso.
     *
     * No abre ninguna puerta -- entrar sigue pidiendo cuenta de admin -- pero
     * es el plano del negocio dibujado para cualquiera que abra el código
     * fuente. El frente en Vue nunca las usa: sólo llama a las públicas y a
     * las de cuenta (carrito, checkout, mis-pedidos, login).
     */
    'except' => [
        'filament.*',
        'livewire.*',
        // Las de lista-precios se quedan a propósito, aunque sean internas: el
        // Vue las llama con route() detrás de v-if="esStaff", así que sacarlas
        // le rompe el acceso al equipo. Lo que viaja es la dirección, no el
        // permiso: las cuatro están cerradas con auth + staff.
    ],
];
