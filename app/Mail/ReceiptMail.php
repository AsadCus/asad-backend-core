<?php

namespace App\Mail;

use App\Models\Receipt;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Attachment;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Header\UnstructuredHeader;

class ReceiptMail extends Mailable
{
    use Queueable, SerializesModels;

    public Receipt $receipt;

    public string $pdfContent;

    /**
     * Create a new message instance.
     */
    public function __construct(Receipt $receipt, string $pdfContent)
    {
        $this->receipt = $receipt;
        $this->pdfContent = $pdfContent;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address(config('mail.from.address'), config('mail.from.name')),
            subject: 'Receipt '.$this->receipt->receipt_number.' from '.config('app.name'),
            using: [
                function (Email $email) {
                    $headers = $email->getHeaders();
                    $headers->addTextHeader('X-Message-Source', parse_url(config('app.url'), PHP_URL_HOST));
                    $headers->add(new UnstructuredHeader('X-Mailer', config('app.name').' Mail System'));
                },
            ]
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'mail.receipt-email',
            with: [
                'receipt' => $this->receipt,
            ]
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [
            Attachment::fromData(fn () => $this->pdfContent, 'receipt_'.$this->receipt->receipt_number.'.pdf')
                ->withMime('application/pdf'),
        ];
    }
}
