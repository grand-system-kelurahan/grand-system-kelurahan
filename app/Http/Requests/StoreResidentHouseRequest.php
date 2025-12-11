<?php
namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreResidentHouseRequest extends FormRequest
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
            'region_id'        => 'nullable|exists:regions,id',
            'resident_id'      => 'nullable|exists:residents,id',
            'name'             => 'required|string|max:100',
            'type'             => 'nullable|string|max:50',
            'description'      => 'nullable|string',
            'encoded_geometry' => 'nullable|string',
        ];
    }
}
