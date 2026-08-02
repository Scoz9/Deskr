<?php

namespace App\Http\Requests\Tickets;

use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Tickets\TicketTransitions;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class TicketStatusUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', 'string', $this->allowedTransitionRule()],
        ];
    }

    /**
     * A status is valid here only if the lifecycle table admits the passage
     * from where the ticket already is — the same table {@see TransitionTicket}
     * asks, checked before the request ever reaches it so a refused passage
     * comes back as a 422 and not a 500.
     */
    private function allowedTransitionRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            $to = TicketStatus::tryFrom((string) $value);

            if ($to === null) {
                $fail('Stato non valido.');

                return;
            }

            /** @var Ticket $ticket */
            $ticket = $this->route('ticket');

            if (! TicketTransitions::allows($ticket->status, $to)) {
                $fail('Questo passaggio di stato non è ammesso.');
            }
        };
    }
}
