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
        Schema::create('reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('campaign_id')->nullable()->constrained()->onDelete('set null');
            $table->dateTime('reservation_date');
            $table->decimal('amount', 10, 2)->nullable();
            $table->enum('status', ['pending', 'confirmed', 'completed', 'cancelled'])->default('pending');
            
            // Approval workflow fields
            $table->enum('approval_status', ['pending', 'approved', 'rejected'])->nullable()->default('pending');
            $table->foreignId('approved_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('approved_at')->nullable();
            $table->text('approval_notes')->nullable();
            $table->text('rejection_reason')->nullable();
            
            // Dynamic form data storage
            $table->json('form_data')->nullable()->comment('Stores dynamic form field values submitted by user');
            
            $table->timestamps();

            $table->index('user_id');
            $table->index('campaign_id');
            $table->index('reservation_date');
            $table->index('status');
            $table->index('approval_status');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('reservations');
    }
};

