<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * ONE-TIME copy of the legacy RSYNC data from local MariaDB (XAMPP) into the new
 * PostgreSQL database (Supabase).
 *
 * - Only READS from MariaDB.
 * - Refuses to run unless every target table is empty (no duplicates, no overwrites).
 * - Keeps the original IDs, then moves each ID counter past the highest copied ID.
 * - Runs inside one transaction: if anything fails, nothing is written.
 */
class ImportLegacyData extends Command
{
    protected $signature = 'rsync:import-legacy {--dry-run : Only count the rows, write nothing}';

    protected $description = 'Copy RSYNC data from the legacy MariaDB database into PostgreSQL (one time)';

    /** Parent tables first, so foreign keys are satisfied. Value = auto-increment key (null = none). */
    private array $tables = [
        'users'                => 'user_id',
        'rooms'                => 'room_id',
        'room_images'          => 'image_id',
        'tenants'              => 'tenant_id',
        'applications'         => 'application_id',
        'password_resets'      => 'id',
        'maintenance_requests' => 'request_id',
        'payments'             => 'payment_id',
        'transactions'         => null,
        'reports'              => 'report_id',
    ];

    /** MariaDB tinyint(1) columns that are boolean in PostgreSQL. */
    private array $booleans = [
        'users'           => ['verified'],
        'password_resets' => ['used'],
    ];

    public function handle(): int
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->error('The default connection must be PostgreSQL (DB_CONNECTION=pgsql). Nothing was copied.');
            return self::FAILURE;
        }

        foreach (['LEGACY_DB_DATABASE'] as $required) {
            if (! env($required)) {
                $this->error("$required is not set in .env. Nothing was copied.");
                return self::FAILURE;
            }
        }

        config(['database.connections.legacy' => [
            'driver'    => 'mariadb',
            'host'      => env('LEGACY_DB_HOST', '127.0.0.1'),
            'port'      => env('LEGACY_DB_PORT', '3307'),
            'database'  => env('LEGACY_DB_DATABASE'),
            'username'  => env('LEGACY_DB_USERNAME', 'root'),
            'password'  => env('LEGACY_DB_PASSWORD', ''),
            'charset'   => 'utf8mb4',
            'collation' => 'utf8mb4_general_ci',
            'prefix'    => '',
            'strict'    => false,
        ]]);

        $legacy = DB::connection('legacy');
        $target = DB::connection();

        try {
            $legacy->getPdo();
        } catch (Throwable $e) {
            $this->error('Cannot connect to the legacy MariaDB database: ' . $e->getMessage());
            return self::FAILURE;
        }

        $this->info('Source: MariaDB ' . $legacy->getDatabaseName() . ' on ' . env('LEGACY_DB_HOST', '127.0.0.1') . ':' . env('LEGACY_DB_PORT', '3307'));
        $tz = $legacy->selectOne('SELECT @@session.time_zone AS tz, @@system_time_zone AS sys, NOW() AS now');
        $this->line("MariaDB time zone: {$tz->tz} (system: {$tz->sys}), MariaDB clock now: {$tz->now}");
        $this->line('PostgreSQL clock now: ' . $target->selectOne('SELECT LOCALTIMESTAMP(0) AS now')->now
            . ' (time zone ' . $target->selectOne('SHOW TIME ZONE')->TimeZone . ')');
        $this->line('Both clocks should show the same local time. If not, stop and tell me.');
        $this->info('Target: PostgreSQL ' . $target->getDatabaseName() . ' on ' . config('database.connections.' . $target->getName() . '.host'));

        // Safety: every target table must exist and be empty.
        foreach (array_keys($this->tables) as $table) {
            if (! Schema::hasTable($table)) {
                $this->error("Target table '$table' does not exist. Run php artisan migrate first. Nothing was copied.");
                return self::FAILURE;
            }
            if ($target->table($table)->exists()) {
                $this->error("Target table '$table' already has rows. The import only runs into empty tables. Nothing was copied.");
                return self::FAILURE;
            }
        }

        $counts = [];
        foreach (array_keys($this->tables) as $table) {
            $counts[$table] = $legacy->table($table)->count();
        }

        if ($this->option('dry-run')) {
            $this->table(['Table', 'Rows that would be copied'], collect($counts)->map(fn ($n, $t) => [$t, $n])->values());
            $this->info('Dry run only: nothing was written.');
            return self::SUCCESS;
        }

        $target->transaction(function () use ($legacy, $target) {
            foreach ($this->tables as $table => $key) {
                // Copy only the columns that exist in BOTH databases.
                $columns = array_values(array_intersect(
                    Schema::connection('legacy')->getColumnListing($table),
                    Schema::getColumnListing($table)
                ));
                $order = $key ?? $columns[0];

                $legacy->table($table)->select($columns)->orderBy($order)
                    ->chunk(500, function ($rows) use ($target, $table) {
                        $batch = [];
                        foreach ($rows as $row) {
                            $row = (array) $row;
                            foreach ($this->booleans[$table] ?? [] as $col) {
                                if (array_key_exists($col, $row) && $row[$col] !== null) {
                                    $row[$col] = (bool) $row[$col];
                                }
                            }
                            $batch[] = $row;
                        }
                        $target->table($table)->insert($batch);
                    });

                if ($key !== null) {
                    // Move the ID counter past the highest copied ID so new rows don't collide.
                    $target->statement(
                        "SELECT setval(pg_get_serial_sequence('$table', '$key'), COALESCE((SELECT MAX($key) FROM $table), 1), (SELECT MAX($key) FROM $table) IS NOT NULL)"
                    );
                }
            }
        });

        $rows = [];
        $ok = true;
        foreach ($counts as $table => $n) {
            $copied = $target->table($table)->count();
            $ok = $ok && $copied === $n;
            $rows[] = [$table, $n, $copied, $copied === $n ? 'OK' : 'MISMATCH'];
        }
        $this->table(['Table', 'MariaDB rows', 'PostgreSQL rows', 'Check'], $rows);

        $ok ? $this->info('Import finished: all row counts match.')
            : $this->error('Row counts do not match — tell me before using the database.');

        return $ok ? self::SUCCESS : self::FAILURE;
    }
}   