#!/bin/bash

# Assign Issue Script
# Helps developers claim and track issues from issues.md

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
ISSUES_FILE="$PROJECT_ROOT/issues.md"

# Function to list available issues
list_issues() {
    local show_assigned="${1:-false}"

    if [ ! -f "$ISSUES_FILE" ]; then
        echo -e "${RED}Error:${NC} issues.md not found"
        exit 1
    fi

    echo -e "${BOLD}╔═══════════════════════════════════════════════════════════════════╗${NC}"
    echo -e "${BOLD}║                         Available Issues                          ║${NC}"
    echo -e "${BOLD}╚═══════════════════════════════════════════════════════════════════╝${NC}"
    echo

    # Extract issue numbers and titles
    grep -E "^### Issue #[0-9]+:" "$ISSUES_FILE" | while read -r line; do
        issue_num=$(echo "$line" | grep -oP '(?<=Issue #)\d+')
        issue_title=$(echo "$line" | grep -oP '(?<=: ).*')

        # Check if assigned (look for "Assigned to:" in next 10 lines after issue header)
        is_assigned=$(awk "/^### Issue #${issue_num}:/{flag=1; count=0} flag{count++; if(count<=10 && /\*\*Assigned to\*\*:/) {print \$0; flag=0}} count>=10{flag=0}" "$ISSUES_FILE" | grep -v "Unassigned" | wc -l)

        if [ "$show_assigned" = "true" ] || [ "$is_assigned" -eq 0 ]; then
            if [ "$is_assigned" -eq 0 ]; then
                echo -e "${GREEN}✓${NC} Issue #${CYAN}${issue_num}${NC}: $issue_title"
            else
                echo -e "  Issue #${CYAN}${issue_num}${NC}: $issue_title ${YELLOW}(assigned)${NC}"
            fi
        fi
    done

    echo
}

# Function to show issue details
show_issue() {
    local issue_num="$1"

    if [ ! -f "$ISSUES_FILE" ]; then
        echo -e "${RED}Error:${NC} issues.md not found"
        exit 1
    fi

    echo -e "${BOLD}Issue #${issue_num} Details${NC}\n"

    # Extract issue section (from ### Issue to next ### or ---)
    awk "/^### Issue #${issue_num}:/{flag=1} flag{print; if(/^###/ && !/^### Issue #${issue_num}:/ || /^---$/) exit}" "$ISSUES_FILE"
}

# Function to assign issue
assign_issue() {
    local issue_num="$1"
    local developer="$2"

    if [ ! -f "$ISSUES_FILE" ]; then
        echo -e "${RED}Error:${NC} issues.md not found"
        exit 1
    fi

    # Check if issue exists
    if ! grep -q "^### Issue #${issue_num}:" "$ISSUES_FILE"; then
        echo -e "${RED}Error:${NC} Issue #${issue_num} not found"
        exit 1
    fi

    echo -e "${BLUE}Assigning Issue #${issue_num} to ${developer}...${NC}\n"

    # Check if already has "Assigned to:" field
    local has_assignment=$(awk "/^### Issue #${issue_num}:/{flag=1; count=0} flag{count++; if(count<=15 && /\*\*Assigned to\*\*:/) {print \"yes\"; flag=0}} count>=15{flag=0}" "$ISSUES_FILE")

    if [ "$has_assignment" = "yes" ]; then
        echo -e "${YELLOW}Note:${NC} Issue already has assignment. Updating...\n"
        # Update existing assignment
        sed -i "/^### Issue #${issue_num}:/,/^###/{s/\*\*Assigned to\*\*:.*/\*\*Assigned to\*\*: ${developer}/}" "$ISSUES_FILE"
    else
        # Add assignment after the issue header
        sed -i "/^### Issue #${issue_num}:/a\\\n**Assigned to**: ${developer}\n**Status**: In Progress\n**Started**: $(date +%Y-%m-%d)" "$ISSUES_FILE"
    fi

    echo -e "${GREEN}✓${NC} Issue #${issue_num} assigned to ${BOLD}${developer}${NC}"
    echo -e "\nNext steps:"
    echo -e "  1. Create branch: ${CYAN}git checkout -b feature/issue-${issue_num}-<description>${NC}"
    echo -e "  2. Lock files: ${CYAN}./scripts/dev-workflow.sh lock --developer \"${developer}\" --issue \"Issue #${issue_num}\"${NC}"
    echo -e "  3. Start working!"
}

# Function to unassign issue
unassign_issue() {
    local issue_num="$1"

    if [ ! -f "$ISSUES_FILE" ]; then
        echo -e "${RED}Error:${NC} issues.md not found"
        exit 1
    fi

    # Remove assignment lines
    sed -i "/^### Issue #${issue_num}:/,/^###/{/\*\*Assigned to\*\*:/d; /\*\*Status\*\*:/d; /\*\*Started\*\*:/d; /\*\*Branch\*\*:/d}" "$ISSUES_FILE"

    echo -e "${GREEN}✓${NC} Issue #${issue_num} unassigned"
}

