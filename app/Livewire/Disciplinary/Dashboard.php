<?php

namespace App\Livewire\Disciplinary;

use App\Models\Disciplinary\DisciplinaryCase;
use App\Services\Disciplinary\DisciplinaryDashboardService;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Dashboard disciplinario')]
class Dashboard extends Component
{
    public function mount(): void
    {
        if (Gate::allows('viewDashboard', DisciplinaryCase::class)) {
            return;
        }

        if (Gate::allows('viewAny', DisciplinaryCase::class)) {
            $this->redirect(route('disciplinary.cases.index'), navigate: true);

            return;
        }

        // Roles con portal propio (supervisor, planeación) que llegan aquí
        // —p. ej. por una URL «intended» tras iniciar sesión— van a su portal
        // en lugar de un 403 abrupto.
        $user = auth()->user();

        if ($user->hasDisciplinaryPortalAccess()) {
            $this->redirect($user->disciplinaryPortalUrl(), navigate: true);

            return;
        }

        abort(403);
    }

    public function render()
    {
        $dashboard = app(DisciplinaryDashboardService::class);
        $actor = auth()->user();
        $data = $dashboard->build($actor);

        return view('livewire.disciplinary.dashboard', [
            'assignedOnly' => $data['assignedOnly'],
            'kpis' => $data['kpis'],
            'workflowDonuts' => $data['workflowDonuts'],
            'byFault' => $data['byFault'],
            'caseMapPins' => $data['caseMapPins'],
            'topMunicipalities' => $data['topMunicipalities'],
            'myWorkload' => $data['myWorkload'],
            'lawyerWorkloadTop' => $data['lawyerWorkloadTop'],
            'actionChips' => $data['actionChips'],
        ]);
    }
}
