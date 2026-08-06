# Plan de arreglos — VeganLife

*Reescrito el 06/08/2026, después de re-verificar los 44 hallazgos con 23 agentes.*
*El parte completo de la revisión está en `REVISION_2026-08-06.md`.*

---

## Lo que cambió respecto del plan anterior

El plan viejo daba por sentado que los 44 problemas eran incendios. La revisión mostró
que no: **casi todos son bugs de verdad, pero hoy no te sacan un peso porque la tienda
está prácticamente vacía.** Vendés 13 de 2.161 presentaciones, no hay una oferta vigente,
no hay un costo cargado, no hay un pedido cancelado.

O sea: no son incendios, son **trampas armadas**. Cada una espera la primera vez que hagas
algo — cargar stock, correr una oferta, cancelar un pedido, importar de nuevo.

Por eso este plan ya no se ordena por área de código. Se ordena así:

1. **Lo que te puede costar algo ya** (aunque la tienda esté vacía)
2. **Lo que te bloquea poder vender**
3. **Las trampas, agrupadas por la acción que las dispara** — arreglás cada grupo *antes*
   de hacer esa cosa por primera vez

**🔷 = necesito una decisión tuya. 👤 = es tarea tuya, yo no llego.**

---

# BLOQUE 1 — ESTA SEMANA

Esto no espera. Lo primero puede destruir trabajo de meses en un despliegue.

## FASE 0 — Antes de que un despliegue te borre algo 👤

### 0.1 🚨 `FILAMENT_FILESYSTEM_DISK` — **1.710 fotos en riesgo**

Si en Laravel Cloud esa variable no dice `s3`, **el disco se borra en cada despliegue**.
Estás a un deploy de perder todas las fotos de productos.

- Antes de tocarla, corré `php artisan imagenes:migrar-a-s3`.
- Después ponela en `s3` y verificá que las imágenes sigan cargando.

### 0.2 Backup andando **y restaurado una vez**

- No hay ningún backup automático corriendo. Revisaron las 163 tareas de Windows: ninguna
  es de VeganLife. El último respaldo real es de hace 23 días.
- Existen 6 archivos de respaldo, pero **nadie restauró ninguno jamás**. No se sabe si sirven.
- En Laravel Cloud: ¿están activados los backups? ¿está habilitado el scheduler/cron?
  Sin eso, allá no corre nada.
- **La prueba de verdad:** restaurar uno en una base aparte y contar filas.

### 0.3 Las contraseñas

`admin@veganlife.com` y `operador@veganlife.com` están en **`password`**, más 3 cuentas de
cliente. Vienen del sembrador que produjo la base de producción, y el README lo advierte.

```
php artisan usuarios:clave admin@veganlife.com <clave-nueva>
```

### 0.4 Las variables de entorno

| Variable | Hoy | Tiene que decir |
|---|---|---|
| `MAIL_MAILER` | `log` | tu proveedor real — **hoy no sale ni un mail** |
| `MAIL_FROM_ADDRESS` | `hello@example.com` | una dirección tuya |
| `MAIL_FROM_NAME` | variable | `VeganLife` escrito completo |
| `APP_URL` | ¿`veganlife.test`? | tu dominio con `https://` — si no, los links de recuperar contraseña van a la nada |
| `APP_NAME` | `veganlfie` | `VeganLife` (firma los mails) |
| `APP_ENV` / `APP_DEBUG` | — | `production` / `false` |
| `LOG_LEVEL` | `debug` | `error` (el registro crece sin control) |
| `QUEUE_CONNECTION` | — | `sync` si no tenés worker: no hay ni un trabajo encolado, estás pagando por nada |

También: la zona horaria está en UTC (3 horas adelantada), y `robots.txt` anuncia el sitemap
con dirección relativa, así que Google no lo descubre. Una línea.

## FASE 1 — Reparar los datos que YA están rotos

Esto no se arregla con código. Hay que tocar las filas.

### 1.1 🚨 El precio fantasma de $300.164,17

