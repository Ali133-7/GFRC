<?php

namespace App\Console\Commands;

use App\Models\Receipt;
use App\Models\WorkflowExecution;
use App\Services\EventReplayEngine;
use Illuminate\Console\Command;

class LedgerVerify extends Command
{
    protected $signature = 'ledger:verify
        {--entity= : نوع الكيان (receipt أو workflow_execution)}
        {--id= : معرف الكيان المراد التحقق منه}
        {--all : التحقق من جميع الكيانات}
        {--chain-only : التحقق من سلسلة التجزئة فقط}
        {--state-only : التحقق من تطابق الحالة فقط}
        {--forensic : إنشاء تقرير جنائي كامل}';

    protected $description = 'التحقق من سلامة دفتر الأستاذ المالي (سلسلة التجزئة + تطابق الحالة)';

    public function handle(EventReplayEngine $replayEngine): int
    {
        $this->info('═══════════════════════════════════════════');
        $this->info('  نظام التحقق من دفتر الأستاذ المالي');
        $this->info('═══════════════════════════════════════════');
        $this->line('');

        $entity = $this->option('entity');
        $id = $this->option('id');
        $all = $this->option('all');
        $chainOnly = $this->option('chain-only');
        $stateOnly = $this->option('state-only');
        $forensic = $this->option('forensic');

        if (!$entity && !$all) {
            $this->error('يجب تحديد --entity أو --all');
            $this->info('أمثلة:');
            $this->info('  php artisan ledger:verify --entity receipt --id <uuid>');
            $this->info('  php artisan ledger:verify --entity workflow_execution --id <uuid>');
            $this->info('  php artisan ledger:verify --all');
            $this->info('  php artisan ledger:verify --entity receipt --id <uuid> --forensic');
            return self::FAILURE;
        }

        $overallPass = true;

        if ($all) {
            $overallPass = $this->verifyAll($replayEngine, $chainOnly, $stateOnly, $forensic);
        } else {
            $overallPass = $this->verifySingle($replayEngine, $entity, $id, $chainOnly, $stateOnly, $forensic);
        }

        $this->line('');
        if ($overallPass) {
            $this->info('✅ جميع عمليات التحقق نجحت - دفتر الأستاذ سليم');
        } else {
            $this->error('❌ تم اكتشاف مشاكل في دفتر الأستاذ - راجع التقرير أعلاه');
        }

        return $overallPass ? self::SUCCESS : self::FAILURE;
    }

    protected function verifyAll(EventReplayEngine $replayEngine, bool $chainOnly, bool $stateOnly, bool $forensic): bool
    {
        $overallPass = true;

        // Verify all receipts
        $this->info('📋 التحقق من جميع الإيصالات...');
        $receipts = Receipt::orderBy('created_at')->get();
        $this->withProgressBar($receipts, function ($receipt) use ($replayEngine, $chainOnly, $stateOnly, $forensic, &$overallPass) {
            $pass = $this->verifySingle($replayEngine, 'receipt', $receipt->id, $chainOnly, $stateOnly, $forensic, silent: true);
            if (!$pass) $overallPass = false;
        });
        $this->line('');

        // Verify all executions
        $this->info('📋 التحقق من جميع التنفيذات...');
        $executions = WorkflowExecution::orderBy('started_at')->get();
        $this->withProgressBar($executions, function ($execution) use ($replayEngine, $chainOnly, $stateOnly, $forensic, &$overallPass) {
            $pass = $this->verifySingle($replayEngine, 'workflow_execution', $execution->id, $chainOnly, $stateOnly, $forensic, silent: true);
            if (!$pass) $overallPass = false;
        });
        $this->line('');

        return $overallPass;
    }

