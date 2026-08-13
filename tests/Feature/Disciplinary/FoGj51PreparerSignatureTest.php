<?php

namespace Tests\Feature\Disciplinary;

use App\Models\Employee;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\FieldDisciplinaryTestHelpers;
use Tests\TestCase;

class FoGj51PreparerSignatureTest extends TestCase
{
    use FieldDisciplinaryTestHelpers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function makeEmployee(): Employee
    {
        return $this->seedGuardaEmployee('9500'.random_int(100000, 999999), '76001');
    }

    public function test_pdf_action_requires_captured_preparer_signature(): void
    {
        $supervisor = $this->makeSupervisor();
        $employee = $this->makeEmployee();
        $this->seedMunicipality('76001', 'Cali');

        $response = $this->actingAs($supervisor)->post(route('disciplinary.forms.informe.process'), [
            'fo51_action' => 'pdf',
            'fo51_worker_name' => $employee->full_name,
            'fo51_worker_document' => $employee->document_number,
            'fo51_employee_id' => $employee->id,
            'fo51_municipality_code' => '76001',
            'fo51_observations' => 'Hechos de prueba.',
        ]);

        $response->assertSessionHasErrors('fo51_preparer_signature');
    }

    public function test_pdf_action_rejects_text_instead_of_signature_image(): void
    {
        $supervisor = $this->makeSupervisor();
        $employee = $this->makeEmployee();
        $this->seedMunicipality('76001', 'Cali');

        $response = $this->actingAs($supervisor)->post(route('disciplinary.forms.informe.process'), [
            'fo51_action' => 'pdf',
            'fo51_worker_name' => $employee->full_name,
            'fo51_worker_document' => $employee->document_number,
            'fo51_employee_id' => $employee->id,
            'fo51_municipality_code' => '76001',
            'fo51_observations' => 'Hechos de prueba.',
            'fo51_preparer_signature' => 'firma escrita a mano',
        ]);

        $response->assertSessionHasErrors('fo51_preparer_signature');
    }

    public function test_supervisor_validation_errors_redirect_to_full_page_form_not_cases_index(): void
    {
        $supervisor = $this->makeSupervisor();

        $response = $this->actingAs($supervisor)->post(route('disciplinary.forms.informe.process'), [
            'fo51_action' => 'enviar',
        ]);

        $response->assertRedirect(route('disciplinary.evidences-pending.index', ['informe_modal' => 1]));
        $response->assertSessionHasErrors();
    }

    public function test_cargar_validation_errors_redirect_supervisor_to_pdf_upload_modal(): void
    {
        $supervisor = $this->makeSupervisor();

        $response = $this->actingAs($supervisor)->post(route('disciplinary.forms.informe.process'), [
            'fo51_action' => 'cargar',
        ]);

        $response->assertRedirect(route('disciplinary.evidences-pending.index', [
            'cargar_pdf' => 1,
            'informe_modal' => 1,
        ]));
        $response->assertSessionHasErrors();
    }

