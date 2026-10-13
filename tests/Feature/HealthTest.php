<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class HealthTest extends TestCase
{
    public function test_health_is_public_and_returns_the_exact_contract(): void
    {
        $response = $this->get('/api/v1/health');

        $response->assertOk()
            ->assertHeader('Content-Type', 'application/json')
            ->assertExactJson(['status' => 'ok']);

        $this->assertSame('{"status":"ok"}', $response->getContent());
    }

    public function test_health_does_not_open_a_database_connection(): void
    {
        config(['database.default' => 'unconfigured']);

        $this->assertSame([], DB::getConnections());

        $this->getJson('/api/v1/health')->assertOk();

        $this->assertSame([], DB::getConnections());
    }

    public function test_health_remains_public_and_generated_routes_are_not_registered(): void
    {
        $routes = app('router')->getRoutes();

        $health = $routes->getByName('health');
        $this->assertSame('api/v1/health', $health->uri());
        $this->assertSame(['GET', 'HEAD'], $health->methods());
        $this->assertNotContains('jwt', $health->gatherMiddleware());

        $this->getJson('/')->assertNotFound();
        $this->getJson('/up')->assertNotFound();
        $this->getJson('/storage/missing')->assertNotFound();
    }
}
