<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Private files, such as business permits. Never public: they are only shown through
 * the app, to the owner and to admins (see AccountController::permit).
 *
 * - On your computer (PHOTOS_DRIVER=local): storage/app/private/permits (not reachable from the web).
 * - On Render (PHOTOS_DRIVER=supabase): a PRIVATE Supabase Storage bucket (SUPABASE_PRIVATE_BUCKET,
 *   default "documents"), read with the secret key.
 */
class PrivateFiles
{
    private const LOCAL_FOLDER = 'permits';

    private static function onSupabase(): bool
    {
        return config('services.photos.driver') === 'supabase';
    }

    /** Saves an uploaded file under $name. Throws if Supabase refuses it. */
    public static function store(UploadedFile $file, string $name): void
    {
        if (! self::onSupabase()) {
            Storage::disk('local')->putFileAs(self::LOCAL_FOLDER, $file, $name);

            return;
        }

        $response = self::api()
            ->withHeaders(['x-upsert' => 'false'])
            ->withBody(file_get_contents($file->getRealPath()), $file->getMimeType() ?: 'application/octet-stream')
            ->post(self::objectUrl($name));

        if ($response->failed()) {
            throw new RuntimeException('Upload to the private Supabase bucket failed (HTTP ' . $response->status() . ').');
        }
    }

    /** The file's bytes and type, or null if it is missing. */
    public static function get(string $name): ?array
    {
        if (! self::onSupabase()) {
            $path = self::LOCAL_FOLDER . '/' . $name;
            if (! Storage::disk('local')->exists($path)) {
                return null;
            }

            return ['body' => Storage::disk('local')->get($path), 'type' => Storage::disk('local')->mimeType($path) ?: 'application/octet-stream'];
        }

        $response = self::api()->get(self::objectUrl($name));
        if ($response->failed()) {
            return null;
        }

        return ['body' => $response->body(), 'type' => $response->header('Content-Type') ?: 'application/octet-stream'];
    }

    /** Deletes a file. Fine if it is already gone. */
    public static function delete(string $name): void
    {
        if (! self::onSupabase()) {
            Storage::disk('local')->delete(self::LOCAL_FOLDER . '/' . $name);

            return;
        }

        self::api()->delete(self::objectUrl($name));
    }

    private static function objectUrl(string $name): string
    {
        return rtrim((string) config('services.photos.supabase_url'), '/')
            . '/storage/v1/object/' . config('services.photos.private_bucket') . '/' . rawurlencode($name);
    }

    /** HTTP client with the secret key. Old-style keys are JWTs and also go in the Authorization header. */
    private static function api(): \Illuminate\Http\Client\PendingRequest
    {
        $key = (string) config('services.photos.supabase_key');
        $client = Http::timeout(30)->withHeaders(['apikey' => $key]);

        return str_starts_with($key, 'eyJ') ? $client->withToken($key) : $client;
    }
}
