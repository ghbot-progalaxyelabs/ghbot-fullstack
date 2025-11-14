#!/bin/bash

# Development Workflow Manager
# Manages file locks and developer coordination

set -e

LOCK_FILE=".dev-locks.json"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
NC='\033[0m' # No Color
BOLD='\033[1m'

# Initialize lock file if it doesn't exist
init_lock_file() {
    if [ ! -f "$PROJECT_ROOT/$LOCK_FILE" ]; then
        echo '{"locks":[],"version":"1.0.0","lastUpdated":""}' > "$PROJECT_ROOT/$LOCK_FILE"
        echo -e "${GREEN}✓${NC} Initialized $LOCK_FILE"
    fi
}

# Generate unique lock ID
generate_lock_id() {
    echo "lock-$(date +%s)-$(openssl rand -hex 4)"
}

# Get current timestamp
get_timestamp() {
    date -u +"%Y-%m-%dT%H:%M:%SZ"
}

# Read locks from file
read_locks() {
    if [ -f "$PROJECT_ROOT/$LOCK_FILE" ]; then
        cat "$PROJECT_ROOT/$LOCK_FILE"
    else
        echo '{"locks":[]}'
    fi
}

# Write locks to file
write_locks() {
    local locks_json="$1"
    echo "$locks_json" | jq --arg ts "$(get_timestamp)" '.lastUpdated = $ts' > "$PROJECT_ROOT/$LOCK_FILE"
}

