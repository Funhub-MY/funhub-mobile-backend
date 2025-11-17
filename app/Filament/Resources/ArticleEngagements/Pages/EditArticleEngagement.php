<?php

namespace App\Filament\Resources\ArticleEngagements\Pages;

use Filament\Actions\DeleteAction;
use Filament\Support\Exceptions\Halt;
use App\Filament\Resources\ArticleEngagements\ArticleEngagementResource;
use App\Jobs\ProcessEngagementInteractions;
use Carbon\Carbon;
use Filament\Notifications\Notification;
use Filament\Pages\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Queue;

class EditArticleEngagement extends EditRecord
{
    protected static string $resource = ArticleEngagementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function beforeSave(): void
    {
        $engagement = $this->record;

        if ($engagement->executed_at !== null) {
            // If the job has already been executed, don't allow editing
            Notification::make()
                ->warning()
                ->title('This engagement has already been executed and cannot be edited.')
                ->send();

            throw new Halt();
        }
    }

    protected function afterSave(): void
    {
        $engagement = $this->record;

        // Cancel any existing delayed jobs for this engagement first! could be scheduled in the future and
        // yet to execute and you add a duplicate job in!
        Queue::cancel(function ($job) use ($engagement) {
            return $job->resolveName() === ProcessEngagementInteractions::class
                && $job->engagement->is($engagement);
        });

        // dispatch a new job again
        if ($engagement->scheduled_at !== null && Carbon::parse($engagement->scheduled_at)->isFuture()) {
            $delay = Carbon::now()->diffInMinutes(Carbon::parse($engagement->scheduled_at));
            ProcessEngagementInteractions::dispatch($engagement)->delay($delay);
        } else {
            ProcessEngagementInteractions::dispatch($engagement);
        }
    }
}
