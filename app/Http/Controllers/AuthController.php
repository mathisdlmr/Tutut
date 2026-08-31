<?php

namespace App\Http\Controllers;

use App\Enums\Roles;
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
            'clientId' => config('auth.oidc.client_id'),
            'clientSecret' => config('auth.oidc.client_secret'),
            'redirectUri' => config('auth.oidc.redirect_uri'),
            'urlAuthorize' => config('auth.oidc.authorize_url'),
            'urlAccessToken' => config('auth.oidc.access_token_url'),
            'urlResourceOwnerDetails' => config('auth.oidc.owner_details_url'),
            'scopes' => config('auth.oidc.scopes'),
            'pkceMethod' => GenericProvider::PKCE_METHOD_S256,
        ], [
            'httpClient' => new \GuzzleHttp\Client([
                'timeout' => 10,
                'connect_timeout' => 5,
            ]),
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
        $request->session()->put('oidc_state', $state);

        $authorizationUrl = $this->provider->getAuthorizationUrl([
            'state' => $state,
        ]);

        // PKCE code is like a random S256 session/state used to avoid interception
        $request->session()->put('oidc_pkce_code', $this->provider->getPkceCode());

        return redirect($authorizationUrl);
    }

    public function callback(Request $request)
    {
        $storedState = $request->session()->pull('oidc_state');
        if (!$request->has('state') || $request->get('state') !== $storedState) {
            abort(400, 'Invalid state: ' . $request->get('state') . ' VS ' . $storedState);
        }

        if (!$request->has('code')) {
            abort(400, 'No authorization code');
        }

        $pkceCode = $request->session()->pull('oidc_pkce_code');
        $this->provider->setPkceCode($pkceCode);

        try {
            $accessToken = $this->provider->getAccessToken('authorization_code', [
                'code' => $request->get('code'),
            ]);

            $resourceOwner = $this->provider->getResourceOwner($accessToken);
            $claims = $resourceOwner->toArray();

            $attributes = $claims['attributes'] ?? [];
            $email = $claims['email'] ?? $attributes['email'] ?? $attributes['mail'] ?? null;

            if (empty($email)) {
                abort(401, 'Email absent des informations renvoyées par le CAS');
            }

            $givenName = $claims['given_name'] ?? $attributes['givenName'] ?? $attributes['given_name'] ?? null;
            $familyName = $claims['family_name'] ?? $attributes['sn'] ?? $attributes['family_name'] ?? null;

            $user = User::firstOrCreate(
                ['email' => $email],
                [
                    'firstName' => $givenName,
                    'lastName' => $familyName,
                    'role' => Roles::Tutee->value,
                ]
            );

            if (!$user->firstName && !empty($givenName)) {
                $user->firstName = $givenName;
                $user->lastName = $familyName;
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
        return redirect(config('auth.oidc.logout_url'));
    }
}