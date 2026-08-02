<?php

namespace App\Http\Requests\Users;

use App\Concerns\ProfileValidationRules;
use App\Concerns\ValidatesAssignableRoles;
use App\Models\Organization;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UserStoreRequest extends FormRequest
{
    use ProfileValidationRules, ValidatesAssignableRoles;

    /**
     * Get the validation rules that apply to the request.
     *
     * The organization is optional and says nothing about the role: §4 calls
     * it "the requester's company" and the model documents it as null for
     * agents and admins, but that is a convention about what the column is
     * for — nothing reads it for anybody else, so there is no rule here to
     * police it with.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...$this->profileRules(),
            'role' => ['required', 'string', Rule::exists('roles', 'name'), $this->assignableRoleRule()],
            'organization_id' => ['nullable', 'integer', Rule::exists(Organization::class, 'id')],
        ];
    }
}
