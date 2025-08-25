<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use Symfony\Component\HttpFoundation\Response;

class FilamentAutoAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        if (config('app.env') === 'local') {
            $userID = config('auth.auto_user_id');
            $user = User::find($userID);
            
            if ($user) {
                Auth::login($user);
                session()->put('login_web_' . sha1(static::class), $user->getAuthIdentifier());
                session()->save();
                return $next($request);
            }
            
            abort(403, 'Utilisateur auto-login introuvable');
        }
        if (config('auth.app_no_login')) {
            if (!Auth::check()) {
                return redirect()->route('filament.tutut.auth.login');
            }
        }
        return $next($request);
    }
}