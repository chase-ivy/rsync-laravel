<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\Room;
use App\Models\Tenancy;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Part G3 — admin role and the real Admin Dashboard (admin_dashboard.php).
 */
class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $email, string $role = 'tenant', bool $emailVerified = true, string $name = 'Kobe'): User
    {
        $user = User::create(['name' => $name, 'age' => 21, 'email' => $email, 'password' => Hash::make('secret123'), 'role' => $role]);
        if ($emailVerified) {
            $user->markEmailAsVerified();
        }

        return $user;
    }

    private function admin(): User
    {
        return $this->user('admin@gmail.com', 'admin', true, 'Boss');
    }

    public function test_only_admins_can_open_it_and_admins_land_there_after_login(): void
    {
        $this->get('/admin/dashboard')->assertRedirect(route('login'));
        $this->actingAs($this->user('t@gmail.com'))->get('/admin/dashboard')->assertRedirect(route('login'));
        $this->actingAs($this->user('l@gmail.com', 'landlord'))->get('/admin/dashboard')->assertRedirect(route('login'));

        $this->admin();
        $this->get('/logout');
        $this->post('/login', ['email' => 'admin@gmail.com', 'password' => 'secret123'])->assertRedirect(route('admin.dashboard'));
        $this->get('/admin/dashboard')->assertOk()
            ->assertSee('Admin Dashboard')->assertSee('Financial Summary')->assertSee('Verify Users')
            ->assertSee('Verify Payments')->assertSee('Transaction Monitoring');
    }

    public function test_nobody_can_register_as_admin(): void
    {
        $this->post('/register', [
            'name' => 'Sneaky', 'age' => 20, 'email' => 'sneaky@gmail.com',
            'password' => 'secret123', 'password_confirmation' => 'secret123', 'role' => 'admin',
        ]);
        $this->assertNull(User::where('email', 'sneaky@gmail.com')->first());
    }

    public function test_financial_summary_counts_verified_payments_only(): void
    {
        $t = $this->user('t@gmail.com');
        $thisMonth = now()->startOfMonth()->addDays(1)->toDateString();
        Payment::create(['user_id' => $t->user_id, 'amount' => 4500, 'date' => $thisMonth, 'status' => 'Verified']);
        Payment::create(['user_id' => $t->user_id, 'amount' => 1000, 'date' => now()->subYear()->toDateString(), 'status' => 'Verified']);
        Payment::create(['user_id' => $t->user_id, 'amount' => 9999, 'date' => $thisMonth, 'status' => 'Pending']);

        $res = $this->actingAs($this->admin())->get('/admin/dashboard');
        $res->assertViewHas('totalPayments', 5500.0)
            ->assertViewHas('monthlyEarnings', 4500.0)
            ->assertSee('₱5,500')
            ->assertSee('monthlyEarningsChart', false);
        $this->assertSame([['month' => now()->format('F'), 'amount' => 4500.0]], $res->viewData('monthly')->all());
    }

    public function test_chart_is_replaced_by_a_message_when_there_is_no_data(): void
    {
        $this->actingAs($this->admin())->get('/admin/dashboard')
            ->assertSee('No verified payments this year yet.')
            ->assertDontSee('monthlyEarningsChart', false)
            ->assertSee('No users waiting for verification.')
            ->assertSee('No payments yet.')
            ->assertSee('No transactions yet.');
    }

    public function test_verify_users_lists_unverified_non_landlords_and_approve_works(): void
    {
        $pending = $this->user('p@gmail.com', 'tenant', true, 'Biboy Del Rosario');
        $this->user('s@gmail.com', 'sublessor', true, 'Mark Santos');
        $this->user('l@gmail.com', 'landlord', true, 'Landlord Lee');
        $done = $this->user('d@gmail.com', 'tenant', true, 'Already Done');
        $done->forceFill(['verified' => true])->save();
        $admin = $this->admin();

        $this->actingAs($admin)->get('/admin/dashboard')
            ->assertSee('Biboy Del Rosario')->assertSee('Mark Santos')
            ->assertDontSee('Landlord Lee')->assertDontSee('Already Done');

        $this->actingAs($admin)->post(route('admin.users.approve', $pending->user_id))->assertRedirect(route('admin.dashboard'));
        $this->assertTrue($pending->fresh()->verified);
        $this->actingAs($admin)->get('/admin/dashboard')->assertSee('Biboy Del Rosario is now verified.');
        $this->actingAs($admin)->get('/admin/dashboard')->assertDontSee('Biboy Del Rosario');
    }

    public function test_reject_keeps_the_user_but_removes_them_from_the_list(): void
    {
        $pending = $this->user('p@gmail.com', 'tenant', true, 'Mark Santos');
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.users.reject', $pending->user_id));
        $fresh = $pending->fresh();
        $this->assertNotNull($fresh);
        $this->assertFalse($fresh->verified);
        $this->assertNotNull($fresh->verification_rejected_at);
        $this->actingAs($admin)->get('/admin/dashboard')->assertSee('Mark Santos was rejected.');
        $this->actingAs($admin)->get('/admin/dashboard')->assertDontSee('Mark Santos');
    }

    public function test_landlords_and_admins_cannot_be_approved_or_rejected(): void
    {
        $landlord = $this->user('l@gmail.com', 'landlord');
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.users.approve', $landlord->user_id));
        $this->actingAs($admin)->post(route('admin.users.reject', $admin->user_id));
        $this->assertFalse($landlord->fresh()->verified);
        $this->assertNull($admin->fresh()->verification_rejected_at);
        $this->actingAs($admin)->get('/admin/dashboard')->assertSee('User not found.');
    }

    public function test_verify_payment(): void
    {
        $t = $this->user('t@gmail.com', 'tenant', true, 'Anna Cruz');
        $p = Payment::create(['user_id' => $t->user_id, 'amount' => 5000, 'date' => now()->toDateString(), 'status' => 'Pending']);
        $admin = $this->admin();

        $this->actingAs($admin)->get('/admin/dashboard')
            ->assertSee('Anna Cruz')->assertSee('₱5,000.00')
            ->assertSee(route('admin.payments.verify', $p->payment_id), false);

        $this->actingAs($admin)->post(route('admin.payments.verify', $p->payment_id))->assertRedirect(route('admin.dashboard'));
        $this->assertSame('Verified', $p->fresh()->status);
        $this->actingAs($admin)->get('/admin/dashboard')->assertSee('Payment of ₱5,000.00 verified.');

        // Twice → error, and the row now says Completed
        $this->actingAs($admin)->post(route('admin.payments.verify', $p->payment_id));
        $this->actingAs($admin)->get('/admin/dashboard')->assertSee('That payment is already verified.')->assertSee('Completed');
        $this->actingAs($admin)->post(route('admin.payments.verify', 99999));
        $this->actingAs($admin)->get('/admin/dashboard')->assertSee('Payment not found.');
    }

    public function test_tenant_payment_shows_up_for_the_admin(): void
    {
        // The whole G2 → G3 flow: tenant submits, admin verifies, tenant sees Verified.
        $t = $this->user('t@gmail.com', 'tenant', true, 'Anna Cruz');
        $room = Room::create(['room_name' => 'Room 201', 'price' => 4500, 'description' => 'x', 'max_occupants' => 2]);
        Tenancy::create(['user_id' => $t->user_id, 'room_id' => $room->room_id, 'start_date' => '2026-05-01', 'end_date' => '2026-12-01', 'status' => 'Active']);
        $this->actingAs($t)->post('/tenant/payments', ['amount' => '4500', 'date' => now()->toDateString()]);

        $p = Payment::firstOrFail();
        $this->actingAs($this->admin())->post(route('admin.payments.verify', $p->payment_id));
        $this->actingAs($t)->get('/tenant/dashboard')->assertSee('Verified');
    }

    public function test_transactions_newest_twelve(): void
    {
        for ($i = 1; $i <= 13; $i++) {
            Transaction::create(['transaction_id' => sprintf('2026%02d-RM01', $i <= 12 ? $i : 1) . ($i === 13 ? 'X' : ''),
                'type' => 'Rent', 'amount' => 5000, 'date' => $i <= 12 ? sprintf('2025-%02d-01', $i) : '2024-01-01', 'status' => $i % 2 ? 'Completed' : 'Pending']);
        }

        $this->actingAs($this->admin())->get('/admin/dashboard')
            ->assertSee('202612-RM01')->assertSee('2025-12-01')
            ->assertDontSee('202601-RM01X');
    }

    public function test_unverified_admin_can_look_but_not_act(): void
    {
        $admin = $this->user('a2@gmail.com', 'admin', false);
        $t = $this->user('t@gmail.com');
        $p = Payment::create(['user_id' => $t->user_id, 'amount' => 5000, 'date' => now()->toDateString(), 'status' => 'Pending']);

        $this->actingAs($admin)->get('/admin/dashboard')->assertOk();
        $this->actingAs($admin)->post(route('admin.payments.verify', $p->payment_id))->assertRedirect(route('verification.notice'));
        $this->assertSame('Pending', $p->fresh()->status);
    }

    public function test_old_address_redirects(): void
    {
        $this->get('/admin_dashboard.php')->assertRedirect(route('admin.dashboard'));
    }

    public function test_make_admin_command(): void
    {
        $u = $this->user('Kenneth@Gmail.com');

        $this->artisan('rsync:make-admin', ['email' => 'nobody@gmail.com'])->assertFailed();

        $this->artisan('rsync:make-admin', ['email' => 'kenneth@gmail.com'])
            ->expectsConfirmation('Make this user an admin?', 'no')->assertSuccessful();
        $this->assertSame('tenant', $u->fresh()->role);

        $this->artisan('rsync:make-admin', ['email' => 'kenneth@gmail.com'])
            ->expectsConfirmation('Make this user an admin?', 'yes')->assertSuccessful();
        $this->assertSame('admin', $u->fresh()->role);

        $this->artisan('rsync:make-admin', ['email' => 'kenneth@gmail.com'])
            ->expectsOutput('Kenneth@Gmail.com is already an admin.')->assertSuccessful();
    }
}