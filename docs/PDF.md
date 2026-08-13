# Construcción de PDF — SJ LegalSuite

Documentación técnica de cómo se generan los PDFs disciplinarios: motor, plantillas Blade, paginación, rutas, pruebas y calibración Dompdf.

---

## 1. Visión general

```
Datos del caso / borrador
    → Servicio de dominio (buildViewData)
        → Vista Blade (HTML Letter)
            → HtmlLetterPdfGenerator::fromView()
                → LetterPdfDriver::current()
                    ├─ dompdf     → DompdfLetterPdfDriver::render()
                    └─ browsershot → renderDirect() | Artisan CLI | cola (legado)
                → string binario PDF
                    → HTTP (inline/descarga) | almacenamiento en expediente
```

**Principios de diseño**

| Principio | Descripción |
|-----------|-------------|
| Fachada única | Todo pasa por `HtmlLetterPdfGenerator`; controladores y servicios **no** eligen motor. |
| HTML primero | Las plantillas son Blade + CSS; el PDF es render de ese HTML. |
| Letter fijo | Salida siempre tamaño carta (`8.5in × 11in`). |
| Paginación explícita (formas largas) | FO-GJ-03 y FO-GJ-04 calculan páginas en PHP antes de renderizar (Dompdf no es fiable para encabezados repetidos + firmas). |
| Activos embebidos | Logo y fuentes Liberation van incrustados (data URI / TTF registrados); no dependen del SO del servidor. |
| Tablas, no flex crítico | Bloques sensibles (meta, firmas) usan `<table>` para alturas predecibles en Dompdf. |

---

## 2. Motor PDF (`PDF_DRIVER`)

### Configuración

| Archivo | Contenido |
|---------|-----------|
| `.env` / `.env.example` | Variables `PDF_*` |
| `config/services.php` | Clave `pdf` |

| Variable | Valores | Uso |
|----------|---------|-----|
| `PDF_DRIVER` | `dompdf` \| `browsershot` | Motor principal. **Producción Hostinger: `dompdf`** (inmediato, sin Chrome). |
| `PDF_USE_QUEUE` | `true` \| `false` | Con Browsershot: encola FO-GJ-03/51 en petición web. Ignorado con Dompdf. |
| `PDF_VIA_ARTISAN_CLI` | `true` \| `false` | Browsershot delega a `php artisan disciplinary:render-pdf` desde web. |
| `PDF_NO_SANDBOX` | `true` \| `false` | Flags Chrome en hosting compartido. |
| `PDF_CHROME_PATH`, `NODE_BINARY`, `NPM_BINARY`, `PDF_CLI_PHP` | rutas | Resolución de binarios. |
| `PDF_BROWSER_TIMEOUT` | segundos (ej. `120`) | Timeout Browsershot. |
| `PDF_VIEWPORT_WIDTH`, `PDF_VIEWPORT_HEIGHT` | px | Viewport Browsershot. |

### Clases del motor

| Clase | Ruta | Rol |
|-------|------|-----|
| `LetterPdfDriver` | `app/Support/Pdf/LetterPdfDriver.php` | Lee `PDF_DRIVER`; `usesDompdf()`, `usesBrowsershot()`, `shouldUseQueue()`. |
| `HtmlLetterPdfGenerator` | `app/Support/Pdf/HtmlLetterPdfGenerator.php` | `fromView()`, `fromHtml()`, ramas Dompdf/Browsershot. |
| `DompdfLetterPdfDriver` | `app/Support/Pdf/DompdfLetterPdfDriver.php` | barryvdh/laravel-dompdf; registra Liberation; inyecta CSS de refuerzo. |
| `HtmlLetterPdfArtisanCliRenderer` | `app/Support/Pdf/HtmlLetterPdfArtisanCliRenderer.php` | Subproceso artisan para LiteSpeed. |
| `BrowsershotBinaryResolver` | `app/Support/Pdf/BrowsershotBinaryResolver.php` | Node, npm, Chrome. |
| `PdfCliPhpBinaryResolver` | `app/Support/Pdf/PdfCliPhpBinaryResolver.php` | PHP para CLI render. |

### Selección en `HtmlLetterPdfGenerator::fromHtml`

1. **`dompdf`** → render síncrono (recomendado en Hostinger).
2. **`browsershot` + `PDF_VIA_ARTISAN_CLI` + petición web** → CLI artisan.
3. **`browsershot` resto** → `renderDirect()` (Spatie Browsershot + Puppeteer).

### Comandos de diagnóstico

```bash
php artisan disciplinary:pdf-check    # driver, Node, fuentes, logo
php artisan disciplinary:pdf-smoke      # genera PDF real de prueba
php artisan disciplinary:render-pdf     # render CLI (cola / artisan path)
php artisan disciplinary:process-pdf-queue  # worker cola (cron)
```

