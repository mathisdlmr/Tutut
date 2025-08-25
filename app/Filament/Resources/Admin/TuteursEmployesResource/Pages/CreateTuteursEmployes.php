<?php

namespace App\Filament\Resources\Admin\TuteursEmployesResource\Pages;

use App\Filament\Resources\Admin\TuteursEmployesResource;
use App\Models\User;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

/**
 * Page de création des tuteurs employés
 * 
 * Cette page permet d'ajouter plusieurs tuteurs employés en une seule opération
 * en fournissant une liste d'adresses email et un rôle commun à attribuer.
 */
class CreateTuteursEmployes extends CreateRecord
{
    protected static string $resource = TuteursEmployesResource::class;

    public function getTitle(): string
    {
        return __('resources.tuteurs_employes.create_label');
    }
    
    protected function getFormActions(): array
    {
        return [
            $this->getCreateFormAction(),
            $this->getCancelFormAction(),
        ];
    }

    /**
     * Gère la création des utilisateurs à partir des emails fournis
     * 
     * Cette méthode surcharge le comportement standard de CreateRecord pour:
     * - Prendre en charge plusieurs emails à la fois (séparés par virgule)
     * - Créer ou mettre à jour chaque utilisateur avec le rôle spécifié
     * - Afficher une notification avec le nombre d'utilisateurs traités
     * 
     * @param array $data Les données du formulaire (emails et rôle)
     * @return \Illuminate\Database\Eloquent\Model Le premier utilisateur créé (pour la redirection)
     */
    protected function handleRecordCreation(array $data): \Illuminate\Database\Eloquent\Model
    {
        $emails = is_string($data['email']) ? explode(',', $data['email']) : $data['email'];    
        $emails = array_map('trim', $emails);
        $role = $data['role'];
        $createdUsers = collect();
    
        foreach ($emails as $email) {
            $user = User::firstOrCreate(
                ['email' => $email],
                [
                    'role' => $role,
                ]
            );
    
            $createdUsers->push($user);
        }
    
        Notification::make()
            ->title("Création réussie")
            ->body("{$createdUsers->count()} utilisateur·trice·s ont été ajouté·e·s.")
            ->success()
            ->send();
    
        return $createdUsers->first() ?? new User();
    }
    
}
