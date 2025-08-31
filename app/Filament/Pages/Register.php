<?php

namespace App\Filament\Pages;

use App\Notifications\CreateAccountNotification;
use App\Providers\RouteServiceProvider;
use DanHarrin\LivewireRateLimiting\Exceptions\TooManyRequestsException;
use DanHarrin\LivewireRateLimiting\WithRateLimiting;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Facades\Filament;
use Filament\Forms\Components\Component;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Http\Responses\Auth\Contracts\RegistrationResponse;
use Filament\Notifications\Notification;
use Filament\Pages\Concerns\InteractsWithFormActions;
use Filament\Pages\SimplePage;
use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\SessionGuard;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Firebase\JWT\JWT;

/**
 * @property Form $form
 */
class Register extends SimplePage
{
    use InteractsWithFormActions;
    use WithRateLimiting;

    /**
     * @var view-string
     */
    protected static string $view = 'filament-panels::pages.auth.register';

    /**
     * @var array<string, mixed> | null
     */
    public ?array $data = [];

    protected string $userModel;

    public function mount(): void
    {
        if (Filament::auth()->check()) {
            redirect()->intended(Filament::getUrl());
        }

        $this->form->fill();
    }

    public function register(): void
    {
        try {
            $this->rateLimit(5);     // 5 tentatives avant de se prendre un TO de 1 minute 
        } catch (TooManyRequestsException $exception) {
            Notification::make()
                ->title(__('filament-panels::pages/auth/register.notifications.throttled.title', [
                    'seconds' => $exception->secondsUntilAvailable,
                    'minutes' => ceil($exception->secondsUntilAvailable / 60),
                ]))
                ->body(array_key_exists('body', __('filament-panels::pages/auth/register.notifications.throttled') ?: []) ? __('filament-panels::pages/auth/register.notifications.throttled.body', [
                    'seconds' => $exception->secondsUntilAvailable,
                    'minutes' => ceil($exception->secondsUntilAvailable / 60),
                ]) : null)
                ->danger()
                ->send();

            return;
        }

        $data = $this->form->getState();

        $userData = collect($data)->only([
            'firstName',
            'lastName',
            'email',
            'password',
        ])->toArray();

        $userData['exp'] = time() + 3600;

        app()->bind(
            \Illuminate\Auth\Listeners\SendEmailVerificationNotification::class,
            \Filament\Listeners\Auth\SendEmailVerificationNotification::class,
        );
        //event(new Registered($user));

        $privateKey = config("services.crypt.private");

        $token = JWT::encode($userData, $privateKey, 'RS256');
        $email = $userData['email'];
        $link = config('app.url').'/registration?token='.urlencode($token);

        \Illuminate\Support\Facades\Notification::route('mail', $email)
            ->notify(new CreateAccountNotification($token, $link, $userData['firstName'].' '.$userData['lastName']));

        Notification::make()
            ->title("Un email de confirmation vous a été envoyé.")
            ->body("Votre compte sera créé une fois que vous aurez validé votre email.")
            ->success()
            ->send();

        $this->redirect(route('filament.tutut.auth.login'), navigate: true);

        return;
    }

    public function form(Form $form): Form
    {
        return $form;
    }

    /**
     * @return array<int | string, string | Form>
     */
    protected function getForms(): array
    {
        return [
            'form' => $this->form(
                $this->makeForm()
                    ->schema([
                        $this->getFirstNameFormComponent(),
                        $this->getLastNameFormComponent(),
                        $this->getEmailFormComponent(),
                        $this->getPasswordFormComponent(),
                        $this->getPasswordConfirmationFormComponent(),
                    ])
                    ->statePath('data'),
            ),
        ];
    }

    protected function getFirstNameFormComponent(): Component
    {
        return TextInput::make('firstName')
            ->label('Prénom')
            ->required()
            ->maxLength(255)
            ->autofocus();
    }

    protected function getLastNameFormComponent(): Component
    {
        return TextInput::make('lastName')
            ->label('Nom')
            ->required()
            ->maxLength(255);
    }

    protected function getEmailFormComponent(): Component
    {
        return TextInput::make('email')
            ->label('Email')
            ->email()
            ->required()
            ->maxLength(255)
            ->unique($this->getUserModel())
            ->rule('regex:/^[^@]+@.*utc.*$/i')
            ->validationMessages([
                'regex' => 'Seules les adresses email UTC (@etu.utc.fr, @hds.utc.fr...) sont acceptées.',
            ])
            ->validationAttribute('Cette adresse email est déjà utilisée.');
    }

    protected function getPasswordFormComponent(): Component
    {
        return TextInput::make('password')
            ->label('Mot de passe')
            ->password()
            ->required()
            ->rule(Password::default())
            ->dehydrateStateUsing(fn ($state) => Hash::make($state))
            ->same('passwordConfirmation')
            ->validationMessages([
                'min' => 'Le mot de passe doit contenir au moins :min caractères.',
                'same' => 'Les deux mots de passe doivent être identiques.',
            ]);
        }

    protected function getPasswordConfirmationFormComponent(): Component
    {
        return TextInput::make('passwordConfirmation')
            ->label(__('filament-panels::pages/auth/register.form.password_confirmation.label'))
            ->password()
            ->required()
            ->dehydrated(false)
            ->validationMessages([
                'min' => 'Le mot de passe doit contenir au moins :min caractères.',
                'same' => 'Les deux mots de passe doivent être identiques.',
            ]);
    }

    public function loginAction(): Action
    {
        return Action::make('login')
            ->link()
            ->label(__('filament-panels::pages/auth/register.actions.login.label'))
            ->url(filament()->getLoginUrl());
    }

    protected function getUserModel(): string
    {
        if (isset($this->userModel)) {
            return $this->userModel;
        }

        /** @var SessionGuard $authGuard */
        $authGuard = Filament::auth();

        /** @var EloquentUserProvider $provider */
        $provider = $authGuard->getProvider();

        return $this->userModel = $provider->getModel();
    }

    public function getHeading(): string | Htmlable
    {
        return "Créer un compte";
    }

    /**
     * @return array<Action | ActionGroup>
     */
    protected function getFormActions(): array
    {
        return [
            $this->getRegisterFormAction(),
        ];
    }

    public function getRegisterFormAction(): Action
    {
        return Action::make('register')
            ->label(__('filament-panels::pages/auth/register.form.actions.register.label'))
            ->submit('register');
    }

    protected function hasFullWidthFormActions(): bool
    {
        return true;
    }
}