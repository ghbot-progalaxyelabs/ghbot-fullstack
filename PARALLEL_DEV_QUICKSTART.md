# Parallel Development Quick Start Guide

This is a quick reference for coordinating parallel development work.

## 🚀 Before You Start Working

### 1. Check What's Available

```bash
# See all current locks
./scripts/dev-workflow.sh status

# Check specific files
./scripts/dev-workflow.sh check-files --files "www/src/app/pages/editor/editor.component.ts"
```

### 2. Choose Your Work

Look at [issues.md](./issues.md) and find an unassigned issue in your area:

**Suggested Work Zones:**

| Zone | Files | Recommended Issues |
|------|-------|-------------------|
| **Authentication** | `api/src/App/Routes/Auth*.php`<br>`www/src/app/services/auth.service.ts` | #1-6, #19, #26 |
| **Content Persistence** | `api/src/App/Routes/Websites*.php`<br>`www/src/app/pages/editor/*` | #7-14 |
| **Website Management** | `api/src/App/Routes/Websites*.php`<br>`www/src/app/pages/my-websites/*` | #15-18 |
| **Infrastructure** | `docker-compose.yaml`<br>`docs/vm-setup/*` | VM Setup Tasks |

### 3. Create Your Branch

```bash
git checkout main
git pull origin main
git checkout -b feature/issue-12-save-button
```

### 4. Lock Your Files

```bash
./scripts/dev-workflow.sh lock \
  --developer "Your Name" \
  --issue "Issue #12: Add Save button" \
  --branch "feature/issue-12-save-button" \
  --files "www/src/app/pages/editor/editor.component.ts,www/src/app/pages/editor/website.service.ts"
```

**Save your lock ID!** You'll need it later.

---

## 💻 While Working

### Update Your Progress

```bash
# Update status message
./scripts/dev-workflow.sh update \
  --lock-id lock-1731589200-abc123 \
  --status "Implementing save button UI - 50% complete"
```

### Add More Files If Needed

```bash
./scripts/dev-workflow.sh add-files \
  --lock-id lock-1731589200-abc123 \
  --files "www/src/app/shared/components/save-button.component.ts"
```

### Check for Conflicts Before Pulling

```bash
# Before: git pull origin main
./scripts/dev-workflow.sh check-conflicts
```

---

## ✅ When You're Done

### 1. Commit and Push

```bash
git add .
git commit -m "Add save button to editor toolbar

- Added Save button component
- Implemented saveWebsite() method
- Added keyboard shortcut (Ctrl+S)

Fixes #12"

git push origin feature/issue-12-save-button
```

### 2. Create Pull Request

```bash
gh pr create --title "Add Save button to editor" --body "Fixes #12

## Changes
- Added Save button to editor toolbar
- Implemented website serialization
- Added auto-save indicator

## Testing
- [x] Save button appears in toolbar
- [x] Clicking save persists data
- [x] Keyboard shortcut works (Ctrl+S)
"
```

### 3. Release Your Lock

```bash
./scripts/dev-workflow.sh unlock --lock-id lock-1731589200-abc123
```

---

## 🚨 If You Need Locked Files

### Check Who Has It

```bash
./scripts/dev-workflow.sh info --lock-id lock-1731589200-abc123
```

### Contact the Developer

1. **Slack/Teams**: Send them a message
2. **GitHub**: Comment on their issue/PR
3. **Email**: If urgent

### Options to Resolve

- **Wait**: Let them finish (preferred)
- **Coordinate**: Split the work differently
- **Pair**: Work together on the same files
- **Temporary unlock**: They unlock specific files for you

---

## 📊 Common Commands

```bash
# View all locks
./scripts/dev-workflow.sh status

# View your locks only
./scripts/dev-workflow.sh status --developer "Your Name"

# View stale locks (>24 hours)
./scripts/dev-workflow.sh status --stale

# Check if files are available
./scripts/dev-workflow.sh check-files --files "file1.ts,file2.php"

# Get lock details
./scripts/dev-workflow.sh info --lock-id lock-001

# Install git hooks (warns before committing locked files)
./scripts/dev-workflow.sh install-hooks

# Unlock all your locks
./scripts/dev-workflow.sh unlock --developer "Your Name" --all
```

---

## 📋 Best Practices

### ✅ Do

