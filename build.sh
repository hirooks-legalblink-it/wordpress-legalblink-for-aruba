#!/bin/bash

# Build script for LegalBlink for Aruba WordPress Plugin
# This script compiles the frontend, installs dependencies, and creates a distributable plugin zip

set -e  # Exit on error

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Directories
PROJECT_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ADMIN_UI_DIR="${PROJECT_ROOT}/admin-ui"
PLUGIN_DIR="${PROJECT_ROOT}/plugin/legalblink-for-aruba"
DIST_DIR="${PROJECT_ROOT}/dist"
PLUGIN_NAME="legalblink-for-aruba"
TEMP_BUILD_DIR="${DIST_DIR}/temp-build"

echo -e "${GREEN}========================================${NC}"
echo -e "${GREEN}LegalBlink Plugin Build Script${NC}"
echo -e "${GREEN}========================================${NC}"

# Step 0: Check system requirements
echo -e "\n${YELLOW}[0/5] Checking system requirements...${NC}"

# Check for required commands
REQUIRED_COMMANDS=("node" "npm" "composer" "rsync" "zip")
MISSING_COMMANDS=()

for cmd in "${REQUIRED_COMMANDS[@]}"; do
    if ! command -v "$cmd" &> /dev/null; then
        MISSING_COMMANDS+=("$cmd")
    fi
done

if [ ${#MISSING_COMMANDS[@]} -ne 0 ]; then
    echo -e "${RED}✗ Missing required commands: ${MISSING_COMMANDS[*]}${NC}"
    echo -e "${YELLOW}Please install the missing commands:${NC}"
    for cmd in "${MISSING_COMMANDS[@]}"; do
        case "$cmd" in
            node|npm)
                echo -e "  - Node.js: https://nodejs.org/ or use 'brew install node'"
                ;;
            composer)
                echo -e "  - Composer: https://getcomposer.org/ or use 'brew install composer'"
                ;;
            rsync)
                echo -e "  - rsync: Should be pre-installed on macOS, or use 'brew install rsync'"
                ;;
            zip)
                echo -e "  - zip: Should be pre-installed on macOS"
                ;;
        esac
    done
    exit 1
fi

echo -e "${GREEN}✓ All required commands found${NC}"

# Check Node.js version (require 20+)
NODE_VERSION=$(node -v | cut -d'v' -f2 | cut -d'.' -f1)
if [ "$NODE_VERSION" -lt 20 ]; then
    echo -e "${RED}✗ Node.js version $NODE_VERSION detected${NC}"
    echo -e "${RED}  Node.js version 20 or higher is required${NC}"
    echo -e "${YELLOW}  Please upgrade Node.js: https://nodejs.org/${NC}"
    echo -e "${YELLOW}  Or use nvm: 'nvm install 20 && nvm use 20'${NC}"
    exit 1
fi

echo -e "${GREEN}✓ Node.js version $(node -v) (>= 20) ${NC}"

# Step 1: Clean previous builds
echo -e "\n${YELLOW}[1/5] Cleaning previous builds...${NC}"
rm -rf "${DIST_DIR}"
mkdir -p "${DIST_DIR}"
mkdir -p "${TEMP_BUILD_DIR}/${PLUGIN_NAME}"

# Step 2: Build frontend
echo -e "\n${YELLOW}[2/5] Building frontend (Vue + Vite)...${NC}"
cd "${ADMIN_UI_DIR}"

# Check if node_modules exists, if not install dependencies
if [ ! -d "node_modules" ]; then
    echo -e "${YELLOW}Installing npm dependencies...${NC}"
    npm install
fi

# Build the frontend
npm run build

if [ $? -eq 0 ]; then
    echo -e "${GREEN}✓ Frontend build completed${NC}"
else
    echo -e "${RED}✗ Frontend build failed${NC}"
    exit 1
fi

# Step 3: Install Composer dependencies
echo -e "\n${YELLOW}[3/5] Installing Composer dependencies...${NC}"
cd "${PLUGIN_DIR}"

# Install composer dependencies with optimized autoloader (no dev dependencies)
composer install --no-dev --optimize-autoloader --no-interaction

if [ $? -eq 0 ]; then
    echo -e "${GREEN}✓ Composer dependencies installed${NC}"
else
    echo -e "${RED}✗ Composer install failed${NC}"
    exit 1
fi

# Step 4: Copy plugin files to temp build directory
echo -e "\n${YELLOW}[4/5] Copying plugin files...${NC}"
cd "${PLUGIN_DIR}"

# Copy all necessary files, excluding development/scaffolding files
rsync -av \
    --exclude='.git' \
    --exclude='.gitignore' \
    --exclude='.DS_Store' \
    --exclude='node_modules' \
    --exclude='composer.json' \
    --exclude='composer.lock' \
    --exclude='.env' \
    --exclude='*.log' \
    --exclude='.idea' \
    --exclude='.vscode' \
    --exclude='logs/*.log' \
    --exclude='*.zip' \
    --exclude='*.example.php' \
    ./ "${TEMP_BUILD_DIR}/${PLUGIN_NAME}/"

# Ensure logs directory exists but is empty (keep index.php)
mkdir -p "${TEMP_BUILD_DIR}/${PLUGIN_NAME}/logs"
rm -f "${TEMP_BUILD_DIR}/${PLUGIN_NAME}/logs/"*.log

echo -e "${GREEN}✓ Plugin files copied${NC}"

# Step 5: Create zip file
echo -e "\n${YELLOW}[5/5] Creating plugin zip...${NC}"
cd "${TEMP_BUILD_DIR}"

# Get version from plugin file
VERSION=$(grep -E "Version:" "${TEMP_BUILD_DIR}/${PLUGIN_NAME}/legalblink-for-aruba.php" | awk '{print $3}')
ZIP_NAME="${PLUGIN_NAME}_${VERSION}.zip"

# Create the zip
zip -r "${DIST_DIR}/${ZIP_NAME}" "${PLUGIN_NAME}" -q

if [ $? -eq 0 ]; then
    echo -e "${GREEN}✓ Plugin zip created: ${ZIP_NAME}${NC}"
else
    echo -e "${RED}✗ Failed to create zip${NC}"
    exit 1
fi

# Cleanup temp directory
echo -e "\n${YELLOW}Cleaning up...${NC}"
rm -rf "${TEMP_BUILD_DIR}"

# Final output
echo -e "\n${GREEN}========================================${NC}"
echo -e "${GREEN}Build completed successfully!${NC}"
echo -e "${GREEN}========================================${NC}"
echo -e "Plugin zip: ${GREEN}${DIST_DIR}/${ZIP_NAME}${NC}"
echo -e "Size: $(du -h "${DIST_DIR}/${ZIP_NAME}" | cut -f1)"
echo -e "\n${YELLOW}Contents:${NC}"
unzip -l "${DIST_DIR}/${ZIP_NAME}"

echo -e "\n${GREEN}Ready for distribution!${NC}"

