# Moodle Plugin Packaging Guide

Complete guide for creating a valid ZIP file for the Moodle Plugin Directory.

## The Problem: Windows Backslashes

**Critical Issue**: PowerShell's `Compress-Archive` creates ZIP files with Windows backslashes (`\`) in paths. The Moodle Plugin Directory validator runs on Linux and requires forward slashes (`/`).

**Symptoms**:
- Error: "File version.php not found"
- Error: "English language file does not contain $string['pluginname']"
- Warning: "Release notes not found"

**Even though** the files exist in the ZIP, the validator can't find them due to path separator mismatch.

## The Solution: Use Python to Create ZIP

Python's `zipfile` module creates cross-platform ZIPs with forward slashes regardless of the OS.

### Quick Command (Run from `c:\Dev\moodle-lumination\local\`)

```bash
cd /c/Dev/moodle-lumination/local && python3 << 'PYEOF'
import zipfile
import os

# Create ZIP with forward slashes directly from source
with zipfile.ZipFile('lumination.zip', 'w', zipfile.ZIP_DEFLATED) as zipf:
    base_dir = 'lumination'
    for root, dirs, files in os.walk(base_dir):
        # Exclude unwanted directories
        dirs[:] = [d for d in dirs if d not in ['.git', '.github', '__pycache__']]

        for file in files:
            # Skip unwanted files
            if file in ['CLAUDE.md'] or file.endswith('.pyc'):
                continue
            if 'docs/PACKAGING.md' in os.path.join(root, file):
                continue

            filepath = os.path.join(root, file)
            # Use forward slashes for ZIP paths
            arcname = filepath.replace(os.sep, '/')
            zipf.write(filepath, arcname)

print('Created lumination.zip with Unix paths')
PYEOF
```

### Verification

```bash
# Check for forward slashes (should show lumination/version.php)
unzip -l lumination.zip | head -15

# Should NOT show warning about backslashes
unzip -t lumination.zip 2>&1 | grep -i warning

# Verify critical files exist
unzip -l lumination.zip | grep -E "(version.php|README.md|local_lumination.php)"
```

## Upload Settings

When uploading to https://moodle.org/plugins/:

1. **Plugin type**: Local plugin
2. **Frankenstyle name**: `local_lumination`
3. **Supported Moodle versions**: 4.4, 4.5
4. **Checkboxes** (ALL UNCHECKED):
   - ☐ Rename root directory
   - ☐ Auto remove system files
   - ☐ Fix README file name

## ZIP Structure Requirements

The validator expects:

```
lumination.zip
└── lumination/                          ← Folder name matches plugin (NOT local_lumination)
    ├── version.php                      ← REQUIRED
    ├── README.md                        ← REQUIRED
    ├── LICENSE                          ← REQUIRED
    ├── CHANGES.md                       ← Recommended
    ├── lang/en/local_lumination.php     ← REQUIRED, must contain $string['pluginname']
    ├── db/access.php
    ├── db/install.xml
    ├── db/upgrade.php
    ├── classes/
    ├── tests/
    └── ...
```

## Files to Exclude

- `.git/` - Version control (adds 5+ MB)
- `.github/` - CI workflows (won't work on moodle.org)
- `CLAUDE.md` - Internal dev documentation
- `docs/PACKAGING.md` - This file (not needed for end users)
- `__pycache__/`, `*.pyc` - Python artifacts
- `.vscode/`, `.idea/` - Editor configs

## Why PowerShell Doesn't Work

```powershell
# This creates INVALID ZIPs for Moodle
Compress-Archive -Path lumination -DestinationPath lumination.zip

# Results in paths like: lumination\version.php (backslashes)
# Moodle expects: lumination/version.php (forward slashes)
```

Even though Windows can extract these ZIPs fine, the Moodle validator (running on Linux) interprets backslashes as part of the filename, not path separators.

## Common Validation Errors

### "File version.php not found"

**Cause**: ZIP uses backslashes instead of forward slashes
**Fix**: Use the Python script above

### "English language file does not contain $string['pluginname']"

**Causes**:
1. Path separator issue (backslashes) ← Most common
2. Missing line in `lang/en/local_lumination.php`
3. Typo in variable name

**Fix**:
1. Use Python script for ZIP creation
2. Verify with: `grep "pluginname" lang/en/local_lumination.php`

### "Release notes not found"

**Cause**: Missing `README.md` or `README.txt` at plugin root
**Fix**: Ensure `lumination/README.md` exists in ZIP

## Alternative Method: Use GitHub Release

If you have the plugin on GitHub:

1. Create a git tag: `git tag v0.1.0 && git push origin v0.1.0`
2. Create a GitHub release from that tag
3. GitHub generates a source ZIP automatically
4. Download that ZIP - it will have correct forward slashes
5. May need to rename the root folder from `moodle-local_lumination-0.1.0` to `lumination`

## Troubleshooting Checklist

Before uploading, verify:

- [ ] ZIP created with Python (not PowerShell)
- [ ] `unzip -l lumination.zip` shows forward slashes
- [ ] Files appear at `lumination/version.php` (not nested deeper)
- [ ] No warning about backslashes when running `unzip -t`
- [ ] `version.php` exists at root of lumination folder
- [ ] `lang/en/local_lumination.php` contains `$string['pluginname']`
- [ ] `README.md` exists at root of lumination folder
- [ ] ZIP size is ~50-60KB (without .git it's much smaller)

## After Successful Upload

The Moodle Plugin Directory will:

1. **Automatic validation** - Checks file structure, GPL license, etc.
2. **Manual review** - A human reviewer checks code quality and security
3. **Approval** - Plugin becomes publicly available

You can add:
- Screenshots
- Demo credentials (for testing)
- Links to documentation, issue tracker, repository
- Detailed description

The review process typically takes a few days to a week.
