<?php

namespace App\Notifications;

use App\Models\Ticket;
use App\Models\User;
use App\Notifications\Concerns\LinksToTicket;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Scrapkit\NotificationKit\Contracts\Manageable;
use Scrapkit\NotificationKit\Domain\Templates\DataTransferObjects\PlaceholderDefinition;
use Scrapkit\NotificationKit\Domain\Templates\DataTransferObjects\TemplateDefinition;
use Scrapkit\NotificationKit\Domain\Templates\Enums\TemplateType;
use Scrapkit\NotificationKit\Notifications\Concerns\HasManagedContent;

/**
 * Told to the requester when the team marks their ticket resolved (roadmap
 * step 37) — the same fact `App\Tickets\Events\TicketResolved` already
 * carries for the audit trail, and the one the resolution time of step 46 is
 * measured against (§4: a `risolto` ticket auto-closes after 7 days, so this
 * is also the requester's one chance to say it is not solved before it does).
 *
 * Queued, like every notification of this application (§5).
 */
class TicketResolved extends Notification implements Manageable, ShouldQueue
{
    use HasManagedContent, LinksToTicket, Queueable;

    public function __construct(public Ticket $ticket) {}

    /**
     * The editable content behind this notification.
     */
    public static function template(): TemplateDefinition
    {
        return new TemplateDefinition(
            key: 'tickets.resolved',
            type: TemplateType::Email,
            name: 'Ticket resolved',
            description: 'Sent to the requester when their ticket is marked resolved.',
            defaultSubject: '[{{ ticket.reference }}] {{ ticket.subject }}',
            defaultBody: <<<'MARKDOWN'
                Hi {{ requester.name }},

                Your request **{{ ticket.reference }}** has been marked as resolved.

                [Review your request]({{ action.url }})

                If everything is fine, there is nothing else to do. If it is not solved, reply and we will pick it back up.
                MARKDOWN,
            placeholders: [
                new PlaceholderDefinition('requester.name', 'Name of whoever asked for help', 'Mario Rossi'),
                new PlaceholderDefinition('ticket.reference', 'Public reference of the ticket', 'DSK-000123'),
                new PlaceholderDefinition('ticket.subject', 'Subject of the request', 'La stampante non risponde'),
                new PlaceholderDefinition('app.name', 'Application name', config('app.name')),
                new PlaceholderDefinition('action.url', 'Link that opens the ticket', 'https://example.test/assistenza/ticket/1?signature=...'),
            ],
            sampleData: [
                'requester' => ['name' => 'Mario Rossi'],
                'ticket' => ['reference' => 'DSK-000123', 'subject' => 'La stampante non risponde'],
                'app' => ['name' => config('app.name')],
                'action' => ['url' => 'https://example.test/assistenza/ticket/1?signature=...'],
            ],
        );
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(User $notifiable): MailMessage
    {
        $content = $this->renderManaged([
            'requester' => ['name' => $notifiable->name],
            'ticket' => [
                'reference' => $this->ticket->reference,
                'subject' => $this->ticket->subject,
            ],
            'app' => ['name' => config('app.name')],
            'action' => ['url' => self::linkTo($this->ticket)],
        ]);

        return (new MailMessage)
            ->subject($content->subject ?? sprintf('[%s] %s', $this->ticket->reference, $this->ticket->subject))
            ->markdown('notification-kit::mail.managed', ['renderedBody' => $content->bodyHtml]);
    }
}
