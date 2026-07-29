<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\AvatarUpdateRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Scrapkit\FileProcessingKit\Facades\FileProcessingKit;

class AvatarController extends Controller
{
    /**
     * Settings act on the authenticated user only: there is no Profile
     * model or policy to authorize against.
     */
    protected static bool $authorizesResources = false;

    /**
     * The bounding box every avatar is scaled down to fit within.
     */
    private const MAX_WIDTH = 512;

    private const MAX_HEIGHT = 512;

    /**
     * Replace the authenticated user's avatar.
     */
    public function update(AvatarUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();
        $upload = $request->file('avatar');

        if (! $upload instanceof UploadedFile) {
            throw ValidationException::withMessages([
                'avatar' => __('The avatar failed to upload.'),
            ]);
        }

        $pending = FileProcessingKit::from($upload);

        /**
         * The validation rules already reject non-images by extension and
         * MIME type; detecting the category from the contents guards against
         * a payload that only claims to be one.
         */
        if (! $pending->category()->isImage()) {
            throw ValidationException::withMessages([
                'avatar' => __('The avatar contents are not a valid image.'),
            ]);
        }

        $path = $pending
            ->scale(self::MAX_WIDTH, self::MAX_HEIGHT)
            ->store(User::AVATAR_DIRECTORY, User::AVATAR_DISK);

        $user->deleteAvatarFile();

        $user->avatar_path = $path;
        $user->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Avatar updated.')]);

        return to_route('profile.edit');
    }

    /**
     * Remove the authenticated user's avatar.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $user = $request->user();

        $user->deleteAvatarFile();

        $user->avatar_path = null;
        $user->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Avatar removed.')]);

        return to_route('profile.edit');
    }
}
