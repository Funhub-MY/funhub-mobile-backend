<?php

namespace App\Console\Commands;

use App\Models\Article;
use Carbon\Carbon;
use Illuminate\Console\Command;

class MoveMediaDisk extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'media:move-disk {model} {from_date} {to_date}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Moves spatie medialibrary media file disk';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $model = $this->argument('model');

        $originDisk = $this->ask('Origin disk?');
        $destinationDisk = $this->ask('Destination disk?');

        if (!class_exists($model)) {
            $this->error('Model does not exist');
            return Command::FAILURE;
        }

        $from_date = $this->argument('from_date');
        $to_date = $this->argument('to_date');

        // ensure from_date and to_date is is yyyy-mm-dd format
        if (!Carbon::createFromFormat('Y-m-d', $from_date) || !Carbon::createFromFormat('Y-m-d', $to_date)) {
            $this->error('Invalid date format, please use yyyy-mm-dd');
            return Command::FAILURE;
        }

        // check if model has MEDIA_COLLECTION_NAME const
        if (!defined("$model::MEDIA_COLLECTION_NAME")) {
            $this->error('Model does not have MEDIA_COLLECTION_NAME constant, please set it');
            return Command::FAILURE;
        }

        $model = new $model;

        // model where created_at date between from and to
        $items = $model::whereBetween('created_at', [$from_date, $to_date])
            ->get();

        $this->info('Total items: ' . $items->count());

        $items->each(function ($model) use ($originDisk, $destinationDisk) {
            $this->info('Moving media for model ID: ' . $model->id);

            // get media count
            $this->info('Media count: ' . $model->getMedia($model::MEDIA_COLLECTION_NAME)->count());

            $model->getMedia($model::MEDIA_COLLECTION_NAME)->each(function ($media) use ($model, $originDisk, $destinationDisk) {
                if ($media->disk === $originDisk) {
                    $this->info('Moving media: ' . $media->id. ' to disk '. $destinationDisk);
                    $media->move($model, $model::MEDIA_COLLECTION_NAME, $destinationDisk);
                } else {
                    // media is already on destination disk
                    $this->info('Media: ' . $media->id. ' is already on disk '. $destinationDisk);
                }
            });
        });

        return Command::SUCCESS;
    }
}
