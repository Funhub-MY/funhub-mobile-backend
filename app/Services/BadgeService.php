<?php

namespace App\Services;

use App\Models\Badge;
use App\Models\Campaign;
use App\Models\Reservation;
use App\Models\User;
use App\Models\UserBadge;
use Illuminate\Support\Facades\Log;

class BadgeService
{
    /**
     * Award badge to user for a reservation
     * Finds the first active badge for the campaign and awards it
     * 
     * @param Reservation $reservation
     * @return UserBadge|null
     */
    public function awardBadgeForReservation(Reservation $reservation): ?UserBadge
    {
        $campaign = $reservation->campaign;
        
        if (!$campaign) {
            Log::warning('BadgeService: Cannot award badge - reservation has no campaign', [
                'reservation_id' => $reservation->id,
            ]);
            return null;
        }

        // Find active badge for this campaign
        $badge = Badge::active()
            ->forCampaign($campaign->id)
            ->first();

        if (!$badge) {
            Log::info('BadgeService: No badge configured for campaign', [
                'campaign_id' => $campaign->id,
                'reservation_id' => $reservation->id,
            ]);
            return null;
        }

        return $this->awardBadge($reservation->user, $badge, $reservation);
    }

    /**
     * Award a specific badge to a user
     * 
     * @param User $user
     * @param Badge $badge
     * @param Reservation|null $reservation
     * @param array $metadata
     * @return UserBadge|null
     */
    public function awardBadge(
        User $user, 
        Badge $badge, 
        ?Reservation $reservation = null, 
        array $metadata = []
    ): ?UserBadge {
        // Check if user already has this badge
        if ($user->hasBadge($badge->id)) {
            Log::info('BadgeService: User already has this badge', [
                'user_id' => $user->id,
                'badge_id' => $badge->id,
            ]);
            return null;
        }

        // Create the user badge record
        $userBadge = UserBadge::create([
            'user_id' => $user->id,
            'badge_id' => $badge->id,
            'earned_at' => now(),
            'reservation_id' => $reservation?->id,
            'progress_value' => $metadata['progress_value'] ?? null,
            'metadata' => $metadata,
            'is_active' => false, // Not showcase by default
        ]);

        Log::info('BadgeService: Badge awarded to user', [
            'user_id' => $user->id,
            'badge_id' => $badge->id,
            'badge_name' => $badge->name,
            'reservation_id' => $reservation?->id,
        ]);

        return $userBadge;
    }

    /**
     * Award a badge by name to a user
     * 
     * @param User $user
     * @param string $badgeName
     * @param array $metadata
     * @return UserBadge|null
     */
    public function awardBadgeByName(User $user, string $badgeName, array $metadata = []): ?UserBadge
    {
        $badge = Badge::where('name', $badgeName)->active()->first();

        if (!$badge) {
            Log::warning('BadgeService: Badge not found by name', [
                'badge_name' => $badgeName,
            ]);
            return null;
        }

        return $this->awardBadge($user, $badge, null, $metadata);
    }

    /**
     * Award multiple badges to a user
     * 
     * @param User $user
     * @param array $badgeIds
     * @param Reservation|null $reservation
     * @param array $metadata
     * @return array
     */
    public function awardBadges(
        User $user, 
        array $badgeIds, 
        ?Reservation $reservation = null, 
        array $metadata = []
    ): array {
        $awarded = [];
        
        $badges = Badge::whereIn('id', $badgeIds)->active()->get();
        
        foreach ($badges as $badge) {
            $userBadge = $this->awardBadge($user, $badge, $reservation, $metadata);
            if ($userBadge) {
                $awarded[] = $userBadge;
            }
        }

        return $awarded;
    }

    /**
     * Revoke a badge from a user
     * 
     * @param User $user
     * @param Badge $badge
     * @return bool
     */
    public function revokeBadge(User $user, Badge $badge): bool
    {
        $deleted = UserBadge::where('user_id', $user->id)
            ->where('badge_id', $badge->id)
            ->delete();

        if ($deleted) {
            Log::info('BadgeService: Badge revoked from user', [
                'user_id' => $user->id,
                'badge_id' => $badge->id,
            ]);
        }

        return $deleted > 0;
    }

    /**
     * Get all badges for a user
     * 
     * @param User $user
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getUserBadges(User $user)
    {
        return $user->badges()->with('campaign')->get();
    }

    /**
     * Get user's showcase badge
     * 
     * @param User $user
     * @return Badge|null
     */
    public function getShowcaseBadge(User $user): ?Badge
    {
        return $user->getShowcaseBadge();
    }

    /**
     * Set a badge as user's showcase
     * 
     * @param User $user
     * @param Badge $badge
     * @return bool
     */
    public function setShowcaseBadge(User $user, Badge $badge): bool
    {
        $userBadge = UserBadge::where('user_id', $user->id)
            ->where('badge_id', $badge->id)
            ->first();

        if (!$userBadge) {
            return false;
        }

        $userBadge->setAsShowcase();
        return true;
    }

    /**
     * Get badges available for a campaign
     * 
     * @param int $campaignId
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getCampaignBadges(int $campaignId)
    {
        return Badge::active()
            ->forCampaign($campaignId)
            ->get();
    }
}
