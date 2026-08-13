<?php

use App\Http\Controllers\Disciplinary\ComiteActaCaseController;
use App\Http\Controllers\Disciplinary\DecisionComunicadoCaseController;
use App\Http\Controllers\Disciplinary\DisciplinaryAgendaAttachmentDownloadController;
use App\Http\Controllers\Disciplinary\DisciplinaryAgendaAttachmentInlineController;
use App\Http\Controllers\Disciplinary\DisciplinaryAgendaThreadAttachmentDownloadController;
use App\Http\Controllers\Disciplinary\DisciplinaryAgendaThreadAttachmentInlineController;
use App\Http\Controllers\Disciplinary\DisciplinaryCaseController;
use App\Http\Controllers\Disciplinary\DisciplinaryCaseDocumentInlineController;
use App\Http\Controllers\Disciplinary\DisciplinaryDashboardController;
use App\Http\Controllers\Disciplinary\DisciplinaryGeoJsonController;
use App\Http\Controllers\Disciplinary\DisciplinaryPortalController;
use App\Http\Controllers\Disciplinary\FoGj03CaseController;
use App\Http\Controllers\Disciplinary\FoGj04CaseController;
use App\Http\Controllers\Disciplinary\FoGj44CaseController;
use App\Http\Controllers\Disciplinary\FoGj51InformeController;
use App\Http\Controllers\Disciplinary\FoGj54CaseController;
use App\Http\Controllers\Disciplinary\InformeSubmissionEvidenceInlineController;
use App\Http\Controllers\Disciplinary\OfficialFormBlankDownloadController;
use App\Http\Controllers\Disciplinary\OfficialFormPreviewController;
use App\Http\Controllers\Disciplinary\OrganizationLetterheadController;
use App\Http\Controllers\Disciplinary\SupervisorEvidenceUploadPreviewController;
use App\Http\Controllers\Disciplinary\SupervisorSignedNotificationPreviewController;
use App\Http\Controllers\Employees\EmployeeSearchController;
use App\Http\Controllers\Employees\EmployeeTemplateDownloadController;
use App\Livewire\Auth\ForcePasswordChange;
use App\Livewire\Disciplinary\Cases\CaseDetail;
use App\Livewire\Disciplinary\Cases\CasesIndex;
use App\Livewire\Disciplinary\Coordinations\Index as CoordinationsIndex;
use App\Livewire\Disciplinary\Dashboard;
use App\Livewire\Disciplinary\FormatsCatalog;
use App\Livewire\Disciplinary\InformesPendientes;
use App\Livewire\Disciplinary\Administrativa\PendingDecisionHrIndex;
use App\Livewire\Disciplinary\Supervisor\HistoryIndex as SupervisorHistoryIndex;
use App\Livewire\Disciplinary\Supervisor\PendingEvidenceIndex;
use App\Livewire\Employees\EmployeesIndex;
use App\Livewire\Home;
use App\Livewire\Settings\CitationArticlesIndex;
use App\Livewire\Settings\DiligenceQuestionsIndex;
use App\Livewire\Settings\SupervisionZonesIndex;
use App\Livewire\Settings\TerritoryImport;
use App\Livewire\Users\OrganizationCatalog;
use App\Livewire\Users\UserDetail;
use App\Livewire\Users\UsersIndex;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

Route::view('/', 'welcome');

Route::middleware(['auth'])->group(function () {
    Route::get('password/first-login', ForcePasswordChange::class)->name('password.force-change');
});

Route::middleware(['auth', 'must-change-password'])->group(function () {
    Route::view('profile', 'profile')->name('profile');

    Route::get('profile/signature', function () {
        $user = auth()->user();
        abort_unless($user && $user->hasSignature(), 404);

        return Storage::disk($user->signature_disk ?? 'local')->response((string) $user->signature_path);
    })->name('profile.signature');
});

