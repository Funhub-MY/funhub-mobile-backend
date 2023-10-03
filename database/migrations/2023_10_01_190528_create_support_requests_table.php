<?php

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
        Schema::create('support_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id');
            $table->string('title');
            $table->tinyInteger('status')->default(0); // 0 = pending, 1 = in progress, 2 = more info, 3 = closed, 4 = reopened, 5 = invalid
            $table->foreignId('requestor_id');
            $table->foreignId('assignee_id')->nullable();
            $table->text('internal_remarks')->nullable();
            $table->nullableMorphs('associated'); // morphs to supportable models (e.g. merchant, user, etc.)
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('support_requests');
    }
};
