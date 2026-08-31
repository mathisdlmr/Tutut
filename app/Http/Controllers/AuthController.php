<?php
namespace App\Http\Controllers;
use App\Enums\Roles;
use App\Models\User;
use App\Providers\RouteServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use League\OAuth2\Client\Provider\GenericProvider;
class AuthController extends Controller
{
    public GenericProvider $provider;
    public function __construct()
    {
        Log::info('OIDC: construction du provider', [
            'authorize_url' => config('auth.oidc.authorize_url'),
            'access_token_url' => config('auth.oidc.access_token_url'),
            'owner_details_url' => config('auth.oidc.owner_details_url'),
            'redirect_uri' => config('auth.oidc.redirect_uri'),
        ]);
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
        Log::info('OIDC: provider construit avec timeout Guzzle (10s / connect 5s)');
    }
    public function login(Request $request)
    {
        Log::info('OIDC: entrée dans login()');
        if (config('auth.app_no_login', false)) {
            Log::info('OIDC: app_no_login actif, tentative auto-login');
            try {
                $userID = config('auth.auto_user_id');
                $user = User::find($userID);
                if ($user) {
                    Log::info('OIDC: auto-login réussi', ['user_id' => $userID]);
                    Auth::login($user);
                    return redirect(RouteServiceProvider::HOME);
                }
                Log::warning('OIDC: auto_user_id introuvable', ['user_id' => $userID]);
            } catch (\Exception $e) {
                Log::error('OIDC: erreur auto-login', ['message' => $e->getMessage()]);
                return response()->json(['message' => 'Login error :' . $e->getMessage()], 400);
            }
        }
        $state = bin2hex(random_bytes(16));
        $request->session()->put('oidc_state', $state);
        Log::info('OIDC: state généré et stocké en session', ['state' => $state]);
        $authorizationUrl = $this->provider->getAuthorizationUrl([
            'state' => $state,
        ]);
        Log::info('OIDC: authorizationUrl générée', ['url' => $authorizationUrl]);
        // PKCE code is like a random S256 session/state used to avoid interception
        $request->session()->put('oidc_pkce_code', $this->provider->getPkceCode());
        Log::info('OIDC: pkce_code stocké en session');
        return redirect($authorizationUrl);
    }
    public function callback(Request $request)
    {
        Log::info('OIDC: entrée dans callback()', [
            'has_code' => $request->has('code'),
            'has_state' => $request->has('state'),
        ]);
        $storedState = $request->session()->pull('oidc_state');
        if (!$request->has('state') || $request->get('state') !== $storedState) {
            Log::error('OIDC: state invalide', [
                'received' => $request->get('state'),
                'stored' => $storedState,
            ]);
            abort(400, 'Invalid state: ' . $request->get('state') . ' VS ' . $storedState);
        }
        Log::info('OIDC: state validé avec succès');
        if (!$request->has('code')) {
            Log::error('OIDC: code absent de la requête');
            abort(400, 'No authorization code');
        }
        $pkceCode = $request->session()->pull('oidc_pkce_code');
        Log::info('OIDC: pkce_code récupéré de la session', ['present' => !empty($pkceCode)]);
        $this->provider->setPkceCode($pkceCode);
        try {
            Log::info('OIDC: avant appel getAccessToken (POST vers access_token_url)');
            $accessToken = $this->provider->getAccessToken('authorization_code', [
                'code' => $request->get('code'),
            ]);
            Log::info('OIDC: access token reçu avec succès', [
                'expires' => $accessToken->getExpires(),
                'has_refresh_token' => !empty($accessToken->getRefreshToken()),
            ]);

            Log::info('OIDC: avant appel getResourceOwner');
            $resourceOwner = $this->provider->getResourceOwner($accessToken);
            $claims = $resourceOwner->toArray();
            Log::info('OIDC: resource owner reçu', ['claims_keys' => array_keys($claims)]);

            if (empty($claims['email'])) {
                Log::error('OIDC: email absent des claims', ['claims_keys' => array_keys($claims)]);
                abort(401, 'Email absent des informations renvoyées par le CAS');
            }

            Log::info('OIDC: avant firstOrCreate user', ['email' => $claims['email']]);
            $user = User::firstOrCreate(
                ['email' => $claims['email']],
                [
                    'firstName' => $claims['given_name'] ?? null,
                    'lastName' => $claims['family_name'] ?? null,
                    'role' => Roles::Tutee->value,
                ]
            );
            Log::info('OIDC: user résolu', ['user_id' => $user->id, 'wasRecentlyCreated' => $user->wasRecentlyCreated]);

            if (!$user->firstName && !empty($claims['given_name'])) {
                $user->firstName = $claims['given_name'];
                $user->lastName = $claims['family_name'] ?? null;
                $user->save();
                Log::info('OIDC: firstName/lastName mis à jour', ['user_id' => $user->id]);
            }

            Auth::login($user);
            Log::info('OIDC: Auth::login effectué', ['user_id' => $user->id]);
            $request->session()->regenerate();
            Log::info('OIDC: session régénérée, redirection vers HOME');
            return redirect(RouteServiceProvider::HOME);
        } catch (\Exception $e) {
            Log::error('OIDC: exception dans callback()', [
                'message' => $e->getMessage(),
                'class' => get_class($e),
                'trace' => $e->getTraceAsString(),
            ]);
            abort(400, 'Callback error : ' . $e->getMessage());
        }
    }
    public function logout()
    {
        Log::info('OIDC: logout appelé');
        Auth::logout();
        return redirect(config('auth.oidc.logout_url'));
    }
}