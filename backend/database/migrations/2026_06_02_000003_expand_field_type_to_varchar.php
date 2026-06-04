<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'sqlite') {
            Schema::table('register_fields', function (Blueprint $table) {
                $table->string('field_type', 30)->default('text')->change();
            });
        } elseif ($driver === 'pgsql') {
            DB::statement("ALTER TABLE register_fields ALTER COLUMN field_type TYPE varchar(30)");
        } elseif ($driver === 'mysql') {
            DB::statement("ALTER TABLE register_fields MODIFY field_type VARCHAR(30) DEFAULT 'text'");
        }
    }

    public function down(): void
    {
    }
};