### Cola Browsershot (legado)

Solo aplica con `PDF_DRIVER=browsershot` y `PDF_USE_QUEUE=true`:

- Jobs: `ProcessFoGj03PdfJob`, `ProcessFoGj51PdfJob` (cola `pdf`)
- Stores: `FoGj03PdfQueueStore`, `FoGj51PdfQueueStore`
- Pantallas de espera: `fo-gj-03-pdf-queue-wait`, `fo-gj-51-pdf-queue-wait`

Con **`PDF_DRIVER=dompdf`** la generación es **síncrona** en la misma petición HTTP (vista previa y descarga inmediatas).

---

## 3. Modelo de página Letter (CSS compartido)

Archivo base: `resources/views/disciplinary/forms/partials/official-letter-pdf-styles.blade.php`

### Por qué este modelo

Dompdf **no respeta** `box-sizing: border-box` al combinar `width: 100%` + `padding` → el borde derecho se corta. Solución:

```css
@page { size: Letter; margin: 0; }
.ogj-page {
    width: 7.5in;      /* 8.5in − 2×0.5in */
    margin: 0.5in;
    padding: 0;
}
```

### Variables tipográficas

| Variable CSS | Valor | Uso |
|--------------|-------|-----|
| `--ogj-font-body` | `12px` | Cuerpo |
| `--ogj-font-meta` | `11px` | Tabla meta (código, fecha, versión, página) |
| `--ogj-font-title` | `13px` | Título del documento |
| `--ogj-font-micro` | `10px` | Texto auxiliar |

Familia: **Liberation Sans** embebida como `SjPdfSans` (`EmbeddedPdfFont::FAMILY_SANS`).

### Fuentes y logo

| Recurso | Ruta |
|---------|------|
| TTF Liberation | `resources/fonts/pdf/` |
| Registro Dompdf | `DompdfLetterPdfDriver::registerLiberationFonts()` |
| Cache Dompdf | `storage/fonts/` (debe ser escribible) |
| Logo expediente/PDF | `public/images/logo solo.png` (`DisciplinaryAssets::LOGO_RELATIVE_PATH`) |
| Logo embebido | `EmbeddedPublicAsset` → data URI en vistas |

### Shell de una sola página

Formas cortas usan el componente:

`resources/views/components/disciplinary/forms/official-letter-pdf-shell.blade.php`

Incluye encabezado una vez y deja fluir el cuerpo (Dompdf suele producir 1 hoja).

### Formas multipágina

FO-GJ-03 y FO-GJ-04 **no** usan el shell. Cada hoja es un `<div class="ogj-page ogj-page-break">` con su propio encabezado Blade y meta **«Página N de M»**.

```css
.ogj-page-break { page-break-before: always; break-before: page; }
```

**No usar `position: fixed`** para letterhead en Dompdf (comportamiento inestable).

---

## 4. Catálogo de formatos PDF

Registro de plantillas en blanco: `OfficialFormsCatalog::htmlBlankPdfRegistry()`  
Factory de respuesta HTTP: `OfficialFormHtmlBlankPdfFactory`

| Código | Documento | Vista diligenciada | Vista en blanco | Servicio |
|--------|-----------|-------------------|-----------------|----------|
| **FO-GJ-51** | Informe disciplinario | `fo-gj-51-filled-download` | `fo-gj-51-blank-download` | `FoGj51PdfBuilder` |
| **FO-GJ-03** | Citación | `fo-gj-03-filled-download` | `fo-gj-03-blank-download` | `FoGj03CitationService` |
| **FO-GJ-04** | Acta de diligencia | `fo-gj-04-filled-download` | `fo-gj-04-blank-download` | `FoGj04DiligenceActaService` |
| **FO-GJ-44** | Constancia inasistencia | `fo-gj-44-filled-download` | `fo-gj-44-blank-download` | `FoGj44ConstanciaService` |
| **FO-GJ-54** | Reprogramación | `fo-gj-54-filled-download` | `fo-gj-54-blank-download` | `FoGj54ReprogramacionService` |
| **ACTA-COMITE** | Acta comité | `comite-acta-filled-download` | `comite-acta-blank-download` | `ComiteActaService` |
| **FO-GJ-45** | Acta de archivo (terminación) | `fo-gj-45-filled-download` | `fo-gj-45-blank-download` | `DecisionComunicadoService` / `FoGj45DraftService` |
| **FO-GJ-46** | Llamado de atención | `fo-gj-46-filled-download` | `fo-gj-46-blank-download` | `DecisionComunicadoService` / `FoGj46DraftService` |
| **FO-GJ-47** | Suspensión | `fo-gj-47-filled-download` | `fo-gj-47-blank-download` | `DecisionComunicadoService` / `FoGj47DraftService` |