Tres renglones de pedido (bifes de seitán 500gr) tienen ese precio. El producto más caro del
catálogo cuesta $118.400.

**Consecuencia:** tu pantalla de deudores dice **$3.048.232** cuando lo real es **~$150.631**.
El 95% es humo. **Son 3 filas.**

*Atenuante: los pedidos son de prueba tuyos y de QA, no de un cliente.*

### 1.2 Sacar de la venta lo que no debería estar publicado

- Un producto a la venta llamado **"Envío compra menor a $100000"**.
- El combo publicado se llama **"combito"**, con la descripción
  *"para ver el partido!! para ver a la seleccion papáaa"*.
- Usuarios de prueba escritos en la base real: id 18 "QA Recorrido", id 8 "Cliente Prueba Uno",
  y un "asdasdasd".

### 1.3 La basura acumulada

21 marcas activas vacías (4 sin ningún producto) · 94 ofertas vencidas hace 39 días ·
1 marca borrada ("Asana") con 2 productos colgando.

### 1.4 Los duplicados — 🔷 **decisión**

**28 grupos de productos triplicados dentro de la misma marca** (69 productos, 28 activos),
de reimportaciones repetidas. **Algunas copias tienen precio distinto**, así que hay que mirar
cuál es la buena antes de borrar. ¿Los reviso uno por uno y te paso la lista, o querés mirarla vos?

*(Los "22 nombres repetidos bajo marcas distintas" del informe viejo eran ciertos pero **no son
un problema**: 3 marcas venden "Agua de rosas", es normal.)*

### 1.5 Los 331 productos apagados con presentaciones vivas

**Va después de la fase 2.1**, no antes. Si limpiás los datos sin arreglar el código, mañana
vuelven a quedar colgando.

## FASE 2 — Lo que puede romper plata ya

### 2.1 🚨 El operador repisa los precios históricos

Al guardar **cualquier** pedido viejo, aunque no toque nada, le repisa el precio histórico con
el de hoy. **9 de 9 renglones cambiarían ahora mismo:** los cuatro pedidos pasarían de
**$3.079.670 a $247.761**.

Es un bug nuevo, no estaba en los 44, y es de los peores.

### 2.2 Apagar un producto tiene que apagar sus presentaciones

Es la raíz de los 331 productos apagados con 344 presentaciones vivas y con precio colgando.
Arreglá esto y recién después limpiá (1.5).

### 2.3 El agujero de "qué se puede comprar", completo

El A2 original era la punta. La revisión encontró que **`/marcas/{slug}` y `/categorías/{slug}`
tienen el mismo agujero**, más **3 lugares más en el mismo controlador**, y que combos y
búsqueda también arrastran productos de baja.

Un solo lugar que defina "comprable" y los usen todos.

🔷 **Ficha de producto de baja: ¿404, o se ve sin botón con un cartel "no disponible"?**

### 2.4 El banner acepta código ejecutable

El campo URL del banner es texto libre sin validar. Cargaron `javascript:alert(1)` y **quedó
tal cual en la portada**. Hace falta cuenta de empleado, pero no debería poder.

---

# BLOQUE 2 — PARA PODER VENDER

Hoy la tienda no está en condiciones de recibir un cliente. Esto es lo que falta.

## FASE 3 — Lanzamiento

### 3.1 👤 Cargar el stock — **13 de 2.161**

El 99,4% del catálogo se ve y no se puede comprar. Esto no es código, es inventario.
Es lo que más cambia la realidad de todo el resto del plan.

### 3.2 Que el cliente pueda registrarse y entrar

- **9 mensajes en clave de programador**, no 3. Escribís el mail con una mayúscula (lo que hace
  el teclado del celular solo) y te sale `validation.lowercase` y no te deja. Errás la clave →
  `auth.failed`. Insistís → `auth.throttle` y quedás afuera **incluso con la clave correcta**.
  Faltan dos archivos de traducción.
