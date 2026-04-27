@echo off
pushd %TEMP%
title ZeroGrav Pre-Send Check

echo.
echo ========================================
echo   ZeroGrav Outreach Dry-Run Check
echo ========================================
echo.

wsl bash -c "cd /home/a0915/zerograv/outreach && python3 src/pre_send_check.py"
if errorlevel 1 goto FAILED

echo.
echo ----------------------------------------
echo.

:ASK
set "VID="
set /p VID="Preview vendor email? Enter ID (or press Enter to exit): "
if "%VID%"=="" goto END

echo.
wsl bash -c "cd /home/a0915/zerograv/outreach && python3 src/pre_send_check.py --preview %VID%"
echo.
echo ----------------------------------------
goto ASK

:FAILED
echo.
echo ERROR: pre_send_check failed.
echo Run run_dashboard.bat first to install dependencies.
echo.
pause
popd
exit /b 1

:END
echo.
pause
popd
