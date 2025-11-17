<?php

namespace App\Filament\Resources\ArticleEngagements\Pages;

use App\Filament\Resources\ArticleEngagements\ArticleEngagementResource;
use App\Jobs\ProcessEngagementInteractions;
use Carbon\Carbon;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Log;

class CreateArticleEngagement extends CreateRecord
{
    protected static string $resource = ArticleEngagementResource::class;

    protected function afterCreate(): void
    {
        $engagement = $this->record;

        if ($engagement->scheduled_at === null) { // immediate fire, else will rely on RunArticleEngagement cron to fire
            $users = $engagement->users;
            $articleId = $engagement->article_id;
            $action = $engagement->action;
            $comment = $engagement->comment;

            // immediately mark as executed no matter the below succes or failed
            $engagement->executed_at = now();
            $engagement->save();

            // if only one user then direct execute
            if ($users->count() === 1) {
                Log::info("Processing engagement for user ID {$users->first()->id} on article ID {$articleId} with action {$action}");
                ProcessEngagementInteractions::dispatch($engagement->users->first()->id, $articleId, $action, $comment);
                return;
            } else {
                foreach ($users as $user) {
                    Log::info("Processing engagement for user ID {$user->id} on article ID {$articleId} with action {$action}");
                    // random delay between 1min-120hours
                    ProcessEngagementInteractions::dispatch($user->id, $articleId, $action, $comment)
                        ->delay(Carbon::now()->addMinutes(rand(1, 7200)));
                }
            }
        }
    }
}
