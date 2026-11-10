<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Where room photos live.
 *
 * - On your computer (PHOTOS_DRIVER=local, the default): public/images, exactly like the old app.
 * - On Render (PHOTOS_DRIVER=supabase): a public Supabase Storage bucket, because Render's own
 *   disk is wiped on every deploy and restart. Talks to Supabase's Storage API over HTTPS,
 *   so no extra package is needed. Settings: config/services.php → 'photos'.
 *
 * The database only stores the file name, so switching places never touches the data.
 */
class RoomPhotos
{
    private static function onSupabase(): bool
    {
        return config('services.photos.driver') === 'supabase';
    }

    /** Web address of one photo. */
    public static function url(string $filename): string
    {
        return self::baseUrl() . '/' . rawurlencode($filename);
    }

    /** Web address of the folder (the page scripts add "/<file name>" themselves). */
    public static function baseUrl(): string
    {
        if (! self::onSupabase()) {
            return asset('images');
        }

        return rtrim((string) config('services.photos.supabase_url'), '/')
            . '/storage/v1/object/public/' . config('services.photos.bucket');
    }

    /** Saves an uploaded photo under $name. Throws if Supabase refuses it. */
    public static function store(UploadedFile $file, string $name): void
    {
        if (! self::onSupabase()) {
            $file->move(public_path('images'), $name);

            return;
        }

        $response = self::api()
            ->withHeaders(['x-upsert' => 'false'])
            ->withBody(file_get_contents($file->getRealPath()), $file->getMimeType() ?: 'application/octet-stream')
            ->post(self::objectUrl($name));

        if ($response->failed()) {
            throw new RuntimeException('Photo upload to Supabase Storage failed (HTTP ' . $response->status() . ').');
        }
    }

    /** Deletes a photo. Fine if it is already gone. Returns true if it was deleted. */
    public static function delete(string $filename): bool
    {
        if (! self::onSupabase()) {
            return File::delete(public_path('images/' . $filename));
        }

        return self::api()->delete(self::objectUrl($filename))->successful();
    }

    private static function objectUrl(string $name): string
    {
        return rtrim((string) config('services.photos.supabase_url'), '/')
            . '/storage/v1/object/' . config('services.photos.bucket') . '/' . rawurlencode($name);
    }

    /** HTTP client with the secret key. Old-style keys are JWTs and also go in the Authorization header. */
    private static function api(): \Illuminate\Http\Client\PendingRequest
    {
        $key = (string) config('services.photos.supabase_key');
        $client = Http::timeout(30)->withHeaders(['apikey' => $key]);

        return str_starts_with($key, 'eyJ') ? $client->withToken($key) : $client;
    }
}