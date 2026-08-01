<?php

namespace App\Http\Controllers;

use App\Models\Attachment;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * The only way to the bytes of an attachment.
 *
 * The disk is private and has `serve` off (§8), so there is no address to guess
 * and no second door: whoever wants a file comes through here, with a signature
 * that says the link was handed to them by the application.
 */
class AttachmentController extends Controller
{
    /**
     * The signature is what authorizes this call, so there is no resource
     * ability to check. It is also why the route is not open to the console
     * yet: the policy of step 21 joins the signature when the portal (step 26)
     * and the console (step 34) start linking files, and neither exists.
     */
    protected static bool $authorizesResources = false;

    /**
     * Hand back the file under the name the sender gave it.
     *
     * The name is written on the response and never used to find the file: what
     * the sender typed decides what the download is called, nothing more.
     */
    public function show(Attachment $attachment): StreamedResponse
    {
        try {
            return Storage::disk($attachment->disk)
                ->download($attachment->path, $attachment->original_name);
        } catch (Throwable) {
            // The disk throws on a missing file (§8). A row whose file is gone
            // is a broken link, and a broken link is a 404 — never an empty
            // download that looks like the real thing.
            abort(404);
        }
    }
}