    public function test_cargar_with_evidence_stores_pending_submission(): void
    {
        $supervisor = $this->makeSupervisor();
        $employee = $this->makeEmployee();
        $this->seedMunicipality('76001', 'Cali');

        $reviewer = User::factory()->create([
            'email' => 'ops-cargar-'.random_int(1000, 9999).'@test.local',
            'email_verified_at' => now(),
            'must_change_password' => false,
            'is_active' => true,
        ]);
        $reviewer->assignRole('nivel2');

        $pdf = \Illuminate\Http\UploadedFile::fake()->create('informe.pdf', 120, 'application/pdf');
        $evidence = \Illuminate\Http\UploadedFile::fake()->image('prueba.jpg', 80, 80);

        $response = $this->actingAs($supervisor)->post(route('disciplinary.forms.informe.process'), [
            'fo51_action' => 'cargar',
            'fo51_assigned_reviewer_id' => $reviewer->id,
            'fo51_employee_id' => $employee->id,
            'informe_worker_name' => $employee->full_name,
            'informe_worker_document' => $employee->document_number,
            'fo51_municipality_code' => '76001',
            'fo51_report_dd' => '13',
            'fo51_report_mm' => '08',
            'fo51_report_yyyy' => '2026',
            'fo51_shift' => 'Mañana',
            'fo51_position' => 'Puesto 1',
            'fo51_fault_left' => ['Retardo al Servicio'],
            'fo51_fault_right' => ['Incumplimiento de consignas'],
            'fo51_fault_other_chk' => '1',
            'fo51_fault_other_detail' => 'Falta adicional de prueba',
            'informe_file' => $pdf,
            'evidence_images' => [$evidence],
        ]);

        $response->assertRedirect(route('disciplinary.evidences-pending.index'));
        $response->assertSessionHas('success');

        $submission = \App\Models\Disciplinary\InformeSubmission::query()->latest('id')->first();
        $this->assertNotNull($submission);
        $this->assertSame($employee->id, $submission->employee_id);
        $this->assertSame($reviewer->id, $submission->assigned_reviewer_id);
        $this->assertIsArray($submission->evidence_paths);
        $this->assertCount(1, $submission->evidence_paths);
        $this->assertSame('76001', data_get($submission->form_snapshot, 'fo51_municipality_code'));
        $this->assertSame(['Retardo al Servicio'], data_get($submission->form_snapshot, 'fo51_fault_left'));
        $this->assertSame(['Incumplimiento de consignas'], data_get($submission->form_snapshot, 'fo51_fault_right'));
        $this->assertTrue((bool) data_get($submission->form_snapshot, 'fo51_fault_other_chk'));
        $this->assertSame('Falta adicional de prueba', data_get($submission->form_snapshot, 'fo51_fault_other_detail'));
    }

    public function test_filled_pdf_view_renders_preparer_signature_image(): void
    {
        $signature = $this->sampleSignatureDataUri();

        $html = view('disciplinary.forms.fo-gj-51-filled-download', [
            'embeddedLogoSrc' => 'data:image/png;base64,AA==',
            'workerName' => 'Trabajador Prueba',
            'workerDocument' => '1234567890',
            'workerCargo' => 'Operario',
            'city' => 'Cali',
            'shift' => 'Mañana',
            'position' => 'Puesto 1',
            'faultOtherDetail' => '',
            'observations' => 'Observaciones.',
            'preparerName' => 'Supervisor campo',
            'preparerRole' => 'nivel7',
            'preparerSignature' => $signature,
            'reportDay' => '16',
            'reportMonth' => '06',
            'reportYear' => '2026',
            'faultLeftChecked' => [],
            'faultRightChecked' => [],
            'faultOtherChecked' => false,
            'jurPd' => '',
            'entregaGh' => '',
            'jurDd' => '',
            'jurMm' => '',
            'jurYyyy' => '',
        ])->render();

        $this->assertStringContainsString('class="fo51-signature-img"', $html);
        $this->assertStringContainsString($signature, $html);
    }

    public function test_informe_form_includes_signature_capture_alpine_component(): void
    {
        $supervisor = $this->makeSupervisor();

        $response = $this->actingAs($supervisor)->get(route('disciplinary.forms.informe-fo-gj-51', [
            'vista_completa' => 1,
        ]));

        $response->assertOk();
        $response->assertSee('class="fo51-interactive"', false);
        $response->assertSee('class="ogj-letter-screen-sheet"', false);
        $response->assertSee('fo51-block-personal', false);
        $response->assertSee('fo51-personal-inner', false);
        $response->assertSee('fo51-inline-lbl', false);
        $response->assertSee('sjFo51PreparerSignature', false);
        $response->assertSee('Capturar firma', false);
        $response->assertSee('Agregar evidencias (opcional)', false);
        $response->assertSee('form="fo51-informe-form"', false);
        $response->assertDontSee('x-model="evidenceModalOpen"', false);
        $response->assertDontSee('name="fo51_preparer_signature" class="fo51-in"', false);
    }

