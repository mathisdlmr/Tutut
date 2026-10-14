<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Auth;

/**
 * Widget de gestion de l'abonnement aux mails
 *
 * Permet à chaque utilisateur d'accepter ou de refuser les mails envoyés
 * depuis la page "Envoyer un mail" (champ `accepts_emails` de la table users).
 */
class EmailSubscriptionWidget extends Widget
{
    protected static string $view = 'filament.widgets.email-subscription-widget';

    public function toggle(): void
    {
        $user = Auth::user();
        $user->accepts_emails = !$user->accepts_emails;
        $user->save();
    }
}
