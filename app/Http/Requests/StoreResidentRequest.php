<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreResidentRequest extends FormRequest
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
            'national_number_id' => 'required|string',
            'name' => 'required|string',
            'gender' => 'required|string',
            'place_of_birth' => 'required|string',
            'date_of_birth' => 'required|date',
            'religion' => 'required|string',
            'rt' => 'required|string',
            'rw' => 'required|string',
            'education' => 'required|string',
            'occupation' => 'required|string',
            'marital_status' => 'required|string',
            'citizenship' => 'required|string',
            'blood_type' => 'required|string',
            'disabilities' => 'required|string',
            'father_name' => 'required|string',
            'mother_name' => 'required|string',
            'region_id' => 'required|numeric',
        ];
    }
}
