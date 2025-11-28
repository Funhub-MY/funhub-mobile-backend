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
        Schema::create('reservation_form_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->unique()->constrained()->onDelete('cascade');
            $table->json('form_fields')->comment('JSON array defining form fields for reservation. Example: [{"field_key": "name", "label": "Full Name", "field_type": "text"}, {"field_key": "phone", "label": "Phone Number", "field_type": "text"}]');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('campaign_id');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('reservation_form_fields');
    }
};

