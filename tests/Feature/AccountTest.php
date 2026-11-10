<?php

namespace Tests\Feature;

use App\Models\Room;
use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Account page (details, email verification, password) and the logged-in navigation links.
 */
class AccountTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $email = 'kobe@gmail.com', string $role = 'tenant', bool $verified = true): User
    {
        $user = User::create(['name' => 'Kobe', 'age' => 21, 'email' => $email, 'password' => Hash::make('secret123'), 'role' => $role]);
        if ($verified) {
            $user->markEmailAsVerified();
        }

        return $user;
    }

    public function test_guests_are_sent_to_login(): void
    {
        $this->get('/account')->assertRedirect(route('login'));
        $this->post('/account/profile', ['name' => 'X'])->assertRedirect(route('login'));
        $this->post('/account/password', [])->assertRedirect(route('login'));
    }

    public function test_every_role_can_open_the_account_page(): void
    {
        foreach (['tenant', 'landlord', 'sublessor', 'admin'] as $i => $role) {
            $this->actingAs($this->user("u{$i}@gmail.com", $role))
                ->get('/account')
                ->assertOk()
                ->assertSee('My Account')
                ->assertSee("u{$i}@gmail.com")
                ->assertSee('Change password');
        }
    }

    public function test_page_shows_verification_status(): void
    {
        $this->actingAs($this->user('a@gmail.com'))->get('/account')->assertSee('Verified')->assertDontSee('Resend verification link');
        $this->actingAs($this->user('b@gmail.com', 'tenant', false))->get('/account')->assertSee('Not verified')->assertSee('Resend verification link');
    }

    public function test_resend_link_from_account_page(): void
    {
        Notification::fake();
        $user = $this->user('b@gmail.com', 'tenant', false);

        $this->actingAs($user)->from('/account')->post(route('verification.send'))->assertRedirect('/account');
        Notification::assertSentTo($user, VerifyEmail::class);
    }

    private function details(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Kobe Bryant',
            'birthday' => '2004-08-23',
            'contact_number' => '0917 123 4567',
            'emergency_contact_name' => 'Pamela Bryant',
            'emergency_contact_relationship' => 'Mother',
            'emergency_contact_number' => '09181234567',
            'social_media_url' => 'https://facebook.com/kobe.bryant',
        ], $overrides);
    }

    public function test_tenant_saves_personal_details_and_emergency_contact(): void
    {
        $this->travelTo('2026-10-21 10:00:00');
        $user = $this->user();

        $this->actingAs($user)->from('/account')->post('/account/profile', $this->details())
            ->assertRedirect('/account')
            ->assertSessionHas('msg', 'Your details were saved.');

        $user->refresh();
        $this->assertSame('Kobe Bryant', $user->name);
        $this->assertSame('2004-08-23', $user->birthday->toDateString());
        $this->assertSame(22, $user->ageYears());
        $this->assertSame('09171234567', $user->contact_number);   // spaces removed
        $this->assertSame('Pamela Bryant', $user->emergency_contact_name);
        $this->assertSame('Mother', $user->emergency_contact_relationship);
        $this->assertSame('09181234567', $user->emergency_contact_number);
        $this->assertSame('https://facebook.com/kobe.bryant', $user->social_media_url);
        $this->assertTrue($user->hasCompleteProfile());
    }

    public function test_details_are_validated(): void
    {
        $user = $this->user();
        $bad = [
            ['name' => 'K'],                                   // too short
            ['name' => str_repeat('a', 101)],                  // too long
            ['name' => 'Kobe123'],                             // numbers
            ['birthday' => '2999-01-01'],                      // future
            ['contact_number' => '12345'],                     // not a PH mobile number
            ['emergency_contact_relationship' => 'Friend'],    // not in the list
            ['emergency_contact_number' => '09171234567'],     // same as own number
            ['social_media_url' => 'not a link'],
        ];
        foreach ($bad as $fields) {
            $this->actingAs($user)->from('/account')->post('/account/profile', $this->details($fields))
                ->assertSessionHasErrorsIn('profile', [array_key_first($fields)]);
        }

        $this->assertSame('Kobe', $user->fresh()->name);   // nothing was saved
    }

    public function test_landlords_only_fill_in_name_birthday_and_contact(): void
    {
        $landlord = $this->user('l@gmail.com', 'landlord');

        $this->actingAs($landlord)->get('/account')->assertDontSee('Emergency contact')->assertDontSee('Valid ID');
        $this->post('/account/profile', ['name' => 'Lee Landlord', 'birthday' => '1985-01-01', 'contact_number' => '+639171234567'])
            ->assertSessionHas('msg', 'Your details were saved.');

        $this->assertSame('Lee Landlord', $landlord->fresh()->name);
    }

    public function test_details_need_a_verified_email_first(): void
    {
        $user = $this->user('u@gmail.com', 'tenant', false);

        $this->actingAs($user)->get('/account')->assertSee('Verify your email to fill in')->assertDontSee('name="birthday"', false);
        $this->post('/account/profile', $this->details())->assertRedirect(route('verification.notice'));
    }

    public function test_email_cannot_be_changed(): void
    {
        $user = $this->user();

        $this->actingAs($user)->get('/account')->assertSee('kobe@gmail.com')->assertDontSee('name="email"', false);
        $this->post('/account/profile', $this->details(['email' => 'other@gmail.com']));

        $this->assertSame('kobe@gmail.com', $user->fresh()->email);
    }

    public function test_valid_id_upload_and_who_can_see_it(): void
    {
        \Illuminate\Support\Facades\Storage::fake('local');
        $tenant = $this->user();
        $this->actingAs($tenant)->post('/account/valid-id', ['valid_id' => \Illuminate\Http\UploadedFile::fake()->image('id.jpg')])
            ->assertSessionHas('msg', 'Your valid ID was uploaded.');
        $url = '/account/valid-id/' . $tenant->user_id;

        $landlord = $this->user('l@gmail.com', 'landlord');
        $room = \App\Models\Room::create(['owner_id' => $landlord->user_id, 'room_name' => 'Room 201', 'price' => 4500, 'max_occupants' => 2]);

        $this->get($url)->assertOk();                                                     // the tenant
        $this->actingAs($this->user('a@gmail.com', 'admin'))->get($url)->assertOk();     // an admin
        $this->actingAs($landlord)->get($url)->assertForbidden();                         // not their tenant yet
        \App\Models\RoomApplication::create(['user_id' => $tenant->user_id, 'room_id' => $room->room_id, 'status' => 'Pending', 'applied_at' => now()]);
        $this->actingAs($landlord)->get($url)->assertOk();                                // applied to their room
        $this->actingAs($this->user('o@gmail.com', 'landlord'))->get($url)->assertForbidden();   // another landlord
        $this->actingAs($this->user('t2@gmail.com'))->get($url)->assertForbidden();     // another tenant
    }

    public function test_change_password(): void
    {
        $user = $this->user();

        $this->actingAs($user)->from('/account')
            ->post('/account/password', ['current_password' => 'secret123', 'password' => 'NewPass456', 'password_confirmation' => 'NewPass456'])
            ->assertRedirect('/account')
            ->assertSessionHas('msg', 'Your password was changed.');

        $this->assertTrue(Hash::check('NewPass456', $user->fresh()->password));
    }

    public function test_change_password_rejects_bad_input(): void
    {
        $user = $this->user();
        $post = fn (array $data) => $this->actingAs($user)->from('/account')->post('/account/password', $data);

        $post(['current_password' => 'wrong', 'password' => 'NewPass456', 'password_confirmation' => 'NewPass456'])
            ->assertSessionHasErrorsIn('password', ['current_password']);
        foreach (['Short1', 'newpass456', 'NEWPASS456', 'NewPassword'] as $weak) {
            $post(['current_password' => 'secret123', 'password' => $weak, 'password_confirmation' => $weak])
                ->assertSessionHasErrorsIn('password', ['password']);
        }
        $post(['current_password' => 'secret123', 'password' => 'NewPass456', 'password_confirmation' => 'Different1'])
            ->assertSessionHasErrorsIn('password', ['password']);

        $this->assertTrue(Hash::check('secret123', $user->fresh()->password));
    }

    public function test_dashboards_have_an_account_link(): void
    {
        $this->actingAs($this->user('t@gmail.com'))->get('/tenant/dashboard')
            ->assertSeeInOrder(['Account', 'Browse Rooms', 'Logout']);
        $this->actingAs($this->user('l@gmail.com', 'landlord'))->get('/landlord/dashboard')
            ->assertSeeInOrder(['Account', 'Maintenance', 'Logout']);
    }

    public function test_logged_in_tenant_browsing_rooms_sees_dashboard_account_and_logout(): void
    {
        $room = Room::create(['room_name' => 'Room 201', 'price' => 4500, 'description' => 'x', 'max_occupants' => 2]);
        $tenant = $this->user('t@gmail.com');

        foreach (['/rooms', '/rooms/' . $room->room_id, '/'] as $url) {
            $this->actingAs($tenant)->get($url)
                ->assertSee(route('tenant.dashboard'))
                ->assertSee(route('account'))
                ->assertSee(route('logout'))
                ->assertDontSee('>Login</a>', false);
        }
    }

    public function test_guests_still_see_login(): void
    {
        $this->get('/rooms')->assertSee('>Login</a>', false)->assertDontSee(route('account'));
    }
}