- **5 campos del registro no pintan el error** (negocio, dirección, ciudad, provincia, tipo de
  cliente): apretás "Crear cuenta", la página vuelve igual, cero texto rojo, cero cuenta.
  Arreglo de 5 líneas.
- **4 pantallas de cuenta enteras en inglés** (olvidé mi contraseña, restablecer, verificar mail,
  confirmar) y el mail de recuperación también.
  *(El informe viejo decía que eran claras dentro de un sitio oscuro: era al revés, el sitio es claro.)*

### 3.3 Que el correo funcione de punta a punta

Depende de la 0.4. Después hay que probarlo de verdad: comprar y ver que llegue.
Hoy **al cliente no le llega nada** después de comprar.

### 3.4 El celular

- El botón de WhatsApp **tapa "Agregar al carrito"**: 17×20px de pisada. Tocás ahí y se abre
  Instagram.
- **Doble clic agrega dos veces.** Confirmado, el carrito quedó en 2.
- En el checkout, **"Confirmar pedido" está a 4 pantallas de scroll**, detrás de dos pantallas
  y media de "Te puede interesar" que dicen todos "Sin stock".
- Después de comprar, la pantalla dice **"confirmado" y "pendiente" a la vez**.
- **76 textos por debajo de 12px** en el sitio público (no 86). Los peores: "Sin TACC" a **8px**
  y el precio por kilo a **9,5px**. En la portada, con 587 tarjetas, son más de 1.300 apariciones.

### 3.5 Los filtros vacíos

**Fríos = 0 productos. Sin TACC = 2. Ofertas = 0.** Tres enlaces del menú que no llevan a nada.
Hay que ver si es dato faltante o filtro roto.

---

# BLOQUE 3 — LAS TRAMPAS

Agrupadas por **la acción que las dispara**. Arreglás el grupo antes de hacer esa cosa por
primera vez. Si no la vas a hacer todavía, puede esperar.

## 🪤 Antes de cancelar tu primer pedido

- **A5 / B2** — El desplegable "Estado" cambia la palabra y nada más: no devuelve stock, no
  manda el correo que el botón promete. Y para un pedido **entregado**, el desplegable es el
  **único** camino. Hoy: 0 pedidos llegaron a cancelado o entregado, los 4 vivos expuestos.
  🔷 **¿Saco el desplegable, o lo hago pasar por la misma lógica que el botón?**
- **A6** — El stock se devuelve **dos veces**. Cancelás → revivís → cancelás, o editás un
  pedido ya cancelado. 28 unidades reservadas en riesgo apenas canceles el primero.
- **Cancelar por el desplegable NUNCA devuelve el stock** (espejo de A6, mismo arreglo).
- **Cancelar un pedido ya cobrado cambia la caja de un mes ya cerrado.** Medido con 100
  cancelaciones: **$1.710.016 de plata cobrada se vuelven invisibles.**
- **A11** — Borrar un producto deja pedidos viejos en $0 si el operador los abre.

## 🪤 Antes de correr tu primera oferta o tocar un combo

- **C1** — El carrito ignora el descuento del combo: publicado a $1.500, cobra $2.000.
- **C2** — Cambiar el tipo de precio del combo no borra el valor viejo, y el viejo gana.
- **C3** — "Ofertas masivas" no limpia el precio de oferta cargado a mano. **Las 94 filas con
  oferta % están a UNA acción de admin de caer en la trampa.**
- **C4** — Un precio de oferta en $0 pasa la validación y el producto queda gratis.
- **IVA** — Subís un precio +30% por "Actualizar precios", tocás el IVA de la marca, y el precio
  vuelve al original. **Los $300 se pierden y no vuelven.** *(Este el re-verificador lo dio por
  muerto y el adversario lo resucitó reproduciéndolo por el panel real.)*

## 🪤 Antes de volver a importar una lista

- **A9** 🔷 — Destildar "Actualizar productos existentes (precio y datos)" **no protege los
  precios**. Alcanza las 2.161 cuando lo uses.
  **¿La casilla frena los precios de verdad, o le cambiamos el texto?** Esta decisión cambia
  el arreglo entero.
