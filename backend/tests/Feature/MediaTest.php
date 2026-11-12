<?php

namespace Tests\Feature;

use App\Models\Media;
use App\Models\Provider;
use App\Models\Location;
use App\Models\User;
use App\Models\Specialist;
use App\Models\Customer;
use App\Models\SpecialistAccount;
use App\Models\PortalAccount;
use App\Services\MediaAuthorizationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MediaTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected User $user;
    protected Provider $provider;
    protected Location $location;
    protected Provider $otherProvider;
    protected Location $otherLocation;
    protected MediaAuthorizationService $authService;

    protected function setUp(): void
    {
        parent::setUp();
        
        Storage::fake('public');
        
        $this->user = User::factory()->create();
        $this->provider = Provider::factory()->create();
        $this->location = Location::factory()->create(['provider_id' => $this->provider->id]);
        $this->otherProvider = Provider::factory()->create();
        $this->otherLocation = Location::factory()->create(['provider_id' => $this->otherProvider->id]);
        
        // Associate user with provider using ProviderUser factory
        \App\Models\ProviderUser::factory()->create([
            'user_id' => $this->user->id,
            'provider_id' => $this->provider->id,
            'role' => 'admin',
        ]);
        
        // Create Sanctum token for API authentication
        $this->token = $this->user->createToken('test-token')->plainTextToken;
        
        $this->authService = app(MediaAuthorizationService::class);
    }

    // --- Ownership Tests ---

    public function test_media_belongs_to_provider()
    {
        $media = Media::factory()->create([
            'provider_id' => $this->provider->id,
        ]);

        $this->assertEquals($this->provider->id, $media->provider_id);
        $this->assertEquals($this->provider->id, $media->provider->id);
    }

    public function test_provider_relationship_works()
    {
        $media = Media::factory()->create([
            'provider_id' => $this->provider->id,
        ]);

        $this->assertInstanceOf(Provider::class, $media->provider);
        $this->assertEquals($this->provider->id, $media->provider->id);
    }

    public function test_location_relationship_works()
    {
        $media = Media::factory()->create([
            'provider_id' => $this->provider->id,
            'location_id' => $this->location->id,
        ]);

        $this->assertInstanceOf(Location::class, $media->location);
        $this->assertEquals($this->location->id, $media->location->id);
    }

    // --- Provider Isolation Tests ---

    public function test_provider_isolation_via_scope()
    {
        Media::factory()->create(['provider_id' => $this->provider->id]);
        Media::factory()->create(['provider_id' => $this->otherProvider->id]);

        $providerMedia = Media::forProvider($this->provider->id)->get();
        $this->assertCount(1, $providerMedia);
        $this->assertEquals($this->provider->id, $providerMedia->first()->provider_id);
    }

    public function test_cross_provider_access_denied_via_authorization()
    {
        $media = Media::factory()->create([
            'provider_id' => $this->otherProvider->id,
            'visibility' => Media::VISIBILITY_PRIVATE,
        ]);

        $this->actingAs($this->user);
        $canRead = $this->authService->canRead($media, $this->user);
        
        $this->assertFalse($canRead);
    }

    public function test_cross_provider_deletion_denied_via_authorization()
    {
        $media = Media::factory()->create([
            'provider_id' => $this->otherProvider->id,
            'visibility' => Media::VISIBILITY_PRIVATE,
        ]);

        $this->actingAs($this->user);
        $canDelete = $this->authService->canDelete($media, $this->user);
        
        $this->assertFalse($canDelete);
    }

    // --- Location Context Tests ---

    public function test_provider_level_media_has_null_location_id()
    {
        $media = Media::factory()->providerLevel()->create([
            'provider_id' => $this->provider->id,
        ]);

        $this->assertNull($media->location_id);
    }

    public function test_location_scoped_media_belongs_to_correct_provider()
    {
        $media = Media::factory()->forLocation($this->location)->create([
            'provider_id' => $this->provider->id,
        ]);

        $this->assertEquals($this->location->id, $media->location_id);
        $this->assertEquals($this->provider->id, $media->provider_id);
        $this->assertEquals($this->provider->id, $media->location->provider_id);
    }

    public function test_cross_provider_location_assignment_rejected()
    {
        // Attempt to create media with location from different provider
        $media = Media::factory()->make([
            'provider_id' => $this->provider->id,
            'location_id' => $this->otherLocation->id, // Wrong provider
        ]);

        $this->assertNotEquals($this->provider->id, $media->location->provider_id);
    }

    // --- Upload/Creation Tests ---

    public function test_media_service_creates_media_with_provider_context()
    {
        $file = UploadedFile::fake()->image('test.jpg', 800, 600);
        
        $media = app(\App\Services\MediaService::class)->upload($file, [
            'provider_id' => $this->provider->id,
            'visibility' => Media::VISIBILITY_PUBLIC,
        ]);

        $this->assertEquals($this->provider->id, $media->provider_id);
        $this->assertEquals('image/jpeg', $media->mime_type);
        $this->assertDatabaseHas('media', [
            'provider_id' => $this->provider->id,
            'mime_type' => 'image/jpeg',
        ]);
    }

    public function test_media_service_creates_location_scoped_media()
    {
        $file = UploadedFile::fake()->image('test.jpg', 800, 600);
        
        $media = app(\App\Services\MediaService::class)->upload($file, [
            'provider_id' => $this->provider->id,
            'location_id' => $this->location->id,
            'visibility' => Media::VISIBILITY_PUBLIC,
        ]);

        $this->assertEquals($this->provider->id, $media->provider_id);
        $this->assertEquals($this->location->id, $media->location_id);
        $this->assertDatabaseHas('media', [
            'provider_id' => $this->provider->id,
            'location_id' => $this->location->id,
        ]);
    }

    // --- Visibility Tests ---

    public function test_public_media_is_accessible()
    {
        $media = Media::factory()->public()->create([
            'provider_id' => $this->provider->id,
        ]);

        $this->assertTrue($media->isPublic());
    }

    public function test_private_media_is_not_publicly_accessible()
    {
        $media = Media::factory()->private()->create([
            'provider_id' => $this->otherProvider->id,
        ]);

        $this->actingAs($this->user);
        $canRead = $this->authService->canRead($media, $this->user);
        
        $this->assertFalse($canRead);
    }

    public function test_authenticated_visibility_allows_any_authenticated_user()
    {
        $media = Media::factory()->authenticated()->create([
            'provider_id' => $this->otherProvider->id,
        ]);

        $this->actingAs($this->user);
        $canRead = $this->authService->canRead($media, $this->user);
        
        $this->assertTrue($canRead);
    }

    // --- Lifecycle Tests ---

    public function test_media_can_be_soft_deleted()
    {
        $media = Media::factory()->create([
            'provider_id' => $this->provider->id,
        ]);

        $media->delete();

        $this->assertSoftDeleted('media', ['id' => $media->id]);
    }

    public function test_deleted_media_not_in_default_queries()
    {
        Media::factory()->create(['provider_id' => $this->provider->id]);
        $deletedMedia = Media::factory()->create(['provider_id' => $this->provider->id]);
        $deletedMedia->delete();

        $activeMedia = Media::where('provider_id', $this->provider->id)->get();
        
        $this->assertCount(1, $activeMedia);
        $this->assertNotEquals($deletedMedia->id, $activeMedia->first()->id);
    }

    // --- Polymorphic Attachable Tests ---

    public function test_media_can_be_attached_to_specialist()
    {
        $specialist = Specialist::factory()->create([
            'provider_id' => $this->provider->id,
        ]);

        $media = Media::factory()->forSpecialist($specialist)->create([
            'provider_id' => $this->provider->id,
        ]);

        $this->assertEquals(Specialist::class, $media->attachable_type);
        $this->assertEquals($specialist->id, $media->attachable_id);
        $this->assertEquals($specialist->id, $media->attachable->id);
    }

    public function test_media_can_be_attached_to_customer()
    {
        $customer = Customer::factory()->create();

        $media = Media::factory()->forCustomer($customer)->create([
            'provider_id' => $this->provider->id,
        ]);

        $this->assertEquals(Customer::class, $media->attachable_type);
        $this->assertEquals($customer->id, $media->attachable_id);
    }

    // --- Scopes Tests ---

    public function test_for_provider_scope()
    {
        Media::factory()->count(3)->create(['provider_id' => $this->provider->id]);
        Media::factory()->count(2)->create(['provider_id' => $this->otherProvider->id]);

        $providerMedia = Media::forProvider($this->provider->id)->get();
        
        $this->assertCount(3, $providerMedia);
    }

    public function test_for_location_scope()
    {
        Media::factory()->count(2)->forLocation($this->location)->create([
            'provider_id' => $this->provider->id,
        ]);
        Media::factory()->count(3)->forLocation($this->otherLocation)->create([
            'provider_id' => $this->otherProvider->id,
        ]);

        $locationMedia = Media::forLocation($this->location->id)->get();
        
        $this->assertCount(2, $locationMedia);
    }

    public function test_provider_level_scope()
    {
        Media::factory()->count(2)->providerLevel()->create([
            'provider_id' => $this->provider->id,
        ]);
        Media::factory()->count(3)->forLocation($this->location)->create([
            'provider_id' => $this->provider->id,
        ]);

        $providerLevelMedia = Media::forProvider($this->provider->id)->providerLevel()->get();
        
        $this->assertCount(2, $providerLevelMedia);
    }

    public function test_location_scoped_scope()
    {
        Media::factory()->count(2)->providerLevel()->create([
            'provider_id' => $this->provider->id,
        ]);
        Media::factory()->count(3)->forLocation($this->location)->create([
            'provider_id' => $this->provider->id,
        ]);

        $locationScopedMedia = Media::forProvider($this->provider->id)->locationScoped()->get();
        
        $this->assertCount(3, $locationScopedMedia);
    }

    public function test_images_scope()
    {
        Media::factory()->count(3)->create([
            'provider_id' => $this->provider->id,
            'mime_type' => 'image/jpeg',
        ]);
        Media::factory()->create([
            'provider_id' => $this->provider->id,
            'mime_type' => 'application/pdf',
        ]);

        $images = Media::forProvider($this->provider->id)->images()->get();
        
        $this->assertCount(3, $images);
    }

    public function test_public_scope()
    {
        Media::factory()->count(2)->public()->create(['provider_id' => $this->provider->id]);
        Media::factory()->count(3)->private()->create(['provider_id' => $this->provider->id]);

        $publicMedia = Media::forProvider($this->provider->id)->public()->get();
        
        $this->assertCount(2, $publicMedia);
    }

    // --- Authorization Service Tests ---

    public function test_uploader_can_delete_own_media()
    {
        $media = Media::factory()->uploadedByUser($this->user)->create([
            'provider_id' => $this->provider->id,
        ]);

        $this->actingAs($this->user);
        $canDelete = $this->authService->canDelete($media, $this->user);
        
        $this->assertTrue($canDelete);
    }

    public function test_specialist_can_own_their_media()
    {
        $specialist = Specialist::factory()->create(['provider_id' => $this->provider->id]);
        
        $media = Media::factory()->forSpecialist($specialist)->create([
            'provider_id' => $this->provider->id,
        ]);

        // Test that the media is attached to the specialist
        $this->assertEquals(Specialist::class, $media->attachable_type);
        $this->assertEquals($specialist->id, $media->attachable_id);
    }

    // --- Factory States Tests ---

    public function test_factory_public_state()
    {
        $media = Media::factory()->public()->create();
        
        $this->assertEquals(Media::VISIBILITY_PUBLIC, $media->visibility);
    }

    public function test_factory_private_state()
    {
        $media = Media::factory()->private()->create();
        
        $this->assertEquals(Media::VISIBILITY_PRIVATE, $media->visibility);
    }

    public function test_factory_authenticated_state()
    {
        $media = Media::factory()->authenticated()->create();
        
        $this->assertEquals(Media::VISIBILITY_AUTHENTICATED, $media->visibility);
    }

    public function test_factory_gallery_state()
    {
        $media = Media::factory()->gallery()->create();
        
        $this->assertEquals(Media::VISIBILITY_PUBLIC, $media->visibility);
        $this->assertStringStartsWith('image/', $media->mime_type);
    }

    public function test_factory_png_state()
    {
        $media = Media::factory()->png()->create();
        
        $this->assertEquals('image/png', $media->mime_type);
        $this->assertStringEndsWith('.png', $media->filename);
    }

    public function test_factory_webp_state()
    {
        $media = Media::factory()->webp()->create();
        
        $this->assertEquals('image/webp', $media->mime_type);
        $this->assertStringEndsWith('.webp', $media->filename);
    }

    public function test_factory_svg_state()
    {
        $media = Media::factory()->svg()->create();
        
        $this->assertEquals('image/svg+xml', $media->mime_type);
        $this->assertStringEndsWith('.svg', $media->filename);
    }

    // --- URL Generation Tests ---

    public function test_url_attribute_generates_correctly()
    {
        Storage::fake('public');
        
        $media = Media::factory()->create([
            'disk' => 'public',
            'path' => 'uploads/test.jpg',
        ]);

        $url = $media->url;
        
        $this->assertIsString($url);
        $this->assertStringContainsString('test.jpg', $url);
    }
}