    protected function verifySingle(EventReplayEngine $replayEngine, string $entity, string $id, bool $chainOnly, bool $stateOnly, bool $forensic, bool $silent = false): bool
    {
        $pass = true;

        try {
            if ($entity === 'receipt') {
                if (!$chainOnly) {
                    $stateReport = $replayEngine->verifyReceipt($id);
                    if (!$silent) {
                        $this->line('');
                        $this->info("📄 إيصال: {$id}");
                        $this->line("   الحالة المخزنة: {$stateReport['replayed_state']['status']}");
                        $this->line("   الأحداث المعاد تطبيقها: {$stateReport['events_replayed']}");
                    }

                    if ($stateReport['integrity'] === 'FAIL') {
                        $pass = false;
                        if (!$silent) {
                            $this->error('   ❌ تطابق الحالة: FAIL');
                            foreach ($stateReport['discrepancies'] as $field => $values) {
                                $this->error("      {$field}: مخزون={$values['stored']} مقابل معاد={$values['replayed']}");
                            }
                        }
                    } elseif (!$silent) {
                        $this->info('   ✅ تطابق الحالة: PASS');
                    }
                }

                if (!$stateOnly) {
                    $chainReport = $replayEngine->verifyReceiptChain($id);
                    if (!$silent) {
                        $this->line("   سلسلة التجزئة: {$chainReport['chain_integrity']} ({$chainReport['total_events']} حدث)");
                    }

                    if ($chainReport['chain_integrity'] === 'FAIL') {
                        $pass = false;
                        if (!$silent) {
                            foreach ($chainReport['broken_links'] as $link) {
                                $this->error("      رابط مكسور في الحدث #{$link['event_index']}: {$link['issue']}");
                            }
                        }
                    } elseif (!$silent) {
                        $this->info('   ✅ سلسلة التجزئة: PASS');
                    }
                }

                if ($forensic && !$silent) {
                    $report = $replayEngine->forensicReportReceipt($id);
                    $this->line('');
                    $this->info('   ┌─── تقرير جنائي ───');
                    $this->info("   │ السلامة العامة: {$report['overall_integrity']}");
                    $this->info("   │ عدد الأحداث: {$report['event_count']}");
                    $this->info("   │ سلامة السلسلة: {$report['chain_integrity']}");
                    $this->info("   │ سلامة الحالة: {$report['state_integrity']}");
                    $this->info("   │ تم التوليد: {$report['generated_at']}");
                    $this->info('   └─────────────────────');
                }

            } elseif ($entity === 'workflow_execution') {
                if (!$chainOnly) {
                    $stateReport = $replayEngine->verifyExecution($id);
                    if (!$silent) {
                        $this->line('');
                        $this->info("⚙️ تنفيذ: {$id}");
                        $this->line("   الحالة المخزنة: {$stateReport['replayed_state']['status']}");
                        $this->line("   الأحداث المعاد تطبيقها: {$stateReport['events_replayed']}");
                    }

                    if ($stateReport['integrity'] === 'FAIL') {
                        $pass = false;
                        if (!$silent) {
                            $this->error('   ❌ تطابق الحالة: FAIL');
                            foreach ($stateReport['discrepancies'] as $field => $values) {
                                $this->error("      {$field}: مخزون={$values['stored']} مقابل معاد={$values['replayed']}");
                            }
                        }
                    } elseif (!$silent) {
                        $this->info('   ✅ تطابق الحالة: PASS');
                    }
                }

                if (!$stateOnly) {
                    $chainReport = $replayEngine->verifyExecutionChain($id);
                    if (!$silent) {
                        $this->line("   سلسلة التجزئة: {$chainReport['chain_integrity']} ({$chainReport['total_events']} حدث)");
                    }

                    if ($chainReport['chain_integrity'] === 'FAIL') {
                        $pass = false;
                        if (!$silent) {
                            foreach ($chainReport['broken_links'] as $link) {
                                $this->error("      رابط مكسور في الحدث #{$link['event_index']}: {$link['issue']}");
                            }
                        }
                    } elseif (!$silent) {
                        $this->info('   ✅ سلسلة التجزئة: PASS');
                    }
                }

                if ($forensic && !$silent) {
                    $report = $replayEngine->forensicReportExecution($id);
                    $this->line('');
                    $this->info('   ┌─── تقرير جنائي ───');
                    $this->info("   │ السلامة العامة: {$report['overall_integrity']}");
                    $this->info("   │ عدد الأحداث: {$report['event_count']}");
                    $this->info("   │ سلامة السلسلة: {$report['chain_integrity']}");
                    $this->info("   │ سلامة الحالة: {$report['state_integrity']}");
                    $this->info("   │ تم التوليد: {$report['generated_at']}");
                    $this->info('   └─────────────────────');
                }
            }
        } catch (\Exception $e) {
            $pass = false;
            if (!$silent) {
                $this->error("   ❌ خطأ: {$e->getMessage()}");
            }
        }

        return $pass;
    }
}
