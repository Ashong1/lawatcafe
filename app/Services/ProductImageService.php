<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Product photos for the register and the guest menu. The browser shrinks
 * them before upload (this server has no GD to do it), so files arrive small
 * and are stored as they are.
 */
class ProductImageService
{
    public const DIR = 'products';

    public function replace(Product $product, ?UploadedFile $file, bool $remove = false): void
    {
        if (! $file && ! $remove) {
            return;
        }

        $old = $product->image_path;

        // A new name on every upload, so a cached old photo is never shown for the new one.
        $product->image_path = $file
            ? $file->storeAs(self::DIR, Str::uuid().'.'.$file->guessExtension(), 'public')
            : null;
        $product->save();

        $this->delete($old);
    }

    public function delete(?string $path): void
    {
        if ($path && str_starts_with($path, self::DIR.'/')) {
            Storage::disk('public')->delete($path);
        }
    }
}
