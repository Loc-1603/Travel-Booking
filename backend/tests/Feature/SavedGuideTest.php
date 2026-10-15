<?php

use App\Models\SavedGuide;
use App\Models\TourProvider;
use App\Models\User;

beforeEach(function (): void {
    $this->vendor = User::create([
        'name' => 'Saved Guide Vendor', 'email' => 'saved-guide-vendor@test.local',
        'password' => bcrypt('password'), 'role' => 'vendor', 'status' => 'active',
    ]);
    $this->customer = User::create([
        'name' => 'Saved Guide Customer', 'email' => 'saved-guide-customer@test.local',
        'password' => bcrypt('password'), 'role' => 'customer', 'status' => 'active',
    ]);

    $this->provider = TourProvider::create([
        'vendor_id' => $this->vendor->id, 'business_name' => 'Saved Guide A',
        'bio' => 'Local expert', 'languages' => ['vi', 'en'], 'status' => 'approved',
    ]);
    $this->pendingProvider = TourProvider::create([
        'vendor_id' => $this->vendor->id, 'business_name' => 'Pending Guide B',
        'status' => 'pending',
    ]);
});

test('guest cannot access saved guides', function (): void {
    $this->getJson('/api/v1/saved-guides')->assertUnauthorized();
    $this->postJson('/api/v1/saved-guides', ['provider_uuid' => $this->provider->uuid])->assertUnauthorized();
    $this->deleteJson('/api/v1/saved-guides/'.$this->provider->uuid)->assertUnauthorized();
});

test('saved guides crud', function (): void {
    $this->actingAs($this->customer)->getJson('/api/v1/saved-guides')
        ->assertOk()
        ->assertJsonPath('data.data', []);

    $store = $this->actingAs($this->customer)->postJson('/api/v1/saved-guides', [
        'provider_uuid' => $this->provider->uuid,
    ])->assertCreated();
    expect($store->json('data.guide.uuid'))->toBe($this->provider->uuid)
        ->and($store->json('data.guide.business_name'))->toBe('Saved Guide A');

    // Idempotent re-save
    $this->actingAs($this->customer)->postJson('/api/v1/saved-guides', [
        'provider_uuid' => $this->provider->uuid,
    ])->assertCreated();
    expect(SavedGuide::count())->toBe(1);

    // Index lists the guide with created_at
    $list = $this->actingAs($this->customer)->getJson('/api/v1/saved-guides')->assertOk();
    expect($list->json('data.data.0.guide.uuid'))->toBe($this->provider->uuid)
        ->and($list->json('data.data.0.created_at'))->not->toBeNull();

    // Saved list is per-user: another customer sees nothing.
    $other = User::create([
        'name' => 'Other Customer', 'email' => 'saved-guide-other@test.local',
        'password' => bcrypt('password'), 'role' => 'customer', 'status' => 'active',
    ]);
    $this->actingAs($other)->getJson('/api/v1/saved-guides')
        ->assertOk()
        ->assertJsonPath('data.data', []);

    $this->actingAs($this->customer)
        ->deleteJson('/api/v1/saved-guides/'.$this->provider->uuid)
        ->assertNoContent();
    expect(SavedGuide::count())->toBe(0);

    // Second delete -> 404
    $this->actingAs($this->customer)
        ->deleteJson('/api/v1/saved-guides/'.$this->provider->uuid)
        ->assertNotFound();
});

test('saved guides reject unknown or unapproved providers', function (): void {
    $this->actingAs($this->customer)->postJson('/api/v1/saved-guides', [
        'provider_uuid' => '00000000-0000-0000-0000-000000000000',
    ])->assertNotFound();

    $this->actingAs($this->customer)->postJson('/api/v1/saved-guides', [
        'provider_uuid' => $this->pendingProvider->uuid,
    ])->assertNotFound();

    $this->actingAs($this->customer)->postJson('/api/v1/saved-guides', [])
        ->assertStatus(422);
});
