<?php

return [
    'nav' => [
        'title' => 'Discord auth',
        'settings' => 'Settings',
    ],

    'permission' => 'View and change discord-auth settings',

    'settings' => [
        'title' => 'discord-auth plugin settings',
        'discord-portal' => 'Save a discord app',

        'client_id' => 'Client ID',
        'client_secret' => 'Client Secret',
        'guild' => 'Guild ID (let empty if you want to allow user to login even if he is not present on your guild server',
    ],
];
