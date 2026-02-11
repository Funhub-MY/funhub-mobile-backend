<?php

namespace App\Mail;

use App\Models\CnyMerchandise;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class MerchandiseWinEmail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public CnyMerchandise $merchandise,
        public string $source = 'CNY Campaign'
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Congratulations! You won: ' . $this->merchandise->name,
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.merchandise-win',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
