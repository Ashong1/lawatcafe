<?php

namespace App\Services\Agent;

use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * A photo attached to a staff/admin/super_admin Barista AI message.
 *
 * Arrives as a data URL the browser has already downscaled (longest side
 * 1280px, JPEG), which keeps it under nginx's default 1MB body limit and
 * cheap for the model. It is sent to the model for this one turn only and
 * never stored: conversation history keeps a text marker, not the image.
 *
 * Not accepted on the guest portal endpoint — anonymous uploads over public
 * Wi-Fi are a different risk entirely, and that endpoint doesn't read this
 * field at all.
 */
class ChatImage
{
    /** Base64 length ceiling: ~1MB of image, above what the client produces. */
    public const MAX_BASE64_LENGTH = 1_400_000;

    private const ALLOWED = ['image/jpeg', 'image/png', 'image/webp'];

    /** Stored in conversation history in place of the image itself. */
    public const HISTORY_MARKER = '📷 [photo attached]';

    public static function rules(): array
    {
        return [
            'message' => 'required_without:image|nullable|string|max:1000',
            'image' => 'nullable|string|max:'.self::MAX_BASE64_LENGTH,
        ];
    }

    /**
     * The request's photo as a verified data URL, or null if none was sent.
     * Checks the bytes really are an image of an allowed type — the declared
     * prefix alone is just a claim.
     */
    public static function fromRequest(Request $request): ?string
    {
        $dataUrl = $request->input('image');
        if (! $dataUrl) {
            return null;
        }

        if (! preg_match('#^data:(image/(?:jpeg|png|webp));base64,#', $dataUrl, $m)) {
            throw ValidationException::withMessages(['image' => 'The photo must be a JPEG, PNG or WebP image.']);
        }

        $bytes = base64_decode(substr($dataUrl, strlen($m[0])), true);
        $info = $bytes !== false ? @getimagesizefromstring($bytes) : false;

        if (! $info || ! in_array($info['mime'], self::ALLOWED, true)) {
            throw ValidationException::withMessages(['image' => 'That file could not be read as a photo.']);
        }

        return $dataUrl;
    }

    /**
     * The user turn's content: plain text, or text + image parts in the
     * OpenAI/OpenRouter multimodal format when a photo is attached.
     */
    public static function userContent(string $text, ?string $dataUrl): string|array
    {
        if (! $dataUrl) {
            return $text;
        }

        return [
            ['type' => 'text', 'text' => $text !== '' ? $text : 'Please look at this photo and help me with it.'],
            ['type' => 'image_url', 'image_url' => ['url' => $dataUrl]],
        ];
    }

    /** Appended to the system turn when a photo is attached. */
    public static function systemNote(?string $dataUrl): string
    {
        return $dataUrl
            ? "\n\nPHOTO ATTACHED: the user's latest message includes a photo — typically a receipt, delivery slip, stock shelf, product, or a screen showing an error. Read it carefully and use what you see to complete their request, calling your tools where the task needs an action. If part of it is unreadable, say which part instead of guessing numbers."
            : '';
    }

    /** What conversation history records for this turn. */
    public static function historyText(string $text, ?string $dataUrl): string
    {
        return $dataUrl ? trim(self::HISTORY_MARKER.' '.$text) : $text;
    }
}