- **Si no mapeás la columna Precio, escribe $0 en todo lo que toca** y saca esos productos del
  sitio, sin un aviso. *(Nuevo, grave.)*
- **A8** — Una fila con precio inválido se lleva puesta a toda una **marca o categoría** nueva.
  Peor de lo reportado: basta una categoría.
- **A10** — Al sincronizar, dos productos del mismo nombre y distinta marca **se funden en uno**
  y sobrevive el equivocado. 22 grupos / 47 productos.
- **A3** — El HTML exportado escribe el precio con la oferta ya aplicada; al reimportarlo la
  rebaja se vuelve precio de lista.
- **B8** 🔷 — La columna Stock se automapea pero se ignora en los productos que ya existen.
  **¿Que actualice el stock, o que deje de ofrecer la columna?**
- **Un producto dado de baja nunca vuelve**, aunque el proveedor lo siga vendiendo: **41 de los
  331 están en la lista del proveedor** y el importador les actualiza el precio sin republicarlos.
- **B9** (cambiar la unidad deja la vieja a la venta) · **B10** (presentación en papelera) ·
  **C5** ("1.500" se lee $1,50; negativo → positivo) · **C6** (precio no numérico pisa con $0) ·
  **C7** (la vista previa ignora el mapeo) · **C8** (fila en blanco crea marca "").

## 🪤 Antes de tener muchos clientes debiendo

- **"Resumen de cuenta" no pagina y se muere sola.** 1.000 clientes = 10 segundos.
  3.000 = 30 segundos y se cae. 6.000 = 1 minuto. **No es un número absurdo para una distribuidora.**
- **A4 / B12** 🔷 — Borrar un cliente deja los pedidos huérfanos y **borra la deuda**; si alguna
  vez pagó, la pantalla se cae con un error crudo. Hoy: 1 de 10 usuarios borraría $11.437,50.
  **¿Lo prohíbo y ofrezco "desactivar", o se lleva los pedidos?**
- **El borrado masivo de clientes se corta a la mitad**, dejando datos borrados a medias.
- **B4** — El botón de WhatsApp reclama plata en pedidos cancelados y a quien ya pagó.
- **El modal de cobro dice "Debe $-3.000"** a quien tiene saldo a favor, y deja guardar un cobro de $0.
- **B6** — El CSV de gastos ignora los filtros de la pantalla.

## 🪤 Cuando dos personas usen el sistema a la vez

- **Dos pestañas del mismo cliente en el carrito → se pierde un producto entero** (3 de 3 rondas).
- **Dos operadores en el mismo pedido → el stock queda mal** (2 de 2).
- **El aumento masivo de precios no es atómico:** durante 1,2 segundos la tienda mostró precios
  viejos y nuevos a la vez, y un pedido salió con las dos listas mezcladas.
- **A7** — El checkout mira el carrito **antes** de resolver los precios. Si el admin sube el
  precio mientras el cliente confirma, el pedido sale **10× más caro**, o **$0 con cero renglones**,
  y el cliente ve "compra confirmada". **4 de 4 casos rotos.**
- ✅ **Lo bueno:** dos personas comprando la última unidad **no** se rompe (0 de 3 rondas).

## 🪤 Cosas que están mal ahora mismo pero no explotan

- **El escritorio del panel: 4 de sus 5 recuadros dan números equivocados.** "Sin stock" y
  "Mercadería en stock" cuentan productos dados de baja; "Mercadería" muestra el precio de venta
  y no lo invertido; el "Balance del mes" cuenta pedidos pendientes sin confirmar ni cobrar, y
  suma el cancelado.
- **B13** — Buscar `%` o `_` devuelve el catálogo entero (1667 en vez de 28). Es calidad de
  búsqueda, no plata: quien busca "100%" recibe 5 filas en vez de 4.
