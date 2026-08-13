<?php

namespace App\Support\Disciplinary;

use App\Enums\Disciplinary\InformeSubmissionStatus;
use App\Models\Disciplinary\DisciplinaryCase;
use App\Models\Disciplinary\DisciplinaryDocument;
use App\Models\Disciplinary\InformeSubmission;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Bitácora textual del supervisor (nivel7): solo metadatos de sus propias cargas.
 * No expone rutas de archivo, números de expediente ni enlaces al caso.
 */
final class SupervisorActivityHistoryService
{
    public const FILTER_ALL = '';

    public const FILTER_INFORME = 'informe';

    public const FILTER_CITACION = 'citacion';

    public const FILTER_DECISION = 'decision';

    /**
     * @return list<string>
     */
    public static function allowedFilters(): array
    {
        return [
            self::FILTER_ALL,
            self::FILTER_INFORME,
            self::FILTER_CITACION,
            self::FILTER_DECISION,
        ];
    }

    /**
     * @return Collection<int, array{
     *     key: string,
     *     kind: string,
     *     kind_label: string,
     *     title: string,
     *     status_label: string,
     *     detail: string|null,
     *     employee_name: string,
     *     employee_document: string,
     *     occurred_at: Carbon,
     *     day_key: string,
     * }>
     */
    public function entries(
        User $supervisor,
        string $filter = self::FILTER_ALL,
        string $search = '',
        int $limit = 100,
    ): Collection {
        $filter = in_array($filter, self::allowedFilters(), true) ? $filter : self::FILTER_ALL;
        $search = trim($search);
        $limit = max(1, min($limit, 200));

        $rows = collect();

        if ($filter === self::FILTER_ALL || $filter === self::FILTER_INFORME) {
            $rows = $rows->merge($this->informeEntries($supervisor, $search));
        }

        if ($filter === self::FILTER_ALL || $filter === self::FILTER_CITACION) {
            $rows = $rows->merge($this->evidenceEntries(
                $supervisor,
                $search,
                DisciplinaryCase::NOTE_CITATION_EVIDENCE_PREFIX,
                self::FILTER_CITACION,
                'Citación',
                'Evidencia de citación cargada',
            ));
        }

        if ($filter === self::FILTER_ALL || $filter === self::FILTER_DECISION) {
            $rows = $rows->merge($this->evidenceEntries(
                $supervisor,
                $search,
                DisciplinaryCase::NOTE_DECISION_EVIDENCE_PREFIX,
                self::FILTER_DECISION,
                'Decisión',
                'Evidencia de decisión cargada',
            ));
        }

        return $rows
            ->sortByDesc(fn (array $row) => $row['occurred_at']->getTimestamp())
            ->take($limit)
            ->values();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function informeEntries(User $supervisor, string $search): Collection
    {
        return InformeSubmission::query()
            ->where('submitted_by', $supervisor->id)
            ->with(['employee:id,first_name,last_name,document_number'])
            ->when($search !== '', function ($query) use ($search) {
                $query->whereHas('employee', function ($employee) use ($search) {
                    $employee->where(function ($inner) use ($search) {
                        $inner->where('document_number', 'like', '%'.$search.'%')
                            ->orWhere('first_name', 'like', '%'.$search.'%')
                            ->orWhere('last_name', 'like', '%'.$search.'%');
                    });
                });
            })
            ->orderByDesc('created_at')
            ->limit(200)
            ->get()
            ->map(function (InformeSubmission $submission) {
                $employee = $submission->employee;
                $status = $submission->status instanceof InformeSubmissionStatus
                    ? $submission->status
                    : InformeSubmissionStatus::PENDIENTE_REVISION;
                $occurredAt = $submission->created_at instanceof Carbon
                    ? $submission->created_at->copy()
                    : Carbon::parse($submission->created_at);

                return $this->mapEntry(
                    key: 'informe-'.$submission->id,
                    kind: self::FILTER_INFORME,
                    kindLabel: 'Informe',
                    title: 'Informe FO-GJ-51 enviado a revisión',
                    statusLabel: $status->label(),
                    detail: filled($submission->summary)
                        ? Str::limit(trim((string) $submission->summary), 160)
                        : null,
                    employeeName: $this->employeeDisplayName($employee?->first_name, $employee?->last_name),
                    employeeDocument: (string) ($employee?->document_number ?? ''),
                    occurredAt: $occurredAt,
                );
            });
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function evidenceEntries(
        User $supervisor,
        string $search,
        string $notePrefix,
        string $kind,
        string $kindLabel,
        string $title,
    ): Collection {
        return DisciplinaryDocument::query()
            ->where('uploaded_by', $supervisor->id)
            ->where('notes', 'like', $notePrefix.'%')
            ->with(['case.employee:id,first_name,last_name,document_number'])
            ->when($search !== '', function ($query) use ($search) {
                $query->whereHas('case.employee', function ($employee) use ($search) {
                    $employee->where(function ($inner) use ($search) {
                        $inner->where('document_number', 'like', '%'.$search.'%')
                            ->orWhere('first_name', 'like', '%'.$search.'%')
                            ->orWhere('last_name', 'like', '%'.$search.'%');
                    });
                });
            })
            ->orderByDesc('created_at')
            ->limit(200)
            ->get()
            ->map(function (DisciplinaryDocument $document) use ($kind, $kindLabel, $title, $notePrefix) {
                $employee = $document->case?->employee;
                $occurredAt = $document->created_at instanceof Carbon
                    ? $document->created_at->copy()
                    : Carbon::parse($document->created_at);
                $modality = $this->modalityFromNotes((string) ($document->notes ?? ''), $notePrefix);

                return $this->mapEntry(
                    key: $kind.'-doc-'.$document->id,
                    kind: $kind,
                    kindLabel: $kindLabel,
                    title: $title,
                    statusLabel: 'Cargada',
                    detail: $modality,
                    employeeName: $this->employeeDisplayName($employee?->first_name, $employee?->last_name),
                    employeeDocument: (string) ($employee?->document_number ?? ''),
                    occurredAt: $occurredAt,
                );
            });
    }

    /**
     * @return array{
     *     key: string,
     *     kind: string,
     *     kind_label: string,
     *     title: string,
     *     status_label: string,
     *     detail: string|null,
     *     employee_name: string,
     *     employee_document: string,
     *     occurred_at: Carbon,
     *     day_key: string,
     * }
     */
    private function mapEntry(
        string $key,
        string $kind,
        string $kindLabel,
        string $title,
        string $statusLabel,
        ?string $detail,
        string $employeeName,
        string $employeeDocument,
        Carbon $occurredAt,
    ): array {
        return [
            'key' => $key,
            'kind' => $kind,
            'kind_label' => $kindLabel,
            'title' => $title,
            'status_label' => $statusLabel,
            'detail' => $detail,
            'employee_name' => $employeeName,
            'employee_document' => $employeeDocument,
            'occurred_at' => $occurredAt,
            'day_key' => $occurredAt->toDateString(),
        ];
    }

    private function employeeDisplayName(?string $first, ?string $last): string
    {
        $name = trim(trim((string) $first).' '.trim((string) $last));

        return $name !== '' ? $name : 'Trabajador sin nombre';
    }

    private function modalityFromNotes(string $notes, string $prefix): ?string
    {
        $trimmed = trim($notes);
        if ($trimmed === '' || ! str_starts_with($trimmed, $prefix)) {
            return null;
        }

        $rest = trim(Str::after($trimmed, $prefix));
        $rest = ltrim($rest, "-–— \t");

        return $rest !== '' ? $rest : null;
    }
}
