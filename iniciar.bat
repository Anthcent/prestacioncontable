@echo off
title Iniciar Sistema DISTAL
color 0B

echo ==========================================================
echo        INICIADOR DEL SISTEMA - DISTAL S.A.
echo ==========================================================
echo.
set "XAMPP_DIR=C:\xampp"

IF NOT EXIST "%XAMPP_DIR%" (
    echo [ERROR] No se encontro XAMPP en C:\xampp.
    echo El sistema requiere XAMPP para iniciar.
    pause
    exit /b 1
)

echo [1/3] Verificando Base de Datos (MySQL)...
tasklist /FI "IMAGENAME eq mysqld.exe" 2>NUL | find /I /N "mysqld.exe">NUL
if "%ERRORLEVEL%"=="0" (
    echo - MySQL ya esta en ejecucion.
) else (
    echo - Iniciando MySQL...
    start /B "" "%XAMPP_DIR%\mysql\bin\mysqld.exe"
    timeout /t 2 >nul
)

echo.
echo [2/3] Iniciando el Servidor Web (Backend/Frontend)...
start "Servidor PHP DISTAL" /MIN "%XAMPP_DIR%\php\php.exe" -S localhost:8000 -t "%~dp0\"
echo - Servidor local activado en el puerto 8000.

echo.
echo [3/3] Abriendo el sistema en el navegador...
timeout /t 2 >nul
start http://localhost:8000/

echo.
echo ==========================================================
echo               SISTEMA EN EJECUCION
echo ==========================================================
echo El sistema esta funcionando correctamente.
echo.
echo ATENCION: Para apagar el sistema, cierre esta ventana y
echo la ventana minimizada del Servidor PHP DISTAL.
echo.
pause >nul
