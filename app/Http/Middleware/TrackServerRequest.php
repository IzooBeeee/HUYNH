<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class TrackServerRequest
{
    public function handle(Request $request, Closure $next): Response
    {
        $port     = (int) ($request->server('SERVER_PORT') ?? 8001);
        $serverId = match ($port) {
            8001 => 'Server-1',
            8002 => 'Server-2',
            8003 => 'Server-3',
            default => 'Server-' . $port,
        };

        $response = $next($request);

        // Ghi heartbeat vào Redis — dùng để check status thay vì HTTP ping
        try {
            Cache::put("heartbeat_{$serverId}", now()->timestamp, 30);
        } catch (\Exception) {
            // Redis down — không crash app
        }

        $response->headers->set('X-Served-By', $serverId);
        $response->headers->set('X-Server-Port', (string) $port);

        return $response;
    }
}