- **Lock early**: Before starting work
- **Update status**: Keep team informed of progress
- **Unlock promptly**: As soon as you're done
- **Communicate**: Tell team when starting/finishing
- **Small PRs**: Keep changes focused
- **Check conflicts**: Before pulling changes

### ❌ Don't

- **Don't ignore locks**: Always check first
- **Don't hoard**: Only lock files you're actively working on
- **Don't leave stale locks**: Update or release unused locks
- **Don't force push**: To shared branches
- **Don't modify locked files**: Without coordination

---

## 🎯 Recommended Work Distribution

### For 3-4 Developers Working in Parallel

**Developer A - Authentication** (1-2 weeks)
- Issues: #1, #2, #3, #4, #5, #6
- Files: `api/src/App/Routes/Auth*.php`, `www/src/app/services/auth.service.ts`

**Developer B - Content Persistence** (1-2 weeks)
- Issues: #7, #8, #9, #10, #11, #12, #13
- Files: `www/src/app/pages/editor/*`, `api/src/App/Routes/Websites*.php`

**Developer C - Website Management** (1 week)
- Issues: #15, #16, #17, #18
- Files: `www/src/app/pages/my-websites/*`, `api/src/App/Routes/Websites*.php`
- **Note**: Wait for Developer B to finish #8, #9 first

**Developer D - Infrastructure/DevOps** (2-3 hours)
- VM Setup: Tasks 1-25 from `docs/vm-setup/TODO.md`
- Files: `docker-compose.yaml`, Apache configs, deployment scripts

### Parallel Groups

**No conflicts** (can work simultaneously):
- A (Auth) + B (Editor) + D (DevOps)
- A (Auth) + D (DevOps)
- B (Editor) + D (DevOps)

**Potential conflicts** (coordinate):
- B (Editor) + C (Website List) - both touch Websites routes
- Any 2 working on same route files

---

## 🔄 Workflow Example

```bash
# Morning - Start work
./scripts/dev-workflow.sh status                    # Check what's locked
git checkout -b feature/issue-12-save-button        # Create branch
./scripts/dev-workflow.sh lock \                    # Lock files
  --developer "Alice" \
  --issue "Issue #12" \
  --files "www/src/app/pages/editor/editor.component.ts"

# Mid-day - Update progress
./scripts/dev-workflow.sh update \                  # Update status
  --lock-id lock-001 \
  --status "70% complete - testing save functionality"

# Afternoon - Need to pull changes
./scripts/dev-workflow.sh check-conflicts           # Check first
git pull origin main                                # Pull if safe

# End of day - Done!
git add . && git commit -m "Add save button"        # Commit
git push origin feature/issue-12-save-button        # Push
gh pr create --title "Add Save button" --body "..." # PR
./scripts/dev-workflow.sh unlock --lock-id lock-001 # Unlock!
```

---

## 🆘 Troubleshooting

### "Files already locked by someone else"

```bash
# Check who has it
./scripts/dev-workflow.sh info --lock-id <their-lock-id>

# Contact them
# If stale (>24 hours), discuss with team lead
```

### "I forgot my lock ID"

```bash
# Find your locks
./scripts/dev-workflow.sh status --developer "Your Name"
```

### "Git hook blocking my commit"

```bash
# The hook warns you about locked files
# Either:
# 1. Coordinate with lock owner
# 2. Override if you discussed it: Press 'y' when prompted
```

### "Need to work on locked files urgently"

```bash
# 1. Try to contact the developer first
# 2. Check if lock is stale
./scripts/dev-workflow.sh status --stale

# 3. If emergency and can't reach them:
#    Discuss with team lead about override
```

---

## 📚 Full Documentation

- [DEVELOPMENT_WORKFLOW.md](./DEVELOPMENT_WORKFLOW.md) - Complete workflow guide
- [issues.md](./issues.md) - All development issues
- [README.md](./README.md) - Project overview

---

## 💡 Tips

1. **Check locks first thing each morning**
2. **Update your status before lunch and end of day**
3. **Release locks immediately when done**
4. **Use meaningful status messages** - helps others plan
5. **Communicate in Slack when locking/unlocking major files**
6. **Small, focused PRs** merge faster and reduce conflicts
7. **Install git hooks** - prevents accidents

---

**Questions?** Ask in the team channel or check the full workflow documentation.
