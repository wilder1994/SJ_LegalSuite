<?php

namespace App\Http\Requests\Disciplinary;

use App\Models\Disciplinary\DisciplinaryCase;
use App\Models\Disciplinary\InformeSubmission;
use App\Models\Employee;
use App\Rules\PngSignatureDataUri;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class FoGj51ProcessRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if (! $user) {
            return false;
        }

        if ((string) $this->input('fo51_action') === 'pdf') {
            return $user->can('disciplinary.download-pdf')
                || $user->can('create', DisciplinaryCase::class)
                || $user->can('generateFo51Inform', DisciplinaryCase::class);
        }

        return $user->can('submit', InformeSubmission::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = StoreFoGj51InformePdfRequest::fieldRules();

        $rules['fo51_preparer_signature'] = [
            Rule::requiredIf(fn () => in_array((string) $this->input('fo51_action'), ['pdf', 'enviar'], true)),
            'nullable',
            'string',
            'max:524288',
            new PngSignatureDataUri,
        ];

        return array_merge($rules, [
            'fo51_action' => ['required', Rule::in(['pdf', 'enviar', 'cargar'])],
            'fo51_assigned_reviewer_id' => [
                Rule::requiredIf(fn () => in_array((string) $this->input('fo51_action'), ['enviar', 'cargar'], true)),
                'nullable',
                'integer',
                'exists:users,id',
            ],

            /* Informe digitado desde pantalla: identidad debe coincidir con el PDF generado */
            'fo51_worker_name' => [
                Rule::requiredIf(fn () => (string) $this->input('fo51_action') === 'enviar'),
                'nullable',
                'string',
                'max:500',
            ],
            'fo51_worker_document' => array_merge(
                [Rule::requiredIf(fn () => (string) $this->input('fo51_action') === 'enviar')],
                Employee::documentNumberRules(false),
            ),

            /* PDF externo: el sistema no extrae texto; debe capturarse a mano */
            'informe_worker_name' => [
                Rule::requiredIf(fn () => (string) $this->input('fo51_action') === 'cargar'),
                'nullable',
                'string',
                'max:500',
            ],
            'informe_worker_document' => array_merge(
                [Rule::requiredIf(fn () => (string) $this->input('fo51_action') === 'cargar')],
                Employee::documentNumberRules(false),
            ),

            'informe_file' => [
                Rule::requiredIf(fn () => (string) $this->input('fo51_action') === 'cargar'),
                'nullable',
                'file',
                'mimetypes:application/pdf',
                'max:15360',
            ],

            'fo51_report_dd' => [
                Rule::requiredIf(fn () => (string) $this->input('fo51_action') === 'cargar'),
                'nullable',
                'string',
                'max:2',
            ],
            'fo51_report_mm' => [
                Rule::requiredIf(fn () => (string) $this->input('fo51_action') === 'cargar'),
                'nullable',
                'string',
                'max:2',
            ],
            'fo51_report_yyyy' => [
                Rule::requiredIf(fn () => (string) $this->input('fo51_action') === 'cargar'),
                'nullable',
                'string',
                'max:4',
            ],

            'evidence_images' => ['nullable', 'array', 'max:10'],
            'evidence_images.*' => ['nullable', 'image', 'max:5120'],
        ]);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'fo51_preparer_signature.required' => 'Capture la firma de quien elabora el informe antes de continuar.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $merge = [
            'fo51_fault_other_chk' => $this->boolean('fo51_fault_other_chk'),
        ];

        if ($this->has('fo51_worker_document')) {
            $merge['fo51_worker_document'] = Employee::normalizeDocumentNumber((string) $this->input('fo51_worker_document'));
        }

        if ($this->has('informe_worker_document')) {
            $merge['informe_worker_document'] = Employee::normalizeDocumentNumber((string) $this->input('informe_worker_document'));
        }

        if ((string) $this->input('fo51_action') === 'cargar') {
            if (! $this->filled('fo51_worker_name') && $this->filled('informe_worker_name')) {
                $merge['fo51_worker_name'] = trim((string) $this->input('informe_worker_name'));
            }
            if (! $this->filled('fo51_worker_document') && $this->filled('informe_worker_document')) {
                $merge['fo51_worker_document'] = Employee::normalizeDocumentNumber((string) $this->input('informe_worker_document'));
            }
        }

        $this->merge($merge);
    }

    public function withValidator(Validator $validator): void
    {
        $validator->sometimes(
            'fo51_municipality_code',
            ['required', 'string', 'size:5', Rule::exists('colombian_municipalities', 'municipality_code')],
            fn () => in_array((string) $this->input('fo51_action'), ['pdf', 'enviar', 'cargar'], true)
        );
    }

    protected function failedValidation(Validator $validator): void
    {
        $action = (string) $this->input('fo51_action');
        $user = $this->user();

        if ($user && ! Gate::allows('viewAny', DisciplinaryCase::class)) {
            if ($user->hasRole('nivel7')) {
                $params = [];
                if ($action === 'cargar') {
                    $params['cargar_pdf'] = 1;
                    $params['informe_modal'] = 1;
                } elseif (in_array($action, ['pdf', 'enviar'], true)) {
                    $params['informe_modal'] = 1;
                }

                throw (new ValidationException($validator))
                    ->redirectTo(route('disciplinary.evidences-pending.index', $params));
            }

            $params = ['vista_completa' => 1];
            if ($action === 'cargar') {
                $params['cargar_pdf'] = 1;
            }

            throw (new ValidationException($validator))
                ->redirectTo(route('disciplinary.forms.informe-fo-gj-51', $params));
        }

        $params = ['informe_modal' => 1];

        if ($action === 'cargar') {
            $params['cargar_pdf'] = 1;
        }

        throw (new ValidationException($validator))
            ->redirectTo(route('disciplinary.cases.index', $params));
    }
}
