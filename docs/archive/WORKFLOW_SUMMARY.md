# Parallel Development Workflow - Summary

## 🎯 Purpose

This workflow system prevents merge conflicts and coordinates parallel development across the team.

## 📦 What Was Created

### 1. Documentation
- **[DEVELOPMENT_WORKFLOW.md](./DEVELOPMENT_WORKFLOW.md)** - Complete workflow guide (4000+ words)
- **[PARALLEL_DEV_QUICKSTART.md](./PARALLEL_DEV_QUICKSTART.md)** - Quick reference guide
- **[WORKFLOW_SUMMARY.md](./WORKFLOW_SUMMARY.md)** - This file

### 2. Tools & Scripts
- **`scripts/dev-workflow.sh`** - File locking and coordination tool
- **`scripts/assign-issue.sh`** - Issue assignment helper
- **`.dev-locks.json`** - Lock tracking file (version controlled)

### 3. Updated Files
- **[README.md](./README.md)** - Added workflow section to Contributing
- **[.gitignore](./.gitignore)** - Added note about tracking locks

## 🚀 Quick Start

### For New Developers

```bash
# 1. See available issues
./scripts/assign-issue.sh list

# 2. Pick and assign issue to yourself
./scripts/assign-issue.sh assign --issue 12 --developer "Your Name"

# 3. Create feature branch
git checkout -b feature/issue-12-save-button

# 4. Lock files you'll work on
./scripts/dev-workflow.sh lock \
  --developer "Your Name" \
  --issue "Issue #12" \
  --branch "feature/issue-12-save-button" \
  --files "www/src/app/pages/editor/editor.component.ts,www/src/app/pages/editor/website.service.ts"

# 5. Do your work...

# 6. Commit and push
git add . && git commit -m "Add save button"
git push origin feature/issue-12-save-button

# 7. Create PR
gh pr create --title "Add Save button" --body "Fixes #12"

# 8. Unlock files
./scripts/dev-workflow.sh unlock --lock-id <your-lock-id>
```

## 🔧 Core Commands

### Issue Management
```bash
# List available issues
./scripts/assign-issue.sh list

# Show issue details
./scripts/assign-issue.sh show --issue 12

# Assign to yourself
./scripts/assign-issue.sh assign --issue 12 --developer "Your Name"

# See your issues
./scripts/assign-issue.sh my-issues --developer "Your Name"
```

### File Locking
```bash
# Check current locks
./scripts/dev-workflow.sh status

# Lock files
./scripts/dev-workflow.sh lock \
  --developer "Name" \
  --issue "Issue #12" \
  --files "file1.ts,file2.php"

# Check if files are available
./scripts/dev-workflow.sh check-files --files "file1.ts,file2.php"

# Update status
./scripts/dev-workflow.sh update --lock-id lock-001 --status "70% complete"

# Unlock
./scripts/dev-workflow.sh unlock --lock-id lock-001

# Check for conflicts
./scripts/dev-workflow.sh check-conflicts
```

## 🗂️ Recommended Work Zones

To minimize conflicts, organize work by module:

| Developer | Zone | Files | Issues |
|-----------|------|-------|--------|
| **Dev A** | Authentication | `api/src/App/Routes/Auth*.php`<br>`www/src/app/services/auth.service.ts` | #1-6, #19, #26 |
| **Dev B** | Content/Editor | `www/src/app/pages/editor/*`<br>`api/src/App/Routes/Websites*.php` | #7-14 |
| **Dev C** | Website List | `www/src/app/pages/my-websites/*`<br>`api/src/App/Routes/Websites*.php` | #15-18 |
| **Dev D** | Infrastructure | `docker-compose.yaml`<br>`docs/vm-setup/*` | VM Tasks 1-25 |

**Can work in parallel without conflicts:**
- Dev A + Dev B + Dev D (different file zones)
- Dev A + Dev D
- Dev B + Dev D

**Need coordination:**
- Dev B + Dev C (both touch Websites routes)

## 📋 Best Practices

### ✅ Do
- Check locks before starting work
- Lock files early
- Update status regularly
- Unlock promptly when done
- Communicate in team channel
- Small, focused PRs

### ❌ Don't
- Ignore lock warnings
- Modify locked files without coordination
- Leave stale locks
- Force push to shared branches
- Commit directly to main

## 🎯 Example Parallel Development Scenario

### Scenario: 3 Developers, Week 1

**Monday Morning - Planning:**

```bash
# Dev A (Alice) - Authentication
./scripts/assign-issue.sh assign --issue 1 --developer "Alice"
./scripts/dev-workflow.sh lock --developer "Alice" --issue "Issue #1" \
  --files "api/migrations/001_create_users_table.sql"

# Dev B (Bob) - Editor
./scripts/assign-issue.sh assign --issue 10 --developer "Bob"
./scripts/dev-workflow.sh lock --developer "Bob" --issue "Issue #10" \
  --files "www/src/app/pages/editor/website.service.ts"

# Dev C (Carol) - DevOps
# Working on VM setup - no code conflicts

# Check status
./scripts/dev-workflow.sh status
```

