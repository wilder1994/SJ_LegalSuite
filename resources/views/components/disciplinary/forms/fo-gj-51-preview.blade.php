{{--
  FO-GJ-51 — grillas separadas con espacio entre bloques (como el formato impreso original).
--}}
@props([
    'workerName' => '',
    'workerDocument' => '',
    'workerCargo' => '',
    'employeeId' => '',
    'enableEmployeeLookup' => true,
    /** @var \Illuminate\Support\Collection<string, \Illuminate\Support\Collection<int, array{code: string, name: string}>> */
    'municipalitiesGrouped' => null,
    'city' => '',
    'shift' => '',
    'position' => '',
    'faultOtherDetail' => '',
    'observations' => '',
    'useAuthPreparer' => true,
    'preparerName' => '',
    'preparerRole' => '',
    'preparerSignature' => '',
    'reportDay' => null,
    'reportMonth' => null,
    'reportYear' => null,
    'metaPageLine' => 'Página 1 de 1',
    /** Plantilla sin datos de sesión ni fecha automática (descarga manual). */
    'blankForDownload' => false,
    /** Logo embebido (data URI) para PDF vía Browsershot; si es null se usa asset(). */
    'logoSrc' => null,
    /** @var list<string> */
    'faultLeftChecked' => [],
    /** @var list<string> */
    'faultRightChecked' => [],
    'faultOtherChecked' => false,
    'jurPd' => '',
    'entregaGh' => '',
    'jurDd' => '',
    'jurMm' => '',
    'jurYyyy' => '',
    'renderAsPdf' => false,
])

@php
    use App\Models\ColombianMunicipality;
    use App\Support\Disciplinary\DisciplinaryAssets;
    use App\Support\Disciplinary\FoGj51Catalog;
    use Illuminate\Support\Carbon;

    $municipalitiesGrouped = $municipalitiesGrouped instanceof \Illuminate\Support\Collection
        ? $municipalitiesGrouped
        : ColombianMunicipality::groupedByDepartmentForSelect();

    $municipalitiesFlat = [];
    foreach ($municipalitiesGrouped as $deptName => $rows) {
        foreach ($rows as $mun) {
            $municipalitiesFlat[] = [
                'code' => (string) $mun['code'],
                'name' => (string) $mun['name'],
                'dept' => (string) $deptName,
            ];
        }
    }

    $faultLeft = FoGj51Catalog::faultLeft();
    $faultRight = FoGj51Catalog::faultRight();

    $resolvedLogo = filled((string) $logoSrc) ? $logoSrc : DisciplinaryAssets::logoPublicUrl();

    $observationsText = trim((string) $observations);
    $user = auth()->user();

    if ($blankForDownload) {
        $resolvedPreparerName = '';
        $resolvedPreparerRole = '';
        $resolvedReportDay = '';
        $resolvedReportMonth = '';
        $resolvedReportYear = '';
    } elseif ($useAuthPreparer && $user) {
        $resolvedPreparerName = $user->name;
        $resolvedPreparerRole = filled($user->position)
            ? $user->position
            : (string) ($user->roles->first()?->name ?? '');
        $now = Carbon::now()->locale('es');
        $resolvedReportDay = $reportDay ?? $now->format('d');
        $resolvedReportMonth = $reportMonth ?? $now->format('m');
        $resolvedReportYear = $reportYear ?? $now->format('Y');
    } else {
        $resolvedPreparerName = $preparerName;
        $resolvedPreparerRole = $preparerRole;
        $now = Carbon::now()->locale('es');
        $resolvedReportDay = $reportDay ?? $now->format('d');
        $resolvedReportMonth = $reportMonth ?? $now->format('m');
        $resolvedReportYear = $reportYear ?? $now->format('Y');
    }

    $preparerFieldsReadonly = ! $blankForDownload && $useAuthPreparer && $user;

    $resolvedPreparerSignature = old('fo51_preparer_signature', $preparerSignature);
    $preparerSignatureIsImage = str_starts_with(trim((string) $resolvedPreparerSignature), 'data:image/');

    $faultRightCount = count($faultRight);
    $faultRows = max(count($faultLeft), $faultRightCount + 1);

    $resolvedMetaDate = ucfirst(
        Carbon::now()->timezone(config('app.timezone', 'America/Bogota'))->locale('es')->translatedFormat('F \d\e Y')
    );

    $fo51Interactive = ! ($renderAsPdf ?? false) && ! ($blankForDownload ?? false);
    $isPdfRender = (bool) ($renderAsPdf ?? false);
    $useLetterScreen = ! $isPdfRender;
@endphp

@if ($fo51Interactive)
    @include('disciplinary.forms.partials.fo-gj-51-screen-mobile')
@endif

