<?php

namespace Tests\Feature;

use App\Models\JaspestEnquiry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JaspestAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_booking_is_saved_to_database(): void
    {
        $response = $this->postJson('/jaspest/enquiries', [
            'full_name' => 'Budi Santoso',
            'whatsapp' => '081234567890',
            'address' => 'Jl. Darmo, Surabaya',
            'estimated_area' => 100,
            'need' => 'konsultasi',
            'property_type' => 'rumah',
            'description' => 'Terdapat rayap pada kusen pintu.',
            'consent' => '1',
        ]);

        $response->assertCreated()->assertJsonStructure(['reference']);
        $this->assertDatabaseHas('jaspest_enquiries', [
            'full_name' => 'Budi Santoso',
            'whatsapp' => '6281234567890',
            'status' => 'new',
            'consent' => 1,
        ]);
    }

    public function test_guest_cannot_open_admin_dashboard(): void
    {
        $this->get('/admin/jaspest')->assertRedirect();
    }

    public function test_authorized_admin_can_change_booking_status(): void
    {
        $user = User::factory()->create();
        config(['jaspest_admin.editor_user_ids' => [(string) $user->getAuthIdentifier()]]);
        $this->actingAs($user);

        $booking = JaspestEnquiry::create([
            'reference' => 'JAS-TEST-000001',
            'full_name' => 'Dewi Kartika',
            'whatsapp' => '6281234567890',
            'address' => 'Surabaya',
            'estimated_area' => 90,
            'need' => 'konsultasi',
            'property_type' => 'rumah',
            'description' => 'Semut di dapur.',
            'consent' => true,
            'status' => 'new',
        ]);

        $this->patchJson('/admin/jaspest/api/bookings/'.$booking->reference.'/status', [
            'status' => 'contacted',
        ])->assertOk()->assertJsonPath('status', 'contacted');

        $this->assertDatabaseHas('jaspest_enquiries', [
            'id' => $booking->id,
            'status' => 'contacted',
        ]);
    }
}
