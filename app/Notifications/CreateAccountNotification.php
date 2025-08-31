<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class CreateAccountNotification extends Notification
{
    public string $token;
    public string $url;
    public string $name;

    public function __construct(string $token, string $url = null, string $name = null)
    {
        $this->token = $token;
        if ($url) {
            $this->url = $url;
        }
        $this->name = $name;
    }

    public function via(): array
    {
        return ['mail'];
    }

    public function toMail(): MailMessage
    {
        return (new MailMessage)
            ->subject("Création de votre compte Tut'ut !")
            ->greeting('Hellooo '.$this->name)
            ->line("Tu as essayé de créer un compte sur le site Tut'ut ?")
            ->line("Afin de valider ton compte, tu dois cliquer sur le lien ci-dessous :")
            ->action('Créer mon compte !', $this->url)
            ->line("Si tu n'es pas à l'origine de cette demande, tu peux ignorer ce mail.")
            ->salutation("Pédagogiquement,\nL'équipe de Tut'ut !");
    }
}


