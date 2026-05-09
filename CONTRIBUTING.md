# Contributing to Profiles for Joomla!

Thank you for your interest in contributing to the Profiles for Joomla! project.

We welcome contributions that improve functionality, architecture, documentation, or release quality.

## 🔄 How to Contribute

### Reporting Bugs

If you find a bug, please [open an issue](https://github.com/JoomlaLABS/profiles/issues/new?labels=bug&template=bug_report.md) with:

- A clear, descriptive title
- Detailed steps to reproduce the problem
- Expected behavior versus actual behavior
- Your environment: Joomla version, PHP version, database, browser if relevant
- Screenshots or logs if applicable
- Any related error messages from Joomla or PHP logs

### Suggesting Enhancements

Have an idea for a new feature or architectural improvement? [Open an enhancement issue](https://github.com/JoomlaLABS/profiles/issues/new?labels=enhancement&template=feature_request.md) with:

- A concise description of the idea
- Why it is useful
- How it should behave
- Any examples, screenshots, or references

### Pull Requests

1. Fork the repository
2. Create a feature branch from `main`
3. Make your changes following the project conventions
4. Test your changes thoroughly
5. Commit with clear messages
6. Push your branch
7. Submit a pull request targeting `main`

## 📋 Coding Standards

### PHP Code

- Follow PSR-12 coding standards
- Prefer modern Joomla 6 patterns and dependency injection
- Use strict typing where already established in the codebase
- Keep business logic in models/services, not in controllers or views
- Use parameterized queries and Joomla database abstractions

### Joomla Conventions

- Preserve Joomla menu, router, ACL, and MVC patterns
- Keep manifest, language, and installer changes synchronized
- Prefer minimal changes that solve the root cause
- Avoid regressions in admin list behavior, modal selectors, and routing

### Documentation

- Use clear, concise English
- Update `README.md` when adding or changing user-facing features
- Update `CHANGELOG.md` for notable changes
- Update `INSTALLATION.md` or `SUPPORT.md` when setup or troubleshooting changes

## 🧪 Testing

Before submitting a pull request:

1. Test on a clean Joomla 6 installation when possible
2. Verify administrator list, edit, and modal flows still work
3. Verify frontend directory and profile detail pages if affected
4. Check for PHP warnings, Joomla notices, and routing regressions
5. Run project quality checks if available

## 📝 Commit Message Guidelines

Write commit messages that explain both what changed and why.

Example:

```text
Fix single-profile modal publishing filter

- Force published state in modal layout context
- Prevent unpublished profiles from being selectable in menu items
- Preserve standard admin list behavior
```

## 🏗️ Project Structure

Understanding the package layout helps when contributing.

### Repository Structure

```text
profiles/
├── components/
│   └── com_joomlalabs_profiles/
│       ├── admin/                      # Administrator MVC, forms, tables, services
│       ├── src/                        # Site MVC, router, views, models
│       ├── tmpl/                       # Site menu metadata and layouts
│       ├── media/                      # Public assets
│       ├── language/                   # Site language files
│       ├── script.php                  # Component installer script
│       └── com_joomlalabs_profiles.xml # Component manifest
├── plugins/
│   ├── actionlog/
│   │   └── joomlalabs_profiles/
│   ├── privacy/
│   │   └── joomlalabs_profiles/
│   └── user/
│       └── joomlalabs_profiles_autoprofile/
├── packages/
│   └── pkg_joomlalabs_profiles/
│       ├── pkg_joomlalabs_profiles.xml # Package manifest
│       └── script.php                  # Package installer script
├── build.xml                           # Ant build pipeline
└── README.md
```

### Key Areas

- `components/com_joomlalabs_profiles/admin/src/` contains backend models, views, tables, and extension wiring
- `components/com_joomlalabs_profiles/src/` contains site router, models, and views
- `components/com_joomlalabs_profiles/tmpl/` contains menu-type XML and frontend template entry points
- `plugins/` contains optional integrations bundled in the package
- `packages/pkg_joomlalabs_profiles/` defines the distributable Joomla package

## 🔐 Security

- Never commit secrets, credentials, or local Joomla configuration files
- Use parameterized queries for all database operations
- Escape output in templates and views where applicable
- Respect ACL and publication state behavior
- Report security issues privately to [info@joomlalabs.com](mailto:info@joomlalabs.com)

## 📄 License

By contributing, you agree that your contributions will be licensed under the GNU General Public License v2.0+.

## 📋 Documentation

For project documentation, see:

- [README](README.md)
- [INSTALLATION](INSTALLATION.md)
- [CHANGELOG](CHANGELOG.md)
- [SUPPORT](SUPPORT.md)

## 💬 Questions?

- Need help? [Start a discussion](https://github.com/JoomlaLABS/profiles/discussions)
- Private inquiry? Contact [info@joomlalabs.com](mailto:info@joomlalabs.com)

## 🙏 Thank You!

Your contributions help improve Profiles for Joomla! for the wider Joomla community.

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

---

**Made with ❤️ for the Joomla! Community**