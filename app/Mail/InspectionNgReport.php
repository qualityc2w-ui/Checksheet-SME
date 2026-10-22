<?php

namespace App\Mail;

use App\Models\Inspection;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class InspectionNgReport extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Inspection $inspection) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Laporan temuan QC NG · '.$this->inspection->product?->kode);
    }

    public function content(): Content
    {
        return new Content(text: 'emails.inspection-ng');
    }
}
