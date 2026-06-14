@echo off
REM Docker Setup Script for Windows
REM Usage: Run this script to quickly set up rxmuk with Docker

echo.
echo ===================================
echo   rxmuk Docker Setup for Windows
echo ===================================
echo.

REM Check if Docker is installed
docker --version >nul 2>&1
if %errorlevel% neq 0 (
    echo ERROR: Docker is not installed or not in PATH
    echo Please install Docker Desktop from: https://www.docker.com/products/docker-desktop
    pause
    exit /b 1
)

REM Check if Docker Compose is available
docker-compose --version >nul 2>&1
if %errorlevel% neq 0 (
    echo ERROR: Docker Compose is not available
    echo Please ensure Docker Desktop is properly installed
    pause
    exit /b 1
)

echo ✓ Docker is installed
docker --version
docker-compose --version
echo.

REM Create .env file if it doesn't exist
if not exist .env (
    echo Creating .env file from template...
    if exist .env.example (
        copy .env.example .env
        echo ✓ .env created from .env.example
    ) else (
        echo ERROR: .env.example not found
        pause
        exit /b 1
    )
) else (
    echo ✓ .env file already exists
)

echo.
echo Starting Docker containers...
echo.

REM Start Docker composition
docker-compose up -d

if %errorlevel% neq 0 (
    echo ERROR: Failed to start Docker containers
    pause
    exit /b 1
)

REM Wait for services to be ready
echo.
echo Waiting for services to be ready...
timeout /t 5 /nobreak

REM Check if services are running
docker-compose ps

echo.
echo ===================================
echo ✓ Setup Complete!
echo ===================================
echo.
echo Access the application:
echo.
echo   Website:    http://localhost
echo   PHPMyAdmin: http://localhost:8080
echo   Database:   localhost:3306
echo.
echo Login with:
echo   Username: admin
echo   Password: 123456
echo.
echo Useful commands:
echo.
echo   View logs:    docker-compose logs -f
echo   Stop:         docker-compose down
echo   Restart:      docker-compose restart
echo   SSH to app:   docker-compose exec app bash
echo.
pause
