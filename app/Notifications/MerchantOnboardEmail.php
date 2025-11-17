<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;

class MerchantOnboardEmail extends Notification implements ShouldQueue
{
    use Queueable;

    protected $merchantName, $userEmail, $defaultPassword, $redeemCode;

    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct($merchantName, $userEmail, $defaultPassword, $redeemCode)
    {
        $this->merchantName = $merchantName;
        $this->userEmail = $userEmail;
        $this->defaultPassword = $defaultPassword;
        $this->redeemCode = $redeemCode;
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
        return (new MailMessage)
            ->subject('Welcome to FUNHUB Merchant Portal')
            ->view('emails.merchant-onboard', [
                'merchantName' => $this->merchantName,
                'userEmail' => $this->userEmail,
                'defaultPassword' => $this->defaultPassword,
                'redeemCode' => $this->redeemCode,
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
