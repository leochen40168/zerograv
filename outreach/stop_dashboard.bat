@echo off
chcp 65001 >nul
title Stop ZeroGrav Dashboard
echo 正在停止所有 Streamlit 程序...
wsl bash -c "pkill -f 'streamlit run' && echo 已停止 || echo 沒有正在執行的 dashboard"
echo.
pause
