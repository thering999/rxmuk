#!/bin/bash

# Docker Setup Script for Linux/Mac
# Usage: bash docker-setup.sh

set -e

echo ""
echo "==================================="
echo "  rxmuk Docker Setup"
echo "==================================="
echo ""

# Check if Docker is installed
if ! command -v docker &> /dev/null; then
    echo "❌ ERROR: Docker is not installed"
    echo "Please install Docker from: https://www.docker.com/products/docker-desktop"
    exit 1
fi

# Check if Docker Compose is installed
if ! command -v docker-compose &> /dev/null; then
    echo "❌ ERROR: Docker Compose is not installed"
    echo "Please install Docker Compose: https://docs.docker.com/compose/install/"
    exit 1
fi

echo "✓ Docker is installed"
docker --version
docker-compose --version
echo ""

# Create .env file if it doesn't exist
if [ ! -f .env ]; then
    if [ -f .env.example ]; then
        echo "Creating .env file from template..."
        cp .env.example .env
        echo "✓ .env created from .env.example"
    else
        echo "❌ ERROR: .env.example not found"
        exit 1
    fi
else
    echo "✓ .env file already exists"
fi

echo ""
echo "Starting Docker containers..."
echo ""

# Start Docker composition
docker-compose up -d

if [ $? -ne 0 ]; then
    echo "❌ ERROR: Failed to start Docker containers"
    exit 1
fi

# Wait for services to be ready
echo ""
echo "Waiting for services to be ready..."
sleep 5

# Check if services are running
docker-compose ps

echo ""
echo "==================================="
echo "✓ Setup Complete!"
echo "==================================="
echo ""
echo "Access the application:"
echo ""
echo "  Website:    http://localhost"
echo "  PHPMyAdmin: http://localhost:8080"
echo "  Database:   localhost:3306"
echo ""
echo "Login with:"
echo "  Username: admin"
echo "  Password: 123456"
echo ""
echo "Database credentials:"
echo "  User:     rxmuk_user"
echo "  Password: rxmuk_pass123"
echo ""
echo "Useful commands:"
echo ""
echo "  View logs:      docker-compose logs -f"
echo "  Stop:           docker-compose down"
echo "  Restart:        docker-compose restart"
echo "  SSH to app:     docker-compose exec app bash"
echo "  MySQL shell:    docker-compose exec db mysql -u rxmuk_user -p rxmuk_pass123 rxmuk_db"
echo ""
echo "For more help, see DOCKER.md or QUICK_DOCKER_START.md"
echo ""
