<?php

namespace App\Http\Requests;

use App\Enums\Role;
use App\Models\User;
use App\Support\FieldLimits;
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
            'name' => ['required', 'string', 'max:'.FieldLimits::NAME],
            'email' => [
                'required', 'string', 'email', 'max:254',
                Rule::unique('users', 'email')->ignore($editing instanceof User ? $editing->getKey() : null),
            ],
            'role' => ['required', Rule::enum(Role::class)],
            'current_password' => $this->currentPasswordRules($editing instanceof User ? $editing : null),
        ];
    }

    /** @return array<int, string> */
    private function currentPasswordRules(?User $editing): array
    {
        $actor = $this->user();

        if ($editing === null || ! $actor instanceof User || ! $actor->is($editing)) {
            return [];
        }

        return $this->input('email') === $editing->email ? [] : ['required', 'current_password'];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'name.required' => __('users.name_required'),
            'name.max' => __('validation.text_max', ['label' => __('fields.name'), 'max' => FieldLimits::NAME]),
            'email.required' => __('auth.email_required'),
            'email.email' => __('users.email_invalid'),
            'email.max' => __('validation.text_max', ['label' => __('fields.email'), 'max' => 254]),
            'email.unique' => __('users.email_taken'),
            'role.required' => __('users.role_invalid'),
            'role.Illuminate\Validation\Rules\Enum' => __('users.role_invalid'),
            'current_password.required' => __('users.current_password_required'),
            'current_password.current_password' => __('users.current_password_required'),
        ];
    }
}
