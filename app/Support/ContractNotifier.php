<?php

namespace App\Support;

use App\Models\Tenancy;
use App\Notifications\ContractNotice;

/**
 * Sends ContractNotice to the contract's tenant: first the dashboard notice, then the email.
 * Each step is tried on its own; a failure is written to the log and never stops the request
 * (e.g. Brevo being down must not undo assigning a tenant).
 * Call it AFTER the database transaction is finished.
 */
class ContractNotifier
{
    public static function send(Tenancy $tenancy, string $kind): void
    {
        $tenant = $tenancy->user()->first();
        if (! $tenant) {
            return;
        }

        $notice = ContractNotice::for($tenancy, $kind);

        foreach (['database', 'mail'] as $channel) {
            try {
                $tenant->notifyNow($notice, [$channel]);
            } catch (\Throwable $e) {
                report($e);
            }
        }
    }
}
