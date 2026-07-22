<?php

namespace App\Http\Requests\Building;

use App\Models\Building;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class UpdateBuildingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        /** @var Building $building */
        $building = $this->route('building');

        return [
            'name' => ['sometimes', 'required', 'string', 'max:150'],
            'code' => [
                'sometimes',
                'nullable',
                'string',
                'max:50',
                Rule::unique('buildings', 'code')
                    ->where('company_id', Auth::user()?->company_id)
                    ->ignore($building->id),
            ],
            'address' => ['sometimes', 'required', 'string'],
            'city' => ['sometimes', 'required', 'string', 'max:100'],
            'district' => ['sometimes', 'required', 'string', 'max:100'],
            'manager_name' => ['sometimes', 'nullable', 'string', 'max:150'],
            'manager_phone' => ['sometimes', 'nullable', 'string', 'max:30'],
            'entrance_code' => ['sometimes', 'nullable', 'string', 'max:50'],
            'access_notes' => ['sometimes', 'nullable', 'string'],
            'latitude' => ['sometimes', 'nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['sometimes', 'nullable', 'numeric', 'between:-180,180'],
            'is_active' => ['sometimes', 'boolean'],
            'notes' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
