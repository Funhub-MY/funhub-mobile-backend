<?php

namespace App\Jobs;

use App\Models\ArticleEngagement;
use App\Models\Interaction;
use App\Models\Article;
use App\Models\Comment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessEngagementInteractions implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $engagement;

    public function __construct(ArticleEngagement $engagement)
    {
        $this->engagement = $engagement;
    }

    public function handle()
    {
        $engagement = $this->engagement;

        if ($engagement->scheduled_at === null) {
            $users = $engagement->users;
            $articleId = $engagement->article_id;
            $action = $engagement->action;
            $comment = $engagement->comment;

            // immediately mark as executed no matter the below succes or failed
            $engagement->executed_at = now();
            $engagement->save();

            foreach ($users as $user) {
                Log::info("Processing engagement for user ID {$user->id} on article ID {$articleId} with action {$action}");
                $this->createInteraction($user->id, $articleId, $action, $comment);
                sleep(rand(1, 5) * 60); // Sleep for random minutes (1-10)
            }
        }
    }

    /**
     * Create an Interaction on Article (like/comment)
     *
     * @param $userId
     * @param $articleId
     * @param $action
     * @param $comment
     * @return void
     */
    private function createInteraction($userId, $articleId, $action, $comment = null)
    {
        $interactable = Article::class;
        $interactionType = Interaction::TYPE_LIKE;

        if ($action === 'comment') {
            // Create a comment instead of an interaction
            $comment = Comment::create([
                'user_id' => $userId,
                'commentable_type' => $interactable,
                'commentable_id' => $articleId,
                'body' => $comment,
                'status' => Comment::STATUS_PUBLISHED,
            ]);

            event(new \App\Events\CommentCreated($comment));

            if ($comment->commentable->user && $comment->commentable->user->id != $userId) {
                $locale = $comment->commentable->user->last_lang ?? config('app.locale');
                $comment->commentable->user->notify((new \App\Notifications\Commented($comment))->locale($locale));
            }
        } else {
            // Create an interaction
            $interaction = Interaction::firstOrCreate([
                'user_id' => $userId,
                'interactable_type' => $interactable,
                'interactable_id' => $articleId,
                'type' => $interactionType,
            ]);

            event(new \App\Events\InteractionCreated($interaction));

            if ($interactable === Article::class && $interactionType === Interaction::TYPE_LIKE && $interaction->interactable->user->id !== $userId) {
                $locale = $interaction->interactable->user->last_lang ?? config('app.locale');
                $interaction->interactable->user->notify((new \App\Notifications\ArticleInteracted($interaction))->locale($locale));
            }

            if ($interactable === Article::class && $interactionType === Interaction::TYPE_LIKE) {
                \App\Models\View::create([
                    'user_id' => $userId,
                    'viewable_type' => $interactable,
                    'viewable_id' => $articleId,
                    'ip_address' => request()->ip(),
                ]);
            }
        }
    }
}
