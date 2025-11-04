# Setting Up Plugin Git When Root Already Has Git

## The Problem

Your WordPress root (`jambotours`) already has a git repository. You can't have a nested git repository inside another git repository without special handling.

## Solution Options

### ✅ Option 1: Separate Plugin Repository (RECOMMENDED)

Create the plugin repository in a **separate location**, then you can sync it back.

#### Step 1: Create Separate Directory

```bash
# Create a folder outside your WordPress installation
cd C:\Users\YourName\Desktop
mkdir simple-scheduled-maintenance-plugin
cd simple-scheduled-maintenance-plugin
```

#### Step 2: Copy Plugin Files

**Windows (PowerShell):**
```powershell
Copy-Item -Path "F:\laragon\www\jambotours\wp-content\plugins\simple-scheduled-maintenance\*" -Destination "." -Recurse
```

**Windows (Command Prompt):**
```cmd
xcopy "F:\laragon\www\jambotours\wp-content\plugins\simple-scheduled-maintenance\*" "." /E /I /Y
```

#### Step 3: Initialize Git

```bash
git init
git add .
git commit -m "Initial release v2.3"
```

#### Step 4: Connect to GitHub

```bash
# Create repository on GitHub first, then:
git remote add origin https://github.com/YOUR_USERNAME/simple-scheduled-maintenance.git
git branch -M main
git push -u origin main
```

#### Step 5: Sync Back to WordPress (Optional)

After uploading to GitHub, you can:
1. Keep working in the separate folder and copy changes back
2. Or use the GitHub version as the source of truth

---

### Option 2: Add to .gitignore in Root

If you want to keep the plugin in your main repo but also publish it separately:

#### Step 1: Add Plugin to Root .gitignore

In your root `jambotours/.gitignore`, add:
```
wp-content/plugins/simple-scheduled-maintenance/
```

This prevents the plugin from being tracked by the root repository.

#### Step 2: Initialize Plugin Git

```bash
cd F:\laragon\www\jambotours\wp-content\plugins\simple-scheduled-maintenance
git init
git add .
git commit -m "Initial release v2.3"
git remote add origin https://github.com/YOUR_USERNAME/simple-scheduled-maintenance.git
git branch -M main
git push -u origin main
```

---

### Option 3: Use Git Submodule (Advanced)

If you want the plugin to be part of your main repository but also have its own GitHub repo:

#### Step 1: Remove from Main Git

```bash
cd F:\laragon\www\jambotours
git rm -r --cached wp-content/plugins/simple-scheduled-maintenance
git commit -m "Remove plugin from main repo (will add as submodule)"
```

#### Step 2: Create Plugin Repository on GitHub

First upload the plugin to GitHub (use Option 1 steps).

#### Step 3: Add as Submodule

```bash
cd F:\laragon\www\jambotours
git submodule add https://github.com/YOUR_USERNAME/simple-scheduled-maintenance.git wp-content/plugins/simple-scheduled-maintenance
git commit -m "Add simple-scheduled-maintenance as submodule"
```

---

## Quick Decision Guide

- **Want plugin on GitHub only?** → Use Option 1 (Separate Repository)
- **Want plugin in both repos independently?** → Use Option 2 (Add to .gitignore)
- **Want plugin linked to main repo?** → Use Option 3 (Submodule)

## Recommended: Option 1

**Why?**
- ✅ Clean separation
- ✅ No conflicts with main repo
- ✅ Easy to maintain
- ✅ Standard practice for WordPress plugins

**Workflow:**
1. Work on plugin in separate folder
2. Commit and push to GitHub
3. When ready, copy to WordPress installation
4. Or use Composer/Git to install from GitHub

## Example Workflow (Option 1)

```bash
# Development
cd C:\Users\YourName\simple-scheduled-maintenance-plugin
# Make changes...
git add .
git commit -m "Update feature"
git push origin main

# Deploy to WordPress
xcopy "." "F:\laragon\www\jambotours\wp-content\plugins\simple-scheduled-maintenance" /E /I /Y
```

## Troubleshooting

### "fatal: not a git repository"

You're in the wrong directory. Make sure you're in the plugin folder or your separate plugin folder.

### "fatal: remote origin already exists"

You already added the remote. Remove it first:
```bash
git remote remove origin
git remote add origin https://github.com/YOUR_USERNAME/simple-scheduled-maintenance.git
```

### "Permission denied" when pushing

You need to authenticate with GitHub:
- Use Personal Access Token instead of password
- Or use SSH keys
- Or use GitHub Desktop app

