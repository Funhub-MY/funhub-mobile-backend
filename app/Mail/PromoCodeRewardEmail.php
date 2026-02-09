<?php

namespace App\Mail;

use App\Models\PromotionCode;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PromoCodeRewardEmail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public PromotionCode $promotionCode,
        public string $source = 'CNY Campaign'
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your FUNHUB Reward – Promo Code ' . $this->promotionCode->code,
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.promo-code-reward',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
