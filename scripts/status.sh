#!/bin/bash
# ============================================================
# status.sh — Xem trạng thái toàn bộ hệ thống
# Dùng lệnh: bash scripts/status.sh
# ============================================================

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(dirname "$SCRIPT_DIR")"
PID_DIR="$PROJECT_DIR/storage/pids"

GREEN='\033[0;32m'; RED='\033[0;31m'; YELLOW='\033[1;33m'
BLUE='\033[0;34m'; CYAN='\033[0;36m'; NC='\033[0m'

echo -e "${BLUE}╔══════════════════════════════════════╗${NC}"
echo -e "${BLUE}║   Laravel LB — System Status         ║${NC}"
echo -e "${BLUE}╚══════════════════════════════════════╝${NC}"
echo ""

# Laravel instances
echo -e "${CYAN}Laravel Instances:${NC}"
for port in 8001 8002 8003; do
    pid_file="$PID_DIR/laravel_${port}.pid"
    pid=""
    [ -f "$pid_file" ] && pid=$(cat "$pid_file")

    http_status=$(curl -s -o /dev/null -w "%{http_code}" --max-time 2 "http://127.0.0.1:$port/health" 2>/dev/null || echo "000")

    if [ "$http_status" = "200" ]; then
        echo -e "  ${GREEN}✅ Port $port — ONLINE  (HTTP $http_status) ${pid:+PID $pid}${NC}"
    else
        echo -e "  ${RED}❌ Port $port — OFFLINE (HTTP $http_status)${NC}"
    fi
done

echo ""

# Redis
echo -e "${CYAN}Redis:${NC}"
if redis-cli ping 2>/dev/null | grep -q PONG; then
    echo -e "  ${GREEN}✅ Redis — ONLINE (127.0.0.1:6379)${NC}"
else
    echo -e "  ${RED}❌ Redis — OFFLINE${NC}"
fi

echo ""

# Nginx
echo -e "${CYAN}Nginx:${NC}"
if pgrep -f "nginx" &>/dev/null && curl -s -o /dev/null -m 1 http://localhost:8080/; then
    echo -e "  ${GREEN}✅ Nginx — RUNNING (port 8080)${NC}"
else
    echo -e "  ${YELLOW}⚠️  Nginx — NOT RUNNING${NC}"
    echo -e "     Khởi động: brew services start nginx"
fi

echo ""
echo -e "${CYAN}Demo URL:${NC}"
echo "  http://localhost:8080/demo  (qua Nginx LB)"
echo "  http://localhost:8001       (trực tiếp S1)"
echo "  http://localhost:8002       (trực tiếp S2)"
echo "  http://localhost:8003       (trực tiếp S3)"
