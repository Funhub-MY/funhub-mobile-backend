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
        Schema::table('system_notifications', function (Blueprint $table) {
            $table->unsignedTinyInteger('redirect_type')->after('content');
            $table->unsignedInteger('content_id')->nullable()->after('content');
            $table->string('content_type')->nullable()->after('content');
            $table->index(['content_id', 'content_type']);
            $table->string('static_content_type')->nullable()->after('content');

            $table->string('web_link')->after('content')->change();
            $table->dropColumn(['type']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('system_notifications', function (Blueprint $table) {
            $table->dropColumn('redirect_type');
            $table->dropColumn('content_id');
            $table->dropColumn('content_type');
            $table->dropColumn('static_content_type');
            $table->string('web_link')->after('updated_at')->nullable()->change();
            $table->string('type')->after('id');
            $table->dropIndex(['content_id', 'content_type']);
        });
    }
};