### Variantes firmadas (supervisor)

| Vista | Servicio |
|-------|----------|
| `fo-gj-03-signed-notification-download` | `CitationNotificationSigningService` |
| `fo-gj-45-signed-notification-download` | `DecisionNotificationSigningService` |
| `fo-gj-46-signed-notification-download` | `DecisionNotificationSigningService` |
| `fo-gj-47-signed-notification-download` | `DecisionNotificationSigningService` |

### Estilos por formulario

| Formulario | Parcial de estilos |
|------------|-------------------|
| Compartido FO-GJ | `official-letter-pdf-styles.blade.php` |
| FO-GJ-03 | `fo-gj-03-pdf-styles.blade.php` |
| FO-GJ-04 | `fo-gj-04-pdf-styles.blade.php` |
| ACTA-COMITE | `comite-acta-pdf-styles.blade.php` (12 pt Times; `zeroPageMargins` en generador) |

Raíz de vistas: `resources/views/disciplinary/forms/`

---

## 5. Rutas y flujo de vista previa

### PDF por expediente (`routes/web.php`, prefijo `disciplinary`)

| Ruta | Controlador | Query |
|------|-------------|-------|
| `disciplinary.cases.fo-gj-03.pdf` | `FoGj03CaseController@download` | `?inline=1` → iframe |
| `disciplinary.cases.fo-gj-04.pdf` | `FoGj04CaseController@download` | `?inline=1` |
| `disciplinary.cases.fo-gj-44.pdf` | `FoGj44CaseController@download` | `?inline=1` |
| `disciplinary.cases.fo-gj-54.pdf` | `FoGj54CaseController@download` | `?inline=1` |
| `disciplinary.cases.comite-acta.pdf` | `ComiteActaCaseController@download` | `?inline=1` |
| `disciplinary.cases.decision-comunicado.pdf` | `DecisionComunicadoCaseController@download` | `?inline=1` |

### Catálogo Formatos (en blanco)

| Ruta | Controlador |
|------|-------------|
| `disciplinary.formats.preview/{code}` | `OfficialFormPreviewController` (inline) |
| `disciplinary.formats.descarga-en-blanco/{code}` | `OfficialFormBlankDownloadController` (attachment) |

### Flujo típico (ej. FO-GJ-04)

1. Usuario abre **Vista previa** en Livewire (`CaseDetail`).
2. Modal con iframe → `route('disciplinary.cases.fo-gj-04.pdf', ['case' => $case, 'inline' => 1])`.
3. `Gate::authorize('previewFoGj04')`.
4. `FoGj04DiligenceActaService::downloadPdf()` → `buildViewData()` + `HtmlLetterPdfGenerator::fromView(...)`.
5. Respuesta `Content-Type: application/pdf`, `Content-Disposition: inline`.

### Flujo datos → binario (ejemplo FO-GJ-04)

```
FoGj04DraftService (fo_gj_04_payload en DisciplinaryCase)
    → FoGj04DiligenceActaService::buildViewData()
        → merge: trabajador, cargos desde FO-GJ-03, firmas, preguntas
    → fo-gj-04-filled-download.blade.php
        → fo-gj-04-body.blade.php
            → FoGj04PagePlanner::plan() → N páginas .ogj-page
    → HtmlLetterPdfGenerator::fromHtml()
    → PDF binario
```

---

## 6. FO-GJ-03 — Paginación (`FoGj03DocumentPaginator`)

**Archivo:** `app/Support/Disciplinary/FoGj03DocumentPaginator.php`  
**Vista cuerpo:** `resources/views/disciplinary/forms/partials/fo-gj-03-body.blade.php`

### Contenido dinámico (fuera del paginador)

- **Artículos/numerales:** `FoGj03CitationArticleResolver` + plantillas por falta (`CitationFaultTemplateService`; UI Ajustes · Artículos).
- **Género gramatical:** `WorkerLegalPhrasing` inyecta saludo, verbos y párrafo de traslado según `employees.gender` (también FO-GJ-04/54).

### Contrato de producto

1. **Cuerpo continuo** — cargos y párrafo de traslado/evidencia se trocean entre hojas.
2. **Encabezado en cada hoja** — partial `fo-gj-03-header` por `.ogj-page`.
3. **Solo firmas atómicas** — `attachClosingAtomically()` mueve el bloque completo de cierre si no cabe.
4. **«Página N de M»** — `finalizePageMeta()`; debe coincidir con hojas físicas Dompdf.

### Constantes de calibración (Dompdf + 12px)

