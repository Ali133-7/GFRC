<?php

namespace Tests\Unit;

use App\Exceptions\Workflow\TemporalOverlapException;
use App\Models\FeeVersion;
use App\Models\OfficialFee;
use App\Models\OfficialFeeCategory;
use Tests\TestCase;

class FeeVersionOverlapTest extends TestCase
{
    private OfficialFee $fee;

    protected function setUp(): void
    {
        parent::setUp();

        $category = OfficialFeeCategory::create([
            'name_ar' => 'Test Category',
            'name_en' => 'Test Category',
            'code' => 'TEST-CAT',
        ]);

        $this->fee = OfficialFee::create([
            'category_id' => $category->id,
            'fee_code' => 'TEST-001',
            'name_ar' => 'Test Fee',
            'name_en' => 'Test Fee',
            'is_active' => true,
        ]);

        FeeVersion::create([
            'fee_id' => $this->fee->id,
            'version' => 1,
            'amount' => '10.000',
            'effective_from' => '2024-01-01',
            'effective_to' => '2024-12-31',
        ]);
    }

    public function test_overlapping_version_is_rejected(): void
    {
        $this->expectException(TemporalOverlapException::class);

        FeeVersion::create([
            'fee_id' => $this->fee->id,
            'version' => 2,
            'amount' => '15.000',
            'effective_from' => '2024-06-01',
            'effective_to' => '2024-12-31',
        ]);
    }

    public function test_non_overlapping_version_is_allowed(): void
    {
        $version = FeeVersion::create([
            'fee_id' => $this->fee->id,
            'version' => 2,
            'amount' => '15.000',
            'effective_from' => '2025-01-01',
            'effective_to' => '2025-12-31',
        ]);

        $this->assertDatabaseHas('fee_versions', [
            'id' => $version->id,
            'version' => 2,
        ]);
    }

    public function test_adjacent_version_is_allowed(): void
    {
        $version = FeeVersion::create([
            'fee_id' => $this->fee->id,
            'version' => 2,
            'amount' => '15.000',
            'effective_from' => '2025-01-01',
            'effective_to' => null,
        ]);

        $this->assertDatabaseHas('fee_versions', [
            'id' => $version->id,
        ]);
    }

    public function test_open_ended_overlap_is_rejected(): void
    {
        $this->expectException(TemporalOverlapException::class);

        FeeVersion::create([
            'fee_id' => $this->fee->id,
            'version' => 2,
            'amount' => '15.000',
            'effective_from' => '2024-06-01',
            'effective_to' => null,
        ]);
    }

    public function test_update_without_overlap_is_allowed(): void
    {
        $version = FeeVersion::create([
            'fee_id' => $this->fee->id,
            'version' => 2,
            'amount' => '15.000',
            'effective_from' => '2025-01-01',
            'effective_to' => '2025-06-30',
        ]);

        $version->update(['amount' => '20.000']);

        $this->assertDatabaseHas('fee_versions', [
            'id' => $version->id,
            'amount' => '20.000',
        ]);
    }
}
