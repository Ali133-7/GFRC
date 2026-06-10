<?php

namespace App\Console\Commands;

use App\Models\WorkflowExecution;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CleanupAbandonedWorkflows extends Command
{
    protected $signature = 'workflow:cleanup-abandoned';

    protected $description = 'Mark stale in-progress workflow executions as abandoned';

    public function handle(): int
    {
        $hours = config('workflow.abandoned_hours', 24);
        $threshold = now()->subHours($hours);

        $count = 0;

        WorkflowExecution::where('status', 'in_progress')
            ->where('updated_at', '<', $threshold)
            ->chunkById(100, function ($executions) use (&$count) {
                foreach ($executions as $execution) {
                    $execution->update([
                        'status' => 'abandoned',
                        'cancelled_at' => now(),
                        'cancel_reason' => 'system_timeout',
                    ]);

                    activity('workflow')
                        ->on($execution)
                        ->log('auto_abandoned');

                    $count++;
                }
            });

        Log::info("workflow:cleanup-abandoned marked {$count} executions as abandoned (threshold: {$hours}h)");
        $this->info("Marked {$count} executions as abandoned.");

        return self::SUCCESS;
    }
}
