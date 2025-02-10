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
    protected $signature = 'byteplus:play-info {vid : The video ID} {--format= : The format of the video} {--definition= : The video definition} {--streamType= : The stream type} {--codec= : The video codec}';

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
            'Action' => 'GetPlayInfo',
            'Version' => '2023-01-01',
            'Vid' => $this->argument('vid'),
            'FileType' => 'video',
            'Definition' => $this->option('definition') ?? 'auto',
            'Format' => $this->option('format') ?? 'hls',
            'Codec' => $this->option('codec') ?? 'H264',
            'Ssl' => '1'
        ];

        // Remove null options
        $params = array_filter($params);

        $result = $byteplusService->getPlayInfo($this->argument('vid'), $params);

        $this->info('Play info retrieved successfully:');
        $this->line(json_encode($result, JSON_PRETTY_PRINT));
    }
}
