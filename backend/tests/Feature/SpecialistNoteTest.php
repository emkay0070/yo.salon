<?php

namespace Tests\Feature;

use App\Models\SpecialistNote;
use App\Models\Customer;
use App\Models\Specialist;
use App\Models\Provider;
use App\Models\Booking;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SpecialistNoteTest extends TestCase
{
    use RefreshDatabase;

    private Specialist $specialist;
    private Customer $customer;
    private Provider $provider;

    protected function setUp(): void
    {
        parent::setUp();

        // Create provider
        $this->provider = Provider::create([
            'id' => Str::uuid(),
            'name' => 'Test Provider',
            'display_name' => 'Test Provider Display',
            'slug' => 'test-provider',
            'active' => true,
        ]);

        // Create specialist
        $this->specialist = Specialist::create([
            'id' => Str::uuid(),
            'name' => 'Test Specialist',
            'active' => true,
            'provider_id' => $this->provider->id,
        ]);

        // Create customer
        $this->customer = Customer::create([
            'id' => Str::uuid(),
            'name' => 'Test Customer',
            'email' => 'test@example.com',
            'phone' => '1234567890',
        ]);
    }

    public function test_note_creation_with_canonical_fields()
    {
        $note = SpecialistNote::create([
            'customer_id' => $this->customer->id,
            'specialist_id' => $this->specialist->id,
            'provider_id' => $this->provider->id,
            'note' => 'Test note content',
            'note_type' => 'general',
        ]);

        $this->assertNotNull($note);
        $this->assertEquals($this->customer->id, $note->customer_id);
        $this->assertEquals($this->specialist->id, $note->specialist_id);
        $this->assertEquals('Test note content', $note->note);
        $this->assertEquals('general', $note->note_type);
    }

    public function test_note_creation_without_booking()
    {
        $note = SpecialistNote::create([
            'customer_id' => $this->customer->id,
            'specialist_id' => $this->specialist->id,
            'provider_id' => $this->provider->id,
            'booking_id' => null,
            'note' => 'Test note without booking',
        ]);

        $this->assertNotNull($note);
        $this->assertNull($note->booking_id);
    }

    public function test_note_specialist_customer_scoping()
    {
        $note = SpecialistNote::create([
            'customer_id' => $this->customer->id,
            'specialist_id' => $this->specialist->id,
            'provider_id' => $this->provider->id,
            'note' => 'Test note',
        ]);

        // Query by customer should find it
        $foundByCustomer = SpecialistNote::byCustomer($this->customer->id)
            ->where('id', $note->id)
            ->exists();
        $this->assertTrue($foundByCustomer);

        // Query by specialist should find it
        $foundBySpecialist = SpecialistNote::bySpecialist($this->specialist->id)
            ->where('id', $note->id)
            ->exists();
        $this->assertTrue($foundBySpecialist);

        // Combined query should find it
        $foundCombined = SpecialistNote::where('customer_id', $this->customer->id)
            ->where('specialist_id', $this->specialist->id)
            ->where('id', $note->id)
            ->exists();
        $this->assertTrue($foundCombined);
    }

    public function test_note_visibility_scopes()
    {
        $note = SpecialistNote::create([
            'customer_id' => $this->customer->id,
            'specialist_id' => $this->specialist->id,
            'provider_id' => $this->provider->id,
            'note' => 'Test note',
            'customer_visible' => true,
            'is_important' => true,
            'is_private' => false,
        ]);

        // Customer visible scope
        $visible = SpecialistNote::customerVisible()
            ->where('id', $note->id)
            ->exists();
        $this->assertTrue($visible);

        // Important scope
        $important = SpecialistNote::important()
            ->where('id', $note->id)
            ->exists();
        $this->assertTrue($important);

        // Private scope
        $private = SpecialistNote::private()
            ->where('id', $note->id)
            ->exists();
        $this->assertFalse($private);
    }

    public function test_note_type_scoping()
    {
        $note = SpecialistNote::create([
            'customer_id' => $this->customer->id,
            'specialist_id' => $this->specialist->id,
            'provider_id' => $this->provider->id,
            'note' => 'Preference note',
            'note_type' => 'preference',
        ]);

        $found = SpecialistNote::byType('preference')
            ->where('id', $note->id)
            ->exists();
        $this->assertTrue($found);

        $notFound = SpecialistNote::byType('observation')
            ->where('id', $note->id)
            ->exists();
        $this->assertFalse($notFound);
    }

    public function test_no_salon_id_in_fillable()
    {
        $this->assertNotContains('salon_id', (new SpecialistNote())->getFillable());
    }

    public function test_no_salon_relationship()
    {
        $note = SpecialistNote::create([
            'customer_id' => $this->customer->id,
            'specialist_id' => $this->specialist->id,
            'provider_id' => $this->provider->id,
            'note' => 'Test note',
        ]);

        // salon relationship should not exist
        $this->assertFalse(method_exists($note, 'salon'));
    }

    public function test_no_bySalon_scope()
    {
        // bySalon scope should not exist
        $this->assertFalse(method_exists(SpecialistNote::class, 'scopeBySalon'));
    }

    public function test_valid_note_without_booking_accepted()
    {
        $note = SpecialistNote::create([
            'customer_id' => $this->customer->id,
            'specialist_id' => $this->specialist->id,
            'provider_id' => $this->provider->id,
            'booking_id' => null,
            'note' => 'Test note without booking',
        ]);

        $this->assertNotNull($note);
        $this->assertNull($note->booking_id);
    }

    public function test_specialist_cannot_retrieve_another_specialists_notes()
    {
        // Create another specialist
        $otherSpecialist = Specialist::create([
            'id' => Str::uuid(),
            'name' => 'Other Specialist',
            'active' => true,
            'provider_id' => $this->provider->id,
        ]);

        // Create a note by the original specialist
        $note = SpecialistNote::create([
            'customer_id' => $this->customer->id,
            'specialist_id' => $this->specialist->id,
            'provider_id' => $this->provider->id,
            'note' => 'Original specialist note',
        ]);

        // Query by other specialist should NOT find it
        $foundByOther = SpecialistNote::where('customer_id', $this->customer->id)
            ->where('specialist_id', $otherSpecialist->id)
            ->where('id', $note->id)
            ->exists();
        $this->assertFalse($foundByOther);

        // Query by original specialist should find it
        $foundByOriginal = SpecialistNote::where('customer_id', $this->customer->id)
            ->where('specialist_id', $this->specialist->id)
            ->where('id', $note->id)
            ->exists();
        $this->assertTrue($foundByOriginal);
    }
}
