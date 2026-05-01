# Profiles for Joomla!

![Profiles for Joomla!](https://repository-images.githubusercontent.com/1226321205/2420ddf3-87ff-4197-8a9c-edf63c0640a1)

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

## 🤝 Contributing

We welcome contributions! Here's how you can help:

### 🔄 How to Contribute

1. **🍴 Fork** the repository
2. **🌿 Create** a feature branch (`git checkout -b feature/amazing-feature`)
3. **✨ Make** your changes following our coding standards
4. **🧪 Add** tests if applicable
5. **💾 Commit** your changes (`git commit -m 'Add some amazing feature'`)
6. **🚀 Push** to the branch (`git push origin feature/amazing-feature`)
7. **📮 Submit** a pull request

### 📋 Guidelines

- Follow PSR-12 coding standards for PHP code
- Write clear, concise commit messages
- Test your changes thoroughly before submitting
- Update documentation as needed
- Ensure your code is well-documented with inline comments
- Maintain security best practices

## 📄 License

This project is licensed under the **GNU General Public License v2.0** - see the [LICENSE](LICENSE) file for details.

```
GNU GENERAL PUBLIC LICENSE
Version 2, June 1991

Copyright (C) 2023-2026 Joomla!LABS

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.
```

## 👥 Project Information

### 🏢 Project Owner

**Joomla!LABS** - [https://joomlalabs.com](https://joomlalabs.com)

[![Email](https://img.shields.io/badge/Email-info%40joomlalabs.com-red?style=for-the-badge&logo=gmail&logoColor=white)](mailto:info@joomlalabs.com)

*Joomla!LABS is the company that owns and maintains this project.*

### 👨‍💻 Contributors

**Luca Racchetti** - Lead Developer

[![LinkedIn](https://img.shields.io/badge/LinkedIn-Luca%20Racchetti-blue?style=for-the-badge&logo=linkedin&logoColor=white)](https://www.linkedin.com/in/razzo/)
[![GitHub](https://img.shields.io/badge/GitHub-Razzo1987-black?style=for-the-badge&logo=github&logoColor=white)](https://github.com/Razzo1987)

*Full-Stack Developer passionate about creating modern, efficient web applications and tools for the Joomla! community*

## 🆘 Support

### 💬 Get Help

Need help? We're here for you!

- 🐛 **Found a bug?** [Open an issue](https://github.com/JoomlaLABS/profiles/issues/new?labels=bug&template=bug_report.md)
- 💡 **Have a feature request?** [Open an issue](https://github.com/JoomlaLABS/profiles/issues/new?labels=enhancement&template=feature_request.md)
- ❓ **Questions?** [Start a discussion](https://github.com/JoomlaLABS/profiles/discussions)

## 💝 Donate

If you find this project useful, consider supporting its development:

[![Sponsor on GitHub](https://img.shields.io/badge/Sponsor-GitHub-ea4aaa?style=for-the-badge&logo=github)](https://github.com/sponsors/JoomlaLABS)
[![Buy me a beer](https://img.shields.io/badge/🍺%20Buy%20me%20a-beer-FFDD00?style=for-the-badge&labelColor=FFDD00&color=FFDD00)](https://buymeacoffee.com/razzo)
[![Donate via PayPal](https://img.shields.io/badge/Donate-PayPal-0070BA?style=for-the-badge&logo=paypal&logoColor=white)](https://www.paypal.com/donate/?hosted_button_id=4SRPUJWYMG3GL)

Your support helps maintain and improve this project!

---

**Made with ❤️ for the Joomla! Community**

**⭐ If this project helped you, please consider giving it a star! ⭐**
