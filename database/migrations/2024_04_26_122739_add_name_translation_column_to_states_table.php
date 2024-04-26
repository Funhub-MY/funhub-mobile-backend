<?php

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
        Schema::table('states', function (Blueprint $table) {
            $table->json('name_translation')->after('name');
        });

        // Update name_translation column for existing record with the hardcoded list
        $translations = [
            'Perlis' => '玻璃市',
            'Kedah' => '吉打',
            'Pulau Pinang' => '槟城',
            'Perak' => '霹雳',
            'Pahang' => '彭亨',
            'Kelantan' => '吉兰丹',
            'Terengganu' => '登嘉楼',
            'Selangor' => '雪兰莪',
            'W.P. Kuala Lumpur' => '吉隆坡',
            'W.P. Putrajaya' => '布城',
            'Negeri Sembilan' => '森美兰',
            'Malacca' => '马六甲',
            'Johor' => '柔佛',
            'Sabah' => '沙巴',
            'Sarawak' => '砂拉越',
            'W.P. Labuan' => '纳闽',
            'Others' => '其他',
            'Federal Territory of Kuala Lumpur' => '吉隆坡联邦直辖区',
            'Melaka' => '马六甲',
            '吉隆坡联邦直辖区' => '吉隆坡联邦直辖区',
        ];

        foreach ($translations as $name => $translation) {
            DB::table('states')
                ->where('name', $name)
                ->update(['name_translation' => json_encode(['en' => $name, 'zh' => $translation])]);
        }

        // For names not in the list, set the 'en' value of the name_translation to the value of the name itself
        $states = DB::table('states')->get();

        foreach ($states as $state) {
            if (!array_key_exists($state->name, $translations)) {
                DB::table('states')
                    ->where('name', $state->name)
                    ->update(['name_translation' => json_encode(['en' => $state->name, 'zh' => ''])]);
            }
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('states', function (Blueprint $table) {
            $table->dropColumn('name_translation');
        });
    }
};
