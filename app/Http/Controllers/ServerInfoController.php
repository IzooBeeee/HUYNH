<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Session;

class ServerInfoController extends Controller
{
    public function info(): JsonResponse
    {
        $port     = (int) ($_SERVER['SERVER_PORT'] ?? request()->getPort() ?? 8001);
        $serverId = $this->resolveServerId($port);

        // Increment per-server request counter stored in Redis (shared)
        $counterKey = "request_count_{$serverId}";
        $count      = Cache::increment($counterKey);

        // Increment total request counter
        Cache::increment('request_count_total');

        return response()->json([
            'server_id'     => $serverId,
            'port'          => $port,
            'request_count' => $count,
            'session_id'    => Session::getId(),
            'php_version'   => PHP_VERSION,
            'timestamp'     => now()->toIso8601String(),
        ])->withHeaders([
            'X-Served-By'   => $serverId,
            'X-Server-Port' => $port,
        ]);
    }

    private function resolveServerId(int $port): string
    {
        return match ($port) {
            8001 => 'Server-1',
            8002 => 'Server-2',
            8003 => 'Server-3',
            default => 'Server-' . $port,
        };
    }
}
