<?php

namespace App\Http\Requests\Support;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * An email Postmark has parsed and is handing off to the webhook.
 *
 * There is no session and no signature here — the caller is Postmark, not a
 * browser — so the credential is the HTTP Basic Auth Postmark's inbound
 * webhook is configured to send. Refused whenever either side of it is
 * blank: a server nobody has configured must not accept mail from anybody
 * who finds the URL.
 */
class PostmarkInboundRequest extends FormRequest
{
    public function authorize(): bool
    {
        $username = (string) config('services.postmark.inbound.username');
        $password = (string) config('services.postmark.inbound.password');

        if ($username === '' || $password === '') {
            return false;
        }

        return hash_equals($username, (string) $this->getUser())
            && hash_equals($password, (string) $this->getPassword());
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * Only the sender's address is required: it is the one thing a ticket
     * cannot exist without. A missing subject or body is not a reason to
     * lose the request — the adapter fills in something rather than refuse
     * it (§3: an unclassified request still lands, it does not bounce).
     *
     * @return array<string, array<int, ValidationRule|string>>
     */
    public function rules(): array
    {
        return [
            'FromFull' => ['required', 'array'],
            'FromFull.Email' => ['required', 'email'],
            'FromFull.Name' => ['nullable', 'string'],
            'Subject' => ['nullable', 'string'],
            'TextBody' => ['nullable', 'string'],
        ];
    }
}
