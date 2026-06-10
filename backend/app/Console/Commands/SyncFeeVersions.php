<?php

namespace App\Console\Commands;

use App\Models\FeeVersion;
use App\Models\OfficialFee;
use Illuminate\Console\Command;

/**
 * Ensures every official fee has an active fee_version whose amount matches the fee's
 * displayed amount. Repairs the divergence where fees managed via the fee library had no
 * fee_version (so the engine could not resolve them) or a stale version amount.
 */
class SyncFeeVersions extends Command
{
    protected $signature = 'fees:sync-versions {--dry-run : Show changes without saving}';
    protected $description = 'Create/sync a fee_version for every official fee so the engine resolves the same amount the library shows';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');
        $created = 0;
        $updated = 0;

        foreach (OfficialFee::all() as $fee) {
            $version = $fee->feeVersions()->orderByDesc('version')->first();

            if (!$version) {
                $this->line("CREATE  {$fee->fee_code}: version v1 amount={$fee->amount}");
                if (!$dry) {
                    FeeVersion::create([
                        'fee_id' => $fee->id,
                        'version' => 1,
                        'amount' => $fee->amount,
                        'effective_from' => $fee->effective_from ?? now(),
                        'effective_to' => $fee->effective_to,
                    ]);
                }
                $created++;
            } elseif ((string) $version->amount !== (string) $fee->amount) {
                $this->line("SYNC    {$fee->fee_code}: version v{$version->version} {$version->amount} → {$fee->amount}");
                if (!$dry) {
                    $version->update(['amount' => $fee->amount]);
                }
                $updated++;
            }
        }

        $this->newLine();
        $this->info(($dry ? '[DRY RUN] ' : '') . "Versions created: {$created}; synced: {$updated}");
        if ($dry && ($created + $updated) > 0) {
            $this->comment('Re-run without --dry-run to apply.');
        }
        return self::SUCCESS;
    }
}
