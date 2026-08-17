# Análisis de Cierre — Contexto del proyecto

Repo: https://github.com/jorgedelarocacalix-tech/analisis-cierre  
App live (GitHub Pages, principal): https://jorgedelarocacalix-tech.github.io/analisis-cierre/  
App live (Netlify, en pausa): https://analisis-cierre-laroca.netlify.app — la cuenta de
Netlify llegó a su límite de créditos (2026-08-15) y bloquea deploys nuevos hasta que se
agreguen créditos ahí; mientras tanto usar solo GitHub Pages, que se actualiza solo con
cada `git push` a `main` y no depende de Netlify.  
Stack: Vanilla JS · HTML único (index.html) · Supabase · SheetJS  
Supabase proyecto ID: `ixskgawbpwwxdjnkiixt`  
Supabase URL: `https://ixskgawbpwwxdjnkiixt.supabase.co`  
Supabase key: (quitada de este archivo por seguridad — está en `index.html`,
que sí la necesita para funcionar; no la vuelvas a pegar aquí)

---

## Sucursales

| Código | Nombre | Cartera ID (cobranza) |
|--------|--------|----------------------|
| R1 | La Roca Comercial | `LA_ROCA_COMERCIAL_1` |
| R2 | Roca 1 Inverca | `LA_ROCA_MOTORS_BARRIO_ARRIBA_1` |
| R3 | La Roca Motor 2 | `LA_ROCA_MOTORS_LA_LIBERTAD_2` |
| R4 | Su Mueble Danlí | `SU_MUEBLE` |
| R5 | Su Moto Danlí | `SU_MOTO_DANLI` |

```js
const SNAMES = {R1:'La Roca Comercial',R2:'Roca 1 Inverca',R3:'La Roca Motor 2',R4:'Su Mueble Danlí',R5:'Su Moto Danlí'}
const SUCORDER = ['R1','R2','R3','R4','R5']
const CARTERA_TO_SUC = {
  LA_ROCA_COMERCIAL_1:'R1',
  LA_ROCA_MOTORS_BARRIO_ARRIBA_1:'R2',
  LA_ROCA_MOTORS_LA_LIBERTAD_2:'R3',
  SU_MOTO_DANLI:'R5',
  SU_MUEBLE:'R4'
}
```

---

## Tabs de la app

| Tab | Panel ID | Descripción |
|-----|----------|-------------|
| 📊 Resumen | `panel-resumen` | Cierre junio + Seguimiento julio + Monitor cartera |
| 💰 Ventas/Vendedores | `panel-ventas` | Ranking por sucursal con meta julio |
| 📦 Inventario | `panel-inventario` | Análisis de inventario por sucursal |
| 💳 Cuentas x Cobrar | `panel-cxc` | Cartera de clientes con mora |
| 📈 Proyección | `panel-proyeccion` | Snapshot de cobro proyectado julio |

---

## Supabase — Tablas usadas

### `arqueos`
Registros diarios de caja por sucursal.  
Campos clave: `id, sucursal, fecha, cobrado, gastos, depositado, analisis_json, version, diferencia`

- `analisis_json.ventas[]` → `total_erp` = ventas del día
- `analisis_json.cobros[]` → `tipo` (ABONO/PRIMA/CONTADO), `monto`
- `analisis_json.depositos[]` → `banco`, `tipo`, `monto` (POS si banco contiene 'POS')
- `diferencia` = campo almacenado del arqueo (C−G=D), NO recalcular
- Deduplicación: por `sucursal+fecha`, se toma la versión más alta (`version DESC`)

**Junio 2026:** `fecha >= '2026-06-01' AND fecha <= '2026-06-30'`  
**Julio 2026:** `fecha >= '2026-07-01' AND fecha <= hoy` (seguimiento diario)

### `carteras`
Carteras de cobro con clientes. Campos: `id, clientes (JSONB array)`  
Cada cliente: `nombre, saldo, cuota, dias_mora, tramo, ultimo_pago, etc.`

### `cierre_proyeccion`
Snapshot mensual de proyección de cobro por cartera.  
Campos: `cartera_id, mes_key ('2026-07'), datos (JSONB array), cerrado_por, cerrado_at`

Cada elemento de `datos`:
```json
{
  "nombre": "Juan Perez",
  "saldo": 45000,
  "saldoEsperado": 42000,
  "montoAsesor": 3000,
  "diaAsesor": 20,
  "mora": "MES",
  "moraVal": 0,
  "cuota": 3000,
  "cuotasTrans": 5
}
```
- `diaAsesor` = día del mes en que se espera el pago
- `montoAsesor` = monto que debe cobrar ese mes
- `mora` = tramo: MES / 60 / 90 / 120 / 150 / VENCIDO / INACTIVO
- `moraVal` = monto en mora

### `cierre_ventas`
Archivos Excel de ventas por sucursal, guardados en Supabase.  
PK: `(mes_key, sucursal)` — `mes_key = '2026-06'`  
Campos: `mes_key, sucursal, rows (JSONB), headers (JSONB), updated_at`

### `cierre_inventario`
Igual que `cierre_ventas` pero para inventario.

### `proyecciones`
Proyecciones individuales por cliente (tabla antigua, menos usada).  
Campos: `id, cartera_id, cliente_nombre, fecha_proyectada, monto_proyectado, gestor`

---

## Funciones clave en index.html

### `aggBySuc(list)` — agrega arqueos por sucursal
Retorna `{R1: {cobrado, gastos, depositado, ventas_erp, dias, cob_abono, cob_prima, cob_contado, pos, diferencia}}`

