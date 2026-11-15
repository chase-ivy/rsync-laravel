<?php

namespace Tests\Feature;

use App\Models\Room;
use App\Models\RoomImage;
use App\Models\Tenancy;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Room price type: "per person" (default) or "for the whole room" (split between tenants by capacity).
 */
class RoomPriceTypeTest extends TestCase
{
    use RefreshDatabase;

    private string $publicDir;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();   // assigning sends contract notices

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

    private function user(string $email, string $role): User
    {
        $u = User::create(['name' => ucfirst(strtok($email, '@')), 'age' => 30, 'email' => $email, 'password' => Hash::make('x'), 'role' => $role]);
        $u->markEmailAsVerified();

        return $u;
    }

    private function room(User $owner, int $price, int $max, ?string $type = null): Room
    {
        return Room::create(['owner_id' => $owner->user_id, 'room_name' => 'Room 1', 'price' => $price, 'max_occupants' => $max, 'status' => 'Available', 'address' => '12 Session Rd, Baguio']
            + ($type ? ['price_type' => $type] : []));
    }

    private function assign(User $landlord, Room $room, User $tenant)
    {
        return $this->actingAs($landlord)->post('/landlord/tenants/assign', [
            'room_id' => $room->room_id, 'user_id' => $tenant->user_id, 'start_date' => '2026-12-01', 'end_date' => '2027-06-01',
        ]);
    }

    public function test_new_and_existing_rooms_default_to_per_person(): void
    {
        $room = $this->room($this->user('l@gmail.com', 'landlord'), 3500, 2);

        $this->assertSame('per_person', $room->fresh()->price_type);
        $this->assertSame('per_person', (new Room)->price_type);
        $this->assertSame(3500.0, $room->fresh()->perPersonRent());
    }

    public function test_split_math(): void
    {
        $this->assertSame(1750.0, (new Room(['price' => 3500, 'max_occupants' => 2, 'price_type' => 'whole_room']))->perPersonRent());
        $this->assertSame(333.33, (new Room(['price' => 1000, 'max_occupants' => 3, 'price_type' => 'whole_room']))->perPersonRent());
        $this->assertSame(1000.0, (new Room(['price' => 1000, 'max_occupants' => 3, 'price_type' => 'per_person']))->perPersonRent());
    }

    public function test_add_room_saves_the_price_type(): void
    {
        $landlord = $this->user('l@gmail.com', 'landlord');
        $form = ['room_name' => 'Room 9', 'address' => '12 Session Rd, Baguio', 'price' => 6000, 'max_occupants' => 3, 'images' => [UploadedFile::fake()->image('a.jpg')]];

        $this->actingAs($landlord)->post('/landlord/rooms', $form + ['price_type' => 'whole_room'])->assertRedirect('/landlord/dashboard');
        $this->assertSame('whole_room', Room::where('room_name', 'Room 9')->value('price_type'));

        // Without the choice (old form): per person.
        $this->actingAs($landlord)->post('/landlord/rooms', ['room_name' => 'Room 10', 'images' => [UploadedFile::fake()->image('b.jpg')]] + $form);
        $this->assertSame('per_person', Room::where('room_name', 'Room 10')->value('price_type'));
    }

    public function test_invalid_price_type_is_refused(): void
    {
        $landlord = $this->user('l@gmail.com', 'landlord');

        $this->actingAs($landlord)->post('/landlord/rooms', [
            'room_name' => 'Room 9', 'address' => '12 Session Rd, Baguio', 'price' => 6000, 'max_occupants' => 3,
            'price_type' => 'per_bed', 'images' => [UploadedFile::fake()->image('a.jpg')],
        ])->assertSessionHas('msg', 'err:Please choose whether the price is per person or for the whole room.');

        $this->assertSame(0, Room::count());
    }

    public function test_edit_room_changes_or_keeps_the_price_type(): void
    {
        $landlord = $this->user('l@gmail.com', 'landlord');
        $room = $this->room($landlord, 3500, 2);
        RoomImage::create(['room_id' => $room->room_id, 'filename' => 'a.jpg', 'sort_order' => 0]);
        $form = ['room_id' => $room->room_id, 'room_name' => 'Room 1', 'address' => '12 Session Rd, Baguio', 'price' => 3500, 'max_occupants' => 2];

        $this->actingAs($landlord)->post('/landlord/rooms/update', $form + ['price_type' => 'whole_room'])->assertSessionHas('msg', 'Room updated.');
        $this->assertSame('whole_room', $room->fresh()->price_type);

        $this->post('/landlord/rooms/update', $form)->assertSessionHas('msg', 'Room updated.');   // not sent: kept
        $this->assertSame('whole_room', $room->fresh()->price_type);

        $this->post('/landlord/rooms/update', $form + ['price_type' => 'bogus'])->assertSessionHas('msg', fn ($m) => str_starts_with($m, 'err:'));
        $this->assertSame('whole_room', $room->fresh()->price_type);
    }

