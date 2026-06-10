<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use App\Models\Permission;
use App\Models\Role;

/**
 * Backfill the per-register read permission (read-register-{code}) for every existing
 * register. This permission gates the cross_register_check validation type. New registers
 * receive theirs in RegisterService::create(); this migration covers registers created
 * before that hook existed.
 *
 * Granted to super_admin only — no Gate::before bypass exists. Other roles obtain the
 * permission on demand, not automatically.
 */
return new class extends Migration
{
    public function up(): void
    {
        $superAdmin = Role::where('name', 'super_admin')->where('guard_name', 'api')->first();

        foreach (DB::table('registers')->pluck('code') as $code) {
            $permission = Permission::firstOrCreate(
                ['name' => "read-register-{$code}", 'guard_name' => 'api']
            );

            $superAdmin?->givePermissionTo($permission);
        }
    }

    public function down(): void
    {
        $names = DB::table('registers')->pluck('code')
            ->map(fn ($code) => "read-register-{$code}")
            ->all();

        if (!empty($names)) {
            Permission::whereIn('name', $names)->where('guard_name', 'api')->delete();
        }
    }
};
