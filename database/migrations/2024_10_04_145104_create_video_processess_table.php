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
        Schema::create('video_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('media_id'); // media_id (SpatieMediaLibrary)
            $table->string('job_id')->unique();
            $table->string('provider');
            $table->tinyInteger('status')
                ->default(0)
                ->comment('0 = pending, 1 = processing, 2 = completed, 3 = failed');
            $table->string('title')->nullable();
            $table->string('source_url')->nullable();
            $table->double('width')->nullable();
            $table->double('height')->nullable();
            $table->double('duration')->nullable();
            $table->double('bitrate')->nullable();
            $table->string('format')->nullable();

            $table->json('results')->nullable(); // processed urls

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
        Schema::dropIfExists('video_jobs');
    }
};