    public function test_filled_pdf_view_does_not_include_mobile_interactive_layout(): void
    {
        $signature = $this->sampleSignatureDataUri();

        $html = view('disciplinary.forms.fo-gj-51-filled-download', [
            'embeddedLogoSrc' => 'data:image/png;base64,AA==',
            'workerName' => 'Trabajador Prueba',
            'workerDocument' => '1234567890',
            'workerCargo' => 'Operario',
            'city' => 'Cali',
            'shift' => 'Mañana',
            'position' => 'Puesto 1',
            'faultOtherDetail' => '',
            'observations' => 'Observaciones.',
            'preparerName' => 'Supervisor campo',
            'preparerRole' => 'nivel7',
            'preparerSignature' => $signature,
            'reportDay' => '16',
            'reportMonth' => '06',
            'reportYear' => '2026',
            'faultLeftChecked' => [],
            'faultRightChecked' => [],
            'faultOtherChecked' => false,
            'jurPd' => '',
            'entregaGh' => '',
            'jurDd' => '',
            'jurMm' => '',
            'jurYyyy' => '',
        ])->render();

        $this->assertDoesNotMatchRegularExpression('/<div[^>]*class="[^"]*fo51-interactive/', $html);
        $this->assertStringNotContainsString('class="ogj-letter-screen-sheet"', $html);
        $this->assertStringNotContainsString('x-ref="fo51LetterSheet"', $html);
        $this->assertStringNotContainsString('@media (max-width: 767px)', $html);
        $this->assertStringContainsString('fo51-pdf', $html);
        $this->assertStringContainsString('fo51-fault-line-tbl', $html);
        $this->assertStringContainsString('fo51-obs-pdf', $html);
        $this->assertStringNotContainsString('<textarea', $html);
        $this->assertStringContainsString('fo51-personal-inner', $html);
        $this->assertStringNotContainsString('.fo51-personal-cell {
        display: flex', $html);
        $this->assertStringContainsString('colspan="3"', $html);
        $this->assertStringContainsString('>NOMBRE:</span>', $html);
        $this->assertSame(6, substr_count($html, 'class="fo51-personal-cell"'));
    }

    public function test_filled_pdf_dompdf_fits_one_physical_page(): void
    {
        config(['services.pdf.driver' => 'dompdf']);

        $signature = $this->sampleSignatureDataUri();

        $html = view('disciplinary.forms.fo-gj-51-filled-download', [
            'embeddedLogoSrc' => 'data:image/png;base64,AA==',
            'workerName' => 'TEGUE LASPRILLA ABRAHAM',
            'workerDocument' => '76269756',
            'workerCargo' => 'GUARDA DE SEGURIDAD',
            'city' => 'Cali',
            'shift' => 'Mañana',
            'position' => 'Puesto 1',
            'faultOtherDetail' => '',
            'observations' => 'estoy realizando pruebas para probar la creación de PDF',
            'preparerName' => 'Supervisor campo',
            'preparerRole' => 'nivel2',
            'preparerSignature' => $signature,
            'reportDay' => '21',
            'reportMonth' => '07',
            'reportYear' => '2026',
            'faultLeftChecked' => ['Retardo al Servicio'],
            'faultRightChecked' => ['Incumplimiento de consignas'],
            'faultOtherChecked' => false,
            'jurPd' => '',
            'entregaGh' => '',
            'jurDd' => '',
            'jurMm' => '',
            'jurYyyy' => '',
        ])->render();

        $binary = \App\Support\Pdf\HtmlLetterPdfGenerator::fromHtml($html);
        $physical = preg_match_all('/\/Type\s*\/Page\b/', $binary);

        $this->assertSame(1, $physical, 'FO-GJ-51 canónico debe caber en 1 hoja Letter Dompdf');
        $this->assertSame(1, preg_match_all('/<td class="ogj-meta-code">FO-GJ-51<\/td>/', $html));
    }

    private function makeSupervisor(): User
    {
        $this->seedMunicipality('76001', 'Cali');

        return $this->seedFieldUserWithCities('nivel7', ['76001']);
    }

    private function sampleSignatureDataUri(): string
    {
        $png = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==',
            true
        );

        return 'data:image/png;base64,'.base64_encode($png ?: '');
    }
}
