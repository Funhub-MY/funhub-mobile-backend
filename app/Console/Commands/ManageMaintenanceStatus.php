<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use App\Models\Maintenance;
use Illuminate\Console\Command;

class ManageMaintenanceStatus extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'manage-maintenance-status';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Activate or deactivate maintenance based on scheduled dates';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $now = Carbon::now();

        Maintenance::where('start_date', '<=', $now)
            ->where('end_date', '>=', $now)
            ->update(['is_active' => true]);

        Maintenance::where('end_date', '<', $now)
            ->update(['is_active' => false]);

        $this->info('Maintenance status updated successfully.');

        return Command::SUCCESS;
    }
}