    public function test_modals_have_the_choice_and_the_table_shows_the_price(): void
    {
        $landlord = $this->user('l@gmail.com', 'landlord');
        $this->room($landlord, 3500, 2, 'whole_room');

        $this->actingAs($landlord)->get('/landlord/dashboard')->assertOk()
            ->assertSee('Per person (each tenant pays this)')
            ->assertSee('For the whole room (split between tenants)')
            ->assertSee('name="price_type"', false)
            ->assertSee('data-price-type="whole_room"', false)
            ->assertSee('₱3,500 / room')
            ->assertSee('about ₱1,750 per person when full (2)');
    }

    public function test_public_pages_show_the_price_type(): void
    {
        $landlord = $this->user('l@gmail.com', 'landlord');
        $whole = $this->room($landlord, 3500, 2, 'whole_room');
        $person = Room::create(['owner_id' => $landlord->user_id, 'room_name' => 'Room 2', 'price' => 2800, 'max_occupants' => 1, 'status' => 'Available']);

        $this->get('/rooms/' . $whole->room_id)->assertOk()
            ->assertSee('₱3,500<span> / room</span>', false)
            ->assertSee('about ₱1,750 per person when full (2)')
            ->assertSee("doesn't change when roommates move in or out", false);
        $this->get('/rooms/' . $person->room_id)->assertSee('₱2,800<span> / person</span>', false);

        $this->get('/rooms')->assertSee('₱3,500<span> / room</span>', false)->assertSee('about ₱1,750 per person when full (2)')
            ->assertSee('₱2,800<span> / person</span>', false);
    }

    public function test_search_json_keys_are_unchanged(): void
    {
        $this->room($this->user('l@gmail.com', 'landlord'), 3500, 2, 'whole_room');

        $this->getJson('/?search=room')->assertOk()->assertJsonStructure([['room_id', 'room_name', 'price', 'status', 'current_occupants', 'max_occupants', 'cover_image']])
            ->assertJsonMissingPath('0.price_type');
    }

    public function test_assign_uses_the_price_or_the_split(): void
    {
        $landlord = $this->user('l@gmail.com', 'landlord');
        $perPerson = $this->room($landlord, 3500, 2);
        $whole = $this->room($landlord, 3500, 2, 'whole_room');
        $thirds = $this->room($landlord, 1000, 3, 'whole_room');

        $this->assign($landlord, $perPerson, $this->user('a@gmail.com', 'tenant'))->assertExactJson(['status' => 'success']);
        $this->assign($landlord, $whole, $this->user('b@gmail.com', 'tenant'))->assertExactJson(['status' => 'success']);
        $this->assign($landlord, $thirds, $this->user('c@gmail.com', 'tenant'))->assertExactJson(['status' => 'success']);

        $this->assertSame(3500.0, Tenancy::where('room_id', $perPerson->room_id)->first()->monthly_rent);
        $this->assertSame(1750.0, Tenancy::where('room_id', $whole->room_id)->first()->monthly_rent);
        $this->assertSame(333.33, Tenancy::where('room_id', $thirds->room_id)->first()->monthly_rent);

        // The split is by capacity: a second roommate pays the same, the first one's rent doesn't change.
        $this->assign($landlord, $whole, $this->user('d@gmail.com', 'tenant'));
        $this->assertSame([1750.0, 1750.0], Tenancy::where('room_id', $whole->room_id)->orderBy('tenant_id')->pluck('monthly_rent')->map(fn ($v) => (float) $v)->all());
    }

    public function test_existing_contracts_keep_their_rent_and_landlord_can_override(): void
    {
        $landlord = $this->user('l@gmail.com', 'landlord');
        $room = $this->room($landlord, 3500, 2);
        $tenant = $this->user('a@gmail.com', 'tenant');
        $this->assign($landlord, $room, $tenant);
        $c = Tenancy::first();

        $room->update(['price_type' => 'whole_room']);   // later change of the room
        $this->assertSame(3500.0, $c->fresh()->monthly_rent);

        $this->actingAs($landlord)->post(route('landlord.contracts.update', $c->tenant_id), [
            'monthly_rent' => '1800', 'end_date' => '2027-06-01', 'repairs_policy' => 'landlord', 'terms' => 'A', 'house_rules' => 'B',
        ])->assertRedirect('/landlord/tenants');
        $this->assertSame(1800.0, $c->fresh()->monthly_rent);
    }

    public function test_demo_seeder_room_is_per_person_and_contract_not_accepted(): void
    {
        $this->seed(\Database\Seeders\DemoAccountsSeeder::class);
        $this->seed(\Database\Seeders\DemoAccountsSeeder::class);   // still idempotent

        $room = Room::where('room_name', 'Demo Room A')->first();
        $this->assertSame('per_person', $room->price_type);
        $this->assertSame(1, Tenancy::count());
        $c = Tenancy::first();
        $this->assertSame(3500.0, $c->monthly_rent);
        $this->assertNull($c->accepted_at);
    }
}
