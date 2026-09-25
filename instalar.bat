@echo off
title Instalador del Sistema DISTAL
color 0A

echo ==========================================================
echo        INSTALADOR AUTOMATICO - SISTEMA DISTAL S.A.
echo ==========================================================
echo.
echo Este script instalara el sistema en su servidor local (XAMPP).
echo Requisitos Previos:
echo 1. Tener XAMPP instalado en la ruta por defecto (C:\xampp).
echo 2. El panel de control de XAMPP debe estar abierto.
echo 3. Los modulos de "Apache" y "MySQL" deben estar INICIADOS.
echo.
pause

set "XAMPP_DIR=C:\xampp"
set "HTDOCS_DIR=%XAMPP_DIR%\htdocs\sistema_distal"
set "MYSQL_EXE=%XAMPP_DIR%\mysql\bin\mysql.exe"

IF NOT EXIST "%XAMPP_DIR%" (
    echo.
    echo [ERROR] No se encontro XAMPP en C:\xampp.
    echo Por favor instale XAMPP en la ruta por defecto y vuelva a intentar.
    echo.
    pause
    exit /b 1
)

IF NOT EXIST "%MYSQL_EXE%" (
    echo.
    echo [ERROR] No se encontro MySQL.
    echo Asegurese de que XAMPP contenga MySQL.
    echo.
    pause
    exit /b 1
)

echo.
echo [1/3] Preparando el directorio destino en htdocs...
IF NOT EXIST "%HTDOCS_DIR%" (
    mkdir "%HTDOCS_DIR%"
) ELSE (
    echo El directorio %HTDOCS_DIR% ya existe. Los archivos se sobreescribiran.
)

echo.
echo [2/3] Copiando los archivos del sistema...
xcopy "%~dp0*" "%HTDOCS_DIR%\" /E /Y /C /I /Q
echo Archivos copiados con exito.

echo.
echo [3/3] Configurando la Base de Datos...
echo Creando e importando tablas desde sql\setup.sql
"%MYSQL_EXE%" -u root -e "CREATE DATABASE IF NOT EXISTS sistema_distal;"
"%MYSQL_EXE%" -u root sistema_distal < "%~dp0sql\setup.sql"

IF %ERRORLEVEL% NEQ 0 (
    echo.
    echo [ADVERTENCIA] Hubo un problema al importar la base de datos.
    echo Asegurese de que MySQL este corriendo en XAMPP.
) ELSE (
    echo Base de datos importada correctamente.
)

echo.
echo ==========================================================
echo               INSTALACION COMPLETADA
echo ==========================================================
echo El sistema se ha instalado exitosamente.
echo Puede acceder al sistema abriendo su navegador web e
echo ingresando a la siguiente direccion:
echo.
echo      http://localhost/sistema_distal
echo.
echo Presione cualquier tecla para salir...
pause >nul
