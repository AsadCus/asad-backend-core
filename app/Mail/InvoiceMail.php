<?php

namespace App\Mail;

use App\Models\Invoice;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Attachment;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Header\UnstructuredHeader;

class InvoiceMail extends Mailable
{
    use Queueable, SerializesModels;

    public Invoice $invoice;

    public string $pdfContent;

    /**
     * Create a new message instance.
     */
    public function __construct(Invoice $invoice, string $pdfContent)
    {
        $this->invoice = $invoice;
        $this->pdfContent = $pdfContent;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address(config('mail.from.address'), config('mail.from.name')),
            subject: 'Invoice '.$this->invoice->invoice_number.' from '.config('app.name'),
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
            view: 'mail.invoice-email',
            with: [
                'invoice' => $this->invoice,
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
            Attachment::fromData(fn () => $this->pdfContent, 'invoice_'.$this->invoice->invoice_number.'.pdf')
                ->withMime('application/pdf'),
        ];
    }
}
