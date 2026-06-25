<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Load Balancer Monitor — Laravel LB</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            background: #0f172a;
            color: #e2e8f0;
            min-height: 100vh;
        }

        header {
            background: linear-gradient(135deg, #1e3a8a, #1e40af);
            padding: 20px 32px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 1px solid #2563eb;
            box-shadow: 0 4px 20px rgba(0,0,0,0.4);
        }

        header h1 { font-size: 1.5rem; font-weight: 700; letter-spacing: -0.5px; }
        header span { font-size: 0.85rem; opacity: 0.7; }

        .badge {
            background: #22c55e;
            color: #fff;
            font-size: 0.7rem;
            padding: 2px 8px;
            border-radius: 99px;
            font-weight: 600;
            animation: pulse 2s infinite;
        }

        @keyframes pulse { 0%,100%{opacity:1} 50%{opacity:0.6} }

        .container { max-width: 1100px; margin: 0 auto; padding: 32px 20px; }

        /* Current server banner */
        #current-banner {
            background: linear-gradient(135deg, #1e293b, #0f172a);
            border: 1px solid #334155;
            border-radius: 12px;
            padding: 24px 32px;
            margin-bottom: 28px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
        }

        .banner-label { font-size: 0.85rem; color: #94a3b8; margin-bottom: 4px; }

        #current-server-id {
            font-size: 2.5rem;
            font-weight: 800;
            color: #60a5fa;
            letter-spacing: -1px;
        }

        #current-port { font-size: 1rem; color: #64748b; margin-top: 2px; }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            border-radius: 8px;
            font-size: 0.9rem;
            font-weight: 600;
            cursor: pointer;
            border: none;
            transition: all 0.15s;
        }

        .btn:disabled { opacity: 0.5; cursor: not-allowed; }
        .btn-primary { background: #2563eb; color: #fff; }
        .btn-primary:hover:not(:disabled) { background: #1d4ed8; }
        .btn-danger  { background: #dc2626; color: #fff; }
        .btn-danger:hover:not(:disabled)  { background: #b91c1c; }
        .btn-success { background: #16a34a; color: #fff; }
        .btn-success:hover:not(:disabled) { background: #15803d; }
        .btn-warning { background: #d97706; color: #fff; }
        .btn-warning:hover:not(:disabled) { background: #b45309; }
        .btn-ghost { background: #1e293b; color: #94a3b8; border: 1px solid #334155; }
        .btn-ghost:hover:not(:disabled) { background: #334155; color: #e2e8f0; }

        .btn-sm { padding: 6px 12px; font-size: 0.78rem; border-radius: 6px; }

        .btn-group { display: flex; gap: 10px; flex-wrap: wrap; align-items: center; }

        /* Global controls bar */
        .global-controls {
            background: #1e293b;
            border: 1px solid #334155;
            border-radius: 12px;
            padding: 16px 24px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
        }

        .global-controls-label {
            font-size: 0.85rem;
            color: #64748b;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Server cards grid */
        .servers-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 16px;
            margin-bottom: 28px;
        }

        @media(max-width:700px){ .servers-grid { grid-template-columns: 1fr; } }

        .server-card {
            background: #1e293b;
            border: 2px solid #334155;
            border-radius: 12px;
            padding: 20px;
            transition: all 0.3s;
            position: relative;
            overflow: hidden;
        }

        .server-card.active {
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37,99,235,0.2), 0 8px 24px rgba(0,0,0,0.3);
        }

        .server-card.offline {
            border-color: #7f1d1d;
            opacity: 0.75;
        }

        .server-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 3px;
            background: linear-gradient(90deg, #2563eb, #7c3aed);
            opacity: 0;
            transition: opacity 0.3s;
        }

        .server-card.active::before { opacity: 1; }

        .card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 16px;
        }

        .server-name { font-size: 1.1rem; font-weight: 700; }

        .status-dot {
            width: 10px; height: 10px;
            border-radius: 50%;
            background: #22c55e;
            box-shadow: 0 0 8px #22c55e;
        }

        .status-dot.offline {
            background: #ef4444;
            box-shadow: 0 0 8px #ef4444;
            animation: none;
        }

        .status-dot.online { animation: glow 1.5s infinite; }

        @keyframes glow {
            0%,100%{box-shadow: 0 0 6px #22c55e}
            50%{box-shadow: 0 0 14px #22c55e}
        }

        .stat-row { display: flex; justify-content: space-between; margin-bottom: 8px; }
        .stat-label { font-size: 0.8rem; color: #64748b; }
        .stat-value { font-size: 0.9rem; font-weight: 600; color: #e2e8f0; }

        .request-count {
            font-size: 2rem;
            font-weight: 800;
            color: #60a5fa;
            margin: 12px 0 4px;
        }

        .progress-bar {
            height: 6px;
            background: #0f172a;
            border-radius: 99px;
            overflow: hidden;
            margin-top: 10px;
        }

        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #2563eb, #7c3aed);
            border-radius: 99px;
            transition: width 0.5s ease;
            width: 0%;
        }

        /* Card control buttons */
        .card-controls {
            display: flex;
            gap: 8px;
            margin-top: 16px;
            padding-top: 14px;
            border-top: 1px solid #0f172a;
        }

        .card-controls .btn { flex: 1; justify-content: center; }

        /* Chart section */
        .section {
            background: #1e293b;
            border: 1px solid #334155;
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 24px;
        }

        .section-title {
            font-size: 0.9rem;
            font-weight: 700;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 20px;
        }

        /* Bar chart */
        .bar-chart {
            display: flex;
            align-items: flex-end;
            gap: 16px;
            height: 180px;
            padding-top: 20px;
        }

        .bar-item {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 6px;
            height: 100%;
            justify-content: flex-end;
        }

        .bar-value { font-size: 0.85rem; font-weight: 700; color: #60a5fa; }

        .bar {
            width: 100%;
            background: linear-gradient(to top, #1d4ed8, #7c3aed);
            border-radius: 6px 6px 0 0;
            transition: height 0.5s cubic-bezier(0.34,1.56,0.64,1);
            min-height: 4px;
            max-height: 140px;
        }

        .bar.offline-bar { background: #7f1d1d; }

        .bar-label { font-size: 0.78rem; color: #64748b; font-weight: 600; }

        /* Timeline chart (last 20 requests) */
        .timeline { display: flex; gap: 4px; flex-wrap: wrap; }

        .timeline-dot {
            width: 28px; height: 28px;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.6rem;
            font-weight: 700;
            transition: all 0.3s;
        }

        .dot-s1 { background: rgba(37,99,235,0.3); color: #60a5fa; border: 1px solid #2563eb; }
        .dot-s2 { background: rgba(124,58,237,0.3); color: #a78bfa; border: 1px solid #7c3aed; }
        .dot-s3 { background: rgba(5,150,105,0.3); color: #34d399; border: 1px solid #059669; }

        /* Log panel */
        #log-panel {
            font-family: 'Courier New', monospace;
            font-size: 0.78rem;
            max-height: 160px;
            overflow-y: auto;
            color: #94a3b8;
        }

        .log-line { padding: 3px 0; border-bottom: 1px solid #0f172a; }
        .log-line .ts { color: #475569; }
        .log-line .server { font-weight: 700; }
        .log-s1 .server { color: #60a5fa; }
        .log-s2 .server { color: #a78bfa; }
        .log-s3 .server { color: #34d399; }
        .log-ctrl .server { color: #f59e0b; }

        #total-badge {
            font-size: 2rem;
            font-weight: 800;
            color: #f59e0b;
        }

        .auto-label {
            font-size: 0.78rem;
            color: #64748b;
            margin-left: 8px;
        }

        #auto-toggle {
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
        }

        .toggle-switch {
            width: 36px; height: 20px;
            background: #334155;
            border-radius: 99px;
            position: relative;
            transition: background 0.3s;
        }

        .toggle-switch.on { background: #2563eb; }

        .toggle-knob {
            width: 16px; height: 16px;
            background: #fff;
            border-radius: 50%;
            position: absolute;
            top: 2px; left: 2px;
            transition: left 0.3s;
        }

        .toggle-switch.on .toggle-knob { left: 18px; }

        /* ===== Modal ===== */
        .modal-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.7);
            backdrop-filter: blur(4px);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }

        .modal-overlay.open { display: flex; }

        .modal {
            background: #1e293b;
            border: 1px solid #334155;
            border-radius: 16px;
            padding: 28px 32px;
            width: 90%;
            max-width: 560px;
            box-shadow: 0 25px 60px rgba(0,0,0,0.6);
            animation: slideUp 0.2s ease;
        }

        @keyframes slideUp {
            from { opacity: 0; transform: translateY(20px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        .modal-title {
            font-size: 1.1rem;
            font-weight: 700;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .modal-output {
            background: #0f172a;
            border: 1px solid #1e293b;
            border-radius: 8px;
            padding: 16px;
            font-family: 'Courier New', monospace;
            font-size: 0.8rem;
            color: #94a3b8;
            white-space: pre-wrap;
            max-height: 260px;
            overflow-y: auto;
            line-height: 1.6;
            margin-bottom: 20px;
        }

        .modal-footer { display: flex; justify-content: space-between; align-items: center; }

        /* Countdown progress bar inside modal */
        .modal-progress-wrap {
            margin-bottom: 16px;
            display: none;
        }
        .modal-progress-wrap.active { display: block; }
        .modal-progress-label {
            font-size: 0.78rem;
            color: #64748b;
            margin-bottom: 6px;
            display: flex;
            justify-content: space-between;
        }
        .modal-progress-bar {
            height: 4px;
            background: #0f172a;
            border-radius: 99px;
            overflow: hidden;
        }
        .modal-progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #2563eb, #7c3aed);
            border-radius: 99px;
            width: 100%;
            transition: width 1s linear;
        }

        /* Spinner */
        .spinner {
            width: 16px; height: 16px;
            border: 2px solid rgba(255,255,255,0.3);
            border-top-color: #fff;
            border-radius: 50%;
            animation: spin 0.7s linear infinite;
            display: inline-block;
        }

        @keyframes spin { to { transform: rotate(360deg); } }
    </style>
</head>
<body>

<header>
    <div style="display:flex;align-items:center;gap:12px">
        <h1>⚖️ Load Balancer Monitor</h1>
        <span class="badge">LIVE</span>
    </div>
    <span>Laravel 11 + Nginx + Redis</span>
</header>

<div class="container">

    {{-- Current Request Banner --}}
    <div id="current-banner">
        <div>
            <div class="banner-label">Request hiện tại được xử lý bởi</div>
            <div id="current-server-id">—</div>
            <div id="current-port">port: —</div>
        </div>
        <div class="btn-group">
            <button class="btn btn-primary" onclick="fetchServerInfo()">↻ Refresh</button>
            <label id="auto-toggle">
                <div class="toggle-switch" id="auto-switch">
                    <div class="toggle-knob"></div>
                </div>
                <span class="auto-label">Auto refresh</span>
            </label>
            <button class="btn btn-danger" onclick="resetCounters()">Reset bộ đếm</button>
        </div>
    </div>

    {{-- Tổng request --}}
    <div style="display:flex;gap:16px;margin-bottom:20px">
        <div class="section" style="flex:1;padding:16px 24px">
            <div class="banner-label">Tổng requests đã xử lý</div>
            <div id="total-badge">0</div>
        </div>
        <div class="section" style="flex:2;padding:16px 24px">
            <div class="banner-label" style="margin-bottom:10px">Session ID (giữ nguyên dù đổi server)</div>
            <code id="session-id" style="font-size:0.8rem;color:#34d399;word-break:break-all">—</code>
        </div>
    </div>

    {{-- Global Controls --}}
    <div class="global-controls">
        <span class="global-controls-label">🎛️ Điều khiển hệ thống</span>
        <div class="btn-group">
            <button class="btn btn-success" id="btn-start-all" onclick="controlAll('start')">
                ▶ Khởi động tất cả
            </button>
            <button class="btn btn-danger" id="btn-stop-all" onclick="controlAll('stop')">
                ■ Dừng tất cả
            </button>
        </div>
    </div>

    {{-- Server cards --}}
    <div class="servers-grid" id="servers-grid">
        @foreach([['Server-1',8001],['Server-2',8002],['Server-3',8003]] as [$id, $port])
        <div class="server-card" id="card-{{ $id }}">
            <div class="card-header">
                <div class="server-name">{{ $id }}</div>
                <div class="status-dot" id="dot-{{ $id }}"></div>
            </div>
            <div class="stat-row">
                <span class="stat-label">Port</span>
                <span class="stat-value">{{ $port }}</span>
            </div>
            <div class="stat-row">
                <span class="stat-label">Trạng thái</span>
                <span class="stat-value" id="status-{{ $id }}">Đang kiểm tra...</span>
            </div>
            <div style="font-size:0.75rem;color:#64748b;margin-top:8px">Requests xử lý</div>
            <div class="request-count" id="count-{{ $id }}">0</div>
            <div class="progress-bar">
                <div class="progress-fill" id="bar-{{ $id }}"></div>
            </div>
            {{-- Control buttons --}}
            <div class="card-controls">
                <button class="btn btn-success btn-sm" id="btn-start-{{ $id }}"
                        onclick="controlServer('start', '{{ $id }}', {{ $port }})">
                    ▶ Start
                </button>
                <button class="btn btn-warning btn-sm" id="btn-stop-{{ $id }}"
                        onclick="controlServer('stop', '{{ $id }}', {{ $port }})">
                    ■ Stop
                </button>
            </div>
        </div>
        @endforeach
    </div>

    {{-- Bar chart --}}
    <div class="section">
        <div class="section-title">Phân phối requests theo server</div>
        <div class="bar-chart">
            @foreach(['Server-1','Server-2','Server-3'] as $srv)
            <div class="bar-item">
                <div class="bar-value" id="bv-{{ $srv }}">0</div>
                <div class="bar" id="b-{{ $srv }}" style="height:4px"></div>
                <div class="bar-label">{{ $srv }}</div>
            </div>
            @endforeach
        </div>
    </div>

    {{-- Timeline --}}
    <div class="section">
        <div class="section-title">Timeline requests gần nhất</div>
        <div class="timeline" id="timeline"></div>
    </div>

    {{-- Log --}}
    <div class="section">
        <div class="section-title" style="display:flex;justify-content:space-between">
            <span>Activity Log</span>
            <button class="btn btn-ghost" style="padding:4px 12px;font-size:0.75rem" onclick="clearLog()">Xóa log</button>
        </div>
        <div id="log-panel"></div>
    </div>

</div>

{{-- Modal Output --}}
<div class="modal-overlay" id="modal-overlay" onclick="closeModal(event)">
    <div class="modal">
        <div class="modal-title" id="modal-title">Output</div>
        <div class="modal-output" id="modal-output">...</div>
        <div class="modal-progress-wrap" id="modal-progress-wrap">
            <div class="modal-progress-label">
                <span id="modal-progress-text">Đang xử lý...</span>
                <span id="modal-countdown-num">3s</span>
            </div>
            <div class="modal-progress-bar">
                <div class="modal-progress-fill" id="modal-progress-fill"></div>
            </div>
        </div>
        <div class="modal-footer">
            <span id="modal-auto-close-hint" style="font-size:0.75rem;color:#475569"></span>
            <button class="btn btn-ghost" onclick="closeModal()">Đóng</button>
        </div>
    </div>
</div>

<script>
    const timeline = [];
    const maxTimeline = 30;
    let autoInterval = null;
    let autoOn = false;
    let currentServerId = null;

    const colorClass = {
        'Server-1': 'dot-s1 log-s1',
        'Server-2': 'dot-s2 log-s2',
        'Server-3': 'dot-s3 log-s3',
    };

    // =====================
    // Server Info & Stats
    // =====================
    async function fetchServerInfo() {
        try {
            const res = await fetch('/server-info', { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            const data = await res.json();

            document.getElementById('current-server-id').textContent = data.server_id;
            document.getElementById('current-port').textContent = 'port: ' + data.port;
            document.getElementById('session-id').textContent = data.session_id;
            currentServerId = data.server_id;

            ['Server-1','Server-2','Server-3'].forEach(id => {
                document.getElementById('card-' + id).classList.toggle('active', id === data.server_id);
            });

            timeline.push(data.server_id);
            if (timeline.length > maxTimeline) timeline.shift();
            renderTimeline();

            addLog(data.server_id, data.port, data.request_count, 'request');
            fetchStats();
        } catch (e) {
            addLog('ERROR', '-', 'Cannot reach server — check if instances are running', 'error');
        }
    }

    async function fetchStats() {
        try {
            const res = await fetch('/dashboard/stats');
            const data = await res.json();

            document.getElementById('total-badge').textContent = data.total_requests.toLocaleString();

            const maxCount = Math.max(...data.servers.map(s => s.request_count), 1);

            data.servers.forEach(server => {
                const { id, request_count, status } = server;
                const isOnline = status === 'online';

                document.getElementById('count-' + id).textContent = request_count.toLocaleString();
                document.getElementById('status-' + id).textContent = isOnline ? '🟢 Online' : '🔴 Offline';

                const card = document.getElementById('card-' + id);
                const dot  = document.getElementById('dot-' + id);

                card.classList.toggle('offline', !isOnline);
                dot.classList.toggle('online', isOnline);
                dot.classList.toggle('offline', !isOnline);

                // Progress bar
                const pct = maxCount > 0 ? (request_count / maxCount) * 100 : 0;
                document.getElementById('bar-' + id).style.width = pct + '%';

                // Bar chart
                const barH = maxCount > 0 ? Math.max(4, (request_count / maxCount) * 140) : 4;
                const barEl = document.getElementById('b-' + id);
                barEl.style.height = barH + 'px';
                barEl.classList.toggle('offline-bar', !isOnline);
                document.getElementById('bv-' + id).textContent = request_count;

                // Update start/stop button states
                updateCardButtons(id, isOnline);
            });
        } catch (e) {}
    }

    function updateCardButtons(serverId, isOnline) {
        const btnStart = document.getElementById('btn-start-' + serverId);
        const btnStop  = document.getElementById('btn-stop-' + serverId);
        if (!btnStart || !btnStop) return;
        btnStart.disabled = isOnline;
        btnStop.disabled  = !isOnline;
    }

    /** Optimistic update — đổi card sang Offline ngay khi click Stop */
    function setCardOffline(serverId) {
        document.getElementById('status-' + serverId).textContent = '🔴 Offline';
        const card = document.getElementById('card-' + serverId);
        const dot  = document.getElementById('dot-' + serverId);
        card.classList.add('offline');
        dot.classList.remove('online');
        dot.classList.add('offline');
        updateCardButtons(serverId, false);
    }

    // =====================
    // Server Control
    // =====================
    // Tắt/bật auto-refresh từ code (không qua click)
    function setAutoRefresh(on) {
        if (on === autoOn) return;
        autoOn = on;
        const sw = document.getElementById('auto-switch');
        sw.classList.toggle('on', autoOn);
        if (autoOn) {
            fetchServerInfo();
            autoInterval = setInterval(fetchServerInfo, 1500);
        } else {
            clearInterval(autoInterval);
        }
    }

    async function controlServer(action, serverId, port) {
        const btnStart = document.getElementById('btn-start-' + serverId);
        const btnStop  = document.getElementById('btn-stop-' + serverId);

        const isStop = action === 'stop';
        const label  = isStop ? '■ Stop' : '▶ Start';
        const activeBtn = isStop ? btnStop : btnStart;

        activeBtn.innerHTML = '<span class="spinner"></span> ' + (isStop ? 'Đang dừng...' : 'Đang khởi động...');
        activeBtn.disabled = true;

        const url = isStop ? '/server/stop' : '/server/start';

        try {
            const res = await fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
                body: JSON.stringify({ port }),
            });

            const data = await res.json();
            const emoji = data.success ? (isStop ? '🛑' : '✅') : '❌';

            if (!data.success) {
                showModal(`❌ ${isStop ? 'Dừng' : 'Khởi động'} ${serverId}`, data.message);
                activeBtn.innerHTML = label;
                return;
            }

            addLog(serverId, port, isStop ? 'đang dừng...' : 'khởi động thành công', 'ctrl');

            if (isStop) {
                // Optimistic update ngay lập tức
                setCardOffline(serverId);
                // fetchStats ngay — server đã chết (sync kill) hoặc sắp chết (async self-kill)
                fetchStats();

                showModalWithCountdown(
                    `🛑 Dừng ${serverId} (port ${port})`,
                    data.message,
                    1,  // 1s: sync kill = server đã chết; async = 0.8s delay là đủ
                    () => {
                        fetchStats();
                        addLog(serverId, port, 'đã dừng ✓', 'ctrl');
                    }
                );
            } else {
                showModalWithCountdown(
                    `✅ Khởi động ${serverId} (port ${port})`,
                    data.message,
                    2,
                    () => {
                        fetchStats();
                        addLog(serverId, port, 'đã online ✓', 'ctrl');
                    }
                );
            }

        } catch (e) {
            showModal('❌ Lỗi', 'Không thể kết nối — hãy kiểm tra server.');
        }

        activeBtn.innerHTML = label;
    }

    async function controlAll(action) {
        const btn = document.getElementById('btn-' + action + '-all');
        const origText = btn.innerHTML;
        btn.innerHTML = '<span class="spinner"></span> Đang xử lý...';
        btn.disabled = true;

        // stop → gọi stop-others: dừng 2 server, giữ server đang xử lý request này
        // start → gọi start-all: khởi động tất cả
        const url = action === 'start' ? '/server/start-all' : '/server/stop-others';

        try {
            const res = await fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
            });

            const data = await res.json();
            const countdownSec = action === 'stop' ? 3 : 2;

            if (action === 'start') {
                addLog('SYSTEM', '—', 'Khởi động tất cả server', 'ctrl');
                showModalWithCountdown('✅ Khởi động tất cả', data.message, countdownSec, () => {
                    fetchStats();
                });
            } else {
                const kept = data.kept_port || '?';
                const stopped = (data.stopped || []).join(', ');
                addLog('SYSTEM', '—', `Dừng tất cả — giữ port ${kept}, dừng [${stopped}]`, 'ctrl');
                showModalWithCountdown('🛑 Dừng tất cả',
                    `🛑 Đã dừng port ${stopped} — giữ port ${kept} cho Dashboard.`,
                    countdownSec, () => { fetchStats(); });
            }
        } catch (e) {
            showModal('❌ Lỗi', 'Không thể thực hiện lệnh.');
        }

        btn.innerHTML = origText;
        btn.disabled = false;
    }

    // =====================
    // Modal
    // =====================
    let _countdownTimer = null;

    function showModal(title, content) {
        _clearCountdown();
        document.getElementById('modal-title').innerHTML = title;
        document.getElementById('modal-output').textContent = content || '(Không có output)';
        document.getElementById('modal-progress-wrap').classList.remove('active');
        document.getElementById('modal-auto-close-hint').textContent = '';
        document.getElementById('modal-overlay').classList.add('open');
    }

    /**
     * Hiển thị modal kèm countdown bar chính xác.
     * Tự đóng modal + chạy onDone() khi hết giờ.
     */
    function showModalWithCountdown(title, content, seconds, onDone) {
        _clearCountdown();
        document.getElementById('modal-title').innerHTML = title;
        document.getElementById('modal-output').textContent = content || '';
        document.getElementById('modal-overlay').classList.add('open');

        const progressWrap = document.getElementById('modal-progress-wrap');
        const progressFill = document.getElementById('modal-progress-fill');
        const progressText = document.getElementById('modal-progress-text');
        const countdownNum = document.getElementById('modal-countdown-num');
        const hint        = document.getElementById('modal-auto-close-hint');

        progressWrap.classList.add('active');
        progressFill.style.transition = 'none';
        progressFill.style.width = '100%';
        progressText.textContent = 'Đang xử lý...';
        countdownNum.textContent = seconds + 's';
        hint.textContent = 'Tự đóng sau ' + seconds + 's';

        // Force reflow rồi mới animate
        progressFill.getBoundingClientRect();
        progressFill.style.transition = 'width ' + seconds + 's linear';
        progressFill.style.width = '0%';

        let remaining = seconds;
        _countdownTimer = setInterval(() => {
            remaining--;
            countdownNum.textContent = remaining + 's';
            hint.textContent = remaining > 0 ? 'Tự đóng sau ' + remaining + 's' : 'Đang cập nhật...';
            if (remaining <= 0) {
                _clearCountdown();
                document.getElementById('modal-overlay').classList.remove('open');
                if (typeof onDone === 'function') onDone();
            }
        }, 1000);
    }

    function _clearCountdown() {
        if (_countdownTimer) {
            clearInterval(_countdownTimer);
            _countdownTimer = null;
        }
    }

    function closeModal(e) {
        if (!e || e.target === document.getElementById('modal-overlay')) {
            _clearCountdown();
            document.getElementById('modal-overlay').classList.remove('open');
        }
    }

    // =====================
    // Timeline & Log
    // =====================
    function renderTimeline() {
        const el = document.getElementById('timeline');
        el.innerHTML = timeline.map(sid => {
            const cls = {
                'Server-1': 'dot-s1',
                'Server-2': 'dot-s2',
                'Server-3': 'dot-s3',
            }[sid] || '';
            const label = sid.replace('Server-', 'S');
            return `<div class="timeline-dot ${cls}" title="${sid}">${label}</div>`;
        }).join('');
    }

    function addLog(serverId, port, info, type = 'request') {
        const panel = document.getElementById('log-panel');
        const now = new Date().toLocaleTimeString('vi-VN');
        const logCls = type === 'ctrl' ? 'log-ctrl' : ({
            'Server-1': 'log-s1',
            'Server-2': 'log-s2',
            'Server-3': 'log-s3',
        }[serverId] || '');

        const line = document.createElement('div');
        line.className = `log-line ${logCls}`;

        if (type === 'request') {
            line.innerHTML = `<span class="ts">[${now}]</span> Request → <span class="server">${serverId}</span> (port ${port}) — request #${info}`;
        } else if (type === 'ctrl') {
            line.innerHTML = `<span class="ts">[${now}]</span> 🎛️ <span class="server">${serverId}</span> — ${info}`;
        } else {
            line.innerHTML = `<span class="ts">[${now}]</span> ❌ <span class="server">${serverId}</span> — ${info}`;
        }

        panel.insertBefore(line, panel.firstChild);
        while (panel.children.length > 50) panel.removeChild(panel.lastChild);
    }

    function clearLog() {
        document.getElementById('log-panel').innerHTML = '';
    }

    async function resetCounters() {
        if (!confirm('Reset tất cả bộ đếm về 0?')) return;
        await fetch('/dashboard/reset', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            },
        });
        timeline.length = 0;
        renderTimeline();
        clearLog();
        fetchStats();
    }

    // Auto-refresh toggle
    document.getElementById('auto-toggle').addEventListener('click', () => {
        autoOn = !autoOn;
        const sw = document.getElementById('auto-switch');
        sw.classList.toggle('on', autoOn);

        if (autoOn) {
            fetchServerInfo();
            autoInterval = setInterval(fetchServerInfo, 1500);
        } else {
            clearInterval(autoInterval);
        }
    });

    // Initial load
    fetchServerInfo();
    setInterval(fetchStats, 3000);
</script>
</body>
</html>
