<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class S3CloneCommand extends Command
{
    protected $signature = 'storage:clone-local-to-cloud';

    protected $description = 'Clone files from local storage to Huawei OBS when cloud storage is configured';

    public function handle(): int
    {
        if (! storage_is_cloud()) {
            $this->error('Set FILESYSTEM_DISK to hwc_obs (or legacy s3) to use cloud storage.');

            return Command::FAILURE;
        }

        $localPath = $this->ask('Enter the local path to clone from', storage_path('app/public'));
        $visibility = $this->choice('Do you want to make the files public?', ['public', 'private'], 0);
        $cloudPath = $this->ask('Enter the cloud path to clone to', '/');

        try {
            Storage::disk(storage_private_disk())
                ->copyDirectory($localPath, $cloudPath, $visibility);

            $this->info('Files cloned successfully to Huawei OBS.');
        } catch (\Exception $e) {
            $this->error('An error occurred while cloning files: ' . $e->getMessage());

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