# Function to show my issues
show_my_issues() {
    local developer="$1"

    if [ ! -f "$ISSUES_FILE" ]; then
        echo -e "${RED}Error:${NC} issues.md not found"
        exit 1
    fi

    echo -e "${BOLD}Issues assigned to ${developer}${NC}\n"

    # Find all issues assigned to developer
    grep -B 2 "\*\*Assigned to\*\*: ${developer}" "$ISSUES_FILE" | grep "^### Issue #" | while read -r line; do
        issue_num=$(echo "$line" | grep -oP '(?<=Issue #)\d+')
        issue_title=$(echo "$line" | grep -oP '(?<=: ).*')

        # Get status
        status=$(awk "/^### Issue #${issue_num}:/{flag=1; count=0} flag{count++; if(count<=10 && /\*\*Status\*\*:/) {print \$0; flag=0}} count>=10{flag=0}" "$ISSUES_FILE" | grep -oP '(?<=\*\*Status\*\*: ).*')

        echo -e "  Issue #${CYAN}${issue_num}${NC}: $issue_title"
        [ -n "$status" ] && echo -e "    Status: ${YELLOW}${status}${NC}"
    done

    echo
}

# Show help
show_help() {
    cat << EOF
${BOLD}Issue Assignment Manager${NC}

${BOLD}USAGE:${NC}
    $0 <command> [options]

${BOLD}COMMANDS:${NC}
    ${CYAN}list${NC}              List all available (unassigned) issues
    ${CYAN}list-all${NC}          List all issues including assigned ones
    ${CYAN}show${NC}              Show details for a specific issue
    ${CYAN}assign${NC}            Assign an issue to a developer
    ${CYAN}unassign${NC}          Unassign an issue
    ${CYAN}my-issues${NC}         Show issues assigned to a developer

${BOLD}OPTIONS:${NC}
    --issue NUM           Issue number
    --developer NAME      Developer name

${BOLD}EXAMPLES:${NC}
    # List available issues
    $0 list

    # Show issue details
    $0 show --issue 12

    # Assign issue to yourself
    $0 assign --issue 12 --developer "John Doe"

    # Show your issues
    $0 my-issues --developer "John Doe"

    # Unassign when done
    $0 unassign --issue 12

${BOLD}WORKFLOW:${NC}
    1. List issues: $0 list
    2. Show details: $0 show --issue 12
    3. Assign to yourself: $0 assign --issue 12 --developer "Your Name"
    4. Lock files: ./scripts/dev-workflow.sh lock ...
    5. Do work
    6. Create PR
    7. Unassign: $0 unassign --issue 12

EOF
}

# Main script logic
main() {
    cd "$PROJECT_ROOT"

    local command="${1:-}"
    shift || true

    case "$command" in
        list)
            list_issues false
            ;;

        list-all)
            list_issues true
            ;;

        show)
            local issue_num=""

            while [[ $# -gt 0 ]]; do
                case "$1" in
                    --issue) issue_num="$2"; shift 2 ;;
                    *) echo "Unknown option: $1"; exit 1 ;;
                esac
            done

            if [ -z "$issue_num" ]; then
                echo -e "${RED}Error:${NC} --issue required"
                exit 1
            fi

            show_issue "$issue_num"
            ;;

        assign)
            local issue_num=""
            local developer=""

            while [[ $# -gt 0 ]]; do
                case "$1" in
                    --issue) issue_num="$2"; shift 2 ;;
                    --developer) developer="$2"; shift 2 ;;
                    *) echo "Unknown option: $1"; exit 1 ;;
                esac
            done

            if [ -z "$issue_num" ] || [ -z "$developer" ]; then
                echo -e "${RED}Error:${NC} --issue and --developer required"
                exit 1
            fi

            assign_issue "$issue_num" "$developer"
            ;;

        unassign)
            local issue_num=""

            while [[ $# -gt 0 ]]; do
                case "$1" in
                    --issue) issue_num="$2"; shift 2 ;;
                    *) echo "Unknown option: $1"; exit 1 ;;
                esac
            done

            if [ -z "$issue_num" ]; then
                echo -e "${RED}Error:${NC} --issue required"
                exit 1
            fi

            unassign_issue "$issue_num"
            ;;

        my-issues)
            local developer=""

            while [[ $# -gt 0 ]]; do
                case "$1" in
                    --developer) developer="$2"; shift 2 ;;
                    *) echo "Unknown option: $1"; exit 1 ;;
                esac
            done

            if [ -z "$developer" ]; then
                echo -e "${RED}Error:${NC} --developer required"
                exit 1
            fi

            show_my_issues "$developer"
            ;;

        help|--help|-h)
            show_help
            ;;

        *)
            echo -e "${RED}Error:${NC} Unknown command: $command"
            echo "Use '$0 help' for usage information"
            exit 1
            ;;
    esac
}

main "$@"
