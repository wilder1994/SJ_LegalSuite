@props([
    'prefillWorkerName' => null,
    'prefillWorkerDocument' => null,
    'operacionesReviewers' => null,
    'asPageOverlay' => true,
    'livewireClose' => true,
])

@php
    use App\Models\ColombianMunicipality;
    use App\Support\Disciplinary\FoGj51Catalog;

    $operacionesReviewers = $operacionesReviewers ?? collect();
    $municipalitiesGrouped = ColombianMunicipality::groupedByDepartmentForSelect();
    $munItems = [];
    foreach ($municipalitiesGrouped as $dept => $rows) {
        foreach ($rows as $m) {
            $munItems[] = [
                'code' => (string) ($m['code'] ?? ''),
                'name' => (string) ($m['name'] ?? ''),
                'dept' => (string) $dept,
            ];
        }
    }
    $today = now()->timezone(config('app.timezone', 'America/Bogota'));
    $defaultDd = old('fo51_report_dd', $today->format('d'));
    $defaultMm = old('fo51_report_mm', $today->format('m'));
    $defaultYyyy = old('fo51_report_yyyy', $today->format('Y'));
    $employeeSearchUrl = route('api.employees.search');
    $asPageOverlay = ($asPageOverlay ?? true) === true;
    $livewireClose = ($livewireClose ?? true) === true;
    $faultLeft = FoGj51Catalog::faultLeft();
    $faultRight = FoGj51Catalog::faultRight();
    $faultLeftChecked = array_values(array_filter((array) old('fo51_fault_left', []), 'is_string'));
    $faultRightChecked = array_values(array_filter((array) old('fo51_fault_right', []), 'is_string'));
    $faultOtherChecked = (bool) old('fo51_fault_other_chk', false);
    $faultOtherDetail = (string) old('fo51_fault_other_detail', '');
@endphp

<div
    @class([
        'fixed inset-0 z-[60] flex items-end justify-center p-0 sm:items-center sm:p-4' => $asPageOverlay,
        'relative w-full' => ! $asPageOverlay,
    ])
    @if ($asPageOverlay)
        role="dialog"
        aria-modal="true"
        aria-labelledby="fo51-pdf-upload-title"
        @if ($livewireClose)
            wire:key="fo51-pdf-upload-modal"
        @endif
    @endif
