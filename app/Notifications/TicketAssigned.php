<?php

namespace App\Notifications;

use App\Models\Ticket;
use App\Models\User;
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
 * Told to the agent a ticket lands with, out of the pool (roadmap step 38).
 *
 * Unlike every notification of step 37, the recipient here is a console
 * account and not a portal visitor: the link is the ordinary authenticated
 * route to the detail of step 34, not a signed one — an agent logs in with a
 * password, and has no need of the key a requester's link is (§3).
 *
 * Queued, like every notification of this application (§5).
 */
class TicketAssigned extends Notification implements Manageable, ShouldQueue
{
    use HasManagedContent, Queueable;

    public function __construct(public Ticket $ticket) {}

    /**
     * The editable content behind this notification.
     */
    public static function template(): TemplateDefinition
    {
        return new TemplateDefinition(
            key: 'tickets.assigned',
            type: TemplateType::Email,
            name: 'Ticket assigned',
            description: 'Sent to the agent a ticket is assigned to.',
            defaultSubject: '[{{ ticket.reference }}] {{ ticket.subject }}',
            defaultBody: <<<'MARKDOWN'
                Hi {{ assignee.name }},

                **{{ ticket.reference }}** — {{ ticket.subject }} — is now yours.

                [Open the ticket]({{ action.url }})
                MARKDOWN,
            placeholders: [
                new PlaceholderDefinition('assignee.name', 'Name of the agent the ticket was assigned to', 'Luca Bianchi'),
                new PlaceholderDefinition('ticket.reference', 'Public reference of the ticket', 'DSK-000123'),
                new PlaceholderDefinition('ticket.subject', 'Subject of the request', 'La stampante non risponde'),
                new PlaceholderDefinition('app.name', 'Application name', config('app.name')),
                new PlaceholderDefinition('action.url', 'Link that opens the ticket in the console', 'https://example.test/tickets/1'),
            ],
            sampleData: [
                'assignee' => ['name' => 'Luca Bianchi'],
                'ticket' => ['reference' => 'DSK-000123', 'subject' => 'La stampante non risponde'],
                'app' => ['name' => config('app.name')],
                'action' => ['url' => 'https://example.test/tickets/1'],
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
            'assignee' => ['name' => $notifiable->name],
            'ticket' => [
                'reference' => $this->ticket->reference,
                'subject' => $this->ticket->subject,
            ],
            'app' => ['name' => config('app.name')],
            'action' => ['url' => route('tickets.show', $this->ticket)],
        ]);

        return (new MailMessage)
            ->subject($content->subject ?? sprintf('[%s] %s', $this->ticket->reference, $this->ticket->subject))
            ->markdown('notification-kit::mail.managed', ['renderedBody' => $content->bodyHtml]);
    }
}