Route::middleware(['auth', 'must-change-password', 'verified'])->group(function () {
    Route::get('dashboard', Home::class)->name('dashboard');

    Route::redirect('settings', 'settings/territorio')->name('settings.index');

    Route::get('settings/territorio', TerritoryImport::class)->name('settings.territory-import');
    Route::get('settings/citacion-articulos', CitationArticlesIndex::class)->name('settings.citation-articles');
    Route::get('settings/preguntas-diligencia', DiligenceQuestionsIndex::class)->name('settings.diligence-questions');
    Route::get('settings/zonas-supervision', SupervisionZonesIndex::class)->name('settings.supervision-zones');

    Route::prefix('disciplinary')->name('disciplinary.')->group(function () {
        Route::get('/', DisciplinaryPortalController::class)->name('index');
        Route::get('dashboard', Dashboard::class)->name('dashboard');
        Route::get('map-geo/{file}', DisciplinaryGeoJsonController::class)
            ->where('file', 'gadm41_COL_1\.json|gadm41_COL_2\.json')
            ->name('map-geo');
        Route::get('formats', FormatsCatalog::class)->name('formats.index');
        Route::get('formats/descarga-en-blanco/{code}', OfficialFormBlankDownloadController::class)
            ->where('code', '[A-Za-z0-9\-]+')
            ->name('formats.download-blank');
        Route::get('formats/preview/{code}', OfficialFormPreviewController::class)
            ->where('code', '[A-Za-z0-9\-]+')
            ->name('formats.preview');
        Route::get('formats/membrete', [OrganizationLetterheadController::class, 'show'])
            ->name('formats.letterhead');

        Route::get('forms/informe-fo-gj-51', [FoGj51InformeController::class, 'show'])
            ->name('forms.informe-fo-gj-51');
        Route::post('forms/informe-fo-gj-51', [FoGj51InformeController::class, 'process'])
            ->name('forms.informe.process');
        Route::get('forms/informe-fo-gj-51/pdf-queue/{token}', [FoGj51InformeController::class, 'pdfQueueWait'])
            ->name('forms.informe-fo-gj-51.pdf-queue');
        Route::get('forms/informe-fo-gj-51/pdf-queue/{token}/status', [FoGj51InformeController::class, 'pdfQueueStatus'])
            ->name('forms.informe-fo-gj-51.pdf-queue.status');
        Route::get('forms/informe-fo-gj-51/pdf-queue/{token}/download', [FoGj51InformeController::class, 'pdfQueueDownload'])
            ->name('forms.informe-fo-gj-51.pdf-queue.download');
        Route::get('forms/informe-fo-gj-51/pdf-queue/{token}/complete', [FoGj51InformeController::class, 'pdfQueueComplete'])
            ->name('forms.informe-fo-gj-51.pdf-queue.complete');

        Route::get('informes-pendientes', InformesPendientes::class)->name('informes-pendientes.index');
        Route::get('informes-pendientes/{submission}/pdf', [FoGj51InformeController::class, 'pendingPdf'])
            ->name('informes-pendientes.pdf');
        Route::get('informes-pendientes/{submission}/evidence/{index}', InformeSubmissionEvidenceInlineController::class)
            ->whereNumber('index')
            ->name('informes-pendientes.evidence');

        Route::get('cases', CasesIndex::class)->name('cases.index');
        Route::get('evidences-pending', PendingEvidenceIndex::class)->name('evidences-pending.index');
        Route::get('evidences-pending/signed-preview/{token}', SupervisorSignedNotificationPreviewController::class)
            ->name('evidences-pending.signed-preview');
        Route::get('evidences-pending/scanned-preview', SupervisorEvidenceUploadPreviewController::class)
            ->name('evidences-pending.scanned-preview');
        Route::get('historial', SupervisorHistoryIndex::class)->name('historial.index');
        Route::get('decision-hr-pending', PendingDecisionHrIndex::class)->name('decision-hr-pending.index');
        Route::get('coordinations', CoordinationsIndex::class)->name('coordinations.index');
        Route::get('coordinations/{thread}/attachments/{attachment}/inline', DisciplinaryAgendaThreadAttachmentInlineController::class)
            ->name('coordinations.attachments.inline');
        Route::get('coordinations/{thread}/attachments/{attachment}', DisciplinaryAgendaThreadAttachmentDownloadController::class)
            ->name('coordinations.attachments.download');
        Route::get('cases/{case}/agenda-attachments/{attachment}/inline', DisciplinaryAgendaAttachmentInlineController::class)
            ->name('cases.agenda-attachment.inline');
        Route::get('cases/{case}/agenda-attachments/{attachment}', DisciplinaryAgendaAttachmentDownloadController::class)
            ->name('cases.agenda-attachment.download');
        Route::get('cases/{case}/documents/{document}/file', DisciplinaryCaseDocumentInlineController::class)
            ->name('cases.documents.file');
        Route::get('cases/{case}/fo-gj-03/pdf', [FoGj03CaseController::class, 'download'])
            ->name('cases.fo-gj-03.pdf');
        Route::get('cases/{case}/fo-gj-03/pdf-queue/{token}', [FoGj03CaseController::class, 'pdfQueueWait'])
            ->name('cases.fo-gj-03.pdf-queue');
        Route::get('cases/{case}/fo-gj-03/pdf-queue/{token}/status', [FoGj03CaseController::class, 'pdfQueueStatus'])
            ->name('cases.fo-gj-03.pdf-queue.status');
        Route::get('cases/{case}/fo-gj-03/pdf-queue/{token}/download', [FoGj03CaseController::class, 'pdfQueueDownload'])
            ->name('cases.fo-gj-03.pdf-queue.download');
        Route::get('cases/{case}/fo-gj-03/pdf-queue/{token}/complete', [FoGj03CaseController::class, 'pdfQueueComplete'])
            ->name('cases.fo-gj-03.pdf-queue.complete');
        Route::post('cases/{case}/fo-gj-03/generate', [FoGj03CaseController::class, 'generate'])
            ->name('cases.fo-gj-03.generate');
        Route::get('cases/{case}/fo-gj-04/pdf', [FoGj04CaseController::class, 'download'])
            ->name('cases.fo-gj-04.pdf');
        Route::get('cases/{case}/fo-gj-44/pdf', [FoGj44CaseController::class, 'download'])
            ->name('cases.fo-gj-44.pdf');
        Route::get('cases/{case}/fo-gj-54/pdf', [FoGj54CaseController::class, 'download'])
            ->name('cases.fo-gj-54.pdf');
        Route::get('cases/{case}/comite-acta/pdf', [ComiteActaCaseController::class, 'download'])
            ->name('cases.comite-acta.pdf');
        Route::get('cases/{case}/decision-comunicado/pdf', [DecisionComunicadoCaseController::class, 'download'])
            ->name('cases.decision-comunicado.pdf');
        Route::get('cases/{case}', CaseDetail::class)->name('cases.show');
    });

    Route::get('employees', EmployeesIndex::class)->name('employees.index');
    Route::get('employees/plantilla', EmployeeTemplateDownloadController::class)->name('employees.template');
    Route::get('api/employees/search', EmployeeSearchController::class)->name('api.employees.search');

    Route::prefix('users')->name('users.')->group(function () {
        Route::get('/', UsersIndex::class)->name('index');
        Route::get('/organizacion', OrganizationCatalog::class)->name('organization');
        Route::redirect('/zonas-supervision', '/settings/zonas-supervision')->name('supervision-zones');
        Route::get('/{user}', UserDetail::class)->name('show');
    });

    Route::prefix('api/disciplinary')->name('api.disciplinary.')->group(function () {
        Route::get('dashboard', DisciplinaryDashboardController::class)->name('dashboard');
        Route::get('cases', [DisciplinaryCaseController::class, 'index'])->name('cases.index');
        Route::post('cases', [DisciplinaryCaseController::class, 'store'])->name('cases.store');
        Route::get('cases/{case}', [DisciplinaryCaseController::class, 'show'])->name('cases.show');
        Route::get('cases/{case}/transitions', [DisciplinaryCaseController::class, 'allowedTransitions'])->name('cases.transitions');
        Route::post('cases/{case}/transition', [DisciplinaryCaseController::class, 'transition'])->name('cases.transition');
    });
});

