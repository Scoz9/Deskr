<?php

namespace App\Http\Requests\Users;

use App\Concerns\ProfileValidationRules;
use App\Concerns\ValidatesAssignableRoles;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UserStoreRequest extends FormRequest
{
    use ProfileValidationRules, ValidatesAssignableRoles;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...$this->profileRules(),
            'role' => ['required', 'string', Rule::exists('roles', 'name'), $this->assignableRoleRule()],
        ];
    }
}
