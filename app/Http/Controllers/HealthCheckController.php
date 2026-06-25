<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class HealthCheckController extends Controller
{
    public function check(): JsonResponse
    {
        $port     = (int) ($_SERVER['SERVER_PORT'] ?? request()->getPort() ?? 8001);
        $serverId = $this->resolveServerId($port);

        $status = [
            'status'    => 'healthy',
            'server_id' => $serverId,
            'port'      => $port,
            'timestamp' => now()->toIso8601String(),
            'checks'    => [],
        ];

        // Check SQLite
        try {
            DB::select('SELECT 1');
            $status['checks']['database'] = 'sqlite ok';
        } catch (\Exception $e) {
            $status['checks']['database'] = 'error';
            $status['status'] = 'degraded';
        }

        // Check Redis
        try {
            $redis = new \Predis\Client([
                'scheme' => 'tcp',
                'host'   => config('database.redis.default.host', '127.0.0.1'),
                'port'   => config('database.redis.default.port', 6379),
            ]);
            $redis->ping();
            $status['checks']['redis'] = 'ok';
        } catch (\Exception $e) {
            $status['checks']['redis'] = 'error';
            $status['status'] = 'degraded';
        }

        return response()->json($status, $status['status'] === 'healthy' ? 200 : 503);
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
