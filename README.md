# Profiles for Joomla!

![Profiles for Joomla!](https://github.com/user-attachments/assets/63e6c81c-8b6e-42c4-b1c7-1f23adc82fa5)

![GitHub all releases](https://img.shields.io/github/downloads/JoomlaLABS/profiles/total?style=for-the-badge&color=blue)
![GitHub release (latest by SemVer)](https://img.shields.io/github/downloads/JoomlaLABS/profiles/latest/total?style=for-the-badge&color=blue)
![GitHub release (latest by SemVer)](https://img.shields.io/github/v/release/JoomlaLABS/profiles?sort=semver&style=for-the-badge&color=blue)

[![License](https://img.shields.io/badge/license-GPL%202.0%2B-green.svg)](LICENSE)
[![Joomla 6.0+](https://img.shields.io/badge/Joomla!-6.0+-1A3867?logo=joomla&logoColor=white)]()
[![PHP 8.1+](https://img.shields.io/badge/PHP-8.1+-777BB4?logo=php&logoColor=white)]()

## 📖 Description

**Profiles** is a  Joomla extension for managing business/person profiles with category-driven field policies, versioning and optional automation plugins.

## Overview

Joomla!LABS Profiles provides a standalone profile domain (separate from `com_users`) with:

- Category-driven profile types and `display_name` policies
- Native `com_fields` integration (context: `com_joomlalabs_profiles.record`)
- Item versioning and action logging support
- Frontend directory and public profile detail pages
- Optional plugins: Action Log, Privacy and Auto-Profile (user)

The package contains a core component and three plugins. See "Package layout" for paths.

## Features

- Category-driven schemas and display-name patterns
- Flexible user-link policies per category (optional/required, single/multiple)
- Custom fields support via Joomla `com_fields` (WAF-safe context)
- Backend management with Views, Models, Tables and Forms
- Public directory view and profile detail pages with SEF routing
- Installer postflight that enables recommended plugins and bootstraps default fields/categories

## Requirements

| Software | Minimum | Recommended |
|---|---:|---:|
| Joomla! | 6.0.0 | 6.0+ |
| PHP | 8.1 | 8.2+ |

## Installation

1. Download the release package from [GitHub Releases](https://github.com/JoomlaLABS/profiles/releases)
2. In Joomla Administrator go to System → Extensions → Install and upload the package.
3. After installation the package postflight will attempt to enable recommended plugins (Action Log, Privacy); verify plugin status in System → Extensions → Plugins.
4. Verify that default `com_fields` groups and categories are present.

## Configuration

- Configure categories and the `display_name_pattern` for each category in the component configuration.
- Adjust `user_link_policy` per category according to your domain rules.
- Review and configure plugins:
	- `plg_actionlog_joomlalabs_profiles` (recommended)
	- `plg_privacy_joomlalabs_profiles` (privacy/GDPR hooks)
	- `plg_user_joomlalabs_profiles_autoprofile` (automatic profile creation on user events)

## Usage

- Use the Directory menu type to expose the public listing of profiles.
- Use the Single Profile menu type to link directly to a profile detail page.
- Auto-creation: configure the Auto-Profile plugin to map user fields to profile fields and optionally override `display_name` patterns.

## Package layout

- Component: `components/com_joomlalabs_profiles/`
- Administrator code: `components/com_joomlalabs_profiles/admin/`
- Site code: `components/com_joomlalabs_profiles/site/`
- Package: `packages/pkg_joomlalabs_profiles/`
- Plugins:
	- `plugins/actionlog/joomlalabs_profiles/`
	- `plugins/privacy/joomlalabs_profiles/`
	- `plugins/user/joomlalabs_profiles_autoprofile/`

## Contributing

Contributions are welcome. Please open issues or pull requests on GitHub. When contributing:

- Fork the repository and create a feature branch
- Follow the repository code style and tests
- Update documentation and changelog for user-facing changes

## License

This project is released under the terms of the GNU General Public License v2.0 or later. See the `LICENSE` file for details.

## Support

For bugs, feature requests or questions open an issue on the project repository. For commercial support contact the project maintainers.

---

Made with ❤️ for the Joomla! community.
