<?php

namespace App\Filament\Resources\Tutee\BecomeTutorResource\Pages;

use App\Filament\Resources\Tutee\BecomeTutorResource;
use App\Models\BecomeTutor;
use App\Models\UV;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Auth;

/**
 * Page de création d'une demande pour devenir tuteur
 *
 * Cette page permet aux tutorés de soumettre leur candidature
 * pour devenir tuteur. Elle gère à la fois la création d'une
 * nouvelle demande et la mise à jour d'une demande existante.
 */
class CreateBecomeTutorRequest extends CreateRecord
{
    protected static string $resource = BecomeTutorResource::class;

    /**
     * Obtient le titre de la page
     *
     * @return string|Htmlable Le titre traduit
     */
    public function getTitle(): string|Htmlable
    {
        return __('resources.become_tutor.title');
    }

    /**
     * Désactive le bouton de création par défaut
     *
     * Cette méthode permet de personnaliser les actions du formulaire
     * en utilisant la méthode getFormActions() à la place.
     *
     * @return bool Toujours faux pour masquer le bouton par défaut
     */
    protected function hasCreateAction(): bool
    {
        return false;
    }

    /**
     * Définit les actions personnalisées du formulaire
     *
     * Ajoute un bouton de sauvegarde et un bouton de suppression
     * si une demande existe déjà pour l'utilisateur.
     *
     * @return array Liste des actions du formulaire
     */
    protected function getFormActions(): array
    {
        return [
            Action::make('save')
                ->label(__('resources.become_tutor.actions.save'))
                ->submit('create')
                ->color('primary'),
            Action::make('delete')
                ->label(__('resources.become_tutor.actions.delete'))
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading(__('resources.become_tutor.actions.delete_modal_title'))
                ->modalDescription(__('resources.become_tutor.actions.delete_modal_description'))
                ->action(function () {
                    $existingRequest = Auth::user()->becomeTutorRequest;
                    if ($existingRequest) {
                        $existingRequest->delete();

                        Notification::make()
                            ->title(__('resources.become_tutor.notifications.deleted_title'))
                            ->body(__('resources.become_tutor.notifications.deleted_body'))
                            ->danger()
                            ->send();

                        $this->form->fill();
                    }
                })
                ->visible(fn () => (bool) Auth::user()->becomeTutorRequest)
        ];
    }

    /**
     * Initialise la page et remplit les champs si une demande existe déjà
     *
     * Cette méthode est exécutée lorsque la page est chargée.
     * Elle vérifie si l'utilisateur a déjà une demande en cours
     * et pré-remplit le formulaire avec ces données.
     */
    public function mount(): void
    {
        $existingRequest = Auth::user()->becomeTutorRequest;

        if ($existingRequest) {
            $this->form->fill([
                'fk_user' => $existingRequest->fk_user,
                'semester' => $existingRequest->semester,
                'motivation' => $existingRequest->motivation,
                'UVs' => collect($existingRequest->UVs)->pluck('code')->toArray(),
                'status' => $existingRequest->status
            ]);
        } else {
            parent::mount();
        }
    }

    /**
     * Affiche une notification après la création d'une demande
     *
     * Cette méthode est exécutée après la création réussie d'une demande.
     */
    protected function afterCreate(): void
    {
        Notification::make()
            ->title(__('resources.become_tutor.notifications.submitted_title'))
            ->body(__('resources.become_tutor.notifications.submitted_body'))
            ->success()
            ->send();
    }

    /**
     * Transforme les données du formulaire avant la création
     *
     * Cette méthode:
     * - Convertit les codes UV en objets complets avec intitulés
     * - Force le statut à "pending" (en attente)
     * - Ajoute l'ID de l'utilisateur connecté
     *
     * @param array $data Les données du formulaire
     * @return array Les données transformées
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (isset($data['UVs'])) {
            $uvList = [];
            foreach ($data['UVs'] as $uvCode) {
                $uv = UV::where('code', $uvCode)->first();
                if ($uv) {
                    $uvList[] = [
                        'code' => $uv->code,
                        'intitule' => $uv->intitule
                    ];
                }
            }
            $data['UVs'] = $uvList;
        }
        $data['status'] = 'pending';
        $data['fk_user'] = Auth::id();
        return $data;
    }

    /**
     * Gère la création d'un enregistrement ou la mise à jour d'une demande existante
     *
     * Cette méthode vérifie si l'utilisateur a déjà soumis une demande.
     * Si c'est le cas, elle met à jour cette demande au lieu d'en créer une nouvelle.
     *
     * @param array $data Les données du formulaire
     * @return \Illuminate\Database\Eloquent\Model L'enregistrement créé ou mis à jour
     */
    protected function handleRecordCreation(array $data): \Illuminate\Database\Eloquent\Model
    {
        $existingRequest = BecomeTutor::where('fk_user', Auth::id())->first();

        if ($existingRequest) {
            $existingRequest->update($data);
            return $existingRequest;
        }

        return static::getModel()::create($data);
    }
}
