<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use App\Models\SupportRequestMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

class NewSupportRequestRaised extends Notification implements ShouldQueue
{
    use Queueable;

    protected $message, $category, $title;

    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct(SupportRequestMessage $message)
    {
        $this->message = $message;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function via($notifiable)
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return \Illuminate\Notifications\Messages\MailMessage
     */
    public function toMail($notifiable)
    {
        return (new MailMessage)
            ->subject('New Support Request Has Been Raised')
            ->markdown('emails.new-support-request-raised', [
                'category' => $this->message->request->category->name,
                'title' => $this->message->request['title'],
            ]);
    }

    /**
     * Get the array representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function toArray($notifiable)
    {
        return [
            //
        ];
    }
}
