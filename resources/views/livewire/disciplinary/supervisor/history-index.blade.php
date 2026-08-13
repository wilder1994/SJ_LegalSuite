@php
    use App\Support\Disciplinary\SupervisorActivityHistoryService;
    use Illuminate\Support\Carbon;

    $supervisionZone = auth()->user()->currentSupervisionZone();
    $total = $entries->count();
    $hasFilters = $search !== '' || $filter !== SupervisorActivityHistoryService::FILTER_ALL;
@endphp

<div class="disciplinary-supervisor-history mx-auto flex h-[calc(100dvh-3.25rem)] max-h-[calc(100dvh-3.25rem)] w-full max-w-[960px] flex-col overflow-hidden px-3 py-2 sm:px-5 sm:py-3 lg:px-6">
    @push('module-nav')
        <x-disciplinary.nav />
    @endpush

    <header class="mb-3 shrink-0 border-b border-slate-200 pb-3 dark:border-white/10">
        <p class="text-[10px] font-bold uppercase tracking-[0.2em] text-fuchsia-400/90">Supervisión · Campo</p>
        <h1 class="mt-0.5 text-base font-semibold text-slate-900 dark:text-white sm:text-lg">Mi historial</h1>
        <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">
            @if ($supervisionZone)
                {{ $supervisionZone->name }}
                <span class="text-slate-400 dark:text-slate-500">·</span>
            @endif
            Registro textual de informes y notificaciones que usted cargó.
            <span class="font-medium text-slate-600 dark:text-slate-300">Sin acceso a expedientes ni archivos.</span>
        </p>
    </header>

    <div class="mb-3 flex shrink-0 flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex flex-wrap gap-1.5" role="tablist" aria-label="Filtrar historial">
            @foreach ($filterOptions as $value => $label)
                <button
                    type="button"
                    wire:click="setFilter(@js($value))"
                    role="tab"
                    aria-selected="{{ $filter === $value ? 'true' : 'false' }}"
                    class="rounded-lg px-2.5 py-1.5 text-xs font-medium transition
                        {{ $filter === $value
                            ? 'bg-slate-900 text-white dark:bg-white dark:text-slate-900'
                            : 'bg-slate-100 text-slate-600 hover:bg-slate-200 dark:bg-white/10 dark:text-slate-300 dark:hover:bg-white/15' }}"
                >
                    {{ $label }}
                </button>
            @endforeach
        </div>

        <div class="relative w-full sm:max-w-xs">
            <label for="supervisor-history-search" class="sr-only">Buscar por trabajador</label>
            <input
                id="supervisor-history-search"
                type="search"
                wire:model.live.debounce.300ms="search"
                placeholder="Buscar por nombre o cédula…"
                class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 placeholder:text-slate-400 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/30 dark:border-white/10 dark:bg-white/[0.04] dark:text-slate-100 dark:placeholder:text-slate-500"
            />
        </div>
    </div>

    <div class="min-h-0 flex-1 overflow-y-auto pb-6">
        @if ($total === 0)
            <div class="rounded-xl border border-dashed border-slate-300 bg-white/60 px-4 py-10 text-center dark:border-white/15 dark:bg-white/[0.03]">
                <p class="text-sm font-medium text-slate-800 dark:text-slate-100">
                    {{ $hasFilters ? 'Sin resultados con esos filtros' : 'Aún no hay actividad en su historial' }}
                </p>
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                    @if ($hasFilters)
                        Pruebe otra búsqueda o limpie el filtro de tipo.
                    @else
                        Cuando envíe un informe FO-GJ-51 o cargue una evidencia de notificación, aparecerá aquí en texto.
                    @endif
                </p>
                @if (! $hasFilters)
                    <a href="{{ route('disciplinary.evidences-pending.index') }}" wire:navigate
                       class="mt-4 inline-flex text-xs font-semibold text-indigo-600 hover:text-indigo-500 dark:text-cyan-300 dark:hover:text-cyan-200">
                        Ir a Mi trabajo
                    </a>
                @endif
            </div>
        @else
            <p class="mb-3 text-[11px] tabular-nums text-slate-500 dark:text-slate-400">
                {{ number_format($total) }} {{ $total === 1 ? 'registro' : 'registros' }}
            </p>

            <ol class="space-y-6">
                @foreach ($grouped as $dayKey => $dayEntries)
                    @php
                        $day = Carbon::parse($dayKey)->locale('es');
                        $dayLabel = $day->isToday()
                            ? 'Hoy'
                            : ($day->isYesterday() ? 'Ayer' : $day->isoFormat('D [de] MMMM YYYY'));
                    @endphp
                    <li>
                        <h2 class="sticky top-0 z-10 mb-2 bg-slate-50/95 py-1 text-[11px] font-bold uppercase tracking-wider text-slate-500 backdrop-blur dark:bg-dash-void/95 dark:text-slate-400">
                            {{ $dayLabel }}
                        </h2>
                        <ul class="relative space-y-0 border-l border-slate-200 pl-4 dark:border-white/10">
                            @foreach ($dayEntries as $entry)
                                <li wire:key="{{ $entry['key'] }}" class="relative pb-4 last:pb-0">
                                    <span class="absolute -left-[1.28rem] top-1.5 h-2.5 w-2.5 rounded-full ring-4 ring-slate-50 dark:ring-dash-void
                                        {{ match ($entry['kind']) {
                                            SupervisorActivityHistoryService::FILTER_INFORME => 'bg-fuchsia-500',
                                            SupervisorActivityHistoryService::FILTER_CITACION => 'bg-amber-500',
                                            SupervisorActivityHistoryService::FILTER_DECISION => 'bg-emerald-500',
                                            default => 'bg-slate-400',
                                        } }}"
                                        aria-hidden="true"></span>

                                    <article class="rounded-lg border border-slate-200/80 bg-white px-3 py-2.5 dark:border-white/10 dark:bg-white/[0.04]">
                                        <div class="flex flex-wrap items-start justify-between gap-2">
                                            <div class="min-w-0">
                                                <div class="flex flex-wrap items-center gap-1.5">
                                                    <span class="rounded px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide
                                                        {{ match ($entry['kind']) {
                                                            SupervisorActivityHistoryService::FILTER_INFORME => 'bg-fuchsia-50 text-fuchsia-800 dark:bg-fuchsia-500/15 dark:text-fuchsia-200',
                                                            SupervisorActivityHistoryService::FILTER_CITACION => 'bg-amber-50 text-amber-900 dark:bg-amber-500/15 dark:text-amber-100',
                                                            SupervisorActivityHistoryService::FILTER_DECISION => 'bg-emerald-50 text-emerald-900 dark:bg-emerald-500/15 dark:text-emerald-100',
                                                            default => 'bg-slate-100 text-slate-700 dark:bg-white/10 dark:text-slate-200',
                                                        } }}">
                                                        {{ $entry['kind_label'] }}
                                                    </span>
                                                    <span class="text-[10px] font-medium text-slate-500 dark:text-slate-400">
                                                        {{ $entry['status_label'] }}
                                                    </span>
                                                </div>
                                                <p class="mt-1 text-sm font-medium text-slate-900 dark:text-slate-100">
                                                    {{ $entry['title'] }}
                                                </p>
                                                <p class="mt-0.5 text-xs text-slate-600 dark:text-slate-300">
                                                    {{ $entry['employee_name'] }}
                                                    @if ($entry['employee_document'] !== '')
                                                        <span class="text-slate-400 dark:text-slate-500">·</span>
                                                        CC {{ $entry['employee_document'] }}
                                                    @endif
                                                </p>
                                                @if (filled($entry['detail']))
                                                    <p class="mt-1 text-[11px] leading-snug text-slate-500 dark:text-slate-400">
                                                        {{ $entry['detail'] }}
                                                    </p>
                                                @endif
                                            </div>
                                            <time
                                                class="shrink-0 tabular-nums text-[11px] text-slate-400 dark:text-slate-500"
                                                datetime="{{ $entry['occurred_at']->toIso8601String() }}"
                                            >
                                                {{ $entry['occurred_at']->format('H:i') }}
                                            </time>
                                        </div>
                                    </article>
                                </li>
                            @endforeach
                        </ul>
                    </li>
                @endforeach
            </ol>
        @endif
    </div>
</div>
