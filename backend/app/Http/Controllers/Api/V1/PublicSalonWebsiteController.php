<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Salon;
use App\Services\WebsiteConfigurationResolver;
use App\DataTransferObjects\EffectiveWebsiteConfiguration;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class PublicSalonWebsiteController extends Controller
{
    protected WebsiteConfigurationResolver $resolver;

    public function __construct(WebsiteConfigurationResolver $resolver)
    {
        $this->resolver = $resolver;
    }

    /**
     * Get the effective website configuration for a salon by slug
     */
    public function show(Request $request, string $slug): JsonResponse
    {
        $salon = Salon::where('slug', $slug)
            ->where('active', true)
            ->first();

        if (!$salon) {
            return response()->json(['error' => 'Salon not found'], 404);
        }

        $config = $this->resolver->resolve($salon);
        $effectiveConfig = EffectiveWebsiteConfiguration::fromArray($config);

        return response()->json([
            'salon' => $effectiveConfig->salon,
            'brand' => $effectiveConfig->brand,
            'theme' => $effectiveConfig->theme,
            'sections' => $effectiveConfig->sections,
            'navigation' => $effectiveConfig->navigation,
            'colors' => $effectiveConfig->getFinalColors(),
        ]);
    }
}
