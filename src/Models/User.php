<?php

namespace Azuriom\Plugin\DiscordAuth\Models;


class User extends \Azuriom\Models\User
{
    public function discord(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Discord::class);
    }
}
