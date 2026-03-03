<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Attachment;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Queue\SerializesModels;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Header\UnstructuredHeader;

class WelcomeMail extends Mailable
{
    use Queueable, SerializesModels;

    private string $name;

    private string $email;

    private string $password;

    public function __construct(string $name, string $email, string $password)
    {
        $this->name = $name;
        $this->email = $email;
        $this->password = $password;
    }

    /**
     * Define the envelope (headers, sender, etc.)
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address(config('mail.from.address'), config('mail.from.name')),
            subject: 'Welcome to '.config('app.name'),
            using: [
                function (Email $email) {
                    $headers = $email->getHeaders();
                    $headers->addTextHeader('X-Message-Source', parse_url(config('app.url'), PHP_URL_HOST));
                    $headers->add(new UnstructuredHeader('X-Mailer', config('app.name').' Mail System'));
                    $headers->addTextHeader('X-Environment', app()->environment());
                    $headers->addTextHeader('X-Sent-At', now()->toIso8601String());
                },
            ]
        );
    }

    /**
     * Define the mail content (HTML + variables)
     */
    public function content(): Content
    {
        return new Content(
            view: 'mail.welcome-email',
            with: [
                'name' => $this->name,
                'email' => $this->email,
                'password' => $this->password,
                'loginUrl' => rtrim(config('app.url'), '/').'/login',
            ],
        );
    }

    /**
     * Include attachments (local file only, embedded inline)
     */
    // public function attachments(): array
    // {
    //     $logoPath = public_path('logo-primary.png');

    //     if (file_exists($logoPath)) {
    //         return [
    //             Attachment::fromPath($logoPath)
    //                 ->as('logo.png')
    //                 ->withMime('image/png'),
    //         ];
    //     }

    //     return [];
    // }

    /**
     * Optional — Define extra headers at Laravel level
     */
    public function headers(): Headers
    {
        return new Headers(
            messageId: 'welcome-'.uniqid().'@'.parse_url(config('app.url'), PHP_URL_HOST),
            references: [],
            text: [
                'X-App-Environment' => app()->environment(),
                'X-Mail-Category' => 'WelcomeEmail',
                'X-App-Name' => config('app.name'),
            ],
        );
    }
}
