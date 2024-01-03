<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class VoucherRedeemedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $username, $merchantName, $merchantOffer;

    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct($username, $merchantName, $offer)
    {
        $this->username = $username;
        $this->merchantName = $merchantName;
        $this->merchantOffer = $offer;
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
        ->subject('A User Has Redeemed Your Voucher')
        ->markdown('emails.voucher-redeemed', [
            'username' => $this->username,
            'merchantName' => $this->merchantName,
            'merchantOffer' => $this->merchantOffer->name,
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
