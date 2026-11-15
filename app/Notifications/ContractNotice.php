<?php

namespace App\Notifications;

use App\Models\Tenancy;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells a tenant about a new contract (landlord assigned them) or a changed one (landlord edited it).
 * Two channels: an email, and a row in `notifications` shown on the tenant dashboard.
 * Sent right away (not queued) through App\Support\ContractNotifier, which keeps a failed
 * email from breaking the landlord's request.
 */
class ContractNotice extends Notification
{
    public const NEW = 'new';

    public const UPDATED = 'updated';

    public function __construct(
        public readonly string $kind,          // NEW or UPDATED
        public readonly int $tenancyId,
        public readonly string $roomName,
        public readonly string $landlordName,
    ) {
    }

    /** Builds the notice from a contract (room and landlord names are copied, so the notice still reads well later). */
    public static function for(Tenancy $tenancy, string $kind): self
    {
        $tenancy->loadMissing('room.owner');

        return new self($kind, (int) $tenancy->tenant_id, (string) ($tenancy->room?->room_name ?? 'your room'), (string) ($tenancy->room?->owner?->name ?? 'your landlord'));
    }

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    /** "You have a new rental contract" / "Your rental contract was updated". */
    public function title(): string
    {
        return $this->kind === self::NEW ? 'You have a new rental contract' : 'Your rental contract was updated';
    }

    /** One plain sentence for the email and the dashboard. */
    public function sentence(): string
    {
        return $this->kind === self::NEW
            ? "{$this->landlordName} added you as a tenant of {$this->roomName}. Please review and accept your contract."
            : "{$this->landlordName} changed your contract for {$this->roomName}. Please review the new terms and accept them again.";
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->title())
            ->greeting('Hello ' . $notifiable->name . ',')
            ->line($this->sentence())
            ->line('Room: ' . $this->roomName)
            ->line('Landlord: ' . $this->landlordName)
            ->action('View my contract', route('contracts.show', $this->tenancyId))
            ->salutation('— RSYNC Team');
    }

    /** Saved in notifications.data (JSON) for the tenant dashboard. */
    public function toArray(object $notifiable): array
    {
        return [
            'kind' => $this->kind,
            'tenancy_id' => $this->tenancyId,
            'room_name' => $this->roomName,
            'landlord_name' => $this->landlordName,
            'title' => $this->title(),
            'message' => $this->sentence(),
        ];
    }
}
