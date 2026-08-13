@php
    use App\Support\Disciplinary\SupervisorEvidenceQueueService;

    $hasQueueFilters = $search !== '' || $activeQueue !== '';
    $visibleCount = $queueTasks->count();
    $supervisionZone = auth()->user()->currentSupervisionZone();
    $citationCount = (int) ($queueCounts['citation'] ?? 0);
    $decisionCount = (int) ($queueCounts['decision'] ?? 0);
    $totalCount = (int) ($queueCounts['total'] ?? 0);
@endphp

<div class="disciplinary-supervisor-queue mx-auto flex h-[calc(100dvh-3.25rem)] max-h-[calc(100dvh-3.25rem)] w-full max-w-[1600px] flex-col overflow-hidden px-3 py-2 sm:px-5 sm:py-3 lg:px-6">
    @push('module-nav')
        <x-disciplinary.nav />
    @endpush

    {{-- Cabecera + bloque FO-GJ-51 (CRUD de informes) --}}
    <header class="mb-3 flex shrink-0 flex-col gap-3 border-b border-slate-200 pb-3 dark:border-white/10 lg:flex-row lg:items-end lg:justify-between">
        <div class="min-w-0">
            <p class="text-[10px] font-bold uppercase tracking-[0.2em] text-fuchsia-400/90">Supervisión · Campo</p>
            <h1 class="truncate text-base font-semibold text-slate-900 dark:text-white sm:text-lg">Mi trabajo</h1>
            <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">
                @if ($supervisionZone)
                    {{ $supervisionZone->name }}
                    <span class="text-slate-400 dark:text-slate-500">·</span>
                @endif
                <span class="tabular-nums font-medium text-slate-700 dark:text-slate-200">{{ number_format($totalCount) }}</span>
                {{ $totalCount === 1 ? 'tarea abierta' : 'tareas abiertas' }}
            </p>
        </div>

        @can('generateFo51Inform', \App\Models\Disciplinary\DisciplinaryCase::class)
            <div class="w-full shrink-0 rounded-xl border border-slate-200 bg-white p-3 shadow-sm ring-1 ring-slate-200 dark:border-white/10 dark:bg-white/[0.04] dark:ring-white/10 lg:w-auto lg:min-w-[20rem]">
                <p class="text-sm font-semibold text-slate-900 dark:text-slate-100">Informes disciplinarios</p>
                <p class="mt-0.5 text-[11px] text-slate-500 dark:text-slate-400">FO-GJ-51 · crear en pantalla o cargar PDF</p>
                <div class="mt-2.5 flex flex-col gap-2 sm:flex-row lg:flex-col xl:flex-row">
                    <x-ui.btn type="button" wire:click="openFo51Modal(false)" class="!h-11 w-full justify-center text-sm sm:!h-9 sm:text-xs lg:!h-11 lg:text-sm xl:!h-9 xl:text-xs">
                        Nuevo informe (FO-GJ-51)
                    </x-ui.btn>
                    <x-ui.btn type="button" variant="ghost" wire:click="openFo51Modal(true)" class="!h-11 w-full justify-center text-sm sm:!h-9 sm:text-xs lg:!h-11 lg:text-sm xl:!h-9 xl:text-xs">
                        Cargar PDF de informe
                    </x-ui.btn>
                </div>
            </div>
        @endcan
    </header>

    @if (session('success'))
        <div class="mb-2 shrink-0 rounded-lg bg-emerald-50 px-3 py-2 text-xs text-emerald-900 ring-1 ring-emerald-200 dark:bg-emerald-500/15 dark:text-emerald-100 dark:ring-emerald-500/30">
            {{ session('success') }}
        </div>
    @endif

    @if (! auth()->user()->hasFieldDisciplinaryScopeConfigured())
        <div class="mb-2 shrink-0 rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-900 ring-1 ring-amber-200 dark:bg-amber-500/15 dark:text-amber-100 dark:ring-amber-500/30">
            Su usuario no tiene ciudades autorizadas. Contacte al administrador para asignarle municipios antes de cargar evidencias o generar informes.
        </div>
    @endif

    @if (! $supervisionZone)
        <div class="mb-2 shrink-0 rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-900 ring-1 ring-amber-200 dark:bg-amber-500/15 dark:text-amber-100 dark:ring-amber-500/30">
            Su usuario no tiene zona de supervisión. Sin zona, la cola de notificaciones permanecerá vacía. Pida al administrador asignarle una zona en Usuarios.
        </div>
    @endif

    {{-- KPIs (escritorio) --}}
    <div class="mb-3 hidden shrink-0 grid-cols-3 gap-3 lg:grid">
        <div class="rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 shadow-sm ring-1 ring-slate-200 dark:border-white/10 dark:bg-white/[0.04] dark:ring-white/10">
            <p class="text-[11px] text-slate-500 dark:text-slate-400">Citaciones pendientes</p>
            <p class="mt-0.5 text-sm font-semibold tabular-nums text-slate-900 dark:text-white">{{ number_format($citationCount) }}</p>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 shadow-sm ring-1 ring-slate-200 dark:border-white/10 dark:bg-white/[0.04] dark:ring-white/10">
            <p class="text-[11px] text-slate-500 dark:text-slate-400">Decisiones pendientes</p>
            <p class="mt-0.5 text-sm font-semibold tabular-nums text-slate-900 dark:text-white">{{ number_format($decisionCount) }}</p>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 shadow-sm ring-1 ring-slate-200 dark:border-white/10 dark:bg-white/[0.04] dark:ring-white/10">
            <p class="text-[11px] text-slate-500 dark:text-slate-400">Zona asignada</p>
            <p class="mt-0.5 truncate text-sm font-semibold text-slate-900 dark:text-white">{{ $supervisionZone?->name ?? 'Sin zona' }}</p>
        </div>
    </div>

    {{-- Bandeja: título + filtros + búsqueda --}}
    <div class="mb-2 flex shrink-0 flex-col gap-2">
        <div class="flex items-center justify-between gap-2">
            <h2 class="text-sm font-semibold text-slate-900 dark:text-slate-100">Bandeja de notificaciones</h2>
            <p class="text-[11px] tabular-nums text-slate-500 dark:text-slate-400">
                {{ number_format($visibleCount) }}
                {{ $visibleCount === 1 ? 'tarea' : 'tareas' }}
            </p>
        </div>

        <nav
            class="flex shrink-0 gap-1 rounded-xl border border-slate-200 bg-white p-1 shadow-sm ring-1 ring-slate-200 dark:border-white/10 dark:bg-white/[0.04] dark:ring-white/5"
            aria-label="Filtrar bandeja de notificaciones">
            @php
                $railItems = [
                    ['key' => SupervisorEvidenceQueueService::QUEUE_CITATION, 'label' => 'Citación', 'count' => $citationCount, 'accent' => 'text-orange-500 dark:text-orange-400'],
                    ['key' => SupervisorEvidenceQueueService::QUEUE_DECISION, 'label' => 'Decisión', 'count' => $decisionCount, 'accent' => 'text-fuchsia-500 dark:text-fuchsia-400'],
                    ['key' => '', 'label' => 'Todos', 'count' => $totalCount, 'accent' => 'text-slate-600 dark:text-slate-300'],
                ];
            @endphp
            @foreach ($railItems as $item)
                @php $isActive = $activeQueue === $item['key']; @endphp
                <button type="button" wire:click="setQueue('{{ $item['key'] }}')" title="{{ $item['label'] }}"
                    @class([
                        'flex min-h-11 flex-1 flex-col items-center justify-center gap-0.5 rounded-lg px-2 py-1.5 text-xs font-semibold tabular-nums transition sm:min-h-9 sm:flex-row sm:gap-1.5',
                        'bg-slate-100 ring-1 ring-slate-300 dark:bg-white/10 dark:ring-white/20' => $isActive,
                        'text-slate-600 hover:bg-slate-50 dark:text-slate-300 dark:hover:bg-white/[0.06]' => ! $isActive,
                    ])>
                    <span class="{{ $item['accent'] }}">{{ $item['label'] }}</span>
                    <span class="text-slate-700 dark:text-slate-200">{{ number_format($item['count']) }}</span>
                </button>
            @endforeach
        </nav>

        <div>
            <label for="supervisor-queue-search" class="sr-only">Buscar</label>
            <input id="supervisor-queue-search" type="search" wire:model.live.debounce.350ms="search"
                placeholder="N° de caso, nombre o documento…" autocomplete="off"
                class="w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-white/15 dark:bg-dash-lift dark:text-white lg:max-w-md">
            @if ($search !== '')
                <p class="mt-1 text-[11px] text-slate-500 dark:text-slate-400">
                    <span class="font-semibold tabular-nums text-slate-700 dark:text-slate-200">{{ number_format($visibleCount) }}</span>
                    {{ $visibleCount === 1 ? 'resultado' : 'resultados' }}
                </p>
            @endif
        </div>
    </div>

    {{-- Lista inbox (móvil: tarjetas · escritorio: filas) — sin tabla HTML --}}
    <div class="flex min-h-0 flex-1 flex-col overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm ring-1 ring-slate-200 dark:border-white/10 dark:bg-white/[0.04] dark:ring-white/10">
        <div class="hidden shrink-0 grid-cols-[minmax(0,1.4fr)_minmax(0,1.2fr)_10.5rem] gap-4 border-b border-slate-200 bg-slate-50 px-4 py-2.5 text-[11px] font-semibold uppercase tracking-wide text-slate-500 dark:border-white/10 dark:bg-dash-ink/80 dark:text-dash-muted lg:grid">
            <span>Caso · Trabajador</span>
            <span>Notificación · Documento</span>
            <span>Acciones</span>
        </div>

        <div class="min-h-0 flex-1 overflow-auto">
            @forelse ($queueTasks as $row)
                @php
                    $task = $row['case'];
                    $isCitation = $row['queue_type'] === SupervisorEvidenceQueueService::QUEUE_CITATION;
                    $typeBadgeClass = $isCitation
                        ? 'bg-orange-50 text-orange-800 ring-orange-200 dark:bg-orange-500/15 dark:text-orange-200 dark:ring-orange-500/30'
                        : 'bg-fuchsia-50 text-fuchsia-800 ring-fuchsia-200 dark:bg-fuchsia-500/15 dark:text-fuchsia-200 dark:ring-fuchsia-500/30';
                    $generatedLabel = $row['generated_at']?->timezone('America/Bogota')->format('d/m/Y H:i') ?? '—';
                    $workerName = trim(($task->employee?->first_name ?? '').' '.($task->employee?->last_name ?? ''));
                @endphp

                <article
                    wire:key="supervisor-queue-{{ $row['queue_type'] }}-{{ $task->id }}"
                    class="border-b border-slate-200 p-3.5 last:border-b-0 dark:border-white/10 lg:grid lg:grid-cols-[minmax(0,1.4fr)_minmax(0,1.2fr)_10.5rem] lg:items-center lg:gap-4 lg:px-4 lg:py-3.5 lg:hover:bg-slate-50 dark:lg:hover:bg-white/[0.04]">
                    {{-- Identidad --}}
                    <div class="min-w-0 space-y-1.5">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="inline-flex rounded-full px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide ring-1 ring-inset {{ $typeBadgeClass }}">
                                {{ $isCitation ? 'Citación' : 'Decisión' }}
                            </span>
                            <span class="font-mono text-xs font-semibold text-slate-700 dark:text-slate-300">{{ $task->case_number }}</span>
                            <span class="ml-auto text-[11px] tabular-nums text-slate-500 dark:text-slate-400 lg:hidden">{{ $generatedLabel }}</span>
                        </div>
                        <p class="font-semibold text-slate-900 dark:text-slate-100">
                            {{ $workerName !== '' ? $workerName : 'Trabajador sin nombre' }}
                        </p>
                        @if ($task->employee?->document_number)
                            <p class="text-[11px] text-slate-500 dark:text-slate-400">CC {{ $task->employee->document_number }}</p>
                        @endif
                    </div>

                    {{-- Meta notificación
                    <div class="mt-3 rounded-lg bg-slate-50 px-3 py-2.5 dark:bg-white/[0.04] lg:mt-0 lg:rounded-none lg:bg-transparent lg:p-0 dark:lg:bg-transparent">
                        <p class="text-[11px] text-slate-500 dark:text-slate-400 lg:mb-0.5">Notificación</p>
                        <p class="text-xs font-medium text-slate-800 dark:text-slate-200">{{ $row['notification_summary'] }}</p>
                        <p class="mt-1 text-[11px] text-slate-500 dark:text-slate-400">
                            <span class="font-semibold text-slate-700 dark:text-slate-300">{{ $row['document_label'] }}</span>
                            <span class="hidden lg:inline"> · Generado {{ $generatedLabel }}</span>
                        </p>
                    </div>

                    {{-- Acciones --}}
                    <div class="mt-3 space-y-2 lg:mt-0">
                        <input type="file"
                            id="evidence-file-{{ $task->id }}"
                            class="sr-only"
                            accept="application/pdf"
                            wire:model.live="citationEvidenceFileByCase.{{ $task->id }}">
                        <label for="evidence-file-{{ $task->id }}"
                            class="sj-btn {{ $isCitation ? 'sj-btn--teal' : 'sj-btn--primary' }} !inline-flex !h-11 w-full cursor-pointer items-center justify-center !px-3 !text-sm lg:!h-9 lg:!text-xs">
                            Cargar PDF
                        </label>

                        @if ($isCitation)
                            @can('viewFoGj03NotificationForSupervisor', $task)
                                <x-ui.btn type="button" variant="ghost" wire:click="openNotificationModal({{ $task->id }})" class="!h-11 w-full justify-center !text-sm lg:!h-9 lg:!text-xs">
                                    Ver notificación
                                </x-ui.btn>
                            @endcan
                        @else
                            @can('viewDecisionComunicadoForSupervisor', $task)
                                <x-ui.btn type="button" variant="ghost" wire:click="openDecisionNotificationModal({{ $task->id }})" class="!h-11 w-full justify-center !text-sm lg:!h-9 lg:!text-xs">
                                    Ver notificación
                                </x-ui.btn>
                            @endcan
                        @endif

                        @error('citationEvidenceFileByCase.'.$task->id)
                            <p class="text-[11px] text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>
                </article>
            @empty
                <div class="flex min-h-[16rem] items-center justify-center px-4 py-10 text-center">
                    <div class="mx-auto max-w-md space-y-2.5">
                        <p class="text-sm font-semibold text-slate-800 dark:text-slate-100">
                            @if ($search !== '')
                                Sin resultados para su búsqueda
                            @elseif ($activeQueue === SupervisorEvidenceQueueService::QUEUE_CITATION)
                                No hay citaciones pendientes
                            @elseif ($activeQueue === SupervisorEvidenceQueueService::QUEUE_DECISION)
                                No hay decisiones pendientes
                            @else
                                No hay notificaciones pendientes
                            @endif
                        </p>
                        <p class="text-xs leading-relaxed text-slate-500 dark:text-slate-400">
                            @if ($search !== '')
                                Pruebe con otro número de caso, nombre o documento.
                            @elseif ($activeQueue === SupervisorEvidenceQueueService::QUEUE_CITATION)
                                Cuando planeación le asigne una citación, aparecerá aquí para cargar el PDF o registrar la firma.
                            @elseif ($activeQueue === SupervisorEvidenceQueueService::QUEUE_DECISION)
                                Cuando planeación le asigne un comunicado de decisión, aparecerá aquí para cargar el PDF o registrar la firma.
                            @else
                                Cuando planeación le asigne una notificación, aparecerá aquí para cargar el PDF o registrar la firma en pantalla. También puede crear un informe FO-GJ-51 desde el bloque de arriba.
                            @endif
                        </p>
                        @if ($search !== '' || $activeQueue !== '')
                            <div>
                                <x-ui.btn type="button" variant="ghost" wire:click="clearQueueFilters" class="!h-9 text-xs">
                                    Ver todas las tareas
                                </x-ui.btn>
                            </div>
                        @elseif (Gate::allows('generateFo51Inform', \App\Models\Disciplinary\DisciplinaryCase::class))
                            <div>
                                <x-ui.btn type="button" wire:click="openFo51Modal(false)" class="!h-9 text-xs">
                                    Nuevo informe FO-GJ-51
                                </x-ui.btn>
                            </div>
                        @endif
                    </div>
                </div>
            @endforelse
        </div>

        @if ($visibleCount > 0)
            <div class="flex shrink-0 flex-wrap items-center justify-between gap-2 border-t border-slate-200 px-3 py-2 text-xs text-slate-500 dark:border-white/10 dark:text-dash-muted">
                <p>
                    Mostrando
                    <span class="font-semibold tabular-nums text-slate-700 dark:text-slate-200">{{ number_format($visibleCount) }}</span>
                    {{ $visibleCount === 1 ? 'tarea' : 'tareas' }}
                    @if ($hasQueueFilters)
                        en esta vista
                    @endif
                </p>
            </div>
        @endif
    </div>

    @include('livewire.disciplinary.supervisor.partials.pending-evidence-modals', [
        'evidencePreviewCaseId' => $evidencePreviewCaseId,
        'evidencePreviewUrl' => $evidencePreviewUrl,
        'notificationCaseId' => $notificationCaseId,
        'notificationCase' => $notificationCase,
        'notificationViewData' => $notificationViewData,
        'decisionNotificationCaseId' => $decisionNotificationCaseId,
        'decisionNotificationCase' => $decisionNotificationCase,
        'decisionNotificationViewData' => $decisionNotificationViewData,
        'signedNotificationPreviewToken' => $signedNotificationPreviewToken,
        'signedNotificationPreviewUrl' => $signedNotificationPreviewUrl,
        'signedNotificationDownloadUrl' => $signedNotificationDownloadUrl,
        'signedNotificationPreviewFilename' => $signedNotificationPreviewFilename,
        'notificationEvidenceType' => $notificationEvidenceType,
        'workerSignatureDataUri' => $workerSignatureDataUri,
        'witness1SignatureDataUri' => $witness1SignatureDataUri,
        'witness2SignatureDataUri' => $witness2SignatureDataUri,
        'signaturePadTarget' => $signaturePadTarget,
        'showSignaturePadModal' => $showSignaturePadModal,
    ])

    @if ($showFo51Modal)
        @include('disciplinary.forms.partials.fo-gj-51-informe-modal-shell', [
            'prefillWorkerName' => $fo51PrefillName,
            'prefillWorkerDocument' => $fo51PrefillDocument,
            'openPdfUploadModal' => false,
            'operacionesReviewers' => $operacionesReviewers ?? collect(),
        ])
    @endif

    @if ($showFo51PdfUploadModal)
        @include('disciplinary.forms.partials.fo-gj-51-pdf-upload-modal', [
            'prefillWorkerName' => $fo51PrefillName,
            'prefillWorkerDocument' => $fo51PrefillDocument,
            'operacionesReviewers' => $operacionesReviewers ?? collect(),
            'livewireClose' => true,
        ])
    @endif
</div>