| Constante | Valor | Significado |
|-----------|-------|-------------|
| `PAGE_UNITS` | 74 | Capacidad de cuerpo bajo encabezado |
| `CLOSING_SAFETY_UNITS` | 5 | Holgura antes de firmas en última hoja |
| `OPENING_UNITS` | 9 | Bloque de apertura |
| `CHARGES_LEAD_UNITS` | 4 | Intro de cargos |
| `CHARGES_TAIL_UNITS` | 3 | Cierre de cargos |
| `ARTICLES_BASE_UNITS` | 2 | Base artículos |
| `UNITS_PER_ARTICLE` | 3.2 | Por línea de artículo |
| `EVIDENCE_LEAD_UNITS` | 4 | Intro evidencia |
| `CLOSING_UNITS` | 11 | Bloque firmas |
| `WITNESSES_UNITS` | 10 | Extra si testigos rechazados |
| `CHARS_PER_LINE` | 60 | Estimación de líneas |
| `TEXT_GROWTH_FACTOR` | 1.25 | Factor interlineado Dompdf |

### Parciales Blade

| Partial | Contenido |
|---------|-----------|
| `fo-gj-03-opening` | Datos trabajador, fecha diligencia |
| `fo-gj-03-charges` | Descripción de cargos (chunks) |
| `fo-gj-03-articles` | Numerales art. 66/68/76 |
| `fo-gj-03-evidence` | Traslado probatorio |
| `fo-gj-03-closing-signatures` | Cordialmente + firmas (atómico) |

### Encabezado

- Altura fija meta: **76px** (4 filas × 19px).
- Columnas tabla: **25% / 50% / 25%**.
- Forma canónica corta: **1 hoja** Letter.

---

## 7. FO-GJ-04 — Paginación (`FoGj04PagePlanner`)

**Archivo:** `app/Support/Disciplinary/FoGj04PagePlanner.php`  
**Vista cuerpo:** `resources/views/disciplinary/forms/partials/fo-gj-04-body.blade.php`

Mismo contrato que FO-GJ-03, extendido para acta de diligencia con cuestionario.

### Constantes de calibración

| Constante | Valor | Significado |
|-----------|-------|-------------|
| `PAGE_UNITS` | 62 | Capacidad (menor que FO-GJ-03: intro más denso) |
| `CLOSING_SAFETY_UNITS` | 5 | Holgura antes de firmas |
| `INTRO_LEAD_UNITS` | 11 | Apertura + partes (empleador/trabajador) |
| `CHARGES_TAIL_UNITS` | 2 | Cierre bloque cargos |
| `TERMS_LEAD_UNITS` | 2 | Intro lista términos |
| `INTRO_MANIFESTATION_UNITS` | 2 | «EL TRABAJADOR manifestó…» |
| `INTRO_QUIZ_LEAD_UNITS` | 2 | Lead del cuestionario |
| `QUESTION_TITLE_UNITS` | 2 | Base por pregunta |
| `CLOSING_TEXT_UNITS` | 3 | Párrafo de cierre |
| `SIGNATURES_UNITS` | 12 | Tabla firmas |
| `CHARS_PER_LINE` | 64 | Estimación |
| `TEXT_GROWTH_FACTOR` | 1.28 | Factor interlineado |

### Bloques del cuerpo

| Tipo | Troceable | Notas |
|------|-----------|-------|
| `intro_lead` | No | Apertura fija |
| `charges_text` | Sí | Descripción de cargos (desde FO-GJ-03) |
| `term_text` (1–5) | Sí | Textos en `FoGj04PagePlanner::termTexts()` |
| `intro_manifestation` | No | Manifestación del trabajador |
| `intro_quiz_lead` | No | Intro al cuestionario |
| `question_pair` | Parcial | **Pregunta + R: atómicas**; respuesta larga solo trocea el texto de la R: |
| `closing_text` | No | Cierre antes de firmas |
| Firmas | No | `attachSignaturesAtomically()` |

### Reglas de pregunta + respuesta

- Cada ítem del cuestionario es `{ question, answer }` (`FoGj04DraftService`).
- El planner emite bloques `question_pair`.
- Si pregunta + respuesta corta **no caben** en la hoja actual → **salto de página antes** del par (nunca título en p.1 y `R:` en p.2).
- Se puede partir **entre** pregunta 1 y 2, 2 y 3, etc.
- Respuesta larga: `isAnswerContinuation` en `fo-gj-04-question-item.blade.php`.
- CSS refuerzo: `.ogj-04-question { page-break-inside: avoid; }`.

### Parciales Blade

