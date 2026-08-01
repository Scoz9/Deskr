<?php

namespace App\Http\Requests\Support;

use App\Models\Ticket;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * A requester answering their own ticket from the portal.
 *
 * The portal session is the only credential a POST admits (§3: "un POST
 * vuole identità e CSRF, non una firma nella query string") — the signed
 * link of the confirmation email carries no session, so it cannot reach this
 * far. Whoever is not the ticket's own requester is refused before a single
 * field is looked at.
 */
class PortalReplyRequest extends FormRequest
{
    /**
     * The one filter between two customers (§3): being logged in as somebody
     * else is worth nothing here.
     */
    public function authorize(): bool
    {
        return $this->user()?->getKey() === $this->ticket()?->requester_id;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, ValidationRule|string>>
     */
    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:5000'],
        ];
    }

    private function ticket(): ?Ticket
    {
        $ticket = $this->route('ticket');

        return $ticket instanceof Ticket ? $ticket : null;
    }
}
