#!/bin/bash

# WebMeteor Build Script
# Builds Docker containers in the correct order to ensure dependencies are met

set -e  # Exit on error

echo "================================================"
echo "WebMeteor Docker Build Script"
echo "================================================"

# Colors for output
GREEN='\033[0;32m'
BLUE='\033[0;34m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Step 1: Generate API client from PHP routes
echo -e "\n${BLUE}[1/5] Generating TypeScript API client from PHP routes...${NC}"
cd api
php generate client --output=../www/api-client
if [ $? -eq 0 ]; then
    echo -e "${GREEN}✓ API client generated successfully${NC}"
else
    echo -e "${YELLOW}⚠ API client generation failed, using existing client${NC}"
fi
cd ..

# Step 2: Build database (no build step, just pull image)
echo -e "\n${BLUE}[2/5] Pulling database image...${NC}"
docker compose pull db
echo -e "${GREEN}✓ Database image ready${NC}"

# Step 3: Build API container
echo -e "\n${BLUE}[3/5] Building API container...${NC}"
docker compose build api
echo -e "${GREEN}✓ API container built${NC}"

# Step 4: Build Alert service
echo -e "\n${BLUE}[4/5] Building Alert service...${NC}"
docker compose build alert
echo -e "${GREEN}✓ Alert service built${NC}"

# Step 5: Build WWW (frontend) container
echo -e "\n${BLUE}[5/5] Building WWW (frontend) container...${NC}"
docker compose build www
echo -e "${GREEN}✓ WWW container built${NC}"

echo -e "\n${GREEN}================================================${NC}"
echo -e "${GREEN}✓ All containers built successfully!${NC}"
echo -e "${GREEN}================================================${NC}"

echo -e "\n${BLUE}To start the services, run:${NC}"
echo -e "  docker compose up"
echo -e "\n${BLUE}Or to rebuild and start:${NC}"
echo -e "  docker compose up --build"
echo -e "\n${BLUE}Or run this build script again and then start:${NC}"
echo -e "  ./build.sh && docker compose up"
