<?php

namespace App\Http\Requests;

use App\Enums\Role;
use App\Models\MlModel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PredictionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole(Role::Admin, Role::Instructor) ?? false;
    }

    public function rules(): array
    {
        return [
            'workflow' => ['required', Rule::in(['demand', 'sales', 'slow', 'best'])],
            'model' => ['required', Rule::in(array_keys(MlModel::ALGORITHMS))],
            'product_id' => ['required_if:workflow,demand', 'nullable', 'integer', 'exists:products,id'],
            'horizon_days' => ['required_if:workflow,demand,sales', 'nullable', 'integer', 'min:1', 'max:365'],
            'history_months' => ['nullable', 'integer', Rule::in([2, 3, 6, 12])],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'period_days' => ['required_if:workflow,best', 'nullable', 'integer', Rule::in([30, 60, 90])],
        ];
    }

    public function messages(): array
    {
        return [
            'product_id.required_if' => 'Select a product to forecast.',
            'horizon_days.required_if' => 'Enter a whole number from 1 to 365 days.',
            'horizon_days.max' => 'Enter a whole number from 1 to 365 days.',
        ];
    }
}
