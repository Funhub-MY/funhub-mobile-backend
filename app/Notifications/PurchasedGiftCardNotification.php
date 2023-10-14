<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PurchasedGiftCardNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $transactionNo, $dateTime, $itemTitle, $quantity, $subtotal;

    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct($transactionNo, $dateTime, $itemTitle, $quantity, $subtotal)
    {
        $this->transactionNo = $transactionNo;
        $this->dateTime = $dateTime;
        $this->itemTitle = $itemTitle;
        $this->quantity = $quantity;
        $this->subtotal = $subtotal;
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
            ->subject('Purchase Receipt')
            ->markdown('emails.purchased-gift-card', [
                'transactionNo' => $this->transactionNo,
                'dateTime' => $this->dateTime,
                'itemTitle' => $this->itemTitle,
                'quantity' => $this->quantity,
                'subtotal' => $this->subtotal
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
