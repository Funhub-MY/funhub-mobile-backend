<?php

use Carbon\Carbon;
use App\Models\Country;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $malaysia = Country::where('code', 'MY')->first();
        
        if ($malaysia) {
            DB::table('states')->insert([
                ['country_id' => $malaysia->id, 'code' => 'OTHERS', 'name' => 'Others', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ]);
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::table('states')->where('code', 'OTHERS')->delete();
    }
};
