<?php

namespace App\Http\Requests\Categories;

use App\Models\Category;
use App\Models\Team;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CategoryUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * Re-routing a category is allowed and deliberately does not touch the
     * tickets already filed under it: the intake writes `team_id` on the
     * ticket itself (§4), so where they went stays where they went.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique(Category::class, 'name')->ignore($this->route('category')),
            ],
            'team_id' => ['required', 'integer', Rule::exists(Team::class, 'id')],
        ];
    }
}