- **D2** — El sitemap publica **21 páginas de marca vacías** a Google, más un ancla muerta.
- **B5a** — El encabezado de la Caja muestra un rango y abajo el total de otro. Solo pasa pegado
  al cartel de error, nunca en silencio.
- **B14** (pedido gigante) · **la imagen de banner que tumba el guardado** · **D1** (el subtotal
  en pantalla, solo lo ve el admin) · **D3b/c/d** (código muerto).

---

# LO QUE SE CAYÓ DEL PLAN

No lo toquemos. Es trabajo que nos ahorramos:

- **B3 — "el listado pierde un producto y repite otro al paginar".** **Era falso.** Recorrieron
  las 70 páginas por HTTP dos veces con la consulta real de la app: **1667 de 1667, cero
  repetidos, cero faltantes.** El adversario lo intentó tumbar por 21 caminos. El auditor
  original midió con un atajo que no es lo que sirve la web.
- **B5 (la mitad)** — La Caja **sí avisa** con fechas al revés o vacías, y ese aviso está desde
  que se creó la pantalla.
- **"La imagen gigante recomprime los banners en cada guardado"** — con imágenes reales frena
  en una sola pasada.
- **D3a** — Las dos columnas de banner sin usar son deliberadas: el comentario dice que quedan
  "por si hiciera falta volver atrás".
- **B7 — "Debe desde"** — No es un bug, es un rótulo. 🔷 Vos elegís: renombrarla a
  "Cliente desde", ordenar por saldo, o dejarla. Las 2 filas que se ven hoy están bien.

---

# LO QUE SIGUE SIN PROBARSE

- **Producción entera.** Nadie tiene acceso a Laravel Cloud. Todo lo de la fase 0 es lista para vos.
- **Ningún backup se restauró jamás.** Sabemos que los 6 archivos existen y cuánto pesan.
  No sabemos si sirven.
- **En el navegador quedaron dos cosas sin probar:** el botón "Agregar" de las tarjetas del
  catálogo (se traba en su animación) y el menú hamburguesa en celular.
- **Las flechas de las filas de productos:** de 67 filas, en 16 hay una flecha que no mueve nada;
  en las otras 51 no se pudo comprobar.
- **Los formularios del panel paso a paso** (crear un banner, una marca, un usuario) no se
  llenaron uno por uno.
- **`app:importar-productos-desde-url`** no se ejecutó a fondo.
- **Un punto de concurrencia se simuló a mano** (el importador sosteniendo una transacción 60
  segundos). Todo lo demás corrió sobre el código real.

---

# LAS DECISIONES QUE ME FALTAN

| # | Decisión | Frena |
|---|---|---|
| 1 | Ficha de producto de baja: ¿404 o cartel "no disponible"? | 2.3 |
| 2 | El desplegable "Estado": ¿se saca o se arregla? | trampa de cancelar |
| 3 | Borrar clientes con pedidos: ¿se prohíbe o se lleva los pedidos? | trampa de clientes |
| 4 | La casilla del importador: ¿frena los precios o le cambio el texto? | trampa de importar |
| 5 | El stock en el importador: ¿lo actualiza o saco la columna? | trampa de importar |
| 6 | Los 28 grupos duplicados: ¿te paso la lista o la mirás vos? | 1.4 |
| 7 | "Debe desde": ¿renombrar, reordenar, o dejar? | nada, es cosmético |
| 8 | ¿El importador acepta una columna de costo? | llenar la pantalla de Precios |

---

## Si tengo que elegir qué hacer primero

1. **`FILAMENT_FILESYSTEM_DISK`.** Estás a un despliegue de perder 1.710 fotos.
2. **El backup, andando y restaurado una vez.** 23 días sin nada.
3. **Las 3 filas del precio fantasma.** El 95% de tu deuda en pantalla es humo.
4. **`MAIL_MAILER` y las contraseñas.** Sin eso no podés lanzar, y con `password` no deberías.
5. **El operador repisando precios históricos** (2.1). Es el peor bug del sistema y es nuevo.

Todo lo demás puede esperar a que decidas cargar stock y abrir la tienda.
