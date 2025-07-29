# Backup and Migrate Google Drive

This module provides **Google Drive destination support** for the Backup and Migrate module in **Drupal 11**.

⭐ **Star this project** if you find it useful!

## 🎯 **Why This Module?**

- ✅ **Simple Setup**: OAuth2 integration with Google Drive
- ✅ **Secure**: Uses official Google API PHP Client
- ✅ **Free**: No costs - uses your Google Drive storage
- ✅ **Automatic**: Set and forget backup solution
- ✅ **Drupal 11 Ready**: Built specifically for Drupal 11

## ⚡ **Quick Start**

1. Install module: `composer require drupal/backup_migrate_google_drive`
2. Enable: `drush en backup_migrate_google_drive -y`
3. Set up Google OAuth credentials (see Configuration section)
4. Go to: `/admin/config/development/backup_migrate/settings/google-drive`
5. Connect with your Google account
6. Start backing up! 🎉

## 📋 **Requirements**

- Drupal 11
- PHP 8.1+
- Backup and Migrate module
- Gmail/Google account with Google Drive
- Google Cloud Console project (free)

## 🔧 **Installation**

### Via Composer (Recommended)

```bash
composer require drupal/backup_migrate_google_drive
drush en backup_migrate_google_drive -y
```

### Manual Installation

1. Download and extract to `modules/contrib/`
2. Enable: `drush en backup_migrate_google_drive -y`

## ⚙️ **Configuration**

### Google OAuth Setup

1. Go to [Google Cloud Console](https://console.cloud.google.com/)
2. Create a new project or select an existing one
3. Enable the Google Drive API for your project
4. Create OAuth 2.0 credentials:
   - Application type: Web application
   - Authorized redirect URIs: `https://yoursite.com/admin/config/development/backup_migrate/settings/google-drive/callback`
5. Copy the Client ID and Client Secret

### Module Configuration

1. Navigate to: **Configuration** → **Development** → **Backup and Migrate** → **Settings** → **Google Drive**
2. Enter your Client ID and Client Secret from Google Cloud Console
3. Save the configuration
4. Click **"Connect to Google Drive"**
5. Authorize with your Google account
6. Done! 🎉

## 🎯 **Usage**

### Creating Backup Destination

1. Go to: **Configuration** → **Development** → **Backup and Migrate**
2. Click **"Destinations"** tab
3. Click **"Add Destination"**
4. Select **"Google Drive"**
5. Configure folder path (e.g., `/backups/mysite`)
6. Save!

### Running Backups

- **Manual**: Use Backup and Migrate interface
- **Automated**: Set up cron schedules in Backup and Migrate
- **Drush**: `drush bam:backup --destination=google_drive_destination_id`

## 🔐 **Security & Privacy**

- ✅ Uses OAuth2 (industry standard)
- ✅ Tokens stored securely in Drupal config
- ✅ No passwords stored
- ✅ You control your Google Drive access
- ✅ Can revoke access anytime in Google Account settings

## � **Costs**

**Completely FREE!** 🎉

- Module: Open source (GPL-2.0+)
- Google Drive API: Free quota (plenty for backups)
- Your Google Drive storage: 15GB free, paid plans available

## 🆘 **Troubleshooting**

### "Invalid Client" Error

- Using shared app: Try reconnecting
- Using custom app: Check your OAuth credentials

### "Access Denied" Error

- Check Google Drive permissions
- Ensure backup folder exists and is accessible

### "Quota Exceeded" Error

- You've hit Google Drive storage limit
- Clean old backups or upgrade Google Drive plan

## 🤝 **Contributing**

We welcome contributions!

1. Fork the project
2. Create feature branch: `git checkout -b feature/amazing-feature`
3. Commit: `git commit -m 'Add amazing feature'`
4. Push: `git push origin feature/amazing-feature`
5. Open Pull Request

## 📄 **License**

GPL-2.0+ (same as Drupal)

## 🙏 **Credits**

- Built for the Drupal community
- Uses Google API PHP Client
- Integrates with Backup and Migrate module

## 📞 **Support**

- 🐛 **Bug reports**: [Issue queue on Drupal.org](https://www.drupal.org/project/backup_migrate_google_drive/issues)
- 💬 **Questions**: Use issue queue with "support" tag
- 📖 **Documentation**: See this README and module help

---

⭐ **If this module helped you, please star it and leave a review!** ⭐

- Go to Google Drive settings and click "Connect Google Drive"
- Complete the authorization process

### "Access token expired"

- The module will automatically refresh tokens
- If issues persist, disconnect and reconnect your account

### "Permission denied"

- Make sure you granted all requested permissions during authorization
- Try disconnecting and reconnecting your Google account

## Requirements

- **Drupal 11**
- **PHP 8.1+**
- Backup and Migrate module
- Google API PHP Client Library (^2.18)

## Support

This module provides a simple, user-friendly way to backup your Drupal site to Google Drive using just your Gmail account - no complex setup required!

- Check that the service account has the necessary permissions

### Permission Errors

- Make sure you've shared your Google Drive folder with the service account email address
- Verify that the service account has write permissions to the folder

### File Not Found Errors

- Check that the folder ID is correct (if specified)
- Ensure the folder exists and is accessible by the service account

## Support

This module is provided as-is. For issues related to:

- Backup and Migrate core functionality: Visit the [Backup and Migrate project page](https://www.drupal.org/project/backup_migrate)
- Google Drive API: Check the [Google Drive API documentation](https://developers.google.com/drive/api)
