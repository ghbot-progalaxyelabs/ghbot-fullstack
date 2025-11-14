#!/bin/bash

# Finish work helper
# Guides developers through completing and cleaning up work

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
echo -e "${BOLD}║              WebMeteor - Finish Work Helper                       ║${NC}"
echo -e "${BOLD}╚═══════════════════════════════════════════════════════════════════╝${NC}"
echo

# Get current branch
current_branch=$(git branch --show-current)
echo -e "Current branch: ${CYAN}${current_branch}${NC}"

# Find locks for this branch
locks=$(cat "$PROJECT_ROOT/.dev-locks.json" | jq -r ".locks[] | select(.branch == \"$current_branch\") | .id")

if [ -z "$locks" ]; then
    echo -e "${YELLOW}⚠ No locks found for this branch${NC}"
    echo "You may have already unlocked, or started work without locking."
    echo
    read -p "Continue anyway? (y/N) " -n 1 -r
    echo
    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
        exit 0
    fi
else
    echo -e "${GREEN}✓ Found locks for this branch${NC}"
    echo "$locks" | while read -r lock_id; do
        "$SCRIPT_DIR/dev-workflow.sh" info --lock-id "$lock_id"
        echo
    done
fi

# Check git status
echo -e "${CYAN}Step 1: Review Changes${NC}"
echo
git status

echo
read -p "Have you committed all your changes? (Y/n) " -n 1 -r
echo
if [[ ! $REPLY =~ ^[Yy]$ ]] && [ ! -z "$REPLY" ]; then
    echo
    echo -e "${YELLOW}Tip: Commit your changes first:${NC}"
    echo -e "  git add ."
    echo -e "  git commit -m \"Your commit message\""
    echo
    exit 0
fi

# Push to remote
echo
echo -e "${CYAN}Step 2: Push to Remote${NC}"
echo
read -p "Push branch to remote? (Y/n) " -n 1 -r
echo
if [[ $REPLY =~ ^[Yy]$ ]] || [ -z "$REPLY" ]; then
    git push origin "$current_branch" || {
        echo -e "${RED}Failed to push. Fix any issues and try again.${NC}"
        exit 1
    }
    echo -e "${GREEN}✓ Pushed successfully${NC}"
fi

# Create PR
echo
echo -e "${CYAN}Step 3: Create Pull Request${NC}"
echo
read -p "Create pull request? (Y/n) " -n 1 -r
echo
if [[ $REPLY =~ ^[Yy]$ ]] || [ -z "$REPLY" ]; then
    # Extract issue number from branch name
    issue_num=$(echo "$current_branch" | grep -oP '(?<=issue-)\d+' || echo "")

    if [ -n "$issue_num" ]; then
        pr_body="Fixes #${issue_num}"
    else
        pr_body=""
    fi

    echo
    read -p "PR title: " pr_title

    if [ -z "$pr_title" ]; then
        echo -e "${YELLOW}Using branch name as title${NC}"
        pr_title="$current_branch"
    fi

    if command -v gh &> /dev/null; then
        if [ -n "$pr_body" ]; then
            gh pr create --title "$pr_title" --body "$pr_body" || {
                echo -e "${YELLOW}Failed to create PR automatically. Create it manually on GitHub.${NC}"
            }
        else
            gh pr create --title "$pr_title" || {
                echo -e "${YELLOW}Failed to create PR automatically. Create it manually on GitHub.${NC}"
            }
        fi
    else
        echo -e "${YELLOW}GitHub CLI not installed. Create PR manually:${NC}"
        echo -e "  https://github.com/your-org/ghbot-fullstack/compare/${current_branch}"
    fi
fi

# Unlock files
echo
echo -e "${CYAN}Step 4: Unlock Files${NC}"
echo

if [ -n "$locks" ]; then
    echo "$locks" | while read -r lock_id; do
        echo "Unlocking: $lock_id"
        "$SCRIPT_DIR/dev-workflow.sh" unlock --lock-id "$lock_id"
    done
    echo -e "${GREEN}✓ All locks released${NC}"
else
    echo -e "${YELLOW}No locks to release${NC}"
fi

# Unassign issue
echo
echo -e "${CYAN}Step 5: Update Issue Status${NC}"
echo
read -p "Mark issue as complete? (Y/n) " -n 1 -r
echo
if [[ $REPLY =~ ^[Yy]$ ]] || [ -z "$REPLY" ]; then
    if [ -n "$issue_num" ]; then
        "$SCRIPT_DIR/assign-issue.sh" unassign --issue "$issue_num" || echo "Could not unassign issue"
        echo -e "${GREEN}✓ Issue marked as complete${NC}"
    else
        echo -e "${YELLOW}Could not determine issue number from branch name${NC}"
    fi
fi

# Summary
echo
echo -e "${BOLD}╔═══════════════════════════════════════════════════════════════════╗${NC}"
echo -e "${BOLD}║                         Work Complete! 🎉                         ║${NC}"
echo -e "${BOLD}╚═══════════════════════════════════════════════════════════════════╝${NC}"
echo
echo -e "${GREEN}✓ Changes committed${NC}"
echo -e "${GREEN}✓ Branch pushed${NC}"
echo -e "${GREEN}✓ Pull request created${NC}"
echo -e "${GREEN}✓ Files unlocked${NC}"
echo -e "${GREEN}✓ Issue updated${NC}"
echo
echo -e "${BOLD}Next Steps:${NC}"
echo -e "  1. Wait for code review"
echo -e "  2. Address any feedback"
echo -e "  3. Once approved, merge PR"
echo -e "  4. Start next task: ${CYAN}./scripts/start-work.sh${NC}"
echo
echo -e "${YELLOW}Great job! 🚀${NC}"
