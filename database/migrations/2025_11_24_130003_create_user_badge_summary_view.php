<?php

use Illuminate\Database\Migrations\Migration;
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
        DB::statement("
            CREATE VIEW user_badge_summary AS
            SELECT 
                u.id AS user_id,
                u.name AS user_name,
                c.id AS campaign_id,
                c.title AS campaign_name,
                COUNT(DISTINCT ub.badge_id) AS badges_earned,
                COUNT(DISTINCT r.id) AS total_reservations,
                COALESCE(SUM(r.amount), 0) AS total_spent
            FROM users u
            LEFT JOIN reservations r ON u.id = r.user_id AND r.status = 'completed'
            LEFT JOIN campaigns c ON r.campaign_id = c.id
            LEFT JOIN user_badges ub ON u.id = ub.user_id
            LEFT JOIN badges b ON ub.badge_id = b.id AND b.campaign_id = c.id
            GROUP BY u.id, u.name, c.id, c.title
        ");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::statement('DROP VIEW IF EXISTS user_badge_summary');
    }
};

