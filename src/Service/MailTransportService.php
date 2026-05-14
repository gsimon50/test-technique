<?php

namespace App\Service;

use Symfony\Component\Mime\Email;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

class MailTransportService
{

    public function __construct(
        private MailerInterface $mailer,
    ) {}

    public function mailer(string $form,string $to, string $subject, string $text ): bool {

        $email = (new Email())
        ->from($form)
        ->to($to)
        ->subject($subject)
        ->text($text);


        try {
            $this->mailer->send($email);
            // Mail envoyé avec succès
            return true;
        } catch (TransportExceptionInterface $e) {
            // Erreur d'envoi
            echo $e->getMessage();
            return false;
        }
        
    }
}