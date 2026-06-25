<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class DashboardController extends Controller
{
    public function index()
    {
        return view('dashboard');
    }

    public function stats(): JsonResponse
    {
        $servers = [
            ['id' => 'Server-1', 'port' => 8001],
            ['id' => 'Server-2', 'port' => 8002],
            ['id' => 'Server-3', 'port' => 8003],
        ];

        $data = [];
        foreach ($servers as $server) {
            $count  = (int) Cache::get("request_count_{$server['id']}", 0);
            $status = $this->checkServerStatus($server['port']);
            $data[] = [
                'id'            => $server['id'],
                'port'          => $server['port'],
                'request_count' => $count,
                'status'        => $status,
            ];
        }

        return response()->json([
            'servers'        => $data,
            'total_requests' => (int) Cache::get('request_count_total', 0),
            'timestamp'      => now()->toIso8601String(),
        ]);
    }

    public function resetCounters(): JsonResponse
    {
        Cache::forget('request_count_Server-1');
        Cache::forget('request_count_Server-2');
        Cache::forget('request_count_Server-3');
        Cache::forget('request_count_total');

        return response()->json(['message' => 'Counters reset successfully']);
    }

    private function checkServerStatus(int $port): string
    {
        $serverId = match ($port) {
            8001 => 'Server-1',
            8002 => 'Server-2',
            8003 => 'Server-3',
            default => 'Server-' . $port,
        };

        $lastSeen = Cache::get("heartbeat_{$serverId}");
        if ($lastSeen && (now()->timestamp - $lastSeen) <= 25) {
            return 'online';
        }

        return 'offline';
    }
}
