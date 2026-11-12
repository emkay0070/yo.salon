<?php

namespace Tests\Feature;

use App\Models\Testimonial;
use App\Models\Provider;
use App\Models\Location;
use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TestimonialTest extends TestCase
{
    use RefreshDatabase;

    private Provider $provider;
    private Location $location;
    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->provider = Provider::factory()->create();
        $this->location = Location::factory()->create([
            'provider_id' => $this->provider->id,
        ]);
        $this->customer = Customer::factory()->create();
    }

    public function test_testimonial_starts_without_consent()
    {
        $testimonial = Testimonial::factory()->create([
            'provider_id' => $this->provider->id,
        ]);

        $this->assertFalse($testimonial->consent_given);
        $this->assertNull($testimonial->consent_date);
        $this->assertEquals('pending', $testimonial->status);
    }

    public function test_consent_date_recorded_when_consent_given()
    {
        $testimonial = Testimonial::factory()->create([
            'provider_id' => $this->provider->id,
            'consent_given' => false,
        ]);

        $testimonial->update([
            'consent_given' => true,
            'consent_date' => now(),
        ]);

        $testimonial->refresh();
        $this->assertNotNull($testimonial->consent_date);
    }

    public function test_public_index_returns_only_visible_approved_with_consent()
    {
        Testimonial::factory()->create([
            'provider_id' => $this->provider->id,
            'consent_given' => true,
            'status' => 'approved',
            'visible' => true,
        ]);

        Testimonial::factory()->create([
            'provider_id' => $this->provider->id,
            'consent_given' => false,
            'status' => 'approved',
            'visible' => true,
        ]);

        Testimonial::factory()->create([
            'provider_id' => $this->provider->id,
            'consent_given' => true,
            'status' => 'pending',
            'visible' => true,
        ]);

        $response = $this->getJson("/api/v1/providers/{$this->provider->slug}/testimonials");

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
    }

    public function test_model_scopes()
    {
        $visibleTestimonial = Testimonial::factory()->create([
            'provider_id' => $this->provider->id,
            'consent_given' => true,
            'status' => 'approved',
            'visible' => true,
        ]);

        $hiddenTestimonial = Testimonial::factory()->create([
            'provider_id' => $this->provider->id,
            'consent_given' => false,
            'status' => 'pending',
            'visible' => false,
        ]);

        $publiclyVisible = Testimonial::publiclyVisible()->get();
        $this->assertCount(1, $publiclyVisible);
        $this->assertEquals($visibleTestimonial->id, $publiclyVisible->first()->id);

        $withConsent = Testimonial::withConsent()->get();
        $this->assertCount(1, $withConsent);

        $approved = Testimonial::approved()->get();
        $this->assertCount(1, $approved);
    }

    public function test_provider_relationship()
    {
        $testimonial = Testimonial::factory()->create([
            'provider_id' => $this->provider->id,
        ]);

        $this->assertEquals($this->provider->id, $testimonial->provider->id);
    }

    public function test_location_relationship()
    {
        $testimonial = Testimonial::factory()->create([
            'provider_id' => $this->provider->id,
            'location_id' => $this->location->id,
        ]);

        $this->assertEquals($this->location->id, $testimonial->location->id);
    }

    public function test_customer_relationship_optional()
    {
        $testimonialWithCustomer = Testimonial::factory()->create([
            'provider_id' => $this->provider->id,
            'customer_id' => $this->customer->id,
        ]);

        $testimonialWithoutCustomer = Testimonial::factory()->create([
            'provider_id' => $this->provider->id,
            'customer_id' => null,
        ]);

        $this->assertEquals($this->customer->id, $testimonialWithCustomer->customer->id);
        $this->assertNull($testimonialWithoutCustomer->customer_id);
    }

    public function test_scope_for_provider()
    {
        $otherProvider = Provider::factory()->create();
        
        Testimonial::factory()->create([
            'provider_id' => $this->provider->id,
        ]);

        Testimonial::factory()->create([
            'provider_id' => $otherProvider->id,
        ]);

        $providerTestimonials = Testimonial::forProvider($this->provider->id)->get();
        $this->assertCount(1, $providerTestimonials);
    }

    public function test_scope_for_location()
    {
        $otherLocation = Location::factory()->create([
            'provider_id' => $this->provider->id,
        ]);

        Testimonial::factory()->create([
            'provider_id' => $this->provider->id,
            'location_id' => $this->location->id,
        ]);

        Testimonial::factory()->create([
            'provider_id' => $this->provider->id,
            'location_id' => $otherLocation->id,
        ]);

        $locationTestimonials = Testimonial::forLocation($this->location->id)->get();
        $this->assertCount(1, $locationTestimonials);
    }

    public function test_rating_validation()
    {
        $testimonial = Testimonial::factory()->create([
            'provider_id' => $this->provider->id,
            'rating' => 5,
        ]);

        $this->assertGreaterThanOrEqual(1, $testimonial->rating);
        $this->assertLessThanOrEqual(5, $testimonial->rating);
    }

    public function test_status_transitions()
    {
        $testimonial = Testimonial::factory()->create([
            'provider_id' => $this->provider->id,
            'status' => 'pending',
        ]);

        $testimonial->update(['status' => 'approved']);
        $this->assertEquals('approved', $testimonial->status);

        $testimonial->update(['status' => 'rejected']);
        $this->assertEquals('rejected', $testimonial->status);

        $testimonial->update(['status' => 'flagged']);
        $this->assertEquals('flagged', $testimonial->status);
    }

    public function test_consent_enforcement_at_model_level()
    {
        $testimonial = Testimonial::factory()->create([
            'provider_id' => $this->provider->id,
            'consent_given' => false,
            'visible' => false,
        ]);

        // Without consent, should not be publicly visible
        $publicTestimonials = Testimonial::publiclyVisible()->get();
        $this->assertCount(0, $publicTestimonials);

        // With consent, should be publicly visible if also approved and visible
        $testimonial->update([
            'consent_given' => true,
            'status' => 'approved',
            'visible' => true,
        ]);

        $publicTestimonials = Testimonial::publiclyVisible()->get();
        $this->assertCount(1, $publicTestimonials);
    }
}
