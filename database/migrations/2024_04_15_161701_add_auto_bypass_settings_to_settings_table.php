<?php

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Setting::create([
            'key' => 'recommendation_auto_bypass_view',
            'value' => 200,
        ]);

        Setting::create([
            'key' => 'recommendation_auto_bypass_like',
            'value' => 50,
        ]);

        Setting::create([
            'key' => 'recommendation_auto_bypass_share',
            'value' => 3,
        ]);

        Setting::create([
            'key' => 'recommendation_auto_bypass_bookmark',
            'value' => 1,
        ]);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
    }
};
