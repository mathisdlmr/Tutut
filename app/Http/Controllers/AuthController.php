<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Providers\RouteServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use League\OAuth2\Client\Provider\GenericProvider;

class AuthController extends Controller
{
    public GenericProvider $provider;

    public function __construct()
    {
        $this->provider = new GenericProvider([
            'clientId' => config('auth.oauth.client_id'),
            'clientSecret' => config('auth.oauth.client_secret'),
            'redirectUri' => config('auth.oauth.redirect_uri'),
            'urlAuthorize' => config('auth.oauth.authorize_url'),
            'urlAccessToken' => config('auth.oauth.access_token_url'),
            'urlResourceOwnerDetails' => config('auth.oauth.owner_details_url'),
            'scopes' => config('auth.oauth.scopes'),
        ]);
    }

    public function login(Request $request)
    {
        if (config('auth.app_no_login', false)) {
            try {
                $userID = config('auth.auto_user_id');
                $user = User::find($userID);
                if ($user) {
                    Auth::login($user);
                    return redirect(RouteServiceProvider::HOME);
                }
            } catch (\Exception $e) {
                return response()->json(['message' => 'Login error :' . $e->getMessage()], 400);
            }
        }

        $state = bin2hex(random_bytes(16));
        $request->session()->put('oauth2state', $state);

        $authorizationUrl = $this->provider->getAuthorizationUrl([
            'state' => $state
        ]);

        return redirect($authorizationUrl);
    }

    public function callback(Request $request)
    {
        $storedState = $request->session()->pull('oauth2state');

        if (!$request->has('state') || $request->get('state') !== $storedState) {
            abort(400, 'Invalid state: ' . $request->get('state') . ' VS ' . $storedState);
        }

        if (!$request->has('code')) {
            abort(400, 'No authorization code');
        }
        try {
            $accessToken = $this->provider->getAccessToken('authorization_code', [
                'code' => $request->get('code'),
            ]);

            $resourceOwner = $this->provider->getResourceOwner($accessToken);
            $userDetails = $resourceOwner->toArray();

            if ($userDetails['deleted_at'] != null || $userDetails['active'] != 1) {
                abort(401, 'Compte supprimé ou désactivé');
            }

            $user = User::where('email', $userDetails['email'])->first();
            if (!$user->firstName) {
                $user->firstName = $userDetails['firstName'];
                $user->lastName = $userDetails['lastName'];
                $user->save();
            }

            Auth::login($user);
            $request->session()->regenerate();
            return redirect(RouteServiceProvider::HOME);
        } catch (\Exception $e) {
            abort(400, 'Callback error : ' . $e->getMessage());
        }
    }

    public function logout()
    {
        Auth::logout();
        return redirect(config('auth.oauth.logout_url'));
    }
}
