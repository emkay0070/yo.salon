<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Provider;
use App\Models\BusinessCategory;
use App\Services\OnboardingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Str;
use Tests\TestCase;

class BusinessCategoryOnboardingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Seed business categories
        $this->artisan('db:seed', ['--class' => 'BusinessCategorySeeder']);
    }

    public function test_onboarding_persists_selected_business_category()
    {
        // Create a user
        $user = User::factory()->create();

        // Get a valid business category (e.g., spa)
        $spaCategory = BusinessCategory::where('slug', 'spa')->first();
        $this->assertNotNull($spaCategory, 'Spa category should exist');

        // Simulate onboarding data with spa category
        $onboardingData = [
            'business' => [
                'name' => 'Test Spa',
                'category' => 'spa',
                'description' => 'A test spa',
                'phone' => '+1234567890',
                'email' => 'test@spa.com',
            ],
        ];

        // Complete onboarding
        $onboardingService = app(OnboardingService::class);
        $provider = $onboardingService->complete($user, $onboardingData);

        // Assert provider was created
        $this->assertNotNull($provider);
        $this->assertEquals('Test Spa', $provider->display_name);

        // Assert business_category_id was persisted
        $this->assertEquals($spaCategory->id, $provider->business_category_id);

        // Assert the relationship works
        $this->assertNotNull($provider->businessCategory);
        $this->assertEquals('spa', $provider->businessCategory->slug);
        $this->assertEquals('Spa', $provider->businessCategory->name);
    }

    public function test_onboarding_fails_with_invalid_category_slug()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Invalid business category: invalid-category');

        $user = User::factory()->create();

        $onboardingData = [
            'business' => [
                'name' => 'Test Business',
                'category' => 'invalid-category',
            ],
        ];

        $onboardingService = app(OnboardingService::class);
        $onboardingService->complete($user, $onboardingData);
    }

    public function test_onboarding_fails_with_null_category()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Invalid business category: none');

        $user = User::factory()->create();

        $onboardingData = [
            'business' => [
                'name' => 'Test Business',
                'category' => null,
            ],
        ];

        $onboardingService = app(OnboardingService::class);
        $onboardingService->complete($user, $onboardingData);
    }

    public function test_auth_response_includes_business_category()
    {
        // Create a user with a provider that has a business category
        $user = User::factory()->create();
        $spaCategory = BusinessCategory::where('slug', 'spa')->first();

        $provider = Provider::factory()->create([
            'business_category_id' => $spaCategory->id,
        ]);

        $user->providers()->attach($provider->id, ['id' => Str::uuid(), 'role' => 'owner']);

        // Login and get response
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertStatus(200);

        // Assert the response includes providers with businessCategory
        $responseData = $response->json();
        $this->assertArrayHasKey('user', $responseData);
        $this->assertArrayHasKey('providers', $responseData['user']);

        $providers = $responseData['user']['providers'];
        $this->assertNotEmpty($providers);

        $firstProvider = $providers[0];
        $this->assertArrayHasKey('business_category', $firstProvider);
        $this->assertEquals('spa', $firstProvider['business_category']['slug']);
    }

    public function test_provider_business_category_relationship()
    {
        $spaCategory = BusinessCategory::where('slug', 'spa')->first();

        $provider = Provider::factory()->create([
            'business_category_id' => $spaCategory->id,
        ]);

        // Test the relationship
        $this->assertNotNull($provider->businessCategory);
        $this->assertEquals($spaCategory->id, $provider->businessCategory->id);
        $this->assertEquals('spa', $provider->businessCategory->slug);
        $this->assertEquals('Therapist', $provider->businessCategory->default_specialist_label);
        $this->assertEquals('Treatment', $provider->businessCategory->default_appointment_label);
        $this->assertEquals('Guest', $provider->businessCategory->default_customer_term);
    }

    public function test_hair_salon_category_provides_correct_terminology()
    {
        $hairSalonCategory = BusinessCategory::where('slug', 'hair_salon')->first();
        $this->assertNotNull($hairSalonCategory);

        $provider = Provider::factory()->create([
            'business_category_id' => $hairSalonCategory->id,
        ]);

        $this->assertEquals('Stylist', $provider->businessCategory->default_specialist_label);
        $this->assertEquals('Stylists', $provider->businessCategory->default_specialist_plural);
        $this->assertEquals('Appointment', $provider->businessCategory->default_appointment_label);
        $this->assertEquals('Client', $provider->businessCategory->default_customer_term);
    }

    public function test_spa_category_provides_correct_terminology()
    {
        $spaCategory = BusinessCategory::where('slug', 'spa')->first();
        $this->assertNotNull($spaCategory);

        $provider = Provider::factory()->create([
            'business_category_id' => $spaCategory->id,
        ]);

        $this->assertEquals('Therapist', $provider->businessCategory->default_specialist_label);
        $this->assertEquals('Therapists', $provider->businessCategory->default_specialist_plural);
        $this->assertEquals('Treatment', $provider->businessCategory->default_appointment_label);
        $this->assertEquals('Guest', $provider->businessCategory->default_customer_term);
    }
}
