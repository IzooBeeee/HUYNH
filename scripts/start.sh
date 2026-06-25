#!/bin/bash
# ============================================================
# start.sh — Khởi động 3 instance Laravel + Queue Worker
# Dùng lệnh: bash scripts/start.sh
# ============================================================

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(dirname "$SCRIPT_DIR")"
PID_DIR="$PROJECT_DIR/storage/pids"
LOG_DIR="$PROJECT_DIR/storage/logs"

RED='\033[0;31m'; GREEN='\033[0;32m'; YELLOW='\033[1;33m'
BLUE='\033[0;34m'; CYAN='\033[0;36m'; NC='\033[0m'

mkdir -p "$PID_DIR" "$LOG_DIR"

print_header() {
    echo -e "${BLUE}╔══════════════════════════════════════╗${NC}"
    echo -e "${BLUE}║   Laravel Load Balancer — START      ║${NC}"
    echo -e "${BLUE}╚══════════════════════════════════════╝${NC}"
    echo ""
}

check_port() {
    local port=$1
    if lsof -Pi ":$port" -sTCP:LISTEN -t &>/dev/null; then
        echo -e "${YELLOW}⚠️  Port $port đang được dùng — bỏ qua${NC}"
        return 1
    fi
    return 0
}

start_instance() {
    local port=$1
    local server_id=$2
    local pid_file="$PID_DIR/laravel_${port}.pid"
    local log_file="$LOG_DIR/laravel_${port}.log"

    if check_port "$port"; then
        cd "$PROJECT_DIR"
        PHP_CLI_SERVER_WORKERS=4 php -S "127.0.0.1:$port" "$PROJECT_DIR/server-entry.php" \
            > "$log_file" 2>&1 &
        echo $! > "$pid_file"
        sleep 0.5

        # Verify started
        if kill -0 "$(cat "$pid_file")" 2>/dev/null; then
            echo -e "${GREEN}✅ $server_id (port $port) — PID $(cat "$pid_file")${NC}"
        else
            echo -e "${RED}❌ $server_id (port $port) — Khởi động thất bại, xem log: $log_file${NC}"
        fi
    fi
}

print_header

echo -e "${CYAN}Khởi động các instance Laravel...${NC}"
echo ""

start_instance 8001 "Server-1"
start_instance 8002 "Server-2"
start_instance 8003 "Server-3"

echo ""
echo -e "${CYAN}Chờ các instance ổn định...${NC}"
sleep 2

# Health check
echo ""
echo -e "${CYAN}Kiểm tra health check:${NC}"
for port in 8001 8002 8003; do
    status=$(curl -s -o /dev/null -w "%{http_code}" "http://127.0.0.1:$port/health" 2>/dev/null || echo "000")
    if [ "$status" = "200" ]; then
        echo -e "  ${GREEN}✅ http://127.0.0.1:$port/health → $status OK${NC}"
    else
        echo -e "  ${RED}❌ http://127.0.0.1:$port/health → $status${NC}"
    fi
done

echo ""
# Warm-up: gửi request đến từng server để kích hoạt heartbeat Redis
echo ""
echo -e "${CYAN}Warm-up heartbeat...${NC}"
for port in 8001 8002 8003; do
    curl -s "http://127.0.0.1:$port/server-info" > /dev/null 2>&1 && \
        echo -e "  ${GREEN}✅ Heartbeat port $port OK${NC}" || \
        echo -e "  ${YELLOW}⚠️  Heartbeat port $port bỏ qua${NC}"
done

echo ""
echo -e "${GREEN}══════════════════════════════════════${NC}"
echo -e "${GREEN}Hệ thống đã khởi động!${NC}"
echo -e "${GREEN}══════════════════════════════════════${NC}"
echo ""
echo "  Demo:        http://localhost:8080/demo (qua Nginx)"
echo "  Trực tiếp:   http://localhost:8001 | :8002 | :8003"
echo ""
echo -e "${YELLOW}Dừng hệ thống: bash scripts/stop.sh${NC}"
