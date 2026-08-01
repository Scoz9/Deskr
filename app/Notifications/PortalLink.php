<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;
use Scrapkit\NotificationKit\Contracts\Manageable;
use Scrapkit\NotificationKit\Domain\Templates\DataTransferObjects\PlaceholderDefinition;
use Scrapkit\NotificationKit\Domain\Templates\DataTransferObjects\TemplateDefinition;
use Scrapkit\NotificationKit\Domain\Templates\Enums\TemplateType;
use Scrapkit\NotificationKit\Notifications\Concerns\HasManagedContent;

/**
 * The way into the portal, and the only one: a requester never registers and
 * has no password to type (§3), so the link in this email is the credential.
 *
 * Queued like every notification of this application (§5).
 */
class PortalLink extends Notification implements Manageable, ShouldQueue
{
    use HasManagedContent, Queueable;

    /**
     * How long the link stays good, in days (§5) — the same rule the link in
     * {@see TicketReceived} follows. It is reusable until then, and when it has
     * run out the portal hands out a fresh one instead of refusing.
     */
    public const LINK_DAYS = 7;

    /**
     * The link that opens the portal for this person.
     *
     * The signature covers the id, so a link cannot be edited into somebody
     * else's tickets.
     */
    public static function linkTo(User $requester): string
    {
        return URL::temporarySignedRoute(
            'portal.enter',
            now()->addDays(self::LINK_DAYS),
            ['user' => $requester->getKey()],
        );
    }

    /**
     * The editable content behind this link.
     */
    public static function template(): TemplateDefinition
    {
        return new TemplateDefinition(
            key: 'portal.link',
            type: TemplateType::Email,
            name: 'Portal link',
            description: 'Sent when a requester asks to see their own requests, with the link that opens the portal.',
            defaultSubject: 'Your requests on {{ app.name }}',
            defaultBody: <<<'MARKDOWN'
                Hi {{ requester.name }},

                Here is the way to your requests.

                [Open my requests]({{ action.url }})

                The link works for {{ link.days }} days, as many times as you need. When it stops working, ask for a new one from the same page.
                MARKDOWN,
            placeholders: [
                new PlaceholderDefinition('requester.name', 'Name of whoever asked', 'Mario Rossi'),
                new PlaceholderDefinition('app.name', 'Application name', config('app.name')),
                new PlaceholderDefinition('link.days', 'Days the link stays good', (string) self::LINK_DAYS),
                new PlaceholderDefinition('action.url', 'Link that opens the portal', 'https://example.test/portale/entra/1?signature=...'),
            ],
            sampleData: [
                'requester' => ['name' => 'Mario Rossi'],
                'app' => ['name' => config('app.name')],
                'link' => ['days' => (string) self::LINK_DAYS],
                'action' => ['url' => 'https://example.test/portale/entra/1?signature=...'],
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
            'app' => ['name' => config('app.name')],
            'link' => ['days' => (string) self::LINK_DAYS],
            'action' => ['url' => self::linkTo($notifiable)],
        ]);

        return (new MailMessage)
            ->subject($content->subject ?? __('Your requests on :app', ['app' => config('app.name')]))
            ->markdown('notification-kit::mail.managed', ['renderedBody' => $content->bodyHtml]);
    }
}
