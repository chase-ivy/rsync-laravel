<?php

namespace Tests\Feature;

use App\Models\Room;
use App\Models\RoomImage;
use App\Models\User;
use App\Support\RoomPhotos;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Part H1 — room photos on Supabase Storage (PHOTOS_DRIVER=supabase, used on Render).
 * No real network: Laravel's Http::fake() stands in for Supabase.
 */
class RoomPhotosSupabaseTest extends TestCase
{
    use RefreshDatabase;

    private const BASE = 'https://abcd1234.supabase.co';

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.photos.driver' => 'supabase',
            'services.photos.supabase_url' => self::BASE,
            'services.photos.supabase_key' => 'sb_secret_test',
            'services.photos.bucket' => 'room-photos',
        ]);
    }

    private function landlord(): User
    {
        $u = User::create(['name' => 'Mae', 'age' => 30, 'email' => 'mae@gmail.com', 'password' => Hash::make('x'), 'role' => 'landlord']);
        $u->markEmailAsVerified();

        return $u;
    }

    public function test_urls_point_to_the_public_bucket(): void
    {
        $this->assertSame(self::BASE . '/storage/v1/object/public/room-photos', RoomPhotos::baseUrl());
        $this->assertSame(self::BASE . '/storage/v1/object/public/room-photos/abc_room%201.jpg', RoomPhotos::url('abc_room 1.jpg'));
    }

    public function test_pages_show_supabase_urls(): void
    {
        $room = Room::create(['room_name' => 'Room 201', 'price' => 4500, 'max_occupants' => 2]);
        RoomImage::create(['room_id' => $room->room_id, 'filename' => 'cover.jpg', 'sort_order' => 0]);

        $this->get('/rooms')->assertSee(self::BASE . '/storage/v1/object/public/room-photos/cover.jpg', false);
        $this->get('/rooms/' . $room->room_id)->assertSee(self::BASE . '/storage/v1/object/public/room-photos/cover.jpg', false);
        $this->get('/')->assertSee('data-images-url="' . self::BASE . '/storage/v1/object/public/room-photos"', false);
    }

    public function test_upload_sends_the_file_with_the_secret_key(): void
    {
        Http::fake([self::BASE . '/storage/v1/object/*' => Http::response(['Key' => 'ok'], 200)]);

        $this->actingAs($this->landlord())->post('/landlord/rooms', [
            'room_name' => 'Room 201', 'price' => 4500, 'max_occupants' => 2, 'description' => 'x',
            'images' => [UploadedFile::fake()->image('cover photo.jpg')],
        ])->assertRedirect(route('landlord.dashboard'));

        $img = RoomImage::firstOrFail();
        $this->assertStringEndsWith('_cover_photo.jpg', $img->filename);
        Http::assertSent(fn (HttpRequest $r) => $r->method() === 'POST'
            && $r->url() === self::BASE . '/storage/v1/object/room-photos/' . $img->filename
            && $r->hasHeader('apikey', 'sb_secret_test')
            && ! $r->hasHeader('Authorization')
            && str_starts_with($r->header('Content-Type')[0], 'image/'));
    }

    public function test_old_style_jwt_key_is_also_sent_as_bearer(): void
    {
        config(['services.photos.supabase_key' => 'eyJhbGciOi.test.key']);
        Http::fake([self::BASE . '/*' => Http::response([], 200)]);

        RoomPhotos::delete('a.jpg');

        Http::assertSent(fn (HttpRequest $r) => $r->method() === 'DELETE'
            && $r->url() === self::BASE . '/storage/v1/object/room-photos/a.jpg'
            && $r->hasHeader('Authorization', 'Bearer eyJhbGciOi.test.key'));
    }

        public function test_failed_upload_does_not_add_a_room_without_photos(): void
    {
        Http::fake([self::BASE . '/*' => Http::response(['error' => 'Unauthorized'], 403)]);

        $this->actingAs($this->landlord())->post('/landlord/rooms', [
            'room_name' => 'Room 201', 'price' => 4500, 'max_occupants' => 2, 'description' => 'x',
            'images' => [UploadedFile::fake()->image('a.jpg')],
        ])->assertRedirect(route('landlord.dashboard'));

                $this->assertSame(0, Room::count());   // every photo failed, so the room was not kept
        $this->assertSame(0, RoomImage::count());
    }

    public function test_deleting_a_photo_removes_it_from_the_bucket(): void
    {
        Http::fake([self::BASE . '/*' => Http::response([], 200)]);
        $me = $this->landlord();
        $room = Room::create(['owner_id' => $me->user_id, 'room_name' => 'Room 201', 'price' => 4500, 'max_occupants' => 2]);
                $img = RoomImage::create(['room_id' => $room->room_id, 'filename' => 'a.jpg', 'sort_order' => 0]);
        RoomImage::create(['room_id' => $room->room_id, 'filename' => 'b.jpg', 'sort_order' => 1]);   // a room keeps at least one photo

        $this->actingAs($me)->post('/landlord/rooms/images/delete', ['image_id' => $img->image_id, 'room_id' => $room->room_id]);

        $this->assertNull($img->fresh());
        Http::assertSent(fn (HttpRequest $r) => $r->method() === 'DELETE'
            && $r->url() === self::BASE . '/storage/v1/object/room-photos/a.jpg');
    }
}