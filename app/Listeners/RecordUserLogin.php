<?php

namespace App\Listeners;

use App\Models\User;
use App\Models\UserLoginLog;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Request;

class RecordUserLogin
{
    public function handle(Login $event): void
    {
        $user = $event->user;
        if (! $user instanceof User) {
            return;
        }

        $agent = Request::userAgent();

        UserLoginLog::query()->create([
            'user_id' => $user->id,
            'ip_address' => Request::ip(),
            'user_agent' => $agent ? mb_substr($agent, 0, 500) : null,
            'logged_in_at' => now(),
        ]);

        $staleIds = UserLoginLog::query()
            ->where('user_id', $user->id)
            ->orderByDesc('id')
            ->skip(50)
            ->pluck('id');

        if ($staleIds->isNotEmpty()) {
            UserLoginLog::query()->whereIn('id', $staleIds)->delete();
        }
    }
};
