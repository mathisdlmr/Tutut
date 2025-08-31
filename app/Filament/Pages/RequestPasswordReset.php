<?php

namespace App\Filament\Pages;

use Filament\Pages\Auth\PasswordReset\RequestPasswordReset as BaseRequestPasswordReset;
use Illuminate\Support\Facades\Password;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Contracts\Auth\CanResetPassword;
use Exception;

class RequestPasswordReset extends BaseRequestPasswordReset
{
    public function request(): void
    {
        try {
            $this->rateLimit(2);
        } catch (\DanHarrin\LivewireRateLimiting\Exceptions\TooManyRequestsException $ex) {
            Notification::make()
                ->title(__('filament-panels::pages/auth/password-reset/request-password-reset.notifications.throttled.title', [
                    'seconds' => $ex->secondsUntilAvailable,
                    'minutes' => ceil($ex->secondsUntilAvailable / 60),
                ]))
                ->body(__('filament-panels::pages/auth/password-reset/request-password-reset.notifications.throttled.body', [
                    'seconds' => $ex->secondsUntilAvailable,
                    'minutes' => ceil($ex->secondsUntilAvailable / 60),
                ]))
                ->danger()
                ->send();

            return;
        }

        $data = $this->form->getState();

        $status = Password::broker(Filament::getAuthPasswordBroker())->sendResetLink(
            $data,
            function (CanResetPassword $user, string $token): void {
                if (! method_exists($user, 'notify')) {
                    throw new Exception("Model [" . get_class($user) . "] does not have notify()");
                }

                $token = app('auth.password.broker')->createToken($user);
                $notification = new ResetPasswordNotification($token);
                $notification->url = \Filament\Facades\Filament::getResetPasswordUrl($token, $user);
                $user->notify($notification);

                $this->redirect(route('filament.tutut.auth.login'), navigate: true);
            }
        );

        if ($status !== Password::RESET_LINK_SENT) {
            Notification::make()
                ->title(__($status))
                ->danger()
                ->send();

            return;
        }

        Notification::make()
            ->title(__($status))
            ->success()
            ->send();

        $this->form->fill();
    }
}
