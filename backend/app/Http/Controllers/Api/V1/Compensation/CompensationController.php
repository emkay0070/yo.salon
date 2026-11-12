<?php

namespace App\Http\Controllers\Api\V1\Compensation;

use App\Http\Controllers\Controller;
use App\Models\Provider;
use Illuminate\Http\Request;

abstract class CompensationController extends Controller
{
    /**
     * Verify the authenticated user manages the requested provider.
     * Accepts either provider slug or UUID.
     */
    protected function authorizeProviderManager(string $providerIdentifier): Provider
    {
        $provider = auth()->user()->currentProvider();

        if (!$provider) {
            abort(403, 'Unauthorized access to provider compensation.');
        }

        // Check if identifier matches either slug or UUID
        if ($provider->slug !== $providerIdentifier && $provider->id !== $providerIdentifier) {
            abort(403, 'Unauthorized access to provider compensation.');
        }

        return $provider;
    }
}
