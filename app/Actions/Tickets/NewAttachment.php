<?php

namespace App\Actions\Tickets;

/**
 * A file already written to disk, waiting for the row that points at it.
 *
 * The bytes are stored by whoever received them — the form now, the inbound
 * email of step 30 later — and only the description of the file travels here:
 * the intake has no business knowing what an HTTP upload is, and an email
 * attachment is not one.
 *
 * The name the sender chose travels as data and never as a path: it is what the
 * download is called, not where the file lives.
 */
class NewAttachment
{
    public function __construct(
        public readonly string $disk,
        public readonly string $path,
        public readonly string $originalName,
        public readonly string $mimeType,
        public readonly int $size,
    ) {}
}
