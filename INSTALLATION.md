# Installation Instructions

> **For production use, download from the official repository:** [GitHub Releases](https://github.com/JoomlaLABS/profiles/releases).

## Development Installation

This repository is structured as a package project containing one component and three plugins.

### Build the Package

1. Ensure Ant is available on your system
2. From the repository root, run:

```bash
ant -f build.xml dist
```

3. The package ZIP will be created in `_dist/`
4. Install that ZIP from Joomla Administrator → System → Extensions → Install

### Manual Package Installation

1. Build the package ZIP or create it manually from the package constituents
2. In Joomla Administrator, go to System → Extensions → Install
3. Upload the generated package ZIP
4. Wait for the installer to complete
5. Verify the component and plugins are installed

### Post-Install Verification

After installation, check:

- Component `com_joomlalabs_profiles` is installed
- Plugin `Action Log - JoomlaLABS Profiles` is enabled
- Plugin `Privacy - JoomlaLABS Profiles` is enabled
- Plugin `User - JoomlaLABS Profiles Auto Profile` is installed and configured as needed
- Default categories and baseline custom fields are available

## Initial Configuration

### Categories and Policies

1. Open the component in Joomla Administrator
2. Create or review categories used as profile types
3. Configure category-level policies such as:
   - `display_name_pattern`
   - user-link policy
   - public directory visibility

### Menu Items

Recommended setup:

1. Create a `Directory` menu item
2. Choose the root category for the public listing
3. Configure filter and layout options
4. Optionally create one or more `Single Profile` menu items for highlighted profiles

### Plugins

Review plugin configuration in System → Extensions → Plugins:

- `Action Log - JoomlaLABS Profiles`
- `Privacy - JoomlaLABS Profiles`
- `User - JoomlaLABS Profiles Auto Profile`

## Development Workflow

### Build Targets

Available Ant targets:

```bash
ant -f build.xml dist
ant -f build.xml build
ant -f build.xml phpcs-check
ant -f build.xml phpcs-fix
```

### Notes

- `dist` creates the package without code-style checks
- `build` runs code-style checks before packaging
- `phpcs-check` runs PHP-CS-Fixer in dry-run mode
- `phpcs-fix` applies code-style fixes

## Testing Checklist

After installation, verify:

- Profiles list loads correctly in administrator
- Profile create/edit forms save correctly
- Modal selectors work and only show intended records
- Directory menu items render frontend listing
- Single profile menu items resolve to published profiles
- SEF routes and breadcrumbs behave correctly

## Need Help?

If you encounter issues:

1. Check Joomla error logs
2. Enable Joomla Debug mode
3. Review browser console errors for frontend/admin JavaScript issues
4. Consult [SUPPORT.md](SUPPORT.md)

## Next Steps

After successful installation:

1. Configure category policies
2. Review custom field assignments
3. Publish menu items for directory and profile detail pages
4. Test the optional plugins according to your use case
