<?php
namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateResidentHouseRequest extends FormRequest
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
            'region_id'        => 'sometimes|nullable|exists:regions,id',
            'resident_id'      => 'sometimes|nullable|exists:residents,id',
            'name'             => 'sometimes|required|string|max:100',
            'type'             => 'sometimes|nullable|string|max:50',
            'description'      => 'sometimes|nullable|string',
            'encoded_geometry' => 'sometimes|nullable|string',
        ];
    }
}
