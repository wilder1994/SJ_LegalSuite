<?php

namespace App\Services\Disciplinary;

use App\Enums\Disciplinary\CaseBucket;
use App\Enums\Disciplinary\CaseStatus;
use App\Enums\Disciplinary\StageType;
use App\Models\Disciplinary\DisciplinaryCase;
use App\Models\Disciplinary\Fault;
use App\Models\User;
use App\Support\Disciplinary\WorkflowStageBuckets;
use Illuminate\Support\Facades\DB;

/**
 * Genera todas las métricas del dashboard de un solo viaje a la BD por sección,
 * usando agregaciones eficientes (GROUP BY + COUNT) sobre columnas indexadas.
 *
 * Las consultas se basan en disciplinary_cases.current_status (denormalizado e
 * indexado) para evitar joins costosos contra disciplinary_stages en tiempo real.
 *
 * La distribución por etapa de flujo (A–F) usa current_stage_type (StageType).
 */
class DisciplinaryDashboardService
{
    public function usesAssignedOnlyScope(User $actor): bool
    {
        return $actor->hasRole('nivel6') && ! $actor->hasRole('nivel1');
    }

    /**
     * @return array{
     *     assignedOnly: bool,
     *     kpis: array<string, mixed>,
     *     workflowDonuts: array<string, mixed>,
     *     byFault: list<array<string, mixed>>,
     *     caseMapPins: list<array<string, mixed>>,
     *     topMunicipalities: list<array<string, mixed>>,
     *     myWorkload: ?array<string, mixed>,
     *     lawyerWorkloadTop: list<array<string, mixed>>,
     *     actionChips: list<array{key:string, label:string, count:int|null, href:string}>,
     * }
     */
    public function build(User $actor): array
    {
        $assignedOnly = $this->usesAssignedOnlyScope($actor);
        $pins = $this->casesByMunicipalityMapPins($actor, $assignedOnly);

        $byFault = $this->casesByFault($actor, $assignedOnly);

        $lawyerRows = array_values(array_filter(
            $this->lawyerWorkload($actor),
            fn (array $row) => $row['total'] > 0
        ));

        return [
            'assignedOnly' => $assignedOnly,
            'kpis' => $this->kpis($actor, $assignedOnly),
            'workflowDonuts' => $this->workflowStageDonuts($actor, $assignedOnly),
            'byFault' => $byFault,
            'caseMapPins' => $pins,
            'topMunicipalities' => $this->topMunicipalitiesFromPins($pins, 5),
            'myWorkload' => $assignedOnly ? ($lawyerRows[0] ?? $this->emptyWorkloadRow($actor)) : null,
            'lawyerWorkloadTop' => $assignedOnly ? [] : array_slice($lawyerRows, 0, 5),
            'actionChips' => $this->actionChips($actor, $assignedOnly),
        ];
    }

    /**
     * Chips de acción del tablero → listado filtrado (taxonomía rail / alcance).
     *
     * @return list<array{key:string, label:string, count:int|null, href:string}>
     */
    public function actionChips(User $actor, bool $assignedOnly): array
    {
        $casesIndex = route('disciplinary.cases.index');
        $alerts = app(\App\Services\AlertsService::class)->summary(1, $actor);

        $closed = (int) DisciplinaryCase::query()
            ->when(true, fn ($q) => $this->applyCaseScope($q, $actor, $assignedOnly))
            ->closed()
            ->count();

        $notificationPending = $this->notificationEvidencePendingCount($actor, $assignedOnly);

        $chips = [
            [
                'key' => 'all',
                'label' => $assignedOnly ? 'Mis casos' : 'Alcance',
                'count' => null,
                'href' => $casesIndex,
            ],
            [
                'key' => 'cerrados',
                'label' => 'Cerrados',
                'count' => $closed,
                'href' => $casesIndex.'?'.http_build_query(['stage' => WorkflowStageBuckets::CLOSED_KEY]),
            ],
            [
                'key' => 'vencidos',
                'label' => 'Vencidos',
                'count' => (int) ($alerts['vencidos']['count'] ?? 0),
                'href' => $casesIndex,
            ],
            [
                'key' => 'proximos',
                'label' => 'Por vencer',
                'count' => (int) ($alerts['proximos']['count'] ?? 0),
                'href' => $casesIndex,
            ],
        ];

        if (! $assignedOnly) {
            $chips[] = [
                'key' => 'sin_asignar',
                'label' => 'Sin asignar',
                'count' => (int) ($alerts['sin_asignar']['count'] ?? 0),
                'href' => $casesIndex.'?'.http_build_query(['stage' => 'A']),
            ];
            $chips[] = [
                'key' => 'pend_decision',
                'label' => 'Pend. decisión',
                'count' => (int) ($alerts['pendientes_decision']['count'] ?? 0),
                'href' => $casesIndex.'?'.http_build_query(['stage' => 'D']),
            ];
        } else {
            $chips[] = [
                'key' => 'pool',
                'label' => 'Pool informe',
                'count' => (int) DisciplinaryCase::query()->inInformePool()->count(),
                'href' => $casesIndex.'?'.http_build_query(['stage' => 'A']),
            ];
        }

        $chips[] = [
            'key' => 'notif',
            'label' => 'Notif. pendiente',
            'count' => $notificationPending,
            'href' => $casesIndex.'?'.http_build_query(['stage' => 'B']),
        ];

        return $chips;
    }

