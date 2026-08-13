<?php

namespace App\Livewire\Disciplinary\Cases;

use App\Enums\Disciplinary\CaseStatus;
use App\Exceptions\Disciplinary\CaseAlreadyClaimedException;
use App\Models\Disciplinary\DisciplinaryCase;
use App\Models\Disciplinary\Fault;
use App\Models\User;
use App\Services\Disciplinary\DisciplinaryCaseService;
use App\Services\Disciplinary\DisciplinaryDashboardService;
use App\Support\Disciplinary\WorkflowStageBuckets;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Disciplinarios')]
class CasesIndex extends Component
{
    use WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    /** Etapa A–F, «cerrados» o vacío (todos). */
    #[Url]
    public string $stage = '';

    #[Url]
    public string $status = '';

    #[Url(as: 'lawyer')]
    public string $lawyerId = '';

    #[Url]
    public string $city = '';

    #[Url(as: 'fault')]
    public string $faultId = '';

    #[Url]
    public string $from = '';

    #[Url]
    public string $to = '';

    public int $perPage = 15;

    public bool $showAdvancedFilters = false;

    /** Abre el modal FO-GJ-51 digitado en esta misma pantalla. */
    public bool $showFo51Modal = false;

    /** Modal standalone «Cargar informe en PDF». */
    public bool $showFo51PdfUploadModal = false;

    /** Si true, el flujo abrió primero la carga PDF (validación / deep-link). */
    public bool $fo51OpenPdfFirst = false;

    public ?string $fo51PrefillName = null;

    public ?string $fo51PrefillDocument = null;

    /** Confirmación «Gestionar» para casos en bandeja INFORME (pool). */
    public bool $showClaimConfirm = false;

    public ?int $claimCaseId = null;

    public string $claimCaseNumber = '';

    public function mount(): void
    {
        $user = auth()->user();

        if (! $user->can('viewAny', DisciplinaryCase::class) || $user->hasRole('nivel3')) {
            if ($user->hasDisciplinaryPortalAccess()) {
                $this->redirect($user->disciplinaryPortalUrl(), navigate: true);

                return;
            }

            abort(403);
        }

        $this->stage = WorkflowStageBuckets::normalizeFilterKey($this->stage);
        if (! WorkflowStageBuckets::isValidFilterKey($this->stage)) {
            $this->stage = '';
        }

        // Operaciones: no listan cerrados; el filtro «cerrados» no aplica.
        if ($user->isDisciplinaryOperacionesReviewer()
            && $this->stage === WorkflowStageBuckets::CLOSED_KEY) {
            $this->stage = '';
        }

        if (request()->boolean('informe_modal') || request()->boolean('cargar_pdf')) {
            Gate::authorize('generateFo51Inform', DisciplinaryCase::class);

            $n = request()->string('nombre')->trim()->toString();
            $c = request()->string('cedula')->trim()->toString();
            $this->fo51PrefillName = $n !== '' ? $n : null;
            $this->fo51PrefillDocument = $c !== '' ? $c : null;

            if (request()->boolean('cargar_pdf')) {
                $this->showFo51PdfUploadModal = true;
                $this->fo51OpenPdfFirst = true;
            } else {
                $this->showFo51Modal = true;
            }
        }
    }

    public function openFo51Modal(bool $openPdfFirst = false): void
    {
        Gate::authorize('generateFo51Inform', DisciplinaryCase::class);
        $this->fo51PrefillName = null;
        $this->fo51PrefillDocument = null;
        $this->fo51OpenPdfFirst = $openPdfFirst;

        if ($openPdfFirst) {
            $this->showFo51Modal = false;
            $this->showFo51PdfUploadModal = true;

            return;
        }

        $this->showFo51PdfUploadModal = false;
        $this->showFo51Modal = true;
    }

    public function closeFo51Modal(): void
    {
        $this->showFo51Modal = false;
        $this->fo51OpenPdfFirst = false;
    }

    public function closeFo51PdfUploadModal(): void
    {
        $this->showFo51PdfUploadModal = false;
        $this->fo51OpenPdfFirst = false;
    }

    public function updating($prop): void
    {
        if (in_array($prop, ['search', 'stage', 'status', 'lawyerId', 'city', 'faultId', 'from', 'to'], true)) {
            $this->resetPage();
        }
    }

    public function setStage(string $stage): void
    {
        $normalized = WorkflowStageBuckets::normalizeFilterKey($stage);
        if (! WorkflowStageBuckets::isValidFilterKey($normalized)) {
            return;
        }

        if (auth()->user()->isDisciplinaryOperacionesReviewer()
            && $normalized === WorkflowStageBuckets::CLOSED_KEY) {
            return;
        }

        $this->stage = $this->stage === $normalized ? '' : $normalized;
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->reset(['search', 'stage', 'status', 'lawyerId', 'city', 'faultId', 'from', 'to']);
        $this->showAdvancedFilters = false;
        $this->resetPage();
    }

