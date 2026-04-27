@echo off
pushd %TEMP%
title ZeroGrav Outreach Dashboard

echo.
echo ========================================
echo   ZeroGrav Vendor Outreach Dashboard
echo ========================================
echo.
echo [1/3] Checking WSL...
wsl --status >nul 2>&1
if errorlevel 1 goto NO_WSL

echo [2/3] Checking Python packages (first run installs streamlit, ~30s)...
wsl bash -c "cd /home/a0915/zerograv/outreach && python3 -c 'import streamlit, pandas, dotenv' >/dev/null 2>&1 || python3 -m pip install --user --break-system-packages -q -r requirements.txt"
if errorlevel 1 goto INSTALL_FAILED

echo [3/3] Starting Streamlit...
echo.
echo Browser will open at http://localhost:8501 in 5 seconds.
echo To stop the dashboard, press Ctrl+C here or close this window.
echo.

start "" /b cmd /c "timeout /t 5 /nobreak >nul && start http://localhost:8501"

wsl bash -c "cd /home/a0915/zerograv/outreach && python3 -m streamlit run src/app.py --server.headless true"

echo.
echo Dashboard stopped.
pause
popd
exit /b 0

:NO_WSL
echo ERROR: WSL not installed or not running.
echo Install Ubuntu from Microsoft Store, or run: wsl --install
pause
popd
exit /b 1

:INSTALL_FAILED
echo ERROR: Package install failed. Run manually in WSL:
echo   cd /home/a0915/zerograv/outreach
echo   python3 -m pip install --user --break-system-packages -r requirements.txt
pause
popd
exit /b 1
