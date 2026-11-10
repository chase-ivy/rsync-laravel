<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DemoAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * Part H2 — settings that matter on Render.
 */
class ProductionSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_sessions_and_cache_tables_exist(): void
    {
        $this->assertTrue(Schema::hasTable('sessions'));
        $this->assertTrue(Schema::hasTable('cache'));
        $this->assertTrue(Schema::hasTable('cache_locks'));
    }

    public function test_links_use_https_behind_renders_proxy(): void
    {
        $this->get('/', [
            'X-Forwarded-Proto' => 'https',
            'X-Forwarded-Host' => 'rsync.onrender.com',
            'X-Forwarded-Port' => '443',
        ])->assertOk()->assertSee('https://rsync.onrender.com/rooms', false);
    }

    public function test_production_forces_https_links(): void
    {
        $this->app['env'] = 'production';
        (new \App\Providers\AppServiceProvider($this->app))->boot();

        $this->assertStringStartsWith('https://', URL::to('/rooms'));
    }

    public function test_demo_accounts_are_never_created_in_production(): void
    {
        $this->app['env'] = 'production';

        (new DemoAccountsSeeder)->run();

        $this->assertSame(0, User::where('email', 'like', '%@rsync.test')->count());
    }

    public function test_health_check_for_render(): void
    {
        $this->get('/up')->assertOk();
    }
}