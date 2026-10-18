<?php

namespace Tests\Feature;

use App\Models\MaintenanceRequest;
use App\Models\Payment;
use App\Models\Room;
use App\Models\Tenancy;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Part G2 — new tenant forms: request maintenance and submit a payment.
 */
class TenantRequestsTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $email, string $role = 'tenant', bool $verified = true): User
    {
        $user = User::create(['name' => 'Kobe', 'age' => 21, 'email' => $email, 'password' => Hash::make('x'), 'role' => $role]);
        if ($verified) {
            $user->markEmailAsVerified();
        }

        return $user;
    }

    private function rent(User $tenant, string $roomName = 'Room 201', ?int $ownerId = null): Room
    {
        $room = Room::create(['room_name' => $roomName, 'price' => 4500, 'description' => 'x', 'max_occupants' => 4, 'owner_id' => $ownerId]);
        Tenancy::create(['user_id' => $tenant->user_id, 'room_id' => $room->room_id, 'start_date' => '2026-05-01', 'end_date' => '2026-12-01', 'status' => 'Active']);

        return $room;
    }

    public function test_forms_are_hidden_until_the_tenant_rents_a_room(): void
    {
        $me = $this->user('t@gmail.com');
        $this->actingAs($me)->get('/tenant/dashboard')
            ->assertSee('You can request maintenance once you are renting a room.')
            ->assertSee('You can submit payments once you are renting a room.')
            ->assertDontSee('Send request');

        $this->rent($me);
        $this->actingAs($me)->get('/tenant/dashboard')
            ->assertSee('Send request')->assertSee('Submit payment')
            ->assertSee('<option value="', false);
    }

    public function test_tenant_can_request_maintenance_for_their_room(): void
    {
        $me = $this->user('t@gmail.com');
        $room = $this->rent($me);

        $this->actingAs($me)->post('/tenant/maintenance', ['room_id' => $room->room_id, 'description' => '  Leaking faucet  '])
            ->assertRedirect(route('tenant.dashboard'));

        $this->assertDatabaseHas('maintenance_requests', [
            'tenant_id' => $me->user_id, 'room_id' => $room->room_id, 'description' => 'Leaking faucet', 'status' => 'Pending',
        ]);
        $this->actingAs($me)->get('/tenant/dashboard')
            ->assertSee('Maintenance request sent.')
            ->assertSee('Leaking faucet')
            ->assertSee('badge-pend', false);
    }

    public function test_maintenance_is_refused_for_a_room_the_tenant_does_not_rent(): void
    {
        $me = $this->user('t@gmail.com');
        $this->rent($me);
        $other = $this->rent($this->user('o@gmail.com'), 'Room 999');

        $this->actingAs($me)->post('/tenant/maintenance', ['room_id' => $other->room_id, 'description' => 'Fix it'])
            ->assertRedirect(route('tenant.dashboard'));
        $this->assertSame(0, MaintenanceRequest::count());
        $this->actingAs($me)->get('/tenant/dashboard')->assertSee('You can only request maintenance for a room you are renting.');
    }

    public function test_maintenance_needs_a_description(): void
    {
        $me = $this->user('t@gmail.com');
        $room = $this->rent($me);

        $this->actingAs($me)->post('/tenant/maintenance', ['room_id' => $room->room_id, 'description' => '']);
        $this->assertSame(0, MaintenanceRequest::count());
        $this->actingAs($me)->get('/tenant/dashboard')->assertSee('Please describe the problem.');
    }

    public function test_new_request_shows_on_the_landlords_maintenance_page(): void
    {
        $landlord = $this->user('l@gmail.com', 'landlord');
        $me = $this->user('t@gmail.com');
        $room = $this->rent($me, 'Room 201', $landlord->user_id);

        $this->actingAs($me)->post('/tenant/maintenance', ['room_id' => $room->room_id, 'description' => 'Broken window']);
        $this->actingAs($landlord)->get('/landlord/maintenance')->assertSee('Broken window');
    }

    public function test_tenant_can_submit_a_payment(): void
    {
        $me = $this->user('t@gmail.com');
        $this->rent($me);

        $this->actingAs($me)->post('/tenant/payments', ['amount' => '4500', 'date' => now()->toDateString()])
            ->assertRedirect(route('tenant.dashboard'));

        $p = Payment::first();
        $this->assertSame($me->user_id, $p->user_id);
        $this->assertSame('4500.00', $p->amount);
        $this->assertSame('Pending', $p->status);
        $this->actingAs($me)->get('/tenant/dashboard')
            ->assertSee('Payment submitted.')
            ->assertSee('₱4,500.00');
    }

    public function test_payment_rules(): void
    {
        $me = $this->user('t@gmail.com');

        // Not renting yet
        $this->actingAs($me)->post('/tenant/payments', ['amount' => '100', 'date' => now()->toDateString()]);
        $this->actingAs($me)->get('/tenant/dashboard')->assertSee('You can only submit payments while you are renting a room.');

        $this->rent($me);
        // Future date, zero amount, bad date
        $this->actingAs($me)->post('/tenant/payments', ['amount' => '100', 'date' => now()->addDay()->toDateString()]);
        $this->actingAs($me)->get('/tenant/dashboard')->assertSee('The payment date cannot be in the future.');
        $this->actingAs($me)->post('/tenant/payments', ['amount' => '0', 'date' => now()->toDateString()]);
        $this->actingAs($me)->post('/tenant/payments', ['amount' => '100', 'date' => 'yesterday']);

        $this->assertSame(0, Payment::count());
    }

    public function test_only_my_payments_and_requests_are_listed(): void
    {
        $me = $this->user('t@gmail.com');
        $other = $this->user('o@gmail.com');
        $room = $this->rent($other);
        $this->rent($me);
        Payment::create(['user_id' => $other->user_id, 'amount' => 7777, 'date' => '2026-05-01', 'status' => 'Pending']);
        MaintenanceRequest::create(['tenant_id' => $other->user_id, 'room_id' => $room->room_id, 'description' => 'Someone elses leak', 'status' => 'Pending']);

        $this->actingAs($me)->get('/tenant/dashboard')->assertDontSee('7,777')->assertDontSee('Someone elses leak');
    }

    public function test_unverified_tenants_and_other_roles_cannot_submit(): void
    {
        $unverified = $this->user('u@gmail.com', 'tenant', false);
        $room = $this->rent($unverified);
        $this->actingAs($unverified)->post('/tenant/maintenance', ['room_id' => $room->room_id, 'description' => 'x'])
            ->assertRedirect(route('verification.notice'));
        $this->actingAs($unverified)->post('/tenant/payments', ['amount' => '1', 'date' => now()->toDateString()])
            ->assertRedirect(route('verification.notice'));

        $landlord = $this->user('l@gmail.com', 'landlord');
        $this->actingAs($landlord)->post('/tenant/payments', ['amount' => '1', 'date' => now()->toDateString()])
            ->assertRedirect(route('login'));

        $this->post('/tenant/payments', ['amount' => '1', 'date' => now()->toDateString()])->assertRedirect(route('login'));

        $this->assertSame(0, Payment::count());
        $this->assertSame(0, MaintenanceRequest::count());
    }
}