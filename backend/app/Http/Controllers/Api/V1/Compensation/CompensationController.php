<?php

namespace App\Http\Controllers\Api\V1\Compensation;

use App\Http\Controllers\Controller;
use App\Models\Salon;
use Illuminate\Http\Request;

abstract class CompensationController extends Controller
{
    /**
     * Verify the authenticated user manages the requested salon.
     */
    protected function authorizeSalonManager(string $salonSlug): Salon
    {
        $salon = auth()->user()->currentSalon();

        if (!$salon || $salon->slug !== $salonSlug) {
            abort(403, 'Unauthorized access to salon compensation.');
        }

        return $salon;
    }
}
