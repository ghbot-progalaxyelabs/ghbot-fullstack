#!/bin/bash

# Interactive workflow helper
# Guides developers through starting new work

set -e

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
NC='\033[0m'
BOLD='\033[1m'

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"

echo -e "${BOLD}╔═══════════════════════════════════════════════════════════════════╗${NC}"
echo -e "${BOLD}║              WebMeteor - Start New Work Helper                    ║${NC}"
echo -e "${BOLD}╚═══════════════════════════════════════════════════════════════════╝${NC}"
echo

# Get developer name
echo -e "${CYAN}Step 1: Developer Information${NC}"
read -p "Your name: " developer_name

if [ -z "$developer_name" ]; then
    echo -e "${RED}Error:${NC} Developer name required"
    exit 1
fi

echo
echo -e "${CYAN}Step 2: Choose an Issue${NC}"
echo

# Show available issues
"$SCRIPT_DIR/assign-issue.sh" list

echo
read -p "Enter issue number to work on: " issue_num

if [ -z "$issue_num" ]; then
    echo -e "${RED}Error:${NC} Issue number required"
    exit 1
fi

# Show issue details
echo
echo -e "${CYAN}Step 3: Review Issue Details${NC}"
echo
"$SCRIPT_DIR/assign-issue.sh" show --issue "$issue_num"

echo
read -p "Continue with this issue? (Y/n) " -n 1 -r
echo
if [[ ! $REPLY =~ ^[Yy]$ ]] && [ ! -z "$REPLY" ]; then
    echo "Cancelled."
    exit 0
fi

# Assign issue
echo
echo -e "${CYAN}Step 4: Assigning Issue${NC}"
"$SCRIPT_DIR/assign-issue.sh" assign --issue "$issue_num" --developer "$developer_name"

# Create branch
echo
echo -e "${CYAN}Step 5: Create Branch${NC}"
read -p "Enter branch description (e.g., 'save-button'): " branch_desc

if [ -z "$branch_desc" ]; then
    branch_name="feature/issue-${issue_num}"
else
    branch_name="feature/issue-${issue_num}-${branch_desc}"
fi

echo "Creating branch: ${BOLD}${branch_name}${NC}"
git checkout -b "$branch_name" 2>/dev/null || {
    echo -e "${YELLOW}Branch already exists, switching to it${NC}"
    git checkout "$branch_name"
}

# Lock files
echo
echo -e "${CYAN}Step 6: Lock Files${NC}"
echo "Enter the files you'll be working on (comma-separated):"
echo -e "${YELLOW}Example: www/src/app/pages/editor/editor.component.ts,www/src/app/services/api.service.ts${NC}"
read -p "Files: " files

if [ -z "$files" ]; then
    echo -e "${RED}Error:${NC} No files specified"
    exit 1
fi

# Check if files are already locked
echo
echo "Checking if files are available..."
"$SCRIPT_DIR/dev-workflow.sh" check-files --files "$files"

echo
read -p "Proceed with locking these files? (Y/n) " -n 1 -r
echo
if [[ ! $REPLY =~ ^[Yy]$ ]] && [ ! -z "$REPLY" ]; then
    echo "Cancelled. Branch created but files not locked."
    exit 0
fi

# Estimated completion
echo
read -p "Estimated completion (e.g., '2025-11-15T18:00:00Z' or press Enter to skip): " estimated

# Lock the files
echo
echo -e "${CYAN}Locking files...${NC}"

if [ -z "$estimated" ]; then
    "$SCRIPT_DIR/dev-workflow.sh" lock \
        --developer "$developer_name" \
        --issue "Issue #${issue_num}" \
        --branch "$branch_name" \
        --files "$files"
else
    "$SCRIPT_DIR/dev-workflow.sh" lock \
        --developer "$developer_name" \
        --issue "Issue #${issue_num}" \
        --branch "$branch_name" \
        --files "$files" \
        --estimated "$estimated"
fi

lock_id=$(cat "$PROJECT_ROOT/.dev-locks.json" | jq -r ".locks[] | select(.branch == \"$branch_name\") | .id" | tail -1)

echo
echo -e "${GREEN}✓ All set! You're ready to start working.${NC}"
echo
echo -e "${BOLD}Summary:${NC}"
echo -e "  Developer: ${CYAN}$developer_name${NC}"
echo -e "  Issue: ${CYAN}#$issue_num${NC}"
echo -e "  Branch: ${CYAN}$branch_name${NC}"
echo -e "  Lock ID: ${CYAN}$lock_id${NC}"
echo -e "  Files: $(echo "$files" | tr ',' '\n' | wc -l | xargs) file(s) locked"
echo
echo -e "${BOLD}Next Steps:${NC}"
echo -e "  1. Start coding!"
echo -e "  2. Update status: ${CYAN}./scripts/dev-workflow.sh update --lock-id $lock_id --status \"Your status\"${NC}"
echo -e "  3. When done: ${CYAN}./scripts/finish-work.sh${NC}"
echo
echo -e "${YELLOW}Happy coding! 🚀${NC}"