# Lock files
lock_files() {
    local developer="$1"
    local issue="$2"
    local files="$3"
    local branch="${4:-}"
    local estimated_completion="${5:-}"

    if [ -z "$developer" ] || [ -z "$issue" ] || [ -z "$files" ]; then
        echo -e "${RED}Error:${NC} Missing required parameters"
        echo "Usage: $0 lock --developer \"Name\" --issue \"Issue #X\" --files \"file1,file2\""
        exit 1
    fi

    init_lock_file

    local lock_id=$(generate_lock_id)
    local timestamp=$(get_timestamp)

    # Convert comma-separated files to JSON array
    local files_array=$(echo "$files" | jq -R 'split(",") | map(gsub("^\\s+|\\s+$";""))')

    # Extract issue number
    local issue_number=$(echo "$issue" | grep -oP '(?<=#)\d+' || echo "")

    # Check if any files are already locked
    local locks=$(read_locks)
    local conflicts=$(echo "$locks" | jq -r --argjson files "$files_array" '
        .locks[] | select(.files as $locked | $files | any(. as $file | $locked | contains([$file]))) |
        "File \(.files | join(", ")) locked by \(.developer) (Lock: \(.id))"
    ')

    if [ -n "$conflicts" ]; then
        echo -e "${YELLOW}⚠ Warning: Some files are already locked:${NC}"
        echo "$conflicts"
        read -p "Continue anyway? (y/N) " -n 1 -r
        echo
        if [[ ! $REPLY =~ ^[Yy]$ ]]; then
            echo "Aborted."
            exit 1
        fi
    fi

    # Create new lock
    local new_lock=$(jq -n \
        --arg id "$lock_id" \
        --arg dev "$developer" \
        --arg issue "$issue" \
        --arg issue_num "$issue_number" \
        --argjson files "$files_array" \
        --arg status "Starting work" \
        --arg timestamp "$timestamp" \
        --arg branch "$branch" \
        --arg estimated "$estimated_completion" \
        '{
            id: $id,
            developer: $dev,
            issue: $issue,
            issueNumber: $issue_num,
            files: $files,
            status: $status,
            lockedAt: $timestamp,
            estimatedCompletion: $estimated,
            branch: $branch
        }')

    # Add lock to file
    local updated_locks=$(echo "$locks" | jq --argjson new_lock "$new_lock" '.locks += [$new_lock]')
    write_locks "$updated_locks"

    echo -e "${GREEN}✓ Successfully locked files${NC}"
    echo -e "Lock ID: ${CYAN}$lock_id${NC}"
    echo -e "Developer: ${BOLD}$developer${NC}"
    echo -e "Issue: $issue"
    [ -n "$branch" ] && echo -e "Branch: $branch"
    echo -e "Files ($(echo "$files_array" | jq '. | length')):"
    echo "$files_array" | jq -r '.[] | "  ✓ \(.)"'
}

# Unlock files
unlock_files() {
    local lock_id="$1"
    local developer="$2"
    local all="${3:-false}"

    init_lock_file
    local locks=$(read_locks)

    if [ "$all" = "true" ] && [ -n "$developer" ]; then
        # Unlock all locks for developer
        local updated_locks=$(echo "$locks" | jq --arg dev "$developer" '.locks = [.locks[] | select(.developer != $dev)]')
        local removed_count=$(echo "$locks" | jq --arg dev "$developer" '[.locks[] | select(.developer == $dev)] | length')

        write_locks "$updated_locks"
        echo -e "${GREEN}✓ Unlocked $removed_count lock(s) for $developer${NC}"
    elif [ -n "$lock_id" ]; then
        # Unlock specific lock
        local lock_exists=$(echo "$locks" | jq --arg id "$lock_id" '.locks[] | select(.id == $id) | .id' | wc -l)

        if [ "$lock_exists" -eq 0 ]; then
            echo -e "${RED}Error:${NC} Lock ID not found: $lock_id"
            exit 1
        fi

        local updated_locks=$(echo "$locks" | jq --arg id "$lock_id" '.locks = [.locks[] | select(.id != $id)]')
        write_locks "$updated_locks"
        echo -e "${GREEN}✓ Unlocked lock: $lock_id${NC}"
    else
        echo -e "${RED}Error:${NC} Must specify --lock-id or --developer with --all"
        exit 1
    fi
}

# Update lock status
update_lock() {
    local lock_id="$1"
    local status="$2"

    init_lock_file
    local locks=$(read_locks)

    local lock_exists=$(echo "$locks" | jq --arg id "$lock_id" '.locks[] | select(.id == $id) | .id' | wc -l)

    if [ "$lock_exists" -eq 0 ]; then
        echo -e "${RED}Error:${NC} Lock ID not found: $lock_id"
        exit 1
    fi

    local updated_locks=$(echo "$locks" | jq --arg id "$lock_id" --arg status "$status" '
        .locks = [.locks[] | if .id == $id then .status = $status else . end]
    ')

    write_locks "$updated_locks"
    echo -e "${GREEN}✓ Updated lock status${NC}"
}

# Add files to existing lock
add_files_to_lock() {
    local lock_id="$1"
    local new_files="$2"

    init_lock_file
    local locks=$(read_locks)

    local files_array=$(echo "$new_files" | jq -R 'split(",") | map(gsub("^\\s+|\\s+$";""))')

    local updated_locks=$(echo "$locks" | jq --arg id "$lock_id" --argjson files "$files_array" '
        .locks = [.locks[] | if .id == $id then .files += $files | .files |= unique else . end]
    ')

    write_locks "$updated_locks"
    echo -e "${GREEN}✓ Added files to lock${NC}"
}

# Show status of all locks
show_status() {
    local developer="$1"
    local show_stale="${2:-false}"

    init_lock_file
    local locks=$(read_locks)

    local lock_count=$(echo "$locks" | jq '.locks | length')

    if [ "$lock_count" -eq 0 ]; then
        echo -e "${GREEN}✓ No active locks${NC}"
        return
    fi

    echo -e "${BOLD}╔═══════════════════════════════════════════════════════════════════╗${NC}"
    echo -e "${BOLD}║                    Development Locks Status                       ║${NC}"
    echo -e "${BOLD}╚═══════════════════════════════════════════════════════════════════╝${NC}"
    echo

    local current_time=$(date +%s)

    echo "$locks" | jq -r --arg dev "$developer" --arg stale "$show_stale" --argjson current "$current_time" '
        .locks[] |
        select(if $dev != "" then .developer == $dev else true end) |
        . as $lock |
        ($current - (.lockedAt | fromdateiso8601)) as $age_seconds |
        ($age_seconds / 3600 | floor) as $age_hours |
        select(if $stale == "true" then $age_hours >= 24 else true end) |
        "Lock ID: \(.id)\n" +
        "Developer: \(.developer)\n" +
        "Issue: \(.issue)\n" +
        (if .branch != "" then "Branch: \(.branch)\n" else "" end) +
        "Status: \(.status) (\(
            if $age_hours < 1 then "\($age_seconds / 60 | floor) minutes ago"
            elif $age_hours < 24 then "\($age_hours) hours ago"
            else "\($age_hours / 24 | floor) days ago"
            end
        ))\n" +
        (if .estimatedCompletion != "" then "Estimated Completion: \(.estimatedCompletion)\n" else "" end) +
        "Files (\(.files | length)):\n" +
        (.files | map("  ✓ \(.)") | join("\n")) +
        "\n"
    '

    echo -e "${BOLD}═══════════════════════════════════════════════════════════════════${NC}"

    local file_count=$(echo "$locks" | jq '[.locks[].files[]] | length')
    echo -e "Total Active Locks: ${CYAN}$lock_count${NC}"
    echo -e "Total Locked Files: ${CYAN}$file_count${NC}"
}

# Check if specific files are locked
check_files() {
    local files="$1"

    init_lock_file
    local locks=$(read_locks)

    local files_array=$(echo "$files" | jq -R 'split(",") | map(gsub("^\\s+|\\s+$";""))')

    echo -e "${BOLD}Checking file locks...${NC}\n"

    echo "$files_array" | jq -r '.[]' | while read -r file; do
        local lock_info=$(echo "$locks" | jq -r --arg file "$file" '
            .locks[] | select(.files | contains([$file])) |
            "\(.developer) (Lock: \(.id), Issue: \(.issue))"
        ')

        if [ -n "$lock_info" ]; then
            echo -e "${RED}✗${NC} $file"
            echo -e "  Locked by: $lock_info"
        else
            echo -e "${GREEN}✓${NC} $file - Available"
        fi
    done
}

# Get lock info
get_lock_info() {
    local lock_id="$1"

    init_lock_file
    local locks=$(read_locks)

    local lock_data=$(echo "$locks" | jq --arg id "$lock_id" '.locks[] | select(.id == $id)')

    if [ -z "$lock_data" ]; then
        echo -e "${RED}Error:${NC} Lock ID not found: $lock_id"
        exit 1
    fi

    echo -e "${BOLD}Lock Information${NC}\n"
    echo "$lock_data" | jq -r '
        "Lock ID: \(.id)\n" +
        "Developer: \(.developer)\n" +
        "Issue: \(.issue)\n" +
        (if .issueNumber != "" then "Issue Number: #\(.issueNumber)\n" else "" end) +
        (if .branch != "" then "Branch: \(.branch)\n" else "" end) +
        "Status: \(.status)\n" +
        "Locked At: \(.lockedAt)\n" +
        (if .estimatedCompletion != "" then "Estimated Completion: \(.estimatedCompletion)\n" else "" end) +
        "Files (\(.files | length)):\n" +
        (.files | map("  ✓ \(.)") | join("\n"))
    '
}

# Check for upstream conflicts
check_conflicts() {
    init_lock_file
    local locks=$(read_locks)

    echo -e "${BOLD}Checking for upstream conflicts...${NC}\n"

    # Fetch latest from origin
    git fetch origin main --quiet 2>&1 || true

    # Get all locked files
    local locked_files=$(echo "$locks" | jq -r '.locks[].files[]' | sort -u)

    if [ -z "$locked_files" ]; then
        echo -e "${GREEN}✓ No locked files to check${NC}"
        return
    fi

    local conflicts_found=false

    while IFS= read -r file; do
        # Check if file was modified upstream
        local changes=$(git diff --name-only HEAD origin/main -- "$file" 2>/dev/null || true)

        if [ -n "$changes" ]; then
            conflicts_found=true
            local lock_info=$(echo "$locks" | jq -r --arg file "$file" '
                .locks[] | select(.files | contains([$file])) |
                "\(.developer) (Lock: \(.id))"
            ' | head -1)

            echo -e "${YELLOW}⚠${NC} $file"
            echo -e "  Modified upstream"
            echo -e "  Currently locked by: $lock_info"
        fi
    done <<< "$locked_files"

    if [ "$conflicts_found" = false ]; then
        echo -e "${GREEN}✓ No conflicts detected${NC}"
    else
        echo
        echo -e "${YELLOW}Warning: Some locked files have been modified upstream.${NC}"
        echo -e "Coordinate with lock owners before merging."
    fi
}

# Install git hooks
install_hooks() {
    local git_dir="$PROJECT_ROOT/.git"

    if [ ! -d "$git_dir" ]; then
        echo -e "${RED}Error:${NC} Not a git repository"
        exit 1
    fi

    # Pre-commit hook
    cat > "$git_dir/hooks/pre-commit" << 'HOOK_EOF'
#!/bin/bash

# Check for locked files

LOCK_FILE=".dev-locks.json"
SCRIPT_DIR="$(git rev-parse --show-toplevel)"

if [ ! -f "$SCRIPT_DIR/$LOCK_FILE" ]; then
    exit 0
fi

# Get files being committed
staged_files=$(git diff --cached --name-only)

if [ -z "$staged_files" ]; then
    exit 0
fi

# Check if any staged files are locked by others
current_user=$(git config user.name)
conflicts=false

while IFS= read -r file; do
    lock_owner=$(jq -r --arg file "$file" --arg user "$current_user" '
        .locks[] | select(.files | contains([$file])) |
        select(.developer != $user) | .developer
    ' "$SCRIPT_DIR/$LOCK_FILE" | head -1)

    if [ -n "$lock_owner" ]; then
        echo "⚠ Warning: $file is locked by $lock_owner"
        conflicts=true
    fi
done <<< "$staged_files"

if [ "$conflicts" = true ]; then
    echo
    echo "Some files you're committing are locked by other developers."
    read -p "Continue anyway? (y/N) " -n 1 -r
    echo
    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
        exit 1
    fi
fi

exit 0
HOOK_EOF

    chmod +x "$git_dir/hooks/pre-commit"
    echo -e "${GREEN}✓ Installed pre-commit hook${NC}"
}

# Show help
show_help() {
    cat << EOF
${BOLD}Development Workflow Manager${NC}

${BOLD}USAGE:${NC}
    $0 <command> [options]

${BOLD}COMMANDS:${NC}
    ${CYAN}lock${NC}              Lock files for development
    ${CYAN}unlock${NC}            Release lock on files
    ${CYAN}status${NC}            Show all active locks
    ${CYAN}check-files${NC}       Check if specific files are locked
    ${CYAN}info${NC}              Get detailed information about a lock
    ${CYAN}update${NC}            Update lock status
    ${CYAN}add-files${NC}         Add more files to existing lock
    ${CYAN}check-conflicts${NC}   Check for upstream conflicts with locked files
    ${CYAN}install-hooks${NC}     Install git hooks for lock checking
    ${CYAN}init${NC}              Initialize lock file

${BOLD}LOCK OPTIONS:${NC}
    --developer NAME       Developer name (required)
    --issue "Issue #X"     Issue being worked on (required)
    --files "f1,f2,f3"     Comma-separated file paths (required)
    --branch NAME          Git branch name (optional)
    --estimated TIME       Estimated completion time (optional)

${BOLD}UNLOCK OPTIONS:${NC}
    --lock-id ID          Lock ID to unlock
    --developer NAME      Developer name (with --all)
    --all                 Unlock all locks for developer

${BOLD}STATUS OPTIONS:${NC}
    --developer NAME      Show only locks for specific developer
    --stale               Show only stale locks (>24 hours)

${BOLD}CHECK-FILES OPTIONS:${NC}
    --files "f1,f2,f3"    Comma-separated file paths to check

${BOLD}INFO OPTIONS:${NC}
    --lock-id ID          Lock ID to get info about

${BOLD}UPDATE OPTIONS:${NC}
    --lock-id ID          Lock ID to update
    --status "message"    New status message

${BOLD}ADD-FILES OPTIONS:${NC}
    --lock-id ID          Lock ID to add files to
    --files "f1,f2,f3"    Comma-separated file paths to add

${BOLD}EXAMPLES:${NC}
    # Lock files for work
    $0 lock --developer "John Doe" --issue "Issue #12" --files "src/app.ts,src/utils.ts"

    # Check status
    $0 status

    # Unlock when done
    $0 unlock --lock-id lock-001

    # Check if files are available
    $0 check-files --files "src/app.ts,src/config.ts"

EOF
}

# Main script logic
main() {
    cd "$PROJECT_ROOT"

    local command="${1:-}"
    shift || true

    case "$command" in
        lock)
            local developer=""
            local issue=""
            local files=""
            local branch=""
            local estimated=""

            while [[ $# -gt 0 ]]; do
                case "$1" in
                    --developer) developer="$2"; shift 2 ;;
                    --issue) issue="$2"; shift 2 ;;
                    --files) files="$2"; shift 2 ;;
                    --branch) branch="$2"; shift 2 ;;
                    --estimated) estimated="$2"; shift 2 ;;
                    *) echo "Unknown option: $1"; exit 1 ;;
                esac
            done

            lock_files "$developer" "$issue" "$files" "$branch" "$estimated"
            ;;

        unlock)
            local lock_id=""
            local developer=""
            local all="false"

            while [[ $# -gt 0 ]]; do
                case "$1" in
                    --lock-id) lock_id="$2"; shift 2 ;;
                    --developer) developer="$2"; shift 2 ;;
                    --all) all="true"; shift ;;
                    *) echo "Unknown option: $1"; exit 1 ;;
                esac
            done

            unlock_files "$lock_id" "$developer" "$all"
            ;;

        status)
            local developer=""
            local show_stale="false"

            while [[ $# -gt 0 ]]; do
                case "$1" in
                    --developer) developer="$2"; shift 2 ;;
                    --stale) show_stale="true"; shift ;;
                    *) echo "Unknown option: $1"; exit 1 ;;
                esac
            done

            show_status "$developer" "$show_stale"
            ;;

        check-files)
            local files=""

            while [[ $# -gt 0 ]]; do
                case "$1" in
                    --files) files="$2"; shift 2 ;;
                    *) echo "Unknown option: $1"; exit 1 ;;
                esac
            done

            check_files "$files"
            ;;

        info)
            local lock_id=""

            while [[ $# -gt 0 ]]; do
                case "$1" in
                    --lock-id) lock_id="$2"; shift 2 ;;
                    *) echo "Unknown option: $1"; exit 1 ;;
                esac
            done

            get_lock_info "$lock_id"
            ;;

        update)
            local lock_id=""
            local status=""

            while [[ $# -gt 0 ]]; do
                case "$1" in
                    --lock-id) lock_id="$2"; shift 2 ;;
                    --status) status="$2"; shift 2 ;;
                    *) echo "Unknown option: $1"; exit 1 ;;
                esac
            done

            update_lock "$lock_id" "$status"
            ;;

        add-files)
            local lock_id=""
            local files=""

            while [[ $# -gt 0 ]]; do
                case "$1" in
                    --lock-id) lock_id="$2"; shift 2 ;;
                    --files) files="$2"; shift 2 ;;
                    *) echo "Unknown option: $1"; exit 1 ;;
                esac
            done

            add_files_to_lock "$lock_id" "$files"
            ;;

        check-conflicts)
            check_conflicts
            ;;

        install-hooks)
            install_hooks
            ;;

        init)
            init_lock_file
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

# Check dependencies
if ! command -v jq &> /dev/null; then
    echo -e "${RED}Error:${NC} jq is required but not installed"
    echo "Install with: sudo apt-get install jq"
    exit 1
fi

main "$@"
