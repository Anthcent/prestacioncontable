@echo off
title Limpiar Datos DISTAL
color 0C

echo ==========================================================
echo        LIMPIEZA DE BASE DE DATOS - DISTAL S.A.
echo ==========================================================
echo.
echo ADVERTENCIA: Esta accion eliminara TODOS los empleados
echo y calculos de prestaciones del sistema permanentemente.
echo.
echo Presione cualquier tecla para confirmar o cierre esta ventana para cancelar.
pause >nul

set "XAMPP_DIR=C:\xampp"

IF NOT EXIST "%XAMPP_DIR%" (
    echo [ERROR] No se encontro XAMPP en C:\xampp.
    pause
    exit /b 1
)

echo.
echo Limpiando base de datos...
"%XAMPP_DIR%\mysql\bin\mysql.exe" -u root -e "USE sistema_distal; SET FOREIGN_KEY_CHECKS = 0; TRUNCATE TABLE prestaciones; TRUNCATE TABLE empleados; SET FOREIGN_KEY_CHECKS = 1;"

if "%ERRORLEVEL%"=="0" (
    echo.
    echo [EXITO] La base de datos ha sido limpiada correctamente.
    echo El sistema esta como nuevo, listo para usarse desde 0.
) else (
    echo.
    echo [ERROR] Hubo un problema al intentar limpiar la base de datos.
    echo Asegurate de que MySQL este en ejecucion (puedes abrir iniciar.bat primero).
)

echo.
pause
