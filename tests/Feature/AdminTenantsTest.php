<?php

namespace Tests\Feature;

use App\Models\Room;
use App\Models\Tenancy;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Part G4 — admin Tenants & Contracts (tenants.php).
 */
class AdminTenantsTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $email, string $role = 'tenant', string $name = 'Kobe', bool $emailVerified = true): User
    {
        $user = User::create(['name' => $name, 'age' => 21, 'email' => $email, 'password' => Hash::make('x'), 'role' => $role]);
        if ($emailVerified) {
            $user->markEmailAsVerified();
        }

        return $user;
    }

    private function admin(): User
    {
        return $this->user('admin@gmail.com', 'admin', 'Boss');
    }

    private function contract(User $u, string $room, string $start, string $end, string $status = 'Active'): Tenancy
    {
        $r = Room::firstOrCreate(['room_name' => $room], ['price' => 4500, 'max_occupants' => 4]);

        return Tenancy::create(['user_id' => $u->user_id, 'room_id' => $r->room_id, 'start_date' => $start, 'end_date' => $end, 'status' => $status]);
    }

    public function test_only_admins_can_open_it(): void
    {
        $this->get('/admin/tenants')->assertRedirect(route('login'));
        $this->actingAs($this->user('t@gmail.com'))->get('/admin/tenants')->assertRedirect(route('login'));
        $this->actingAs($this->user('l@gmail.com', 'landlord'))->get('/admin/tenants')->assertRedirect(route('login'));
        $this->actingAs($this->admin())->get('/admin/tenants')->assertOk()
            ->assertSee('Tenant Details')->assertSee('Contract Details')
            ->assertSee('Update Contract Details')->assertSee('Update Tenant Status');
    }

    public function test_tenant_details_show_room_and_active_or_inactive(): void
    {
        $juan = $this->user('juan@gmail.com', 'tenant', 'Juan Dela Cruz');
        $maria = $this->user('maria@gmail.com', 'tenant', 'Maria Santos');
        $this->user('l@gmail.com', 'landlord', 'Landlord Lee');
        $this->contract($juan, 'Deluxe Room', '2026-01-01', '2026-12-31');
        $this->contract($maria, 'Cozy Studio', '2025-03-01', '2025-12-31', 'Left');

        $res = $this->actingAs($this->admin())->get('/admin/tenants');
        $res->assertSee('Juan Dela Cruz')->assertSee('Maria Santos')->assertDontSee('Landlord Lee')
            ->assertSee('2 tenant accounts');
        $this->assertSame(['Deluxe Room'], $res->viewData('tenants')->firstWhere('name', 'Juan Dela Cruz')->tenancies->pluck('room.room_name')->all());
        $this->assertTrue($res->viewData('tenants')->firstWhere('name', 'Maria Santos')->tenancies->isEmpty());
    }

    public function test_contract_list_with_codes_and_active_expired_left(): void
    {
        $u = $this->user('juan@gmail.com', 'tenant', 'Juan Dela Cruz');
        $a = $this->contract($u, 'Deluxe Room', now()->subMonth()->toDateString(), now()->addMonth()->toDateString());
        $e = $this->contract($u, 'Budget Room', '2025-01-01', '2025-06-30');
        $l = $this->contract($u, 'Cozy Studio', '2024-01-01', '2024-06-30', 'Left');

        $this->actingAs($this->admin())->get('/admin/tenants')
            ->assertSee(sprintf('C%03d', $a->tenant_id))
            ->assertSee('badge-active', false)->assertSee('badge-expired', false)->assertSee('badge-left', false)
            ->assertSee('2025-06-30');
    }

    public function test_admin_can_change_contract_dates(): void
    {
        $u = $this->user('juan@gmail.com', 'tenant', 'Juan Dela Cruz');
        $c = $this->contract($u, 'Deluxe Room', '2026-01-01', '2026-12-31');
        $admin = $this->admin();

        $this->actingAs($admin)->post('/admin/contracts/update', ['tenant_id' => $c->tenant_id, 'start_date' => '2026-02-01', 'end_date' => '2027-01-31'])
            ->assertRedirect(route('admin.tenants'));

        $c->refresh();
        $this->assertSame('2026-02-01', $c->start_date->toDateString());
        $this->assertSame('2027-01-31', $c->end_date->toDateString());
        $this->actingAs($admin)->get('/admin/tenants')->assertSee(sprintf('Contract C%03d for Juan Dela Cruz updated.', $c->tenant_id));
    }

    public function test_contract_update_rules(): void
    {
        $u = $this->user('juan@gmail.com');
        $c = $this->contract($u, 'Deluxe Room', '2026-01-01', '2026-12-31');
        $left = $this->contract($u, 'Old Room', '2025-01-01', '2025-02-01', 'Left');
        $admin = $this->admin();

        $this->actingAs($admin)->post('/admin/contracts/update', ['tenant_id' => $c->tenant_id, 'start_date' => '2026-05-01', 'end_date' => '2026-04-01']);
        $this->actingAs($admin)->get('/admin/tenants')->assertSee('The end date must be after the start date.');

        $this->actingAs($admin)->post('/admin/contracts/update', ['tenant_id' => '', 'start_date' => '2026-05-01', 'end_date' => '2026-06-01']);
        $this->actingAs($admin)->get('/admin/tenants')->assertSee('Please select a contract.');

        $this->actingAs($admin)->post('/admin/contracts/update', ['tenant_id' => $left->tenant_id, 'start_date' => '2026-05-01', 'end_date' => '2026-06-01']);
        $this->actingAs($admin)->get('/admin/tenants')->assertSee('Contract not found or no longer active.');

        $this->assertSame('2026-12-31', $c->fresh()->end_date->toDateString());
        $this->assertSame('2025-02-01', $left->fresh()->end_date->toDateString());
    }

    public function test_update_tenant_status(): void
    {
        $u = $this->user('juan@gmail.com', 'tenant', 'Juan Dela Cruz');
        $c = $this->contract($u, 'Deluxe Room', '2026-01-01', '2026-12-31');
        $admin = $this->admin();

        $this->assertSame('Registered', $c->fresh()->payment_status, 'new contracts start as Registered');

        $this->actingAs($admin)->post('/admin/contracts/status', ['tenant_id' => $c->tenant_id, 'payment_status' => 'Delayed'])
            ->assertRedirect(route('admin.tenants'));
        $this->assertSame('Delayed', $c->fresh()->payment_status);
        $this->actingAs($admin)->get('/admin/tenants')->assertSee('Juan Dela Cruz is now marked Delayed.')->assertSee('badge-delayed', false);

        $this->actingAs($admin)->post('/admin/contracts/status', ['tenant_id' => $c->tenant_id, 'payment_status' => 'Hacked']);
        $this->assertSame('Delayed', $c->fresh()->payment_status);
    }

    public function test_unverified_admin_cannot_change_anything(): void
    {
        $admin = $this->user('a2@gmail.com', 'admin', 'Boss', false);
        $c = $this->contract($this->user('juan@gmail.com'), 'Deluxe Room', '2026-01-01', '2026-12-31');

        $this->actingAs($admin)->get('/admin/tenants')->assertOk();
        $this->actingAs($admin)->post('/admin/contracts/status', ['tenant_id' => $c->tenant_id, 'payment_status' => 'Paid'])
            ->assertRedirect(route('verification.notice'));
        $this->actingAs($admin)->post('/admin/contracts/update', ['tenant_id' => $c->tenant_id, 'start_date' => '2026-02-01', 'end_date' => '2026-03-01'])
            ->assertRedirect(route('verification.notice'));
        $this->assertSame('Registered', $c->fresh()->payment_status);
        $this->assertSame('2026-01-01', $c->fresh()->start_date->toDateString());
    }

    public function test_admin_nav_links_and_old_address(): void
    {
        $this->actingAs($this->admin())->get('/admin/dashboard')
            ->assertSee('href="' . route('admin.tenants') . '"', false);
        $this->get('/tenants.php')->assertRedirect(route('admin.tenants'));
    }
}