<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SeederStatus;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

class SeederController extends Controller
{
    /**
     * Get all seeder statuses
     */
    public function index(Request $request): JsonResponse
    {
        $query = SeederStatus::query();

        if ($request->has('category')) {
            $query->byCategory($request->category);
        }

        if ($request->boolean('required_only', false)) {
            $query->required();
        }

        if ($request->boolean('not_seeded_only', false)) {
            $query->notSeeded();
        }

        $seeders = $query->orderByPriority()->get();

        // Calculate summary statistics
        $total = $seeders->count();
        $seeded = $seeders->where('is_seeded', true)->count();
        $notSeeded = $seeders->where('is_seeded', false)->count();
        $requiredNotSeeded = $seeders->where('is_required', true)->where('is_seeded', false)->count();

        return response()->json([
            'data' => $seeders,
            'summary' => [
                'total' => $total,
                'seeded' => $seeded,
                'not_seeded' => $notSeeded,
                'required_not_seeded' => $requiredNotSeeded,
            ],
        ]);
    }

    /**
     * Get a specific seeder status
     */
    public function show(string $seederClass): JsonResponse
    {
        $seeder = SeederStatus::where('seeder_class', $seederClass)->first();

        if (!$seeder) {
            return response()->json(['message' => 'Seeder not found'], 404);
        }

        return response()->json(['data' => $seeder]);
    }

    /**
     * Run a specific seeder
     */
    public function run(Request $request, string $seederClass): JsonResponse
    {
        $seeder = SeederStatus::where('seeder_class', $seederClass)->first();

        if (!$seeder) {
            return response()->json(['message' => 'Seeder not found'], 404);
        }

        $unseed = $request->boolean('unseed', false);

        try {
            DB::beginTransaction();

            if ($unseed) {
                // For unseeding, we need to implement rollback logic
                // This is seeder-specific, so we'll mark as unseeded
                // The actual data cleanup should be handled by the seeder's down method
                $seeder->markAsUnseeded();

                DB::commit();

                return response()->json([
                    'message' => 'Seeder marked as unseeded. Note: Data cleanup must be handled manually or via migration rollback.',
                    'data' => $seeder->fresh(),
                ]);
            }

            // Run the seeder
            $exitCode = Artisan::call('db:seed', [
                '--class' => $seederClass,
                '--force' => true,
            ]);

            if ($exitCode !== 0) {
                DB::rollBack();
                return response()->json([
                    'message' => 'Seeder execution failed',
                    'output' => Artisan::output(),
                ], 500);
            }

            // Count records (this is approximate, actual count depends on seeder)
            $recordsCount = $this->estimateRecordsCount($seederClass);

            $seeder->markAsSeeded(
                $request->user()->email ?? 'system',
                $recordsCount,
                [
                    'output' => Artisan::output(),
                    'exit_code' => $exitCode,
                ]
            );

            DB::commit();

            return response()->json([
                'message' => 'Seeder executed successfully',
                'data' => $seeder->fresh(),
                'output' => Artisan::output(),
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Seeder execution failed: ' . $e->getMessage(),
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Run multiple seeders at once
     */
    public function runMultiple(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'seeder_classes' => 'required|array',
            'seeder_classes.*' => 'string',
            'unseed' => 'boolean',
        ]);

        $results = [];
        $errors = [];

        foreach ($validated['seeder_classes'] as $seederClass) {
            try {
                $seeder = SeederStatus::where('seeder_class', $seederClass)->first();

                if (!$seeder) {
                    $errors[$seederClass] = 'Seeder not found';
                    continue;
                }

                $exitCode = Artisan::call('db:seed', [
                    '--class' => $seederClass,
                    '--force' => true,
                ]);

                if ($exitCode !== 0) {
                    $errors[$seederClass] = Artisan::output();
                    continue;
                }

                $recordsCount = $this->estimateRecordsCount($seederClass);

                $seeder->markAsSeeded(
                    $request->user()->email ?? 'system',
                    $recordsCount,
                    ['output' => Artisan::output()]
                );

                $results[$seederClass] = [
                    'status' => 'success',
                    'records_count' => $recordsCount,
                ];

            } catch (\Exception $e) {
                $errors[$seederClass] = $e->getMessage();
            }
        }

        return response()->json([
            'message' => 'Batch seeder execution completed',
            'results' => $results,
            'errors' => $errors,
        ]);
    }

    /**
     * Register a new seeder
     */
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'seeder_class' => 'required|string|unique:seeder_statuses,seeder_class',
            'display_name' => 'required|string',
            'description' => 'nullable|string',
            'category' => 'required|string',
            'is_required' => 'boolean',
            'priority' => 'integer',
        ]);

        $seeder = SeederStatus::create([
            'id' => \Illuminate\Support\Str::uuid(),
            'seeder_class' => $validated['seeder_class'],
            'display_name' => $validated['display_name'],
            'description' => $validated['description'] ?? null,
            'category' => $validated['category'],
            'is_required' => $validated['is_required'] ?? false,
            'priority' => $validated['priority'] ?? 0,
        ]);

