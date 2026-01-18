<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;
use App\Models\Supplier;
use App\Models\Hospital;
use App\Models\ExternalSupplyRequest;
use Laravel\Sanctum\Sanctum;

class ExternalSupplyRequestNotesTest extends TestCase
{
    use RefreshDatabase;

    protected $supplierUser;
    protected $superAdminUser;
    protected $hospital;
    protected $supplier;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Setup Hospital
        $this->hospital = Hospital::factory()->create();

        // Setup Supplier
        $this->supplier = Supplier::factory()->create();

        // Setup Supplier Admin User
        $this->supplierUser = User::factory()->create([
            'type' => 'supplier_admin',
            'supplier_id' => $this->supplier->id,
            'full_name' => 'Supplier Admin Test'
        ]);

        // Setup Super Admin User
        $this->superAdminUser = User::factory()->create([
            'type' => 'super_admin',
            'full_name' => 'Super Admin Test'
        ]);

        // Setup Dependencies (Drug, etc) - though we might not need items for just notes testing if validation allows
        // Validation in controller checks items presence usually.
        // Let's check logic: SupplyRequestSupplierController::store calls $request->input('items', [])
        // and loop foreach($items). If empty, it creates request with 0 items?
        // Code: $supplyRequest = ExternalSupplyRequest::create(...); then items loop.
        // It doesn't seem to validate items presence strictly BEFORE create (wait, CreateSupplyRequestRequest might).
        // Let's assume we need at least one item if validation requires it.
    }

    public function test_supplier_can_add_initial_note_when_creating_request()
    {
        Sanctum::actingAs($this->supplierUser);

        $payload = [
            'hospital_id' => $this->hospital->id,
            'notes' => 'Initial supplier note',
            'priority' => 'high',
            'items' => [
                 ['drug_id' => 1, 'quantity' => 10] // Mock item, hopefully drug 1 exists or validation skipped? 
                 // CreateSupplyRequestRequest probably validates drug_id exits.
                 // So I need a drug.
            ]
        ];
        
        // Mock Drug
        $drug = \App\Models\Drug::factory()->create();
        $payload['items'][0]['drug_id'] = $drug->id;

        $response = $this->postJson('/api/supplier/supply-requests', $payload);

        $response->assertStatus(201);

        $request = ExternalSupplyRequest::latest()->first();
        $this->assertNotNull($request);
        
        // Check notes structure
        $this->assertIsArray($request->notes);
        $this->assertCount(1, $request->notes);
        $this->assertEquals('Initial supplier note', $request->notes[0]['message']);
        $this->assertEquals('supplier_admin', $request->notes[0]['by']);
    }

    public function test_super_admin_can_append_note_when_approving()
    {
        // 1. Create Request
        $request = ExternalSupplyRequest::factory()->create([
            'supplier_id' => $this->supplier->id,
            'hospital_id' => $this->hospital->id,
            'requested_by' => $this->supplierUser->id,
            'status' => 'pending',
            'notes' => [
                 ['by' => 'supplier_admin', 'message' => 'First note', 'created_at' => now()->toISOString()]
            ]
        ]);

        Sanctum::actingAs($this->superAdminUser);

        // 2. Approve with note
        $response = $this->putJson("/api/super-admin/shipments/{$request->id}/approve", [
            'notes' => 'Approved by Super Admin'
        ]);

        $response->assertStatus(200);

        $request->refresh();
        $this->assertEquals('approved', $request->status);
        $this->assertCount(2, $request->notes);
        $this->assertEquals('Approved by Super Admin', $request->notes[1]['message']);
        $this->assertEquals('super_admin', $request->notes[1]['by']);
    }

    public function test_super_admin_can_append_note_when_rejecting()
    {
        // 1. Create Request
        $request = ExternalSupplyRequest::factory()->create([
            'supplier_id' => $this->supplier->id,
            'hospital_id' => $this->hospital->id,
            'requested_by' => $this->supplierUser->id,
            'status' => 'pending'
        ]);

        Sanctum::actingAs($this->superAdminUser);

        // 2. Reject with note
        $response = $this->putJson("/api/super-admin/shipments/{$request->id}/reject", [
            'notes' => 'Rejected due to X'
        ]);

        $response->assertStatus(200);

        $request->refresh();
        $this->assertEquals('rejected', $request->status);
        $this->assertIsArray($request->notes);
        $this->assertEquals('Rejected due to X', $request->notes[0]['message']);
    }

    public function test_super_admin_gets_legacy_notes_string_in_show()
    {
        // 1. Create Request with complex notes
        $request = ExternalSupplyRequest::factory()->create([
            'supplier_id' => $this->supplier->id,
            'hospital_id' => $this->hospital->id,
            'requested_by' => $this->supplierUser->id,
            'status' => 'pending',
            'notes' => [
                 ['by' => 'supplier_admin', 'message' => 'Legacy Note content', 'created_at' => now()->toIso8601String()],
                 ['by' => 'super_admin', 'message' => 'Reply', 'created_at' => now()->toIso8601String()]
            ]
        ]);

        Sanctum::actingAs($this->superAdminUser);

        $response = $this->getJson("/api/super-admin/shipments/{$request->id}");

        $response->assertStatus(200);
        
        // Assert 'notes' is a string (legacy support)
        $this->assertEquals('Legacy Note content', $response->json('data.notes'));
        
        // Assert 'conversation' is the array
        $this->assertCount(2, $response->json('data.conversation'));
    }
}