    public function toggleAdvancedFilters(): void
    {
        $this->showAdvancedFilters = ! $this->showAdvancedFilters;
    }

    public function openClaimConfirm(int $caseId): void
    {
        $case = DisciplinaryCase::query()->findOrFail($caseId);
        Gate::authorize('claim', $case);
        $this->claimCaseId = $case->id;
        $this->claimCaseNumber = $case->case_number;
        $this->showClaimConfirm = true;
    }

    public function cancelClaimConfirm(): void
    {
        $this->showClaimConfirm = false;
        $this->claimCaseId = null;
        $this->claimCaseNumber = '';
    }

    public function confirmClaimCase(DisciplinaryCaseService $cases): void
    {
        if (! $this->showClaimConfirm || $this->claimCaseId === null) {
            return;
        }

        $case = DisciplinaryCase::query()->findOrFail($this->claimCaseId);
        Gate::authorize('claim', $case);

        try {
            $cases->claimByLawyer($case, auth()->user());
        } catch (CaseAlreadyClaimedException) {
            $this->cancelClaimConfirm();
            session()->flash('error', 'Otro abogado ya tomó este expediente. Actualice el listado.');

            return;
        }

        $caseId = $case->id;
        $this->cancelClaimConfirm();
        session()->flash('success', 'Expediente asignado. Ya puede gestionarlo con normalidad.');
        $this->redirectRoute('disciplinary.cases.show', ['case' => $caseId], navigate: true);
    }

    #[Computed]
    public function stageRail(): array
    {
        return app(DisciplinaryDashboardService::class)->workflowStageRailCounts(auth()->user());
    }

    #[Computed]
    public function lawyers()
    {
        return User::query()->role('nivel6')->orderBy('name')->get(['id', 'name']);
    }

    #[Computed]
    public function faults()
    {
        return Fault::active()->ordered()->get(['id', 'name']);
    }

    #[Computed]
    public function hasSecondaryFilters(): bool
    {
        return $this->status !== ''
            || $this->lawyerId !== ''
            || $this->city !== ''
            || $this->faultId !== ''
            || ($this->from !== '' && $this->to !== '');
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    #[Computed]
    public function cities(): array
    {
        $actor = auth()->user();
        $opts = [];

        $coded = DisciplinaryCase::query()
            ->forDisciplinaryActor($actor)
            ->whereNotNull('disciplinary_cases.municipality_code')
            ->join(
                'colombian_municipalities as m',
                'disciplinary_cases.municipality_code',
                '=',
                'm.municipality_code'
            )
            ->select('disciplinary_cases.municipality_code', 'm.municipality_name', 'm.department_name')
            ->distinct()
            ->orderBy('m.municipality_name')
            ->get();

        foreach ($coded as $row) {
            $opts[] = [
                'value' => (string) $row->municipality_code,
                'label' => (string) $row->municipality_name.' · '.(string) $row->department_name,
            ];
        }

        $legacyCities = DisciplinaryCase::query()
            ->forDisciplinaryActor($actor)
            ->whereNull('municipality_code')
            ->whereNotNull('city')
            ->distinct()
            ->orderBy('city')
            ->pluck('city');

        foreach ($legacyCities as $c) {
            $opts[] = ['value' => (string) $c, 'label' => (string) $c.' (texto libre)'];
        }

        return $opts;
    }

    private function casesQuery()
    {
        return DisciplinaryCase::query()
            ->forDisciplinaryActor(auth()->user())
            ->with(['employee:id,first_name,last_name,document_number', 'assignedLawyer:id,name'])
            ->withCount('faults')
            ->when($this->search !== '', fn ($q) => $q->search($this->search))
            ->when($this->stage === WorkflowStageBuckets::CLOSED_KEY, fn ($q) => $q->closed())
            ->when(
                $this->stage !== '' && $this->stage !== WorkflowStageBuckets::CLOSED_KEY,
                fn ($q) => $q->open()->workflowStageLetter($this->stage)
            )
            ->when($this->status !== '', fn ($q) => $q->withStatus(CaseStatus::from($this->status)))
            ->when($this->lawyerId !== '', fn ($q) => $q->assignedTo((int) $this->lawyerId))
            ->when($this->city !== '', fn ($q) => $q->inCity($this->city))
            ->when($this->faultId !== '', fn ($q) => $q->withFault((int) $this->faultId))
            ->when($this->from !== '' && $this->to !== '', fn ($q) => $q->openedBetween($this->from, $this->to))
            ->orderByDesc('opened_at');
    }

    public function render()
    {
        $cases = $this->casesQuery()->paginate($this->perPage);

        return view('livewire.disciplinary.cases.index', [
            'cases' => $cases,
            'statuses' => CaseStatus::cases(),
            'stageColors' => WorkflowStageBuckets::letterColorClasses(),
            'operacionesReviewers' => User::query()
                ->role('nivel2')
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name']),
        ]);
    }
}
