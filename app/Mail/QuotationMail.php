<?php

namespace App\Mail;

use App\Models\Quotation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class QuotationMail extends Mailable
{
    use Queueable, SerializesModels;

    public $quotation;

    public array $pdfAttachments;

    public $customSubject;

    public $customMessage;

    public bool $isBulk;

    /**
     * Create a new message instance.
     */
    public function __construct(Quotation $quotation, array $pdfAttachments, string $customSubject, string $customMessage, bool $isBulk = false)
    {
        $this->quotation = $quotation;
        $this->pdfAttachments = $pdfAttachments;
        $this->customSubject = $customSubject;
        $this->customMessage = $customMessage;
        $this->isBulk = $isBulk;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->customSubject,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'mail.quotation-email',
            with: [
                'quotation' => $this->quotation,
                'customMessage' => $this->customMessage,
                'isBulk' => $this->isBulk,
            ],
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        $attachments = [];
        foreach ($this->pdfAttachments as $pdf) {
            $attachments[] = Attachment::fromData(fn () => $pdf['content'], $pdf['filename'])
                ->withMime('application/pdf');
        }

        return $attachments;
    }
}
