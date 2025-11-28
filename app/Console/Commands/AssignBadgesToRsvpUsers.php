<?php

namespace App\Console\Commands;

use App\Models\Badge;
use App\Models\Rsvp;
use App\Models\User;
use App\Models\UserBadge;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AssignBadgesToRsvpUsers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'badges:assign-rsvp 
                            {badge_id : The badge ID to assign}
                            {--dry-run : Run without making changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Assign a badge to users who are in the rsvp_users table';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $badgeId = $this->argument('badge_id');
        $dryRun = $this->option('dry-run');

        // Validate badge exists
        $badge = Badge::find($badgeId);
        if (!$badge) {
            $this->error("Badge ID {$badgeId} not found!");
            return Command::FAILURE;
        }

        $this->info("Badge: {$badge->name} (ID: {$badge->id})");
        $this->info($dryRun ? 'DRY RUN MODE - No changes will be made' : 'LIVE MODE - Changes will be saved');
        $this->newLine();

        // Get all RSVP users
        $rsvpUsers = Rsvp::all();
        $this->info("Found {$rsvpUsers->count()} RSVP users");

        $assigned = 0;
        $skipped = 0;
        $notFound = 0;

        foreach ($rsvpUsers as $rsvp) {
            // Find user by email or phone
            $user = User::where('email', $rsvp->email)
                ->orWhere('phone_no', $rsvp->phone_no)
                ->first();

            if (!$user) {
                $notFound++;
                continue;
            }

            // Check if user already has this badge
            $existingBadge = UserBadge::where('user_id', $user->id)
                ->where('badge_id', $badge->id)
                ->exists();

            if ($existingBadge) {
                $skipped++;
                continue;
            }

            // Assign badge
            if (!$dryRun) {
                UserBadge::create([
                    'user_id' => $user->id,
                    'badge_id' => $badge->id,
                    'earned_at' => now(),
                    'metadata' => ['source' => 'rsvp_migration', 'rsvp_email' => $rsvp->email],
                    'is_active' => false,
                ]);
            }

            $assigned++;
        }

        $this->newLine();
        $this->info("Summary:");
        $this->table(
            ['Metric', 'Count'],
            [
                ['Total RSVP Users', $rsvpUsers->count()],
                ['Users Found', $assigned + $skipped],
                ['Badges Assigned', $assigned],
                ['Already Had Badge', $skipped],
                ['Users Not Found', $notFound],
            ]
        );

        if ($dryRun) {
            $this->newLine();
            $this->warn("This was a dry run. Run without --dry-run to apply changes.");
        } else {
            $this->newLine();
            $this->info("Badge assignment completed!");
            
            Log::info('AssignBadgesToRsvpUsers completed', [
                'badge_id' => $badgeId,
                'badge_name' => $badge->name,
                'assigned' => $assigned,
                'skipped' => $skipped,
                'not_found' => $notFound,
            ]);
        }

        return Command::SUCCESS;
    }
}
