<?php

namespace Tests\Feature;

use App\Models\Room;
use App\Models\RoomApplication;
use App\Models\RoomImage;
use App\Models\Tenancy;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Part F — landlord_dashboard.php: rooms, photos, tenants and applicants.
 */
class LandlordDashboardTest extends TestCase
{
    use RefreshDatabase;

    private string $publicDir;

    protected function setUp(): void
    {
        parent::setUp();

        // Uploaded photos go to a temporary folder, never to the real public/images.
        $this->publicDir = sys_get_temp_dir() . '/rsync-test-public-' . uniqid();
        File::makeDirectory($this->publicDir . '/images', 0755, true);
        $this->app->usePublicPath($this->publicDir);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->publicDir);
        parent::tearDown();
    }

    private function user(string $email, string $role, bool $verified = true): User
    {
        $u = User::create(['name' => ucfirst(strtok($email, '@')), 'age' => 30, 'email' => $email, 'password' => Hash::make('x'), 'role' => $role]);
        if ($verified) {
            $u->markEmailAsVerified();
        }

        return $u;
    }

    private function room(User $owner, string $name = 'Room 201', int $max = 2): Room
    {
        return Room::create(['owner_id' => $owner->user_id, 'room_name' => $name, 'price' => 4500, 'description' => 'Nice', 'status' => 'Available', 'max_occupants' => $max]);
    }

    private function roomForm(array $overrides = []): array
    {
        return array_merge(['room_name' => 'Room 305', 'price' => '3900', 'max_occupants' => '2', 'description' => 'Near the school'], $overrides);
    }

    // ---------- Page ----------

    public function test_only_landlords_can_open_it(): void
    {
        $this->get('/landlord/dashboard')->assertRedirect(route('login'));
        $this->actingAs($this->user('t@gmail.com', 'tenant'))->get('/landlord/dashboard')->assertRedirect(route('login'));
        $this->actingAs($this->user('l@gmail.com', 'landlord'))->get('/landlord/dashboard')->assertOk()->assertSee('Room Management');
    }

    public function test_shows_only_my_rooms_with_photos_occupancy_and_tenants(): void
    {
        $me = $this->user('me@gmail.com', 'landlord');
        $other = $this->user('other@gmail.com', 'landlord');
        $tenant = $this->user('kobe@gmail.com', 'tenant');
        $mine = $this->room($me, 'My Room', 2);
        $this->room($other, 'Their Room');
        RoomImage::create(['room_id' => $mine->room_id, 'filename' => 'a.jpg', 'sort_order' => 0]);
        Tenancy::create(['user_id' => $tenant->user_id, 'room_id' => $mine->room_id, 'start_date' => '2026-05-01', 'end_date' => '2026-06-01', 'status' => 'Active']);

        $this->actingAs($me)->get('/landlord/dashboard')
            ->assertSee('My Room')
            ->assertDontSee('Their Room')
            ->assertSee(asset('images/a.jpg'), false)
            ->assertSee('title="Cover"', false)
            ->assertSee('<span>1/2</span>', false)
            ->assertSee('Kobe <span style="opacity:.55">(2026-05-01–2026-06-01)</span>', false)
            ->assertSee('<option value="' . $tenant->user_id . '">Kobe</option>', false);
    }

    public function test_edit_button_carries_room_details_safely(): void
    {
        $me = $this->user('me@gmail.com', 'landlord');
        Room::create(['owner_id' => $me->user_id, 'room_name' => 'It\'s "big"', 'price' => 1, 'description' => "<b>x</b>\nline 2", 'max_occupants' => 1]);

        $this->actingAs($me)->get('/landlord/dashboard')
            ->assertSee('data-name="It&#039;s &quot;big&quot;"', false)
            ->assertDontSee('<b>x</b>', false);
    }

    // ---------- Add / edit / delete room ----------

    public function test_add_room_with_photos(): void
    {
        $me = $this->user('me@gmail.com', 'landlord');

        $this->actingAs($me)->post('/landlord/rooms', $this->roomForm([
            'images' => [UploadedFile::fake()->image('cover photo.jpg'), UploadedFile::fake()->image('two.png')],
        ]))->assertRedirect(route('landlord.dashboard'));

        $room = Room::firstWhere('room_name', 'Room 305');
        $this->assertSame($me->user_id, $room->owner_id);
        $this->assertSame('Available', $room->status);
        $this->assertSame(3900, $room->price);
        $images = $room->images()->get();
        $this->assertSame([0, 1], $images->pluck('sort_order')->all());
        $this->assertMatchesRegularExpression('/^[0-9a-f]{13}_cover_photo\.jpg$/', $images[0]->filename);
        $this->assertFileExists($this->publicDir . '/images/' . $images[0]->filename);

        $this->get('/landlord/dashboard')->assertSee('Room added successfully.')->assertSee('2 image(s) stored successfully.');
        $this->get('/landlord/dashboard')->assertDontSee('Room added successfully.');
    }

    public function test_add_room_rejects_bad_input_and_non_pictures(): void
    {
        $me = $this->user('me@gmail.com', 'landlord');

        $this->actingAs($me)->post('/landlord/rooms', $this->roomForm(['images' => [UploadedFile::fake()->create('evil.php', 1, 'text/x-php')]]));
        $this->get('/landlord/dashboard')->assertSee('<div class="toast err">Only picture files can be uploaded.</div>', false);

        $this->post('/landlord/rooms', $this->roomForm(['room_name' => '']));
        $this->post('/landlord/rooms', $this->roomForm(['price' => '-5']));
        $this->assertSame(0, Room::count());
        $this->assertSame([], File::files($this->publicDir . '/images'));
    }

    public function test_add_room_keeps_at_most_8_photos_and_max_occupants_at_least_1(): void
    {
        $me = $this->user('me@gmail.com', 'landlord');

        $this->actingAs($me)->post('/landlord/rooms', $this->roomForm(['max_occupants' => '0']));
        $this->get('/landlord/dashboard')->assertSee('toast err', false);   // 0 is refused by the form check

        $nine = array_map(fn ($i) => UploadedFile::fake()->image("p$i.jpg"), range(1, 9));
        $this->post('/landlord/rooms', $this->roomForm(['images' => $nine]));
        $this->assertSame(0, Room::count(), 'more than 8 photos is refused');
    }

    public function test_edit_room_updates_and_adds_photos_after_the_existing_ones(): void
    {
        $me = $this->user('me@gmail.com', 'landlord');
        $room = $this->room($me);
        RoomImage::create(['room_id' => $room->room_id, 'filename' => 'old.jpg', 'sort_order' => 0]);

        $this->actingAs($me)->post('/landlord/rooms/update', $this->roomForm([
            'room_id' => $room->room_id, 'room_name' => 'Renamed', 'max_occupants' => '5',
            'images' => [UploadedFile::fake()->image('new.jpg')],
        ]))->assertRedirect(route('landlord.dashboard'));

        $room->refresh();
        $this->assertSame('Renamed', $room->room_name);
        $this->assertSame(5, $room->max_occupants);
        $this->assertSame([0, 1], $room->images()->pluck('sort_order')->all());
        $this->get('/landlord/dashboard')->assertSee('Room updated.')->assertSee('1 new image(s) added.');
    }

    public function test_cannot_edit_or_delete_someone_elses_room(): void
    {
        $me = $this->user('me@gmail.com', 'landlord');
        $theirs = $this->room($this->user('other@gmail.com', 'landlord'), 'Their Room');

        $this->actingAs($me)->post('/landlord/rooms/update', $this->roomForm(['room_id' => $theirs->room_id, 'room_name' => 'Hacked']));
        $this->get('/landlord/dashboard')->assertSee('Failed to update. You can only change your own rooms.');
        $this->assertSame('Their Room', $theirs->fresh()->room_name);

        $this->post('/landlord/rooms/delete', ['room_id' => $theirs->room_id])->assertExactJson(['status' => 'error', 'message' => 'Unauthorized']);
        $this->assertNotNull($theirs->fresh());
    }

    public function test_delete_room(): void
    {
        $me = $this->user('me@gmail.com', 'landlord');
        $room = $this->room($me);
        RoomImage::create(['room_id' => $room->room_id, 'filename' => 'a.jpg']);

        $this->actingAs($me)->post('/landlord/rooms/delete', ['room_id' => $room->room_id])->assertExactJson(['status' => 'deleted']);
        $this->assertNull($room->fresh());
        $this->assertSame(0, RoomImage::count());
    }

    // ---------- Photos ----------

    public function test_list_and_delete_photos_cover_moves_to_the_next(): void
    {
        $me = $this->user('me@gmail.com', 'landlord');
        $room = $this->room($me);
        $a = RoomImage::create(['room_id' => $room->room_id, 'filename' => 'a.jpg', 'sort_order' => 0]);
        RoomImage::create(['room_id' => $room->room_id, 'filename' => 'b.jpg', 'sort_order' => 1]);
        File::put($this->publicDir . '/images/a.jpg', 'x');

        $this->actingAs($me)->post('/landlord/rooms/images', ['room_id' => $room->room_id])
            ->assertJsonCount(2)->assertJsonPath('0.filename', 'a.jpg');

        $this->post('/landlord/rooms/images/delete', ['image_id' => $a->image_id, 'room_id' => $room->room_id])
            ->assertExactJson(['status' => 'deleted', 'remaining' => 1]);

        $this->assertFileDoesNotExist($this->publicDir . '/images/a.jpg');
        $this->assertSame(0, RoomImage::firstWhere('filename', 'b.jpg')->sort_order);
    }

    public function test_cannot_touch_photos_of_someone_elses_room(): void
    {
        $me = $this->user('me@gmail.com', 'landlord');
        $theirs = $this->room($this->user('other@gmail.com', 'landlord'));
        $img = RoomImage::create(['room_id' => $theirs->room_id, 'filename' => 'a.jpg']);

        $this->actingAs($me)->post('/landlord/rooms/images', ['room_id' => $theirs->room_id])->assertExactJson([]);
        $this->post('/landlord/rooms/images/delete', ['image_id' => $img->image_id, 'room_id' => $theirs->room_id])
            ->assertExactJson(['status' => 'error', 'message' => 'Unauthorized']);
        $this->assertNotNull($img->fresh());
    }

    // ---------- Tenants ----------

    public function test_assign_tenant_rules_and_messages(): void
    {
        $me = $this->user('me@gmail.com', 'landlord');
        $kobe = $this->user('kobe@gmail.com', 'tenant');
        $room = $this->room($me, 'Room 201', 1);
        $assign = fn (array $o = []) => $this->actingAs($me)->post('/landlord/tenants/assign', array_merge([
            'room_id' => $room->room_id, 'user_id' => $kobe->user_id, 'start_date' => '2026-05-01', 'end_date' => '2026-06-01',
        ], $o));

        $assign(['start_date' => ''])->assertExactJson(['status' => 'error', 'message' => 'Start and end date are required.']);
        $assign(['end_date' => '2026-05-01'])->assertExactJson(['status' => 'error', 'message' => 'End date must be after start date.']);
        $assign(['start_date' => 'soon'])->assertExactJson(['status' => 'error', 'message' => 'Please enter valid dates.']);
        $assign(['user_id' => $me->user_id])->assertExactJson(['status' => 'error', 'message' => 'Tenant not found']);

        $assign()->assertExactJson(['status' => 'success']);
        $this->assertSame('Occupied', $room->fresh()->status, 'full room becomes Occupied');

        $assign()->assertExactJson(['status' => 'error', 'message' => 'Room is full (1/1 slots)']);
        $this->assertSame(1, Tenancy::count());
    }

    public function test_assign_keeps_room_available_until_full(): void
    {
        $me = $this->user('me@gmail.com', 'landlord');
        $room = $this->room($me, 'Room 201', 2);

        $this->actingAs($me)->post('/landlord/tenants/assign', ['room_id' => $room->room_id, 'user_id' => $this->user('kobe@gmail.com', 'tenant')->user_id, 'start_date' => '2026-05-01', 'end_date' => '2026-06-01'])
            ->assertExactJson(['status' => 'success']);
        $this->assertSame('Available', $room->fresh()->status);
    }

    public function test_remove_tenant_ends_the_contract_today_and_updates_status(): void
    {
        $this->travelTo('2026-10-18 09:00:00');
        $me = $this->user('me@gmail.com', 'landlord');
        $room = $this->room($me, 'Room 201', 2);
        $a = Tenancy::create(['user_id' => $this->user('a@gmail.com', 'tenant')->user_id, 'room_id' => $room->room_id, 'start_date' => '2026-05-01', 'end_date' => '2026-12-31', 'status' => 'Active']);
        $b = Tenancy::create(['user_id' => $this->user('b@gmail.com', 'tenant')->user_id, 'room_id' => $room->room_id, 'start_date' => '2026-01-01', 'end_date' => '2026-03-01', 'status' => 'Active']);

        $this->actingAs($me)->post('/landlord/rooms/tenants', ['room_id' => $room->room_id])
            ->assertJsonPath('0.tenant_id', $b->tenant_id)              // earliest start first
            ->assertJsonPath('0.start_date', '2026-01-01');

        $this->post('/landlord/tenants/remove', ['tenant_id' => $a->tenant_id])->assertExactJson(['status' => 'removed']);
        $this->assertSame('Left', $a->fresh()->status);
        $this->assertSame('2026-10-18', $a->fresh()->end_date->format('Y-m-d'), 'later end date becomes today');
        $this->assertSame('Occupied', $room->fresh()->status, 'someone still lives there (same rule as before)');

        $this->post('/landlord/tenants/remove', ['tenant_id' => $b->tenant_id]);
        $this->assertSame('2026-03-01', $b->fresh()->end_date->format('Y-m-d'), 'earlier end date stays');
        $this->assertSame('Available', $room->fresh()->status);
    }

    public function test_cannot_manage_tenants_of_someone_elses_room(): void
    {
        $me = $this->user('me@gmail.com', 'landlord');
        $theirs = $this->room($this->user('other@gmail.com', 'landlord'));
        $t = Tenancy::create(['user_id' => $this->user('a@gmail.com', 'tenant')->user_id, 'room_id' => $theirs->room_id, 'start_date' => '2026-05-01', 'end_date' => '2026-12-31', 'status' => 'Active']);

        $this->actingAs($me)->post('/landlord/tenants/assign', ['room_id' => $theirs->room_id, 'user_id' => $t->user_id, 'start_date' => '2026-05-01', 'end_date' => '2026-06-01'])
            ->assertExactJson(['status' => 'error', 'message' => 'Room not found']);
        $this->post('/landlord/tenants/remove', ['tenant_id' => $t->tenant_id])->assertExactJson(['status' => 'error', 'message' => 'Tenant not found']);
        $this->post('/landlord/rooms/tenants', ['room_id' => $theirs->room_id])->assertExactJson([]);
        $this->assertSame('Active', $t->fresh()->status);
    }

    // ---------- Applicants ----------

    public function test_applicants_list_verify_and_reject(): void
    {
        $me = $this->user('me@gmail.com', 'landlord');
        $room = $this->room($me);
        $kobe = $this->user('kobe@gmail.com', 'tenant');
        $kyrie = $this->user('kyrie@gmail.com', 'tenant');
        $a1 = RoomApplication::create(['user_id' => $kobe->user_id, 'room_id' => $room->room_id, 'status' => 'Pending', 'applied_at' => '2026-05-01 10:00:00']);
        $a2 = RoomApplication::create(['user_id' => $kyrie->user_id, 'room_id' => $room->room_id, 'status' => 'Pending', 'applied_at' => '2026-05-02 10:00:00']);

        $this->actingAs($me)->post('/landlord/rooms/applicants', ['room_id' => $room->room_id])
            ->assertJsonPath('0.name', 'Kyrie')                       // newest first
            ->assertJsonPath('0.email', 'kyrie@gmail.com')
            ->assertJsonPath('0.applied_at', '2026-05-02 10:00:00')
            ->assertJsonPath('1.application_id', $a1->application_id);

        $this->post('/landlord/applications/verify', ['application_id' => $a1->application_id, 'user_id' => $kobe->user_id, 'room_id' => $room->room_id])
            ->assertExactJson(['status' => 'success']);
        $this->post('/landlord/applications/reject', ['application_id' => $a2->application_id, 'room_id' => $room->room_id])
            ->assertExactJson(['status' => 'success']);

        $this->assertSame('Verified', $a1->fresh()->status);
        $this->assertSame('Rejected', $a2->fresh()->status);
    }

    public function test_cannot_decide_applications_for_someone_elses_room(): void
    {
        $me = $this->user('me@gmail.com', 'landlord');
        $mine = $this->room($me, 'Mine');
        $theirs = $this->room($this->user('other@gmail.com', 'landlord'), 'Theirs');
        $app = RoomApplication::create(['user_id' => $this->user('kobe@gmail.com', 'tenant')->user_id, 'room_id' => $theirs->room_id, 'status' => 'Pending']);

        $this->actingAs($me)->post('/landlord/applications/verify', ['application_id' => $app->application_id, 'room_id' => $theirs->room_id])
            ->assertExactJson(['status' => 'error', 'message' => 'Unauthorized']);
        // Security fix: naming my own room doesn't help either.
        $this->post('/landlord/applications/verify', ['application_id' => $app->application_id, 'room_id' => $mine->room_id])
            ->assertExactJson(['status' => 'error', 'message' => 'Application not found']);
        $this->post('/landlord/rooms/applicants', ['room_id' => $theirs->room_id])->assertExactJson([]);

        $this->assertSame('Pending', $app->fresh()->status);
    }

    // ---------- Email verification ----------

    public function test_unverified_landlord_can_look_but_not_change_anything(): void
    {
        $me = $this->user('me@gmail.com', 'landlord', verified: false);
        $room = $this->room($me);

        $this->actingAs($me)->get('/landlord/dashboard')->assertOk()->assertSee('Please verify your email');
        $this->post('/landlord/rooms', $this->roomForm())->assertRedirect(route('verification.notice'));
        $this->postJson('/landlord/rooms/delete', ['room_id' => $room->room_id])->assertForbidden();

        $this->assertSame(1, Room::count());
    }

    public function test_old_address_redirects(): void
    {
        $this->get('/landlord_dashboard.php')->assertRedirect(route('landlord.dashboard'));
    }
}