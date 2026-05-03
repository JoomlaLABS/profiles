# Profiles for Joomla!

![Profiles for Joomla!](https://repository-images.githubusercontent.com/1226321205/2420ddf3-87ff-4197-8a9c-edf63c0640a1)

![GitHub all releases](https://img.shields.io/github/downloads/JoomlaLABS/profiles/total?style=for-the-badge&color=blue)
![GitHub release (latest by SemVer)](https://img.shields.io/github/downloads/JoomlaLABS/profiles/latest/total?style=for-the-badge&color=blue)
![GitHub release (latest by SemVer)](https://img.shields.io/github/v/release/JoomlaLABS/profiles?sort=semver&style=for-the-badge&color=blue)

[![License](https://img.shields.io/badge/license-GPL%202.0%2B-green.svg)](LICENSE)
[![Joomla 6.0+](https://img.shields.io/badge/Joomla!-6.0+-1A3867?logo=joomla&logoColor=white)]()
[![PHP 8.1+](https://img.shields.io/badge/PHP-8.1+-777BB4?logo=php&logoColor=white)]()

## 📖 Description

Profiles for Joomla! is a Joomla 6 extension suite for managing person and organization profiles with category-driven policies, native custom fields support, public directory pages, and optional automation plugins.

It provides a standalone profile domain separate from `com_users`, while still supporting optional user linkage, action logging, privacy integration, and auto-profile creation workflows.

## 🖼️ Screenshots

Screenshots and usage previews will be added as the public release assets are finalized.

## ✨ Features

### 🧩 Profile Domain Model

- Standalone profile records independent from `com_users`
- Category-driven profile types for people and legal entities
- `display_name` generation controlled by category patterns
- Per-category user-link policy support
- Native Joomla publication states and ACL-aware access

### 🏗️ Joomla Integration

- Native `com_fields` support with WAF-safe context `com_joomlalabs_profiles.record`
- Administrator CRUD with filters, search tools, modal selectors, and version history
- Action Log plugin integration
- Privacy plugin integration
- Optional user plugin for automatic profile creation and synchronization

### 🌐 Frontend Experience

- Public directory menu type with filtering and category-aware navigation
- Public single profile menu type
- SEF routing for directory and profile pages with nested category segments
- Breadcrumb support aligned to Joomla menu context
- Menu-driven profile layout selection (`default`, `cards`, `tabs`)

### 🛠️ Technical Highlights

- Joomla 6.0+ architecture
- PSR-4 namespacing and service provider wiring
- PHP 8.1+ compatible codebase
- Ant build pipeline for package generation
- Package postflight enabling recommended plugins automatically

## 📋 Requirements

| Software | Minimum | Recommended |
|---|---:|---:|
| Joomla! | 6.0.0 | 6.0+ |
| PHP | 8.1+ | 8.2 or 8.3 |
| Database | MySQL 8 / MariaDB 10.5+ | MariaDB 10.5+ |

Joomla configuration:

- Custom Fields component enabled
- Action Log recommended
- Privacy tools recommended
- Search Tools enabled in administrator UI

## 📦 Installation

### Download & Install

1. Download the latest release package from [GitHub Releases](https://github.com/JoomlaLABS/profiles/releases)
2. In Joomla Administrator, go to System → Extensions → Install
3. Upload the package ZIP generated from this repository
4. After installation, verify that the package enabled the recommended plugins
5. Review component options and category configuration

### Initial Configuration

1. Create or review the categories used as profile types
2. Configure `display_name_pattern` and user-link policies per category
3. Review installed custom field groups and baseline fields
4. Create a `Directory` menu item to expose the public listing
5. Optionally create `Single Profile` menu items for curated profile pages

For detailed installation and development setup instructions, see [INSTALLATION.md](INSTALLATION.md).

## 💡 Usage

### Directory Menu Type

Use the directory menu type when you want a browsable public index of profiles.

Typical flow:

1. Create a menu item of type `Directory`
2. Choose the root category for navigation
3. Configure whether subcategories and filters are shown
4. Choose the profile layout used when opening a profile from the directory

### Single Profile Menu Type

Use the single profile menu type when you want a direct menu item for one published profile.

Typical flow:

1. Create a menu item of type `Single Profile`
2. Select a published profile from the modal selector
3. Choose the preferred frontend layout
4. Publish the menu item

### Auto-Profile Plugin

If enabled, the user plugin can automatically create or update profile records in response to Joomla user events, depending on category rules and plugin configuration.

## 🎨 Feature Showcase

### Category-Driven Policies

The component uses category metadata to control business rules instead of hardcoding profile types in PHP.

Examples:

- Person: `display_name_pattern = {first-name} {last-name}`
- Legal Entity: `display_name_pattern = {company-name}`
- User linking can be optional or required, single or multiple, per category

### Frontend Navigation

The frontend combines Joomla menu context with extension-aware routing:

- Directory pages use category-rooted navigation
- Profile detail pages support SEF URLs with nested category segments
- Breadcrumbs add category context only when browsing from a directory menu item
- Single profile menu items avoid duplicating the current profile in the pathway

### Package Composition

The distribution package contains:

- Component: `com_joomlalabs_profiles`
- Plugin: `plg_user_joomlalabs_profiles_autoprofile`
- Plugin: `plg_privacy_joomlalabs_profiles`
- Plugin: `plg_actionlog_joomlalabs_profiles`
