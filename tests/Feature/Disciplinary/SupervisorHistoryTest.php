<?php

namespace Tests\Feature\Disciplinary;

use App\Enums\Disciplinary\CaseStatus;
use App\Enums\Disciplinary\DocumentType;
use App\Enums\Disciplinary\InformeSubmissionStatus;
use App\Livewire\Disciplinary\Supervisor\HistoryIndex;
use App\Models\Disciplinary\DisciplinaryCase;
use App\Models\Disciplinary\DisciplinaryDocument;
use App\Models\Disciplinary\InformeSubmission;
use App\Models\User;
use App\Support\Disciplinary\SupervisorActivityHistoryService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\FieldDisciplinaryTestHelpers;
use Tests\TestCase;

class SupervisorHistoryTest extends TestCase
{
    use FieldDisciplinaryTestHelpers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_supervisor_sees_own_text_only_history(): void
    {
        $supervisor = $this->supervisorUser(['76001']);
        $other = $this->supervisorUser(['76001']);

        $ownEmployee = $this->seedGuardaEmployee('1001001001', '76001');
        $ownEmployee->update(['first_name' => 'Ana', 'last_name' => 'Propia']);

        InformeSubmission::query()->create([
            'submitted_by' => $supervisor->id,
            'employee_id' => $ownEmployee->id,
            'status' => InformeSubmissionStatus::PENDIENTE_REVISION,
            'storage_disk' => 'local',
            'storage_path' => 'disciplinary/informes-pendientes/test/own.pdf',
            'original_filename' => 'own.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 1024,
            'checksum_sha256' => hash('sha256', 'own'),
            'summary' => 'Resumen propio visible',
        ]);

        $foreignEmployee = $this->seedGuardaEmployee('2002002002', '76001');
        $foreignEmployee->update(['first_name' => 'Bruno', 'last_name' => 'Ajeno']);

        InformeSubmission::query()->create([
            'submitted_by' => $other->id,
            'employee_id' => $foreignEmployee->id,
            'status' => InformeSubmissionStatus::PENDIENTE_REVISION,
            'storage_disk' => 'local',
            'storage_path' => 'disciplinary/informes-pendientes/test/other.pdf',
            'original_filename' => 'other.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 1024,
            'checksum_sha256' => hash('sha256', 'other'),
            'summary' => 'No debe verse',
        ]);

        $caseNumber = 'GJ-HIST-SECRET-99';
        $caseEmployee = $this->seedGuardaEmployee('3003003003', '76001');
        $caseEmployee->update(['first_name' => 'Carla', 'last_name' => 'Citada']);

        $case = DisciplinaryCase::query()->create([
            'case_number' => $caseNumber,
            'employee_id' => $caseEmployee->id,
            'current_status' => CaseStatus::CITACION_PROGRAMADA,
            'opened_at' => now()->toDateString(),
            'notification_supervision_zone_id' => $supervisor->currentSupervisionZone()->id,
            'citation_evidence_uploaded_at' => now(),
        ]);

        DisciplinaryDocument::query()->create([
            'disciplinary_case_id' => $case->id,
            'uploaded_by' => $supervisor->id,
            'document_type' => DocumentType::CITACION,
            'original_name' => 'evidencia-secreta.pdf',
            'disk' => 'local',
            'path' => 'disciplinary/test/evidencia-secreta.pdf',
            'mime_type' => 'application/pdf',
            'notes' => DisciplinaryCase::NOTE_CITATION_EVIDENCE_PREFIX.' - Citación firmada por el trabajador',
        ]);

        Livewire::actingAs($supervisor)
            ->test(HistoryIndex::class)
            ->assertSee('Mi historial')
            ->assertSee('Ana Propia')
            ->assertSee('CC 1001001001')
            ->assertSee('Informe FO-GJ-51 enviado a revisión')
            ->assertSee('Resumen propio visible')
            ->assertSee('Carla Citada')
            ->assertSee('Evidencia de citación cargada')
            ->assertSee('Citación firmada por el trabajador')
            ->assertDontSee('Bruno Ajeno')
            ->assertDontSee('No debe verse')
            ->assertDontSee($caseNumber)
            ->assertDontSee('evidencia-secreta.pdf')
            ->assertDontSee('disciplinary/informes-pendientes')
            ->assertDontSee('disciplinary/test/');
    }

