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

    public array $pdfAttachments;

    public string $customSubject;

    public string $customMessage;

    public bool $isBulk;

    /**
     * Create a new message instance.
     */
    public function __construct(Invoice $invoice, array $pdfAttachments, string $customSubject, string $customMessage, bool $isBulk = false)
    {
        $this->invoice = $invoice;
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
            from: new Address(config('mail.from.address'), config('mail.from.name')),
            subject: $this->customSubject,
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
                'customMessage' => $this->customMessage,
                'isBulk' => $this->isBulk,
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
        $attachments = [];
        foreach ($this->pdfAttachments as $pdf) {
            $attachments[] = Attachment::fromData(fn () => $pdf['content'], $pdf['filename'])
                ->withMime('application/pdf');
        }

        return $attachments;
    }
}
