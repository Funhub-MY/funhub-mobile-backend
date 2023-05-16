<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class S3CloneCommand extends Command
{
    protected $signature = 's3:clone';
    protected $description = 'Clone files and folders from local storage to S3 if filesystem.default is set to S3';
    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        if (config('filesystems.default') !== 's3' || config('filesystems.default') !== 's3-public') {
            $this->error('The default filesystem is not set to S3.');
            return;
        }

        $localPath = $this->ask('Enter the local path to clone from',  storage_path('app/public'));
        $visibility = $this->choice('Do you want to make the files public?', ['public', 'private'], 0);
        $s3Path = $this->ask('Enter the S3 path to clone to', '/');

        try {
            Storage::disk(config('filesystems.default'))
                ->copyDirectory($localPath, $s3Path, $visibility);

            $this->info('Files and folders cloned successfully to S3.');
        } catch (\Exception $e) {
            $this->error('An error occurred while cloning files and folders to S3: ' . $e->getMessage());
        }
    }
}
