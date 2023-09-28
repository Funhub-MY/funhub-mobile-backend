<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Role::where('name', 'merchant')->exists()) {
            // create role
            $role = Role::create(['name' => 'merchant', 'guard_name' => 'web']);

            if (Permission::where('name', 'view_merchant::offer::voucher')->exists()) {
                // assign permissions to role
                $role->givePermissionTo('view_merchant::offer::voucher');
            }
            if (Permission::where('name', 'view_any_merchant::offer::voucher')->exists()) {
                // assign permissions to role
                $role->givePermissionTo('view_any_merchant::offer::voucher');
            }
            // assign roles to permissions for  view_merchant::offer, view_merchant::offer::voucher
            // $role->givePermissionTo('view_merchant::offer');
            // // $role->givePermissionTo('view_any_merchant::offer');
            // $role->givePermissionTo('view_merchant::offer::voucher');
            // $role->givePermissionTo('view_any_merchant::offer::voucher');
        }

        // find all users with merchant offers attached
        $users = \App\Models\User::whereHas('merchant_offers')->get();

        // assign merchant role to users if does not have
        foreach ($users as $user) {
            if (!$user->hasRole('merchant')) {
                $user->assignRole('merchant');
            }
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // find all users with merchant offers attached
        $users = \App\Models\User::whereHas('merchant_offers')->get();

        // remove merchant role from users
        foreach ($users as $user) {
            if ($user->hasRole('merchant')) {
                $user->removeRole('merchant');
            }
        }

        // delete role
        Role::where('name', 'merchant')->delete();
    }
};
