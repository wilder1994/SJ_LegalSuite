<?php

namespace Tests\Feature\Disciplinary;

use App\Enums\Disciplinary\CaseStatus;
use App\Enums\Disciplinary\DocumentType;
use App\Livewire\Disciplinary\Supervisor\PendingEvidenceIndex;
use App\Models\Disciplinary\DisciplinaryCase;
use App\Models\Disciplinary\DisciplinaryDocument;
use App\Models\Employee;
use App\Models\User;
use App\Support\Disciplinary\SupervisorEvidenceQueueService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\FieldDisciplinaryTestHelpers;
use Tests\TestCase;

class SupervisorEvidenceQueueTest extends TestCase
{
    use FieldDisciplinaryTestHelpers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_queue_service_merges_citation_and_decision_tasks(): void
    {
        $supervisor = $this->supervisorUser(['76001']);
        $service = app(SupervisorEvidenceQueueService::class);

        $this->seedCitationTask($supervisor);
        $this->seedDecisionTask($supervisor);

        $counts = $service->counts($supervisor);
        $this->assertSame(1, $counts['citation']);
        $this->assertSame(1, $counts['decision']);
        $this->assertSame(2, $counts['total']);

        $all = $service->tasks($supervisor);
        $this->assertCount(2, $all);
    }

    public function test_livewire_filters_citation_queue_rail(): void
    {
        $supervisor = $this->supervisorUser(['76001']);
        $citationCase = $this->seedCitationTask($supervisor);
        $this->seedDecisionTask($supervisor);

        Livewire::actingAs($supervisor)
            ->test(PendingEvidenceIndex::class)
            ->call('setQueue', SupervisorEvidenceQueueService::QUEUE_CITATION)
            ->assertSee($citationCase->case_number)
            ->assertDontSee('DEC-QUEUE-');
    }

    public function test_livewire_search_filters_by_case_number(): void
    {
        $supervisor = $this->supervisorUser(['76001']);
        $match = $this->seedCitationTask($supervisor, 'GJ-PD:SEARCH-1');
        $this->seedCitationTask($supervisor, 'GJ-PD:OTHER-9');

        Livewire::actingAs($supervisor)
            ->test(PendingEvidenceIndex::class)
            ->set('search', 'SEARCH-1')
            ->assertSee($match->case_number)
            ->assertDontSee('GJ-PD:OTHER-9');
    }

    public function test_livewire_opens_fo51_modal(): void
    {
        $supervisor = $this->supervisorUser(['76001']);

        Livewire::actingAs($supervisor)
            ->test(PendingEvidenceIndex::class)
            ->call('openFo51Modal', true)
            ->assertSet('showFo51Modal', false)
            ->assertSet('showFo51PdfUploadModal', true)
            ->assertSet('fo51OpenPdfFirst', true)
            ->assertSee('1 · Archivo del informe')
            ->assertSee('Suelte el PDF aquí')
            ->assertSee('Ampliar')
            ->assertSee('Abrir en pestaña')
            ->assertSee('PDF.js')
            ->assertSee('fo51PdfUploadFileZone', false)
            ->assertSee('4 · Faltas')
            ->assertSee('Marque una o varias faltas')
            ->assertSee('Guardar')
            ->assertSee('fo51PdfUploadFaultsPicker', false)
            ->assertSee('Otros')
            ->assertSee('saveDraft', false)
            ->assertDontSee('addPending', false)
            ->assertDontSee('Columna izquierda')
            ->assertDontSee('Capturar firma');

        Livewire::actingAs($supervisor)
            ->test(PendingEvidenceIndex::class)
            ->call('openFo51Modal', false)
            ->assertSet('showFo51Modal', true)
            ->assertSet('showFo51PdfUploadModal', false)
            ->assertSet('fo51OpenPdfFirst', false);
    }