    private function notificationEvidencePendingCount(User $actor, bool $assignedOnly): int
    {
        $base = DisciplinaryCase::query()
            ->when(true, fn ($q) => $this->applyCaseScope($q, $actor, $assignedOnly))
            ->open();

        $citation = (clone $base)
            ->whereNotNull('fo_gj_03_generated_at')
            ->whereNull('citation_evidence_uploaded_at')
            ->count();

        $decision = 0;
        if (\App\Support\Disciplinary\DecisionWorkflowSchema::isReady()) {
            $decision = (clone $base)
                ->whereNotNull('decision_comunicado_generated_at')
                ->whereNull('decision_evidence_uploaded_at')
                ->count();
        }

        return $citation + $decision;
    }

    /**
     * @param  list<array{code:string, label:string, lat:float, lon:float, count:int}>  $pins
     * @return list<array{code:string, label:string, count:int}>
     */
    public function topMunicipalitiesFromPins(array $pins, int $limit = 5): array
    {
        $sorted = $pins;
        usort($sorted, fn (array $a, array $b) => $b['count'] <=> $a['count'] ?: strcmp($a['label'], $b['label']));

        return array_map(
            fn (array $pin) => [
                'code' => $pin['code'],
                'label' => $pin['label'],
                'count' => $pin['count'],
            ],
            array_slice($sorted, 0, $limit)
        );
    }

