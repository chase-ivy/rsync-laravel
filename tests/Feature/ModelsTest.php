<?php

namespace Tests\Feature;

use App\Models\MaintenanceRequest;
use App\Models\PasswordReset;
use App\Models\Payment;
use App\Models\Report;
use App\Models\Room;
use App\Models\RoomApplication;
use App\Models\RoomImage;
use App\Models\Tenancy;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModelsTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $email, string $role = 'tenant'): User
    {
        return User::create(['name' => ucfirst($role), 'age' => 21, 'email' => $email, 'password' => 'x', 'role' => $role]);
    }

    public function test_rooms_images_owner_tenancies_and_applications(): void
    {
        $landlord = $this->user('mae@gmail.com', 'landlord');
        $tenant = $this->user('kobe@gmail.com');

        $room = Room::create(['owner_id' => $landlord->user_id, 'room_name' => 'Room 201', 'price' => 4500, 'max_occupants' => 4]);
        $room->refresh();
        $this->assertSame('Available', $room->status);
        $this->assertNotNull($room->created_at);

        RoomImage::create(['room_id' => $room->room_id, 'filename' => 'b.jpg', 'sort_order' => 1]);
        RoomImage::create(['room_id' => $room->room_id, 'filename' => 'a.jpg', 'sort_order' => 0]);
        $this->assertSame(['a.jpg', 'b.jpg'], $room->images->pluck('filename')->all());

        Tenancy::create(['user_id' => $tenant->user_id, 'room_id' => $room->room_id, 'start_date' => '2026-05-01', 'end_date' => '2026-06-01', 'status' => 'Active']);
        Tenancy::create(['user_id' => $tenant->user_id, 'room_id' => $room->room_id, 'start_date' => '2026-01-01', 'end_date' => '2026-02-01', 'status' => 'Left']);
        $this->assertSame(2, $room->tenancies()->count());
        $this->assertSame(1, $room->activeTenancies()->count());

        RoomApplication::create(['user_id' => $tenant->user_id, 'room_id' => $room->room_id, 'status' => 'Pending']);

        $this->assertSame($landlord->user_id, $room->owner->user_id);
        $this->assertSame([$room->room_id], $landlord->ownedRooms->pluck('room_id')->all());
        $this->assertSame('Room 201', $tenant->tenancies->first()->room->room_name);
        $this->assertSame('Pending', $tenant->applications->first()->status);
        $this->assertNotNull($tenant->applications->first()->fresh()->applied_at);
    }

    public function test_one_application_per_user_and_room(): void
    {
        $tenant = $this->user('kobe@gmail.com');
        $room = Room::create(['room_name' => 'R', 'price' => 1, 'max_occupants' => 1]);
        RoomApplication::create(['user_id' => $tenant->user_id, 'room_id' => $room->room_id]);

        $this->expectException(UniqueConstraintViolationException::class);
        RoomApplication::create(['user_id' => $tenant->user_id, 'room_id' => $room->room_id]);
    }

    public function test_deleting_a_room_removes_its_images_tenancies_and_applications(): void
    {
        $tenant = $this->user('kobe@gmail.com');
        $room = Room::create(['room_name' => 'R', 'price' => 1, 'max_occupants' => 1]);
        RoomImage::create(['room_id' => $room->room_id, 'filename' => 'a.jpg']);
        Tenancy::create(['user_id' => $tenant->user_id, 'room_id' => $room->room_id, 'start_date' => '2026-01-01', 'end_date' => '2026-02-01']);
        RoomApplication::create(['user_id' => $tenant->user_id, 'room_id' => $room->room_id]);

        $room->delete();

        $this->assertSame(0, RoomImage::count());
        $this->assertSame(0, Tenancy::count());
        $this->assertSame(0, RoomApplication::count());
    }

    public function test_deleting_an_owner_keeps_the_room_without_owner(): void
    {
        $landlord = $this->user('mae@gmail.com', 'landlord');
        $room = Room::create(['owner_id' => $landlord->user_id, 'room_name' => 'R', 'price' => 1, 'max_occupants' => 1]);

        $landlord->delete();

        $this->assertNull($room->fresh()->owner_id);
    }

    public function test_maintenance_payments_reports_and_password_resets(): void
    {
        $tenant = $this->user('kobe@gmail.com');
        $room = Room::create(['room_name' => 'R', 'price' => 1, 'max_occupants' => 1]);

        $req = MaintenanceRequest::create(['tenant_id' => $tenant->user_id, 'room_id' => $room->room_id, 'description' => 'Leaky faucet']);
        $this->assertSame('Pending', $req->fresh()->status);
        $this->assertSame('kobe@gmail.com', $req->tenant->email);
        $this->assertSame(1, $room->maintenanceRequests()->count());

        $pay = Payment::create(['user_id' => $tenant->user_id, 'amount' => 5000, 'date' => '2026-05-01']);
        $this->assertSame('5000.00', $pay->fresh()->amount);
        $this->assertSame('Pending', $pay->fresh()->status);
        $this->assertSame(1, $tenant->payments()->count());

        Report::create(['user_id' => $tenant->user_id, 'room_id' => $room->room_id, 'subject' => 'Noise', 'message' => 'Loud']);
        $this->assertSame(1, $tenant->reports()->count());

        $reset = PasswordReset::create(['email' => 'kobe@gmail.com', 'otp' => '123456', 'expires_at' => now()->addMinutes(10)]);
        $this->assertFalse($reset->fresh()->used);
        $this->assertArrayNotHasKey('otp', $reset->toArray());
    }

    public function test_transactions_use_a_text_key(): void
    {
        Transaction::create(['transaction_id' => '202605-RM01', 'type' => 'Rental Payment', 'amount' => 5000, 'date' => '2026-05-01']);

        $found = Transaction::find('202605-RM01');
        $this->assertSame('Rental Payment', $found->type);
        $this->assertSame('202605-RM01', $found->getKey());
    }
}