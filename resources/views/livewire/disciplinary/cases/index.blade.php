@php
    use App\Support\Disciplinary\WorkflowStageBuckets;

    $isMinimal = auth()->user()->isMinimalDisciplinaryPortalUser();
    $isOperaciones = auth()->user()->isDisciplinaryOperacionesReviewer();
    $rail = $this->stageRail;
    $stageActive = $stage;
    $letterColors = $stageColors;
@endphp

<div class="disciplinary-cases-index mx-auto flex h-[calc(100dvh-3.25rem)] max-h-[calc(100dvh-3.25rem)] w-full max-w-[1600px] flex-col overflow-hidden px-3 py-2 sm:px-5 sm:py-3 lg:px-6">
    @push('module-nav')
        <x-disciplinary.nav />
    @endpush

    <header class="mb-2 flex shrink-0 flex-wrap items-center justify-between gap-2 border-b border-white/10 pb-2 dark:border-white/10">
        <div class="min-w-0">
            @if (auth()->user()->isDisciplinaryProgramador())
                <p class="text-[10px] font-bold uppercase tracking-[0.2em] text-fuchsia-400/90">Planeación · Solicitudes</p>
                <h1 class="truncate text-sm font-semibold text-slate-900 dark:text-white">Citaciones y agendas asignadas</h1>
            @elseif (auth()->user()->isDisciplinaryFieldOperator())
                <p class="text-[10px] font-bold uppercase tracking-[0.2em] text-fuchsia-400/90">Disciplinarios · Campo</p>
                <h1 class="truncate text-sm font-semibold text-slate-900 dark:text-white">Notificaciones asignadas</h1>
            @elseif ($isOperaciones)
                <p class="text-[10px] font-bold uppercase tracking-[0.2em] text-fuchsia-400/90">Disciplinarios · Operaciones</p>
                <h1 class="truncate text-sm font-semibold text-slate-900 dark:text-white">Casos abiertos que autorizó</h1>
            @else
                <p class="text-[10px] font-bold uppercase tracking-[0.2em] text-fuchsia-400/90">Disciplinarios · Listado</p>
                <h1 class="truncate text-sm font-semibold text-slate-900 dark:text-white">Procesos disciplinarios</h1>
            @endif
        </div>
        <div class="flex shrink-0 flex-wrap items-center gap-2">
            @can('generateFo51Inform', \App\Models\Disciplinary\DisciplinaryCase::class)
                <x-ui.btn type="button" wire:click="openFo51Modal(false)" class="!h-8 text-xs">
                    Nuevo informe (FO-GJ-51)
                </x-ui.btn>
                <x-ui.btn type="button" variant="ghost" wire:click="openFo51Modal(true)" class="!h-8 text-xs">
                    Cargar PDF
                </x-ui.btn>
            @endcan
            @unless ($isMinimal || $isOperaciones)
                @can('viewDashboard', \App\Models\Disciplinary\DisciplinaryCase::class)
                    <x-dashboard.button href="{{ route('disciplinary.dashboard') }}" variant="ghost" class="!h-8 text-xs">
                        ← Dashboard
                    </x-dashboard.button>
                @else
                    <x-dashboard.button href="{{ route('dashboard') }}" variant="ghost" class="!h-8 text-xs">
                        ← Inicio
                    </x-dashboard.button>
                @endcan
            @endunless
        </div>
    </header>

    @if (session('error'))
        <div class="mb-2 shrink-0 rounded-lg bg-red-50 px-3 py-2 text-xs text-red-900 ring-1 ring-red-200 dark:bg-red-500/15 dark:text-red-100 dark:ring-red-500/30">
            {{ session('error') }}
        </div>
    @endif
    @if (session('success'))
        <div class="mb-2 shrink-0 rounded-lg bg-emerald-50 px-3 py-2 text-xs text-emerald-900 ring-1 ring-emerald-200 dark:bg-emerald-500/15 dark:text-emerald-100 dark:ring-emerald-500/30">
            {{ session('success') }}
        </div>
    @endif

    @unless ($isMinimal)
        {{-- Rail de etapas A–F (una línea) --}}
        <nav
            class="mb-2 flex shrink-0 flex-wrap items-center gap-0.5 overflow-x-auto rounded-lg border border-slate-200 bg-white px-1 py-1 shadow-sm ring-1 ring-slate-200 dark:border-white/10 dark:bg-white/[0.04] dark:ring-white/5 sm:gap-1 sm:px-1.5"
            aria-label="Filtrar por etapa del proceso"
        >
            @foreach ($rail['stages'] as $st)
                @php
                    $isActive = $stageActive === $st['letter'];
                    $color = $letterColors[$st['letter']] ?? 'text-slate-400';
                @endphp
                <button
                    type="button"
                    wire:click="setStage('{{ $st['letter'] }}')"
                    title="{{ $st['title'] }}"
                    @class([
                        'inline-flex min-w-[2.75rem] shrink-0 items-center justify-center gap-1 rounded-md px-2 py-1.5 text-xs font-semibold tabular-nums transition',
                        'bg-slate-100 ring-1 ring-slate-300 dark:bg-white/10 dark:ring-white/20' => $isActive,
                        'text-slate-600 hover:bg-slate-50 dark:text-slate-300 dark:hover:bg-white/[0.06]' => ! $isActive,
                    ])
                >
                    <span class="{{ $color }}">{{ $st['letter'] }}</span>
                    <span class="text-slate-700 dark:text-slate-200">{{ number_format($st['count']) }}</span>
                </button>
            @endforeach

            @unless ($isOperaciones)
                <span class="mx-0.5 hidden h-5 w-px shrink-0 bg-slate-200 dark:bg-white/15 sm:block" aria-hidden="true"></span>

                <button
                    type="button"
                    wire:click="setStage('cerrados')"
                    title="Casos finalizados o archivados"
                    @class([
                        'inline-flex shrink-0 items-center gap-1 rounded-md px-2 py-1.5 text-xs font-semibold tabular-nums transition',
                        'bg-slate-100 ring-1 ring-slate-300 dark:bg-white/10 dark:ring-white/20' => $stageActive === WorkflowStageBuckets::CLOSED_KEY,
                        'text-slate-600 hover:bg-slate-50 dark:text-slate-300 dark:hover:bg-white/[0.06]' => $stageActive !== WorkflowStageBuckets::CLOSED_KEY,
                    ])
                >
                    <span class="text-emerald-500 dark:text-emerald-400">Cerrados</span>
                    <span class="text-slate-700 dark:text-slate-200">{{ number_format($rail['closed']) }}</span>
                </button>
            @endunless

            <button
                type="button"
                wire:click="setStage('')"
                title="Todos los casos en su alcance"
                @class([
                    'inline-flex shrink-0 items-center gap-1 rounded-md px-2 py-1.5 text-xs font-semibold transition',
                    'bg-slate-100 ring-1 ring-slate-300 dark:bg-white/10 dark:ring-white/20' => $stageActive === '',
                    'text-slate-600 hover:bg-slate-50 dark:text-slate-300 dark:hover:bg-white/[0.06]' => $stageActive !== '',
                ])
            >
                Todos
                <span class="tabular-nums text-slate-700 dark:text-slate-200">{{ number_format($rail['total']) }}</span>
            </button>
        </nav>

        {{-- Filtros compactos --}}
        <div class="mb-2 shrink-0 space-y-2">
            <div class="flex flex-wrap items-end gap-2">
                <div class="min-w-[12rem] flex-1">
                    <label for="dcf-case-search" class="sr-only">Buscador</label>
                    <input
                        id="dcf-case-search"
                        name="dcf_case_search"
                        type="search"
                        wire:model.live.debounce.350ms="search"
                        placeholder="N° de caso, nombre, documento…"
                        autocomplete="off"
                        class="w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-white/15 dark:bg-dash-lift dark:text-white"
                    >
                </div>
                <div class="w-full sm:w-44">
                    <label for="dcf-case-status" class="sr-only">Estado</label>
                    <select
                        id="dcf-case-status"
                        name="dcf_case_status"
                        wire:model.live="status"
                        class="w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-white/15 dark:bg-dash-lift dark:text-white"
                    >
                        <option value="">Estado — todos</option>
                        @foreach ($statuses as $s)
                            @continue($isOperaciones && $s->isTerminal())
                            <option value="{{ $s->value }}">{{ $s->label() }}</option>
                        @endforeach
                    </select>
                </div>
                @unless ($isMinimal || $isOperaciones)
                    <div class="w-full sm:w-40">
                        <label for="dcf-case-lawyer" class="sr-only">Abogado</label>
                        <select
                            id="dcf-case-lawyer"
                            name="dcf_case_lawyer"
                            wire:model.live="lawyerId"
                            class="w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-white/15 dark:bg-dash-lift dark:text-white"
                        >
                            <option value="">Abogado — todos</option>
                            @foreach ($this->lawyers as $u)
                                <option value="{{ $u->id }}">{{ $u->name }}</option>
                            @endforeach
                        </select>
                    </div>
                @endunless
                <button
                    type="button"
                    wire:click="toggleAdvancedFilters"
                    class="inline-flex h-9 items-center rounded-md px-3 text-xs font-semibold text-slate-600 ring-1 ring-slate-200 hover:bg-slate-50 dark:text-slate-300 dark:ring-white/15 dark:hover:bg-white/[0.06]"
                >
                    {{ $showAdvancedFilters ? 'Menos filtros' : 'Más filtros' }}
                </button>
                @if ($search !== '' || $stage !== '' || $this->hasSecondaryFilters)
                    <button
                        type="button"
                        wire:click="clearFilters"
                        class="inline-flex h-9 items-center rounded-md px-3 text-xs font-semibold text-slate-600 ring-1 ring-slate-200 hover:bg-slate-50 dark:text-slate-300 dark:ring-white/15 dark:hover:bg-white/[0.06]"
                    >
                        Limpiar
                    </button>
                @endif
            </div>

            @if ($showAdvancedFilters)
                <div class="grid grid-cols-1 gap-2 rounded-lg border border-slate-200 bg-white p-3 ring-1 ring-slate-200 dark:border-white/10 dark:bg-white/[0.03] dark:ring-white/5 sm:grid-cols-3">
                    <div>
                        <label for="dcf-case-city" class="mb-1 block text-[10px] font-semibold uppercase tracking-wide text-slate-500 dark:text-dash-muted">Municipio / ciudad</label>
                        <select id="dcf-case-city" name="dcf_case_city" wire:model.live="city" class="w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-white/15 dark:bg-dash-lift dark:text-white">
                            <option value="">— Todas —</option>
                            @foreach ($this->cities as $opt)
                                <option value="{{ $opt['value'] }}">{{ $opt['label'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="dcf-case-fault" class="mb-1 block text-[10px] font-semibold uppercase tracking-wide text-slate-500 dark:text-dash-muted">Falta</label>
                        <select id="dcf-case-fault" name="dcf_case_fault" wire:model.live="faultId" class="w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-white/15 dark:bg-dash-lift dark:text-white">
                            <option value="">— Todas —</option>
                            @foreach ($this->faults as $f)
                                <option value="{{ $f->id }}">{{ $f->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label for="dcf-case-from" class="mb-1 block text-[10px] font-semibold uppercase tracking-wide text-slate-500 dark:text-dash-muted">Desde</label>
                            <input id="dcf-case-from" name="dcf_case_from" type="date" wire:model.live="from" class="w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-white/15 dark:bg-dash-lift dark:text-white">
                        </div>
                        <div>
                            <label for="dcf-case-to" class="mb-1 block text-[10px] font-semibold uppercase tracking-wide text-slate-500 dark:text-dash-muted">Hasta</label>
                            <input id="dcf-case-to" name="dcf_case_to" type="date" wire:model.live="to" class="w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-white/15 dark:bg-dash-lift dark:text-white">
                        </div>
                    </div>
                </div>
            @endif
        </div>
    @else
        <div class="mb-2 shrink-0">
            <label for="dcf-case-search-min" class="sr-only">Buscador</label>
            <input
                id="dcf-case-search-min"
                name="dcf_case_search_min"
                type="search"
                wire:model.live.debounce.350ms="search"
                placeholder="N° de caso, nombre, documento…"
                autocomplete="off"
                class="w-full max-w-md rounded-md border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-white/15 dark:bg-dash-lift dark:text-white"
            >
        </div>
    @endunless

    {{-- Tabla --}}
    <div class="flex min-h-0 flex-1 flex-col overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm ring-1 ring-slate-200 dark:border-white/10 dark:bg-white/[0.04] dark:ring-white/5">
        <div class="min-h-0 flex-1 overflow-auto">
            <table class="min-w-full divide-y divide-slate-200 dark:divide-white/10">
                <thead class="sticky top-0 z-10 bg-slate-50 text-[10px] uppercase tracking-wider text-slate-500 dark:bg-dash-ink/95 dark:text-dash-muted">
                    <tr>
                        <th class="px-3 py-2 text-left font-semibold">Etapa</th>
                        <th class="px-3 py-2 text-left font-semibold">N° caso</th>
                        <th class="px-3 py-2 text-left font-semibold">Disciplinado</th>
                        <th class="px-3 py-2 text-left font-semibold">Ciudad</th>
                        <th class="px-3 py-2 text-left font-semibold">{{ $isOperaciones ? 'Trámite' : 'Estado' }}</th>
                        @unless ($isMinimal || $isOperaciones)
                            <th class="px-3 py-2 text-left font-semibold">Abogado</th>
                        @endunless
                        @unless ($isOperaciones)
                            <th class="px-3 py-2 text-center font-semibold">Faltas</th>
                        @endunless
                        <th class="px-3 py-2 text-left font-semibold">Apertura</th>
                        <th class="px-3 py-2 text-right font-semibold">Acción</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 bg-white text-sm dark:divide-white/10 dark:bg-transparent">
                    @forelse ($cases as $case)
                        @php
                            $letter = WorkflowStageBuckets::letterForStageType($case->current_stage_type);
                            $letterClass = $letter ? ($letterColors[$letter] ?? 'text-slate-400') : 'text-slate-400';
                            $followUp = $isOperaciones ? $case->operacionesFollowUpSummary() : null;
                        @endphp
                        <tr wire:key="case-row-{{ $case->id }}" class="group hover:bg-slate-50 dark:hover:bg-white/[0.04]">
                            <td class="px-3 py-2.5">
                                @if ($letter)
                                    <span
                                        class="inline-flex h-7 w-7 items-center justify-center rounded-md bg-slate-100 text-xs font-bold ring-1 ring-slate-200 dark:bg-white/[0.06] dark:ring-white/10 {{ $letterClass }}"
                                        title="{{ WorkflowStageBuckets::titleForLetter($letter) }}"
                                    >{{ $letter }}</span>
                                @else
                                    <span class="text-slate-400">—</span>
                                @endif
                            </td>
                            <td class="px-3 py-2.5 font-mono text-xs text-slate-700 dark:text-slate-300">{{ $case->case_number }}</td>
                            <td class="px-3 py-2.5">
                                <div class="font-medium text-slate-900 dark:text-slate-100">
                                    {{ $case->employee?->first_name }} {{ $case->employee?->last_name }}
                                </div>
                                <div class="text-xs text-slate-500 dark:text-slate-400">CC {{ $case->employee?->document_number }}</div>
                            </td>
                            <td class="px-3 py-2.5 text-slate-700 dark:text-slate-300">{{ $case->city ?? '—' }}</td>
                            <td class="px-3 py-2.5 max-w-[14rem]">
                                @if ($isOperaciones && $followUp)
                                    <div class="text-xs font-semibold text-slate-800 dark:text-slate-100">{{ $followUp['headline'] }}</div>
                                    <div class="mt-0.5 truncate text-xs text-slate-500 dark:text-slate-400" title="{{ $followUp['stage_title'] }}">
                                        {{ $followUp['stage_title'] }}
                                    </div>
                                @else
                                    <x-disciplinary.status-badge :status="$case->current_status" class="max-w-full truncate" title="{{ $case->current_status->label() }}" />
                                @endif
                            </td>
                            @unless ($isMinimal || $isOperaciones)
                                <td class="px-3 py-2.5 text-slate-700 dark:text-slate-300">
                                    @if ($case->isInInformePool())
                                        <span class="inline-flex items-center rounded px-1.5 py-0.5 text-xs font-semibold text-amber-700 ring-1 ring-amber-200 dark:text-amber-300 dark:ring-amber-500/40">Bandeja compartida</span>
                                    @else
                                        {{ $case->assignedLawyer?->name ?? '— Sin asignar —' }}
                                    @endif
                                </td>
                            @endunless
                            @unless ($isOperaciones)
                                <td class="px-3 py-2.5 text-center tabular-nums text-slate-700 dark:text-slate-300">{{ $case->faults_count }}</td>
                            @endunless
                            <td class="px-3 py-2.5 whitespace-nowrap text-slate-700 dark:text-slate-300">{{ $case->opened_at?->format('Y-m-d') }}</td>
                            <td class="px-3 py-2.5 text-right">
                                @can('claim', $case)
                                    <button type="button" wire:click="openClaimConfirm({{ $case->id }})"
                                        class="inline-flex items-center rounded-md bg-indigo-600 px-2.5 py-1 text-xs font-semibold text-white hover:bg-indigo-700">
                                        Gestionar
                                    </button>
                                @else
                                    <a href="{{ route('disciplinary.cases.show', $case) }}" wire:navigate
                                        class="inline-flex items-center rounded-md bg-indigo-600 px-2.5 py-1 text-xs font-semibold text-white hover:bg-indigo-700">
                                        @if (auth()->user()->isDisciplinaryProgramador())
                                            Programar
                                        @elseif ($isOperaciones)
                                            Ver
                                        @elseif ($case->isInInformePool() && auth()->user()->hasRole('nivel5'))
                                            Ver
                                        @else
                                            Gestionar
                                        @endif
                                    </a>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $isOperaciones ? 7 : ($isMinimal ? 8 : 9) }}" class="px-4 py-16 text-center text-sm text-slate-500 dark:text-slate-400">
                                @if ($stage !== '' || $search !== '' || $this->hasSecondaryFilters)
                                    No se encontraron casos con los filtros actuales.
                                    <button type="button" wire:click="clearFilters" class="mt-2 block w-full text-xs font-semibold text-indigo-600 hover:text-indigo-500 dark:text-indigo-400">Limpiar filtros</button>
                                @elseif ($isOperaciones)
                                    No hay casos abiertos que haya autorizado.
                                @else
                                    No hay casos en su alcance.
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($cases->hasPages() || $cases->total() > 0)
            <div class="flex shrink-0 flex-wrap items-center justify-between gap-2 border-t border-slate-200 px-3 py-2 text-xs text-slate-500 dark:border-white/10 dark:text-dash-muted">
                <p>
                    Mostrando
                    <span class="font-semibold tabular-nums text-slate-700 dark:text-slate-200">{{ number_format($cases->firstItem() ?? 0) }}</span>–<span class="font-semibold tabular-nums text-slate-700 dark:text-slate-200">{{ number_format($cases->lastItem() ?? 0) }}</span>
                    de
                    <span class="font-semibold tabular-nums text-slate-700 dark:text-slate-200">{{ number_format($cases->total()) }}</span>
                </p>
                <div>{{ $cases->links() }}</div>
            </div>
        @endif
    </div>

    @if ($showClaimConfirm)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4" wire:key="claim-confirm-{{ $claimCaseId }}">
            <div class="w-full max-w-md rounded-lg bg-white p-6 shadow-xl dark:bg-dash-lift dark:ring-1 dark:ring-white/10"
                role="dialog" aria-modal="true" aria-labelledby="claim-confirm-title">
                <h2 id="claim-confirm-title" class="text-lg font-bold text-slate-900 dark:text-white">
                    Confirmar gestión del caso
                </h2>
                <p class="mt-3 text-sm text-slate-600 dark:text-slate-300">
                    ¿Confirma que tomará la gestión del expediente
                    <strong class="font-mono text-slate-900 dark:text-white">{{ $claimCaseNumber }}</strong>?
                    Se le asignará como abogado titular y dejará de estar disponible en la bandeja compartida para otros abogados.
                </p>
                <div class="mt-6 flex flex-wrap justify-end gap-2">
                    <button type="button" wire:click="cancelClaimConfirm"
                        class="rounded-md px-4 py-2 text-sm font-semibold text-slate-700 ring-1 ring-slate-300 hover:bg-slate-50 dark:text-slate-200 dark:ring-white/20 dark:hover:bg-white/10">
                        Cancelar
                    </button>
                    <button type="button" wire:click="confirmClaimCase" wire:loading.attr="disabled"
                        class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700 disabled:opacity-60">
                        <span wire:loading.remove wire:target="confirmClaimCase">Sí, gestionar caso</span>
                        <span wire:loading wire:target="confirmClaimCase">Asignando…</span>
                    </button>
                </div>
            </div>
        </div>
    @endif

    @if ($showFo51Modal)
        @include('disciplinary.forms.partials.fo-gj-51-informe-modal-shell', [
            'prefillWorkerName' => $fo51PrefillName,
            'prefillWorkerDocument' => $fo51PrefillDocument,
            'openPdfUploadModal' => false,
            'operacionesReviewers' => $operacionesReviewers,
        ])
    @endif

    @if ($showFo51PdfUploadModal)
        @include('disciplinary.forms.partials.fo-gj-51-pdf-upload-modal', [
            'prefillWorkerName' => $fo51PrefillName,
            'prefillWorkerDocument' => $fo51PrefillDocument,
            'operacionesReviewers' => $operacionesReviewers,
            'livewireClose' => true,
        ])
    @endif
</div>
