<?php

namespace App\Http\Controllers;

use App\Models\Attachment;
use App\Models\Ticket;
use App\Models\TicketMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The ticket as whoever asked for help sees it: read only, reached by the
 * signed link the confirmation email carries.
 *
 * The signature is the key, not a session — a requester never registers and
 * never logs in (§3). What this page shows is deliberately less than what the
 * console shows: the state of the request and the conversation, and nothing
 * about how the helpdesk is organised behind it.
 */
class SupportTicketController extends Controller
{
    /**
     * The signature on the link authorizes this call, so there is no ability to
     * check and nobody to check it against. The policy of step 21 joins in when
     * the portal of step 26 gives a requester more than one ticket to reach.
     */
    protected static bool $authorizesResources = false;

    /**
     * Show the request and its thread.
     *
     * Two ways in and no third: the signature on the link the confirmation
     * email carries, or the portal session of whoever opened the request. A
     * requester never logs in with a password (§3), so neither one alone is
     * "the" credential — and being logged in as somebody else is worth nothing
     * here, which is the one filter between two customers.
     */
    public function show(Request $request, Ticket $ticket): Response
    {
        abort_unless(
            $request->hasValidSignature()
                || $request->user()?->getKey() === $ticket->requester_id,
            403,
        );

        $ticket->load(['messages.author:id,name', 'messages.attachments']);

        return Inertia::render('support/ticket', [
            'ticket' => [
                'reference' => $ticket->reference,
                'subject' => $ticket->subject,
                'status' => $ticket->status->value,
                'openedAt' => $ticket->created_at?->toIso8601String(),
                // Neither the assignee nor the team nor the category travel
                // here: who is working on the request, and how the helpdesk
                // splits its work, is none of the requester's business.
                'messages' => $ticket->messages
                    ->reject(fn (TicketMessage $message): bool => $message->is_internal)
                    ->values()
                    ->map(fn (TicketMessage $message): array => [
                        'id' => $message->id,
                        'body' => $message->body,
                        'author' => $message->author->name,
                        'writtenAt' => $message->created_at?->toIso8601String(),
                        'attachments' => $message->attachments
                            ->map(fn (Attachment $attachment): array => [
                                'name' => $attachment->original_name,
                                'url' => URL::signedRoute('attachments.show', ['attachment' => $attachment]),
                            ])
                            ->all(),
                    ])
                    ->all(),
            ],
        ]);
    }
}
