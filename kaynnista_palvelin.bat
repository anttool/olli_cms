@echo off
chcp 65001 >nul
title Olli CMS - Paikallinen kehityspalvelin
echo ========================================================
echo   Olli CMS - Moderni tietokannaton julkaisujarjestelma
echo ========================================================
echo.

where php >nul 2>nul
if %errorlevel% equ 0 (
    echo Kaynnistetaan PHP-palvelin osoitteeseen http://localhost:8000 ...
    start http://localhost:8000
    php -S localhost:8000
    goto :eof
)

if exist "C:\xampp\php\php.exe" (
    echo Kaynnistetaan XAMPP PHP-palvelin osoitteeseen http://localhost:8000 ...
    start http://localhost:8000
    "C:\xampp\php\php.exe" -S localhost:8000
    goto :eof
)

echo [HUOMIO] PHP:ta ei loytynyt tietokoneen polusta (PATH).
echo Voit asentaa PHP:n Windowsiin yhdella komennolla PowerShellissa:
echo   winget install PHP.PHP.8.3
echo Tai siirtaa koko olli_cms-kansion suoraan webhotelliin!
echo.
pause
