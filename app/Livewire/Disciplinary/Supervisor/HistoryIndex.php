<?php

namespace App\Livewire\Disciplinary\Supervisor;

use App\Support\Disciplinary\SupervisorActivityHistoryService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Historial de supervisión')]
class HistoryIndex extends Component
{
    #[Url(as: 'tipo', history: true)]
    public string $filter = SupervisorActivityHistoryService::FILTER_ALL;

    #[Url(as: 'q', history: true)]
    public string $search = '';

    public function mount(): void
    {
        abort_unless(auth()->user()?->hasRole('nivel7'), 403);

        if (! in_array($this->filter, SupervisorActivityHistoryService::allowedFilters(), true)) {
            $this->filter = SupervisorActivityHistoryService::FILTER_ALL;
        }
    }

    public function setFilter(string $filter): void
    {
        $this->filter = in_array($filter, SupervisorActivityHistoryService::allowedFilters(), true)
            ? $filter
            : SupervisorActivityHistoryService::FILTER_ALL;
    }

    public function updatedSearch(): void
    {
        $this->search = trim($this->search);
    }

    public function render(SupervisorActivityHistoryService $history)
    {
        $supervisor = auth()->user();
        $entries = $history->entries($supervisor, $this->filter, $this->search);
        $grouped = $entries->groupBy(fn (array $row) => $row['day_key']);

        return view('livewire.disciplinary.supervisor.history-index', [
            'entries' => $entries,
            'grouped' => $grouped,
            'filterOptions' => [
                SupervisorActivityHistoryService::FILTER_ALL => 'Todo',
                SupervisorActivityHistoryService::FILTER_INFORME => 'Informes',
                SupervisorActivityHistoryService::FILTER_CITACION => 'Citaciones',
                SupervisorActivityHistoryService::FILTER_DECISION => 'Decisiones',
            ],
        ]);
    }
}
