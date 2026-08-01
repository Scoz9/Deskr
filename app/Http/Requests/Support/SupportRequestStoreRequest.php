<?php

namespace App\Http\Requests\Support;

use App\Enums\TicketStatus;
use App\Http\Controllers\SupportRequestController;
use App\Models\Category;
use App\Models\Ticket;
use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * What the public form is allowed to say.
 *
 * The browser checks the same fields before anybody waits for the server, and
 * that check is a courtesy: this one is the defence. Everything here arrives
 * from the open internet, the category id included — and that id decides which
 * team a ticket lands on.
 */
class SupportRequestStoreRequest extends FormRequest
{
    /**
     * Anybody may ask for help: that is the whole point of the intake (§3).
     * What guards this door is the rate limit on the route, the honeypot and
     * the cap below, not an ability.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * The address is deliberately not `unique`: a requester who writes twice is
     * the normal case, and the second request lands on the account the first
     * one created.
     *
     * @return array<string, array<int, ValidationRule|Closure|string>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', $this->openTicketsRule()],
            'categoryId' => ['required', 'integer', Rule::exists(Category::class, 'id')],
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:5000'],
            'website' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * Refuse an address that already holds as many open tickets as the helpdesk
     * is willing to keep for one person (§5).
     *
     * Said out loud, unlike the honeypot: somebody with that many open requests
     * is not a script, and leaving them to guess why nothing happened is the
     * one sure way to make them write again.
     */
    private function openTicketsRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if (! is_string($value)) {
                return;
            }

            $requester = User::query()->where('email', $value)->first();

            if ($requester === null) {
                return;
            }

            $openTickets = Ticket::query()
                ->where('requester_id', $requester->getKey())
                ->whereNotIn('status', [TicketStatus::Chiuso->value, TicketStatus::Annullato->value])
                ->count();

            if ($openTickets >= SupportRequestController::OPEN_TICKETS_PER_EMAIL) {
                $fail(__('support.open_tickets_reached'));
            }
        };
    }
}
