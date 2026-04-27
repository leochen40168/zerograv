@echo off
chcp 65001 >nul
title ZeroGrav Outreach Dashboard

echo.
echo ========================================
echo   ZeroGrav Vendor Outreach Dashboard
echo ========================================
echo.
echo [1/3] 檢查 WSL 是否就緒...
wsl --status >nul 2>&1
if errorlevel 1 (
  echo ERROR: WSL 沒裝或沒啟動。請先在 Microsoft Store 安裝 WSL/Ubuntu。
  pause
  exit /b 1
)

echo [2/3] 檢查 Python 套件 (第一次跑會花約 30 秒安裝 streamlit)...
wsl bash -c "cd ~/zerograv/outreach && python3 -c 'import streamlit, pandas, dotenv' >/dev/null 2>&1 || python3 -m pip install --user --break-system-packages -q -r requirements.txt"
if errorlevel 1 (
  echo ERROR: 套件安裝失敗。請手動跑：
  echo   wsl bash -c "cd ~/zerograv/outreach ^&^& python3 -m pip install --user --break-system-packages -r requirements.txt"
  pause
  exit /b 1
)

echo [3/3] 啟動 Streamlit...
echo.
echo Browser 將在約 5 秒後自動開啟：http://localhost:8501
echo (要停止 dashboard，請在這視窗按 Ctrl+C 或直接關閉)
echo.

REM 5 秒後在背景開瀏覽器
start "" /b cmd /c "timeout /t 5 /nobreak >nul && start http://localhost:8501"

REM 真正啟動 streamlit (會 block 在這直到你停掉)
wsl bash -c "cd ~/zerograv/outreach && python3 -m streamlit run src/app.py --server.headless true"

echo.
echo Dashboard 已停止。
pause
