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

    protected $message, $category, $title, $notificationType;

    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct(SupportRequestMessage $message, $notificationType = 'initial')
    {
        $this->message = $message;
        $this->notificationType = $notificationType;
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
     * @return MailMessage
     */
    public function toMail($notifiable)
    {
        $subject = ($this->notificationType === 'initial') ? 'New Support Request Has Been Raised' : 'Support Request Updated';

        $content = ($this->notificationType === 'initial') ? 'Kindly note that a new support request has been raised' : 'Kindly note that a support request has been updated';

        return (new MailMessage)
            ->subject($subject)
            ->markdown('emails.new-support-request-raised', [
                'category' => $this->message->request->category->name,
                'title' => $this->message->request['title'],
                'content' => $content,
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