| Partial | Rol |
|---------|-----|
| `fo-gj-04-intro` | Partes, cargos, términos 1–5, manifestación, lead cuestionario |
| `fo-gj-04-question-item` | Una pregunta (título + R:) |
| `fo-gj-04-closing-signatures` | Cierre + firmas empleador/trabajador |
| `fo-gj-04-header` | Letterhead por página |

### Plantilla en blanco vs diligenciada

| Vista | `blankForDownload` |
|-------|---------------------|
| `fo-gj-04-blank-download` | `true` (guías `_ _ _ _`) |
| `fo-gj-04-filled-download` | `false` |

---

## 8. FO-GJ-51 — Informe disciplinario (pantalla + PDF)

**Componente único:** `resources/views/components/disciplinary/forms/fo-gj-51-preview.blade.php`  
**Servicio PDF:** `app/Services/Disciplinary/FoGj51PdfBuilder.php` → `fo-gj-51-filled-download`  
**Formulario web:** `fo-gj-51-informe-body` → modal (`fo-gj-51-informe-modal-shell`) o página `fo-gj-51-fill`

### Dos modos de render (misma plantilla)

| Modo | Activación | Uso |
|------|------------|-----|
| **Pantalla interactiva** | `renderAsPdf=false`, `blankForDownload=false` | Diligenciar, capturar firma, enviar a revisión |
| **PDF Dompdf** | `renderAsPdf=true` en `fo-gj-51-filled-download` | `FoGj51PdfBuilder::buildBinary()` |
| **Blanco (catálogo)** | `blankForDownload=true` | Descarga / preview en Formatos |

Clases en el contenedor:

- `.fo51-interactive` — formulario editable (flex en grilla personal y faltas; OK en navegador).
- `.fo51-pdf` — reglas compactas solo para Dompdf (sin flexbox).
- `.fo51-letter-screen-host` + `.ogj-letter-screen-sheet` — **solo pantalla**, no PDF.

### Pantalla: hoja Letter centrada

El formulario interactivo se envuelve en:

```html
<div class="ogj-letter-screen-scaler">
  <div class="ogj-letter-screen-sheet"> <!-- 8.5in × min 11in, sombra -->
    <official-letter-pdf-shell …>
```

- Centrado horizontal (`justify-content: center` en el scaler).
- Escala Alpine si el modal es más estrecho que 8.5″ (mismo patrón que FO-GJ-03 en evidencias).
- Dentro de la hoja, `.ogj-page` usa `width: 100%`, `padding: 0.5in`, `min-height: 10in` (reglas en `official-letter-pdf-styles` bajo `.ogj-letter-screen-sheet`).

**No** aplica al PDF: Dompdf sigue con `.ogj-page { width: 7.5in; margin: 0.5in }` sin el envoltorio de pantalla.

### PDF: alineación con la plantilla (1 hoja Letter)

Dompdf **no soporta flexbox** de forma fiable. El modo `.fo51-pdf` evita flex y controles HTML pesados:

| Bloque | Pantalla | PDF (`renderAsPdf`) |
|--------|----------|---------------------|
| Datos trabajador | `fo51-personal-inner` (flex) | `display: table` en etiqueta + valor |
| Faltas | `fo51-fault-line` (flex + checkbox) | Mini-tabla `fo51-fault-line-tbl`: texto izq. + casilla al final (`fo51-fault-chk-box`) |
| Observaciones | `<textarea rows="10">` | `<div class="fo51-obs-pdf">` (altura según texto, min ~72px) |
| Elaborador / jurídico | `<input>` | `<span class="fo51-static">` |
| Espaciado entre bloques | `margin-bottom: 11px` | `7px` en `.fo51-pdf .fo51-block` |

Casillas de faltas en PDF: cuadrado con borde y **X** si está marcada (no `<input type="checkbox">`).

### Grilla datos del trabajador (estructura fija)

Tabla 4 columnas en `fo-gj-51-preview`:

- Fila 1: **CC:** (25%) + **NOMBRE:** (`colspan="3"`).
- Fila 2: **CARGO:** | **CIUDAD:** | **TURNO:** | **PUESTO:** (25% c/u).

Etiquetas inline con `fo51-inline-lbl` + valor en `fo51-personal-val`. En móvil, `fo-gj-51-screen-mobile` apila celdas (`@media max-width: 767px`).

### Firma del elaborador

- Pantalla digitada: `sjFo51PreparerSignature()` + `signature-capture-modal-alpine` → `fo51_preparer_signature` (data URI PNG).
- PDF generado: `<img class="fo51-signature-img">` si hay firma capturada.
- Validación: `PngSignatureDataUri` en `StoreFoGj51InformePdfRequest` / `FoGj51ProcessRequest` (**solo** acciones `pdf` y `enviar`; **no** en `cargar`).