    public function test_history_filters_by_kind_and_search(): void
    {
        $supervisor = $this->supervisorUser(['76001']);
        $service = app(SupervisorActivityHistoryService::class);

        $informeEmployee = $this->seedGuardaEmployee('4114114114', '76001');
        $informeEmployee->update(['first_name' => 'Diego', 'last_name' => 'Informe']);

        InformeSubmission::query()->create([
            'submitted_by' => $supervisor->id,
            'employee_id' => $informeEmployee->id,
            'status' => InformeSubmissionStatus::AUTORIZADO,
            'storage_disk' => 'local',
            'storage_path' => 'disciplinary/informes-pendientes/test/filtro.pdf',
            'original_filename' => 'filtro.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 512,
            'checksum_sha256' => hash('sha256', 'filtro'),
        ]);

        $decisionEmployee = $this->seedGuardaEmployee('5225225225', '76001');
        $decisionEmployee->update(['first_name' => 'Elena', 'last_name' => 'Decision']);

        $decisionCase = DisciplinaryCase::query()->create([
            'case_number' => 'GJ-HIST-DEC-1',
            'employee_id' => $decisionEmployee->id,
            'current_status' => CaseStatus::DECISION,
            'opened_at' => now()->toDateString(),
            'decision_evidence_uploaded_at' => now(),
        ]);

        DisciplinaryDocument::query()->create([
            'disciplinary_case_id' => $decisionCase->id,
            'uploaded_by' => $supervisor->id,
            'document_type' => DocumentType::DECISION,
            'original_name' => 'dec.pdf',
            'disk' => 'local',
            'path' => 'disciplinary/test/dec.pdf',
            'mime_type' => 'application/pdf',
            'notes' => DisciplinaryCase::NOTE_DECISION_EVIDENCE_PREFIX.' - Rechazo de firma con dos testigos',
        ]);

        $informes = $service->entries($supervisor, SupervisorActivityHistoryService::FILTER_INFORME);
        $this->assertCount(1, $informes);
        $this->assertSame(SupervisorActivityHistoryService::FILTER_INFORME, $informes->first()['kind']);
        $this->assertArrayNotHasKey('case_number', $informes->first());
        $this->assertArrayNotHasKey('path', $informes->first());

        $search = $service->entries($supervisor, SupervisorActivityHistoryService::FILTER_ALL, 'Elena');
        $this->assertCount(1, $search);
        $this->assertSame(SupervisorActivityHistoryService::FILTER_DECISION, $search->first()['kind']);

        Livewire::actingAs($supervisor)
            ->test(HistoryIndex::class)
            ->call('setFilter', SupervisorActivityHistoryService::FILTER_DECISION)
            ->assertSee('Elena Decision')
            ->assertDontSee('Diego Informe')
            ->set('search', '411411')
            ->assertSee('Sin resultados');
    }

    public function test_non_supervisor_cannot_open_history(): void
    {
        $lawyer = User::factory()->create([
            'email' => 'lawyer-hist@test.local',
            'email_verified_at' => now(),
            'must_change_password' => false,
            'is_active' => true,
        ]);
        $lawyer->assignRole('nivel6');

        $this->actingAs($lawyer)
            ->get(route('disciplinary.historial.index'))
            ->assertForbidden();
    }

    /** @param list<string> $municipalityCodes */
    private function supervisorUser(array $municipalityCodes = ['76001']): User
    {
        foreach ($municipalityCodes as $code) {
            $this->seedMunicipality($code);
        }

        return $this->seedFieldUserWithCities('nivel7', $municipalityCodes);
    }
}
