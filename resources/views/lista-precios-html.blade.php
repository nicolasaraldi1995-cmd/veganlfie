@php
    // Iniciales presentes, para armar el índice alfabético lateral.
    $iniciales = $marcas->pluck('inicial')->unique()->values();
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>VEGANLIFE · Lista de precios {{ $generado->format('d/m/Y') }}</title>
<style>
    :root{
        --papel:#f3f1ec;
        --tinta:#1a1d21;
        --grafito:#52565e;
        --tenue:#8e919a;
        --azul:#31799b;
        --azul-claro:#5ca8cc;
        --oferta:#c0392f;
        --linea:rgba(0,0,0,.10);
        --blanco:#fff;
    }
    *{margin:0;padding:0;box-sizing:border-box;-webkit-tap-highlight-color:transparent}
    html{scroll-behavior:smooth}
    body{
        background:var(--papel);color:var(--tinta);
        font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,"Helvetica Neue",Arial,sans-serif;
        font-size:15px;line-height:1.4;
        -webkit-font-smoothing:antialiased;
        padding-bottom:64px;
    }
    .wrap{max-width:760px;margin:0 auto}

    /* ---------- Encabezado ---------- */
    header{padding:18px 16px 14px;display:flex;align-items:center;gap:12px}
    header img{width:44px;height:44px;object-fit:contain;flex-shrink:0}
    .marca-nombre{font-size:19px;font-weight:800;letter-spacing:-.02em;line-height:1.1}
    .marca-sub{font-size:11px;color:var(--tenue);letter-spacing:.14em;text-transform:uppercase;margin-top:2px}
    .fecha{margin-left:auto;text-align:right;font-size:10.5px;color:var(--tenue);line-height:1.5;letter-spacing:.02em}
    .fecha b{display:block;font-size:13.5px;color:var(--tinta);font-variant-numeric:tabular-nums;letter-spacing:-.01em}

    /* ---------- Barra fija: buscador + índice ---------- */
    .barra{position:sticky;top:0;z-index:20;background:var(--papel);
        box-shadow:0 1px 0 var(--linea),0 6px 16px -12px rgba(0,0,0,.35)}
    .buscador{padding:10px 16px 8px;display:flex;align-items:center;gap:10px}
    .campo{flex:1;position:relative}
    .campo svg{position:absolute;left:12px;top:50%;transform:translateY(-50%);width:17px;height:17px;color:var(--tenue)}
    input[type=search]{
        width:100%;padding:12px 14px 12px 36px;font-size:16px;font-family:inherit;color:var(--tinta);
        background:var(--blanco);border:1px solid var(--linea);border-radius:11px;outline:none;
        -webkit-appearance:none;appearance:none;
    }
    input[type=search]:focus{border-color:var(--azul-claro);box-shadow:0 0 0 3px rgba(92,168,204,.18)}
    .contador{font-size:11px;color:var(--grafito);white-space:nowrap;font-variant-numeric:tabular-nums}
    .contador b{color:var(--azul);font-weight:700}

    .indice{display:flex;gap:2px;overflow-x:auto;padding:0 12px 9px;scrollbar-width:none}
    .indice::-webkit-scrollbar{display:none}
    .indice button{
        flex:0 0 auto;min-width:26px;height:26px;border:0;border-radius:7px;cursor:pointer;
        background:transparent;color:var(--grafito);font:600 12px/1 inherit;font-family:inherit;
    }
    .indice button:hover,.indice button:focus-visible{background:rgba(49,121,155,.12);color:var(--azul);outline:none}

    /* ---------- Marca desplegable ---------- */
    .marca{border-top:1px solid var(--linea)}
    .marca-btn{
        width:100%;display:flex;align-items:center;gap:10px;padding:14px 16px;
        background:transparent;border:0;cursor:pointer;text-align:left;font-family:inherit;color:inherit;
    }
    .marca-btn:focus-visible{outline:2px solid var(--azul-claro);outline-offset:-2px}
    .chev{width:16px;height:16px;color:var(--tenue);flex-shrink:0;transition:transform .18s ease}
    .abierta .chev{transform:rotate(90deg);color:var(--azul)}
    .marca-btn h2{flex:1;font-size:14px;font-weight:700;letter-spacing:.01em;text-transform:uppercase}
    .abierta .marca-btn h2{color:var(--azul)}
    .cuenta{font-size:11px;color:var(--grafito);background:rgba(0,0,0,.05);padding:2px 8px;border-radius:20px;font-variant-numeric:tabular-nums}

    .cuerpo{display:none;padding:0 16px 8px}
    .abierta .cuerpo{display:block}

    /* ---------- Producto y precios ---------- */
    .prod{padding:9px 0;border-top:1px dashed rgba(0,0,0,.09)}
    .prod:first-child{border-top:0}
    .prod-nom{font-size:14px;font-weight:600;line-height:1.3}
    .tags{display:inline-flex;gap:4px;margin-left:6px;vertical-align:1px}
    .tag{font-size:9px;font-weight:800;letter-spacing:.04em;padding:2px 5px;border-radius:4px;text-transform:uppercase}
    .t-tacc{background:#d8f0e4;color:#1a6b4c}
    .t-frio{background:#dceefb;color:#1d6a96}
    .t-cong{background:#dbe3fb;color:#2b4796}

    .pres{display:flex;align-items:baseline;gap:8px;padding:4px 0 2px}
    .unidad{font-size:12.5px;color:var(--grafito);min-width:62px}
    .guia{flex:1;border-bottom:1px dotted rgba(0,0,0,.18);transform:translateY(-3px)}
    .precio{font-size:16px;font-weight:700;font-variant-numeric:tabular-nums;letter-spacing:-.02em;white-space:nowrap}
    .precio.hay-oferta{color:var(--oferta)}
    .antes{font-size:11.5px;color:var(--tenue);text-decoration:line-through;font-variant-numeric:tabular-nums;white-space:nowrap}

    /* ---------- Otros ---------- */
    .vacio{display:none;padding:56px 20px;text-align:center;color:var(--grafito);font-size:14px}
    .vacio.ver{display:block}
    footer{padding:22px 16px 8px;text-align:center;font-size:11px;color:var(--tenue);line-height:1.7;border-top:1px solid var(--linea);margin-top:8px}
    footer a{color:var(--azul);text-decoration:none;font-weight:600}
    .arriba{
        position:fixed;right:14px;bottom:14px;z-index:30;width:44px;height:44px;border-radius:50%;
        border:0;cursor:pointer;background:var(--azul);color:#fff;display:none;
        align-items:center;justify-content:center;box-shadow:0 4px 14px rgba(0,0,0,.28);
    }
    .arriba.ver{display:flex}
    @media (prefers-reduced-motion:reduce){*{transition:none!important}html{scroll-behavior:auto}}
    @media print{.barra,.arriba,.indice{display:none}.cuerpo{display:block!important}}
</style>
</head>
<body>
<div class="wrap">

    <header>
        <img src="data:image/png;base64,{{ $logo }}" alt="VEGANLIFE">
        <div>
            <div class="marca-nombre">VEGANLIFE</div>
            <div class="marca-sub">Distribuidora</div>
        </div>
        {{-- Solo la fecha: el conteo lo lleva el buscador, que además cambia al filtrar. --}}
        <div class="fecha">
            Precios al
            <b>{{ $generado->format('d/m/Y') }}</b>
        </div>
    </header>

    <div class="barra">
        <div class="buscador">
            <div class="campo">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                <input type="search" id="q" placeholder="Buscar producto o marca" autocomplete="off">
            </div>
            <span class="contador"><b id="n">{{ number_format($totalProductos, 0, ',', '.') }}</b> productos</span>
        </div>
        <nav class="indice" aria-label="Ir a marcas por inicial">
            @foreach ($iniciales as $inicial)
                <button type="button" data-inicial="{{ $inicial }}">{{ $inicial }}</button>
            @endforeach
        </nav>
    </div>

    <main id="lista">
        @foreach ($marcas as $i => $marca)
            <section class="marca" data-marca="{{ mb_strtolower($marca['nombre']) }}" data-inicial="{{ $marca['inicial'] }}">
                <button type="button" class="marca-btn" aria-expanded="false">
                    <svg class="chev" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                    <h2>{{ $marca['nombre'] }}</h2>
                    <span class="cuenta">{{ count($marca['productos']) }}</span>
                </button>
                <div class="cuerpo">
                    @foreach ($marca['productos'] as $p)
                        <div class="prod" data-prod="{{ mb_strtolower($p['nombre']) }}">
                            <div class="prod-nom">{{ $p['nombre'] }}@if ($p['sin_tacc'] || $p['frio'] || $p['congelado'])<span class="tags">@if ($p['sin_tacc'])<span class="tag t-tacc">sin tacc</span>@endif @if ($p['frio'])<span class="tag t-frio">frío</span>@endif @if ($p['congelado'])<span class="tag t-cong">congelado</span>@endif</span>@endif</div>
                            @foreach ($p['presentaciones'] as $pr)
                                <div class="pres">
                                    <span class="unidad">{{ $pr['unidad'] }}</span>
                                    <span class="guia"></span>
                                    @if ($pr['en_oferta'])
                                        <span class="antes">${{ number_format($pr['precio'], 0, ',', '.') }}</span>
                                        <span class="precio hay-oferta">${{ number_format($pr['precio_final'], 0, ',', '.') }}</span>
                                    @else
                                        <span class="precio">${{ number_format($pr['precio'], 0, ',', '.') }}</span>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @endforeach
                </div>
            </section>
        @endforeach
    </main>

    <p class="vacio" id="vacio">No hay productos que coincidan con la búsqueda.</p>

    <footer>
        Precios sujetos a modificación · Consultá disponibilidad por zona<br>
        <a href="https://wa.me/5492477504048">WhatsApp 2477 50-4048</a> · Pergamino, Buenos Aires
    </footer>
</div>

<button class="arriba" id="arriba" type="button" aria-label="Volver arriba">
    <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 15l7-7 7 7"/></svg>
</button>

<script>
(function () {
    var lista = document.getElementById('lista');
    var secciones = Array.prototype.slice.call(lista.querySelectorAll('.marca'));
    var input = document.getElementById('q');
    var contador = document.getElementById('n');
    var vacio = document.getElementById('vacio');
    var arriba = document.getElementById('arriba');

    function abrir(seccion, estado) {
        seccion.classList.toggle('abierta', estado);
        seccion.querySelector('.marca-btn').setAttribute('aria-expanded', estado ? 'true' : 'false');
    }

    // Abrir / cerrar marca
    secciones.forEach(function (seccion) {
        seccion.querySelector('.marca-btn').addEventListener('click', function () {
            abrir(seccion, !seccion.classList.contains('abierta'));
        });
    });

    // Índice alfabético: lleva a la primera marca de esa letra y la abre
    document.querySelectorAll('.indice button').forEach(function (boton) {
        boton.addEventListener('click', function () {
            var destino = secciones.filter(function (s) {
                return s.dataset.inicial === boton.dataset.inicial && s.style.display !== 'none';
            })[0];
            if (!destino) return;
            abrir(destino, true);
            destino.scrollIntoView({ block: 'start' });
        });
    });

    function formatear(n) {
        return n.toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    }

    var timer;
    input.addEventListener('input', function () {
        clearTimeout(timer);
        timer = setTimeout(filtrar, 120);
    });

    function filtrar() {
        var termino = input.value.toLowerCase().trim();
        var visibles = 0;

        secciones.forEach(function (seccion) {
            var coincideMarca = !termino || seccion.dataset.marca.indexOf(termino) !== -1;
            var enSeccion = 0;

            seccion.querySelectorAll('.prod').forEach(function (prod) {
                var mostrar = !termino || coincideMarca || prod.dataset.prod.indexOf(termino) !== -1;
                prod.style.display = mostrar ? '' : 'none';
                if (mostrar) enSeccion++;
            });

            seccion.style.display = enSeccion ? '' : 'none';
            seccion.querySelector('.cuenta').textContent = enSeccion;
            // Buscando conviene ver los resultados sin tener que abrir cada marca.
            if (termino) abrir(seccion, enSeccion > 0);
            visibles += enSeccion;
        });

        if (!termino) secciones.forEach(function (s) { abrir(s, false); });

        contador.textContent = formatear(visibles);
        vacio.classList.toggle('ver', visibles === 0);
    }

    window.addEventListener('scroll', function () {
        arriba.classList.toggle('ver', window.scrollY > 700);
    }, { passive: true });

    arriba.addEventListener('click', function () {
        window.scrollTo({ top: 0, behavior: 'smooth' });
        input.focus();
    });
})();
</script>
</body>
</html>
