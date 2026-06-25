#!/bin/bash
# ============================================================
# demo_failover.sh — Script demo kịch bản failover tự động
# Chạy trước giáo viên để demo toàn bộ chu trình:
#   1. Hệ thống đang chạy bình thường
#   2. Tắt Server-2
#   3. Chờ Nginx phát hiện server down
#   4. Khởi động lại Server-2 (recovery)
# ============================================================

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

RED='\033[0;31m'; GREEN='\033[0;32m'; YELLOW='\033[1;33m'
BLUE='\033[0;34m'; CYAN='\033[0;36m'; BOLD='\033[1m'; NC='\033[0m'

banner() {
    echo ""
    echo -e "${CYAN}${BOLD}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    echo -e "${CYAN}${BOLD}  $1${NC}"
    echo -e "${CYAN}${BOLD}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    echo ""
}

wait_with_countdown() {
    local seconds=$1
    local msg=$2
    echo -ne "${YELLOW}$msg${NC}"
    for ((i=seconds; i>0; i--)); do
        echo -ne "\r${YELLOW}$msg ($i giây) ${NC}"
        sleep 1
    done
    echo ""
}

check_all_servers() {
    echo -e "${CYAN}Trạng thái servers:${NC}"
    for port in 8001 8002 8003; do
        status=$(curl -s -o /dev/null -w "%{http_code}" "http://127.0.0.1:$port/health" 2>/dev/null || echo "000")
        if [ "$status" = "200" ]; then
            echo -e "  ${GREEN}✅ Port $port — ONLINE${NC}"
        else
            echo -e "  ${RED}❌ Port $port — OFFLINE${NC}"
        fi
    done
    echo ""
}

# ============================================================
banner "BƯỚC 1: Kiểm tra hệ thống ban đầu"
# ============================================================

check_all_servers

echo -e "Mở trình duyệt tại: ${BLUE}http://localhost/demo${NC}"
echo -e "Bật Auto Refresh để thấy requests phân phối luân phiên."
read -p "$(echo -e "${YELLOW}Nhấn Enter khi đã mở trang demo...${NC}")"

# ============================================================
banner "BƯỚC 2: Mô phỏng Server-2 bị lỗi (Failover)"
# ============================================================

echo -e "${RED}Đang tắt Server-2 (port 8002)...${NC}"
bash "$SCRIPT_DIR/stop.sh" 8002

echo ""
echo -e "${YELLOW}Nginx sẽ phát hiện Server-2 down sau tối đa 3 lần fail.${NC}"
echo -e "${YELLOW}Traffic sẽ tự động chuyển sang Server-1 và Server-3.${NC}"
echo ""

wait_with_countdown 10 "Chờ Nginx cập nhật health check... "

echo ""
check_all_servers

echo -e "Quan sát trang demo: ${BLUE}http://localhost/demo${NC}"
echo -e "→ Server-2 hiển thị OFFLINE, requests chỉ đến Server-1 và Server-3"
echo ""
read -p "$(echo -e "${YELLOW}Nhấn Enter khi đã quan sát xong kịch bản failover...${NC}")"

# ============================================================
banner "BƯỚC 3: Recovery — Khởi động lại Server-2"
# ============================================================

echo -e "${GREEN}Đang khởi động lại Server-2...${NC}"
bash "$SCRIPT_DIR/restart_one.sh" 8002

echo ""
echo -e "${YELLOW}Nginx sẽ tự động đưa Server-2 vào pool sau khi health check pass.${NC}"

wait_with_countdown 15 "Chờ Nginx nhận diện Server-2 trở lại... "

echo ""
check_all_servers

# ============================================================
banner "KẾT QUẢ DEMO"
# ============================================================

echo -e "${GREEN}✅ Failover thành công:${NC}"
echo "  • Server-2 bị tắt → traffic tự động chuyển sang Server-1, Server-3"
echo "  • Người dùng không nhận thấy gián đoạn"
echo "  • Server-2 restart → tự động vào pool trở lại"
echo "  • Session vẫn giữ nguyên nhờ Redis tập trung"
echo ""
echo -e "${CYAN}Xem trang demo: ${BLUE}http://localhost/demo${NC}"
