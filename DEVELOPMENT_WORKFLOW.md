# Development Workflow - Parallel Development Coordination

This document outlines the workflow for parallel development to prevent conflicts and ensure smooth collaboration.

## 📋 Overview

When multiple developers work in parallel, this workflow helps coordinate:
- **File ownership** - Who is actively working on which files
- **Task assignment** - Clear ownership of features/issues
- **Conflict prevention** - Avoid merge conflicts before they happen
- **Communication** - Visibility into what everyone is working on

---

## 🚦 Workflow Steps

### 1. Before Starting Work

#### Check Current Locks
```bash
# View what files are currently being worked on
./scripts/dev-workflow.sh status

# Check if specific files are locked
./scripts/dev-workflow.sh check-files src/app/pages/editor/editor.component.ts api/src/App/Routes/WebsitesRoute.php
```

#### Claim Your Work Area
```bash
# Lock files you'll be working on
./scripts/dev-workflow.sh lock \
  --developer "Your Name" \
  --issue "Issue #12: Add Save button" \
  --files "www/src/app/pages/editor/editor.component.ts,www/src/app/pages/editor/website.service.ts"

# Or use the interactive mode
./scripts/dev-workflow.sh lock --interactive
```

This creates an entry in `.dev-locks.json` that others can see.

---

### 2. During Development

#### Update Your Progress
```bash
# Update lock status
./scripts/dev-workflow.sh update \
  --lock-id <your-lock-id> \
  --status "In progress: Implementing save button UI"

# Add more files if needed
./scripts/dev-workflow.sh add-files \
  --lock-id <your-lock-id> \
  --files "www/src/app/shared/components/save-button.component.ts"
```

#### Check for Conflicts
```bash
# Before pulling latest changes
./scripts/dev-workflow.sh check-conflicts

# This warns if locked files have been modified upstream
```

---

### 3. After Completing Work

#### Release Your Locks
```bash
# When done with work
./scripts/dev-workflow.sh unlock --lock-id <your-lock-id>

# Or unlock all your locks
./scripts/dev-workflow.sh unlock --developer "Your Name" --all
```

#### Communicate Completion
```bash
# Mark issue as complete in issues.md
# Update others on Slack/Teams that files are available
```

---

## 📁 File Lock System

### Lock File Structure

Location: `.dev-locks.json`

```json
{
  "locks": [
    {
      "id": "lock-001",
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
  ]
}
```

### Lock Rules

1. **Respect Locks**: Don't modify files locked by others without coordination
2. **Update Regularly**: Keep your lock status current
3. **Release Promptly**: Unlock files as soon as you're done
4. **Communicate**: If you need to work on locked files, talk to the owner
5. **Stale Locks**: Locks older than 24 hours should be reviewed

---

## 🎯 Issue-Based Development

### Issue Assignment in issues.md

Before starting work on an issue:

1. **Assign Yourself**: Add your name to the issue
2. **Update Status**: Mark as "In Progress"
3. **Lock Files**: Use the workflow script to lock related files

Example in `issues.md`:
```markdown
### Issue #12: Add Save button to editor

**Assigned to**: John Doe
**Status**: In Progress
**Branch**: feature/issue-12-save-button
**Started**: 2025-11-14
**Files Locked**: See .dev-locks.json (lock-001)

**Acceptance Criteria**:
- [ ] Add "Save" button to editor toolbar UI
- [ ] Implement saveWebsite() method
...
```

---

## 🔄 Branching Strategy

### Branch Naming Convention

```
feature/issue-<number>-<short-description>
bugfix/issue-<number>-<short-description>
hotfix/<description>
refactor/<description>
docs/<description>
```

Examples:
- `feature/issue-12-save-button`
- `bugfix/issue-26-signin-endpoint`
- `refactor/website-serialization`

### Working with Branches

