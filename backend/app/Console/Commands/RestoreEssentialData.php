<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RestoreEssentialData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'data:restore {--force : Force restore without confirmation}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Restore essential data (users, roles, permissions) without losing existing data';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->warn('⚠️  WARNING: This will restore essential data.');
        $this->warn('   This will NOT delete existing data.');
        $this->warn('');

        if (!$this->option('force')) {
            if (!$this->confirm('Do you want to continue?', true)) {
                return Command::FAILURE;
            }
        }

        try {
            $this->info('🔄 Restoring essential data...');
            $this->call('db:seed', ['--class' => 'KeepDataSeeder']);
            
            $this->info('');
            $this->info('✅ Data restored successfully!');
            $this->info('');
            $this->table(
                ['User', 'Role', 'Password'],
                [
                    ['admin', 'admin', 'password'],
                    ['cashier', 'cashier', 'password'],
                    ['auditor', 'auditor', 'password'],
                ]
            );
            
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error('❌ Error: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
