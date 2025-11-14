# Development Workflow Scripts

This directory contains helper scripts for parallel development coordination.

## 📦 Available Scripts

### 🚀 Start Work Helper
**`./start-work.sh`**

Interactive script that guides you through starting new work:
1. Enter your name
2. Choose an issue from the list
3. Review issue details
4. Assign issue to yourself
5. Create feature branch
6. Lock files
7. Ready to code!

**Usage:**
```bash
./scripts/start-work.sh
```

---

### ✅ Finish Work Helper
**`./finish-work.sh`**

Interactive script for completing and cleaning up work:
1. Review your changes
2. Push to remote
3. Create pull request
4. Unlock files
5. Update issue status

**Usage:**
```bash
./scripts/finish-work.sh
```

---

### 🔒 Development Workflow Manager
**`./dev-workflow.sh`**

Core file locking and coordination tool.

**Common Commands:**
```bash
# Check status of all locks
./scripts/dev-workflow.sh status

# Lock files
./scripts/dev-workflow.sh lock \
  --developer "Your Name" \
  --issue "Issue #12" \
  --files "file1.ts,file2.php"

# Check if files are available
./scripts/dev-workflow.sh check-files --files "file1.ts,file2.php"

# Update lock status
./scripts/dev-workflow.sh update \
  --lock-id lock-001 \
  --status "70% complete"

# Unlock files
./scripts/dev-workflow.sh unlock --lock-id lock-001

# Check for upstream conflicts
./scripts/dev-workflow.sh check-conflicts

# Install git hooks
./scripts/dev-workflow.sh install-hooks

# Show help
./scripts/dev-workflow.sh help
```

---

### 📋 Issue Assignment Manager
**`./assign-issue.sh`**

Helper for managing issue assignments.

**Common Commands:**
```bash
# List available issues
./scripts/assign-issue.sh list

# List all issues (including assigned)
./scripts/assign-issue.sh list-all

# Show issue details
./scripts/assign-issue.sh show --issue 12

# Assign issue to yourself
./scripts/assign-issue.sh assign \
  --issue 12 \
  --developer "Your Name"

# Show your issues
./scripts/assign-issue.sh my-issues --developer "Your Name"

# Unassign issue
./scripts/assign-issue.sh unassign --issue 12

# Show help
./scripts/assign-issue.sh help
```

---

## 🎯 Quick Workflow

### Starting Work (Easy Mode)
```bash
# Use the interactive helper
./scripts/start-work.sh
```

### Starting Work (Manual Mode)
```bash
# 1. Check available issues
./scripts/assign-issue.sh list

# 2. Assign to yourself
./scripts/assign-issue.sh assign --issue 12 --developer "Your Name"

# 3. Create branch
git checkout -b feature/issue-12-description

# 4. Lock files
./scripts/dev-workflow.sh lock \
  --developer "Your Name" \
  --issue "Issue #12" \
  --files "file1.ts,file2.php"

# 5. Work on your code...
```

### Finishing Work (Easy Mode)
```bash
# Use the interactive helper
./scripts/finish-work.sh
```

### Finishing Work (Manual Mode)
```bash
# 1. Commit and push
git add .
git commit -m "Your message"
git push origin your-branch

# 2. Create PR
gh pr create --title "Your title" --body "Fixes #12"

# 3. Unlock files
./scripts/dev-workflow.sh unlock --lock-id lock-001

# 4. Unassign issue
./scripts/assign-issue.sh unassign --issue 12
```

---

## 🛠️ Script Dependencies

### Required
- `bash` (4.0+)
- `git`
- `jq` (JSON processor)

Install jq:
```bash
# Ubuntu/Debian
sudo apt-get install jq

# macOS
brew install jq
```

### Optional
- `gh` (GitHub CLI) - For automatic PR creation
  ```bash
  # Ubuntu/Debian
  curl -fsSL https://cli.github.com/packages/githubcli-archive-keyring.gpg | sudo dd of=/usr/share/keyrings/githubcli-archive-keyring.gpg
  echo "deb [arch=$(dpkg --print-architecture) signed-by=/usr/share/keyrings/githubcli-archive-keyring.gpg] https://cli.github.com/packages stable main" | sudo tee /etc/apt/sources.list.d/github-cli.list > /dev/null
  sudo apt update
  sudo apt install gh

  # macOS
  brew install gh
  ```

---

## 📁 Files Created/Modified

### Created by Scripts
- **`.dev-locks.json`** - Lock tracking file (keep in git)

### Modified by Scripts
- **`issues.md`** - Issue assignments added
- Git commit history

---

## 🔧 Troubleshooting

### "jq: command not found"
```bash
sudo apt-get install jq
```

### "Permission denied"
```bash
chmod +x scripts/*.sh
```

### "Lock file corrupted"
```bash
./scripts/dev-workflow.sh init
```

### "Can't find lock ID"
```bash
./scripts/dev-workflow.sh status --developer "Your Name"
```

---

## 📚 Documentation

- **Quick Start**: [PARALLEL_DEV_QUICKSTART.md](../PARALLEL_DEV_QUICKSTART.md)
- **Full Workflow**: [DEVELOPMENT_WORKFLOW.md](../DEVELOPMENT_WORKFLOW.md)
- **Summary**: [WORKFLOW_SUMMARY.md](../WORKFLOW_SUMMARY.md)
- **Issues**: [issues.md](../issues.md)

---

## 💡 Tips

1. **Use interactive helpers** - `start-work.sh` and `finish-work.sh` guide you through everything
2. **Check locks first** - Always run `status` before starting work
3. **Update progress** - Let team know your status with `update` command
4. **Install hooks** - Run `./scripts/dev-workflow.sh install-hooks` once
5. **Small PRs** - Lock fewer files and finish faster

---

## 🎓 Examples

### Example 1: Complete Workflow
```bash
# Morning - Start work
./scripts/start-work.sh
# Follow prompts...

# Afternoon - Update status
./scripts/dev-workflow.sh update \
  --lock-id lock-001 \
  --status "Implemented feature, writing tests"

# Evening - Finish
./scripts/finish-work.sh
# Follow prompts...
```

### Example 2: Quick Check
```bash
# See who's working on what
./scripts/dev-workflow.sh status

# Check specific files
./scripts/dev-workflow.sh check-files \
  --files "www/src/app/pages/editor/editor.component.ts"
```

### Example 3: Coordination
```bash
# You need a file someone else has locked
./scripts/dev-workflow.sh info --lock-id lock-001

# Shows:
# Developer: Alice
# Issue: Issue #12
# Status: Almost done, testing
# Files: editor.component.ts

# Contact Alice, wait for her to finish
```

---

**Created**: 2025-11-14
**Version**: 1.0.0
**Maintainer**: Development Team
