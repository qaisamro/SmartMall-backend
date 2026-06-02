<?php

namespace Tests\Feature;

use App\Models\Mall;
use App\Models\ProductImport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ProductImportAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_mall_owner_cannot_access_product_imports_for_another_mall(): void
    {
        Role::create(['name' => 'mall-owner']);

        $owner = User::factory()->create();
        $otherOwner = User::factory()->create();
        $owner->assignRole('mall-owner');
        $otherOwner->assignRole('mall-owner');

        $mall = Mall::create([
            'owner_id' => $owner->id,
            'name_ar' => 'Owner Mall',
            'name_en' => 'Owner Mall',
            'slug' => 'owner-mall',
        ]);

        $otherMall = Mall::create([
            'owner_id' => $otherOwner->id,
            'name_ar' => 'Other Mall',
            'name_en' => 'Other Mall',
            'slug' => 'other-mall',
        ]);

        $otherImport = ProductImport::create([
            'user_id' => $otherOwner->id,
            'mall_id' => $otherMall->id,
            'file_name' => 'products.csv',
            'file_path' => 'private/imports/products.csv',
            'status' => 'pending',
        ]);

        ProductImport::create([
            'user_id' => $owner->id,
            'mall_id' => $mall->id,
            'file_name' => 'own-products.csv',
            'file_path' => 'private/imports/own-products.csv',
            'status' => 'pending',
        ]);

        Sanctum::actingAs($owner);

        $this->getJson("/api/v1/owner/product-imports/{$otherImport->id}")
            ->assertForbidden();

        $this->postJson("/api/v1/owner/product-imports/{$otherImport->id}/start")
            ->assertForbidden();

        $this->deleteJson("/api/v1/owner/product-imports/{$otherImport->id}")
            ->assertForbidden();

        $this->getJson("/api/v1/owner/product-imports?mall_id={$otherMall->id}")
            ->assertForbidden();

        $response = $this->getJson('/api/v1/owner/product-imports')
            ->assertOk()
            ->json('data');

        $this->assertNotContains($otherImport->id, collect($response)->pluck('id')->all());
    }
}