>
    @if ($asPageOverlay)
        <div
            class="absolute inset-0 bg-black/50 dark:bg-black/60"
            @if ($livewireClose)
                wire:click="closeFo51PdfUploadModal"
            @else
                @click="$dispatch('fo51-pdf-upload-close')"
            @endif
            aria-hidden="true"
        ></div>
    @endif

    <div
        @class([
            'relative flex max-h-[min(96dvh,900px)] w-full max-w-lg flex-col overflow-hidden rounded-t-xl bg-slate-50 shadow-2xl ring-1 ring-slate-200 sm:rounded-xl dark:bg-dash-ink dark:ring-white/15' => $asPageOverlay,
            'flex w-full max-w-lg flex-col overflow-hidden rounded-xl bg-slate-50 shadow-sm ring-1 ring-slate-200 dark:bg-dash-ink dark:ring-white/15' => ! $asPageOverlay,
        ])
        x-data="Object.assign(
            {},
            window.evidenceTilesState('pdf_upload_evidence_in_'),
            window.disciplinaryFo51EmployeeCombo(
                @js($employeeSearchUrl),
                @js(old('informe_worker_document', $prefillWorkerDocument ?? '')),
                @js(old('informe_worker_name', $prefillWorkerName ?? '')),
                @js(old('fo51_worker_cargo', '')),
                @js(old('fo51_employee_id'))
            ),
            { evidenceSheetOpen: false }
        )"
        @keydown.escape.window="evidenceSheetOpen = false">
        <div class="flex shrink-0 items-start justify-between gap-3 border-b border-slate-200 px-4 py-3 dark:border-white/10 sm:px-5">
            <div class="min-w-0">
                <p class="text-[10px] font-semibold uppercase tracking-widest text-slate-500 dark:text-dash-muted">Disciplinarios · FO-GJ-51</p>
                <h2 id="fo51-pdf-upload-title" class="mt-0.5 text-base font-bold text-slate-900 dark:text-white sm:text-lg">Cargar informe en PDF</h2>
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                    Primero el PDF; luego trabajador, faltas, metadatos y pruebas opcionales.
                </p>
            </div>
            @if ($asPageOverlay)
                <button type="button"
                    @if ($livewireClose)
                        wire:click="closeFo51PdfUploadModal"
                    @else
                        @click="$dispatch('fo51-pdf-upload-close')"
                    @endif
                    class="shrink-0 rounded-md p-2 text-slate-500 hover:bg-slate-100 hover:text-slate-900 dark:text-slate-400 dark:hover:bg-white/10 dark:hover:text-white"
                    aria-label="Cerrar">
                    ✕
                </button>
            @endif
        </div>

        <form method="post" action="{{ route('disciplinary.forms.informe.process') }}" enctype="multipart/form-data"
            class="flex min-h-0 flex-1 flex-col"
            @submit="evidenceSheetOpen = false">
            @csrf
            <input type="hidden" name="fo51_action" value="cargar">
            <input type="hidden" name="fo51_employee_id" x-model="employeeId">

            <div class="min-h-0 flex-1 space-y-4 overflow-y-auto px-4 py-4 sm:px-5">
                @error('fo51_action')
                    <div class="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-800 ring-1 ring-red-200 dark:bg-red-500/15 dark:text-red-200 dark:ring-red-500/30">
                        {{ $message }}
                    </div>
                @enderror

                <section
                    class="space-y-3 rounded-xl border border-slate-200 bg-white p-3.5 dark:border-white/10 dark:bg-white/[0.04]"
                    x-data="window.fo51PdfUploadFileZone({ maxBytes: 15 * 1024 * 1024, inputId: 'pdf_upload_informe_file' })"
                    @paste.window="handleWindowPaste($event)">
                    <div>
                        <h3 class="text-sm font-semibold text-slate-900 dark:text-slate-100">1 · Archivo del informe</h3>
                        <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">
                            PDF FO-GJ-51 <span class="text-red-600">*</span> · clic, arrastrar o pegar (Ctrl+V)
                        </p>
                    </div>

                    <input id="pdf_upload_informe_file" type="file" name="informe_file" accept="application/pdf,.pdf" required
                        class="sr-only"
                        @change="onInputChange($event)">

                    <div x-show="! hasFile">
                        <button type="button"
                            class="flex w-full flex-col items-center justify-center rounded-xl border-2 border-dashed px-4 py-8 text-center transition"
                            :class="dragging
                                ? 'border-indigo-400 bg-indigo-50/80 dark:border-indigo-400/50 dark:bg-indigo-500/10'
                                : 'border-slate-300 bg-slate-50/80 hover:border-indigo-300 dark:border-white/15 dark:bg-white/[0.03] dark:hover:border-indigo-400/40'"
                            @click="openPicker()"
                            @dragenter.prevent="dragging = true"
                            @dragover.prevent="dragging = true"
                            @dragleave.prevent="dragging = false"
                            @drop.prevent="onDrop($event)">
                            <svg class="h-9 w-9 text-slate-400 dark:text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
                            </svg>
                            <p class="mt-3 text-sm font-semibold text-slate-800 dark:text-slate-100">Suelte el PDF aquí</p>
                            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">o haga clic para seleccionar · también puede pegar</p>
                            <div class="mt-3 flex flex-wrap justify-center gap-1.5">
                                <span class="rounded-md bg-white px-2 py-0.5 text-[10px] font-semibold text-slate-600 ring-1 ring-slate-200 dark:bg-white/10 dark:text-slate-300 dark:ring-white/10">.pdf</span>
                                <span class="rounded-md bg-white px-2 py-0.5 text-[10px] font-semibold text-slate-600 ring-1 ring-slate-200 dark:bg-white/10 dark:text-slate-300 dark:ring-white/10">máx. 15 MB</span>
                            </div>
                        </button>
                    </div>

                    <div x-show="hasFile" x-cloak class="space-y-3" style="display: none;">
                        <div class="flex items-start justify-between gap-3 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2.5 dark:border-white/10 dark:bg-white/[0.05]">
                            <div class="min-w-0">
                                <p class="truncate text-sm font-semibold text-slate-900 dark:text-white" x-text="fileName"></p>
                                <p class="text-xs text-slate-500 dark:text-slate-400">
                                    <span x-text="fileSizeLabel"></span>
                                    <span x-show="pageCount > 0" x-text="' · ' + pageCount + ' pág.'"></span>
                                </p>
                            </div>
                            <div class="flex shrink-0 flex-wrap justify-end gap-2">
                                <button type="button" @click="openLightbox()"
                                    class="text-xs font-semibold text-indigo-600 hover:text-indigo-800 dark:text-indigo-300 dark:hover:text-indigo-200">
                                    Ampliar
                                </button>
                                <button type="button" @click="openInNewTab()"
                                    class="text-xs font-semibold text-indigo-600 hover:text-indigo-800 dark:text-indigo-300 dark:hover:text-indigo-200">
                                    Abrir en pestaña
                                </button>
                                <button type="button" @click="changeFile()"
                                    class="text-xs font-semibold text-indigo-600 hover:text-indigo-800 dark:text-indigo-300 dark:hover:text-indigo-200">
                                    Cambiar
                                </button>
                                <button type="button" @click="clearFile()"
                                    class="text-xs font-semibold text-slate-500 hover:text-slate-800 dark:text-slate-400 dark:hover:text-white">
                                    Quitar
                                </button>
                            </div>
                        </div>

                        <div
                            x-ref="inlineScroll"
                            class="h-72 overflow-y-auto overscroll-contain rounded-lg border border-slate-200 bg-slate-100 dark:border-white/10 dark:bg-black/40 sm:h-80"
                            style="overscroll-behavior: contain; -webkit-overflow-scrolling: touch;">
                            <div x-show="rendering" class="px-3 py-6 text-center text-xs text-slate-500 dark:text-slate-400">
                                Renderizando PDF…
                            </div>
                            <div x-ref="inlinePages" class="space-y-2 p-2"></div>
                        </div>

                        <p x-show="renderError" x-cloak class="text-sm text-amber-700 dark:text-amber-200" x-text="renderError" style="display: none;"></p>
                        <p class="text-xs text-slate-500 dark:text-slate-400">
                            Vista previa con scroll real (PDF.js). Use la rueda o la barra dentro del recuadro.
                        </p>
                    </div>

                    <template x-teleport="body">
                        <div
                            x-show="lightboxOpen"
                            x-cloak
                            class="fixed inset-0 z-[120] flex items-center justify-center p-3 sm:p-4"
                            style="display: none;"
                            role="dialog"
                            aria-modal="true"
                            aria-labelledby="fo51-pdf-lightbox-title"
                            @keydown.escape.window="if (lightboxOpen) closeLightbox()">
                            <div class="absolute inset-0 bg-black/60" @click="closeLightbox()" aria-hidden="true"></div>
                            <div class="relative flex h-[min(92dvh,calc(100dvh-2rem))] w-full max-w-5xl flex-col overflow-hidden rounded-xl bg-white shadow-2xl ring-1 ring-slate-200 dark:bg-dash-ink dark:ring-white/15">
                                <div class="flex shrink-0 items-center justify-between gap-3 border-b border-slate-200 px-4 py-3 dark:border-white/10 sm:px-5">
                                    <div class="min-w-0">
                                        <h2 id="fo51-pdf-lightbox-title" class="truncate text-base font-bold text-slate-900 dark:text-white" x-text="fileName"></h2>
                                        <p class="text-xs text-slate-500 dark:text-slate-400">PDF.js · desplácese el documento aquí</p>
                                    </div>
                                    <div class="flex shrink-0 flex-wrap items-center justify-end gap-2">
                                        <button type="button" @click="openInNewTab()"
                                            class="rounded-md px-3 py-1.5 text-xs font-semibold text-indigo-800 ring-1 ring-indigo-300 hover:bg-indigo-50 dark:text-indigo-200 dark:ring-indigo-400/40 dark:hover:bg-white/10">
                                            Abrir en pestaña
                                        </button>
                                        <button type="button" @click="changeFile()"
                                            class="rounded-md px-3 py-1.5 text-xs font-semibold text-slate-700 ring-1 ring-slate-300 hover:bg-slate-50 dark:text-slate-200 dark:ring-white/20 dark:hover:bg-white/10">
                                            Cambiar
                                        </button>
                                        <button type="button" @click="closeLightbox()"
                                            class="rounded-md p-2 text-slate-500 hover:bg-slate-100 hover:text-slate-900 dark:text-slate-400 dark:hover:bg-white/10 dark:hover:text-white"
                                            aria-label="Cerrar vista ampliada">
                                            ✕
                                        </button>
                                    </div>
                                </div>
                                <div
                                    x-ref="lightboxScroll"
                                    class="min-h-0 flex-1 overflow-y-auto overscroll-contain bg-slate-100 dark:bg-black/40"
                                    style="overscroll-behavior: contain; -webkit-overflow-scrolling: touch;">
                                    <div x-show="rendering" class="px-3 py-8 text-center text-sm text-slate-500 dark:text-slate-400">
                                        Renderizando PDF…
                                    </div>
                                    <div x-ref="lightboxPages" class="mx-auto max-w-4xl space-y-3 p-3 sm:p-4"></div>
                                </div>
                            </div>
                        </div>
                    </template>

                    <p x-show="error" x-cloak class="text-sm text-red-600 dark:text-red-400" x-text="error" style="display: none;"></p>
                    @error('informe_file')
                        <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </section>

                <section class="space-y-3 rounded-xl border border-slate-200 bg-white p-3.5 dark:border-white/10 dark:bg-white/[0.04]">
                    <h3 class="text-sm font-semibold text-slate-900 dark:text-slate-100">2 · Trabajador</h3>
                    <div>
                        <label for="pdf_upload_worker_document" class="block text-[11px] font-semibold uppercase tracking-wide text-slate-600 dark:text-slate-400">
                            CC <span class="text-red-600">*</span>
                        </label>
                        <div class="relative mt-1.5">
                            <input id="pdf_upload_worker_document" type="text" name="informe_worker_document" required
                                class="block w-full rounded-md border-slate-300 text-sm shadow-sm dark:border-white/15 dark:bg-dash-lift dark:text-white"
                                x-model="query" autocomplete="off" inputmode="numeric" pattern="[0-9]*"
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
                        @error('informe_worker_document')
                            <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label for="pdf_upload_worker_name" class="block text-[11px] font-semibold uppercase tracking-wide text-slate-600 dark:text-slate-400">
                            Nombre completo <span class="text-red-600">*</span>
                        </label>
                        <input id="pdf_upload_worker_name" type="text" name="informe_worker_name" required maxlength="500"
                            class="mt-1.5 block w-full rounded-md border-slate-300 text-sm shadow-sm dark:border-white/15 dark:bg-dash-lift dark:text-white"
                            x-model="workerName" autocomplete="off"
                            placeholder="Nombre completo">
                        @error('informe_worker_name')
                            <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label class="block text-[11px] font-semibold uppercase tracking-wide text-slate-600 dark:text-slate-400">Cargo</label>
                        <input type="text" name="fo51_worker_cargo" maxlength="120"
                            class="mt-1.5 block w-full rounded-md border-slate-300 bg-slate-50 text-sm shadow-sm dark:border-white/15 dark:bg-white/5 dark:text-white"
                            x-model="workerCargo" readonly tabindex="-1"
                            value="{{ old('fo51_worker_cargo') }}">
                    </div>
                </section>

                <section class="space-y-3 rounded-xl border border-slate-200 bg-white p-3.5 dark:border-white/10 dark:bg-white/[0.04]">
                    <h3 class="text-sm font-semibold text-slate-900 dark:text-slate-100">3 · Datos del informe</h3>
                    <div>
                        <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-600 dark:text-slate-400">
                            Fecha del informe <span class="text-red-600">*</span>
                        </p>
                        <div class="mt-1.5 grid grid-cols-3 gap-2">
                            <div>
                                <label for="pdf_upload_dd" class="sr-only">Día</label>
                                <input id="pdf_upload_dd" name="fo51_report_dd" maxlength="2" inputmode="numeric" required
                                    value="{{ $defaultDd }}"
                                    class="block w-full rounded-md border-slate-300 text-center text-sm dark:border-white/15 dark:bg-dash-lift dark:text-white"
                                    placeholder="DD">
                            </div>
                            <div>
                                <label for="pdf_upload_mm" class="sr-only">Mes</label>
                                <input id="pdf_upload_mm" name="fo51_report_mm" maxlength="2" inputmode="numeric" required
                                    value="{{ $defaultMm }}"
                                    class="block w-full rounded-md border-slate-300 text-center text-sm dark:border-white/15 dark:bg-dash-lift dark:text-white"
                                    placeholder="MM">
                            </div>
                            <div>
                                <label for="pdf_upload_yyyy" class="sr-only">Año</label>
                                <input id="pdf_upload_yyyy" name="fo51_report_yyyy" maxlength="4" inputmode="numeric" required
                                    value="{{ $defaultYyyy }}"
                                    class="block w-full rounded-md border-slate-300 text-center text-sm dark:border-white/15 dark:bg-dash-lift dark:text-white"
                                    placeholder="AAAA">
                            </div>
                        </div>
                        @error('fo51_report_dd')
                            <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <div
                        x-data="window.disciplinaryFo51MunicipalityCombo(@js($munItems), @js(old('fo51_municipality_code', '')), { required: true })"
                        @fo51-employee-selected.window="if ($event.detail.municipalityCode) { code = $event.detail.municipalityCode; const it = items.find(i => i.code === code); if (it) { query = it.name + ' — ' + it.dept; } }">
                        <label for="pdf_upload_city" class="block text-[11px] font-semibold uppercase tracking-wide text-slate-600 dark:text-slate-400">
                            Ciudad <span class="text-red-600">*</span>
                        </label>
                        <input type="hidden" name="fo51_municipality_code" x-model="code" required>
                        <div class="relative mt-1.5">
                            <input id="pdf_upload_city" type="text" class="block w-full rounded-md border-slate-300 text-sm shadow-sm dark:border-white/15 dark:bg-dash-lift dark:text-white"
                                x-model="query" autocomplete="off" placeholder="Escriba municipio o departamento…"
                                @focus="openList()" @input="onInput()" @blur="onBlur()" @keydown="onKeydown($event)">
                            <ul x-show="open && filtered.length" x-cloak
                                class="absolute left-0 right-0 z-[90] mt-0.5 max-h-48 overflow-auto rounded-md border border-slate-300 bg-white py-1 text-xs shadow-xl dark:border-white/20 dark:bg-slate-900"
                                role="listbox">
                                <template x-for="(it, idx) in filtered" :key="it.code">
                                    <li class="cursor-pointer px-2 py-1.5 hover:bg-indigo-50 dark:hover:bg-white/10"
                                        :class="{ 'bg-indigo-100 dark:bg-white/15': idx === highlightedIndex }"
                                        @mousedown.prevent="selectItem(it)"
                                        x-text="it.name + ' · ' + it.dept"></li>
                                </template>
                            </ul>
                        </div>
                        @error('fo51_municipality_code')
                            <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="pdf_upload_shift" class="block text-[11px] font-semibold uppercase tracking-wide text-slate-600 dark:text-slate-400">Turno</label>
                        <input id="pdf_upload_shift" type="text" name="fo51_shift" maxlength="120" value="{{ old('fo51_shift') }}"
                            class="mt-1.5 block w-full rounded-md border-slate-300 text-sm dark:border-white/15 dark:bg-dash-lift dark:text-white"
                            placeholder="Mañana / Tarde / Noche">
                    </div>
                    <div>
                        <label for="pdf_upload_position" class="block text-[11px] font-semibold uppercase tracking-wide text-slate-600 dark:text-slate-400">Puesto</label>
                        <input id="pdf_upload_position" type="text" name="fo51_position" maxlength="120" value="{{ old('fo51_position') }}"
                            class="mt-1.5 block w-full rounded-md border-slate-300 text-sm dark:border-white/15 dark:bg-dash-lift dark:text-white"
                            placeholder="Instalación o puesto">
                    </div>
                </section>

                <section
                    class="space-y-3 rounded-xl border border-slate-200 bg-white p-3.5 dark:border-white/10 dark:bg-white/[0.04]"
                    x-data="window.fo51PdfUploadFaultsPicker({
                        leftCatalog: @js($faultLeft),
                        rightCatalog: @js($faultRight),
                        initialLeft: @js($faultLeftChecked),
                        initialRight: @js($faultRightChecked),
                        otherChecked: @js($faultOtherChecked),
                        otherDetail: @js($faultOtherDetail),
                    })">
                    <div>
                        <h3 class="text-sm font-semibold text-slate-900 dark:text-slate-100">4 · Faltas</h3>
                        <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">
                            Elija del catálogo FO-GJ-51 (misma falta del PDF). Solo «Otros» es fijo.
                        </p>
                    </div>

                    <template x-for="label in selectedLeft" :key="'L-' + label">
                        <input type="hidden" name="fo51_fault_left[]" :value="label">
                    </template>
                    <template x-for="label in selectedRight" :key="'R-' + label">
                        <input type="hidden" name="fo51_fault_right[]" :value="label">
                    </template>

                    <div class="space-y-2" @keydown.escape.window="if (open) closePanel()">
                        <span class="block text-[11px] font-semibold uppercase tracking-wide text-slate-600 dark:text-slate-400">
                            Falta del catálogo
                        </span>
                        <div class="relative">
                            <button type="button" id="pdf_upload_fault_select"
                                class="flex min-h-11 w-full items-center justify-between gap-2 rounded-md border border-slate-300 bg-white px-3 text-left text-sm shadow-sm dark:border-white/15 dark:bg-dash-lift dark:text-white"
                                @click="togglePanel()"
                                :aria-expanded="open ? 'true' : 'false'"
                                aria-haspopup="listbox"
                                aria-controls="pdf_upload_fault_panel">
                                <span class="min-w-0 truncate"
                                    :class="selected.length ? 'text-slate-900 dark:text-white' : 'text-slate-500 dark:text-slate-400'"
                                    x-text="triggerLabel"></span>
                                <span class="shrink-0 text-slate-400" aria-hidden="true">▾</span>
                            </button>

                            <div id="pdf_upload_fault_panel"
                                x-show="open"
                                x-cloak
                                x-transition.opacity.duration.150ms
                                class="absolute left-0 right-0 z-[80] mt-1 overflow-hidden rounded-md border border-slate-300 bg-white shadow-xl dark:border-white/20 dark:bg-slate-900"
                                style="display: none;"
                                @click.outside="closePanel()"
                                role="listbox"
                                aria-multiselectable="true"
                                aria-label="Catálogo de faltas FO-GJ-51">
                                <ul class="max-h-56 overflow-y-auto py-1">
                                    <template x-for="opt in catalog" :key="opt">
                                        <li role="option" :aria-selected="isDraftChecked(opt) ? 'true' : 'false'">
                                            <label
                                                class="flex cursor-pointer items-start gap-2.5 px-3 py-2 text-sm text-slate-800 hover:bg-indigo-50 dark:text-slate-100 dark:hover:bg-white/10"
                                                :class="{ 'bg-indigo-50 dark:bg-white/10': isDraftChecked(opt) }">
                                                <input type="checkbox"
                                                    class="mt-0.5 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 dark:border-white/20 dark:bg-dash-lift"
                                                    :checked="isDraftChecked(opt)"
                                                    @change="toggleDraft(opt)">
                                                <span class="min-w-0 leading-snug" x-text="opt"></span>
                                            </label>
                                        </li>
                                    </template>
                                </ul>
                                <div class="flex items-center justify-end border-t border-slate-200 px-3 py-2 dark:border-white/10">
                                    <button type="button"
                                        class="text-sm font-semibold text-indigo-600 hover:text-indigo-800 dark:text-indigo-300 dark:hover:text-indigo-200"
                                        @click="saveDraft()">
                                        Guardar
                                    </button>
                                </div>
                            </div>
                        </div>
                        <p class="text-xs text-slate-500 dark:text-slate-400">
                            Marque una o varias faltas y pulse Guardar.
                        </p>
                    </div>

                    <div x-show="selected.length" x-cloak class="space-y-2">
                        <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-600 dark:text-slate-400">
                            Seleccionadas (<span x-text="selected.length"></span>)
                        </p>
                        <div class="flex flex-wrap gap-2">
                            <template x-for="label in selected" :key="label">
                                <button type="button"
                                    class="inline-flex max-w-full items-center gap-1.5 rounded-full border border-slate-300 bg-slate-100 px-2.5 py-1 text-left text-xs font-medium text-slate-800 hover:bg-slate-200 dark:border-white/15 dark:bg-white/10 dark:text-slate-100 dark:hover:bg-white/15"
                                    @click="remove(label)"
                                    :title="'Quitar: ' + label">
                                    <span class="truncate" x-text="label"></span>
                                    <span class="shrink-0 text-slate-500 dark:text-slate-400" aria-hidden="true">×</span>
                                </button>
                            </template>
                        </div>
                    </div>

                    <div class="space-y-2 border-t border-slate-100 pt-3 dark:border-white/10">
                        <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-600 dark:text-slate-400">Fijo</p>
                        <label class="flex cursor-pointer items-start gap-2 text-sm font-medium text-slate-800 dark:text-slate-200">
                            <input type="checkbox" name="fo51_fault_other_chk" value="1"
                                class="mt-0.5 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 dark:border-white/20 dark:bg-dash-lift"
                                x-model="otherChecked">
                            <span>Otros</span>
                        </label>
                        <div>
                            <label for="pdf_upload_fault_other_detail" class="sr-only">¿Cuál?</label>
                            <input id="pdf_upload_fault_other_detail" type="text" name="fo51_fault_other_detail" maxlength="500"
                                class="block w-full rounded-md border-slate-300 text-sm dark:border-white/15 dark:bg-dash-lift dark:text-white disabled:opacity-50"
                                placeholder="¿Cuál?"
                                x-model="otherDetail"
                                :disabled="! otherChecked">
                        </div>
                    </div>

                    @error('fo51_fault_left')
                        <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                    @error('fo51_fault_right')
                        <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                    @error('fo51_fault_other_detail')
                        <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </section>

                <section class="space-y-3 rounded-xl border border-slate-200 bg-white p-3.5 dark:border-white/10 dark:bg-white/[0.04]">
                    <h3 class="text-sm font-semibold text-slate-900 dark:text-slate-100">5 · Pruebas / evidencias</h3>
                    <p class="text-xs text-slate-500 dark:text-slate-400">Opcional · hasta 10 imágenes · se envían con el PDF a revisión.</p>
                    <button type="button" @click="evidenceSheetOpen = true"
                        class="inline-flex min-h-11 w-full items-center justify-center rounded-md border border-emerald-600/40 bg-white px-4 text-sm font-semibold text-emerald-800 hover:bg-emerald-50 dark:border-emerald-500/30 dark:bg-white/5 dark:text-emerald-200">
                        <span x-text="urls.filter(Boolean).length ? `Gestionar pruebas (${urls.filter(Boolean).length})` : 'Agregar pruebas (opcional)'"></span>
                    </button>
                    <div class="flex gap-2 overflow-x-auto">
                        <template x-for="(url, idx) in urls" :key="idx">
                            <div x-show="url" class="relative h-12 w-12 shrink-0 overflow-hidden rounded-md bg-slate-200 dark:bg-black/40">
                                <img :src="url" alt="" class="h-full w-full object-cover">
                            </div>
                        </template>
                    </div>
                    @error('evidence_images')
                        <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </section>

                <section class="space-y-3 rounded-xl border border-slate-200 bg-white p-3.5 dark:border-white/10 dark:bg-white/[0.04]">
                    <h3 class="text-sm font-semibold text-slate-900 dark:text-slate-100">6 · Envío</h3>
                    <div>
                        <label for="pdf_upload_reviewer" class="block text-[11px] font-semibold uppercase tracking-wide text-slate-600 dark:text-slate-400">
                            Revisor de operaciones <span class="text-red-600">*</span>
                        </label>
                        <select id="pdf_upload_reviewer" name="fo51_assigned_reviewer_id" required
                            class="mt-1.5 block w-full rounded-md border-slate-300 text-sm dark:border-white/15 dark:bg-dash-lift dark:text-white">
                            <option value="">— Seleccione revisor —</option>
                            @foreach ($operacionesReviewers as $rev)
                                <option value="{{ $rev->id }}" @selected((int) old('fo51_assigned_reviewer_id') === (int) $rev->id)>{{ $rev->name }}</option>
                            @endforeach
                        </select>
                        @error('fo51_assigned_reviewer_id')
                            <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>
                </section>
            </div>

            <div class="flex shrink-0 flex-col gap-2 border-t border-slate-200 bg-slate-50/95 px-4 py-3 dark:border-white/10 dark:bg-dash-ink/95 sm:px-5">
                <button type="submit"
                    class="inline-flex min-h-12 w-full items-center justify-center rounded-md bg-indigo-600 px-4 text-sm font-semibold text-white hover:bg-indigo-700 dark:bg-indigo-500">
                    Cargar PDF y enviar a revisión
                </button>
                @if ($asPageOverlay)
                    <button type="button"
                        @if ($livewireClose)
                            wire:click="closeFo51PdfUploadModal"
                        @else
                            @click="$dispatch('fo51-pdf-upload-close')"
                        @endif
                        class="inline-flex min-h-10 w-full items-center justify-center rounded-md text-sm font-semibold text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-white/10">
                        Cancelar
                    </button>
                @endif
            </div>
        </form>

        {{-- Sheet evidencias --}}
        <div x-show="evidenceSheetOpen" x-cloak
            class="absolute inset-0 z-[70] flex flex-col bg-black/40"
            style="display: none;"
            @click.self="evidenceSheetOpen = false">
            <div class="mt-auto flex max-h-[85%] flex-col overflow-hidden rounded-t-xl bg-white dark:bg-dash-ink" @click.stop>
                <div class="flex items-start justify-between gap-2 border-b border-slate-200 px-4 py-3 dark:border-white/10">
                    <div>
                        <p class="text-[11px] font-bold uppercase tracking-widest text-emerald-700 dark:text-emerald-300">Pruebas</p>
                        <h3 class="text-base font-bold text-slate-900 dark:text-white">Evidencia fotográfica</h3>
                    </div>
                    <button type="button" @click="evidenceSheetOpen = false" class="rounded-md p-2 text-slate-400 hover:bg-slate-100 dark:hover:bg-white/10" aria-label="Cerrar">✕</button>
                </div>
                <div class="min-h-0 flex-1 overflow-y-auto px-4 py-4">
                    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                        @for ($i = 0; $i < 10; $i++)
                            <div class="relative aspect-[5/4] overflow-hidden rounded-xl border border-emerald-400/25 bg-slate-900/40">
                                <input type="file"
                                    id="pdf_upload_evidence_in_{{ $i }}"
                                    name="evidence_images[]"
                                    accept="image/jpeg,image/png,image/gif,image/webp,.jpg,.jpeg,.png,.gif,.webp"
                                    class="sr-only"
                                    x-on:change="setPreview({{ $i }}, $event)">
                                <label for="pdf_upload_evidence_in_{{ $i }}" class="absolute inset-0 flex cursor-pointer items-center justify-center">
                                    <img x-show="urls[{{ $i }}]" x-bind:src="urls[{{ $i }}]" alt="" class="absolute inset-0 h-full w-full object-cover">
                                    <span x-show="! urls[{{ $i }}]" class="text-3xl font-extralight text-white/90">+</span>
                                </label>
                                <button type="button" x-show="urls[{{ $i }}]" x-on:click.prevent.stop="clear({{ $i }})"
                                    class="absolute right-1 top-1 z-10 flex h-7 w-7 items-center justify-center rounded-full bg-rose-600 text-xs font-bold text-white">×</button>
                            </div>
                        @endfor
                    </div>
                </div>
                <div class="border-t border-slate-200 px-4 py-3 dark:border-white/10">
                    <button type="button" @click="evidenceSheetOpen = false"
                        class="inline-flex min-h-11 w-full items-center justify-center rounded-md bg-indigo-600 text-sm font-semibold text-white">Listo</button>
                </div>
            </div>
        </div>
    </div>
</div>
