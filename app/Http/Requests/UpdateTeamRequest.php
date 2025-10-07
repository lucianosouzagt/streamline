<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTeamRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $team = $this->route('team');

        return $team && ($team->owner_id === $this->user()->id || $this->user()->hasPermission('teams.update'));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $team = $this->route('team');

        return [
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                Rule::unique('teams', 'name')->ignore($team->id ?? null),
            ],
            'description' => 'nullable|string|max:1000',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'name.required' => 'O nome do time é obrigatório.',
            'name.unique' => 'Já existe um time com este nome.',
            'name.max' => 'O nome do time não pode ter mais de 255 caracteres.',
            'description.max' => 'A descrição não pode ter mais de 1000 caracteres.',
        ];
    }
}
