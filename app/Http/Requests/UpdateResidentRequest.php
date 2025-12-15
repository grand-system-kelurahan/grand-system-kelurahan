<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateResidentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'national_number_id'    => 'nullable|string',
            'name'                  => 'nullable|string',
            'gender'                => 'nullable|string',
            'place_of_birth'        => 'nullable|string',
            'date_of_birth'         => 'nullable|date',
            'religion'              => 'nullable|string',
            'rt'                    => 'nullable|string',
            'rw'                    => 'nullable|string',
            'education'             => 'nullable|string',
            'occupation'            => 'nullable|string',
            'marital_status'        => 'nullable|string',
            'citizenship'           => 'nullable|string',
            'blood_type'            => 'nullable|string',
            'disabilities'          => 'nullable|string',
            'father_name'           => 'nullable|string',
            'mother_name'           => 'nullable|string',
            'region_id'             => 'nullable|numeric',
        ];
    }
}
