<?php

namespace App\Http\Middleware;

use App\Models\PlatformAdminGrant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePlatformAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $grant = $request->user()
            && PlatformAdminGrant::where('user_id', $request->user()->id)->exists();

        abort_if(! $grant, 403, 'هذه المنطقة مخصصة لمشرفي OnTrack فقط.');

        return $next($request);
    }
}