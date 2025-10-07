<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTaskRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->hasPermission('tasks.create');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'project_id' => 'required|exists:projects,id',
            'status' => 'required|in:pending,in_progress,completed,cancelled',
            'priority' => 'required|in:low,medium,high,urgent',
            'due_date' => 'nullable|date|after_or_equal:today',
            'assigned_users' => 'nullable|array',
            'assigned_users.*' => 'exists:users,id',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'title.required' => 'O título da tarefa é obrigatório.',
            'title.max' => 'O título não pode ter mais de 255 caracteres.',
            'description.max' => 'A descrição não pode ter mais de 2000 caracteres.',
            'project_id.required' => 'O projeto é obrigatório.',
            'project_id.exists' => 'Projeto não encontrado.',
            'status.required' => 'O status da tarefa é obrigatório.',
            'status.in' => 'Status inválido. Use: pending, in_progress, completed ou cancelled.',
            'priority.required' => 'A prioridade da tarefa é obrigatória.',
            'priority.in' => 'Prioridade inválida. Use: low, medium, high ou urgent.',
            'due_date.after_or_equal' => 'A data de vencimento deve ser hoje ou uma data futura.',
            'assigned_users.array' => 'Os usuários atribuídos devem ser uma lista.',
            'assigned_users.*.exists' => 'Um ou mais usuários não foram encontrados.',
        ];
    }
}