```bash
# Create feature branch
git checkout -b feature/issue-12-save-button

# Lock files for this branch
./scripts/dev-workflow.sh lock \
  --developer "John Doe" \
  --issue "Issue #12" \
  --branch "feature/issue-12-save-button" \
  --files "www/src/app/pages/editor/editor.component.ts"

# Work on your feature...

# When done, create PR
gh pr create --title "Add Save button to editor" --body "Fixes #12"

# Release lock
./scripts/dev-workflow.sh unlock --branch "feature/issue-12-save-button"
```

---

## 🗂️ File Zones - Organized by Module

To minimize conflicts, organize work by module/zone:

### Frontend Zones

```
www/
├── src/app/
│   ├── pages/
│   │   ├── editor/           # Zone: Editor Module
│   │   ├── website-wizard/   # Zone: Wizard Module
│   │   └── my-websites/      # Zone: Website List Module
│   ├── services/             # Zone: Shared Services
│   │   ├── auth.service.ts   # Zone: Authentication
│   │   └── api.service.ts    # Zone: API Client
│   └── shared/               # Zone: Shared Components
```

### Backend Zones

```
api/
├── src/App/
│   ├── Routes/
│   │   ├── AuthRoute.php     # Zone: Authentication Routes
│   │   ├── WebsitesRoute.php # Zone: Website Routes
│   │   └── UsersRoute.php    # Zone: User Routes
│   ├── DTO/                  # Zone: Data Transfer Objects
│   ├── Contracts/            # Zone: Interfaces
│   └── Services/             # Zone: Business Logic
└── Framework/                # Zone: Core Framework
```

### Recommended Zone Assignment

When multiple devs work in parallel:

| Developer | Zone | Issues |
|-----------|------|--------|
| Dev A | Authentication | Issues #1-6 |
| Dev B | Content Persistence | Issues #7-13 |
| Dev C | Website Management | Issues #15-18 |
| Dev D | Infrastructure/DevOps | VM Setup, Deployment |

---

## 🚨 Conflict Resolution

### If You Need to Work on Locked Files

1. **Check Lock Details**:
   ```bash
   ./scripts/dev-workflow.sh info --lock-id <lock-id>
   ```

2. **Contact the Developer**:
   - Slack/Teams message
   - Email
   - Comment on the GitHub issue

3. **Coordinate**:
   - Wait for them to finish
   - Split the work differently
   - Pair program together
   - One person temporarily unlocks specific files

4. **Emergency Override** (use sparingly):
   ```bash
   # Only if developer is unavailable and lock is stale
   ./scripts/dev-workflow.sh override --lock-id <lock-id> --reason "Developer out sick"
   ```

### If Files Are Modified While Locked

```bash
# Check for upstream changes
git fetch origin main
./scripts/dev-workflow.sh check-conflicts

# If conflicts detected, coordinate with other developer
# Either:
# A) They revert their changes
# B) You merge and resolve conflicts
# C) You coordinate to split the work
```

---

## 📊 Lock Status Dashboard

### View All Locks

```bash
# Show all current locks
./scripts/dev-workflow.sh status

# Show locks by developer
./scripts/dev-workflow.sh status --developer "John Doe"

# Show stale locks (>24 hours)
./scripts/dev-workflow.sh status --stale
```

### Example Output

```
╔═══════════════════════════════════════════════════════════════════╗
║                    Development Locks Status                       ║
╚═══════════════════════════════════════════════════════════════════╝

Lock ID: lock-001
Developer: John Doe
Issue: Issue #12: Add Save button
Branch: feature/issue-12-save-button
Status: In progress (3 hours ago)
Files (2):
  ✓ www/src/app/pages/editor/editor.component.ts
  ✓ www/src/app/pages/editor/website.service.ts

Lock ID: lock-002
Developer: Jane Smith
Issue: Issue #8: Create GET /websites/:id route
Branch: feature/issue-8-get-website
Status: Testing (1 hour ago)
Files (3):
  ✓ api/src/App/Routes/WebsitesRoute.php
  ✓ api/src/App/DTO/GetWebsiteRequest.php
  ✓ api/src/App/DTO/GetWebsiteResponse.php

═══════════════════════════════════════════════════════════════════
Total Active Locks: 2
Total Locked Files: 5
```

