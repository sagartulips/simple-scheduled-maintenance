# Quick Start Guide - Upload to GitHub & WordPress.org

## ⚠️ IMPORTANT: Before Uploading

### 1. Remove Debug Code (Required)
Remove all `error_log()` statements before submitting to WordPress.org:
- Search for: `error_log('SSM DEBUG`
- Remove or comment out all debug logging

### 2. Update Plugin Header
Already updated:
- ✅ License changed to GPLv2
- ⚠️ Update `YOUR_USERNAME` in Plugin URI and Author URI

### 3. Update License Field
Already changed from "Proprietary" to "GPLv2 or later" ✅

## 📤 Step-by-Step Upload Process

### ⚠️ IMPORTANT: If Root Already Has Git

If your WordPress root directory already has a git repository, you have 2 options:

#### **Option 1: Separate Plugin Repository (Recommended)**

Create a standalone repository just for the plugin:

```bash
# 1. Create a temporary directory outside your WordPress root
cd C:\Users\YourName\Desktop  # or any location outside jambotours
mkdir simple-scheduled-maintenance-temp
cd simple-scheduled-maintenance-temp

# 2. Copy all plugin files
xcopy "F:\laragon\www\jambotours\wp-content\plugins\simple-scheduled-maintenance\*" "." /E /I

# 3. Initialize git (this won't affect your root git)
git init
git add .
git commit -m "Initial release v2.3"

# 4. Create repository on GitHub first, then:
git remote add origin https://github.com/YOUR_USERNAME/simple-scheduled-maintenance.git
git branch -M main
git push -u origin main
```

#### **Option 2: Use Git Submodule (Advanced)**

If you want to keep the plugin in your main repository as a submodule:

```bash
# From your WordPress root (jambotours)
cd wp-content/plugins

# Remove the plugin from main git (but keep files)
git rm -r --cached simple-scheduled-maintenance

# Create submodule
git submodule add https://github.com/YOUR_USERNAME/simple-scheduled-maintenance.git simple-scheduled-maintenance

# Commit the submodule reference
git commit -m "Add simple-scheduled-maintenance as submodule"
```

#### **Option 3: Extract Plugin to Separate Folder**

```bash
# Copy plugin to a new location
xcopy "F:\laragon\www\jambotours\wp-content\plugins\simple-scheduled-maintenance" "F:\laragon\www\simple-scheduled-maintenance-plugin" /E /I

# Navigate to new location
cd F:\laragon\www\simple-scheduled-maintenance-plugin

# Initialize git
git init
git add .
git commit -m "Initial release v2.3"

# Add GitHub remote
git remote add origin https://github.com/YOUR_USERNAME/simple-scheduled-maintenance.git
git branch -M main
git push -u origin main
```

### Step 1: Create GitHub Repository

1. Go to https://github.com
2. Click "+" → "New repository"
3. Name: `simple-scheduled-maintenance`
4. Description: "WordPress scheduled maintenance plugin with multi-language support"
5. Choose **Public**
6. **DO NOT** initialize with README (you already have files)
7. Click "Create repository"

### Step 2: Upload Files (Choose Option Above)

### Step 3: Submit to WordPress.org

1. **Create Account**: https://wordpress.org/support/register.php
2. **Submit Plugin**: https://wordpress.org/plugins/developers/add/
3. **Provide**:
   - GitHub repository URL
   - Plugin description
   - Your email address

### Step 4: Wait for Review
- Review takes 1-2 weeks
- WordPress.org team will check:
  - Security
  - Coding standards
  - Functionality
  - Documentation

## 📋 Checklist Before Submission

- [ ] Remove all `error_log()` debug statements
- [ ] Update Plugin URI with your GitHub URL
- [ ] Update Author URI with your GitHub/profile URL
- [ ] Test plugin on WordPress 5.0+ and PHP 7.0+
- [ ] Verify all features work correctly
- [ ] Check readme.txt is complete
- [ ] Ensure License is GPLv2 or later ✅

## 🔧 Files Created

- ✅ `.gitignore` - Excludes unnecessary files
- ✅ `GITHUB_SETUP.md` - Detailed guide
- ✅ `QUICK_START.md` - This file

## 📝 After Approval

Your plugin will be available at:
- WordPress.org: `https://wordpress.org/plugins/simple-scheduled-maintenance/`
- Users can install via: Plugins → Add New → Search "Simple Scheduled Maintenance"

## 🆘 Need Help?

- WordPress Plugin Handbook: https://developer.wordpress.org/plugins/
- GitHub Guide: https://guides.github.com/
- WordPress.org Support: https://wordpress.org/support/

