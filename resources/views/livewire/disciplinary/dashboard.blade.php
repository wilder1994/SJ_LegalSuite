@php
    $chartDark = ($uiTheme ?? 'light') === 'dark';
    $stagePalette = [
        'A' => ['from' => '#818cf8', 'to' => '#4338ca', 'shadow' => '#818cf8', 'letter' => 'text-indigo-400'],
        'B' => ['from' => '#fb923c', 'to' => '#9a3412', 'shadow' => '#fb923c', 'letter' => 'text-orange-400'],
        'C' => ['from' => '#22d3ee', 'to' => '#155e75', 'shadow' => '#22d3ee', 'letter' => 'text-cyan-400'],
        'D' => ['from' => '#e879f9', 'to' => '#86198f', 'shadow' => '#e879f9', 'letter' => 'text-fuchsia-400'],
        'E' => ['from' => '#f472b6', 'to' => '#9f1239', 'shadow' => '#f472b6', 'letter' => 'text-pink-400'],
        'F' => ['from' => '#34d399', 'to' => '#166534', 'shadow' => '#34d399', 'letter' => 'text-emerald-400'],
    ];
    $chartConfig = [
        'chartDark' => $chartDark,
        'workflow' => $workflowDonuts,
        'stagePalette' => collect($stagePalette)->map(fn ($p) => [
            'from' => $p['from'],
            'to' => $p['to'],
            'shadow' => $p['shadow'],
        ])->all(),
    ];
    $faultBarMax = max(1, (int) collect($byFault)->max('total'));
    $faultCasesTotal = (int) collect($byFault)->sum('total');
@endphp

