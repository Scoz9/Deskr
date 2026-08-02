<?php

namespace App\Http\Requests\Teams;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class TeamStoreRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * The name is not required to be unique, same as an organization's: the
     * `teams` migration carries no unique index, and nothing looks a team up
     * by name — the categories point at its id.
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