<style>
    /* Cuerpo FO-GJ-51 (encabezado grilla vía official-letter-pdf-shell). */
    .fo51-body-blocks {
        width: 100%;
        max-width: 100%;
        min-width: 0;
        box-sizing: border-box;
    }
    /* Cada bloque = una grilla propia + hueco debajo */
    .fo51-block {
        border: 1px solid #000;
        margin-bottom: 11px;
        box-sizing: border-box;
        background: #fff;
    }
    .fo51-page > .fo51-micro {
        margin-bottom: 0;
    }
    .fo51-tbl {
        width: 100%;
        border-collapse: collapse;
        table-layout: fixed;
        border-spacing: 0;
        margin: 0;
    }
    .fo51-tbl td,
    .fo51-tbl th {
        border: 1px solid #000;
        vertical-align: middle;
        padding: 3px 5px;
        background: #fff;
        color: #000;
    }
    .fo51-tbl th {
        font-weight: bold;
        font-size: var(--ogj-font-meta);
        text-align: left;
    }
    .fo51-in {
        width: 100%;
        border: none !important;
        outline: none !important;
        background: #fff !important;
        font: inherit;
        padding: 4px 5px;
        margin: 0;
        box-sizing: border-box;
        color: #000;
    }
    .fo51-in:focus {
        outline: 1px dotted #555 !important;
        outline-offset: 1px;
    }
    .fo51-static {
        display: block;
        min-height: 1.25em;
        padding: 4px 5px;
        box-sizing: border-box;
        color: #000;
        line-height: 1.3;
    }
    textarea.fo51-in {
        display: block;
        min-height: 168px;
        line-height: 1.38;
        text-align: justify;
        resize: vertical;
    }
    .fo51-chk {
        width: 13px;
        height: 13px;
        margin: 0;
        flex-shrink: 0;
        accent-color: #000;
    }
    .fo51-lbl-cap {
        font-weight: bold;
        font-size: var(--ogj-font-meta);
        text-transform: uppercase;
        padding: 5px 5px !important;
        line-height: 1.2;
    }
    .fo51-personal-cell {
        vertical-align: middle;
        padding: 3px 5px !important;
    }
    .fo51-personal-inner {
        display: flex;
        align-items: center;
        gap: 4px;
        min-width: 0;
    }
    .fo51-inline-lbl {
        font-weight: bold;
        font-size: var(--ogj-font-meta);
        text-transform: uppercase;
        flex-shrink: 0;
        line-height: 1.2;
        white-space: nowrap;
    }
    .fo51-personal-val {
        flex: 1;
        min-width: 0;
    }
    .fo51-personal-val .fo51-in,
    .fo51-personal-val .fo51-static {
        width: 100%;
    }
    .fo51-personal-val > .relative {
        width: 100%;
        max-width: 100%;
        box-sizing: border-box;
    }
    .fo51-date-grid .fo51-date-lbl {
        text-align: center;
        font-weight: bold;
        font-size: var(--ogj-font-meta);
        padding: 5px 6px !important;
        vertical-align: middle;
    }
    .fo51-date-grid .fo51-date-val {
        padding: 0 !important;
        vertical-align: middle;
        text-align: center;
    }
    .fo51-date-grid .fo51-date-val .fo51-in {
        text-align: center;
        min-height: 1.65em;
    }
    .fo51-date-grid .fo51-date-val .fo51-static {
        text-align: center;
    }
    .fo51-date-grid .fo51-lbl-cap {
        vertical-align: middle;
    }
    /* Fecha: 50% ancho, sin marco exterior (solo bordes de celdas). */
    .fo51-date-wrap {
        width: 50%;
        max-width: 50%;
        margin-bottom: 11px;
        box-sizing: border-box;
        border: none;
        background: transparent;
    }
    .fo51-date-wrap .fo51-date-grid {
        width: 100%;
    }
    .fo51-fault-head {
        font-weight: bold;
        font-size: var(--ogj-font-meta);
        text-transform: uppercase;
        padding: 6px 6px !important;
        line-height: 1.25;
    }
    .fo51-fault-line {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 10px;
        padding: 4px 5px;
        line-height: 1.28;
        min-height: 1.35em;
    }
    .fo51-fault-line > span:first-child {
        flex: 1;
        text-align: left;
    }
    .fo51-obs-head {
        font-weight: bold;
        font-size: var(--ogj-font-meta);
        padding: 6px 7px !important;
        line-height: 1.3;
        border-bottom: 1px solid #000 !important;
        text-transform: uppercase;
    }
    .fo51-obs-head .fo51-obs-sub {
        text-transform: none;
        font-weight: bold;
    }
    .fo51-sign-cap th {
        text-align: center !important;
        text-transform: uppercase;
        font-size: var(--ogj-font-meta);
        padding: 6px 4px !important;
    }
    .fo51-sign-note td {
        text-align: center !important;
        font-style: italic;
        font-size: var(--ogj-font-meta);
        padding: 6px 8px !important;
        border-top: 1px solid #000 !important;
    }
    .fo51-signature-img,
    .fo51-signature-preview {
        display: block;
        max-height: 32px;
        max-width: 100%;
        margin: 0 auto;
        object-fit: contain;
    }
    .fo51-signature-capture-inner {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 3px;
        min-height: 36px;
        padding: 2px 4px;
        box-sizing: border-box;
    }
    .fo51-signature-capture-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border: 1px solid #94a3b8;
        border-radius: 4px;
        background: #f8fafc;
        color: #0f172a;
        font-size: 10px;
        font-weight: 600;
        line-height: 1.2;
        padding: 4px 8px;
        cursor: pointer;
        white-space: nowrap;
    }
    .fo51-signature-capture-btn:hover {
        background: #e2e8f0;
    }
    .fo51-signature-capture-link {
        border: 0;
        background: transparent;
        color: #4338ca;
        font-size: 10px;
        font-weight: 600;
        text-decoration: underline;
        cursor: pointer;
        padding: 0;
    }
    th.fo51-foot-band {
        background: #e0e0e0 !important;
        text-align: center !important;
        font-size: var(--ogj-font-meta);
        text-transform: uppercase;
        padding: 7px 6px !important;
        letter-spacing: 0.02em;
        border-bottom: 1px solid #000 !important;
    }
    .fo51-micro {
        font-size: var(--ogj-font-micro);
        color: #555;
        text-align: center;
        padding: 6px 8px 0;
        line-height: 1.25;
        border: none !important;
        margin-top: 2px;
        margin-bottom: 0;
    }
    @media (max-width: 900px) {
        .fo51-body-blocks {
            min-width: 0;
            width: 100%;
        }
        textarea.fo51-in {
            min-height: 140px;
        }
    }
    /* Dompdf: sin flexbox; grillas con tablas; cuerpo compacto en 1 hoja Letter. */
    .fo51-pdf .fo51-block {
        margin-bottom: 7px;
    }
    .fo51-pdf .fo51-date-wrap {
        margin-bottom: 7px;
    }
    .fo51-pdf .fo51-personal-inner {
        display: table;
        width: 100%;
    }
    .fo51-pdf .fo51-inline-lbl {
        display: table-cell;
        width: 1%;
        white-space: nowrap;
        vertical-align: middle;
        padding-right: 4px;
    }
    .fo51-pdf .fo51-personal-val {
        display: table-cell;
        vertical-align: middle;
        width: auto;
    }
    .fo51-pdf .fo51-static {
        display: inline;
        min-height: 0;
        padding: 1px 0;
        line-height: 1.25;
    }
    .fo51-fault-line-tbl {
        width: 100%;
        border-collapse: collapse;
        table-layout: fixed;
        margin: 0;
    }
    .fo51-fault-line-tbl td {
        border: none;
        padding: 3px 5px;
        vertical-align: middle;
        background: #fff;
        color: #000;
    }
    .fo51-fault-text {
        text-align: left;
        line-height: 1.28;
    }
    .fo51-fault-chk {
        text-align: right;
        width: 18px;
        white-space: nowrap;
        vertical-align: middle;
    }
    .fo51-fault-chk-box {
        display: inline-block;
        width: 11px;
        height: 11px;
        border: 1px solid #000;
        text-align: center;
        line-height: 10px;
        font-size: 9px;
        font-weight: bold;
        vertical-align: middle;
        box-sizing: border-box;
    }
    .fo51-fault-otros-detail {
        border-bottom: 1px solid #000;
        display: inline-block;
        min-width: 6rem;
        padding: 0 2px;
        line-height: 1.25;
    }
    .fo51-obs-pdf {
        padding: 5px 6px;
        min-height: 72px;
        text-align: justify;
        line-height: 1.32;
        white-space: pre-wrap;
        color: #000;
        box-sizing: border-box;
    }
    .fo51-pdf .fo51-sign-cell {
        padding: 4px 5px !important;
        height: auto !important;
        vertical-align: middle;
    }
    .fo51-pdf .fo51-legal-cell {
        padding: 4px 5px !important;
        height: auto !important;
        vertical-align: middle;
    }
    .fo51-pdf .fo51-legal-cell--center {
        text-align: center;
    }
    .fo51-letter-screen-host {
        width: 100%;
        display: flex;
        flex-direction: column;
        min-height: 0;
    }
    .fo51-interactive .ogj-letter-screen-scaler {
        padding: 0;
    }
    .fo51-letter-zoom-toolbar {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 0.5rem;
        padding: 0.5rem 0.65rem;
        border-bottom: 1px solid rgba(148, 163, 184, 0.35);
        background: rgba(248, 250, 252, 0.95);
    }
    .dark .fo51-letter-zoom-toolbar {
        background: rgba(15, 23, 42, 0.65);
        border-bottom-color: rgba(255, 255, 255, 0.1);
    }
    .fo51-letter-viewport {
        overflow: auto;
        -webkit-overflow-scrolling: touch;
        overscroll-behavior: contain;
        background: #e2e8f0;
        padding: 0.5rem;
        min-height: 12rem;
        max-height: min(70vh, 52rem);
    }
    .fo51-modal-chrome .fo51-letter-viewport {
        max-height: none;
    }
    .dark .fo51-letter-viewport {
        background: rgba(2, 6, 23, 0.55);
    }
    .fo51-letter-zoom-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 2.25rem;
        min-height: 2.25rem;
        padding: 0 0.65rem;
        border-radius: 0.5rem;
        border: 1px solid rgba(148, 163, 184, 0.45);
        background: #fff;
        color: #0f172a;
        font-size: 0.8125rem;
        font-weight: 600;
        cursor: pointer;
    }
    .dark .fo51-letter-zoom-btn {
        background: rgba(255, 255, 255, 0.06);
        border-color: rgba(255, 255, 255, 0.15);
        color: #e2e8f0;
    }
    .fo51-letter-zoom-btn:hover {
        background: #f1f5f9;
    }
    .dark .fo51-letter-zoom-btn:hover {
        background: rgba(255, 255, 255, 0.1);
    }
    .fo51-letter-zoom-pct {
        min-width: 3.25rem;
        text-align: center;
        font-size: 0.8125rem;
        font-weight: 600;
        font-variant-numeric: tabular-nums;
        color: #334155;
    }
    .dark .fo51-letter-zoom-pct {
        color: #cbd5e1;
    }