Route::post('/deploy/{token}', function (string $token) {
    $expected = (string) config('app.deploy_webhook_token');

    if ($expected === '' || ! hash_equals($expected, $token)) {
        abort(403, 'Unauthorized');
    }

    $gitPull = Process::path(base_path())
        ->withEnvironmentVariables([
            'GIT_SSH_COMMAND' => 'ssh -i /home/u348559544/.ssh/id_ed25519 -o StrictHostKeyChecking=no',
        ])
        ->run('git pull origin main');

    if (! $gitPull->successful()) {
        Log::error('Deploy webhook: git pull failed', [
            'output' => $gitPull->output(),
            'error' => $gitPull->errorOutput(),
        ]);

        return response()->json([
            'status' => 'error',
            'message' => 'git pull failed',
            'git_error' => $gitPull->errorOutput(),
            'git_output' => $gitPull->output(),
        ], 500);
    }

    $artisanClear = Process::path(base_path())->run('php artisan optimize:clear');

    Log::info('Deploy webhook executed', [
        'git' => $gitPull->output(),
        'artisan' => $artisanClear->output(),
    ]);

    return response()->json([
        'status' => 'success',
        'message' => 'Código actualizado con éxito en Hostinger.',
    ]);
})->name('deploy.webhook');

require __DIR__.'/auth.php';
