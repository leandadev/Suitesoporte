@echo off
title Stellar Ops Hub - Consola de Operaciones de Soporte
cd /d "%~dp0"
echo =====================================================================
echo STELLAR OPS HUB - CONSOLA DE SOPORTE & OPERACIONES ERP
echo =====================================================================
echo.
echo Panel Web: http://127.0.0.1:8095/admin
echo Usuario:   soporte@leandatech.com
echo Clave:     admin1234
echo.
echo Presione Ctrl+C para detener el servidor.
echo =====================================================================
php artisan serve --host=0.0.0.0 --port=8095
pause