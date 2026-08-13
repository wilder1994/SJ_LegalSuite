<?php

namespace App\Http\Controllers\Disciplinary;

use App\Enums\Disciplinary\InformeSubmissionStatus;
use App\Http\Requests\Disciplinary\FoGj51ProcessRequest;
use App\Jobs\Disciplinary\ProcessFoGj51PdfJob;
use App\Models\Disciplinary\DisciplinaryCase;
use App\Models\Disciplinary\InformeSubmission;
use App\Models\Employee;
use App\Models\User;
use App\Services\Disciplinary\DisciplinaryInformeSubmissionService;
use App\Services\Disciplinary\FoGj51PdfBuilder;
use App\Services\Employees\EmployeeResolver;
use App\Support\Pdf\FoGj51PdfQueueStore;
use App\Support\Pdf\LetterPdfDriver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class FoGj51InformeController
{
    public function __construct(
        private readonly FoGj51PdfBuilder $pdfBuilder,
    ) {}

    public function show(Request $request): RedirectResponse|View
    {
        if (! Gate::allows('create', DisciplinaryCase::class)
            && ! Gate::allows('generateFo51Inform', DisciplinaryCase::class)) {
            abort(403);
        }

        if ($request->boolean('vista_completa') || ! Gate::allows('viewAny', DisciplinaryCase::class)) {
            return view('disciplinary.forms.fo-gj-51-fill', [
                'prefillWorkerName' => $request->string('nombre')->trim()->toString() ?: null,
                'prefillWorkerDocument' => $request->string('cedula')->trim()->toString() ?: null,
                'openPdfUploadModal' => $request->boolean('cargar_pdf'),
                'operacionesReviewers' => User::query()
                    ->role('nivel2')
                    ->where('is_active', true)
                    ->orderBy('name')
                    ->get(['id', 'name']),
            ]);
        }

        $query = array_filter([
            'informe_modal' => 1,
            'cargar_pdf' => $request->boolean('cargar_pdf') ? 1 : null,
            'nombre' => $request->string('nombre')->trim()->toString() ?: null,
            'cedula' => $request->string('cedula')->trim()->toString() ?: null,
        ], fn ($v) => $v !== null && $v !== '');

        return Redirect::route('disciplinary.cases.index', $query);
    }

    public function process(FoGj51ProcessRequest $request): Response|RedirectResponse
    {
        $action = (string) $request->validated('fo51_action');

        if ($this->shouldQueuePdf() && in_array($action, ['pdf', 'enviar'], true)) {
            return $this->dispatchQueuedPdf($request, $action);
        }

        return match ($action) {
            'pdf' => $this->respondPdfDownload($request),
            'enviar' => $this->submitToRevisionQueue($request),
            'cargar' => $this->uploadToRevisionQueue($request),
            default => abort(400),
        };
    }

    public function pdfQueueWait(Request $request, string $token): View
    {
        abort_unless(FoGj51PdfQueueStore::belongsToUser($token, (int) $request->user()->id), 404);

        $meta = FoGj51PdfQueueStore::meta($token);
        abort_if($meta === null, 404);

        return view('disciplinary.forms.fo-gj-51-pdf-queue-wait', [
            'token' => $token,
            'intent' => (string) ($meta['intent'] ?? 'pdf'),
            'statusUrl' => route('disciplinary.forms.informe-fo-gj-51.pdf-queue.status', ['token' => $token]),
            'downloadUrl' => route('disciplinary.forms.informe-fo-gj-51.pdf-queue.download', ['token' => $token]),
        ]);
    }

    public function pdfQueueStatus(Request $request, string $token): JsonResponse
    {
        abort_unless(FoGj51PdfQueueStore::belongsToUser($token, (int) $request->user()->id), 404);

        $meta = FoGj51PdfQueueStore::meta($token);
        abort_if($meta === null, 404);

        $status = (string) ($meta['status'] ?? FoGj51PdfQueueStore::STATUS_PENDING);
        $payload = [
            'status' => $status,
            'error' => $meta['error'] ?? null,
        ];

        if ($status === FoGj51PdfQueueStore::STATUS_SUBMITTED) {
            $payload['redirect_url'] = route('disciplinary.forms.informe-fo-gj-51.pdf-queue.complete', ['token' => $token]);
        }

        return response()->json($payload);
    }

    public function pdfQueueComplete(Request $request, string $token): RedirectResponse
    {
        abort_unless(FoGj51PdfQueueStore::belongsToUser($token, (int) $request->user()->id), 404);

        $meta = FoGj51PdfQueueStore::meta($token);
        abort_if($meta === null || ($meta['status'] ?? '') !== FoGj51PdfQueueStore::STATUS_SUBMITTED, 404);

        $routeName = (string) ($meta['redirect_route'] ?? 'disciplinary.evidences-pending.index');

        return redirect()
            ->route(Route::has($routeName) ? $routeName : 'disciplinary.evidences-pending.index')
            ->with('success', 'Su informe quedó en cola para revisión de dirección. Cuando sea autorizado se creará el expediente.');
    }

    public function pdfQueueDownload(Request $request, string $token): Response
    {
        abort_unless(FoGj51PdfQueueStore::belongsToUser($token, (int) $request->user()->id), 404);

        $meta = FoGj51PdfQueueStore::meta($token);
        abort_if($meta === null || ($meta['status'] ?? '') !== FoGj51PdfQueueStore::STATUS_READY, 404);

        $path = FoGj51PdfQueueStore::outputPath($token);
        abort_unless(is_readable($path), 404);

        return response(
            (string) file_get_contents($path),
            200,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="FO-GJ-51-informe-disciplinario.pdf"',
            ],
        );
    }

    public function pendingPdf(Request $request, InformeSubmission $submission)
    {
        Gate::authorize('view', $submission);

        if ($submission->status !== InformeSubmissionStatus::PENDIENTE_REVISION
            || $submission->storage_path === '') {
            abort(404);
        }

        $disk = $submission->storage_disk;
        $path = $submission->storage_path;

        if (! Storage::disk($disk)->exists($path)) {
            abort(404);
        }

        if ($request->boolean('inline')) {
            $absolute = Storage::disk($disk)->path($path);
            if (! is_readable($absolute)) {
                abort(404);
            }

            $filename = basename((string) ($submission->original_filename ?: 'FO-GJ-51-informe.pdf'));
            $filename = str_replace(["\r", "\n", '"'], '', $filename) ?: 'FO-GJ-51-informe.pdf';

            return response()->file($absolute, [
                'Content-Type' => $submission->mime_type ?? 'application/pdf',
                'Content-Disposition' => 'inline; filename="'.$filename.'"',
            ]);
        }

        return Storage::disk($disk)->download(
            $path,
            $submission->original_filename ?? 'FO-GJ-51-informe.pdf',
            ['Content-Type' => $submission->mime_type ?? 'application/pdf']
        );
    }

    private function shouldQueuePdf(): bool
    {
        return LetterPdfDriver::shouldUseQueue();
    }

    private function dispatchQueuedPdf(FoGj51ProcessRequest $request, string $intent): RedirectResponse
    {
        $validated = $request->validated();

        if ($intent === 'enviar') {
            try {
                app(EmployeeResolver::class)->resolveForDisciplinaryActor(
                    $request->user(),
                    isset($validated['fo51_employee_id']) ? (int) $validated['fo51_employee_id'] : null,
                    (string) ($validated['fo51_worker_document'] ?? ''),
                );
            } catch (\InvalidArgumentException $e) {
                if (! Gate::allows('viewAny', DisciplinaryCase::class)) {
                    return redirect()
                        ->route('disciplinary.forms.informe-fo-gj-51', ['vista_completa' => 1])
                        ->withInput()
                        ->withErrors(['fo51_worker_document' => $e->getMessage()]);
                }

                return redirect()
                    ->route('disciplinary.cases.index', ['informe_modal' => 1])
                    ->withInput()
                    ->withErrors(['fo51_worker_document' => $e->getMessage()]);
            }
        }

        $formFields = $this->onlyFo51FormFields($validated);
        $token = FoGj51PdfQueueStore::create($intent, (int) $request->user()->id, [
            'form_fields' => $formFields,
            'assigned_reviewer_id' => isset($validated['fo51_assigned_reviewer_id'])
                ? (int) $validated['fo51_assigned_reviewer_id']
                : null,
            'evidence_files' => [],
        ]);

        $evidenceFiles = collect($request->file('evidence_images', []))
            ->filter(fn ($f) => $f instanceof UploadedFile && $f->isValid())
            ->take(10)
            ->values()
            ->all();

        if ($evidenceFiles !== []) {
            $storedEvidence = FoGj51PdfQueueStore::storeEvidenceFiles($token, $evidenceFiles);
            $payload = FoGj51PdfQueueStore::payload($token);
            if ($payload !== null) {
                $payload['evidence_files'] = $storedEvidence;
                file_put_contents(
                    FoGj51PdfQueueStore::directoryFor($token).'/payload.json',
                    json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                );
            }
        }

        ProcessFoGj51PdfJob::dispatch($token);

        return redirect()->route('disciplinary.forms.informe-fo-gj-51.pdf-queue', ['token' => $token]);
    }

    private function submitToRevisionQueue(FoGj51ProcessRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $resolver = app(EmployeeResolver::class);
        try {
            $employee = $resolver->resolveForDisciplinaryActor(
                $request->user(),
                isset($validated['fo51_employee_id']) ? (int) $validated['fo51_employee_id'] : null,
                (string) ($validated['fo51_worker_document'] ?? ''),
            );
        } catch (\InvalidArgumentException $e) {
            if (! Gate::allows('viewAny', DisciplinaryCase::class)) {
                return redirect()
                    ->route('disciplinary.forms.informe-fo-gj-51', ['vista_completa' => 1])
                    ->withInput()
                    ->withErrors(['fo51_worker_document' => $e->getMessage()]);
            }

            return redirect()
                ->route('disciplinary.cases.index', ['informe_modal' => 1])
                ->withInput()
                ->withErrors(['fo51_worker_document' => $e->getMessage()]);
        }

        $v = $this->onlyFo51FormFields($validated);
        $binary = $this->pdfBuilder->buildBinary($v, $employee);

        $path = tempnam(sys_get_temp_dir(), 'fo51_');
        if ($path === false) {
            abort(500, 'No se pudo preparar el archivo temporal del informe.');
        }

        file_put_contents($path, $binary);

        try {
            $uploaded = new UploadedFile(
                $path,
                'FO-GJ-51-informe-disciplinario.pdf',
                'application/pdf',
                UPLOAD_ERR_OK,
                true
            );
            app(DisciplinaryInformeSubmissionService::class)->storePending(
                $uploaded,
                $request->user(),
                $employee->id,
                (int) $validated['fo51_assigned_reviewer_id'],
                $v,
                isset($v['fo51_observations']) ? mb_substr((string) $v['fo51_observations'], 0, 5000) : null,
                collect($request->file('evidence_images', []))
                    ->filter(fn ($f) => $f instanceof UploadedFile && $f->isValid())
                    ->take(10)
                    ->values()
                    ->all(),
            );
        } finally {
            if (is_file($path)) {
                @unlink($path);
            }
        }

        return redirect()
            ->route($this->postSubmitRouteName())
            ->with('success', 'Su informe quedó en cola para revisión de dirección. Cuando sea autorizado se creará el expediente.');
    }

    private function uploadToRevisionQueue(FoGj51ProcessRequest $request): RedirectResponse
    {
        $file = $request->file('informe_file');
        if (! $file instanceof UploadedFile) {
            abort(400);
        }

        $validated = $request->validated();
        $resolver = app(EmployeeResolver::class);
        try {
            $employee = $resolver->resolveForDisciplinaryActor(
                $request->user(),
                isset($validated['fo51_employee_id']) ? (int) $validated['fo51_employee_id'] : null,
                (string) ($validated['informe_worker_document'] ?? ''),
            );
        } catch (\InvalidArgumentException $e) {
            $user = $request->user();
            if ($user && ! Gate::allows('viewAny', DisciplinaryCase::class)) {
                if ($user->hasRole('nivel7')) {
                    return redirect()
                        ->route('disciplinary.evidences-pending.index', ['informe_modal' => 1, 'cargar_pdf' => 1])
                        ->withInput()
                        ->withErrors(['informe_worker_document' => $e->getMessage()]);
                }

                return redirect()
                    ->route('disciplinary.forms.informe-fo-gj-51', ['vista_completa' => 1, 'cargar_pdf' => 1])
                    ->withInput()
                    ->withErrors(['informe_worker_document' => $e->getMessage()]);
            }

            return redirect()
                ->route('disciplinary.cases.index', ['informe_modal' => 1, 'cargar_pdf' => 1])
                ->withInput()
                ->withErrors(['informe_worker_document' => $e->getMessage()]);
        }

        $v = array_merge($this->onlyFo51FormFields($validated), [
            'informe_declared_worker_name' => trim((string) ($validated['informe_worker_name'] ?? '')),
            'informe_declared_worker_document' => Employee::normalizeDocumentNumber((string) ($validated['informe_worker_document'] ?? '')),
            'fo51_worker_name' => trim((string) ($validated['informe_worker_name'] ?? '')),
            'fo51_worker_document' => Employee::normalizeDocumentNumber((string) ($validated['informe_worker_document'] ?? '')),
        ]);

        $evidenceImages = collect($request->file('evidence_images', []))
            ->filter(fn ($f) => $f instanceof UploadedFile && $f->isValid())
            ->take(10)
            ->values()
            ->all();

        app(DisciplinaryInformeSubmissionService::class)->storePending(
            $file,
            $request->user(),
            $employee->id,
            (int) $validated['fo51_assigned_reviewer_id'],
            $v,
            isset($validated['informe_worker_name'])
                ? mb_substr(trim((string) $validated['informe_worker_name']), 0, 120)
                : null,
            $evidenceImages,
        );

        return redirect()
            ->route($this->postSubmitRouteName())
            ->with('success', 'El PDF se envió a revisión de dirección. Cuando sea autorizado se creará el expediente.');
    }

    private function respondPdfDownload(FoGj51ProcessRequest $request): Response
    {
        $validated = $request->validated();
        $v = $this->onlyFo51FormFields($validated);
        $employee = $this->tryResolveEmployeeForFo51($validated);
        $binary = $this->pdfBuilder->buildBinary($v, $employee);

        return response($binary, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="FO-GJ-51-informe-disciplinario.pdf"',
        ]);
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function onlyFo51FormFields(array $validated): array
    {
        return Arr::except($validated, [
            'fo51_action',
            'informe_file',
            'informe_worker_name',
            'informe_worker_document',
            'evidence_images',
        ]);
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function tryResolveEmployeeForFo51(array $validated): ?Employee
    {
        return $this->pdfBuilder->resolveEmployee($this->onlyFo51FormFields($validated));
    }

    private function postSubmitRouteName(): string
    {
        return Gate::allows('viewAny', DisciplinaryCase::class)
            ? 'disciplinary.cases.index'
            : 'disciplinary.evidences-pending.index';
    }
}
