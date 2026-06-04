<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::getDriverName();
        if ($driver !== 'pgsql') {
            return;
        }
        // Block UPDATE on workflow_execution_events
        DB::unprepared("
            CREATE OR REPLACE FUNCTION block_workflow_execution_events_update()
            RETURNS TRIGGER AS \$\$
            BEGIN
                RAISE EXCEPTION 'workflow_execution_events is append-only. Updates are not allowed. Event ID: %', OLD.id;
            END;
            \$\$ LANGUAGE plpgsql;

            CREATE TRIGGER trg_block_workflow_execution_events_update
                BEFORE UPDATE ON workflow_execution_events
                FOR EACH ROW
                EXECUTE FUNCTION block_workflow_execution_events_update();
        ");

        // Block DELETE on workflow_execution_events
        DB::unprepared("
            CREATE OR REPLACE FUNCTION block_workflow_execution_events_delete()
            RETURNS TRIGGER AS \$\$
            BEGIN
                RAISE EXCEPTION 'workflow_execution_events is append-only. Deletes are not allowed. Event ID: %', OLD.id;
            END;
            \$\$ LANGUAGE plpgsql;

            CREATE TRIGGER trg_block_workflow_execution_events_delete
                BEFORE DELETE ON workflow_execution_events
                FOR EACH ROW
                EXECUTE FUNCTION block_workflow_execution_events_delete();
        ");

        // Block UPDATE on receipt_events
        DB::unprepared("
            CREATE OR REPLACE FUNCTION block_receipt_events_update()
            RETURNS TRIGGER AS \$\$
            BEGIN
                RAISE EXCEPTION 'receipt_events is append-only. Updates are not allowed. Event ID: %', OLD.id;
            END;
            \$\$ LANGUAGE plpgsql;

            CREATE TRIGGER trg_block_receipt_events_update
                BEFORE UPDATE ON receipt_events
                FOR EACH ROW
                EXECUTE FUNCTION block_receipt_events_update();
        ");

        // Block DELETE on receipt_events
        DB::unprepared("
            CREATE OR REPLACE FUNCTION block_receipt_events_delete()
            RETURNS TRIGGER AS \$\$
            BEGIN
                RAISE EXCEPTION 'receipt_events is append-only. Deletes are not allowed. Event ID: %', OLD.id;
            END;
            \$\$ LANGUAGE plpgsql;

            CREATE TRIGGER trg_block_receipt_events_delete
                BEFORE DELETE ON receipt_events
                FOR EACH ROW
                EXECUTE FUNCTION block_receipt_events_delete();
        ");

        // Block UPDATE on idempotency_keys (once set, never change)
        DB::unprepared("
            CREATE OR REPLACE FUNCTION block_idempotency_keys_update()
            RETURNS TRIGGER AS \$\$
            BEGIN
                RAISE EXCEPTION 'idempotency_keys are immutable. Updates are not allowed. Key: %', OLD.key;
            END;
            \$\$ LANGUAGE plpgsql;

            CREATE TRIGGER trg_block_idempotency_keys_update
                BEFORE UPDATE ON idempotency_keys
                FOR EACH ROW
                EXECUTE FUNCTION block_idempotency_keys_update();
        ");

        // Block DELETE on idempotency_keys
        DB::unprepared("
            CREATE OR REPLACE FUNCTION block_idempotency_keys_delete()
            RETURNS TRIGGER AS \$\$
            BEGIN
                RAISE EXCEPTION 'idempotency_keys are immutable. Deletes are not allowed. Key: %', OLD.key;
            END;
            \$\$ LANGUAGE plpgsql;

            CREATE TRIGGER trg_block_idempotency_keys_delete
                BEFORE DELETE ON idempotency_keys
                FOR EACH ROW
                EXECUTE FUNCTION block_idempotency_keys_delete();
        ");
    }

    public function down(): void
    {
        $driver = DB::getDriverName();
        if ($driver !== 'pgsql') {
            return;
        }
        DB::unprepared('DROP TRIGGER IF EXISTS trg_block_workflow_execution_events_update ON workflow_execution_events');
        DB::unprepared('DROP TRIGGER IF EXISTS trg_block_workflow_execution_events_delete ON workflow_execution_events');
        DB::unprepared('DROP FUNCTION IF EXISTS block_workflow_execution_events_update()');
        DB::unprepared('DROP FUNCTION IF EXISTS block_workflow_execution_events_delete()');

        DB::unprepared('DROP TRIGGER IF EXISTS trg_block_receipt_events_update ON receipt_events');
        DB::unprepared('DROP TRIGGER IF EXISTS trg_block_receipt_events_delete ON receipt_events');
        DB::unprepared('DROP FUNCTION IF EXISTS block_receipt_events_update()');
        DB::unprepared('DROP FUNCTION IF EXISTS block_receipt_events_delete()');

        DB::unprepared('DROP TRIGGER IF EXISTS trg_block_idempotency_keys_update ON idempotency_keys');
        DB::unprepared('DROP TRIGGER IF EXISTS trg_block_idempotency_keys_delete ON idempotency_keys');
        DB::unprepared('DROP FUNCTION IF EXISTS block_idempotency_keys_update()');
        DB::unprepared('DROP FUNCTION IF EXISTS block_idempotency_keys_delete()');
    }
};
