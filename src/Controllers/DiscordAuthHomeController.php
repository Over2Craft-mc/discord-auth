<?php

namespace Azuriom\Plugin\DiscordAuth\Controllers;

use Azuriom\Http\Controllers\Controller;
use Azuriom\Plugin\DiscordAuth\Models\Discord;
use Azuriom\Plugin\DiscordAuth\Models\User;
use Azuriom\Rules\GameAuth;
use Azuriom\Rules\Username;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Socialite\Facades\Socialite;

class DiscordAuthHomeController extends Controller
{

    private mixed $guild;

    public function __construct() {
        config(["services.discord.client_id" => setting('discord-auth.client_id', '')]);
        config(["services.discord.client_secret" => setting('discord-auth.client_secret', '')]);
        config(["services.discord.redirect_id" => "/discord-auth/callback"]);
        $this->guild = setting('discord-auth.guild', '');
    }

    public function username(): Factory|View|Application
    {
        return view('discord-auth::username', ['conditions' => setting('conditions')]);
    }

    public function registerUsername(Request $request): RedirectResponse
    {

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
     * @return Response
     */
    public function redirectToProvider(): Response
    {
        return Socialite::driver('discord')
            ->scopes('guilds')->redirect();
    }

    private function hasRightGuild($guilds): bool
    {

        if ($this->guild == '') {
            return true;
        }

        $found = false;
        foreach ($guilds as $guild) {
            if ($guild['id'] == $this->guild) {
                return true;
            }
        }

        return false;
    }

    /**
     * Obtain the user information from Discord.
     *
     * @param Request $request
     * @return View|Factory|Response|JsonResponse|RedirectResponse|Application
     * @throws RequestException
     * @throws ValidationException
     */
    public function handleProviderCallback(Request $request): View|Factory|Response|JsonResponse|RedirectResponse|Application
    {

        $user = Socialite::driver('discord')->user();

        $guilds = Http::withToken($user->token)
            ->get('https://discord.com/api/users/@me/guilds')
            ->throw()
            ->json();

        if (!$this->hasRightGuild($guilds)) {
            return view('discord-auth::guild');
        }

        $discordId = $user->user['id'];
        $email = $user->user['email'];
        $created = false;

        $discords = Discord::with('user')->where('discord_id', $discordId)->orderByDesc('id')->get();

        if ($discords->isEmpty() || $discords->first()->user->is_deleted) { // Aucun compte discord n'existe

            if (Auth::guest() && User::where('email', $email)->exists()) {
                $redirect = redirect();
                $redirect->setIntendedUrl(route('discord-auth.login'));
                return $redirect
                    ->route('login')
                    ->with('error', trans('discord-auth::messages.email_already_exists'));
            } elseif (Auth::user()) {
                $userToLogin = Auth::user();
            } else {
                $userToLogin = User::forceCreate([
                    'name' => $discordId,
                    'email' => $email,
                    'password' => Hash::make(Str::random(32)),
                    'last_login_ip' => $request->ip(),
                    'last_login_at' => now(),
                ]);

                $created = true;
            }

            $discord = new Discord();
            $discord->discord_id = $discordId;
            $discord->user_id = $userToLogin->id;
            $discord->save();

        } else {
            $userToLogin = $discords->first()->user;
        }

        if ($userToLogin->isBanned()) {
            throw ValidationException::withMessages([
                'email' => trans('auth.suspended'),
            ])->redirectTo(URL::route('login'));
        }

        if (setting('maintenance-status', false) && ! $userToLogin->can('maintenance.access')) {
            return $this->sendMaintenanceResponse($request);
        }

        $this->guard()->login($userToLogin);

        if ($created) {
            return redirect()->route('discord-auth.username');
        }

        return redirect()->route('home');
    }

    /**
     * Send the response after the user was authenticated.
     *
     * @param Request $request
     * @return RedirectResponse|JsonResponse
     */
    protected function sendLoginResponse(Request $request): JsonResponse|RedirectResponse
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
     * @param Request $request
     * @return JsonResponse
     */
    protected function sendMaintenanceResponse(Request $request): JsonResponse
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => trans('auth.maintenance')], 503);
        }

        return redirect()->back()->with('error', trans('auth.maintenance'));
    }

    /**
     * Get the guard to be used during authentication.
     *
     * @return StatefulGuard
     */
    protected function guard()
    {
        return Auth::guard();
    }
}
