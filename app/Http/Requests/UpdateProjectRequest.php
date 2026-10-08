<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProjectRequest extends FormRequest
{
    /**
     * Редактировать проект может только руководитель (ProjectPolicy::update).
     */
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('project'));
    }

    /**
     * Правила валидации редактирования проекта.
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:100'],
            'start_date' => ['required', 'date'],
            'due_date' => ['required', 'date', 'after_or_equal:start_date'],
            'status' => ['required', 'in:active,paused,completed,cancelled'],
            'responsible' => ['required', 'array', 'size:4'],
            'responsible.*' => ['required', Rule::exists('users', 'id')->where('is_active', 1)],
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => 'Укажите название проекта',
            'title.max' => 'Название не длиннее 100 символов',
            'start_date.required' => 'Укажите дату начала',
            'due_date.required' => 'Укажите желаемую дату завершения',
            'due_date.after_or_equal' => 'Дата завершения не может быть раньше даты начала',
            'responsible.required' => 'Назначьте ответственного по каждой задаче',
            'responsible.*.exists' => 'Ответственный должен быть активным пользователем',
        ];
    }
}