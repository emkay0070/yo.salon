<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RouteDebugTest extends TestCase
{
    #[Test]
    public function customer_payment_initialize_route_exists(): void
    {
        $routes = app('router')->getRoutes();
        foreach ($routes as $route) {
            if (str_contains($route->uri, 'payments/customer/initialize')) {
                $this->assertTrue(true);
                return;
            }
        }
        $this->fail('Route payments/customer/initialize not found');
    }

    #[Test]
    public function customer_payment_initialize_endpoint_responds(): void
    {
        $response = $this->post('/api/payments/customer/initialize', []);
        // Should not be 404 (route not found) - validation errors are expected
        $this->assertNotEquals(404, $response->status());
    }
}
