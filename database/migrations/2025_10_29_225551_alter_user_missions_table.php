<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_missions', function (Blueprint $table) {
            // Check if total_chance column exists before renaming
            if (Schema::hasColumn('user_missions', 'total_chance')) {
                $table->renameColumn('total_chance', 'draw_chance');
            }
            
            // Change draw_chance to integer if it exists (either originally or after rename)
            if (Schema::hasColumn('user_missions', 'draw_chance')) {
                $table->integer('draw_chance')->change();
            }
            
            // Add extra_chance column if it doesn't exist
            if (!Schema::hasColumn('user_missions', 'extra_chance')) {
                $table->integer('extra_chance')
                      ->after('draw_chance')
                      ->default(0);
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
    }
};
