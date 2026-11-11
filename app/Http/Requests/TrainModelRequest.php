<?php

namespace App\Http\Requests;

use App\Enums\Role;
use App\Models\MlModel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TrainModelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole(Role::Instructor) ?? false;
    }

    public function rules(): array
    {
        return [
            'dataset_id' => ['required', 'integer', 'exists:datasets,id'],
            'algorithm' => ['required', Rule::in([...array_keys(MlModel::ALGORITHMS), 'both'])],
            'split' => ['required', Rule::in(['0.7', '0.8', '0.9', 0.7, 0.8, 0.9])],
            'tune' => ['nullable', 'boolean'],
            'seasonal' => ['nullable', 'boolean'],
        ];
    }
}
