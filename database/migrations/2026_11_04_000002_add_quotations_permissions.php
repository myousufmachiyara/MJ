<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Creates quotations.index/create/edit/delete/print and gives them to the
 * superadmin and manager roles, so existing databases get the new module
 * without re-running the full DatabaseSeeder. (DatabaseSeeder also lists
 * 'quotations' for fresh installs.) Safe to run more than once.
 */
return new class extends Migration
{
    private array $actions = ['index', 'create', 'edit', 'delete', 'print'];

    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $names = [];
        foreach ($this->actions as $action) {
            $names[] = Permission::firstOrCreate(['name' => "quotations.$action"])->name;
        }

        foreach (['superadmin', 'manager'] as $roleName) {
            $role = Role::where('name', $roleName)->first();
            if ($role) {
                $role->givePermissionTo($names);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Permission::whereIn('name', array_map(fn ($a) => "quotations.$a", $this->actions))->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
