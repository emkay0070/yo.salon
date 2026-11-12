<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Models\Provider;
use App\Models\Service;
use App\Models\Specialist;

class TrackViewCount
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Only track GET requests
        if ($request->isMethod('GET')) {
            $this->trackView($request);
        }

        return $response;
    }

    /**
     * Track view based on route pattern
     */
    protected function trackView(Request $request): void
    {
        $route = $request->route();
        if (!$route) return;

        $uri = $request->path();

        // Track provider views (via slug)
        if (preg_match('#^providers/([^/]+)$#', $uri, $matches)) {
            $provider = Provider::where('slug', $matches[1])->first();
            if ($provider) {
                $provider->increment('view_count');
            }
        }

        // Track service views
        if (preg_match('#^services/([^/]+)$#', $uri, $matches)) {
            $service = Service::find($matches[1]);
            if ($service) {
                $service->increment('view_count');
            }
        }

        // Track specialist views
        if (preg_match('#^specialists/([^/]+)$#', $uri, $matches)) {
            $specialist = Specialist::where('id', $matches[1])->orWhere('handle', $matches[1])->first();
            if ($specialist) {
                $specialist->increment('view_count');
            }
        }
    }
}
