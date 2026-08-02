<?php

namespace App\Http\Requests\Organizations;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class OrganizationStoreRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * Unlike a role's name, an organization's is not required to be unique:
     * it is a label a person typed, not an identifier the permission system
     * reads, and two real companies sharing a name is not something the
     * helpdesk needs to refuse.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
        ];
    }
}
