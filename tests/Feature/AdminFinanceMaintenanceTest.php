<?php

namespace Tests\Feature;

use App\Models\MaintenanceRequest;
use App\Models\Payment;
use App\Models\Room;
use App\Models\Tenancy;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Part G5 — admin Finance (report.php) and admin Maintenance (maintainance.php).
 */
class AdminFinanceMaintenanceTest extends TestCase
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

    private function rent(User $u, string $room = 'Deluxe Room', int $price = 6500): Room
    {
        $r = Room::create(['room_name' => $room, 'price' => $price, 'max_occupants' => 2]);
        Tenancy::create(['user_id' => $u->user_id, 'room_id' => $r->room_id, 'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'status' => 'Active']);

        return $r;
    }

    // ---------- Finance ----------

    public function test_only_admins_can_open_finance_and_maintenance(): void
    {
        foreach (['/admin/finance', '/admin/maintenance'] as $url) {
            $this->get($url)->assertRedirect(route('login'));
            $this->actingAs($this->user("t{$url[7]}@gmail.com"))->get($url)->assertRedirect(route('login'));
        }
        $admin = $this->admin();
        $this->actingAs($admin)->get('/admin/finance')->assertOk()
            ->assertSee('Tenant Total Amount')->assertSee('Monthly Income Summary')
            ->assertSee('Record Transaction')->assertSee('Tenant Transactions');
        $this->actingAs($admin)->get('/admin/maintenance')->assertOk()
            ->assertSee('Maintenance Requests')->assertSee('Maintenance Status Report');
    }

    public function test_tenant_totals_use_rent_and_payments(): void
    {
        $juan = $this->user('juan@gmail.com', 'tenant', 'Juan Dela Cruz');
        $this->rent($juan, 'Deluxe Room', 6500);
        Payment::create(['user_id' => $juan->user_id, 'amount' => 6500, 'date' => now()->toDateString(), 'status' => 'Verified']);
        Payment::create(['user_id' => $juan->user_id, 'amount' => 500, 'date' => now()->toDateString(), 'status' => 'Verified']);
        Payment::create(['user_id' => $juan->user_id, 'amount' => 1200, 'date' => now()->toDateString(), 'status' => 'Pending']);

        $res = $this->actingAs($this->admin())->get('/admin/finance');
        $row = $res->viewData('rows')->first();
        $this->assertSame(['tenant' => 'Juan Dela Cruz', 'room' => 'Deluxe Room', 'rent' => 6500.0, 'paid' => 7000.0, 'waiting' => 1200.0], $row);
        $res->assertSee('₱7,000.00')->assertSee('₱1,200.00')
            ->assertSee(now()->format('F'))->assertSee('monthlyEarningsChart', false);
    }

    public function test_record_transaction_makes_the_id_automatically(): void
    {
        $juan = $this->user('juan@gmail.com', 'tenant', 'Juan Dela Cruz');
        Transaction::create(['transaction_id' => '202605-RM01', 'type' => 'Rental Payment', 'amount' => 5000, 'date' => '2026-05-01', 'status' => 'Completed']);
        Transaction::create(['transaction_id' => '202605-RM03', 'type' => 'Rental Payment', 'amount' => 5000, 'date' => '2026-05-03', 'status' => 'Completed']);
        $admin = $this->admin();

        $this->actingAs($admin)->post('/admin/transactions', ['user_id' => $juan->user_id, 'type' => 'Deposit', 'amount' => '4500', 'date' => '2026-05-20', 'status' => 'Pending'])
            ->assertRedirect(route('admin.finance'));
        $this->actingAs($admin)->post('/admin/transactions', ['user_id' => '', 'type' => 'Other', 'amount' => '100', 'date' => '2026-06-02', 'status' => 'Completed']);

        $new = Transaction::find('202605-RM04');
        $this->assertNotNull($new, 'after RM03 comes RM04');
        $this->assertSame($juan->user_id, $new->user_id);
        $this->assertSame('Pending', $new->status);
        $this->assertNull(Transaction::find('202606-RM01')->user_id);

        $this->actingAs($admin)->get('/admin/finance')
            ->assertSee('Transaction 202606-RM01 recorded.')
            ->assertSee('Juan Dela Cruz');
    }

    public function test_record_transaction_rules(): void
    {
        $landlord = $this->user('l@gmail.com', 'landlord');
        $admin = $this->admin();

        $this->actingAs($admin)->post('/admin/transactions', ['type' => 'Deposit', 'amount' => '100', 'date' => now()->addDay()->toDateString(), 'status' => 'Pending']);
        $this->actingAs($admin)->get('/admin/finance')->assertSee('The date cannot be in the future.');

        $this->actingAs($admin)->post('/admin/transactions', ['user_id' => $landlord->user_id, 'type' => 'Deposit', 'amount' => '100', 'date' => '2026-05-01', 'status' => 'Pending']);
        $this->actingAs($admin)->get('/admin/finance')->assertSee('Please pick a tenant from the list.');

        $this->actingAs($admin)->post('/admin/transactions', ['type' => 'Bribe', 'amount' => '100', 'date' => '2026-05-01', 'status' => 'Pending']);
        $this->actingAs($admin)->post('/admin/transactions', ['type' => 'Deposit', 'amount' => '0', 'date' => '2026-05-01', 'status' => 'Pending']);

        $this->assertSame(0, Transaction::count());
    }

    public function test_mark_transaction_as_paid(): void
    {
        Transaction::create(['transaction_id' => '202605-RM02', 'type' => 'Rental Payment', 'amount' => 4500, 'date' => '2026-05-02', 'status' => 'Pending']);
        $admin = $this->admin();

        $this->actingAs($admin)->get('/admin/finance')->assertSee('Mark as Paid');
        $this->actingAs($admin)->post('/admin/transactions/paid', ['transaction_id' => '202605-RM02'])->assertRedirect(route('admin.finance'));
        $this->assertSame('Completed', Transaction::find('202605-RM02')->status);
        $this->actingAs($admin)->get('/admin/finance')->assertSee('202605-RM02 marked as paid.')->assertDontSee('Mark as Paid');

        $this->actingAs($admin)->post('/admin/transactions/paid', ['transaction_id' => '202605-RM02']);
        $this->actingAs($admin)->get('/admin/finance')->assertSee('202605-RM02 is already completed.');
        $this->actingAs($admin)->post('/admin/transactions/paid', ['transaction_id' => 'nope']);
        $this->actingAs($admin)->get('/admin/finance')->assertSee('Transaction not found.');
    }

    // ---------- Maintenance ----------

    public function test_admin_sees_requests_from_all_rooms_and_moves_them_along(): void
    {
        $juan = $this->user('juan@gmail.com', 'tenant', 'Juan Dela Cruz');
        $room = $this->rent($juan);
        $req = MaintenanceRequest::create(['tenant_id' => $juan->user_id, 'room_id' => $room->room_id, 'description' => 'Leaking faucet', 'status' => 'Pending']);
        $admin = $this->admin();

        $this->actingAs($admin)->get('/admin/maintenance')
            ->assertSee(sprintf('MR-%03d', $req->request_id))->assertSee('Juan Dela Cruz')->assertSee('Deluxe Room')
            ->assertSee('Leaking faucet')->assertSee('N/A')
            ->assertSee('Total Requests:</strong> 1', false)->assertSee('Pending:</strong> 1', false);

        $this->actingAs($admin)->post('/admin/maintenance/status', ['request_id' => $req->request_id, 'status' => 'In Progress'])
            ->assertRedirect(route('admin.maintenance'));
        $this->assertSame('In Progress', $req->fresh()->status);
        $this->assertNull($req->fresh()->resolved_at);

        $this->actingAs($admin)->post('/admin/maintenance/status', ['request_id' => $req->request_id, 'status' => 'Completed']);
        $this->assertSame('Completed', $req->fresh()->status);
        $this->assertSame(now()->toDateString(), $req->fresh()->resolved_at->toDateString());
        $this->actingAs($admin)->get('/admin/maintenance')
            ->assertSee(sprintf('MR-%03d marked Completed.', $req->request_id))
            ->assertSee('Completed:</strong> 1', false)
            ->assertSee(now()->format('Y-m-d'));

        // The tenant sees it too
        $this->actingAs($juan)->get('/tenant/dashboard')->assertSee('badge-av', false);
    }

    public function test_maintenance_rules(): void
    {
        $juan = $this->user('juan@gmail.com');
        $room = $this->rent($juan);
        $done = MaintenanceRequest::create(['tenant_id' => $juan->user_id, 'room_id' => $room->room_id, 'description' => 'x', 'status' => 'Completed']);
        $open = MaintenanceRequest::create(['tenant_id' => $juan->user_id, 'room_id' => $room->room_id, 'description' => 'y', 'status' => 'Pending']);
        $admin = $this->admin();

        $this->actingAs($admin)->post('/admin/maintenance/status', ['request_id' => $done->request_id, 'status' => 'In Progress']);
        $this->assertSame('Completed', $done->fresh()->status);
        $this->actingAs($admin)->get('/admin/maintenance')->assertSee('is already completed.');

        $this->actingAs($admin)->post('/admin/maintenance/status', ['request_id' => $open->request_id, 'status' => 'Pending']);
        $this->actingAs($admin)->get('/admin/maintenance')->assertSee('Unknown status.');
        $this->actingAs($admin)->post('/admin/maintenance/status', ['request_id' => 99999, 'status' => 'Completed']);
        $this->actingAs($admin)->get('/admin/maintenance')->assertSee('Request not found.');
        $this->assertSame('Pending', $open->fresh()->status);
    }

    public function test_unverified_admin_can_look_but_not_act(): void
    {
        $admin = $this->user('a2@gmail.com', 'admin', 'Boss', false);
        $juan = $this->user('juan@gmail.com');
        $room = $this->rent($juan);
        $req = MaintenanceRequest::create(['tenant_id' => $juan->user_id, 'room_id' => $room->room_id, 'description' => 'x', 'status' => 'Pending']);

        $this->actingAs($admin)->get('/admin/finance')->assertOk();
        $this->actingAs($admin)->get('/admin/maintenance')->assertOk();
        $this->actingAs($admin)->post('/admin/maintenance/status', ['request_id' => $req->request_id, 'status' => 'Completed'])
            ->assertRedirect(route('verification.notice'));
        $this->actingAs($admin)->post('/admin/transactions', ['type' => 'Deposit', 'amount' => '100', 'date' => '2026-05-01', 'status' => 'Pending'])
            ->assertRedirect(route('verification.notice'));
        $this->assertSame('Pending', $req->fresh()->status);
        $this->assertSame(0, Transaction::count());
    }

    public function test_nav_links_and_old_addresses(): void
    {
        $this->actingAs($this->admin())->get('/admin/dashboard')
            ->assertSee('href="' . route('admin.finance') . '"', false)
            ->assertSee('href="' . route('admin.maintenance') . '"', false);
        $this->get('/report.php')->assertRedirect(route('admin.finance'));
        $this->get('/maintainance.php')->assertRedirect(route('admin.maintenance'));
    }
}