    /**
     * @return array{lawyer_id:int, lawyer_name:string, total:int, pendientes:int, en_proceso:int, finalizados:int}
     */
    private function emptyWorkloadRow(User $actor): array
    {
        return [
            'lawyer_id' => $actor->id,
            'lawyer_name' => (string) $actor->name,
            'total' => 0,
            'pendientes' => 0,
            'en_proceso' => 0,
            'finalizados' => 0,
        ];
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<DisciplinaryCase>  $query
     */
    private function applyCaseScope($query, ?User $actor, bool $assignedOnly): void
    {
        if ($assignedOnly && $actor) {
            $query->where('disciplinary_cases.assigned_lawyer_id', $actor->id);

            return;
        }

        if ($actor) {
            $query->forDisciplinaryActor($actor);
        }
    }

    /**
     * KPIs principales en un único query agrupado.
     *
     * @return array{total:int, pendientes:int, en_proceso:int, finalizados:int, por_estado:array<string,int>}
     */
    public function kpis(?User $actor = null, bool $assignedOnly = false): array
    {
        $rows = DisciplinaryCase::query()
            ->when(true, fn ($q) => $this->applyCaseScope($q, $actor, $assignedOnly))
            ->select('current_status', DB::raw('COUNT(*) as total'))
            ->groupBy('current_status')
            ->pluck('total', 'current_status');

        $byStatus = $rows->all();
        $totals = ['pendientes' => 0, 'en_proceso' => 0, 'finalizados' => 0];

        foreach (CaseStatus::cases() as $status) {
            $count = (int) ($byStatus[$status->value] ?? 0);
            $totals[match ($status->bucket()) {
                CaseBucket::PENDIENTE => 'pendientes',
                CaseBucket::EN_PROCESO => 'en_proceso',
                CaseBucket::FINALIZADO => 'finalizados',
            }] += $count;
        }

        return [
            'total' => array_sum($totals),
            'pendientes' => $totals['pendientes'],
            'en_proceso' => $totals['en_proceso'],
            'finalizados' => $totals['finalizados'],
            'por_estado' => $byStatus,
        ];
    }

    /**
     * Métricas para las donas «Casos por etapa»: total del alcance y seis buckets
     * A–F sobre current_stage_type. B incluye citación, reprogramación y justificación;
     * C incluye comité y diligencia/acta.
     *
     * @return array{
     *     total: int,
     *     stages: list<array{
     *         letter: string,
     *         title: string,
     *         count: int,
     *         rest: int,
     *         percent: float,
     *         percent_label: string
     *     }>
     * }
     */
    public function workflowStageDonuts(?User $actor = null, bool $assignedOnly = false): array
    {
        $byStage = DisciplinaryCase::query()
            ->when(true, fn ($q) => $this->applyCaseScope($q, $actor, $assignedOnly))
            ->select('current_stage_type', DB::raw('COUNT(*) as c'))
            ->groupBy('current_stage_type')
            ->get()
            ->mapWithKeys(function ($row) {
                $raw = $row->current_stage_type;
                $key = match (true) {
                    $raw instanceof StageType => $raw->value,
                    $raw === null, $raw === '' => '',
                    default => (string) $raw,
                };

                return [$key => (int) $row->c];
            });

        $total = (int) $byStage->sum();

        $definitions = WorkflowStageBuckets::definitions();

        $stages = [];
        foreach ($definitions as $def) {
            $count = 0;
            foreach ($def['types'] as $type) {
                $count += (int) ($byStage[$type->value] ?? 0);
            }
            $rest = max(0, $total - $count);
            $percent = $total > 0 ? round(100 * $count / $total, 1) : 0.0;
            $percentLabel = $this->formatPercentLabel($percent);

            $stages[] = [
                'letter' => $def['letter'],
                'title' => $def['title'],
                'count' => $count,
                'rest' => $rest,
                'percent' => $percent,
                'percent_label' => $percentLabel,
            ];
        }

        return [
            'total' => $total,
            'stages' => $stages,
        ];
    }

    private function formatPercentLabel(float $percent): string
    {
        $s = number_format($percent, 1, '.', '');

        return rtrim(rtrim($s, '0'), '.');
    }

    /**
     * Catálogo completo de faltas activas con conteo en el alcance del actor (incluye ceros).
     *
     * @return list<array{fault_id:int, code:string, name:string, total:int, sort_order:int}>
     */
    public function casesByFault(?User $actor = null, bool $assignedOnly = false): array
    {
        $rows = Fault::query()
            ->active()
            ->select(['faults.id', 'faults.code', 'faults.name', 'faults.sort_order'])
            ->withCount([
                'disciplinaryCases as total' => function ($q) use ($actor, $assignedOnly) {
                    if ($assignedOnly && $actor) {
                        $q->where('assigned_lawyer_id', $actor->id);

                        return;
                    }
                    if ($actor) {
                        $q->forDisciplinaryActor($actor);
                    }
                },
            ])
            ->ordered()
            ->get()
            ->map(fn ($r) => [
                'fault_id' => (int) $r->id,
                'code' => $r->code,
                'name' => $r->name,
                'total' => (int) $r->total,
                'sort_order' => (int) $r->sort_order,
            ])
            ->all();

        usort($rows, function (array $a, array $b) {
            return $b['total'] <=> $a['total']
                ?: $a['sort_order'] <=> $b['sort_order']
                ?: strcmp($a['name'], $b['name']);
        });

        return $rows;
    }

    /**
     * Agregación por municipio (código DANE) con coordenadas para mapa Leaflet.
     *
     * @return list<array{code:string, label:string, lat:float, lon:float, count:int}>
     */
    public function casesByMunicipalityMapPins(?User $actor = null, bool $assignedOnly = false): array
    {
        $rows = DisciplinaryCase::query()
            ->when(true, fn ($q) => $this->applyCaseScope($q, $actor, $assignedOnly))
            ->whereNotNull('disciplinary_cases.municipality_code')
            ->join(
                'colombian_municipalities as m',
                'disciplinary_cases.municipality_code',
                '=',
                'm.municipality_code'
            )
            ->whereNotNull('m.latitude')
            ->whereNotNull('m.longitude')
            ->select(
                'disciplinary_cases.municipality_code',
                'm.municipality_name',
                'm.latitude',
                'm.longitude',
                DB::raw('COUNT(*) as total')
            )
            ->groupBy(
                'disciplinary_cases.municipality_code',
                'm.municipality_name',
                'm.latitude',
                'm.longitude'
            )
            ->get();

        return $rows->map(fn ($r) => [
            'code' => (string) $r->municipality_code,
            'label' => (string) $r->municipality_name,
            'lat' => (float) $r->latitude,
            'lon' => (float) $r->longitude,
            'count' => (int) $r->total,
        ])->values()->all();
    }

    /**
     * Distribución por ciudad.
     *
     * @return list<array{city:string, total:int}>
     */
    public function casesByCity(?User $actor = null): array
    {
        return DisciplinaryCase::query()
            ->when($actor, fn ($q) => $q->forDisciplinaryActor($actor))
            ->select('city', DB::raw('COUNT(*) as total'))
            ->whereNotNull('city')
            ->groupBy('city')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($r) => ['city' => (string) $r->city, 'total' => (int) $r->total])
            ->all();
    }