### 8.1 Cargar PDF externo (modal standalone + PDF.js)

Flujo distinto al digitado: el usuario **adjunta** un FO-GJ-51 ya generado (escáner / otro sistema) y metadatos para la cola de revisión.

| Pieza | Rol |
|-------|-----|
| `fo-gj-51-pdf-upload-modal.blade.php` | UI del modal (Livewire o página completa) |
| `fo51-pdf-upload-file.js` | Dropzone: clic, drag/drop, pegar; orquesta preview |
| `fo51-pdfjs-scroll-viewer.js` | Render PDF.js → canvas en contenedor `overflow-y-auto` (scroll real) |
| `fo51-pdf-upload-faults.js` | Panel multi-checkbox + «Guardar» + Otros fijo |
| `FoGj51InformeController::uploadToRevisionQueue` | Persiste PDF + evidencias + snapshot |
| `pdfjs-dist` (npm) | Dependencia front; **no** sustituye Dompdf/Browsershot |

**Contrato**

1. Livewire: `openFo51Modal(true)` / `cargar_pdf=1` → solo `showFo51PdfUploadModal` (sin Letter digitada detrás).
2. Preview: páginas pintadas con PDF.js; worker y chunk en `public/build` tras `npm run build` / Vite.
3. POST `fo51_action=cargar`: `informe_file` + `informe_worker_*` + metadatos + `fo51_fault_*` opcionales + `evidence_images` ≤10 + revisor; **sin** firma elaborador.
4. Independiente del pipeline HTML→Letter de este documento (§1–§7): no cambia `HtmlLetterPdfGenerator` ni `PDF_DRIVER`.

**Hosting:** desplegar assets Vite (`public/build`, incl. `pdf.worker*.mjs`). Si falla el worker/CSP, el modal muestra aviso y permite «Abrir en pestaña»; el envío del archivo sigue válido.

**Tests:** `SupervisorEvidenceQueueTest` (abre modal PDF), `FoGj51PreparerSignatureTest` (cargar + evidencias + faltas en snapshot).

### Expectativa Dompdf

Forma canónica con observaciones cortas + firma: **1 página física** (`FoGj51PreparerSignatureTest::test_filled_pdf_dompdf_fits_one_physical_page`).

### Archivos clave

| Archivo | Rol |
|---------|-----|
| `fo-gj-51-preview.blade.php` | Plantilla + estilos FO-GJ-51 + modos pantalla/PDF |
| `fo-gj-51-informe-body.blade.php` | Form POST digitado, evidencias |
| `fo-gj-51-informe-modal-shell.blade.php` | Modal digitado listado / hub |
| `fo-gj-51-pdf-upload-modal.blade.php` | Modal **Cargar PDF** |
| `fo-gj-51-screen-mobile.blade.php` | CSS móvil (solo `.fo51-interactive`) |
| `fo51-letter-zoom.js` | Zoom/pan Letter en modal digitado |
| `fo51-pdfjs-scroll-viewer.js` | Preview cliente PDF.js |
| `official-letter-pdf-shell.blade.php` | Encabezado FO-GJ-51 (logo, meta, código) |
| `official-letter-pdf-styles.blade.php` | Letter compartido + reglas `.ogj-letter-screen-sheet` |

### Troubleshooting FO-GJ-51

| Síntoma | Causa | Dónde mirar |
|---------|-------|-------------|
| PDF con 2 hojas y pie jurídico suelto | `textarea` / inputs / flex en PDF | Debe usarse `renderAsPdf` + clase `.fo51-pdf` |
| Casilla de falta pegada al texto en PDF | Flex en `fo51-fault-line` | Debe renderizarse `fo51-fault-line-tbl` |
| Grilla trabajador “inflada” en PDF | Flex en `fo51-personal-inner` | Reglas `.fo51-pdf .fo51-personal-inner { display: table }` |
| HTML carrado a la izquierda, bloque cuadrado | Sin `ogj-letter-screen-sheet` | Solo afecta pantalla; envolver preview interactivo |
| Preview carga PDF sin scroll / plugin Chrome | Iframe nativo + blob | Debe usarse PDF.js (`fo51-pdfjs-scroll-viewer`); rebuild Vite |
| «No se pudo previsualizar» con páginas visibles | Estado UI tras `destroy()` | Generación de render en `fo51-pdf-upload-file.js` |
| Chunk PDF.js 404 en hosting | `public/build` desactualizado | `npm run build` y desplegar `public/build` |
| Doble R: / guía + respuesta | N/A en FO-GJ-51 | (FO-GJ-04: ver sección 7) |

### Tests

