#!/bin/bash
# ============================================================
# restart_one.sh — Khởi động lại 1 instance đã bị tắt
# Demo: recovery sau failover
# Dùng lệnh: bash scripts/restart_one.sh [port]
# Ví dụ:     bash scripts/restart_one.sh 8002
# ============================================================

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(dirname "$SCRIPT_DIR")"
PID_DIR="$PROJECT_DIR/storage/pids"
LOG_DIR="$PROJECT_DIR/storage/logs"

GREEN='\033[0;32m'; YELLOW='\033[1;33m'; RED='\033[0;31m'; BLUE='\033[0;34m'; NC='\033[0m'

PORT="${1:-8002}"

case "$PORT" in
    8001) SERVER_ID="Server-1" ;;
    8002) SERVER_ID="Server-2" ;;
    8003) SERVER_ID="Server-3" ;;
    *)    SERVER_ID="Server-$PORT" ;;
esac

echo -e "${BLUE}Khởi động lại $SERVER_ID (port $PORT)...${NC}"

# Kiểm tra port đã có process chưa
if lsof -Pi ":$PORT" -sTCP:LISTEN -t &>/dev/null; then
    echo -e "${YELLOW}⚠️  Port $PORT đang được sử dụng — không cần khởi động lại${NC}"
    exit 0
fi

cd "$PROJECT_DIR"
PHP_CLI_SERVER_WORKERS=4 php -S "127.0.0.1:$PORT" "$PROJECT_DIR/server-entry.php" \
    > "$LOG_DIR/laravel_${PORT}.log" 2>&1 &
echo $! > "$PID_DIR/laravel_${PORT}.pid"

sleep 1

# Verify
status=$(curl -s -o /dev/null -w "%{http_code}" "http://127.0.0.1:$PORT/health" 2>/dev/null || echo "000")
if [ "$status" = "200" ]; then
    echo -e "${GREEN}✅ $SERVER_ID đã khởi động lại thành công!${NC}"
    echo -e "${GREEN}   Nginx sẽ tự động đưa server này vào pool sau ~30 giây.${NC}"
else
    echo -e "${RED}❌ $SERVER_ID khởi động nhưng health check thất bại (HTTP $status)${NC}"
fi
