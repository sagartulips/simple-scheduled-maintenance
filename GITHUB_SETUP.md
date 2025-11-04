# How to Upload Plugin to GitHub and WordPress.org

## Step 1: Prepare Your Plugin Files

### Files You Need:
- ✅ `simple-scheduled-maintenance.php` (main plugin file)
- ✅ `admin-settings.php` (admin interface)
- ✅ `readme.txt` (WordPress.org format)
- ✅ `README.md` (GitHub format)
- ✅ `style.css` (if any)
- ✅ `.gitignore` (to exclude unnecessary files)

## Step 2: Create .gitignore File

Create a `.gitignore` file in your plugin root with:

```
# WordPress
.DS_Store
Thumbs.db
*.log
*.tmp

# IDE
.vscode/
.idea/
*.sublime-*

# OS
.DS_Store
__MACOSX/
```

## Step 3: Upload to GitHub

### Option A: Using GitHub Website

1. Go to [GitHub.com](https://github.com) and sign in
2. Click the "+" icon in top right → "New repository"
3. Repository name: `simple-scheduled-maintenance`
4. Description: "WordPress plugin for scheduled maintenance mode with multi-language support"
5. Choose **Public** (for WordPress.org)
6. **DO NOT** initialize with README (you already have files)
7. Click "Create repository"

### Option B: Using Git Command Line

```bash
# Navigate to your plugin directory
cd wp-content/plugins/simple-scheduled-maintenance

# Initialize git (if not already done)
git init

# Add all files
git add .

# Create initial commit
git commit -m "Initial commit: Simple Scheduled Maintenance plugin v2.3"

# Add GitHub remote (replace YOUR_USERNAME)
git remote add origin https://github.com/YOUR_USERNAME/simple-scheduled-maintenance.git

# Push to GitHub
git branch -M main
git push -u origin main
```

## Step 4: Prepare for WordPress.org Submission

### Requirements Checklist:

- [ ] **Plugin Header**: Must be complete (you have this ✅)
- [ ] **readme.txt**: WordPress.org format (you have this ✅)
- [ ] **No PHP Errors**: Test with PHP 7.0+ and WordPress 5.0+
- [ ] **Security**: All user inputs sanitized (you have this ✅)
- [ ] **Internationalization**: Text domain ready (you have this ✅)
- [ ] **No External Dependencies**: Only WordPress core functions
- [ ] **No Pro/Paid Features**: Must be free and open source

### Important Notes:

1. **Plugin Slug**: WordPress.org will use your plugin directory name
   - Your plugin: `simple-scheduled-maintenance`
   - WordPress.org URL: `https://wordpress.org/plugins/simple-scheduled-maintenance/`

2. **Version Number**: Update version in:
   - `simple-scheduled-maintenance.php` (header)
   - `readme.txt` (Stable tag)

## Step 5: Submit to WordPress.org

### Process:

1. **Create WordPress.org Account**
   - Go to [wordpress.org](https://wordpress.org)
   - Sign up for a free account

2. **Submit Plugin**
   - Go to [Plugin Developer Handbook](https://developer.wordpress.org/plugins/)
   - Read the [Plugin Developer FAQ](https://developer.wordpress.org/plugins/wordpress-org/)
   - Submit via [Plugin Submission Form](https://wordpress.org/plugins/developers/add/)

3. **What You'll Need:**
   - GitHub repository URL
   - Plugin description
   - Screenshots (optional but recommended)
   - Plugin tags/categories

4. **Review Process:**
   - WordPress.org team reviews your plugin
   - Usually takes 1-2 weeks
   - They may request changes
   - Once approved, your plugin goes live!

## Step 6: After Approval

### Maintain Your Plugin:

1. **Update via GitHub:**
   ```bash
   git add .
   git commit -m "Fix: Description of changes"
   git push origin main
   ```

2. **Create Release Tags:**
   ```bash
   git tag -a v2.3 -m "Version 2.3"
   git push origin v2.3
   ```

3. **WordPress.org Sync:**
   - WordPress.org automatically syncs from your GitHub repository
   - Or you can upload ZIP files manually via SVN

## Step 7: WordPress.org SVN Setup (Alternative)

If you prefer SVN instead of GitHub sync:

```bash
# Checkout WordPress.org SVN
svn checkout https://plugins.svn.wordpress.org/simple-scheduled-maintenance/

# Copy your plugin files to trunk/
# Add files
svn add trunk/*

# Commit
svn ci -m "Initial release"

# Create tag for version
svn copy trunk/ tags/2.3/
svn ci -m "Tagging version 2.3"
```

## Important Reminders

### Before Submitting:

- ✅ Test on multiple WordPress versions
- ✅ Test on PHP 7.0, 7.4, 8.0+
- ✅ Check for security issues
- ✅ Ensure all text is translatable
- ✅ Remove debug code (error_log statements)
- ✅ Test activation/deactivation/uninstall
- ✅ Verify no conflicts with popular plugins

### Plugin Header Requirements:

Your plugin header should include:
- Plugin Name
- Plugin URI
- Description
- Version
- Author
- Author URI
- License (GPLv2 or later)
- Text Domain

### readme.txt Format:

Must follow WordPress.org standards:
- Use "Stable tag" for version
- Include "Requires at least" and "Tested up to"
- Include changelog
- Include screenshots section

## Troubleshooting

### Common Issues:

1. **Plugin Rejected**: Usually due to security or coding standards
   - Fix: Review WordPress Coding Standards
   - Fix: Ensure all inputs are sanitized

2. **SVN Sync Issues**: 
   - Fix: Use SVN instead of GitHub sync
   - Fix: Ensure proper file structure

3. **Version Conflicts**:
   - Fix: Always increment version number
   - Fix: Update both plugin header and readme.txt

## Resources

- [WordPress Plugin Handbook](https://developer.wordpress.org/plugins/)
- [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/)
- [Plugin Submission Guidelines](https://developer.wordpress.org/plugins/wordpress-org/)
- [GitHub Guide](https://guides.github.com/)

## Next Steps

1. ✅ Create `.gitignore` file
2. ✅ Review and clean up code (remove debug logs)
3. ✅ Test plugin thoroughly
4. ✅ Create GitHub repository
5. ✅ Upload code to GitHub
6. ✅ Submit to WordPress.org
7. ✅ Wait for approval
8. ✅ Maintain and update regularly

