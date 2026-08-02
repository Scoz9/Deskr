<?php

namespace App\Http\Requests\Tickets;

use App\Models\Attachment;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * A message an operator appends to a ticket from the console — the public
 * reply the requester reads, or the note the team keeps to itself.
 */
class TicketReplyRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, ValidationRule|string>>
     */
    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:5000'],
            'is_internal' => ['sometimes', 'boolean'],
            'attachments' => ['nullable', 'array', 'max:'.Attachment::MAX_PER_MESSAGE],
            // `mimetypes` and not `mimes`: the first asks what the file is, the
            // second believes the extension the sender typed.
            'attachments.*' => [
                'file',
                'max:'.Attachment::MAX_KILOBYTES,
                'mimetypes:'.implode(',', Attachment::ALLOWED_MIME_TYPES),
            ],
        ];
    }
}
