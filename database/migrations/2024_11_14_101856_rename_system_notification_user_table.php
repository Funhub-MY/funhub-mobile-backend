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
		Schema::rename('system_notification_user', 'system_notifications_users');

	}

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
		Schema::rename('system_notifications_users', 'system_notification_user');
	}
};
