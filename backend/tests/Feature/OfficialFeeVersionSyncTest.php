<?php

namespace Tests\Feature;

use App\Models\FeeVersion;
use App\Models\OfficialFee;
use App\Models\OfficialFeeCategory;
use App\Services\FeeEngine;
use Tests\TestCase;

/**
 * The execution engine resolves fees from fee_versions. Managing a fee through the fee
 * library (OfficialFeeController) must therefore keep a fee_version in sync — otherwise
 * set_fee finds no active version (or a stale amount) and the wrong/zero fee is charged.
 */
class OfficialFeeVersionSyncTest extends TestCase
{
    private function categoryId(): string
    {
        return OfficialFeeCategory::create([
            'name_ar' => 'فئة', 'name_en' => 'Cat', 'code' => 'C1', 'sort_order' => 1,
        ])->id;
    }

    public function test_creating_a_fee_creates_a_matching_active_version(): void
    {
        $res = $this->actingAsAdmin()->postJson('/api/v1/official-fees', [
            'category_id' => $this->categoryId(),
            'fee_code' => 'S0',
            'name_ar' => 'رسم الاشتراك',
            'amount' => 7500,
        ])->assertSuccessful();

        $fee = OfficialFee::where('fee_code', 'S0')->firstOrFail();
        $version = $fee->feeVersions()->first();
        $this->assertNotNull($version, 'creating a fee must create a fee_version');
        $this->assertEquals('7500.000', (string) $version->amount);

        // The engine resolves the same amount the library shows.
        $resolved = app(FeeEngine::class)->resolve('S0');
        $this->assertEquals('7500.000', (string) $resolved->amount);
    }

    public function test_updating_fee_amount_syncs_the_version(): void
    {
        $fee = $this->actingAsAdmin()->postJson('/api/v1/official-fees', [
            'category_id' => $this->categoryId(),
            'fee_code' => 'S1',
            'name_ar' => 'رسم',
            'amount' => 5000,
        ])->assertSuccessful()->json('data');

        $this->actingAsAdmin()->putJson("/api/v1/official-fees/{$fee['id']}", [
            'amount' => 9999,
        ])->assertSuccessful();

        $resolved = app(FeeEngine::class)->resolve('S1');
        $this->assertEquals('9999.000', (string) $resolved->amount, 'engine must resolve the edited amount');
    }
}
