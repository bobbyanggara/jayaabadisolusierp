<?php

use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Gate;

/*
| Only accounts allowed to manage JASPEST may subscribe to this channel.
| Do not change this to a public channel: booking data is private.
*/
Broadcast::channel('jaspest.admin', function ($user): bool {
    return Gate::forUser($user)->allows('manage-jaspest');
});
