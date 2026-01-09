# LegalBlink for Aruba - WordPress Plugin

[![WordPress Plugin](https://img.shields.io/badge/WordPress-6.0%2B-blue.svg)](https://wordpress.org/)
[![PHP Version](https://img.shields.io/badge/PHP-7.4%2B-purple.svg)](https://www.php.net/)
[![License](https://img.shields.io/badge/License-GPLv3-green.svg)](https://www.gnu.org/licenses/gpl-3.0.html)

Official WordPress plugin for integrating LegalBlink services by Aruba into your WordPress site. Manage GDPR-compliant privacy policies, cookie policies, cookie banners, and terms and conditions with ease.

## 📖 Overview

**LegalBlink for Aruba** allows you to:

- ✅ Create and manage GDPR-compliant legal documents
- 🍪 Display cookie consent banners with consent management
- 📄 Embed legal documents via shortcodes or iframes
- 🌍 Multi-language support (IT, EN, DE, FR, ES)
- 🔄 Automatic synchronization with LegalBlink platform
- 🛒 Full compatibility with WooCommerce
- 🌐 WordPress Multisite support
- 🔌 WPML and Polylang compatibility

## 📦 Download

- **WordPress.org**: [Download from WordPress Plugin Directory](https://wordpress.org/plugins/legalblink-for-aruba/)
- **Releases**: [GitHub Releases](https://github.com/hirooks-legalblink-it/wordpress-legalblink-for-aruba/releases)

## 🔧 Installation

### From WordPress Admin

1. Go to **Plugins → Add New**
2. Search for "LegalBlink for Aruba"
3. Click **Install Now** and then **Activate**

### Manual Installation

1. Download the latest `.zip` file from [Releases](https://github.com/hirooks-legalblink-it/wordpress-legalblink-for-aruba/releases)
2. Go to **Plugins → Add New → Upload Plugin**
3. Upload the `.zip` file and click **Install Now**
4. Activate the plugin

### From Source (Developers)

See the [Developer Documentation](#-for-developers) section below.

## ⚙️ Configuration

1. Get your LegalBlink token from [https://app.legalblink.it/login](https://app.legalblink.it/login)
2. In WordPress, go to **LegalBlink for Aruba**
3. Enter your token and click **Login**
4. Configure your legal documents and cookie banner settings

## 🎯 Features

### Legal Documents Management

- Privacy Policy
- Cookie Policy
- Terms and Conditions (CGV)
- Automatic updates from LegalBlink platform

### Cookie Banner

- Enable/disable cookie banner site-wide
- Customizable consent management
- GDPR-compliant cookie tracking

### Shortcodes

Embed legal documents anywhere on your site:

```
[lbfa_privacy_policy]
[lbfa_cookie_policy]
[lbfa_cgv_policy]
```

With options:
```
[lbfa_privacy_policy language="en" html="true"]
```

### Cache Management

- Configurable cache duration
- Manual cache clearing
- Optimized performance

## 🛠️ For Developers

This plugin's compiled JavaScript and CSS files are built from human-readable source code.

### 📂 Source Code

The complete source code is available in this repository:

- **Admin UI Source**: [`admin-ui/`](./admin-ui/) - Vue 3 + TypeScript
- **PHP Backend**: [`plugin/legalblink-for-aruba/`](./plugin/legalblink-for-aruba/) - WordPress plugin code
- **Build Script**: [`build.sh`](./build.sh) - Automated build process

### 🏗️ Build Instructions

#### Requirements

- Node.js 20+
- npm
- PHP 7.4+
- Composer

#### Quick Start

```bash
# Clone the repository
git clone https://github.com/hirooks-legalblink-it/wordpress-legalblink-for-aruba.git
cd legalblink-plugin-wp-refactor

# Build everything (automated)
chmod +x build.sh
./build.sh
```

The build script will:
1. Check system requirements
2. Install npm dependencies
3. Build the Vue 3 admin interface
4. Install Composer dependencies
5. Create a distributable plugin `.zip` in `dist/`

#### Manual Build

```bash
# Build admin UI
cd admin-ui
npm install
npm run build

# Install PHP dependencies
cd ../plugin/legalblink-for-aruba
composer install --no-dev --optimize-autoloader
```

#### Development Mode

```bash
# Start dev server with hot reload
cd admin-ui
npm run dev
```

### 📚 Documentation

- **Developer Guide**: [`plugin/legalblink-for-aruba/README-DEVELOPERS.md`](./plugin/legalblink-for-aruba/README-DEVELOPERS.md)
- **Admin UI README**: [`admin-ui/README.md`](./admin-ui/README.md)
- **Build Script**: [`build.sh`](./build.sh) - Self-documented

### 🧰 Tech Stack

**Frontend (Admin UI)**:
- Vue 3 (TypeScript)
- Vuetify 3 (Material Design)
- Vite (Build tool)
- Pinia (State management)
- Vue Router

**Backend**:
- PHP 7.4+
- WordPress REST API
- Composer (Autoloading)

### 📦 Third-Party Libraries

All third-party libraries and their licenses are documented in:
- [`admin-ui/package.json`](./admin-ui/package.json) - npm dependencies
- [`plugin/legalblink-for-aruba/composer.json`](./plugin/legalblink-for-aruba/composer.json) - PHP dependencies
- [`plugin/legalblink-for-aruba/README-DEVELOPERS.md`](./plugin/legalblink-for-aruba/README-DEVELOPERS.md) - Full list with licenses

All frontend libraries are MIT licensed and compatible with the plugin's GPLv3 license.

## 📋 Requirements

- **WordPress**: 6.0 or higher
- **PHP**: 7.4 or higher
- **LegalBlink Account**: Required for authentication

## 🌐 Compatibility

- ✅ WordPress Multisite
- ✅ WooCommerce
- ✅ WPML
- ✅ Polylang
- ✅ PHP 8.0+

## 📝 License

This plugin is licensed under the GNU General Public License v3.0 or later.

- **Plugin**: [GPLv3](https://www.gnu.org/licenses/gpl-3.0.html)

## 🤝 Contributing

Contributions are welcome! To contribute:

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

Please ensure:
- Code follows WordPress and Vue.js best practices
- TypeScript types are properly defined
- The plugin builds successfully (`./build.sh`)

## 🐛 Bug Reports & Support

- **WordPress.org Support**: [Plugin Support Forum](https://wordpress.org/support/plugin/legalblink-for-aruba/)
- **GitHub Issues**: [Report a bug](https://github.com/hirooks-legalblink-it/wordpress-legalblink-for-aruba/issues)
- **LegalBlink Support**: [Mail us](mailto:legalblink@legalblink.it)

## 🗂️ Project Structure

```
legalblink-plugin-wp-refactor/
├── README.md                           # This file
├── build.sh                            # Build script
├── admin-ui/                           # Vue 3 Admin Interface (SOURCE)
│   ├── src/                           # TypeScript/Vue source code
│   │   ├── components/                # Vue components
│   │   ├── services/                  # API services
│   │   ├── stores/                    # Pinia stores
│   │   └── router/                    # Vue Router
│   ├── package.json                   # npm dependencies
│   └── vite.config.mts                # Vite configuration
├── plugin/
│   └── legalblink-for-aruba/          # WordPress Plugin (DISTRIBUTION)
│       ├── readme.txt                 # WordPress.org readme
│       ├── assets/
│       │   └── admin-ui/              # COMPILED JS/CSS
│       ├── classes/                   # PHP classes
│       │   ├── controller/            # REST API controllers
│       │   ├── helper/                # Helper classes
│       │   └── shortcode/             # WordPress shortcodes
│       └── vendor/                    # Composer dependencies
└── dist/                              # Build output (generated)
```

---

**Important**: This plugin complies with WordPress.org guidelines by providing full access to human-readable source code for all compiled assets. See the [Developer Documentation](#-for-developers) section for details.

