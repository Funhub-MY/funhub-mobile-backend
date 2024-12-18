<?php

namespace App\Notifications;

use App\Models\MerchantOffer;
use App\Models\Transaction;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PurchasedOfferNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $transactionNo, $dateTime, $itemTitle, $quantity, $subtotal, $currencyType, $purchaseDate, $purchaseTime, $redemptionStartDate, $redemptionEndDate, $encryptedData, $merchantName, $userName, $merchantOfferCover;

    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct($transactionNo, $dateTime, $itemTitle, $quantity, $subtotal, $currencyType, $purchaseDate, $purchaseTime, $redemptionStartDate, $redemptionEndDate, $encryptedData, $merchantName, $userName, $merchantOfferCover)
    {
        $this->transactionNo = $transactionNo;
        $this->dateTime = $dateTime;
        $this->itemTitle = $itemTitle;
        $this->quantity = $quantity;
        $this->subtotal = $subtotal;
        $this->currencyType = $currencyType;
		$this->purchaseDate = $purchaseDate;
		$this->purchaseTime = $purchaseTime;
		$this->redemptionStartDate = $redemptionStartDate;
		$this->redemptionEndDate = $redemptionEndDate;
        $this->encryptedData = $encryptedData;
        $this->merchantName = $merchantName;
        $this->userName = $userName;
        $this->merchantOfferCover = $merchantOfferCover;
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
            ->view('emails.purchased-offer', [
                'transactionNo' => $this->transactionNo,
                'dateTime' => $this->dateTime,
                'itemTitle' => $this->itemTitle,
                'quantity' => $this->quantity,
                'subtotal' => $this->subtotal,
                'currencyType' => $this->currencyType,
				'purchaseDate' => $this->purchaseDate,
				'purchaseTime' => $this->purchaseTime,
				'redemptionStartDate' => $this->redemptionStartDate,
				'redemptionEndDate' => $this->redemptionEndDate,
                'encryptedData' => $this->encryptedData,
                'merchantName' => $this->merchantName,
                'userName' => $this->userName,
				'merchantOfferCover' => $this->merchantOfferCover
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