    /**
     * Resumen de carga por abogado: total / pendientes / en_proceso / finalizados.
     * Una sola consulta usando CASE/WHEN para no recorrer la tabla varias veces.
     *
     * @return list<array{lawyer_id:int, lawyer_name:string, total:int, pendientes:int, en_proceso:int, finalizados:int}>
     */
    public function lawyerWorkload(?User $actor = null): array
    {
        $pendingValues = $this->statusListByBucket(CaseBucket::PENDIENTE);
        $inProgressValues = $this->statusListByBucket(CaseBucket::EN_PROCESO);
        $finishedValues = $this->statusListByBucket(CaseBucket::FINALIZADO);

        $bind = function (array $values) {
            return implode(',', array_map(fn ($v) => "'".addslashes($v)."'", $values));
        };

        return User::query()
            ->select('users.id as lawyer_id', 'users.name as lawyer_name')
            ->selectRaw('COUNT(dc.id) as total')
            ->selectRaw("SUM(CASE WHEN dc.current_status IN ({$bind($pendingValues)}) THEN 1 ELSE 0 END) as pendientes")
            ->selectRaw("SUM(CASE WHEN dc.current_status IN ({$bind($inProgressValues)}) THEN 1 ELSE 0 END) as en_proceso")
            ->selectRaw("SUM(CASE WHEN dc.current_status IN ({$bind($finishedValues)}) THEN 1 ELSE 0 END) as finalizados")
            ->leftJoin('disciplinary_cases as dc', 'dc.assigned_lawyer_id', '=', 'users.id')
            ->whereNull('dc.deleted_at')
            ->when(
                $actor && $actor->hasRole('nivel6') && ! $actor->hasRole('nivel1'),
                fn ($q) => $q->where('users.id', $actor->id),
            )
            ->groupBy('users.id', 'users.name')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($r) => [
                'lawyer_id' => (int) $r->lawyer_id,
                'lawyer_name' => (string) $r->lawyer_name,
                'total' => (int) $r->total,
                'pendientes' => (int) $r->pendientes,
                'en_proceso' => (int) $r->en_proceso,
                'finalizados' => (int) $r->finalizados,
            ])
            ->all();
    }

    /**
     * Conteos para el rail de etapas del listado (alcance forDisciplinaryActor).
     *
     * @return array{
     *     total: int,
     *     closed: int,
     *     stages: list<array{letter: string, title: string, count: int}>
     * }
     */
    public function workflowStageRailCounts(User $actor): array
    {
        $base = DisciplinaryCase::query()->forDisciplinaryActor($actor);

        $total = (int) (clone $base)->count();
        $closed = (int) (clone $base)->closed()->count();

        $byStage = (clone $base)
            ->open()
            ->select('current_stage_type', DB::raw('COUNT(*) as c'))
            ->groupBy('current_stage_type')
            ->pluck('c', 'current_stage_type')
            ->mapWithKeys(function ($count, $raw) {
                $key = match (true) {
                    $raw instanceof StageType => $raw->value,
                    $raw === null, $raw === '' => '',
                    default => (string) $raw,
                };

                return [$key => (int) $count];
            });

        $stages = [];
        foreach (WorkflowStageBuckets::definitions() as $def) {
            $count = 0;
            foreach ($def['types'] as $type) {
                $count += (int) ($byStage[$type->value] ?? 0);
            }
            $stages[] = [
                'letter' => $def['letter'],
                'title' => $def['title'],
                'count' => $count,
            ];
        }

        return [
            'total' => $total,
            'closed' => $closed,
            'stages' => $stages,
        ];
    }

    /**
     * @return list<string>
     */
    private function statusListByBucket(CaseBucket $bucket): array
    {
        return collect(CaseStatus::cases())
            ->filter(fn (CaseStatus $s) => $s->bucket() === $bucket)
            ->map(fn (CaseStatus $s) => $s->value)
            ->values()
            ->all();
    }
}
