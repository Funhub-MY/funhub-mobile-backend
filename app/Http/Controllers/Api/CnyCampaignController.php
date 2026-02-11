<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\CnyCampaignService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CnyCampaignController extends Controller
{
    public function __construct(
        protected CnyCampaignService $cnyService
    ) {}

    /**
     * CNY campaign config (hardcoded). Provide campaign_id and funcard_product_ids here.
     */
    private function getCnyConfig(): array
    {
        return [
            'campaign_id' => 69,
            'fortune_promo_group_id_49' => 49,
            'fortune_promo_group_id_50' => 50,
            'lucky_draw_promo_group_id_46' => 46,
            'lucky_draw_promo_group_id_47' => 47,
            'lucky_draw_promo_group_id_48' => 48,
            'lucky_draw_repeatable_pool_chance' => 90,
            'funcard_product_ids' => [30,31,32,34],
        ];
    }

    /**
     * Get CNY campaign funcard mission status: true if user purchased any funcard product, plus total purchase count.
     *
     * @return JsonResponse
     *
     * @group CNY Campaign
     * @response scenario="success" {
     *   "has_purchased_funcard": true,
     *   "total_funcard_purchases": 5,
     *   "campaign_id": 69
     * }
     */
    public function getFuncardMission(): JsonResponse
    {
        $user = auth()->user();
        $config = $this->getCnyConfig();
        $status = $this->cnyService->getFuncardMissionStatus($user, $config);

        return response()->json([
            'success' => true,
            'data' => array_merge($status, ['campaign_id' => $config['campaign_id']]),
        ]);
    }

    /**
     * Get CNY fortune pick balance for today (once per day)
     *
     * @return JsonResponse
     *
     * @group CNY Campaign
     * @response scenario="success" {
     *   "fortune_pick_available": true,
     *   "fortune_picks_used_today": 0,
     *   "date": "2026-02-10"
     * }
     */
    public function getPickBalance(): JsonResponse
    {
        $user = auth()->user();
        $config = $this->getCnyConfig();
        $balance = $this->cnyService->getFortunePickBalance($user, $config);

        return response()->json([
            'success' => true,
            'data' => array_merge($balance, ['campaign_id' => $config['campaign_id']]),
        ]);
    }

    /**
     * Perform one fortune pick (once per day). Returns fortune + fortune reward (nothing | promo code).
     *
     * @return JsonResponse
     *
     * @group CNY Campaign
     * @response scenario="success" {
     *   "fortune": { "category": "peace", "title": "平安顺心", "description": "..." },
     *   "fortune_reward": { "fortune_reward_type": "promo_49", "code": "AB1234", ... }
     * }
     */
    public function postFortunePick(): JsonResponse
    {
        $user = auth()->user();

        $config = $this->getCnyConfig();
        try {
            $result = $this->cnyService->consumeFortunePick($user, $config);
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'data' => ['fortune_pick_available' => false],
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Fortune pick successful.',
            'data' => [
                'campaign_id' => $config['campaign_id'],
                'fortune' => $result['fortune'],
                'fortune_reward' => $result['fortune_reward'],
                'pick_id' => $result['pick_id'],
            ],
        ]);
    }

    /**
     * Get current user's CNY fortune pick history
     *
     * @param Request $request
     * @return JsonResponse
     *
     * @group CNY Campaign
     * @queryParam page integer optional Page for pagination. Example: 1
     * @queryParam per_page integer optional Per page. Example: 10
     */
    public function getMyPicks(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();
        $perPage = min((int) $request->get('per_page', 15), 50);
        $picks = $user->cnyFortunePicks()
            ->orderByDesc('picked_at')
            ->paginate($perPage);

        $items = $picks->getCollection()->map(function ($pick) {
            $item = [
                'id' => $pick->id,
                'picked_at' => $pick->picked_at->toIso8601String(),
                'fortune' => [
                    'category' => $pick->fortune_category,
                    'title' => $pick->fortune_title,
                    'description' => $pick->fortune_description,
                ],
                'fortune_reward_type' => $pick->fortune_reward_type,
            ];
            if (in_array($pick->fortune_reward_type, ['promo_49', 'promo_50']) && $pick->promotionCode) {
                $item['promotion_code'] = $pick->promotionCode->code;
            }
            return $item;
        });

        return response()->json([
            'success' => true,
            'data' => [
                'picks' => $items,
                'pagination' => [
                    'current_page' => $picks->currentPage(),
                    'per_page' => $picks->perPage(),
                    'total' => $picks->total(),
                    'last_page' => $picks->lastPage(),
                ],
            ],
        ]);
    }

    /**
     * Get CNY lucky draw balance for today (no base draw; total draws = Fun cards purchased today, e.g. 3 purchases = 3 draws)
     *
     * @return JsonResponse
     *
     * @group CNY Campaign
     * @response scenario="success" {
     *   "lucky_draw_available": true,
     *   "lucky_draws_available": 3,
     *   "lucky_draws_used_today": 0,
     *   "total_draws_today": 3,
     *   "has_funcard_draw_entry": true,
     *   "date": "2026-02-10"
     * }
     */
    public function getLuckyDrawBalance(): JsonResponse
    {
        $user = auth()->user();
        $config = $this->getCnyConfig();
        $balance = $this->cnyService->getLuckyDrawBalance($user, $config);

        return response()->json([
            'success' => true,
            'data' => array_merge($balance, ['campaign_id' => $config['campaign_id']]),
        ]);
    }

    /**
     * Perform one lucky draw (when user has entries from Fun card purchases today; 1 draw per purchase). Returns reward: nothing | promo code | merchandise.
     *
     * @return JsonResponse
     *
     * @group CNY Campaign
     * @response scenario="success" {
     *   "lucky_draw": { "reward_type": "promo_code", "code": "XY5678", ... }
     * }
     */
    public function postLuckyDraw(): JsonResponse
    {
        $user = auth()->user();

        $config = $this->getCnyConfig();
        try {
            $result = $this->cnyService->consumeLuckyDraw($user, $config);
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'data' => ['lucky_draw_available' => false],
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Lucky draw successful.',
            'data' => [
                'campaign_id' => $config['campaign_id'],
                'lucky_draw' => $result['lucky_draw'],
                'draw_id' => $result['draw_id'],
            ],
        ]);
    }

    /**
     * Get current user's CNY lucky draw history
     *
     * @param Request $request
     * @return JsonResponse
     *
     * @group CNY Campaign
     * @queryParam page integer optional Page for pagination. Example: 1
     * @queryParam per_page integer optional Per page. Example: 10
     */
    public function getMyLuckyDraws(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();
        $perPage = min((int) $request->get('per_page', 15), 50);
        $draws = $user->cnyLuckyDraws()
            ->orderByDesc('drawn_at')
            ->paginate($perPage);

        $items = $draws->getCollection()->map(function ($draw) {
            $item = [
                'id' => $draw->id,
                'drawn_at' => $draw->drawn_at->toIso8601String(),
                'reward_type' => $draw->reward_type,
            ];
            if ($draw->reward_type === 'promo_code' && $draw->promotionCode) {
                $item['promotion_code'] = $draw->promotionCode->code;
            }
            if ($draw->reward_type === 'merchandise' && $draw->cnyMerchandise) {
                $item['merchandise_name'] = $draw->cnyMerchandise->name;
            }
            return $item;
        });

        return response()->json([
            'success' => true,
            'data' => [
                'lucky_draws' => $items,
                'pagination' => [
                    'current_page' => $draws->currentPage(),
                    'per_page' => $draws->perPage(),
                    'total' => $draws->total(),
                    'last_page' => $draws->lastPage(),
                ],
            ],
        ]);
    }
}
