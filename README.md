# Azuriom Discord authentication

This Azuriom plugin allow users to authenticate thought Discord OAuth2 API.

## How does it works
This plugin use Discord OAuth2 to authenticate users. 
Discord ID is stored in the field `game_id` of the table `users`
when a user try to login for the first time via Discord. 
The user will be prompted to enter his name 
when login in for the first time,
If the user skip this step the discord ID will be used as the name. 

Note : Discord ID is stored in this format `discord-$discord-id`

The Discord ID will be then used to authenticate future login attempt

## Installations

### Add a login via discord button in your template 

Example : 
`ressources/themes/carbon/view/elements` near `<!-- Authentication Links -->`
```html
    @guest
        <li class="nav-item">
            <a class="btn btn-secondary mx-1 my-2" href="{{ route('discord-auth.login') }}">{{ trans('discord-auth::messages.login_via_discord') }}</a>
        </li>
    @endguest
```

You can also use the admin panel to add a navigation button, but it may be a bit less aesthetic 
`Admin panel -> Navigation` 

###  Register a discord app and fill credentials
* Register a Discord application here https://discord.com/developers/applications
* Fill `client_id` and `client_secret` in `plugins/discord-auth/src/Controllers/DiscordAuthHomeController.php`