        return response()->json([
            'message' => 'Seeder registered successfully',
            'data' => $seeder,
        ], 201);
    }

    /**
     * Update seeder metadata
     */
    public function update(Request $request, string $seederClass): JsonResponse
    {
        $seeder = SeederStatus::where('seeder_class', $seederClass)->first();

        if (!$seeder) {
            return response()->json(['message' => 'Seeder not found'], 404);
        }

        $validated = $request->validate([
            'display_name' => 'sometimes|string',
            'description' => 'nullable|string',
            'category' => 'sometimes|string',
            'is_required' => 'sometimes|boolean',
            'priority' => 'sometimes|integer',
        ]);

        $seeder->update($validated);

        return response()->json([
            'message' => 'Seeder updated successfully',
            'data' => $seeder->fresh(),
        ]);
    }

    /**
     * Delete a seeder registration
     */
    public function destroy(string $seederClass): JsonResponse
    {
        $seeder = SeederStatus::where('seeder_class', $seederClass)->first();

        if (!$seeder) {
            return response()->json(['message' => 'Seeder not found'], 404);
        }

        $seeder->delete();

        return response()->json([
            'message' => 'Seeder registration deleted successfully',
        ]);
    }

    /**
     * Estimate records count for a seeder (approximate)
     */
    private function estimateRecordsCount(string $seederClass): int
    {
        // This is a rough estimation based on common seeders
        // In production, you might want to make this more accurate
        $counts = [
            'ReferenceDataSeeder' => 60,
            'UserSeeder' => 10,
            'SalonSeeder' => 5,
            'ServiceSeeder' => 20,
        ];

        return $counts[$seederClass] ?? 0;
    }

    /**
     * Discover and auto-register seeders from the seeders directory
     */
    public function discover(): JsonResponse
    {
        $seedersPath = database_path('seeders');
        $discovered = [];
        $registered = 0;
        $updated = 0;

        if (!is_dir($seedersPath)) {
            return response()->json([
                'message' => 'Seeders directory not found',
                'discovered' => [],
            ], 404);
        }

        $files = scandir($seedersPath);

        foreach ($files as $file) {
            if (pathinfo($file, PATHINFO_EXTENSION) === 'php') {
                $className = pathinfo($file, PATHINFO_FILENAME);

                // Skip the registration seeder itself
                if ($className === 'RegisterSeedersSeeder') {
                    continue;
                }

                $fullClassName = "Database\\Seeders\\{$className}";

                // Check if class exists
                if (!class_exists($fullClassName)) {
                    continue;
                }

                // Check if it's actually a seeder
                if (!is_subclass_of($fullClassName, \Illuminate\Database\Seeder::class)) {
                    continue;
                }

                $discovered[] = [
                    'class' => $fullClassName,
                    'file' => $className,
                ];

                // Auto-register if not exists
                $existing = SeederStatus::where('seeder_class', $fullClassName)->first();

                if (!$existing) {
                    SeederStatus::create([
                        'id' => \Illuminate\Support\Str::uuid(),
                        'seeder_class' => $fullClassName,
                        'display_name' => $this->generateDisplayName($className),
                        'description' => 'Auto-discovered seeder',
                        'category' => $this->guessCategory($className),
                        'is_required' => false,
                        'priority' => 0,
                    ]);
                    $registered++;
                } else {
                    // Update display name if it's the default
                    if ($existing->display_name === $className) {
                        $existing->update([
                            'display_name' => $this->generateDisplayName($className),
                        ]);
                        $updated++;
                    }
                }
            }
        }

        return response()->json([
            'message' => 'Seeder discovery completed',
            'discovered' => $discovered,
            'registered' => $registered,
            'updated' => $updated,
        ]);
    }

    /**
     * Generate a human-readable display name from class name
     */
    private function generateDisplayName(string $className): string
    {
        // Remove "Seeder" suffix
        $name = str_replace('Seeder', '', $className);
        
        // Convert CamelCase to Title Case
        $name = preg_replace('/([A-Z])/', ' $1', $name);
        $name = trim($name);
        
        return ucwords(strtolower($name));
    }

    /**
     * Guess category from seeder class name
     */
    private function guessCategory(string $className): string
    {
        $lowerName = strtolower($className);

        if (str_contains($lowerName, 'user') || str_contains($lowerName, 'customer') || str_contains($lowerName, 'staff')) {
            return 'users';
        }

        if (str_contains($lowerName, 'salon') || str_contains($lowerName, 'service') || str_contains($lowerName, 'business')) {
            return 'business';
        }

        if (str_contains($lowerName, 'reference') || str_contains($lowerName, 'platform') || str_contains($lowerName, 'config')) {
            return 'platform';
        }

        if (str_contains($lowerName, 'content') || str_contains($lowerName, 'blog') || str_contains($lowerName, 'media')) {
            return 'content';
        }

        return 'general';
    }
}