---

## 🛠️ Git Integration

### Pre-commit Hook (Recommended)

Install pre-commit hook to check for lock conflicts:

```bash
# Install hook
./scripts/dev-workflow.sh install-hooks

# This creates .git/hooks/pre-commit that:
# 1. Checks if you're modifying locked files
# 2. Warns if files aren't locked by you
# 3. Prevents accidental commits to others' work
```

### Pre-push Hook

```bash
# Reminds you to unlock files before pushing
# Checks if your locks are still active
# Updates lock status to "Ready for Review"
```

---

## 📞 Communication Channels

### When to Communicate

- **Before locking**: Let team know you're starting work on an issue
- **During work**: Update status if timeline changes
- **Need help**: Reach out if blocked
- **Conflicts**: Coordinate immediately if you need locked files
- **After completion**: Announce when work is done and locks released

### Communication Tools

- **Slack/Teams**: Real-time coordination
- **GitHub Issues**: Formal issue tracking and comments
- **Pull Requests**: Code review discussions
- **Daily Standups**: Share progress and blockers
- **Lock Status**: Check `.dev-locks.json` regularly

---

## 📈 Best Practices

### ✅ Do's

- **Lock early**: Claim files before starting work
- **Communicate proactively**: Keep team informed
- **Unlock promptly**: Release locks when done
- **Update status**: Keep lock info current
- **Review locks**: Check status before starting new work
- **Respect zones**: Work in your assigned module
- **Small PRs**: Keep changes focused and reviewable
- **Test thoroughly**: Ensure your changes don't break others' work

### ❌ Don'ts

- **Don't ignore locks**: Respect other developers' work
- **Don't hoard locks**: Don't lock files you're not actively working on
- **Don't leave stale locks**: Update or release if not working on it
- **Don't modify locked files**: Without coordinating first
- **Don't commit to main**: Always use feature branches
- **Don't force push**: To shared branches
- **Don't override locks**: Without good reason and communication

---

## 🔧 Troubleshooting

### Lock File Corrupted

```bash
# Backup current locks
cp .dev-locks.json .dev-locks.json.backup

# Regenerate lock file
./scripts/dev-workflow.sh init

# Re-add active locks manually if needed
```

### Lost Lock Information

```bash
# Check git history for lock changes
git log -p .dev-locks.json

# Restore from specific commit
git checkout <commit> -- .dev-locks.json
```

### Merge Conflicts in .dev-locks.json

```bash
# Accept both changes and manually merge
# Locks from different developers should not conflict

# Use tool to merge
./scripts/dev-workflow.sh merge-locks
```

---

## 📚 Related Documentation

- [issues.md](./issues.md) - All development issues
- [CONTRIBUTING.md](./CONTRIBUTING.md) - Contribution guidelines (if exists)
- [README.md](./README.md) - Project overview
- [docs/vm-setup/TODO.md](./docs/vm-setup/TODO.md) - Infrastructure tasks

---

## 🎓 Quick Start Example

```bash
# 1. Check what's available
./scripts/dev-workflow.sh status

# 2. Choose an issue from issues.md
# Issue #12: Add Save button to editor

# 3. Create branch
git checkout -b feature/issue-12-save-button

# 4. Lock files you'll work on
./scripts/dev-workflow.sh lock \
  --developer "Your Name" \
  --issue "Issue #12" \
  --files "www/src/app/pages/editor/editor.component.ts,www/src/app/pages/editor/website.service.ts"

# 5. Do your work
# ... edit files ...

# 6. Commit and push
git add .
git commit -m "Add save button to editor toolbar"
git push origin feature/issue-12-save-button

# 7. Create PR
gh pr create --title "Add Save button to editor" --body "Fixes #12"

# 8. Release lock
./scripts/dev-workflow.sh unlock --branch "feature/issue-12-save-button"
```

---

**Last Updated**: 2025-11-14
**Version**: 1.0.0
**Maintainer**: Development Team
