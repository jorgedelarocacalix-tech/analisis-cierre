# Análisis de Cierre — versión PHP + MySQL (para VPS con cPanel)

Esta carpeta es una versión paralela de la app, adaptada para correr en un
VPS con cPanel usando PHP + MySQL en lugar de Supabase. **No reemplaza** el
`index.html` de la raíz del repo (ese sigue funcionando igual, en Netlify /
GitHub Pages con Supabase, sin cambios).

## Qué hace esta app

Es un dashboard de análisis (no un sistema de captura): junta datos que
generan **otras** apps de La Roca y arma 5 vistas —

- **Resumen**: cierre de junio 2026 + seguimiento diario de julio (ventas y
  cobros CXC) + monitor de cartera por tramos de mora, con export a Excel.
- **Ventas/Vendedores**: sube un Excel de ventas por sucursal (5 sucursales)
  y arma un ranking de vendedores contra su meta.
- **Inventario**: sube un Excel de inventario por sucursal y detecta
  productos por agotarse, capital parado y catálogo duplicado.
- **Cuentas x Cobrar**: cartera de clientes agrupada por tramo de mora.
- **Proyección**: snapshot de cobro proyectado de julio por cartera.

## Cambios respecto a la versión de Supabase

- Backend: PHP + PDO/MySQL en vez de la REST API de Supabase.
- Autenticación: se agregó sesión de PHP con PIN (`login.php` /
  `cambiar_pin.php` / `auth.php`), igual que las demás apps de La Roca ya
  migradas. **La versión original no tenía ningún login** (usaba la key
  pública de Supabase con RLS abierto) — este PIN es una capa nueva, no
  quitamos nada, solo añadimos una protección básica de acceso.
- No se encontró ninguna API de pago (Claude/OpenAI/etc.) en el
  `index.html` original — solo Supabase (para datos) y SheetJS por CDN
  (librería gratuita para leer/escribir Excel en el navegador, se mantuvo
  igual). El único gasto recurrente que esta migración elimina es Supabase.
- Se corrigió un bug de scope de JavaScript detectado al portar el código:
  en el original, la función `aggBySuc()` estaba anidada dentro de
  `renderResumen()`, así que `exportarResumen()` (una función aparte) no
  podía llamarla — esto probablemente rompía en silencio el botón
  "⬇ Descargar Excel" en producción. Aquí se subió `aggBySuc()` a nivel
  superior para que ambas funciones la compartan. El resto de la lógica de
  negocio (fórmulas, agregaciones, deduplicación por versión, etc.) se
  portó sin cambios.
- Los nombres internos `saveToSupabase()` / `loadFromSupabase()` /
  `_fromSupabase` se dejaron igual a propósito (para minimizar el diff con
  el original) pero ya no llaman a Supabase — llaman a los endpoints
  locales `api/ventas.php` y `api/inventario.php`.

## Modelo de datos y de dónde viene cada tabla

Importante: de las 5 tablas reales, **solo 2 las escribe esta misma app**
(`cierre_ventas` y `cierre_inventario`, al subir los Excel). Las otras 3
(`arqueos`, `carteras`, `cierre_proyeccion`) las alimentan **otras** apps de
la suite de La Roca (el sistema de arqueos/caja diario y el CRM/cobranza) —
esta app de Análisis de Cierre solo las **lee**. Si esas otras apps todavía
no están migradas a este mismo servidor/base de datos, estas 3 tablas
quedarán vacías después de importar `schema.sql`, y el dashboard mostrará
"Sin datos" hasta que:

1. Se migren también esas otras apps a este mismo MySQL (recomendado a
   futuro, así todas comparten la base), o
2. Se carguen los datos a mano vía phpMyAdmin, o
3. Se usen los endpoints `POST /api/arqueos.php`, `POST /api/carteras.php`
   y `POST /api/proyeccion.php` (aceptan el mismo formato JSON que
   Supabase) desde un script de importación puntual.

| Tabla | Quién la llena | Esta app |
|---|---|---|
| `arqueos` | Sistema de arqueos/caja diario (otra app) | Solo lee |
| `carteras` | CRM / sistema de cobranza (otra app) | Solo lee |
| `cierre_proyeccion` | CRM / sistema de cobranza (otra app) | Solo lee |
| `cierre_ventas` | Esta misma app (subida de Excel) | Lee y escribe |
| `cierre_inventario` | Esta misma app (subida de Excel) | Lee y escribe |
| `proyecciones` | Tabla antigua/legada mencionada en `CONTEXT.md`, ya no la usa la UI actual | Se crea vacía, sin endpoint propio |

