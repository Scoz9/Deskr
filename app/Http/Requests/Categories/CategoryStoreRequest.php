<?php

namespace App\Http\Requests\Categories;

use App\Models\Category;
use App\Models\Team;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CategoryStoreRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * The name *is* unique here, unlike a team's or an organization's: the
     * `categories` migration carries the index, and the public intake shows
     * this list to whoever is asking for help — two rows reading the same
     * would be a choice nobody can make.
     *
     * The team is required for the same reason the column is: a category
     * without a destination would leave the routing with nowhere to go.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', Rule::unique(Category::class, 'name')],
            'team_id' => ['required', 'integer', Rule::exists(Team::class, 'id')],
        ];
    }
}