{{-- Header compacto + chips · donas chicas · mapa|(Top+Faltas) · Mi carga. Scroll del main si no cabe. --}}
<div class="disciplinary-dashboard mx-auto flex w-full max-w-[1600px] flex-col gap-2 px-3 py-2 pb-4 sm:px-5 sm:py-3 lg:px-6"
    x-data="disciplinaryDashboard(@js($chartConfig))"
    x-init="init()"
    @destroy.window="destroy()">

    @push('module-nav')
        <x-disciplinary.nav />
    @endpush

    <header class="flex shrink-0 flex-col gap-1.5 border-b border-slate-200 pb-2 dark:border-white/10">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <p class="text-[10px] font-bold uppercase tracking-[0.2em] text-fuchsia-400/90">
                Disciplinarios · {{ $assignedOnly ? 'Mi tablero' : 'Dashboard' }}
            </p>
            <x-dashboard.button href="{{ route('disciplinary.cases.index') }}" variant="ghost" class="!h-8 shrink-0 !px-2.5 !text-xs">
                {{ $assignedOnly ? 'Ver mis casos →' : 'Ver listado →' }}
            </x-dashboard.button>
        </div>

        @if (! empty($actionChips))
            <div class="flex flex-wrap gap-1.5" role="navigation" aria-label="Accesos rápidos del tablero">
                @foreach ($actionChips as $chip)
                    <a
                        href="{{ $chip['href'] }}"
                        wire:navigate
                        class="inline-flex items-center gap-1 rounded-md border border-slate-200 bg-white px-2 py-0.5 text-[11px] font-medium text-slate-700 transition hover:bg-slate-50 dark:border-white/10 dark:bg-white/[0.04] dark:text-slate-200 dark:hover:bg-white/[0.08]"
                    >
                        <span>{{ $chip['label'] }}</span>
                        @if ($chip['count'] !== null)
                            <span class="tabular-nums text-slate-500 dark:text-slate-400">{{ number_format((int) $chip['count']) }}</span>
                        @endif
                    </a>
                @endforeach
            </div>
        @endif
    </header>

    <section
        class="shrink-0 overflow-visible rounded-xl border border-slate-200 bg-white px-2 pt-1 pb-0 shadow-sm ring-1 ring-slate-200 dark:border-white/10 dark:bg-white/[0.04] dark:ring-white/5 sm:px-3"
        aria-label="Distribución de casos por etapa del flujo"
        wire:ignore
        x-ref="workflowDonuts"
    >
        {{-- xl (no lg): con sidebar el main suele ser <7×~96px a 1024–1280; 7 cols ahí aplastaba y desalinea donas. --}}
        <div class="grid w-full min-w-0 grid-cols-2 content-start items-start gap-1.5 sm:grid-cols-4 xl:grid-cols-7 xl:gap-1">
            <div class="flex min-w-0 flex-col items-center">
                <p class="mb-0.5 w-full shrink-0 px-0.5 text-center leading-snug" title="Total de casos en su alcance">
                    <span class="block text-[10px] font-bold uppercase tracking-wide text-amber-600 dark:text-amber-300">Total</span>
                    <span class="mt-0.5 block text-[9px] font-medium text-slate-500 dark:text-dash-muted">{{ $assignedOnly ? 'Asignados' : 'Alcance' }}</span>
                </p>
                <div data-workflow-donut="total" data-apex-chart-root class="mx-auto h-[100px] w-full min-w-0 max-w-full overflow-hidden xl:h-[112px]"></div>
            </div>

            @foreach ($workflowDonuts['stages'] as $st)
                @php $pal = $stagePalette[$st['letter']]; @endphp
                <div class="flex min-w-0 flex-col items-center" wire:key="workflow-donut-{{ $st['letter'] }}">
                    <p class="mb-0.5 w-full shrink-0 px-0.5 text-center leading-snug" title="{{ $st['title'] }} (etapa {{ $st['letter'] }})">
                        <span class="block text-[10px] font-bold tabular-nums {{ $pal['letter'] }}">{{ $st['letter'] }}</span>
                        <span class="mt-0.5 line-clamp-2 block text-[9px] font-medium text-slate-600 dark:text-slate-400">{{ $st['title'] }}</span>
                    </p>
                    <div data-workflow-donut="{{ $st['letter'] }}" data-apex-chart-root class="mx-auto h-[100px] w-full min-w-0 max-w-full overflow-hidden xl:h-[112px]"></div>
                </div>
            @endforeach
        </div>
    </section>

    <div class="grid min-h-[320px] grid-cols-1 items-stretch gap-2 lg:min-h-[380px] lg:grid-cols-12 lg:gap-3">
        <section class="flex min-h-[320px] flex-col rounded-xl border border-slate-200 bg-white p-3 shadow-sm ring-1 ring-slate-200 dark:border-white/10 dark:bg-white/[0.04] dark:ring-white/5 lg:col-span-7 lg:min-h-[380px]">
            <div class="mb-1.5 flex shrink-0 items-center justify-between gap-2">
                <div>
                    <h2 class="text-[10px] font-bold uppercase tracking-[0.14em] text-dash-muted">Casos por ciudad</h2>
                    <p class="mt-0.5 text-[10px] text-slate-500 dark:text-dash-muted">Mapa Colombia · pins DIVIPOLA</p>
                </div>
                <span class="rounded-md bg-white/5 px-2 py-0.5 text-[10px] font-bold tabular-nums text-cyan-300 ring-1 ring-cyan-400/20">
                    {{ number_format(count($caseMapPins)) }} municipio(s)
                </span>
            </div>
            <div wire:ignore class="relative min-h-0 flex-1">
                <div id="disciplinary-colombia-map"
                     class="absolute inset-0 z-0 h-full w-full rounded-xl border border-slate-200/80 bg-slate-950/40 ring-1 ring-cyan-500/15 dark:border-white/10 dark:bg-black/30 dark:ring-fuchsia-500/20"
                     data-pins='@json($caseMapPins)'
                     data-chart-dark="{{ $chartDark ? '1' : '0' }}"
                     data-geo-dept="{{ route('disciplinary.map-geo', ['file' => 'gadm41_COL_1.json'], absolute: false) }}"
                     data-geo-mun="{{ route('disciplinary.map-geo', ['file' => 'gadm41_COL_2.json'], absolute: false) }}"
                     role="presentation"
                     aria-label="Mapa de casos por municipio en Colombia">
                </div>
                @if (count($caseMapPins) === 0)
                    <div class="pointer-events-none absolute inset-0 flex items-center justify-center rounded-xl bg-slate-950/45 px-4 text-center dark:bg-black/40">
                        <p class="max-w-xs text-[11px] leading-relaxed text-slate-200">
                            {{ $assignedOnly
                                ? 'Sin pins aún · el mapa se llena con municipios DIVIPOLA de sus casos.'
                                : 'Sin pins aún · el mapa se llena con municipios DIVIPOLA del alcance.' }}
                        </p>
                    </div>
                @endif
            </div>
        </section>

        <aside class="grid min-h-[320px] grid-rows-2 gap-2 lg:col-span-5 lg:min-h-[380px]">
            <section class="flex min-h-0 flex-col overflow-hidden rounded-xl border border-slate-200 bg-white p-3 shadow-sm ring-1 ring-slate-200 dark:border-white/10 dark:bg-white/[0.04] dark:ring-white/5">
                <h2 class="mb-1.5 shrink-0 text-[10px] font-bold uppercase tracking-[0.14em] text-dash-muted">Top municipios</h2>
                <div class="min-h-0 flex-1 overflow-y-auto">
                    @if ($topMunicipalities === [])
                        <p class="flex h-full items-center justify-center text-center text-[11px] text-slate-500 dark:text-dash-muted">
                            Sin municipios con casos.
                        </p>
                    @else
                        <ul class="space-y-0.5">
                            @foreach ($topMunicipalities as $index => $mun)
                                <li>
                                    <button type="button"
                                        x-on:click="focusMunicipality(@js($mun['code']))"
                                        x-bind:class="highlightedMunicipality === @js($mun['code']) ? 'border-cyan-400/50 bg-cyan-500/10 text-cyan-200' : 'border-transparent text-slate-500 hover:border-white/10 hover:bg-white/5 hover:text-slate-200'"
                                        class="flex w-full items-center gap-2 rounded-md border px-2 py-1.5 text-left transition">
                                        <span class="w-4 shrink-0 text-[10px] font-bold tabular-nums text-fuchsia-400/90">{{ $index + 1 }}</span>
                                        <span class="min-w-0 flex-1 truncate text-[11px] font-medium">{{ $mun['label'] }}</span>
                                        <span class="shrink-0 text-[10px] tabular-nums text-slate-500">{{ number_format($mun['count']) }}</span>
                                    </button>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </section>

            <section class="flex min-h-0 flex-col overflow-hidden rounded-xl border border-slate-200 bg-white p-3 shadow-sm ring-1 ring-slate-200 dark:border-white/10 dark:bg-white/[0.04] dark:ring-white/5">
                <div class="mb-1.5 flex shrink-0 items-center justify-between gap-2">
                    <h2 class="text-[10px] font-bold uppercase tracking-[0.14em] text-dash-muted">Casos por tipo de falta</h2>
                    <span class="text-[10px] tabular-nums text-orange-400/90">{{ number_format($faultCasesTotal) }}</span>
                </div>
                <div class="min-h-0 flex-1 overflow-y-auto pr-0.5">
                    @if ($byFault === [])
                        <p class="flex h-full items-center justify-center text-center text-[11px] text-slate-500 dark:text-dash-muted">Sin catálogo de faltas.</p>
                    @else
                        <ul class="space-y-1">
                            @foreach ($byFault as $fault)
                                @php
                                    $total = (int) $fault['total'];
                                    $barPct = $total > 0 ? max(6, (int) round(100 * $total / $faultBarMax)) : 0;
                                @endphp
                                <li class="flex items-center gap-2" title="{{ $fault['name'] }}">
                                    <span class="w-[38%] shrink-0 truncate text-[10px] leading-tight text-slate-600 dark:text-slate-300">{{ $fault['name'] }}</span>
                                    <div class="h-2 min-w-0 flex-1 overflow-hidden rounded-full bg-white/10 ring-1 ring-white/5">
                                        <span
                                            class="block h-full rounded-full transition-all {{ $total > 0 ? 'bg-gradient-to-r from-orange-400 to-orange-700' : 'bg-slate-600/25' }}"
                                            style="width: {{ $barPct }}%"
                                        ></span>
                                    </div>
                                    <span class="w-5 shrink-0 text-right text-[10px] font-semibold tabular-nums {{ $total > 0 ? 'text-orange-300' : 'text-slate-500' }}">{{ $total }}</span>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </section>
        </aside>
    </div>

    @if ($assignedOnly && $myWorkload)
        <section class="shrink-0 rounded-xl border border-slate-200 bg-white p-3 shadow-sm ring-1 ring-slate-200 dark:border-white/10 dark:bg-white/[0.04] dark:ring-white/5">
            <h2 class="mb-2 text-[10px] font-bold uppercase tracking-[0.14em] text-dash-muted">Mi carga</h2>
            <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
                <div class="rounded-lg bg-slate-50 px-2.5 py-2 ring-1 ring-slate-200/80 dark:bg-white/[0.03] dark:ring-white/5">
                    <p class="text-[9px] font-bold uppercase tracking-wide text-dash-muted">Total asignados</p>
                    <p class="text-lg font-bold tabular-nums text-slate-800 dark:text-white">{{ number_format($myWorkload['total']) }}</p>
                </div>
                <div class="rounded-lg bg-slate-50 px-2.5 py-2 ring-1 ring-slate-200/80 dark:bg-white/[0.03] dark:ring-white/5">
                    <p class="text-[9px] font-bold uppercase tracking-wide text-dash-muted">Pendientes</p>
                    <p class="text-lg font-bold tabular-nums text-amber-500 dark:text-amber-400">{{ number_format($myWorkload['pendientes']) }}</p>
                </div>
                <div class="rounded-lg bg-slate-50 px-2.5 py-2 ring-1 ring-slate-200/80 dark:bg-white/[0.03] dark:ring-white/5">
                    <p class="text-[9px] font-bold uppercase tracking-wide text-dash-muted">En proceso</p>
                    <p class="text-lg font-bold tabular-nums text-cyan-600 dark:text-cyan-400">{{ number_format($myWorkload['en_proceso']) }}</p>
                </div>
                <div class="rounded-lg bg-slate-50 px-2.5 py-2 ring-1 ring-slate-200/80 dark:bg-white/[0.03] dark:ring-white/5">
                    <p class="text-[9px] font-bold uppercase tracking-wide text-dash-muted">Finalizados</p>
                    <p class="text-lg font-bold tabular-nums text-emerald-600 dark:text-emerald-400">{{ number_format($myWorkload['finalizados']) }}</p>
                </div>
            </div>
        </section>
    @elseif (! $assignedOnly)
        <section class="shrink-0 rounded-xl border border-slate-200 bg-white p-3 shadow-sm ring-1 ring-slate-200 dark:border-white/10 dark:bg-white/[0.04] dark:ring-white/5">
            <h2 class="mb-2 text-[10px] font-bold uppercase tracking-[0.14em] text-dash-muted">Carga por abogado</h2>
            @if ($lawyerWorkloadTop !== [])
                <ul class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($lawyerWorkloadTop as $row)
                        @php $max = max(1, (int) $row['total']); @endphp
                        <li class="rounded-lg bg-slate-50 px-2.5 py-2 ring-1 ring-slate-200/80 dark:bg-white/[0.03] dark:ring-white/5">
                            <div class="mb-0.5 flex items-center justify-between gap-2 text-[11px]">
                                <span class="truncate font-medium text-slate-700 dark:text-slate-200">{{ $row['lawyer_name'] }}</span>
                                <span class="shrink-0 tabular-nums font-semibold text-fuchsia-600 dark:text-fuchsia-300">{{ $row['total'] }}</span>
                            </div>
                            <div class="flex h-1.5 overflow-hidden rounded-full bg-slate-200 dark:bg-white/10">
                                <span class="bg-amber-400/80" style="width: {{ round(100 * $row['pendientes'] / $max) }}%"></span>
                                <span class="bg-cyan-400/80" style="width: {{ round(100 * $row['en_proceso'] / $max) }}%"></span>
                                <span class="bg-emerald-400/80" style="width: {{ round(100 * $row['finalizados'] / $max) }}%"></span>
                            </div>
                            <p class="mt-0.5 text-[9px] tabular-nums text-slate-500">
                                {{ $row['pendientes'] }} pend · {{ $row['en_proceso'] }} proc · {{ $row['finalizados'] }} fin
                            </p>
                        </li>
                    @endforeach
                </ul>
            @else
                <p class="py-3 text-center text-[11px] text-slate-500 dark:text-dash-muted">Sin datos de carga.</p>
            @endif
        </section>
    @endif
</div>
