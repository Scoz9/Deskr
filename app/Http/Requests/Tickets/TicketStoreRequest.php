<?php

namespace App\Http\Requests\Tickets;

use App\Enums\TicketPriority;
use App\Models\Category;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A ticket the console opens on behalf of whoever called or walked in
 * (roadmap step 39). Unlike the public intake, there is no honeypot and no
 * cap on open tickets — both defend a door anybody on the internet can
 * reach (§5), and this one only an authenticated operator does.
 */
class TicketStoreRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, ValidationRule|string>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'category_id' => ['required', 'integer', Rule::exists(Category::class, 'id')],
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:5000'],
            'priority' => ['required', Rule::enum(TicketPriority::class)],
        ];
    }
}
