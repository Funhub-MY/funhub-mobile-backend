<?php

namespace App\Services;

use App\Mail\MerchandiseWinEmail;
use App\Mail\PromoCodeRewardEmail;
use App\Models\CnyFortunePick;
use App\Models\CnyLuckyDraw;
use App\Models\CnyMerchandise;
use App\Models\CnyMerchandiseWin;
use App\Models\Product;
use App\Models\PromotionCode;
use App\Models\Transaction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class CnyCampaignService
{
    public const FORTUNE_LUCKS = [
        'peace' => [
            ['title' => '平安顺心', 'description' => '日子不惊不险，简单就是福。天天用 FUNHUB 吃点好的，把平凡过得舒服。'],
            ['title' => '诸事安好', 'description' => '没大风浪，就是最好的运气。用 FUNHUB 探新店，生活多点小确幸。'],
            ['title' => '身心无恙', 'description' => '健康在，心也稳。每天花点时间 FUNHUB 玩玩，让自己开心起来。'],
            ['title' => '岁岁安心', 'description' => '烦恼少一点，笑容多一点。FUNHUB 陪你把日常的小事玩出趣味。'],
            ['title' => '平稳前行', 'description' => '不急不慌，一切刚刚好。用 FUNHUB 解锁小体验，让日子有温度。'],
            ['title' => '心安是福', 'description' => '想开了，福气就来了。FUNHUB 每天给你一点值得期待的惊喜。'],
            ['title' => '安然自在', 'description' => '活得舒服，比什么都重要。每天 FUNHUB 小探玩，生活更自在。'],
        ],
        'career' => [
            ['title' => '马上加薪', 'description' => '升职加薪不脱发，努力都会被看见。FUNHUB 天天陪你犒赏努力的自己。'],
            ['title' => '一路高升', 'description' => '机会在路上，贵人慢慢出现。用 FUNHUB 放松一下，顺便偷得一点闲。'],
            ['title' => '事事顺利', 'description' => '工作不卡关，进展比预期好。FUNHUB 给你新体验，日子不只剩忙碌。'],
            ['title' => '前途光明', 'description' => '方向清楚，越走越稳。每天用 FUNHUB 享受小确幸，把努力换成快乐。'],
            ['title' => '稳中求胜', 'description' => '节奏刚好，不急不慌。FUNHUB 带你体验小惊喜，让日常更有盼头。'],
            ['title' => '贵人相助', 'description' => '关键时刻有人拉你一把。用 FUNHUB 轻松解锁生活小幸运。'],
            ['title' => '突破成长', 'description' => '跨过关卡，能力升级。FUNHUB 陪你把拼命换成快乐。'],
        ],
        'study' => [
            ['title' => '学业进步', 'description' => '思路清楚，努力看得见。FUNHUB 天天给你放松的理由。'],
            ['title' => '金榜题名', 'description' => '考试顺利，心态在线。奖励自己一顿小餐，FUNHUB 陪你尝鲜。'],
            ['title' => '稳步成长', 'description' => '每天一点点进步。FUNHUB 让生活不只剩书本。'],
            ['title' => '思路清晰', 'description' => '理解力提升，灵感慢慢蹦出来。用 FUNHUB 小探玩，让大脑喘口气。'],
            ['title' => '轻松应对', 'description' => '不慌不乱，自然发挥。FUNHUB 给你调节学习和生活的好借口。'],
            ['title' => '知识翻倍', 'description' => '笔记整齐，灵感慢慢冒出。每天 FUNHUB 小体验，让思绪充电。'],
            ['title' => '目标达成', 'description' => '计划完成，努力都有回应。FUNHUB 陪你解锁新鲜体验，奖励自己。'],
        ],
        'love' => [
            ['title' => '桃花盛开', 'description' => '遇见有趣的人，日子更甜。FUNHUB 小探玩，说不定就遇见对的人。'],
            ['title' => '缘分到手', 'description' => '轻松互动，心动悄悄来。每天用 FUNHUB，生活多点惊喜。'],
            ['title' => '人缘佳趣', 'description' => '朋友聚会，笑声和探玩同步。FUNHUB 让聚会更有趣。'],
            ['title' => '心动瞬间', 'description' => '甜蜜悄悄跑进日常。FUNHUB 陪你把每次小心动放大。'],
            ['title' => '爱情升温', 'description' => '牵手顺利，缘分慢慢加分。用 FUNHUB 探玩，日子更甜。'],
            ['title' => '美好遇见', 'description' => '探索小店，故事慢慢出现。FUNHUB 每天给你一点小惊喜。'],
            ['title' => '友情满满', 'description' => '老友新朋一起玩，欢乐加倍。FUNHUB 轻轻陪你延伸快乐。'],
        ],
        'family' => [
            ['title' => '家庭和睦', 'description' => '团聚开心，笑声不断。FUNHUB 带你安排小惊喜，让快乐多一点。'],
            ['title' => '欢聚一堂', 'description' => '饭桌笑声多，欢乐和探玩全到。用 FUNHUB，把幸福放大。'],
            ['title' => '温暖时刻', 'description' => '美味上桌，开心顺顺来。FUNHUB 每天给你一点小乐趣。'],
            ['title' => '团圆快乐', 'description' => '聚在一起，心也踏实。FUNHUB 陪你把平凡变成故事。'],
            ['title' => '爱在身边', 'description' => '亲情友情像糖果一样甜。用 FUNHUB，把温暖慢慢蔓延。'],
            ['title' => '温馨满屋', 'description' => '家人开心，笑声连连。FUNHUB 给你日常的小确幸。'],
            ['title' => '元气团圆', 'description' => '一起探玩，欢乐体验齐飞。FUNHUB 陪你收获小惊喜。'],
        ],
        'wealth' => [
            ['title' => '钱袋满满', 'description' => 'FUNCARD 随手兑换附近体验。FUNHUB 带你把好运变成快乐。'],
            ['title' => '支付顺手', 'description' => '花钱开心，心情更好。每天 FUNHUB 给你一点小惊喜。'],
            ['title' => '幸运连连', 'description' => '财运顺利，好事不断来。用 FUNHUB，生活多点趣味。'],
            ['title' => '财运在线', 'description' => '收入稳定，花得安心。FUNHUB 陪你感受小确幸。'],
            ['title' => '钱潮来袭', 'description' => '收获渐多，心里踏实。每天用 FUNHUB，心情更好。'],
            ['title' => '金银满仓', 'description' => '财富渐积，笑容随之。FUNHUB 给你轻松小体验。'],
            ['title' => '元气赚翻', 'description' => '开心消费，探玩体验快乐多。FUNHUB 带你把日常放大。'],
        ],
        'health' => [
            ['title' => '元气满满', 'description' => '起床伸懒腰，精神在线。FUNHUB 陪你动一动、玩一玩。'],
            ['title' => '身体倍棒', 'description' => '运动轻松，步伐轻快。每天用 FUNHUB，发现生活里的小乐趣。'],
            ['title' => '健康加持', 'description' => '步步轻盈，开心和探玩同步。FUNHUB 带你调节心情。'],
            ['title' => '活力充沛', 'description' => '精神满格，日子有劲。FUNHUB 陪你尝鲜新体验。'],
            ['title' => '身心平衡', 'description' => '运动、笑声、美食探玩齐飞。FUNHUB 轻轻陪你，生活更轻松。'],
            ['title' => '体力满分', 'description' => '跳跳跑跑，开心顺顺来。FUNHUB 每天给你一点小确幸。'],
            ['title' => '健康护航', 'description' => '每天被开心包围，状态在线。FUNHUB 陪你过得有趣。'],
            ['title' => '元气在线', 'description' => '日子舒坦，快乐跟着来。用 FUNHUB 探索更多惊喜。'],
        ],
    ];

    /** Fortune reward: 30% nothing, 60% promo_49, 10% promo_50 */
    protected const FORTUNE_CHANCE_NOTHING = 30;
    protected const FORTUNE_CHANCE_PROMO_49 = 60; // 31-90
    protected const FORTUNE_CHANCE_PROMO_50 = 10; // 91-100

    /** Lucky draw: within repeatable pool 46=30%, 47=60%, 48=10% */
    protected const LUCKY_DRAW_CHANCE_46 = 30;
    protected const LUCKY_DRAW_CHANCE_47 = 60;
    protected const LUCKY_DRAW_CHANCE_48 = 10;

    protected function today(): Carbon
    {
        return Carbon::today(config('app.timezone', 'Asia/Kuala_Lumpur'));
    }

    /**
     * Check if user has purchased at least one Fun card product (successful transaction).
     */
    public function userHasPurchasedFuncard(User $user, array $config): bool
    {
        return $this->funcardPurchasesCountOnDate($user, $this->today(), $config) > 0;
    }

    /**
     * Count how many Fun card products the user purchased on a given date (successful transactions).
     * Each purchase = 1 lucky draw entry for that day.
     */
    public function funcardPurchasesCountOnDate(User $user, Carbon $date, array $config): int
    {
        $productIds = $config['funcard_product_ids'] ?? [1];
        if (empty($productIds)) {
            return 0;
        }

        return (int) Transaction::where('user_id', $user->id)
            ->where('status', Transaction::STATUS_SUCCESS)
            ->where('transactionable_type', Product::class)
            ->whereIn('transactionable_id', $productIds)
            ->whereDate('created_at', $date)
            ->count();
    }

    // ---------- Fortune pick (once per day) ----------

    public function fortunePicksUsedOnDate(User $user, Carbon $date): int
    {
        return CnyFortunePick::where('user_id', $user->id)
            ->whereDate('picked_at', $date)
            ->count();
    }

    /**
     * Fortune pick balance: 1 per day, used or not.
     *
     * @return array{fortune_pick_available: bool, fortune_picks_used_today: int, date: string}
     */
    public function getFortunePickBalance(User $user, array $config = []): array
    {
        $today = $this->today();
        $used = $this->fortunePicksUsedOnDate($user, $today);
        $available = $used === 0;

        return [
            'fortune_pick_available' => $available,
            'fortune_picks_used_today' => $used,
            'date' => $today->toDateString(),
        ];
    }

    public function pickFortune(): array
    {
        $lucks = self::FORTUNE_LUCKS;
        $categoryKey = array_rand($lucks);
        $fortune = $lucks[$categoryKey][array_rand($lucks[$categoryKey])];
        return [
            'category' => $categoryKey,
            'title' => $fortune['title'],
            'description' => $fortune['description'],
        ];
    }

    /**
     * Fortune reward: 30% nothing, 60% promo_49, 10% promo_50. Repeatable while stock; fallback to nothing.
     */
    public function performFortuneReward(User $user, array $config): array
    {
        $roll = mt_rand(1, 100);
        $groupId49 = $config['fortune_promo_group_id_49'] ?? 49;
        $groupId50 = $config['fortune_promo_group_id_50'] ?? 50;

        if ($roll <= self::FORTUNE_CHANCE_NOTHING) {
            return ['fortune_reward_type' => 'nothing', 'promotion_code' => null];
        }
        if ($roll <= self::FORTUNE_CHANCE_NOTHING + self::FORTUNE_CHANCE_PROMO_49) {
            $code = $this->assignUnclaimedPromoCodeToUser($groupId49, $user);
            if ($code) {
                return ['fortune_reward_type' => 'promo_49', 'promotion_code' => $code];
            }
            return ['fortune_reward_type' => 'nothing', 'promotion_code' => null];
        }
        // 91-100: promo_50
        $code = $this->assignUnclaimedPromoCodeToUser($groupId50, $user);
        if ($code) {
            return ['fortune_reward_type' => 'promo_50', 'promotion_code' => $code];
        }
        return ['fortune_reward_type' => 'nothing', 'promotion_code' => null];
    }

    /**
     * Consume today's fortune pick: fortune + fortune reward (nothing | promo 49 | 50).
     *
     * @return array{fortune: array, fortune_reward: array, pick_id: int}
     */
    public function consumeFortunePick(User $user, array $config): array
    {
        $balance = $this->getFortunePickBalance($user, $config);
        if (!$balance['fortune_pick_available']) {
            throw new \RuntimeException('Fortune pick already used for today.');
        }

        $fortune = $this->pickFortune();

        return DB::transaction(function () use ($user, $fortune, $config) {
            $rewardResult = $this->performFortuneReward($user, $config);

            $pick = CnyFortunePick::create([
                'user_id' => $user->id,
                'picked_at' => $this->today()->now(),
                'fortune_category' => $fortune['category'],
                'fortune_title' => $fortune['title'],
                'fortune_description' => $fortune['description'],
                'fortune_reward_type' => $rewardResult['fortune_reward_type'],
                'promotion_code_id' => $rewardResult['promotion_code'] ? $rewardResult['promotion_code']->id : null,
            ]);

            $fortuneRewardResponse = $this->formatFortuneRewardResponse($rewardResult);

            return [
                'fortune' => $fortune,
                'fortune_reward' => $fortuneRewardResponse,
                'pick_id' => $pick->id,
            ];
        }, 5);
    }

    protected function formatFortuneRewardResponse(array $result): array
    {
        $type = $result['fortune_reward_type'] ?? 'nothing';
        $out = ['fortune_reward_type' => $type];
        if ($type !== 'nothing' && isset($result['promotion_code'])) {
            $out['code'] = $result['promotion_code']->code;
            $out['promotion_code_id'] = $result['promotion_code']->id;
        }
        return $out;
    }

    // ---------- Lucky draw (once per day, separate pool) ----------

    public function luckyDrawsUsedOnDate(User $user, Carbon $date): int
    {
        return CnyLuckyDraw::where('user_id', $user->id)
            ->whereDate('drawn_at', $date)
            ->count();
    }

    /**
     * Total lucky draws per day = number of Fun cards purchased that day.
     * No base draw; 1 draw per Fun card purchase on that day (e.g. 3 purchases = 3 draws).
     */
    public function totalLuckyDrawsPerDay(User $user, array $config): int
    {
        $today = $this->today();
        return $this->funcardPurchasesCountOnDate($user, $today, $config);
    }

    /**
     * Lucky draw balance: total draws today = Fun cards purchased today; used = draws already taken.
     *
     * @return array{lucky_draw_available: bool, lucky_draws_available: int, lucky_draws_used_today: int, total_draws_today: int, has_funcard_draw_entry: bool, date: string}
     */
    public function getLuckyDrawBalance(User $user, array $config): array
    {
        $today = $this->today();
        $total = $this->totalLuckyDrawsPerDay($user, $config);
        $used = $this->luckyDrawsUsedOnDate($user, $today);
        $available = max(0, $total - $used);

        return [
            'lucky_draw_available' => $available > 0,
            'lucky_draws_available' => $available,
            'lucky_draws_used_today' => $used,
            'total_draws_today' => $total,
            'has_funcard_draw_entry' => $total > 0,
            'date' => $today->toDateString(),
        ];
    }

    /**
     * Lucky draw: 90% repeatable (46/47/48), 10% non-repeatable (merchandise). Within repeatable 46=30%, 47=60%, 48=10%.
     */
    public function performLuckyDraw(User $user, array $config): array
    {
        $repeatableChance = $config['lucky_draw_repeatable_pool_chance'] ?? 90;
        $groupId46 = $config['lucky_draw_promo_group_id_46'] ?? 46;
        $groupId47 = $config['lucky_draw_promo_group_id_47'] ?? 47;
        $groupId48 = $config['lucky_draw_promo_group_id_48'] ?? 48;
        $roll = mt_rand(1, 100);

        if ($roll <= $repeatableChance) {
            // Repeatable pool: 46=30%, 47=60%, 48=10%
            $inner = mt_rand(1, 100);
            if ($inner <= self::LUCKY_DRAW_CHANCE_46) {
                $code = $this->assignUnclaimedPromoCodeToUser($groupId46, $user);
                if ($code) {
                    return ['reward_type' => 'promo_code', 'promotion_code' => $code, 'group_id' => 46];
                }
            } elseif ($inner <= self::LUCKY_DRAW_CHANCE_46 + self::LUCKY_DRAW_CHANCE_47) {
                $code = $this->assignUnclaimedPromoCodeToUser($groupId47, $user);
                if ($code) {
                    return ['reward_type' => 'promo_code', 'promotion_code' => $code, 'group_id' => 47];
                }
            } else {
                $code = $this->assignUnclaimedPromoCodeToUser($groupId48, $user);
                if ($code) {
                    return ['reward_type' => 'promo_code', 'promotion_code' => $code, 'group_id' => 48];
                }
            }
            return ['reward_type' => 'nothing', 'promotion_code' => null, 'merchandise' => null];
        }

        // Non-repeatable: merchandise (0.01 - 10.00)
        $physicalRoll = mt_rand(1, 1000) / 100.0;
        $merchandise = $this->drawMerchandise($physicalRoll);
        if ($merchandise) {
            return ['reward_type' => 'merchandise', 'promotion_code' => null, 'merchandise' => $merchandise];
        }

        return ['reward_type' => 'nothing', 'promotion_code' => null, 'merchandise' => null];
    }

    /**
     * Consume one lucky draw (only when user has draw entries from Fun card purchases today).
     *
     * @return array{lucky_draw: array, draw_id: int}
     */
    public function consumeLuckyDraw(User $user, array $config): array
    {
        $balance = $this->getLuckyDrawBalance($user, $config);
        if (!$balance['lucky_draw_available'] || $balance['lucky_draws_available'] <= 0) {
            throw new \RuntimeException('No lucky draw chances remaining for today.');
        }

        return DB::transaction(function () use ($user, $config) {
            $result = $this->performLuckyDraw($user, $config);

            $draw = CnyLuckyDraw::create([
                'user_id' => $user->id,
                'drawn_at' => $this->today()->now(),
                'reward_type' => $result['reward_type'],
                'promotion_code_id' => isset($result['promotion_code']) ? $result['promotion_code']->id : null,
                'cny_merchandise_id' => isset($result['merchandise']) ? $result['merchandise']->id : null,
            ]);

            if (($result['reward_type'] ?? '') === 'merchandise' && isset($result['merchandise'])) {
                $merch = $result['merchandise'];
                $merch->increment('given_out');
                CnyMerchandiseWin::create([
                    'user_id' => $user->id,
                    'cny_merchandise_id' => $merch->id,
                    'cny_lucky_draw_id' => $draw->id,
                ]);
                if ($user->email) {
                    try {
                        Mail::to($user->email)->queue(new MerchandiseWinEmail($user, $merch, 'CNY Campaign'));
                    } catch (\Throwable $e) {
                        Log::warning('[CnyCampaignService] Failed to queue merchandise win email', [
                            'user_id' => $user->id,
                            'cny_merchandise_id' => $merch->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            }

            $response = $this->formatLuckyDrawResponse($result);

            return [
                'lucky_draw' => $response,
                'draw_id' => $draw->id,
            ];
        }, 5);
    }

    protected function formatLuckyDrawResponse(array $result): array
    {
        $type = $result['reward_type'] ?? 'nothing';
        $out = ['reward_type' => $type];
        if ($type === 'promo_code' && isset($result['promotion_code'])) {
            $out['code'] = $result['promotion_code']->code;
            $out['promotion_code_id'] = $result['promotion_code']->id;
            $out['promotion_code_group_id'] = $result['group_id'] ?? null;
        }
        if ($type === 'merchandise' && isset($result['merchandise'])) {
            $out['merchandise_name'] = $result['merchandise']->name;
            $out['merchandise_id'] = $result['merchandise']->id;
        }
        return $out;
    }

    // ---------- Shared ----------

    protected function assignUnclaimedPromoCodeToUser(int $groupId, User $user): ?PromotionCode
    {
        $code = PromotionCode::where('promotion_code_group_id', $groupId)
            ->whereNull('claimed_by_id')
            ->where('is_redeemed', false)
            ->where('status', true)
            ->lockForUpdate()
            ->first();

        if (!$code) {
            return null;
        }

        $code->update(['claimed_by_id' => $user->id]);
        $code = $code->fresh();

        if ($user->email) {
            try {
                Mail::to($user->email)->queue(new PromoCodeRewardEmail($user, $code, 'CNY Campaign'));
            } catch (\Throwable $e) {
                Log::warning('[CnyCampaignService] Failed to queue promo code reward email', [
                    'user_id' => $user->id,
                    'promotion_code_id' => $code->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $code;
    }

    protected function drawMerchandise(float $roll): ?CnyMerchandise
    {
        $items = CnyMerchandise::whereColumn('given_out', '<', 'quantity')
            ->orderBy('order')
            ->get();

        if ($items->isEmpty()) {
            return null;
        }

        $cumulative = 0.0;
        foreach ($items as $item) {
            $cumulative += (float) $item->win_percentage;
            if ($roll <= $cumulative) {
                return $item;
            }
        }
        return null;
    }
}
