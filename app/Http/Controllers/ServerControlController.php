<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class ServerControlController extends Controller
{
    private const ALLOWED_PORTS = [8001, 8002, 8003];

    private function projectDir(): string
    {
        return base_path();
    }

    private function scriptsDir(): string
    {
        return base_path('scripts');
    }

    /**
     * Kill trực tiếp theo port bằng lsof — không cần shell script.
     * Trả về true nếu có process bị kill.
     */
    private function killPort(int $port): bool
    {
        $pids = trim(shell_exec("lsof -t -i:$port -sTCP:LISTEN 2>/dev/null") ?? '');
        if (empty($pids)) {
            return false;
        }

        foreach (explode("\n", $pids) as $pid) {
            $pid = (int) trim($pid);
            if ($pid > 0) {
                exec("kill $pid 2>/dev/null");
            }
        }

        // Dọn PID file và heartbeat cache
        $pidFile = base_path("storage/pids/laravel_{$port}.pid");
        if (file_exists($pidFile)) {
            @unlink($pidFile);
        }
        $this->clearHeartbeat($port);

        return true;
    }

    /**
     * Xóa heartbeat Redis của server → dashboard thấy Offline ngay lập tức.
     */
    private function clearHeartbeat(int $port): void
    {
        $serverId = match ($port) {
            8001 => 'Server-1',
            8002 => 'Server-2',
            8003 => 'Server-3',
            default => "Server-$port",
        };
        Cache::forget("heartbeat_{$serverId}");
    }

    /**
     * Dừng một port sau 0.8s (async) dùng temp script + nohup.
     * Dùng lsof để kill TẤT CẢ worker processes (PHP_CLI_SERVER_WORKERS=4
     * tạo ra 4 PIDs, stop.sh chỉ kill 1 main PID → workers vẫn sống).
     */
    private function killPortAsync(int $port): void
    {
        $pidFile    = base_path("storage/pids/laravel_{$port}.pid");
        $scriptFile = sys_get_temp_dir() . "/lb_stop_{$port}_" . time() . '.sh';
        $logFile    = sys_get_temp_dir() . "/lb_stop_{$port}.log";

        $scriptContent  = "#!/bin/bash\n";
        $scriptContent .= "sleep 0.8\n";
        $scriptContent .= "# Kill tất cả processes (kể cả 4 PHP workers) đang listen port $port\n";
        $scriptContent .= "PIDS=\$(lsof -t -i:$port -sTCP:LISTEN 2>/dev/null)\n";
        $scriptContent .= "[ -n \"\$PIDS\" ] && echo \"\$PIDS\" | xargs kill 2>/dev/null\n";
        $scriptContent .= "rm -f " . escapeshellarg($pidFile) . "\n";
        $scriptContent .= "rm -f " . escapeshellarg($scriptFile) . "\n";

        file_put_contents($scriptFile, $scriptContent);
        chmod($scriptFile, 0755);

        exec("nohup bash " . escapeshellarg($scriptFile) . " > " . escapeshellarg($logFile) . " 2>&1 &");
    }


    /**
     * Chạy lệnh đồng bộ — dùng cho start/restart (không có nguy cơ self-kill).
     */
    private function runSync(string $cmd): array
    {
        $output = [];
        $code   = 0;
        exec("$cmd 2>&1", $output, $code);
        $text = implode("\n", $output);
        $text = preg_replace('/\033\[[0-9;]*m/', '', $text);
        return [$text, $code];
    }

    /**
     * Xác định port của PHP server đang xử lý request này.
     */
    private function currentPort(): int
    {
        $port = (int) request()->server('SERVER_PORT');
        return in_array($port, self::ALLOWED_PORTS) ? $port : 8001;
    }

    /**
     * Đếm số server đang thực sự lắng nghe.
     */
    private function countRunning(): int
    {
        $count = 0;
        foreach (self::ALLOWED_PORTS as $p) {
            $pids = trim(shell_exec("lsof -t -i:$p -sTCP:LISTEN 2>/dev/null") ?? '');
            if (!empty($pids)) {
                $count++;
            }
        }
        return $count;
    }

    // ──────────────────────────────────────────────
    // Stop single instance
    // ──────────────────────────────────────────────
    public function stop(Request $request): JsonResponse
    {
        $port = (int) $request->input('port');

        if (! in_array($port, self::ALLOWED_PORTS)) {
            return response()->json(['success' => false, 'message' => 'Port không hợp lệ.'], 422);
        }

        // Bảo vệ: không dừng server cuối cùng
        if ($this->countRunning() <= 1) {
            return response()->json([
                'success' => false,
                'message' => "❌ Không thể dừng port $port — đây là server cuối cùng đang chạy!\nDashboard sẽ mất kết nối nếu tắt.",
            ]);
        }

        // Xóa heartbeat ngay — tránh stats trả về "online" sau khi kill
        $this->clearHeartbeat($port);

        if ($this->currentPort() === $port) {
            // Self-kill: PHP đang xử lý request này trên chính server cần kill
            // → phải async (delay 0.8s) để PHP kịp gửi response trước khi chết
            $this->killPortAsync($port);
            $msg = "🛑 Đang dừng port $port (async)...";
        } else {
            // Safe kill: request đang ở server khác → kill đồng bộ ngay lập tức
            // Server đã chết TRƯỚC KHI response về → fetchStats() chắc chắn thấy offline
            $this->killPort($port);
            $msg = "🛑 Đã dừng port $port.";
        }

        return response()->json(['success' => true, 'message' => $msg]);
    }


    // ──────────────────────────────────────────────
    // Start single instance (sync — an toàn)
    // ──────────────────────────────────────────────
    public function start(Request $request): JsonResponse
    {
        $port = (int) $request->input('port');

        if (! in_array($port, self::ALLOWED_PORTS)) {
            return response()->json(['success' => false, 'message' => 'Port không hợp lệ.'], 422);
        }

        $scripts = $this->scriptsDir();
        $project = $this->projectDir();

        $cmd = "cd " . escapeshellarg($project) . " && bash " . escapeshellarg("$scripts/restart_one.sh") . " $port";
        [$text] = $this->runSync($cmd);

        return response()->json(['success' => true, 'message' => $text]);
    }

    // ──────────────────────────────────────────────
    // Start all (sync — an toàn)
    // ──────────────────────────────────────────────
    public function startAll(): JsonResponse
    {
        $scripts = $this->scriptsDir();
        $project = $this->projectDir();

        $cmd = "cd " . escapeshellarg($project) . " && bash " . escapeshellarg("$scripts/start.sh");
        [$text] = $this->runSync($cmd);

        return response()->json(['success' => true, 'message' => $text]);
    }

    // ──────────────────────────────────────────────
    // Stop others — dừng tất cả NGOẠI TRỪ server đang xử lý request.
    // Đồng bộ + direct kill → không có quoting/process-group issues.
    // ──────────────────────────────────────────────
    public function stopOthers(): JsonResponse
    {
        $keepPort    = $this->currentPort();
        $portsToStop = array_values(array_filter(self::ALLOWED_PORTS, fn($p) => $p !== $keepPort));

        $results = [];
        foreach ($portsToStop as $port) {
            $killed = $this->killPort($port);
            $results[] = $killed ? "port $port ✓" : "port $port (không chạy)";
        }

        $summary = implode(', ', $results);

        return response()->json([
            'success'   => true,
            'kept_port' => $keepPort,
            'stopped'   => $portsToStop,
            'message'   => "🛑 Đã dừng: $summary\n✅ Giữ port $keepPort để Dashboard vẫn hoạt động.",
        ]);
    }
}
