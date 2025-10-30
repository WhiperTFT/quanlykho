@echo off
:: ===========================================
:: GIT-SAVE.BAT — TỰ ĐỘNG ADD + COMMIT + PUSH
:: ===========================================

:: Chuyển tới thư mục chứa file bat (đảm bảo chạy đúng repo)
cd /d "%~dp0"

:: Lấy ghi chú commit từ tham số lệnh (nếu có)
set msg=%*

:: Nếu chưa có, hỏi người dùng nhập vào
if "%msg%"=="" (
    echo ===========================================
    echo 💬 NHAP NOI DUNG COMMIT
    echo (vd: Sua loi sales_orders, cap nhat ngay 30/10/2025)
    echo -------------------------------------------
    set /p msg=Noi dung commit: 
    if "%msg%"=="" set msg=Auto commit %date% %time%
)

echo.
echo 💾 Dang them tat ca thay doi vao staging...
git add -A

echo.
echo 🧱 Tao commit: "%msg%"
git commit -m "%msg%"

echo.
echo ☁️ Dang day len GitHub (origin/main)...
git push origin main

echo.
echo ✅ Hoan tat! Da luu code len GitHub thanh cong.
echo -------------------------------------------
git log -1 --oneline

echo.
pause