    public function test_queue_excludes_cases_outside_supervisor_scope(): void
    {
        $this->seedMunicipality('76001', 'Cali');
        $this->seedMunicipality('05001', 'Medellín');

        $supervisor = $this->supervisorUser(['76001']);
        $service = app(SupervisorEvidenceQueueService::class);

        $this->seedCitationTask($supervisor, 'GJ-PD:IN-SCOPE', '76001');
        $this->seedCitationTask($supervisor, 'GJ-PD:OUT-SCOPE', '05001');

        $tasks = $service->tasks($supervisor);
        $this->assertCount(1, $tasks);
        $this->assertSame('GJ-PD:IN-SCOPE', $tasks->first()['case']->case_number);
    }

    public function test_citation_queue_is_shared_by_zone_and_hidden_from_other_zones(): void
    {
        $firstSupervisor = $this->supervisorUser(['76001']);
        $sameZoneSupervisor = $this->supervisorUser(['76001']);
        $otherZoneSupervisor = $this->supervisorUser(['76001']);
        $otherZone = $this->seedSupervisionZone('Zona Distinta');
        $this->assignUserToZone($otherZoneSupervisor, $otherZone);

        $case = $this->seedCitationTask($firstSupervisor, 'GJ-PD:SHARED-ZONE');
        $service = app(SupervisorEvidenceQueueService::class);

        $this->assertTrue($service->tasks($firstSupervisor)->contains(
            fn (array $task): bool => $task['case']->is($case),
        ));
        $this->assertTrue($service->tasks($sameZoneSupervisor)->contains(
            fn (array $task): bool => $task['case']->is($case),
        ));
        $this->assertFalse($service->tasks($otherZoneSupervisor)->contains(
            fn (array $task): bool => $task['case']->is($case),
        ));
    }

    /** @param list<string> $municipalityCodes */
    private function supervisorUser(array $municipalityCodes = ['76001']): User
    {
        foreach ($municipalityCodes as $code) {
            $this->seedMunicipality($code);
        }

        return $this->seedFieldUserWithCities('nivel7', $municipalityCodes);
    }

    private function seedCitationTask(User $supervisor, string $caseNumber = 'GJ-PD:CIT-QUEUE', string $municipalityCode = '76001'): DisciplinaryCase
    {
        $employee = $this->seedGuardaEmployee('9400'.random_int(100000, 999999), $municipalityCode);
        $employee->update([
            'first_name' => 'Cit',
            'last_name' => 'Queue',
        ]);

        $case = DisciplinaryCase::query()->create([
            'case_number' => $caseNumber,
            'employee_id' => $employee->id,
            'current_status' => CaseStatus::CITACION_PROGRAMADA,
            'opened_at' => now()->toDateString(),
            'notification_supervision_zone_id' => $supervisor->currentSupervisionZone()->id,
            'notification_date' => now()->addDay()->toDateString(),
            'notification_shift' => 'Mañana',
            'notification_zone' => 'Norte',
            'fo_gj_03_generated_at' => now(),
        ]);

        DisciplinaryDocument::query()->create([
            'disciplinary_case_id' => $case->id,
            'uploaded_by' => $supervisor->id,
            'document_type' => DocumentType::CITACION,
            'original_name' => 'fo-gj-03.pdf',
            'disk' => 'local',
            'path' => 'disciplinary/test/'.$caseNumber.'.pdf',
            'mime_type' => 'application/pdf',
            'notes' => DisciplinaryCase::NOTE_FO_GJ_03_GENERATED,
        ]);

        return $case;
    }

    private function seedDecisionTask(User $supervisor): DisciplinaryCase
    {
        $employee = $this->seedGuardaEmployee('9500'.random_int(100000, 999999), '76001');
        $employee->update([
            'first_name' => 'Dec',
            'last_name' => 'Queue',
        ]);

        return DisciplinaryCase::query()->create([
            'case_number' => 'DEC-QUEUE-'.random_int(100, 999),
            'employee_id' => $employee->id,
            'current_status' => CaseStatus::DECISION,
            'opened_at' => now()->toDateString(),
            'decision_notification_supervision_zone_id' => $supervisor->currentSupervisionZone()->id,
            'decision_notification_date' => now()->addDays(2)->toDateString(),
            'decision_notification_shift' => 'Tarde',
            'decision_notification_zone' => 'Centro',
            'decision_comunicado_generated_at' => now(),
        ]);
    }
}
