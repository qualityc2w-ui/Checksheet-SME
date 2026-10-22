<?php

namespace App\Mail;

use App\Models\Finding;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class FindingPicNotification extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Finding $finding) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Tindak lanjut temuan QC '.$this->finding->code);
    }

    public function content(): Content
    {
        return new Content(text: 'emails.finding-pic');
    }
}