</style>

@if ($useLetterScreen)
    @if ($fo51Interactive)
    <div
        class="fo51-letter-screen-host"
        x-data="(typeof window.fo51LetterZoom === 'function' ? window.fo51LetterZoom() : { scale: 1, init() {}, percentLabel() { return '100%'; }, spacerStyle() { return {}; }, sheetStyle() { return {}; }, zoomIn() {}, zoomOut() {}, fitWidth() {} })"
        x-init="init()"
    >
        <div class="fo51-letter-zoom-toolbar" role="toolbar" aria-label="Zoom del formato FO-GJ-51">
            <span class="text-[11px] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Formato</span>
            <div class="ml-auto flex items-center gap-1">
                <button type="button" class="fo51-letter-zoom-btn" @click="zoomOut()" aria-label="Alejar">−</button>
                <span class="fo51-letter-zoom-pct" x-text="percentLabel()" aria-live="polite"></span>
                <button type="button" class="fo51-letter-zoom-btn" @click="zoomIn()" aria-label="Acercar">+</button>
                <button type="button" class="fo51-letter-zoom-btn" @click="fitWidth()">Ajustar</button>
            </div>
        </div>
        <div class="fo51-letter-viewport" x-ref="fo51Viewport">
            <div :style="spacerStyle()">
                <div class="ogj-letter-screen-sheet" x-ref="fo51LetterSheet" :style="sheetStyle()">
    @else
    <div
        class="fo51-letter-screen-host"
        x-data="{ scale: 1 }"
        x-init="
            const updateScale = () => {
                const sheet = $refs.fo51LetterSheet;
                if (! sheet) return;
                const available = $el.clientWidth - 24;
                const sheetWidth = sheet.offsetWidth;
                scale = sheetWidth > available ? Math.max(available / sheetWidth, 0.45) : 1;
            };
            $nextTick(updateScale);
            window.addEventListener('resize', updateScale);
        "
    >
        <div class="ogj-letter-screen-scaler">
            <div class="ogj-letter-screen-sheet" x-ref="fo51LetterSheet" :style="`transform: scale(${scale});`">
    @endif
