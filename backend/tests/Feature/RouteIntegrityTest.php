<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class RouteIntegrityTest extends TestCase
{
    /**
     * Every registered controller route must point at a method that exists.
     * A dead target produces a 500 (BadMethodCallException) on every request.
     */
    public function test_every_controller_route_action_resolves_to_an_existing_method(): void
    {
        $broken = [];

        foreach (Route::getRoutes() as $route) {
            $action = $route->getActionName();

            if (!str_contains($action, '@')) {
                continue;
            }

            [$class, $method] = explode('@', $action, 2);

            if (!class_exists($class) || !method_exists($class, $method)) {
                $broken[] = implode('|', $route->methods()) . ' ' . $route->uri() . ' -> ' . $action;
            }
        }

        $this->assertSame(
            [],
            $broken,
            "Routes point at missing controller methods:\n" . implode("\n", $broken)
        );
    }
}
