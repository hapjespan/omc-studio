<?php

namespace App\Http\Requests;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Validation\Rule;

class UserRequest extends AppFormRequest
{
    protected function prepareForValidation(): void
    {
        parent::prepareForValidation();

        if (is_string($this->input('email'))) {
            $this->merge(['email' => mb_strtolower($this->input('email'))]);
        }
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $editing = $this->route('user');

        return [
            'name' => ['required', 'string', 'max:150'],
            'email' => [
                'required', 'string', 'email', 'max:254',
                Rule::unique('users', 'email')->ignore($editing instanceof User ? $editing->getKey() : null),
            ],
            'role' => ['required', Rule::enum(Role::class)],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'name.required' => __('users.name_required'),
            'name.max' => __('validation.text_max', ['label' => __('fields.name'), 'max' => 150]),
            'email.required' => __('auth.email_required'),
            'email.email' => __('users.email_invalid'),
            'email.max' => __('validation.text_max', ['label' => __('fields.email'), 'max' => 254]),
            'email.unique' => __('users.email_taken'),
            'role.required' => __('users.role_invalid'),
            'role.Illuminate\Validation\Rules\Enum' => __('users.role_invalid'),
        ];
    }
}
