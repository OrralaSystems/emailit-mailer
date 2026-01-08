# 📧 Emailit API WordPress Plugin

<p align="center">
  <img src="https://img.shields.io/badge/version-1.2.0-blue.svg" alt="Version 1.2.0">
  <img src="https://img.shields.io/badge/WordPress-5.0%2B-green.svg" alt="WordPress 5.0+">
  <img src="https://img.shields.io/badge/PHP-7.4%2B-purple.svg" alt="PHP 7.4+">
  <img src="https://img.shields.io/badge/License-GPL--2.0-orange.svg" alt="License GPL-2.0">
</p>

<p align="center">
  <strong>Expert-level WordPress plugin that replaces wp_mail() to send emails through the EmailIT API</strong>
</p>

<p align="center">
  Developed with ❤️ by <a href="https://orralasystems.com">Orrala Systems</a>
</p>

---

## ✨ Features

- 🔄 **Automatic wp_mail() replacement** - All WordPress emails are sent through EmailIT
- 🔑 **Bearer authentication** - Secure connection using API Key
- ⚙️ **Complete settings panel** - Easy configuration from WordPress admin
- 📊 **Logging system** - Record of the last 100+ emails with status
- 🧪 **Test email** - Verify configuration with one click
- 🔛 **On/off toggle** - Enable/disable without deactivating the plugin
- 📎 **Attachment support** - Send attachments without issues
- 🛡️ **Secure** - Nonces, sanitization and validation throughout the code

---

## 📋 Requirements

| Requirement | Version |
|-------------|---------|
| WordPress | 5.0+ |
| PHP | 7.4+ |
| EmailIT Account | Active with API Key |

---

## 🚀 Installation

### Manual Method

1. Download or clone this repository
2. Copy the `emailit-mailer` folder to `/wp-content/plugins/`
3. Activate the plugin from **Plugins → Installed Plugins**
4. Configure at **Settings → EmailIT Mailer**

### From GitHub

```bash
cd /path/to/wordpress/wp-content/plugins/
git clone https://github.com/OrralaSystems/emailit-mailer.git
```

---

## ⚙️ Configuration

### 1. Get API Key

1. Log in to [EmailIT](https://emailit.com)
2. Go to the credentials panel
3. Create a new "API" type credential
4. Copy the generated API Key

### 2. Configure the Plugin

1. Go to **Settings → EmailIT Mailer**
2. Enable the plugin with the "Plugin Status" toggle
3. Enter your API Key
4. Configure the sender email and name
5. Optionally configure Reply-To
6. Save changes

### 3. Verify Configuration

1. In the "Send Test Email" section
2. Enter a destination email
3. Click "Send Test Email"
4. Verify the email arrives

---

## 📁 Plugin Structure

```
emailit-mailer/
├── 📄 emailit-mailer.php        # Main file
├── 📁 includes/
│   ├── class-emailit-settings.php   # Settings management
│   ├── class-emailit-api.php        # API client
│   ├── class-emailit-logger.php     # Logging system
│   ├── class-emailit-mailer.php     # wp_mail replacement
│   └── class-emailit-admin.php      # Admin pages
├── 📁 assets/
│   ├── 📁 css/
│   │   └── admin.css                # Admin styles
│   └── 📁 js/
│       └── admin.js                 # Admin JavaScript
├── 📄 uninstall.php                 # Cleanup on uninstall
└── 📄 readme.txt                    # WordPress documentation
```

---

## 🔧 Configuration Options

| Option | Description |
|--------|-------------|
| **Enable Plugin** | Toggle to activate/deactivate sending via EmailIT |
| **API Key** | EmailIT authentication key |
| **From Email** | Address from which emails are sent |
| **From Name** | Name that appears as sender |
| **Force Sender** | Ignore sender from other plugins |
| **Reply-To** | Address for replies |
| **Enable Logs** | Activate email logging |
| **Retention Days** | How long logs are kept |
| **Max Entries** | Limit of logs to store |

---

## 📊 Logs Panel

The plugin includes a logs panel accessible from **Settings → EmailIT Logs** where you can:

- ✅ View recently sent emails
- ❌ Identify failed sends
- 🔍 Filter by status (sent/failed)
- 🔎 Search by email or subject
- 🗑️ Manually clear logs

---

## 🔒 Security

The plugin implements WordPress security best practices:

- **Sanitization**: All inputs are sanitized with WordPress functions
- **Escaping**: All outputs are properly escaped
- **Nonces**: Verification on forms and AJAX requests
- **Capabilities**: Only users with `manage_options` can configure
- **Prepared Statements**: Use of `$wpdb->prepare()` on all queries

---

## 📝 Changelog

### 1.2.0
- 🌐 Plugin fully localized to English (US)
- 📚 Improved code documentation
- 🔧 Various stability improvements

### 1.1.0
- ➕ Added toggle to enable/disable plugin
- 🔄 When disabled, WordPress uses its native method
- 🎨 UI improvements

### 1.0.0
- 🎉 Initial version
- 📧 EmailIT API integration
- ⚙️ Complete settings panel
- 📊 Logging system
- 🧪 Test email

---

## 🤝 Contributing

Contributions are welcome. Please:

1. Fork the repository
2. Create a branch for your feature (`git checkout -b feature/AmazingFeature`)
3. Commit your changes (`git commit -m 'Add some AmazingFeature'`)
4. Push to the branch (`git push origin feature/AmazingFeature`)
5. Open a Pull Request

---

## 📄 License

This project is under the GPL-2.0 License. See the [LICENSE](LICENSE) file for more details.

---

## 🔗 Links

- [EmailIT](https://emailit.com) - Email sending service
- [API Documentation](https://docs.emailit.com) - API documentation
- [Orrala Systems](https://orralasystems.com) - Plugin developers

---

<p align="center">
  <strong>Emailit API WordPress Plugin</strong><br>
  © 2025 Orrala Systems. All rights reserved.
</p>
