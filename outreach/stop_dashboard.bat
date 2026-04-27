@echo off
pushd %TEMP%
title Stop ZeroGrav Dashboard
echo Stopping all Streamlit processes in WSL...
wsl bash -c "pkill -f 'streamlit run' && echo 'Stopped.' || echo 'No dashboard was running.'"
echo.
pause
popd
