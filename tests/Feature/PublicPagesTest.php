<?php

namespace Tests\Feature;

use App\Models\Room;
use App\Models\RoomApplication;
use App\Models\RoomImage;
use App\Models\Tenancy;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Part D — index.php, rooms.php, room_detail.php and the Apply button.
 */
class PublicPagesTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $email, string $role = 'tenant', bool $verified = true): User
    {
        $user = User::create(['name' => ucfirst($role) . ' User', 'age' => 21, 'email' => $email, 'password' => Hash::make('x'), 'role' => $role]);
        if ($verified) {
            $user->markEmailAsVerified();
        }

        return $user;
    }

    private function room(string $name, int $price, string $status = 'Available', int $max = 2, ?string $created = null): Room
    {
        $room = Room::create(['room_name' => $name, 'price' => $price, 'description' => "Nice $name", 'status' => $status, 'max_occupants' => $max]);
        if ($created) {
            $room->forceFill(['created_at' => $created])->save();
        }

        return $room;
    }

    // ---------- Home page ----------

    public function test_home_shows_counts_and_featured_rooms_available_first(): void
    {
        $this->room('Old Available', 3000, 'Available', 2, '2026-01-01 10:00:00');
        $this->room('New Occupied', 4000, 'Occupied', 1, '2026-03-01 10:00:00');
        $this->room('New Available', 5000, 'Available', 2, '2026-02-01 10:00:00');

        $response = $this->get('/')->assertOk()
            ->assertSee('Find a room that feels')
            ->assertSee('name="csrf-token"', false);

        $html = $response->getContent();
        $this->assertMatchesRegularExpression('/stat-num">3<.*Total Rooms/s', $html);
        $this->assertMatchesRegularExpression('/stat-num">2<.*Available/s', $html);
        $this->assertMatchesRegularExpression('/stat-num">1<.*Occupied/s', $html);
        // available (newest first), then the rest
        $response->assertSeeInOrder(['New Available', 'Old Available', 'New Occupied']);
    }

    public function test_home_shows_at_most_6_rooms_and_empty_message(): void
    {
        $this->get('/')->assertSee('No rooms listed yet.');

        foreach (range(1, 8) as $i) {
            $this->room("Room $i", 1000 * $i);
        }
        $this->assertSame(6, substr_count($this->get('/')->getContent(), 'class="c-name"'));
    }

    public function test_card_shows_cover_photo_occupants_and_price(): void
    {
        $tenant = $this->user('t@gmail.com');
        $room = $this->room('Room 201', 4500, 'Available', 4);
        RoomImage::create(['room_id' => $room->room_id, 'filename' => 'second.jpg', 'sort_order' => 1]);
        RoomImage::create(['room_id' => $room->room_id, 'filename' => 'cover.jpg', 'sort_order' => 0]);
        Tenancy::create(['user_id' => $tenant->user_id, 'room_id' => $room->room_id, 'start_date' => '2026-01-01', 'end_date' => '2026-12-01', 'status' => 'Active']);
        Tenancy::create(['user_id' => $tenant->user_id, 'room_id' => $room->room_id, 'start_date' => '2025-01-01', 'end_date' => '2025-12-01', 'status' => 'Left']);

        $this->get('/')
            ->assertSee(asset('images/cover.jpg'), false)
            ->assertSee('Occupants: <strong>1/4</strong>', false)
            ->assertSee('style="width:25%"', false)
            ->assertSee('₱4,500');
    }

    public function test_room_names_are_escaped(): void
    {
        $this->room('<script>bad()</script>', 1000);

        $this->get('/')->assertDontSee('<script>bad()</script>', false);
        $this->get('/rooms')->assertDontSee('<script>bad()</script>', false);
    }

    public function test_pricing_buttons_and_links_point_to_the_new_pages(): void
    {
        $this->get('/')
            ->assertSee('onclick="selectPlan(\'Professional\')"', false)
            ->assertSee('data-register-url="' . route('register') . '"', false)
            ->assertSee('href="' . route('rooms.index') . '"', false);
    }

    // ---------- Live search (index.php?search=) ----------

    public function test_search_matches_name_description_or_price_ignoring_capitals(): void
    {
        $this->room('Room 201', 4500);
        $this->room('Sunny Loft', 7450, 'Occupied');

        $this->assertSame(['Room 201'], collect($this->getJson('/?search=ROOM')->json())->pluck('room_name')->all());
        $this->assertSame(['Sunny Loft'], collect($this->getJson('/?search=nice sunny')->json())->pluck('room_name')->all());
        $this->assertSame(['Sunny Loft'], collect($this->getJson('/?search=745')->json())->pluck('room_name')->all());
        $this->assertSame([], $this->getJson('/?search=zzz')->json());
    }

    public function test_search_returns_the_same_fields_as_before_available_first(): void
    {
        $this->room('B room', 2000, 'Occupied', 1);
        $a = $this->room('A room', 1000, 'Available', 3);
        RoomImage::create(['room_id' => $a->room_id, 'filename' => 'a.jpg', 'sort_order' => 0]);

        $rows = $this->getJson('/?search=room')->assertOk()->json();

        $this->assertSame(['room_id', 'room_name', 'price', 'status', 'current_occupants', 'max_occupants', 'cover_image'], array_keys($rows[0]));
        $this->assertSame(['A room', 'B room'], array_column($rows, 'room_name'));
        $this->assertSame('a.jpg', $rows[0]['cover_image']);
        $this->assertNull($rows[1]['cover_image']);
    }

    public function test_search_returns_at_most_10(): void
    {
        foreach (range(1, 12) as $i) {
            $this->room("Room $i", 1000);
        }

        $this->assertCount(10, $this->getJson('/?search=room')->json());
    }

    // ---------- rooms.php ----------

    public function test_rooms_page_lists_all_rooms_newest_first(): void
    {
        $this->room('Older', 1000, 'Available', 1, '2026-01-01 10:00:00');
        $this->room('Newer', 1000, 'Occupied', 1, '2026-02-01 10:00:00');

        $this->get('/rooms')->assertOk()->assertSee('All Rooms')->assertSeeInOrder(['Newer', 'Older']);
    }

    public function test_rooms_page_search_by_name(): void
    {
        $this->room('Room 201', 1000);
        $this->room('Room 305', 1000);

        $this->get('/rooms?name=201')->assertSee('Room 201')->assertDontSee('Room 305')->assertSee('value="201"', false);
        $this->get('/rooms?name=999')->assertSee('No rooms found.');
    }

    // ---------- room_detail.php ----------

    public function test_room_page_shows_details_gallery_and_slots(): void
    {
        $room = $this->room('Room 201', 4500, 'Available', 2);
        RoomImage::create(['room_id' => $room->room_id, 'filename' => 'b.jpg', 'sort_order' => 1]);
        RoomImage::create(['room_id' => $room->room_id, 'filename' => 'a.jpg', 'sort_order' => 0]);

        $this->get('/rooms/' . $room->room_id)->assertOk()
            ->assertSee('<title>Room 201 · RSYNC </title>', false)
            ->assertSee('₱4,500<span> / month</span>', false)
            ->assertSee('Occupants: <strong>0 / 2</strong>', false)
            ->assertSee('2 persons')
            ->assertSee('2 open')
            ->assertSee('id="mainImgEl"', false)
            ->assertSeeInOrder([asset('images/a.jpg'), asset('images/b.jpg')], false)
            ->assertSee('Login to Apply');
    }

    public function test_unknown_room_goes_back_to_the_list(): void
    {
        $this->get('/rooms/999')->assertRedirect(route('rooms.index'));
        $this->get('/rooms/abc')->assertRedirect(route('rooms.index'));
    }

    public function test_tenant_sees_apply_now_or_room_full(): void
    {
        $tenant = $this->user('t@gmail.com');
        $room = $this->room('Room 201', 4500, 'Available', 1);

        $this->actingAs($tenant)->get('/rooms/' . $room->room_id)->assertSee('Apply Now');

               // Someone else takes the only slot.
        Tenancy::create(['user_id' => $this->user('other@gmail.com')->user_id, 'room_id' => $room->room_id, 'start_date' => '2026-01-01', 'end_date' => '2026-12-01', 'status' => 'Active']);
        $this->actingAs($tenant)->get('/rooms/' . $room->room_id)->assertSee('Room Full')->assertDontSee('Apply Now');
    }

    public function test_landlord_sees_login_to_apply_like_before(): void
    {
        $room = $this->room('Room 201', 4500);

        $this->actingAs($this->user('l@gmail.com', 'landlord'))->get('/rooms/' . $room->room_id)->assertSee('Login to Apply');
    }

    // ---------- Apply (apply_for_room) ----------

    public function test_apply_success(): void
    {
        $tenant = $this->user('t@gmail.com');
        $room = $this->room('Room 201', 4500);

        $this->actingAs($tenant)->post('/rooms/apply', ['apply_for_room' => 1, 'room_id' => $room->room_id])
            ->assertOk()->assertExactJson(['status' => 'success', 'message' => 'Application submitted successfully']);

        $app = RoomApplication::first();
        $this->assertSame('Pending', $app->status);
        $this->assertSame($tenant->user_id, $app->user_id);
        $this->assertNotNull($app->applied_at);
    }

    public function test_apply_errors_use_the_old_messages(): void
    {
        $room = $this->room('Room 201', 4500);

        $this->post('/rooms/apply', ['room_id' => $room->room_id])
            ->assertExactJson(['status' => 'error', 'message' => 'You must be logged in']);
        $this->actingAs($this->user('l@gmail.com', 'landlord'))->post('/rooms/apply', ['room_id' => $room->room_id])
            ->assertExactJson(['status' => 'error', 'message' => 'Only tenants can apply']);

        $tenant = $this->user('t@gmail.com');
        $this->actingAs($tenant)->post('/rooms/apply', ['room_id' => 999])
            ->assertExactJson(['status' => 'error', 'message' => 'Room not found']);

        $this->actingAs($tenant)->post('/rooms/apply', ['room_id' => $room->room_id]);
        $this->actingAs($tenant)->post('/rooms/apply', ['room_id' => $room->room_id])
            ->assertExactJson(['status' => 'error', 'message' => 'You already applied for this room']);

        $this->assertSame(1, RoomApplication::count());
    }

    public function test_apply_after_an_old_rejected_application_gives_a_message_instead_of_crashing(): void
    {
        $tenant = $this->user('t@gmail.com');
        $room = $this->room('Room 201', 4500);
        RoomApplication::create(['user_id' => $tenant->user_id, 'room_id' => $room->room_id, 'status' => 'Rejected']);

        $this->actingAs($tenant)->post('/rooms/apply', ['room_id' => $room->room_id])
            ->assertOk()->assertExactJson(['status' => 'error', 'message' => 'You already applied for this room']);
    }

    public function test_unverified_tenant_must_verify_before_applying(): void
    {
        $tenant = $this->user('t@gmail.com', 'tenant', verified: false);
        $room = $this->room('Room 201', 4500);

        $this->actingAs($tenant)->post('/rooms/apply', ['room_id' => $room->room_id])
            ->assertExactJson(['status' => 'error', 'message' => 'Please verify your email first. Check your inbox for the link.']);

        $this->assertSame(0, RoomApplication::count());
    }

    // ---------- Old addresses ----------

    public function test_old_addresses_redirect(): void
    {
        $room = $this->room('Room 201', 4500);

        $this->get('/index.php')->assertRedirect('/');
        $this->get('/rooms.php')->assertRedirect(route('rooms.index'));
        $this->get('/rooms.php?name=201')->assertRedirect(route('rooms.index', ['name' => '201']));
        $this->get('/room_detail.php?id=' . $room->room_id)->assertRedirect(route('rooms.show', $room->room_id));
        $this->get('/tenant_dashboard.php')->assertRedirect(route('tenant.dashboard'));
    }
}