Output shows:
```
Lock ID: lock-001
Developer: Alice
Issue: Issue #1: Create users table
Files: api/migrations/001_create_users_table.sql

Lock ID: lock-002
Developer: Bob
Issue: Issue #10: Website serialization
Files: www/src/app/pages/editor/website.service.ts

Total Active Locks: 2
```

**Tuesday - Bob needs a file Alice is working on:**

```bash
# Bob checks who has it
./scripts/dev-workflow.sh info --lock-id lock-001

# Bob messages Alice on Slack
# They coordinate - Alice is almost done

# Alice finishes and unlocks
./scripts/dev-workflow.sh unlock --lock-id lock-001

# Bob locks it
./scripts/dev-workflow.sh lock --developer "Bob" --issue "Issue #10" \
  --files "api/src/App/DTO/UsersResponse.php"
```

**Friday - End of Sprint:**

```bash
# Everyone checks their locks
./scripts/dev-workflow.sh status --developer "Alice"
./scripts/dev-workflow.sh status --developer "Bob"

# All create PRs and unlock
./scripts/dev-workflow.sh unlock --developer "Alice" --all
./scripts/dev-workflow.sh unlock --developer "Bob" --all
```

## 🛠️ Script Features

### dev-workflow.sh Features
- Lock/unlock file management
- Real-time status display
- Conflict detection
- Git integration
- Stale lock detection
- Pre-commit hooks
- Colored output

### assign-issue.sh Features
- List available issues
- Show issue details
- Assign/unassign issues
- Track developer assignments
- Integration with issues.md

## 📊 Lock File Format

`.dev-locks.json`:
```json
{
  "locks": [
    {
      "id": "lock-1731589200-abc123",
      "developer": "John Doe",
      "issue": "Issue #12: Add Save button",
      "issueNumber": 12,
      "files": [
        "www/src/app/pages/editor/editor.component.ts",
        "www/src/app/pages/editor/website.service.ts"
      ],
      "status": "In progress: Implementing save button UI",
      "lockedAt": "2025-11-14T10:30:00Z",
      "estimatedCompletion": "2025-11-14T16:00:00Z",
      "branch": "feature/issue-12-save-button"
    }
  ],
  "version": "1.0.0",
  "lastUpdated": "2025-11-14T10:30:00Z"
}
```

## 🔄 Integration with Existing Workflow

### With Git
- Pre-commit hooks check locked files
- Branch names tracked in locks
- Conflict detection with upstream changes

### With GitHub
- Issue numbers tracked
- PR creation integrated
- Webhook compatibility maintained

### With Issues.md
- Issue assignments tracked
- Status updates synchronized
- Priority and phase information preserved

## 📈 Benefits

1. **Prevents Conflicts** - Know who's working on what before conflicts happen
2. **Better Coordination** - Team visibility into active work
3. **Faster Reviews** - PRs don't have unexpected conflicts
4. **Clear Ownership** - No ambiguity about file responsibility
5. **Structured Workflow** - Consistent process for everyone
6. **Easy Onboarding** - New developers can see what's available

## 🎓 Training New Team Members

### 5-Minute Intro
1. Read [PARALLEL_DEV_QUICKSTART.md](./PARALLEL_DEV_QUICKSTART.md)
2. Run `./scripts/assign-issue.sh list`
3. Run `./scripts/dev-workflow.sh status`
4. Pick an issue and follow the quick start steps

### First Task
1. Assign a small issue (#24 or #25 are good starter issues)
2. Lock files
3. Make changes
4. Create PR
5. Unlock

### Full Training
1. Read [DEVELOPMENT_WORKFLOW.md](./DEVELOPMENT_WORKFLOW.md) (15 minutes)
2. Practice workflow with a real issue
3. Coordinate with another developer on shared files
4. Install git hooks

## 🔧 Troubleshooting

### "Lock file corrupted"
```bash
cp .dev-locks.json .dev-locks.json.backup
./scripts/dev-workflow.sh init
```

### "Can't find my lock ID"
```bash
./scripts/dev-workflow.sh status --developer "Your Name"
```

### "Need to work on locked files urgently"
1. Contact the developer
2. Check if lock is stale (`--stale` flag)
3. Coordinate with team lead if needed

### "Git hook blocking commit"
Either coordinate with lock owner or override if discussed.

## 📚 Full Documentation Links

- **Quick Start**: [PARALLEL_DEV_QUICKSTART.md](./PARALLEL_DEV_QUICKSTART.md)
- **Full Workflow**: [DEVELOPMENT_WORKFLOW.md](./DEVELOPMENT_WORKFLOW.md)
- **Issues List**: [issues.md](./issues.md)
- **VM Setup**: [docs/vm-setup/TODO.md](./docs/vm-setup/TODO.md)
- **Project README**: [README.md](./README.md)

## 🎉 Getting Started NOW

```bash
# Check what's available
./scripts/assign-issue.sh list

# Pick an issue that interests you
./scripts/assign-issue.sh show --issue <number>

# Assign it
./scripts/assign-issue.sh assign --issue <number> --developer "Your Name"

# Start working!
git checkout -b feature/issue-<number>-<description>
./scripts/dev-workflow.sh lock --developer "Your Name" --issue "Issue #<number>" --files "..."
```

---

**Created**: 2025-11-14
**Version**: 1.0.0
**Maintainer**: Development Team

**Questions?** Check the full documentation or ask in the team channel.
