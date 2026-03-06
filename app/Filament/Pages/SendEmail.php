<?php

namespace App\Filament\Pages;

use App\Enums\Roles;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Components\{Grid, RichEditor, Select, TextInput};
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

/**
 * Page d'envoi d'emails
 *
 * Cette page permet aux administrateurs et tuteurs privilégiés d'envoyer des emails
 * aux différents utilisateurs de la plateforme.
 * Fonctionnalités:
 * - Sélection des destinataires par rôle
 * - Éditeur riche pour le contenu des emails
 * - Gestion de templates d'emails (sauvegarde et chargement)
 * - Aperçu avant envoi
 * - Envoi massif
 */
class SendEmail extends Page implements Forms\Contracts\HasForms
{
    use Forms\Concerns\InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-paper-airplane';
    protected static string $view = 'filament.pages.send-email';
    protected static ?int $navigationSort = 2;

    public $template;
    public $templateName;
    public $templateOptions = [];
    public $mailTitle;
    public $content;
    public $roles = [];

    public $canSendEmailFlag = false;
    public $canPreviewEmailFlag = false;
    public $canSaveTemplateFlag = false;

    public function getTitle(): string
    {
        return __('resources.pages.send_email.title');
    }

    public static function getNavigationLabel(): string
    {
        return __('resources.pages.send_email.title');
    }

    public static function getNavigationGroup(): string
    {
        return __('resources.pages.send_email.navigation_group');
    }

    public function mount()
    {
        $this->templateOptions = $this->getTemplateOptions();
        $this->updateButtonStates();
    }

    public static function canAccess(): bool
    {
        $user = Auth::user();
        return $user && (Auth::user()->role === Roles::EmployedPrivilegedTutor->value
            || Auth::user()->role === Roles::Administrator->value);
    }

    protected function getFormSchema(): array
    {
        return [
            Grid::make(2)->schema([
                Select::make('template')
                    ->label(__('resources.pages.send_email.fields.template'))
                    ->options(fn () => $this->templateOptions)
                    ->reactive()
                    ->afterStateUpdated(function ($state, callable $set) {
                        if ($state && Storage::exists("email-templates/{$state}.json")) {
                            $data = json_decode(Storage::get("email-templates/{$state}.json"), true);
                            $set('mailTitle', $data['title'] ?? '');
                            $set('content', $data['content'] ?? '');
                            // Mettre à jour l'état des boutons après le chargement du template
                            $this->updateButtonStates();
                        }
                    }),
            ]),

            Select::make('roles')
                ->label(__('resources.pages.send_email.fields.roles'))
                ->multiple()
                ->options(
                    collect(Roles::cases())
                    ->mapWithKeys(fn ($role) => [
                        $role->value => match ($role) {
                            Roles::Administrator => __('resources.pages.send_email.roles.administrator'),
                            Roles::EmployedPrivilegedTutor => __('resources.pages.send_email.roles.employed_privileged_tutor'),
                            Roles::EmployedTutor => __('resources.pages.send_email.roles.employed_tutor'),
                            Roles::Tutor => __('resources.pages.send_email.roles.tutor'),
                            Roles::Tutee => __('resources.pages.send_email.roles.tutee'),
                        }
                    ])
                    ->toArray()
                )
                ->reactive()
                ->afterStateUpdated(fn () => $this->updateButtonStates())
                ->required(),

            TextInput::make('mailTitle')
                ->label(__('resources.pages.send_email.fields.mail_title'))
                ->reactive()
                ->afterStateUpdated(fn () => $this->updateButtonStates())
                ->required(),

            RichEditor::make('content')
                ->label(__('resources.pages.send_email.fields.content'))
                ->reactive()
                ->afterStateUpdated(fn () => $this->updateButtonStates())
                ->required(),

            TextInput::make('templateName')
                ->label(__('resources.pages.send_email.fields.template_name'))
                ->placeholder(__('resources.pages.send_email.fields.template_name'))
                ->reactive()
                ->afterStateUpdated(fn () => $this->updateButtonStates())
                ->helperText(__('resources.pages.send_email.fields.template_name_helper')),
        ];
    }

    // Méthode pour mettre à jour l'état des boutons
    public function updateButtonStates()
    {
        $this->canSendEmailFlag = !empty($this->mailTitle) && !empty($this->content) && !empty($this->roles);
        $this->canPreviewEmailFlag = !empty($this->mailTitle) && !empty($this->content);
        $this->canSaveTemplateFlag = !empty($this->templateName) && !empty($this->mailTitle) && !empty($this->content);
    }

    // S'assurer que les changements de formulaire déclenchent les mises à jour
    public function updated($property)
    {
        $this->updateButtonStates();
    }

    public function previewEmail()
    {
        $this->dispatch('open-modal', id: 'email-preview');
    }

    public function sendEmail()
    {
        if (empty($this->roles)) {
            Notification::make()
                ->title(__('resources.pages.send_email.notifications.error_title'))
                ->body(__('resources.pages.send_email.notifications.error_select_role'))
                ->danger()
                ->send();
            return;
        }

        $users = User::whereIn('role', $this->roles)->get();

        foreach ($users as $user) {
            Mail::raw(strip_tags($this->content), function ($message) use ($user) {
                $message->to($user->email)
                        ->subject($this->mailTitle);
            });
        }

        Notification::make()
            ->title(__('resources.pages.send_email.notifications.success_title'))
            ->body(trans('resources.pages.send_email.notifications.success_body', ['count' => $users->count()]))
            ->success()
            ->send();
    }

    public function saveTemplate()
    {
        if (empty($this->templateName)) {
            Notification::make()
                ->title(__('resources.pages.send_email.notifications.error_title'))
                ->body(__('resources.pages.send_email.notifications.error_template_name'))
                ->danger()
                ->send();
            return;
        }

        $filename = 'email-templates/' . strtolower(str_replace(' ', '_', $this->templateName)) . '.json';

        $templateData = [
            'title' => $this->mailTitle,
            'content' => $this->content,
        ];

        Storage::put($filename, json_encode($templateData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        Notification::make()
            ->title(__('resources.pages.send_email.notifications.template_saved_title'))
            ->body(trans('resources.pages.send_email.notifications.template_saved_body', ['name' => $this->templateName]))
            ->success()
            ->send();

        $this->templateName = null;
        $this->templateOptions = $this->getTemplateOptions();
        $this->updateButtonStates();
    }

    public function deleteTemplate()
    {
        if ($this->template && Storage::exists("email-templates/{$this->template}.json")) {
            Storage::delete("email-templates/{$this->template}.json");

            Notification::make()
                ->title(__('resources.pages.send_email.notifications.template_deleted_title'))
                ->body(trans('resources.pages.send_email.notifications.template_deleted_body', ['name' => $this->template]))
                ->success()
                ->send();

            $this->template = null;
            $this->templateOptions = $this->getTemplateOptions();
            $this->updateButtonStates();
        }
    }

    protected function getTemplateOptions(): array
    {
        $files = Storage::files('email-templates');

        return collect($files)->mapWithKeys(function ($file) {
            $filename = basename($file, '.json');
            return [$filename => ucfirst(str_replace('_', ' ', $filename))];
        })->toArray();
    }

    protected function getRolesOptions(): array
    {
        return collect(Roles::cases())
            ->mapWithKeys(fn ($role) => [$role->value => ucfirst(str_replace('_', ' ', $role->name))])
            ->toArray();
    }
}
