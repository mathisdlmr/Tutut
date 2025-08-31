<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class ResetPasswordNotification extends Notification
{
    public string $token;
    public string $url;

    public function __construct(string $token, string $url = null)
    {
        $this->token = $token;
        if ($url) {
            $this->url = $url;
        }
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Réinitialisation de ton mot de passe Tut'ut")
            ->greeting('Hellooo')
            ->line('Tu as demandé la réinitialisation de votre mot de passe ?')
            ->line('Pour réinitialiser ton mot de passe, tu dois cliquer sur le lien ci-dessous :')
            ->action('Réinitialiser mon mot de passe', $this->url)
            ->line("Si tu n'as pas demandé cette réinitialisation, tu peux ignorer ce mail.")
            ->salutation("Pédagogiquement,\nL'équipe de Tut'ut !");
    }
}


