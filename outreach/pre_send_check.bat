@echo off
chcp 65001 >nul
title ZeroGrav Pre-Send Check (Dry-Run)

echo.
echo ========================================
echo   ZeroGrav 寄信前 Dry-Run 檢查
echo ========================================
echo.

wsl bash -c "cd ~/zerograv/outreach && python3 src/pre_send_check.py"
if errorlevel 1 (
  echo.
  echo ERROR: 執行失敗。可能原因：
  echo   1. WSL 沒啟動
  echo   2. 套件還沒裝（先點 run_dashboard.bat 跑一次自動裝）
  echo.
  pause
  exit /b 1
)

echo.
echo ──────────────────────────────────────────
echo.
:ASK_PREVIEW
set "VID="
set /p VID="預覽某個 vendor 完整信件? 輸入 ID（或直接 Enter 結束）: "
if "%VID%"=="" goto END

echo.
wsl bash -c "cd ~/zerograv/outreach && python3 src/pre_send_check.py --preview %VID%"
echo.
echo ──────────────────────────────────────────
goto ASK_PREVIEW

:END
echo.
pause