- `cob_abono` = cobros tipo ABONO (pagos CXC — lo que baja cartera)
- `cob_prima` = cobros tipo PRIMA (entrada de nuevos créditos)
- `cob_contado` = cobros tipo CONTADO
- `diferencia` = suma de `diferencia` almacenada por arqueo (no recalcular)

### `renderResumen()`
Renderiza el tab Resumen con 4 secciones:
1. **KPIs junio** — cobrado, ventas ERP, gastos, depositado, POS
2. **Seguimiento julio** — ventas: meta (+10% jun) vs vendido; cobros CXC: proy. mes / proy. al díaX / cobrado abonos
3. **Monitor de cartera** — KPIs mora, tramos, filtro por sucursal con botones
4. **Botón ⬇ Descargar Excel** — exporta 3 hojas: Ventas Julio, Cobros CXC, Monitor Cartera

### `renderVentas(allRows)` — ranking vendedores
Muestra secciones **independientes por sucursal** (no combinadas).  
Normalización de nombres solo dentro de la misma sucursal.  
Datos vienen de `xlsVentasSuc` (cargado desde `cierre_ventas` Supabase).

### `fetchProyeccion()`
Lee `cierre_proyeccion` filtrado a `mes_key = '2026-07'`.  
Guarda en `let proyeccion = []`.

### `detectHeaderRow(rows)`
Detecta la fila de encabezados en Excel usando coincidencia exacta de palabras clave.  
`_fromSupabase` flag = ya viene con headers correctos, saltar detección.

### `saveToSupabase(tabla, suc, rows)` / `loadFromSupabase()`
Persiste/carga datos Excel en `cierre_ventas` y `cierre_inventario`.  
Aplica `detectHeaderRow` antes de guardar para evitar guardar filas de título.

---

## Lógica de negocio importante

### Cobros CXC vs arqueos
- **Proyectado al día X** = suma de `montoAsesor` de clientes con `diaAsesor <= X` (de `cierre_proyeccion`)
- **Cobrado real** = `cob_abono` de arqueos julio = SOLO abonos, sin primas ni contado
- El cobrado puede ser mayor que el proyectado al día X porque incluye pagos anticipados y mora de meses anteriores

### R2 (Roca 1 Inverca / Barrio Arriba)
Todos sus clientes tienen `diaAsesor > 8`, por eso proyectado días 1-8 = L0.  
Su cobranza fuerte es en la segunda quincena.

### R1 (La Roca Comercial)
La mayoría del cobro proyectado cae entre días 20-31 (L345,499 de L520,364 total julio).

### Ventas ERP vs junio
Meta julio = `ventas_erp_junio × 1.10` (+10%)  
Ritmo = `(vendido_julio / meta) / (día_actual / 31)` — si > 1 va adelantado

### C−G=D (cuadre de caja)
Usa el campo `diferencia` almacenado por arqueo, NO recalcula `cobrado - gastos - depositado`.  
Tolerancia: diferencia < L5 = ✅

### Monitor de cartera — tramos
| Tramo | Color | Riesgo |
|-------|-------|--------|
| MES | Verde | Al corriente |
| 60 | Ámbar oscuro | Medio |
| 90 | Ámbar | Medio-alto |
| 120 | Rojo | Alto |
| 150 | Rojo | Crítico |
| VENCIDO | Rojo oscuro | Crítico |
| INACTIVO | Gris | Pérdida |

---

## Archivos

```
/Users/jorgecalix/analisis-cierre/
  index.html   — App completa, único archivo
  CONTEXT.md   — Este archivo
```

---

## Flujo de datos

```
Supabase arqueos (junio) ──────────→ Cierre junio (KPIs históricos)
Supabase arqueos (julio, diario) ──→ Seguimiento julio (ventas + cobros)
Supabase cierre_proyeccion (07) ───→ Cobros proyectados + Monitor cartera
Supabase carteras ─────────────────→ Tab CXC (clientes en mora)
Supabase cierre_ventas/inventario ─→ Tab Ventas (ranking vendedores) + Tab Inventario
Excel upload (si no está en SB) ───→ cierre_ventas / cierre_inventario (persiste)
```

---

## Notas técnicas

- No hay build tools — push a `main` = deploy a GitHub Pages
- SheetJS CDN para leer/escribir Excel desde el browser
- RLS Supabase: políticas abiertas con `sb_publishable_` key
- Deduplicación arqueos: `Map` por `sucursal_fecha`, versión más alta gana
- `exportarResumen()` genera Excel con SheetJS en el browser y lo descarga directamente
- `filterMon(suc, btn)` filtra el monitor de cartera por sucursal sin re-renderizar

---

## Netlify — Deploy manual

**Site ID:** `ab7e8f0a-ee23-4c6c-9c21-6d3a128965d4`  
**Token:** ⚠️ el token que estaba aquí quedó expuesto en un repo público y fue
revocado de este archivo. Genera uno nuevo en Netlify (User settings →
Applications → Personal access tokens), revoca el viejo (`nfp_a3vnq...`), y
guárdalo fuera del repo (variable de entorno o gestor de secretos) — nunca
lo pegues de nuevo en un archivo versionado con git.  
**URL:** https://analisis-cierre-laroca.netlify.app

Para re-deployar después de cambios en `index.html`:

```bash
cd /Users/jorgecalix/analisis-cierre
zip -r /tmp/analisis-cierre.zip index.html CONTEXT.md
curl -X POST "https://api.netlify.com/api/v1/sites/ab7e8f0a-ee23-4c6c-9c21-6d3a128965d4/deploys" \
  -H "Authorization: Bearer $NETLIFY_TOKEN" \
  -H "Content-Type: application/zip" \
  --data-binary @/tmp/analisis-cierre.zip
```