@endif

<div @class(['fo51-interactive' => $fo51Interactive, 'fo51-pdf' => $isPdfRender])>
<x-disciplinary.forms.official-letter-pdf-shell
    code="FO-GJ-51"
    headline="Informe disciplinario"
    :logo-src="$resolvedLogo"
    :meta-date="$resolvedMetaDate"
    meta-version="Versión 04"
    :meta-page-line="$metaPageLine"
    :show-micro="false"
>
    <div class="fo51-body-blocks">
        {{-- 1 · Fecha (4 columnas × 2 filas; FECHA: rowspan 2; 50% ancho) --}}
        <div class="fo51-date-wrap">
            <table class="fo51-tbl fo51-date-grid" role="presentation">
                <colgroup>
                    <col style="width:18%">
                    <col style="width:27%">
                    <col style="width:27%">
                    <col style="width:28%">
                </colgroup>
                <tr>
                    <td rowspan="2" class="fo51-lbl-cap">FECHA:</td>
                    <td class="fo51-date-lbl">DD</td>
                    <td class="fo51-date-lbl">MM</td>
                    <td class="fo51-date-lbl">AAAA</td>
                </tr>
                <tr>
                    <td class="fo51-date-val">
                        @if ($renderAsPdf ?? false)
                            <span class="fo51-static">{{ $resolvedReportDay }}</span>
                        @else
                            <input type="text" class="fo51-in" name="fo51_report_dd" maxlength="2" inputmode="numeric" value="{{ $resolvedReportDay }}" autocomplete="off">
                        @endif
                    </td>
                    <td class="fo51-date-val">
                        @if ($renderAsPdf ?? false)
                            <span class="fo51-static">{{ $resolvedReportMonth }}</span>
                        @else
                            <input type="text" class="fo51-in" name="fo51_report_mm" maxlength="2" inputmode="numeric" value="{{ $resolvedReportMonth }}" autocomplete="off">
                        @endif
                    </td>
                    <td class="fo51-date-val">
                        @if ($renderAsPdf ?? false)
                            <span class="fo51-static">{{ $resolvedReportYear }}</span>
                        @else
                            <input type="text" class="fo51-in" name="fo51_report_yyyy" maxlength="4" inputmode="numeric" value="{{ $resolvedReportYear }}" autocomplete="off">
                        @endif
                    </td>
                </tr>
            </table>
        </div>

        {{-- 2 · Datos del trabajador --}}
        @php
            $useEmployeeLookup = ($enableEmployeeLookup ?? true) && ! ($renderAsPdf ?? false) && ! ($blankForDownload ?? false);
            $employeeSearchUrl = route('api.employees.search');
        @endphp
        <div @class(['fo51-block', 'fo51-block-personal' => $fo51Interactive]) @if ($useEmployeeLookup) x-data="window.disciplinaryFo51EmployeeCombo(@js($employeeSearchUrl), @js(old('fo51_worker_document', $workerDocument)), @js(old('fo51_worker_name', $workerName)), @js($workerCargo), @js(old('fo51_employee_id', $employeeId)))" @endif>
            <table class="fo51-tbl" role="presentation">
                <colgroup>
                    <col style="width:25%">
                    <col style="width:25%">
                    <col style="width:25%">
                    <col style="width:25%">
                </colgroup>
                <tr>
                    <td class="fo51-personal-cell">
                        <div class="fo51-personal-inner">
                        <span class="fo51-inline-lbl">CC:</span>
                        <div class="fo51-personal-val">
                        @if ($renderAsPdf ?? false)
                            <span class="fo51-static">{{ $workerDocument }}</span>
                        @elseif ($useEmployeeLookup)
                            <input type="hidden" name="fo51_employee_id" x-model="employeeId">
                            <div class="relative">
                                <input type="text" name="fo51_worker_document" class="fo51-in" x-model="query" autocomplete="off" inputmode="numeric" pattern="[0-9]*" required
                                    @focus="openList()" @input="onInput()" @blur="onBlur()" @keydown="onKeydown($event)"
                                    placeholder="Digite documento…" role="combobox" :aria-expanded="open ? 'true' : 'false'">
                                <ul x-show="open && filtered.length" x-cloak
                                    class="absolute left-0 right-0 z-[90] mt-0.5 max-h-48 overflow-auto rounded-md border border-slate-300 bg-white py-1 text-left text-xs text-slate-900 shadow-xl dark:border-white/20 dark:bg-slate-900 dark:text-slate-100"
                                    role="listbox">
                                    <template x-for="(it, idx) in filtered" :key="it.id">
                                        <li role="option" class="cursor-pointer px-2 py-1.5 hover:bg-indigo-50 dark:hover:bg-white/10"
                                            :class="{ 'bg-indigo-100 dark:bg-white/15': idx === highlightedIndex }"
                                            @mousedown.prevent="selectItem(it)"
                                            x-text="it.document_number + ' · ' + it.full_name"></li>
                                    </template>
                                </ul>
                            </div>
                        @else
                            <input type="text" name="fo51_worker_document" class="fo51-in" value="{{ $workerDocument }}" autocomplete="off" inputmode="numeric">
                        @endif
                        </div>
                        </div>
                    </td>
                    <td class="fo51-personal-cell" colspan="3">
                        <div class="fo51-personal-inner">
                        <span class="fo51-inline-lbl">NOMBRE:</span>
                        <div class="fo51-personal-val">
                        @if ($renderAsPdf ?? false)
                            <span class="fo51-static">{{ $workerName }}</span>
                        @elseif ($useEmployeeLookup)
                            <input type="text" name="fo51_worker_name" class="fo51-in bg-slate-50 dark:bg-white/5" x-model="workerName" readonly required tabindex="-1">
                        @else
                            <input type="text" name="fo51_worker_name" class="fo51-in" value="{{ $workerName }}" autocomplete="off">
                        @endif
                        </div>
                        </div>
                    </td>
                </tr>
                <tr>
                    <td class="fo51-personal-cell">
                        <div class="fo51-personal-inner">
                        <span class="fo51-inline-lbl">CARGO:</span>
                        <div class="fo51-personal-val">
                        @if ($renderAsPdf ?? false)
                            <span class="fo51-static">{{ $workerCargo ?: ' ' }}</span>
                        @elseif ($useEmployeeLookup)
                            <span class="fo51-static" x-text="workerCargo || ' '"></span>
                        @else
                            <span class="fo51-static">{{ $workerCargo ?: ' ' }}</span>
                        @endif
                        </div>
                        </div>
                    </td>
                    <td class="fo51-personal-cell">
                        <div class="fo51-personal-inner">
                        <span class="fo51-inline-lbl">CIUDAD:</span>
                        <div class="fo51-personal-val">
                        @if ($renderAsPdf ?? false)
                            <span class="fo51-static">{{ $city }}</span>
                        @elseif ($blankForDownload ?? false)
                            <select name="fo51_municipality_code" class="fo51-in fo51-select-mun" style="width:100%;max-width:100%;box-sizing:border-box" aria-hidden="true" tabindex="-1">
                                <option value="">—</option>
                            </select>
                        @else
                            <div
                                class="relative"
                                x-data="window.disciplinaryFo51MunicipalityCombo(@js($municipalitiesFlat), @js(old('fo51_municipality_code', '')), @js(['required' => true]))"
                                @fo51-employee-selected.window="if ($event.detail.municipalityCode) { code = $event.detail.municipalityCode; const it = items.find(i => i.code === code); if (it) { query = it.name + ' — ' + it.dept; } }">
                                <input type="hidden" name="fo51_municipality_code" x-model="code" required>
                                <input
                                    type="text"
                                    class="fo51-in"
                                    style="width:100%;max-width:100%;box-sizing:border-box"
                                    autocomplete="off"
                                    autocorrect="off"
                                    autocapitalize="off"
                                    spellcheck="false"
                                    placeholder="Escriba para buscar (municipio, departamento o código)…"
                                    x-model="query"
                                    @focus="openList()"
                                    @input="onInput()"
                                    @blur="onBlur()"
                                    @keydown="onKeydown($event)"
                                    role="combobox"
                                    aria-autocomplete="list"
                                    :aria-expanded="open ? 'true' : 'false'">
                                <ul
                                    x-show="open && (filtered.length > 0 || (query.trim().length >= 1 && filtered.length === 0))"
                                    x-cloak
                                    class="absolute left-0 right-0 z-[80] mt-0.5 max-h-60 overflow-auto rounded-md border border-slate-300 bg-white py-1 text-left text-sm text-slate-900 shadow-xl dark:border-white/20 dark:bg-slate-900 dark:text-slate-100"
                                    role="listbox">
                                    <template x-for="(it, idx) in filtered" :key="it.code">
                                        <li
                                            role="option"
                                            class="cursor-pointer px-3 py-1.5 hover:bg-indigo-50 dark:hover:bg-white/10"
                                            :class="{ 'bg-indigo-100 dark:bg-white/15': idx === highlightedIndex }"
                                            @mousedown.prevent="selectItem(it)"
                                            x-text="it.name + ' — ' + it.dept + ' (' + it.code + ')'"></li>
                                    </template>
                                    <li
                                        x-show="query.trim().length >= 1 && filtered.length === 0"
                                        class="px-3 py-2 text-slate-500 dark:text-slate-400">Sin resultados. Ajuste el texto o importe DIVIPOLA en Ajustes → Territorio.</li>
                                </ul>
                            </div>
                        @endif
                        </div>
                        </div>
                    </td>
                    <td class="fo51-personal-cell">
                        <div class="fo51-personal-inner">
                        <span class="fo51-inline-lbl">TURNO:</span>
                        <div class="fo51-personal-val">
                        @if ($renderAsPdf ?? false)
                            <span class="fo51-static">{{ $shift }}</span>
                        @else
                            <input type="text" name="fo51_shift" class="fo51-in" value="{{ $shift }}">
                        @endif
                        </div>
                        </div>
                    </td>
                    <td class="fo51-personal-cell">
                        <div class="fo51-personal-inner">
                        <span class="fo51-inline-lbl">PUESTO:</span>
                        <div class="fo51-personal-val">
                        @if ($renderAsPdf ?? false)
                            <span class="fo51-static">{{ $position }}</span>
                        @else
                            <input type="text" name="fo51_position" class="fo51-in" value="{{ $position }}">
                        @endif
                        </div>
                        </div>
                    </td>
                </tr>
            </table>
        </div>

        {{-- 3 · Faltas (texto + casilla a la derecha como en el papel) --}}
        <div @class(['fo51-block', 'fo51-block-faults' => $fo51Interactive])>
            <table class="fo51-tbl" role="presentation">
                <thead>
                    <tr>
                        <th colspan="4" class="fo51-fault-head">
                            SEÑALE CON UNA EQUIS (X) LA FALTA COMETIDA POR EL COLABORADOR:
                        </th>
                    </tr>
                </thead>
                <tbody>
                    @for ($r = 0; $r < $faultRows; $r++)
                        <tr>
                            <td colspan="2" style="width:50%;padding:0!important">
                                @if (isset($faultLeft[$r]))
                                    @if ($isPdfRender)
                                        <table class="fo51-fault-line-tbl" role="presentation">
                                            <colgroup>
                                                <col>
                                                <col style="width:18px">
                                            </colgroup>
                                            <tr>
                                                <td class="fo51-fault-text">{{ $faultLeft[$r] }}</td>
                                                <td class="fo51-fault-chk">
                                                    <span class="fo51-fault-chk-box">{!! in_array($faultLeft[$r], $faultLeftChecked, true) ? 'X' : '&nbsp;' !!}</span>
                                                </td>
                                            </tr>
                                        </table>
                                    @else
                                        <div class="fo51-fault-line">
                                            <span>{{ $faultLeft[$r] }}</span>
                                            <input type="checkbox" name="fo51_fault_left[]" value="{{ $faultLeft[$r] }}" class="fo51-chk" title="Marcar falta" aria-label="Marcar: {{ $faultLeft[$r] }}"
                                                @checked(in_array($faultLeft[$r], $faultLeftChecked, true))>
                                        </div>
                                    @endif
                                @endif
                            </td>
                            <td colspan="2" style="width:50%;padding:0!important">
                                @if ($r < $faultRightCount)
                                    @if ($isPdfRender)
                                        <table class="fo51-fault-line-tbl" role="presentation">
                                            <colgroup>
                                                <col>
                                                <col style="width:18px">
                                            </colgroup>
                                            <tr>
                                                <td class="fo51-fault-text">{{ $faultRight[$r] }}</td>
                                                <td class="fo51-fault-chk">
                                                    <span class="fo51-fault-chk-box">{!! in_array($faultRight[$r], $faultRightChecked, true) ? 'X' : '&nbsp;' !!}</span>
                                                </td>
                                            </tr>
                                        </table>
                                    @else
                                        <div class="fo51-fault-line">
                                            <span>{{ $faultRight[$r] }}</span>
                                            <input type="checkbox" name="fo51_fault_right[]" value="{{ $faultRight[$r] }}" class="fo51-chk" title="Marcar falta" aria-label="Marcar: {{ $faultRight[$r] }}"
                                                @checked(in_array($faultRight[$r], $faultRightChecked, true))>
                                        </div>
                                    @endif
                                @elseif ($r === $faultRightCount)
                                    @if ($isPdfRender)
                                        <table class="fo51-fault-line-tbl" role="presentation">
                                            <colgroup>
                                                <col>
                                                <col style="width:18px">
                                            </colgroup>
                                            <tr>
                                                <td class="fo51-fault-text">
                                                    <strong>Otros</strong>
                                                    <span> ¿Cuál?</span>
                                                    <span class="fo51-fault-otros-detail">{{ $faultOtherDetail !== '' ? $faultOtherDetail : ' ' }}</span>
                                                </td>
                                                <td class="fo51-fault-chk">
                                                    <span class="fo51-fault-chk-box">{!! $faultOtherChecked ? 'X' : '&nbsp;' !!}</span>
                                                </td>
                                            </tr>
                                        </table>
                                    @else
                                        <div class="fo51-fault-line">
                                            <span style="display:flex;flex-wrap:wrap;align-items:center;gap:6px;flex:1">
                                                <strong>Otros</strong>
                                                <input type="checkbox" name="fo51_fault_other_chk" value="1" class="fo51-chk" title="Otros" aria-label="Otros"
                                                    @checked($faultOtherChecked)>
                                                <span>¿Cuál?</span>
                                                <input type="text" name="fo51_fault_other_detail" value="{{ $faultOtherDetail }}" class="fo51-in" style="flex:1;min-width:8rem;border-bottom:1px solid #000!important;padding:2px 4px!important">
                                            </span>
                                        </div>
                                    @endif
                                @endif
                            </td>
                        </tr>
                    @endfor
                </tbody>
            </table>
        </div>

        {{-- 5 · Observaciones / hechos --}}
        <div class="fo51-block">
            <table class="fo51-tbl" role="presentation">
                <tr>
                    <td class="fo51-obs-head" colspan="1">
                        OBSERVACIONES / HECHOS <span class="fo51-obs-sub">Explicación del caso concreto y la situación (relación de pruebas)</span>
                    </td>
                </tr>
                <tr>
                    <td style="padding:0!important;vertical-align:top">
                        @if ($isPdfRender)
                            <div class="fo51-obs-pdf">{{ $observationsText !== '' ? $observationsText : ' ' }}</div>
                        @else
                            <textarea name="fo51_observations" class="fo51-in" rows="10">{{ $observationsText }}</textarea>
                        @endif
                    </td>
                </tr>
            </table>
        </div>

        {{-- 6 · Nombre, cargo, fecha elaboración + leyenda --}}
        <div class="fo51-block">
            <table class="fo51-tbl fo51-sign-cap" role="presentation">
                <thead>
                    <tr>
                        <th style="width:34%">NOMBRE</th>
                        <th style="width:33%">CARGO</th>
                        <th style="width:33%">FIRMA</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        @if ($isPdfRender)
                            <td class="fo51-sign-cell" style="width:34%">
                                <span class="fo51-static">{{ $resolvedPreparerName }}</span>
                            </td>
                            <td class="fo51-sign-cell" style="width:33%">
                                <span class="fo51-static">{{ $resolvedPreparerRole }}</span>
                            </td>
                            <td class="fo51-sign-cell" style="width:33%;vertical-align:middle;text-align:center">
                                @if ($preparerSignatureIsImage)
                                    <img src="{{ $resolvedPreparerSignature }}" alt="Firma elaborador" class="fo51-signature-img">
                                @endif
                            </td>
                        @else
                        <td style="padding:0!important;height:38px">
                            <input type="text" name="fo51_preparer_name" class="fo51-in" @if ($preparerFieldsReadonly) readonly @endif value="{{ $resolvedPreparerName }}" style="height:36px">
                        </td>
                        <td style="padding:0!important">
                            <input type="text" name="fo51_preparer_role" class="fo51-in" @if ($preparerFieldsReadonly) readonly @endif value="{{ $resolvedPreparerRole }}" style="height:36px">
                        </td>
                        <td style="padding:0!important;vertical-align:middle">
                            @if ($blankForDownload ?? false)
                                <div style="height:36px"></div>
                            @else
                                <div
                                    x-data="window.sjFo51PreparerSignature(@js($resolvedPreparerSignature))"
                                    class="fo51-signature-capture">
                                    <input type="hidden" name="fo51_preparer_signature" x-model="signatureUri">
                                    <div class="fo51-signature-capture-inner">
                                        <img x-show="hasStoredSignature()"
                                            x-bind:src="signatureUri"
                                            alt="Firma capturada"
                                            class="fo51-signature-preview">
                                        <button type="button"
                                            class="fo51-signature-capture-btn"
                                            x-on:click="openSignatureModal()"
                                            x-text="hasStoredSignature() ? 'Cambiar firma' : 'Capturar firma'"></button>
                                        <button type="button"
                                            class="fo51-signature-capture-link"
                                            x-show="hasStoredSignature()"
                                            x-on:click="clearStoredSignature()">
                                            Quitar
                                        </button>
                                    </div>
                                    <x-disciplinary.signature-capture-modal-alpine
                                        title="Firma de quien elabora el informe"
                                        modal-id="fo51-preparer-signature" />
                                </div>
                            @endif
                        </td>
                        @endif
                    </tr>
                    <tr class="fo51-sign-note-row">
                        <td colspan="3" class="fo51-sign-note">Nombre, cargo y firma de quien elaboró el informe</td>
                    </tr>
                </tbody>
            </table>
        </div>

        {{-- 7 · Gestión jurídica (grilla aparte; barra gris como en el formato base) --}}
        <div @class(['fo51-block', 'fo51-block-legal' => $fo51Interactive])>
            <table class="fo51-tbl" role="presentation">
                <thead>
                    <tr>
                        <th colspan="5" class="fo51-foot-band">ESPACIO EXCLUSIVO PARA GESTIÓN JURÍDICA</th>
                    </tr>
                    <tr>
                        <th class="fo51-lbl-cap" style="text-transform:none;width:28%">JUR-PD-</th>
                        <th class="fo51-lbl-cap" style="text-transform:none;width:32%">ENTREGA A G.H</th>
                        <th class="fo51-lbl-cap" style="width:13%;text-align:center">DD</th>
                        <th class="fo51-lbl-cap" style="width:13%;text-align:center">MM</th>
                        <th class="fo51-lbl-cap" style="width:14%;text-align:center">AAAA</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        @if ($isPdfRender)
                            <td class="fo51-legal-cell" style="width:28%"><span class="fo51-static">{{ $jurPd !== '' ? $jurPd : ' ' }}</span></td>
                            <td class="fo51-legal-cell" style="width:32%"><span class="fo51-static">{{ $entregaGh !== '' ? $entregaGh : ' ' }}</span></td>
                            <td class="fo51-legal-cell fo51-legal-cell--center" style="width:13%"><span class="fo51-static">{{ $jurDd !== '' ? $jurDd : ' ' }}</span></td>
                            <td class="fo51-legal-cell fo51-legal-cell--center" style="width:13%"><span class="fo51-static">{{ $jurMm !== '' ? $jurMm : ' ' }}</span></td>
                            <td class="fo51-legal-cell fo51-legal-cell--center" style="width:14%"><span class="fo51-static">{{ $jurYyyy !== '' ? $jurYyyy : ' ' }}</span></td>
                        @else
                            <td style="padding:0!important;height:36px"><input type="text" name="fo51_jur_pd" class="fo51-in" style="height:34px" value="{{ $jurPd }}"></td>
                            <td style="padding:0!important"><input type="text" name="fo51_entrega_gh" class="fo51-in" style="height:34px" value="{{ $entregaGh }}"></td>
                            <td style="padding:0!important"><input type="text" name="fo51_jur_dd" maxlength="2" class="fo51-in" style="height:34px;text-align:center" value="{{ $jurDd }}"></td>
                            <td style="padding:0!important"><input type="text" name="fo51_jur_mm" maxlength="2" class="fo51-in" style="height:34px;text-align:center" value="{{ $jurMm }}"></td>
                            <td style="padding:0!important"><input type="text" name="fo51_jur_yyyy" maxlength="4" class="fo51-in" style="height:34px;text-align:center" value="{{ $jurYyyy }}"></td>
                        @endif
                    </tr>
                </tbody>
            </table>
        </div>

        <p class="fo51-micro">
            FO-GJ-51 - Uso interno SJ Seguridad - Reproducción no autorizada prohibida.
        </p>
    </div>
</x-disciplinary.forms.official-letter-pdf-shell>
</div>

@if ($useLetterScreen)
    @if ($fo51Interactive)
                </div>
            </div>
        </div>
    </div>
    @else
            </div>
        </div>
    </div>
    @endif
@endif

@if ($useAuthPreparer && $user && ! $blankForDownload)
    <p class="fo51-helper-note" style="font-size:var(--ogj-font-body);color:#64748b;text-align:center;max-width:8.5in;margin:12px auto 0;padding:0 8px">
        Nombre y cargo del elaborador se cargan desde su sesión. Capture su firma con el dedo (móvil) o con el lápiz de la mesa digitalizadora (PC).
    </p>
@endif
