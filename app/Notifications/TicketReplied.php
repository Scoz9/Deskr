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
 * Told to the requester when an operator answers publicly from the console
 * (roadmap step 37). Without it the portal is a place nobody comes back to,
 * and the `in attesa → risposta` cycle §4 counts on never starts — an
 * internal note never reaches here, the same reason it never reaches the
 * portal thread at all (§3).
 *
 * Queued, like every notification of this application (§5).
 */
class TicketReplied extends Notification implements Manageable, ShouldQueue
{
    use HasManagedContent, LinksToTicket, Queueable;

    public function __construct(public Ticket $ticket) {}

    /**
     * The editable content behind this notification.
     */
    public static function template(): TemplateDefinition
    {
        return new TemplateDefinition(
            key: 'tickets.replied',
            type: TemplateType::Email,
            name: 'Ticket replied',
            description: 'Sent to the requester when an operator posts a public reply on their ticket.',
            defaultSubject: '[{{ ticket.reference }}] {{ ticket.subject }}',
            defaultBody: <<<'MARKDOWN'
                Hi {{ requester.name }},

                There is a new reply on your request **{{ ticket.reference }}**.

                [Read the reply]({{ action.url }})
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
