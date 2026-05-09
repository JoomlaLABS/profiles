# Support

Need help with Profiles for Joomla!? This file collects the main support channels and troubleshooting pointers.

## 📚 Documentation

Before asking for help, please check:

- [README](README.md) - Project overview and usage
- [INSTALLATION](INSTALLATION.md) - Installation and setup guide
- [CHANGELOG](CHANGELOG.md) - Version history and recent changes
- [CONTRIBUTING](CONTRIBUTING.md) - Contribution process and project structure

## 💬 Getting Help

### 🐛 Found a Bug?

If you have found a bug:

1. Check whether it already exists in [Issues](https://github.com/JoomlaLABS/profiles/issues)
2. If not, [open a bug report](https://github.com/JoomlaLABS/profiles/issues/new?labels=bug&template=bug_report.md)
3. Include:
   - clear description of the problem
   - reproduction steps
   - expected and actual behavior
   - Joomla and PHP versions
   - screenshots, logs, or stack traces if available

### 💡 Feature Request?

If you want to suggest an improvement:

1. Check existing enhancement requests
2. If it is new, [open a feature request](https://github.com/JoomlaLABS/profiles/issues/new?labels=enhancement&template=feature_request.md)
3. Explain:
   - what you want to achieve
   - why it matters
   - how it could work

### ❓ General Questions?

For general usage or development questions:

- [Start a discussion](https://github.com/JoomlaLABS/profiles/discussions)
- Use the relevant category for Q&A or ideas

## 📧 Direct Contact

For private inquiries, business questions, or security concerns:

- Email: [info@joomlalabs.com](mailto:info@joomlalabs.com)

## 🔍 Common Issues

### Single Profile Menu Cannot Select the Expected Record

Possible causes:

1. The profile is unpublished
2. The modal selector is being opened from a single-profile menu item
3. The profile does not match access or publication constraints

What to check:

- Ensure the target profile is published
- Ensure the current user can view that profile
- Reopen the selector after saving publication state changes

### Profile Page Opens but Breadcrumbs Look Incomplete

Expected behavior:

- Directory menu context adds category and profile breadcrumb context
- Single profile menu items do not duplicate the profile title in the pathway

If behavior looks wrong:

- Verify the active menu item is the expected one
- Check that the profile belongs to the expected public category tree
- Confirm SEF routing is enabled and menu items are configured correctly

### Directory or Profile Page Returns 404

Possible causes:

1. Menu item is misconfigured
2. Profile is unpublished
3. Category is unpublished or not public
4. Alias or route data does not match the current record state

What to check:

- Confirm the record and category are published
- Verify menu item type and selected record/root category
- Rebuild Joomla menu and routing caches if needed

## 🛠️ Troubleshooting

### Enable Debug Mode

1. Go to System → Global Configuration → System
2. Set Debug System to Yes
3. Check:
   - Joomla logs
   - PHP error logs
   - browser console

### Verify Requirements

- Joomla 6.0+
- PHP 8.1+
- MySQL 8 / MariaDB 10.5+
- Custom fields available and enabled

### Check Administrator Flows

If something behaves unexpectedly in backend:

- verify list filters and search tools state
- verify modal selectors in menu item configuration
- verify versioning and toolbar behavior on profile edit view

## 🤝 Contributing

If you want to help improve the project:

- Read [CONTRIBUTING](CONTRIBUTING.md)
- Check [open issues](https://github.com/JoomlaLABS/profiles/issues)
- Submit pull requests with fixes or improvements

## 💝 Support the Project

If this project helped you, consider supporting its development:

- ⭐ [Star the repository](https://github.com/JoomlaLABS/profiles)
- 💰 [Sponsor on GitHub](https://github.com/sponsors/JoomlaLABS)
- 🍺 [Buy me a beer](https://buymeacoffee.com/razzo)
- 💳 [Donate via PayPal](https://www.paypal.com/donate/?hosted_button_id=4SRPUJWYMG3GL)

## ⏱️ Response Time

- **Bug reports**: usually reviewed within a few days
- **Feature requests**: reviewed based on roadmap and scope
- **Security issues**: prioritize direct contact via email
- **General questions**: best handled through GitHub Discussions
