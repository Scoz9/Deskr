<?php

namespace App\Http\Requests\Support;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The address a link is asked for.
 *
 * It is checked for the shape of an address and nothing else: whether anybody
 * answers at it is deliberately not said out loud, because the answer would
 * tell a stranger who is a customer of the helpdesk.
 */
class PortalLinkRequest extends FormRequest
{
    /**
     * Anybody may ask: the portal is reached without an account (§3).
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, ValidationRule|string>>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:255'],
        ];
    }
}
