<?php

declare(strict_types=1);

use App\Notifications\PortalLink;
use App\Notifications\TicketReceived;
use App\Notifications\TicketReplied;
use App\Notifications\TicketResolved;
use App\Notifications\UserInvitation;

return [

    /*
     * Classes implementing Scrapkit\NotificationKit\Contracts\Manageable.
     * The notification-kit:sync command upserts their template definitions.
     */
    'manageables' => [
        PortalLink::class,
        TicketReceived::class,
        TicketReplied::class,
        TicketResolved::class,
        UserInvitation::class,
    ],

    'routes' => [
        'prefix' => 'notification-kit/api/v1',
        'middleware' => ['web', 'auth'],
    ],

    'database' => [
        'connection' => null,
        'table_prefix' => 'notification_kit_',
    ],

    /*
     * Queue settings for the job that sends an approved outbox message.
     */
    'queue' => [
        'connection' => null,
        'queue' => null,
        'tries' => 3,
        'backoff' => 60,
    ],

    /*
     * Template lookups are cached at send time. A ttl of 0 disables caching.
     */
    'cache' => [
        'store' => null,
        'ttl' => 300,
    ],

    /*
     * What to do when a placeholder has no value: 'empty' renders nothing,
     * 'keep' leaves the raw {{ placeholder }} in place. Always logged.
     */
    'placeholders' => [
        'missing' => 'empty',
    ],

    /*
     * Attribute shown for requested_by / decided_by / edited_by users.
     */
    'users' => [
        'display_attribute' => 'name',
    ],

];
