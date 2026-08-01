<?php

namespace App\Actions\Tickets;

/**
 * An email a provider has parsed and is handing off, in the shape every
 * inbound provider has to reduce itself to — the same role {@see NewTicket}
 * plays for every channel, one level up: this is what the email channel's
 * own adapter builds before it ever touches the domain.
 */
class InboundEmail
{
    public function __construct(
        public readonly string $fromEmail,
        public readonly string $fromName,
        public readonly string $subject,
        public readonly string $body,
        /**
         * The `Message-ID` header of this email, when the provider carried
         * one — what threading matches a later reply's `In-Reply-To` against,
         * and what tells a redelivered webhook apart from a new message.
         */
        public readonly ?string $externalMessageId = null,
        /**
         * The `In-Reply-To` header: the one message this email is a direct
         * reply to.
         */
        public readonly ?string $inReplyTo = null,
        /**
         * The `References` header: every message in the thread this email
         * belongs to, oldest first. Checked when `In-Reply-To` alone does not
         * resolve to a ticket — a client that trims the thread still leaves
         * the chain here.
         *
         * @var list<string>
         */
        public readonly array $references = [],
        /**
         * Whether this email carries `Auto-Submitted: <anything but "no">"` —
         * the header RFC 3834 asks autoresponders to set, and the one loop
         * protection can trust without guessing (§5).
         */
        public readonly bool $autoSubmitted = false,
    ) {}
}
