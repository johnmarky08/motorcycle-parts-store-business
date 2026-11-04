<?php

namespace App\Http\Requests;

use App\Enums\Role;
use App\Models\Dataset;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UploadDatasetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole(Role::Instructor) ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:3', 'max:120', "regex:/^[\p{L}\p{N}\s.,'()\-_]+$/u"],
            'type' => ['required', Rule::in(array_keys(Dataset::TYPES))],
            'file' => ['required', 'file', 'extensions:csv,txt', 'mimetypes:text/plain,text/csv,application/csv,application/vnd.ms-excel', 'max:20480'],
        ];
    }

    public function messages(): array
    {
        return [
            'file.max' => 'The CSV file may not be larger than 20 MB.',
            'file.extensions' => 'Upload a .csv file.',
            'file.mimetypes' => 'The file does not look like a CSV text file.',
            'name.regex' => 'The dataset name contains unsupported characters.',
        ];
    }
}
