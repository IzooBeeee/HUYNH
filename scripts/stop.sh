#!/bin/bash
# ============================================================
# stop.sh — Dừng tất cả hoặc 1 instance Laravel cụ thể
# Dùng lệnh: bash scripts/stop.sh [port]
# Ví dụ:     bash scripts/stop.sh 8002   (tắt Server-2 để demo failover)
#            bash scripts/stop.sh         (tắt tất cả)
# ============================================================

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(dirname "$SCRIPT_DIR")"
PID_DIR="$PROJECT_DIR/storage/pids"

RED='\033[0;31m'; GREEN='\033[0;32m'; YELLOW='\033[1;33m'
BLUE='\033[0;34m'; NC='\033[0m'

kill_instance() {
    local port=$1
    local pid_file="$PID_DIR/laravel_${port}.pid"

    if [ -f "$pid_file" ]; then
        local pid
        pid=$(cat "$pid_file")
        if kill -0 "$pid" 2>/dev/null; then
            kill "$pid"
            echo -e "${RED}🛑 Đã dừng instance port $port (PID $pid)${NC}"
        else
            echo -e "${YELLOW}⚠️  Instance port $port không còn chạy${NC}"
        fi
        rm -f "$pid_file"
    else
        # Fallback: kill by port using lsof
        local pids
        pids=$(lsof -t -i ":$port" 2>/dev/null || true)
        if [ -n "$pids" ]; then
            echo "$pids" | xargs kill 2>/dev/null || true
            echo -e "${RED}🛑 Đã dừng instance port $port${NC}"
        else
            echo -e "${YELLOW}⚠️  Không tìm thấy instance port $port${NC}"
        fi
    fi
}

if [ -n "${1:-}" ]; then
    # Tắt 1 instance cụ thể (demo failover)
    port="$1"
    echo -e "${BLUE}Tắt instance port $port để demo failover...${NC}"
    kill_instance "$port"
    echo ""
    echo -e "${YELLOW}Nginx sẽ tự động chuyển traffic sang 2 instance còn lại.${NC}"
    echo -e "${YELLOW}Khởi động lại: bash scripts/restart_one.sh $port${NC}"
else
    # Tắt tất cả
    echo -e "${BLUE}Dừng tất cả instance Laravel...${NC}"
    echo ""
    for port in 8001 8002 8003; do
        kill_instance "$port"
    done
    echo ""
    echo -e "${GREEN}Đã dừng tất cả instance.${NC}"
fi
