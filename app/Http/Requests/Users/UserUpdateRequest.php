<?php

namespace App\Http\Requests\Users;

use App\Concerns\ProfileValidationRules;
use App\Concerns\ValidatesAssignableRoles;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserUpdateRequest extends FormRequest
{
    use ProfileValidationRules, ValidatesAssignableRoles;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $user = $this->route('user');

        return [
            ...$this->profileRules($user instanceof User ? $user->id : null),
            'password' => ['nullable', 'string', Password::default()],
            'role' => ['sometimes', 'required', 'string', Rule::exists('roles', 'name'), $this->assignableRoleRule()],
        ];
    }
}
