<?php

namespace Azuriom\Plugin\DiscordAuth\Controllers;

use Azuriom\Http\Controllers\Controller;
use Azuriom\Models\User;
use Azuriom\Rules\GameAuth;
use Azuriom\Rules\Username;
use GuzzleHttp\Client;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Socialite\Facades\Socialite;

class DiscordAuthHomeController extends Controller
{

    private $guild;

    public function __construct() {
        config(["services.discord.client_id" => ""]);
        config(["services.discord.client_secret" => ""]);
        config(["services.discord.redirect_id" => "/discord-auth/callback"]);
        $this->guild = 'tt';
    }

    public function username() {
        return view('discord-auth::username', ['conditions' => setting('conditions')]);
    }

    public function registerUsername(Request $request) {

        $request->validate([
            'name' => ['required', 'string', 'max:25', 'unique:users', new Username(), new GameAuth()]
        ]);

        $user = Auth::user();
        $user->name = $request->input('name');
        $user->save();

        return redirect()->route('home');
    }

    /**
     * Redirect the user to the Discord authentication page.
     *
     * @return \Illuminate\Http\Response
     */
    public function redirectToProvider()
    {
        return Socialite::driver('discord')
            ->scopes('guilds')->redirect();
    }

    /**
     * Obtain the user information from Discord.
     *
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     * @throws ValidationException
     */
    public function handleProviderCallback(Request $request)
    {
        $user = Socialite::driver('discord')->user();

        $client = new Client();
        $response = $client->send(new \GuzzleHttp\Psr7\Request('GET', 'https://discord.com/api/users/@me/guilds', ['Authorization' => 'Bearer ' . $user->token]));
        $guilds = json_decode((string) $response->getBody(), true);

        if ($this->guild != '') {
            $found = false;
            foreach ($guilds as $guild) {
                if ($guild['id'] == $this->guild) {
                    $found = true;
                    break;
                } 
            }
        }

        /** @var Collection $users */
        $users = User::where('game_id', 'discord' . $user->user['id'])->get();

        if ($users->isEmpty()) {
            $user = User::forceCreate([
                'name' => $user->user['id'],
                'email' => $user->user['email'],
                'password' => Hash::make(Str::random(32)),
                'game_id' => 'discord' . $user->user['id'],
                'last_login_ip' => $request->ip(),
                'last_login_at' => now(),
            ]);
        } else {
            $user = $users->first();
        }

        if ($user->isBanned()) {
            throw ValidationException::withMessages([
                'email' => trans('auth.suspended'),
            ])->redirectTo(URL::route('login'));
        }

        if (setting('maintenance-status', false) && ! $user->can('maintenance.access')) {
            return $this->sendMaintenanceResponse($request);
        }

        $this->guard()->login($user);

        if ($users->isEmpty()) {
            return redirect()->route('discord-auth.username');
        }

        return redirect()->route('home');
    }

    /**
     * Send the response after the user was authenticated.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
     */
    protected function sendLoginResponse(Request $request)
    {
        $request->session()->regenerate();

        $this->clearLoginAttempts($request);

        if ($response = $this->authenticated($request, $this->guard()->user())) {
            return $response;
        }

        return $request->wantsJson()
            ? new JsonResponse([], 204)
            : redirect()->intended($this->redirectPath());
    }

    /**
     * Get the maintenance response.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    protected function sendMaintenanceResponse(Request $request)
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => trans('auth.maintenance')], 503);
        }

        return redirect()->back()->with('error', trans('auth.maintenance'));
    }

    /**
     * Get the guard to be used during authentication.
     *
     * @return \Illuminate\Contracts\Auth\StatefulGuard
     */
    protected function guard()
    {
        return Auth::guard();
    }
}
