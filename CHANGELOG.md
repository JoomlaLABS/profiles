# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/), and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Planned

- Additional documentation screenshots and release assets
- Further frontend refinements for profile directory navigation
- Optional category view strategy for clickable breadcrumb category items
- Expanded test coverage and release automation improvements

## [1.0.0] - 2026-05-03

### Added

#### Component Features

- Standalone profile domain for person and legal entity records
- Category-driven business rules for display name generation and user linking
- Administrator profile management with list, filters, edit form, and modal selectors
- Versioning support for profile records
- Native Joomla custom fields integration using `com_joomlalabs_profiles.record`
- Public directory and profile detail views
- SEF routing for directory and profile pages with nested category path support
- Menu-controlled profile layouts: `default`, `cards`, and `tabs`

#### Plugin Features

- User auto-profile plugin for automatic profile creation and synchronization
- Privacy plugin for export and removal workflows
- Action Log plugin for profile activity tracking

#### Technical Features

- Joomla 6.0+ compatible package structure
- PSR-4 namespacing and service-provider based architecture
- Ant build pipeline producing package and constituent ZIP files
- Package postflight script enabling recommended plugins automatically
- PHP-CS-Fixer build targets for code style checks and fixes

#### Frontend Behavior

- Published-only profile selection in single-profile menu modal
- Breadcrumb policy aligned to Joomla menu context
- Directory-to-profile navigation with category-aware pathway support

### Documentation

- README with overview, features, requirements, installation, and usage
- INSTALLATION guide for package installation and development workflow
- CONTRIBUTING guide with structure, standards, and contribution process
- SUPPORT guide with troubleshooting and contact channels
- CHANGELOG for release notes and future planning

## Release Notes

### Highlights

This is the initial structured release of Profiles for Joomla! as a package-oriented Joomla 6 project.

It delivers:

- Category-driven profile management
- Public frontend directory and detail pages
- Native Joomla integration for fields, action logs, privacy, and versioning
- Package-based distribution with component and plugins

### Requirements

- Joomla 6.0.0 or higher
- PHP 8.1 or higher
- MySQL 8 / MariaDB 10.5+

### Installation

1. Build or download the package ZIP
2. Install it from Joomla Administrator
3. Verify plugins are enabled
4. Configure categories, fields, and menu items

### Breaking Changes

- N/A - Initial structured release

### Migration Guide

- N/A - Initial structured release

## Links

- [Repository](https://github.com/JoomlaLABS/profiles)
- [Issues](https://github.com/JoomlaLABS/profiles/issues)
- [Discussions](https://github.com/JoomlaLABS/profiles/discussions)
- [Releases](https://github.com/JoomlaLABS/profiles/releases)
