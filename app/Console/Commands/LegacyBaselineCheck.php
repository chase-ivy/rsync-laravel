<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * RSYNC baseline check (works on PostgreSQL/Supabase and MariaDB).
 * READ-ONLY: runs only SELECT queries. It never writes to the database.
 */
class LegacyBaselineCheck extends Command
{
    protected $signature = 'rsync:baseline';

    protected $description = 'Read-only checks of the RSYNC database from Laravel';

    private array $expected = [
        'users'                => ['user_id', 'name', 'age', 'email', 'email_verified_at', 'password', 'role', 'verified', 'created_at'],
        'rooms'                => ['room_id', 'owner_id', 'room_name', 'price', 'description', 'status', 'max_occupants', 'created_at'],
        'room_images'          => ['image_id', 'room_id', 'filename', 'sort_order'],
        'tenants'              => ['tenant_id', 'user_id', 'room_id', 'start_date', 'end_date', 'status'],
        'applications'         => ['application_id', 'user_id', 'room_id', 'status', 'applied_at'],
        'password_resets'      => ['id', 'email', 'otp', 'expires_at', 'used', 'created_at'],
        'maintenance_requests' => ['request_id', 'tenant_id', 'room_id', 'description', 'status', 'submitted_at'],
        'payments'             => ['payment_id', 'user_id', 'amount', 'date', 'status'],
        'transactions'         => ['transaction_id', 'type', 'amount', 'date', 'status'],
        'reports'              => ['report_id', 'user_id', 'room_id', 'subject', 'message', 'status', 'created_at'],
    ];

    public function handle(): int
    {
        $driver = DB::connection()->getDriverName();

        // 1. Connection
        try {
            $info = $driver === 'pgsql'
                ? DB::selectOne('SELECT current_database() AS db, version() AS version')
                : DB::selectOne('SELECT DATABASE() AS db, VERSION() AS version');
        } catch (Throwable $e) {
            $this->error('Cannot connect: ' . $e->getMessage());
            return self::FAILURE;
        }
        $this->info("Connected ($driver) to database: " . $info->db);
        $this->line('Server version: ' . $info->version);
        $this->newLine();

        // 2. Tables and columns
        $rows = [];
        foreach ($this->expected as $table => $columns) {
            if (! Schema::hasTable($table)) {
                $rows[] = [$table, 'MISSING TABLE', '-'];
                continue;
            }
            $missing = array_values(array_filter($columns, fn ($c) => ! Schema::hasColumn($table, $c)));
            $rows[] = [$table, $missing ? 'MISSING: ' . implode(', ', $missing) : 'OK', DB::table($table)->count()];
        }
        $this->table(['Table', 'Columns', 'Rows'], $rows);

        // 3. Legacy room query (oop.php get_rooms), unchanged SQL
        try {
            $result = DB::select("SELECT r.*, COUNT(DISTINCT t.tenant_id) AS current_occupants,
                    (SELECT filename FROM room_images ri WHERE ri.room_id = r.room_id ORDER BY ri.sort_order ASC LIMIT 1) AS cover_image
                FROM rooms r
                LEFT JOIN tenants t ON t.room_id = r.room_id AND t.status = 'Active'
                GROUP BY r.room_id
                ORDER BY r.created_at DESC");
            $this->info('Legacy get_rooms query: OK (' . count($result) . ' rows)');
        } catch (Throwable $e) {
            $this->warn('Legacy get_rooms query FAILED: ' . $e->getMessage());
        }

        // 4. Supabase security: RLS on and no API grants
        if ($driver === 'pgsql') {
            $this->newLine();
            $noRls = collect(DB::select("SELECT c.relname FROM pg_class c JOIN pg_namespace n ON n.oid = c.relnamespace
                WHERE n.nspname = 'public' AND c.relkind = 'r' AND NOT c.relrowsecurity ORDER BY 1"))->pluck('relname');
            $noRls->isEmpty()
                ? $this->info('Row Level Security: ON for every table in public')
                : $this->error('Row Level Security is OFF for: ' . $noRls->implode(', '));

            $hasAnon = DB::selectOne("SELECT EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'anon') AS e")->e;
            if ($hasAnon) {
                $exposed = collect(DB::select("SELECT DISTINCT table_name FROM information_schema.role_table_grants
                    WHERE table_schema = 'public' AND grantee IN ('anon', 'authenticated') ORDER BY 1"))->pluck('table_name');
                $exposed->isEmpty()
                    ? $this->info('Supabase API roles (anon, authenticated): no access to any table')
                    : $this->error('Supabase API roles still have access to: ' . $exposed->implode(', '));
            } else {
                $this->line('Supabase API roles not present (plain PostgreSQL) — API check skipped.');
            }
        }

        // 5. Password hash compatibility (optional)
        $this->newLine();
        if ($this->confirm('Test a legacy password with Laravel Hash::check? (needs an account whose password you know)', false)) {
            $email = $this->ask('Email of that account');
            $hash = DB::table('users')->whereRaw('LOWER(email) = LOWER(?)', [$email])->value('password');
            if ($hash === null) {
                $this->error('No user with that email.');
            } else {
                $password = $this->secret('Password (not shown, not stored)');
                Hash::check($password, $hash)
                    ? $this->info('Hash::check = TRUE (legacy passwords work in Laravel)')
                    : $this->error('Hash::check = FALSE (wrong password, or incompatible hash)');
                $this->line('Laravel would want to rehash this password: ' . (Hash::needsRehash($hash) ? 'YES' : 'NO'));
            }
        }

        return self::SUCCESS;
    }
}