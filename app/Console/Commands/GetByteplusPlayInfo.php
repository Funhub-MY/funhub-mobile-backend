<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\ByteplusService;

class GetByteplusPlayInfo extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'byteplus:play-info {vid : The video ID} {--format= : The format of the video} {--definition= : The video definition} {--streamType= : The stream type}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Get play info for a Byteplus video';

    /**
     * Execute the console command.
     */
    public function handle(ByteplusService $byteplusService)
    {
        $params = [
            'vid' => $this->argument('vid'),
            'format' => $this->option('format'),
            'definition' => $this->option('definition'),
            'streamType' => $this->option('streamType'),
        ];

        // Remove null options
        $params = array_filter($params);

        $result = $byteplusService->getPlayInfo($params);

        $this->info('Play info retrieved successfully:');
        $this->line(json_encode($result, JSON_PRETTY_PRINT));
    }
}
