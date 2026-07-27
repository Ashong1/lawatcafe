<?php

namespace Tests\Feature;

use App\Models\Sale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KdsDataEndpointTest extends TestCase
{
    use RefreshDatabase;

    public function test_kds_data_endpoint_returns_html_and_count(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        Sale::create([
            'transaction_number' => 'TRN-TEST0001',
            'total_amount' => 100,
            'status' => 'pending',
            'user_id' => $staff->id,
        ]);
        Sale::create([
            'transaction_number' => 'TRN-TEST0002',
            'total_amount' => 50,
            'status' => 'preparing',
            'user_id' => $staff->id,
        ]);
        Sale::create([
            'transaction_number' => 'TRN-TEST0003',
            'total_amount' => 75,
            'status' => 'completed',
            'user_id' => $staff->id,
        ]);

        $response = $this->actingAs($staff)->getJson(route('kds.data'));

        $response->assertStatus(200)
            ->assertJsonStructure(['html', 'count'])
            ->assertJson(['count' => 2]);

        // The partial only displays the last 4 characters of the transaction number.
        $this->assertStringContainsString('0001', $response->json('html'));
        $this->assertStringContainsString('0002', $response->json('html'));
        $this->assertStringNotContainsString('0003', $response->json('html'));
    }

    public function test_kds_data_endpoint_requires_authentication(): void
    {
        $response = $this->getJson(route('kds.data'));

        $response->assertStatus(401);
    }
}
