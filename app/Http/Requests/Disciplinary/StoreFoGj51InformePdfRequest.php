<?php

namespace App\Http\Requests\Disciplinary;

use App\Models\Disciplinary\DisciplinaryCase;
use App\Models\Employee;
use App\Rules\PngSignatureDataUri;
use App\Support\Disciplinary\FoGj51Catalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFoGj51InformePdfRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if (! $user) {
            return false;
        }

        return $user->can('disciplinary.download-pdf')
            || $user->can('create', DisciplinaryCase::class)
            || $user->can('generateFo51Inform', DisciplinaryCase::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return self::fieldRules();
    }

    /**
     * Reglas de los campos del formulario FO-GJ-51 (sin acción ni adjuntos).
     *
     * @return array<string, mixed>
     */
    public static function fieldRules(): array
    {
        return [
            'fo51_report_dd' => ['nullable', 'string', 'max:2'],
            'fo51_report_mm' => ['nullable', 'string', 'max:2'],
            'fo51_report_yyyy' => ['nullable', 'string', 'max:4'],
            'fo51_worker_name' => ['nullable', 'string', 'max:500'],
            'fo51_worker_document' => Employee::documentNumberRules(false),
            'fo51_employee_id' => ['nullable', 'integer', 'exists:employees,id'],
            'fo51_assigned_reviewer_id' => ['nullable', 'integer', 'exists:users,id'],
            'fo51_municipality_code' => ['nullable', 'string', 'size:5', Rule::exists('colombian_municipalities', 'municipality_code')],
            'fo51_shift' => ['nullable', 'string', 'max:120'],
            'fo51_position' => ['nullable', 'string', 'max:120'],
            'fo51_worker_cargo' => ['nullable', 'string', 'max:120'],
            'fo51_fault_left' => ['nullable', 'array'],
            'fo51_fault_left.*' => ['string', Rule::in(FoGj51Catalog::faultLeft())],
            'fo51_fault_right' => ['nullable', 'array'],
            'fo51_fault_right.*' => ['string', Rule::in(FoGj51Catalog::faultRight())],
            'fo51_fault_other_chk' => ['nullable', 'boolean'],
            'fo51_fault_other_detail' => ['nullable', 'string', 'max:500'],
            'fo51_observations' => ['nullable', 'string', 'max:10000'],
            'fo51_preparer_name' => ['nullable', 'string', 'max:300'],
            'fo51_preparer_role' => ['nullable', 'string', 'max:300'],
            'fo51_preparer_signature' => ['nullable', 'string', 'max:524288', new PngSignatureDataUri],
            'fo51_jur_pd' => ['nullable', 'string', 'max:120'],
            'fo51_entrega_gh' => ['nullable', 'string', 'max:120'],
            'fo51_jur_dd' => ['nullable', 'string', 'max:2'],
            'fo51_jur_mm' => ['nullable', 'string', 'max:2'],
            'fo51_jur_yyyy' => ['nullable', 'string', 'max:4'],
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

        $this->merge($merge);
    }
}