| Test | Qué valida |
|------|------------|
| `FoGj51PreparerSignatureTest` | Firma obligatoria, HTML interactivo vs PDF, **1 hoja Dompdf**, `ogj-letter-screen-sheet` en formulario |
| `FoGj51SnapshotFaultMapperTest` | Mapeo de faltas en snapshot |

---

## 9. Otras formas de una sola página

FO-GJ-44, FO-GJ-54, FO-GJ-45/46/47, ACTA-COMITE (corto); FO-GJ-51 detallado en **§8**.

- Usan `official-letter-pdf-shell` o documento propio sin paginador PHP.
- Un encabezado; Dompdf fluye el contenido.
- **ACTA-COMITE** puede usar `HtmlLetterPdfGenerator::fromView(..., zeroPageMargins: true)` para membrete a sangre completa.

---

## 10. Pruebas y calibración Dompdf

### Archivos de test

| Test | Ruta | Qué valida |
|------|------|------------|
| `OfficialLetterPdfLayoutTest` | `tests/Unit/Support/Pdf/OfficialLetterPdfLayoutTest.php` | CSS Letter + **planned === physical** en Dompdf |
| `FoGj03DocumentPaginatorTest` | `tests/Unit/Disciplinary/FoGj03DocumentPaginatorTest.php` | Lógica del paginador (sin PDF) |
| `FoGj04PagePlannerTest` | `tests/Unit/Disciplinary/FoGj04PagePlannerTest.php` | Lógica FO-GJ-04, pares pregunta+R: |
| `DompdfLetterPdfDriverTest` | `tests/Unit/Support/Pdf/DompdfLetterPdfDriverTest.php` | Smoke del driver |
| `LetterPdfDriverTest` | `tests/Unit/Support/Pdf/LetterPdfDriverTest.php` | Selección de driver |
| `EmbeddedPdfFontTest` | `tests/Unit/Support/Pdf/EmbeddedPdfFontTest.php` | TTF presentes |
| `FoGj51PreparerSignatureTest` | `tests/Feature/Disciplinary/FoGj51PreparerSignatureTest.php` | FO-GJ-51 pantalla vs PDF, 1 hoja Dompdf, cargar+evidencias |
| `SupervisorEvidenceQueueTest` | `tests/Feature/Disciplinary/SupervisorEvidenceQueueTest.php` | Hub supervisor + modal cargar PDF |
| `FoGj03DraftTest` / `FoGj04DraftTest` | `tests/Feature/Disciplinary/` | Rutas + preview inline |

### Metodología «planned vs physical»

Usada en `OfficialLetterPdfLayoutTest`:

```php
config(['services.pdf.driver' => 'dompdf']);

$html = view('disciplinary.forms.fo-gj-04-filled-download', $data)->render();
$binary = HtmlLetterPdfGenerator::fromHtml($html);

$planned  = preg_match_all('/<td class="ogj-meta-code">FO-GJ-04<\/td>/', $html);
$physical = preg_match_all('/\/Type\s*\/Page\b/', $binary);

// Debe cumplirse: planned === physical
```

Además se cuenta la aguja `FO-GJ-0X` en streams descomprimidos del PDF para verificar letterhead por hoja.

### Expectativas canónicas

| Escenario | Esperado |
|-----------|----------|
| FO-GJ-03 citación típica | 1 planificada = 1 física |
| FO-GJ-04 acta corta (1–2 preguntas) | ≥ 2 hojas |
| FO-GJ-04 cargos largos / cuestionario largo | `planned === physical` estricto |
| FO-GJ-51 informe típico | **1** página física Dompdf |

### Cómo recalibrar tras cambiar CSS o constantes

1. Ajustar constantes en `FoGj03DocumentPaginator` o `FoGj04PagePlanner`.
2. Ejecutar tests unitarios del paginador.
3. Ejecutar `OfficialLetterPdfLayoutTest` con Dompdf.
4. Revisar PDF real en Laragon/Hostinger con el mismo `PDF_DRIVER`.
5. Si `planned < physical` → subir `PAGE_UNITS` o bajar sobrecostes de bloques.
6. Si hay hueco grande en p.1 → bajar `INTRO_LEAD_*` o sobrecostes de términos (con cuidado de no romper el caso largo).

```bash
php vendor/bin/phpunit tests/Unit/Disciplinary/FoGj04PagePlannerTest.php
php vendor/bin/phpunit tests/Unit/Support/Pdf/OfficialLetterPdfLayoutTest.php --filter fo_gj_04
```

---

## 11. Después de cambiar plantillas

1. **`npm run build`** — si tocaste assets Vite o clases en vistas con Tailwind.
2. **`php artisan view:clear`** — si la vista previa no refleja cambios.
3. **`php artisan optimize:clear`** — solo si cambiaste config/rutas y lo necesitas.
4. Invalidar caché iframe: query `rev=` (mtime de la vista) en preview de Formatos.

