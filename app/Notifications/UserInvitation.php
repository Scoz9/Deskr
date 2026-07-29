<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Scrapkit\NotificationKit\Contracts\Manageable;
use Scrapkit\NotificationKit\Domain\Templates\DataTransferObjects\PlaceholderDefinition;
use Scrapkit\NotificationKit\Domain\Templates\DataTransferObjects\TemplateDefinition;
use Scrapkit\NotificationKit\Domain\Templates\Enums\TemplateType;
use Scrapkit\NotificationKit\Notifications\Concerns\HasManagedContent;

class UserInvitation extends Notification implements Manageable
{
    use HasManagedContent;

    public function __construct(public string $token) {}

    /**
     * The editable content behind this invitation.
     */
    public static function template(): TemplateDefinition
    {
        return new TemplateDefinition(
            key: 'users.invitation',
            type: TemplateType::Email,
            name: 'User invitation',
            description: 'Sent when an administrator creates an account, so the user can choose their own password.',
            defaultSubject: 'Your {{ app.name }} account',
            defaultBody: <<<'MARKDOWN'
                Hi {{ user.name }},

                An account has been created for you on {{ app.name }}.

                [Set your password]({{ action.url }})

                If the link has expired, you can request a new one from the "Forgot your password?" page.
                MARKDOWN,
            placeholders: [
                new PlaceholderDefinition('user.name', 'Name of the invited user', 'Mario Rossi'),
                new PlaceholderDefinition('app.name', 'Application name', config('app.name')),
                new PlaceholderDefinition('action.url', 'Link that opens the password form', 'https://example.test/reset-password/token'),
            ],
            sampleData: [
                'user' => ['name' => 'Mario Rossi'],
                'app' => ['name' => config('app.name')],
                'action' => ['url' => 'https://example.test/reset-password/token'],
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
            'user' => ['name' => $notifiable->name],
            'app' => ['name' => config('app.name')],
            'action' => ['url' => $this->resetUrl($notifiable)],
        ]);

        return (new MailMessage)
            ->subject($content->subject ?? __('Your :app account', ['app' => config('app.name')]))
            ->markdown('notification-kit::mail.managed', ['renderedBody' => $content->bodyHtml]);
    }

    private function resetUrl(User $notifiable): string
    {
        return url(route('password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ], false));
    }
}