## Estructura

```
php-mysql/
  schema.sql          — tablas MySQL (importar en phpMyAdmin)
  config.php           — credenciales de la base de datos (editar antes de subir)
  auth.php             — helpers de sesión/autenticación
  login.php            — valida el PIN y abre sesión
  cambiar_pin.php       — cambia el PIN (requiere sesión activa)
  api/
    arqueos.php         — GET (rango de fechas) / POST (nueva versión de un arqueo)
    carteras.php         — GET (todas) / POST (crear o reemplazar una cartera)
    proyeccion.php        — GET (?mes_key=) / POST (crear o reemplazar snapshot del mes)
    ventas.php             — GET (?mes_key=) / POST (guardar Excel de ventas de una sucursal)
    inventario.php          — GET (?mes_key=) / POST (guardar Excel de inventario de una sucursal)
  index.html              — la app (mismo diseño y tabs, con PIN, sin Supabase)
```

## Pasos para desplegar en cPanel

1. **Base de datos**: en cPanel → *MySQL® Databases*, crea una base de datos
   y un usuario con todos los privilegios sobre ella. Anota el nombre de la
   base, el usuario y la contraseña.
2. **Importar el esquema**: en cPanel → *phpMyAdmin*, selecciona la base de
   datos y ejecuta el contenido de `schema.sql` (pestaña "SQL"). Requiere
   MySQL 5.7+ o MariaDB 10.2+ por el uso de columnas tipo `JSON` (cualquier
   hosting cPanel moderno lo soporta).
3. **Subir los archivos**: sube toda esta carpeta (`php-mysql/`) al
   directorio público de tu dominio o subdominio (ej.
   `public_html/analisis-cierre/`).
4. **Configurar credenciales**: edita `config.php` en el servidor y reemplaza
   `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS` con los datos del paso 1.
5. **PIN inicial**: el PIN por defecto es `1234`. Se puede cambiar llamando
   a `cambiar_pin.php` (no hay pantalla de configuración en la UI todavía;
   si quieres un botón para esto dentro de la app, se puede agregar
   fácilmente siguiendo el mismo patrón que Fintrack).
6. Abre la URL de tu dominio/subdominio en el navegador — debe pedir el PIN
   antes de mostrar el dashboard.
7. Haz clic en "↺ Cargar datos" para traer la información desde MySQL. Si
   las tablas `arqueos`, `carteras` o `cierre_proyeccion` están vacías
   (ver sección anterior), esas secciones mostrarán "Sin datos" hasta que
   se carguen desde la app correspondiente o manualmente.

## Migrar los datos existentes (opcional)

Los datos que hoy están en Supabase (proyecto `ixskgawbpwwxdjnkiixt`) no se
migran automáticamente. Para pasarlos:

- `arqueos`, `carteras`, `cierre_proyeccion`, `cierre_ventas`,
  `cierre_inventario`: se pueden exportar desde el editor de tablas de
  Supabase (a CSV o JSON) e importar a MySQL con phpMyAdmin. Ojo con las
  columnas JSON (`analisis_json`, `clientes`, `datos`, `headers`, `filas`
  en MySQL — se llamaba `rows` en Supabase, se renombró porque `ROWS` es
  palabra reservada en MySQL 8): deben quedar como texto JSON válido en la
  celda al importar.
- Si prefieres no tocar phpMyAdmin a mano, puedo ayudarte a armar un script
  puntual de migración (Supabase → MySQL) cuando tengas listo el acceso al
  servidor y hayas decidido si vas a migrar también las otras apps que
  alimentan `arqueos` y `carteras`/`cierre_proyeccion`.

## Dato a revisar por el dueño

- El `CONTEXT.md` del repo original tiene, en texto plano, un **token de
  despliegue de Netlify** (`nfp_...`) y las credenciales de Supabase. No se
  tocó ese archivo (no se modifica nada fuera de `php-mysql/`, según lo
  acordado), pero conviene rotar/revocar ese token de Netlify una vez que
  el sitio ya no se despliegue ahí, ya que quedó expuesto en el repo.