---

## 12. Troubleshooting

| Síntoma | Causa probable | Acción |
|---------|----------------|--------|
| PDF cortado a la derecha | `width:100%` + padding en `.ogj-page` | Usar modelo `7.5in` + `margin:0.5in` |
| «Página 1 de 1» con 2 hojas físicas | Paginador subestima intro/cierre | Recalibrar constantes; correr tests Dompdf |
| Hueco grande en p.1 FO-GJ-04 | Sobrestimación de unidades intro/términos | Bajar `INTRO_LEAD_UNITS` / factores; validar con probe |
| Pregunta sin su `R:` en la misma hoja | Empaquetado no atómico | Verificar `question_pair` en `FoGj04PagePlanner` |
| Doble `R:` o guía + respuesta | Blade pinta R: en fila de título vacío | `fo-gj-04-question-item`: solo R: si hay respuesta/guía/continuación |
| Firmas partidas | Falta `page-break-inside: avoid` o adjunto atómico | Revisar `.ogj-03-closing-block` / `attachSignaturesAtomically` |
| Timeout en Hostinger | Browsershot desde web | `PDF_DRIVER=dompdf` |
| Fuentes cuadradas / fallback | TTF no legibles | `php artisan disciplinary:pdf-check`; permisos `storage/fonts` |
| Vista previa en caché | Blade compilado viejo | `view:clear` |

---

## 13. Añadir un nuevo formato PDF

1. Crear vistas `{codigo}-filled-download.blade.php` y opcional `{codigo}-blank-download.blade.php`.
2. Incluir `official-letter-pdf-styles` (o estilos propios si no es FO-GJ).
3. Registrar en `OfficialFormsCatalog::htmlBlankPdfRegistry()` si aplica en blanco.
4. Crear servicio `buildViewData()` + `downloadPdf()` que llame `HtmlLetterPdfGenerator::fromView()`.
5. Controlador + ruta con `?inline=1` y política `preview*`.
6. Si el documento supera 1 hoja de forma predecible → paginador PHP al estilo FO-GJ-03 (unidades + `.ogj-page` explícitas).
7. Añadir test en `OfficialLetterPdfLayoutTest` con `planned === physical`.
8. Documentar en este archivo y en `README.md`.

---

## 14. Mapa de archivos clave

```
app/Support/Pdf/
  HtmlLetterPdfGenerator.php      ← entrada única HTML → PDF
  DompdfLetterPdfDriver.php
  LetterPdfDriver.php
  EmbeddedPdfFont.php
  EmbeddedPublicAsset.php

app/Support/Disciplinary/
  FoGj03DocumentPaginator.php     ← FO-GJ-03 páginas
  FoGj04PagePlanner.php           ← FO-GJ-04 páginas
  OfficialFormsCatalog.php        ← registro formatos
  OfficialFormHtmlBlankPdfFactory.php

app/Services/Disciplinary/
  FoGj03CitationService.php
  FoGj04DiligenceActaService.php
  FoGj44ConstanciaService.php
  FoGj54ReprogramacionService.php
  FoGj51PdfBuilder.php
  ComiteActaService.php
  DecisionComunicadoService.php
  CitationNotificationSigningService.php
  DecisionNotificationSigningService.php

resources/views/components/disciplinary/forms/
  fo-gj-51-preview.blade.php       ← FO-GJ-51 pantalla + PDF (.fo51-pdf)
  fo-gj-51-pdf-upload-modal.blade.php ← Cargar PDF externo (PDF.js preview)
  fo51-pdfjs-scroll-viewer.js      ← Chunk Vite pdfjs-dist (solo modal cargar)

resources/views/disciplinary/forms/
  partials/official-letter-pdf-styles.blade.php
  partials/fo-gj-03-*.blade.php
  partials/fo-gj-04-*.blade.php
  *-filled-download.blade.php
  *-blank-download.blade.php

resources/fonts/pdf/               ← Liberation TTF
public/images/logo solo.png        ← logo único

tests/Feature/Disciplinary/FoGj51PreparerSignatureTest.php
tests/Unit/Support/Pdf/OfficialLetterPdfLayoutTest.php
tests/Unit/Disciplinary/FoGj03DocumentPaginatorTest.php
tests/Unit/Disciplinary/FoGj04PagePlannerTest.php
```

---

## 15. Referencias cruzadas

- `README.md` — sección «PDF disciplinarios», variables `.env`, Hostinger vs local.
- `docs/ARCHITECTURE.md` — stack y estructura general del proyecto.
- `.env.example` — todas las variables `PDF_*`.
