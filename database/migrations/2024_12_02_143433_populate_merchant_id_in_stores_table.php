<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\Store;
use App\Models\User;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // get all stores that have a user_id
        $stores = Store::whereNotNull('user_id')->get();

        foreach ($stores as $store) {
            $user = User::find($store->user_id);
            if ($user && $user->merchant) {
                $store->update([
                    'merchant_id' => $user->merchant->id
                ]);
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
        // set all merchant_id back to null
        Store::query()->update(['merchant_id' => null]);
    }
};
