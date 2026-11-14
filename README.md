<!-- README.md -->
# My File Manager Opensource

<div align="center">

![License](https://img.shields.io/badge/license-MIT-blue.svg)
![Version](https://img.shields.io/badge/version-1.x-green.svg)
![PHP](https://img.shields.io/badge/PHP-7.4%2B-777BB4?logo=php)
![JavaScript](https://img.shields.io/badge/JavaScript-ES6-F7DF1E?logo=javascript)

[![CodeQL](https://github.com/OskarCosimo/My-File-Manager-Opensource/actions/workflows/codeql.yml/badge.svg)](https://github.com/OskarCosimo/My-File-Manager-Opensource/actions/workflows/codeql.yml)
[![PHP Security Analysis](https://github.com/OskarCosimo/My-File-Manager-Opensource/actions/workflows/php-security.yml/badge.svg)](https://github.com/OskarCosimo/My-File-Manager-Opensource/actions/workflows/php-security.yml)
[![Node.js Build](https://github.com/OskarCosimo/My-File-Manager-Opensource/actions/workflows/npm-publish.yml/badge.svg?logo=node.js&style=flat-square)](https://github.com/OskarCosimo/My-File-Manager-Opensource/actions/workflows/npm-publish.yml)

**A modern, secure, and feature-rich file manager with end-to-end encryption support**

[Features](#features) • [Installation](#installation) • [Documentation](#documentation) • [NPM Download](https://www.npmjs.com/package/my-file-manager-opensource) • [License](#license)

</div>

---
# Documentation

## Table of Contents

- [Overview](#overview)
- [Features](#features)
- [Screenshot](#screenshot)
- [Requirements](#requirements)
- [Installation](#installation)
- [Quick Start](#quick-start)
- [Configuration Options](#configuration-options)
  - [JavaScript Initialization Options](#javascript-initialization-options)
  - [PHP Configuration](#php-configuration)
- [Embedding in HTML](#embedding-in-html)
- [API Reference](#api-reference)
  - [Core Methods](#core-methods)
  - [Event Callbacks](#event-callbacks)
- [JavaScript Plugins](#javascript-plugins)
  - [Creating Custom JavaScript Plugins](#creating-custom-javascript-plugins)
  - [Built-in JavaScript Plugins](#built-in-javascript-plugins)
- [PHP Backend System](#php-backend-system)
  - [Core PHP Files](#core-php-files)
  - [PHP Plugin System](#php-plugin-system)
  - [Creating Custom PHP Plugins](#creating-custom-php-plugins)
- [Security Features](#security-features)
  - [End-to-End Encryption](#end-to-end-encryption)
  - [Custom Authentication](#custom-authentication)
  - [Token-Based Security](#token-based-security)
  - [Server-Side Hardening](#server-side-hardening)
- [Customization](#customization)
  - [Custom Menus and Context Menus](#custom-menus-and-context-menus)
  - [Custom Icons](#custom-icons)
  - [Brand Logo](#brand-logo)
  - [Themes](#themes)
- [Internationalization (i18n)](#internationalization-i18n)
- [Browser Compatibility](#browser-compatibility)
- [Contributing](#contributing)
- [License](#license)
- [Credits](#credits)

---

## Overview

**My File Manager** is a powerful, open-source web-based file manager built with vanilla JavaScript and PHP. It provides a modern user interface similar to desktop file managers, with advanced features like end-to-end encryption, sequential chunked file uploads, streaming downloads, plugin architecture, and extensive customization options.

### Why Choose My File Manager?

- 🔐 **Security First**: Built-in encryption, Bearer token authentication, strict path confinement, and comprehensive server-side validations
- 🚀 **Modern Architecture**: Vanilla JavaScript (no framework dependencies), modular PHP backend
- 🔌 **Plugin System**: Extensible with both JavaScript and PHP plugins (Text Editor, Image Editor, Video Editor, Public Links, ClamAV, FTP)
- 🎨 **Fully Customizable**: Themes, icons, menus, branding
- 📱 **Responsive Design**: Works seamlessly on desktop, tablet, and mobile
- 🌍 **Multilingual**: Built-in i18n support with 18 languages
- ⚡ **Performance Optimized**: Streaming chunked uploads/merges, low-memory handling, efficient file operations

---
## Screenshot

<img width="1866" height="492" alt="screenshot-myfilemanager-opensource" src="https://github.com/user-attachments/assets/c0733d15-5c15-4afb-a587-bee37d510ed3" />

## Features

### Core Features

- **File Operations**: Upload, download, delete, rename, copy, cut, paste, create folders
- **Advanced Upload**: Sequential chunked upload queue for large files with smoothed speed/ETA progress tracking
- **Streaming Download**: Real-time download progress modal with cancellation support
- **File Editing**: Built-in editors for text files, raster images, and videos
- **Public Share Links**: Generate expiring download links with optional wait timers, download caps, and registered-user restrictions
- **Search**: Fast debounced file search with recursive directory scanning
- **Trash System**: Soft delete preserving relative paths with restore and empty-trash functionality
- **Drag & Drop**: Intuitive drag-and-drop file uploads
- **Keyboard Shortcuts**: Efficient navigation (Arrow keys, Enter, Backspace, first-letter cycling, Ctrl+C/X/V/A, F2, F5, Del)
- **Multi-selection**: Select multiple files with Ctrl/Shift/Cmd range selection
- **Context Menu**: Right-click context menus for quick actions
- **Breadcrumb Navigation**: Easy UTF-8 safe directory navigation with clickable breadcrumbs
- **List/Grid View**: Toggle between list and grid view modes
- **Sorting**: Sort ascending/descending by name, size, date, or type
- **Quota Management**: Display storage usage, free space, total quota, and max upload size

### Security Features

- **End-to-End Encryption**: AES-256-GCM (WebCrypto) with per-file random salt (`MFMENC01` header) and PBKDF2 (100,000 iterations), plus CryptoJS CTR+HMAC-SHA256 fallback (loaded via SRI without `eval`)
- **Custom Authentication**: Flexible authentication system with callbacks
- **Token-Based Security**: HMAC-SHA256 tokens sent via `Authorization: Bearer` header and POST body (never leaked in URL query strings)
- **IP Whitelist & CORS Allowlist**: Restrict access by IP address and validate cross-origin requests strictly
- **Extension & MIME Filtering**: Server-side filename sanitization, trailing-dot/compound extension blocking, and `finfo` MIME validation
- **Strict Path & Symlink Protection**: `realpath()` confinement checks across all file operations, chunk uploads, and public links
- **Antivirus Integration**: Optional ClamAV daemon (`clamdscan`) scanning on uploaded files
- **XSS & Header Injection Protection**: Escaped UI rendering, URL protocol validation, and RFC 5987 download headers

### Advanced Features

- **Plugin Architecture**: Extend functionality with JavaScript and PHP plugins
- **Custom Menus**: Add custom menu items and context menu entries
- **Brand Customization**: Add your logo and customize the appearance
- **Custom Icons**: Replace default icons with your own PNG files
- **Internationalization**: Multi-language support (18 languages)
- **Auto-Refresh**: Optional automatic refresh (automatically pauses when tab is hidden, searching, or transferring files)
- **Hidden Files**: Show/hide hidden files (dotfiles)
- **Custom File Opener**: Override default file open behavior

---

## Requirements

### Server Requirements

- **PHP**: 7.4 or higher (8.0+ recommended)
- **PHP Extensions**:
  - `fileinfo` (for reliable MIME type detection)
  - `mbstring` (for UTF-8 filename handling)
  - `openssl` / `json` (for crypto/token/JSON handling)
- **Web Server**:
  - **Linux/Unix**: Apache (with mod_rewrite), Nginx, LiteSpeed, Others
  - **Windows**: IIS, Apache (XAMPP/WAMP), Nginx
  - **macOS**: Apache (MAMP/Homebrew), Nginx (Valet), Docker
- **Disk Space**: Depends on your usage

### Client Requirements

- **Modern Browser**: Chrome 90+, Firefox 88+, Safari 14+, Edge 90+
- **JavaScript**: Enabled
- **WebCrypto API**: Recommended for hardware-accelerated encryption (requires HTTPS or `localhost`)

---

## Installation

### 1. Clone or Download

```bash
git clone https://github.com/OskarCosimo/My-File-Manager-Opensource.git
cd my-file-manager-opensource
```

### 2. Directory Structure

Ensure your directory structure matches:

```
my-file-manager-opensource/
├── js/
│   ├── i18n.js                           # Internationalization translations
│   ├── myfilemanager.js                  # Core file manager JavaScript
│   ├── i18n.min.js                       # Minified i18n (production)
│   ├── myfilemanager.min.js              # Minified core JS (production)
│   └── plugins/
│       ├── myfilemanager-image-editor.js # Image editor plugin
│       ├── myfilemanager-text-editor.js  # Text editor plugin
│       ├── myfilemanager-video-editor.js # Video editor plugin
│       ├── public-link-plugin.js         # Public links generator & manager plugin
│       └── [your-custom-plugins].js      # Your custom plugins
├── css/
│   ├── myfilemanager.css                 # Core styles
│   └── myfilemanager.min.css             # Minified styles (production)
├── src/
│   ├── connector.php                     # Main API endpoint
│   ├── .htaccess                         # Apache security rules
│   ├── nginx.conf.example                # Example Nginx configuration
│   ├── myfilemanager.php                 # File manager core class
│   ├── chunkuploader.php                 # Chunked upload handler
│   ├── security.php                      # Security utilities
│   └── plugins/
│       ├── plugininterface.php           # Plugin interface
│       ├── clamav.php                    # ClamAV antivirus scanner plugin
│       ├── ftpplugin.php                 # FTP/FTPS storage plugin
│       ├── publiclinks.php               # Public links backend plugin
│       ├── rate_limiter.php              # Rate limiter for video editor
│       ├── video_editor.php              # FFmpeg video processor plugin
│       └── [your-custom-plugins].php     # Your custom plugins
├── download.php                          # Standalone public link download endpoint
├── assets/                               # PNG icon files and other static assets
├── files/                                # Default file storage directory
├── package.json                          # NPM package info (optional)
└── README.md                             # This file
```

#### Apache (Linux/Windows/macOS)
The included `.htaccess` file in `/src/` works out of the box.
Only `connector.php` is accessible via HTTP.

#### Nginx (Linux/macOS)
Add the configuration from `src/nginx.conf.example` to your server block:
```bash
sudo nano /etc/nginx/sites-available/your-site
# Add the location block from nginx.conf.example
sudo nginx -t
sudo systemctl reload nginx
```

#### Windows IIS
IIS doesn't support `.htaccess` file-level access control without additional modules.

**Recommended solution:**
Move `connector.php` outside the `/src/` folder, update its `require_once` paths, and deny direct HTTP access to `/src/` in IIS Manager.

### 3. Set Permissions

```bash
# Create file storage directory
mkdir -p files
chown www-data:www-data files
chmod 750 files
```

### 4. Configure PHP

Edit `src/connector.php` to configure your file manager:

```php
<?php
require_once __DIR__ . '/myfilemanager.php';
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/chunkuploader.php';

$PATHTOFILES = __DIR__ . '/../files/'; // Absolute path to file storage
if (!is_dir($PATHTOFILES)) {
    mkdir($PATHTOFILES, 0750, true);
}

$config = [
    'rootPath' => $PATHTOFILES,
    'rootUrl' => '',
    'maxQuota' => parseFileSize('5GB'),
    'maxFileSize' => parseFileSize('0.5GB'),

    // Important: include 'application/octet-stream' if client-side encryption (cryptFiles) is enabled
    'allowedMimeTypes' => ['image/*', 'video/*', 'audio/*', 'text/*', 'application/pdf', 'text/plain'],
    // ...
];
```

### 5. Install Dependencies (Optional)

If you want to use the build tools for minification:

```bash
npm install
```

---

## Quick Start

### Basic HTML Integration

Create an HTML file with the file manager:

```html
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My File Manager</title>

    <!-- File Manager CSS -->
    <link rel="stylesheet" href="css/myfilemanager.css">
</head>
<body>
    <!-- File Manager Container -->
    <div id="filemanager" style="height: 100vh;"></div>

    <!-- Load Dependencies -->
    <script src="js/i18n.js"></script>
    <script src="js/myfilemanager.js"></script>

    <!-- Load JavaScript Plugins (Optional) -->
    <script src="js/plugins/myfilemanager-text-editor.js"></script>
    <script src="js/plugins/myfilemanager-image-editor.js"></script>
    <script src="js/plugins/myfilemanager-video-editor.js"></script>
    <script src="js/plugins/public-link-plugin.js"></script>

    <!-- Initialize File Manager -->
    <script>
        var fm = new MyFileManager('#filemanager', {
            url: 'src/connector.php',
            lang: 'en',
            viewMode: 'list',
            theme: 'light'
        });
    </script>
</body>
</html>
```

---

## Configuration Options

### JavaScript Initialization Options

The following table lists all available options when initializing the file manager:

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `url` | String | `/connector.php` | Backend API endpoint URL |
| `downloadUrl` | String | `null` | Custom download URL (leave `null` to use connector) |
| `token` | String | `null` | Authentication token sent via `Authorization: Bearer` header and POST body |
| `lang` | String | Browser language | UI language (`en`, `it`, `es`, `de`, `fr`, `pt`, `zh`, `ja`, `ru`, `ar`, `hi`, `ko`, `nl`, `tr`, `pl`, `sv`, `el`, `vi`) |
| `viewMode` | String | `list` | Default view mode (`list` or `grid`) |
| `theme` | String | `light` | UI theme (`light` or `dark`) |
| `maxFileSize` | Number | `524288000` | Maximum file size in bytes (500MB) |
| `chunkSize` | Number | `1048576` | Chunk size for uploads in bytes (1MB) |
| `trashPath` | String | `.trash` | Trash folder name (relative to root) - recommended not to change |
| `debug` | Boolean | `false` | Enable console debug messages |
| `brandLogo` | String | `null` | URL to brand logo image |
| `brandLink` | String | `null` | Link URL when clicking logo |
| `brandTarget` | String | `_blank` | Link target (`_blank`, `_self`, etc.) |
| `homeLabel` | String | `Home` | Label for home/root directory |
| `cryptFiles` | Boolean | `false` | Enable client-side encryption (requires `'application/octet-stream'` in PHP `allowedMimeTypes`) |
| `encryptionKey` | CryptoKey | `null` | Pre-derived encryption key (if omitted, user can set password via File menu) |
| `encryptionSalt` | Uint8Array | `null` | Salt for key derivation |
| `cryptExclude` | Array | `[]` | MIME patterns to exclude from encryption (e.g., `['video/*', 'audio/*']`) |
| `showUrlOnProperties` | Boolean | `false` | Show file URL in properties dialog |
| `showHidden` | Boolean | `false` | Show hidden files (starting with `.`) |
| `sortBy` | String | `name` | Default sort field (`name`, `size`, `date`, `type`) |
| `sortOrder` | String | `asc` | Default sort order (`asc` or `desc`) |
| `autoRefresh` | Boolean/Number | `false` | Auto-refresh interval in milliseconds (e.g., `30000` for 30s) |
| `hideExtensions` | Array | `[]` | File extensions hidden from the view (e.g., `['.tmp', '.log']`) |
| `banExtensions` | Array | See below | File extensions blocked for upload/rename |
| `features` | Object | All enabled | Enable/disable specific features (deep-merged with defaults) |
| `customMenus` | Array | `[]` | Custom menu items in menu bar |
| `customContextMenu` | Array | `[]` | Custom context menu entries |
| `onInit` | Function | `null` | Callback when file manager initializes |
| `onUploadStart` | Function | `null` | Callback when upload starts |
| `onUploadProgress` | Function | `null` | Callback during upload progress |
| `onUploadComplete` | Function | `null` | Callback when upload completes |
| `onUploadError` | Function | `null` | Callback on upload error |
| `onFileOpen` | Function | `null` | Callback when file is opened |
| `onFileSelect` | Function | `null` | Callback when file is selected |
| `onError` | Function | `null` | Callback on error |
| `onChange` | Function | `null` | Callback on directory open/refresh |
| `customFileOpener` | Function | `null` | Custom file open handler (overrides default download on double-click/Enter) |

#### Default Banned Extensions

For security purposes, the following extensions are blocked by default (customize according to your needs and keep `connector.php` synchronized):

```javascript
banExtensions: [
    // Unix/Linux executables
    'sh', 'bash', 'csh', 'ksh', 'zsh', 'tcsh', 'dash',
    'pl', 'perl', 'py', 'pyc', 'pyo', 'pyw', 'pyz',
    'rb', 'rbw', 'cgi', 'fcgi',
    // Windows executables
    'exe', 'bat', 'cmd', 'com', 'scr', 'pif', 'cpl',
    'msi', 'msp', 'dll', 'ocx', 'sys',
    // Web server scripts
    'php', 'php3', 'php4', 'php5', 'phtml', 'php7',
    'asp', 'aspx', 'asa', 'aspx.cs', 'ashx',
    'jsp', 'jspx', 'jsw', 'jssp', 'do',
    'cfm', 'cfml', 'cfc',
    // Dangerous scripts
    'js', 'vbs', 'vbe', 'jse', 'wsf', 'wsh', 'ps1', 'psm1',
    // macOS dangerous
    'app', 'dmg', 'pkg', 'mpkg',
    // Config files
    'conf', 'cnf', 'ini', 'cfg', 'config',
    'htaccess', 'htpasswd', 'htgroup',
    // Macro virus (Office)
    'docm', 'xlsm', 'pptm', 'dotm', 'xltm'
]
```

#### Features Object

Control which features are enabled:

```javascript
features: {
    upload: true,       // File upload
    download: true,     // File download
    delete: true,       // Delete files/folders
    rename: true,       // Rename files/folders
    copy: true,         // Copy files/folders
    cut: true,          // Cut/move files/folders
    paste: true,        // Paste clipboard
    mkdir: true,        // Create new folder
    search: true,       // Search functionality
    info: true,         // File properties/info
    contextMenu: true   // Right-click context menu
}
```

### PHP Configuration

Configure the backend in `src/connector.php`:

```php
$config = [
    // Storage paths
    'rootPath' => '/absolute/path/to/files/',
    'rootUrl' => '/files/',

    // Quota settings
    'maxQuota' => parseFileSize('5GB'),      // User storage quota
    'maxFileSize' => parseFileSize('500MB'), // Max single file size

    // Folder access policies
    'readOnlyFolders' => ['.trash', 'system', 'config'],
    'protectedFolders' => ['.trash', 'uploads', 'backup'],
    'quotaExcludedFolders' => ['backup'],
    'uploadBlockedFolders' => ['backup'],

    // Allowed MIME types (wildcard supported)
    // Add 'application/octet-stream' if JS client-side encryption (cryptFiles) is enabled
    'allowedMimeTypes' => [
        'image/*', 'video/*', 'audio/*',
        'text/*', 'application/pdf', 'text/plain'
    ],

    // Banned extensions (keep in sync with JavaScript)
    'banExtensions' => ['php', 'phar', 'exe', 'sh', ...],

    // Per-extension granular permissions
    'extensionRestrictions' => [
        'log' => ['read' => true, 'write' => false, 'open' => false, 'delete' => false]
    ],
    'defaultExtensionPermissions' => [
        'read' => true, 'write' => true, 'open' => true, 'delete' => true
    ],

    // Disabled operations
    'disabledOperations' => [], // e.g., ['delete', 'rename']

    // Chunk upload settings
    'chunkSize' => 1048576, // 1MB chunks

    // Trash functionality
    'enableTrash' => true,
    'trashPath' => $PATHTOFILES . '.trash/',

    // Security settings
    'security' => [
        'enableTokenAuth' => false,
        'tokenSecret' => 'your-long-random-secret-key',
        'enableIPWhitelist' => false,
        'allowedIPs' => ['127.0.0.1'],
        'allowedOrigins' => [], // Explicit CORS origins (empty = same-origin only)
    ],

    // Custom authentication callback
    'authCallback' => function() {
        return [
            'id' => 'user123',
            'username' => 'john_doe',
            'quota' => 5368709120, // bytes
            'permissions' => [
                'read', 'write', 'delete', 'upload',
                'download', 'rename', 'copy', 'move',
                'mkdir', 'search', 'quota', 'info'
            ]
        ];
    },

    // Plugins configuration
    'plugins' => [
        'clamav' => ['enabled' => false, 'clamdscan_path' => '/usr/bin/clamdscan'],
        'rate_limiter' => ['enabled' => false],
        'video_editor' => ['enabled' => false, 'ffmpeg_path' => '/usr/bin/ffmpeg'],
        'ftpplugin' => ['enabled' => false],
        'publiclinks' => ['enabled' => false]
    ]
];
```

In production, `authCallback` can be integrated with variables or PHP sessions:

Variables:
```php
'authCallback' => function() use ($userId, $username, $userQuota) {
    return [
        'id' => $userId,
        'username' => $username,
        'quota' => $userQuota,
        'permissions' => [
            'read', 'write', 'delete', 'upload',
            'download', 'rename', 'copy', 'move',
            'mkdir', 'search', 'quota', 'info'
        ]
    ];
}
```

Session:
```php
'authCallback' => function() {
    if (!isset($_SESSION['user_id'])) {
        return false; // Not authenticated
    }

    return [
        'id' => $_SESSION['user_id'],
        'username' => $_SESSION['username'],
        'quota' => $_SESSION['user_quota'] ?? 5368709120,
        'permissions' => [
            'read', 'write', 'delete', 'upload',
            'download', 'rename', 'copy', 'move',
            'mkdir', 'search', 'quota', 'info'
        ]
    ];
}
```

---

## Embedding in HTML

### Basic Embedding

```html
<div id="filemanager" style="height: 600px;"></div>

<script>
    var fm = new MyFileManager('#filemanager', {
        url: 'src/connector.php'
    });
</script>
```

### With Custom Configuration

```html
<div id="filemanager"></div>

<script>
    var fm = new MyFileManager('#filemanager', {
        url: 'src/connector.php',
        lang: 'en',
        theme: 'dark',
        viewMode: 'grid',
        brandLogo: 'assets/logo.png',
        brandLink: 'https://yoursite.com',
        cryptFiles: true,
        showHidden: true,
        autoRefresh: 30000, // Refresh every 30 seconds

        onFileSelect: function(file) {
            console.log('Selected file:', file);
        },

        onUploadComplete: function(data) {
            console.log('Upload complete:', data.file);
        }
    });
</script>
```

### Fullscreen Integration

```html
<!DOCTYPE html>
<html>
<head>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        html, body { height: 100%; overflow: hidden; }
        #filemanager { height: 100vh; }
    </style>
    <link rel="stylesheet" href="css/myfilemanager.css">
</head>
<body>
    <div id="filemanager"></div>
    <script src="js/i18n.js"></script>
    <script src="js/myfilemanager.js"></script>
    <script>
        new MyFileManager('#filemanager', {
            url: 'src/connector.php'
        });
    </script>
</body>
</html>
```

### Multiple Instances

```html
<div id="filemanager1" style="height: 400px;"></div>
<div id="filemanager2" style="height: 400px;"></div>

<script>
    var fm1 = new MyFileManager('#filemanager1', {
        url: 'src/connector.php?user=user1'
    });

    var fm2 = new MyFileManager('#filemanager2', {
        url: 'src/connector.php?user=user2'
    });
</script>
```

---

## API Reference

### Core Methods

#### `open(target)`
Open a directory by base64 hash (empty string for root).

```javascript
fm.open(''); // Open root directory
fm.open('c3ViZm9sZGVy'); // Open subdirectory
```

#### `refresh()`
Refresh the current directory while preserving current selection where possible.

```javascript
fm.refresh();
```

#### `uploadFiles(fileList)`
Enqueue and upload files sequentially.

```javascript
var fileInput = document.getElementById('myFileInput');
fm.uploadFiles(fileInput.files);
```

#### `downloadSelected()` / `downloadFile(file)`
Download currently selected files or a single file object.

```javascript
fm.downloadSelected();
```

#### `deleteSelected()`
Delete currently selected files/folders (moves to trash or deletes permanently if inside trash).

```javascript
fm.deleteSelected();
```

#### `renameSelected()`
Prompt and rename the currently selected item.

```javascript
fm.renameSelected();
```

#### `copySelected()` / `cutSelected()` / `pasteClipboard()`
Clipboard file operations.

```javascript
fm.copySelected();
fm.pasteClipboard();
```

#### `createFolder()`
Prompt and create a new directory in the current path.

```javascript
fm.createFolder();
```

#### `selectAll()` / `clearSelection()`
Select all or clear selection in the current directory.

```javascript
fm.selectAll();
fm.clearSelection();
```

#### `setViewMode(mode)`
Change view mode (`'list'` or `'grid'`).

```javascript
fm.setViewMode('grid');
```

#### `setSortBy(sortBy)`
Sort items (`'name'`, `'size'`, `'date'`, `'type'`). Calling again toggles ascending/descending order.

```javascript
fm.setSortBy('date');
```

#### `setEncryptionKey(password)`
Derive and activate client-side encryption from a user password.

```javascript
fm.setEncryptionKey('my-strong-passphrase');
```

#### `applyTheme()`
Re-apply theme class after updating `fm.options.theme`.

```javascript
fm.options.theme = 'dark';
fm.applyTheme();
```

#### `startAutoRefresh(intervalMs)` / `stopAutoRefresh()`
Control automatic refresh polling.

```javascript
fm.startAutoRefresh(30000);
fm.stopAutoRefresh();
```

#### `registerPlugin(plugin)`
Register a plugin configuration object.

```javascript
fm.registerPlugin(myPluginObject);
```

#### `destroy()`
Destroy file manager instance, abort active transfers, release WakeLock, and remove document event listeners.

```javascript
fm.destroy();
```

### Event Callbacks

#### `onInit`
Called when file manager initializes.

```javascript
onInit: function() {
    console.log('File manager initialized');
}
```

#### `onFileSelect`
Called when a file is selected.

```javascript
onFileSelect: function(file) {
    console.log('Selected:', file);
}
```

#### `onFileOpen`
Called when a file is opened.

```javascript
onFileOpen: function(file) {
    console.log('Opened:', file);
}
```

#### `onUploadStart`
Called when an upload begins.

```javascript
onUploadStart: function(data) {
    console.log('Starting upload:', data.file.name);
}
```

#### `onUploadProgress`
Called periodically during chunked upload.

```javascript
onUploadProgress: function(progress) {
    console.log('Progress:', progress.percent + '%', progress.loaded, '/', progress.total);
}
```

#### `onUploadComplete`
Called when an upload finishes.

```javascript
onUploadComplete: function(data) {
    console.log('Upload complete:', data.file, data.response);
}
```

#### `onUploadError`
Called on upload error.

```javascript
onUploadError: function(data) {
    console.error('Upload error:', data.error);
}
```

#### `onError`
Called on any API/manager error.

```javascript
onError: function(error) {
    console.error('Error:', error);
}
```

#### `onChange`
Called when directory contents load or refresh.

```javascript
onChange: function(response) {
    console.log('Current directory:', response.cwd);
}
```

---

## JavaScript Plugins

### Creating Custom JavaScript Plugins

JavaScript plugins extend the file manager's functionality on the client side using `window.MyFileManagerPlugins` and `fm.registerPlugin()`.

#### Plugin Structure

```javascript
(function(window) {
    'use strict';

    /**
     * My Custom Plugin
     * @param {MyFileManager} fm - File manager instance
     * @returns {Object} Plugin instance
     */
    function MyCustomPlugin(fm) {
        var plugin = {
            name: 'MyCustomPlugin',
            version: '1.0.0',
            fileManager: fm,
            contextMenuItems: [
                {
                    action: 'my-custom-action',
                    label: '⚡ My Action',
                    condition: function(selectedFiles, fileManager) {
                        return selectedFiles.length === 1 && selectedFiles[0].mime !== 'directory';
                    },
                    handler: function(selectedFiles, fileManager) {
                        alert('Action on: ' + selectedFiles[0].name);
                    }
                }
            ]
        };

        fm.registerPlugin(plugin);
        return plugin;
    }

    if (!window.MyFileManagerPlugins) {
        window.MyFileManagerPlugins = [];
    }
    window.MyFileManagerPlugins.push(MyCustomPlugin);

})(window);
```

### Built-in JavaScript Plugins

#### Text Editor Plugin (`js/plugins/myfilemanager-text-editor.js`)
Provides in-browser text file editing with line numbers, undo/redo history, word-wrap toggle, and overwrite option.

**Supported Extensions:**
`txt`, `md`, `log`, `json`, `xml`, `csv`, `html`, `htm`, `css`, `js`, `php`, `py`, `java`, `c`, `cpp`, `h`, `sh`, `bat`, `yaml`, `yml`, `ini`, `conf`, `cfg`

#### Image Editor Plugin (`js/plugins/myfilemanager-image-editor.js`)
Provides canvas-based raster image editing (rotate 90°, flip horizontal/vertical, free crop with scaled coordinates, and save/overwrite).

**Supported Formats:**
`jpg`, `jpeg`, `png`, `gif`, `webp`, `bmp`

#### Video Editor Plugin (`js/plugins/myfilemanager-video-editor.js`)
Provides timeline trimming, cropping, resizing, and timed blur overlays processed safely on the server via `video_editor.php`.

**Supported Formats:**
`mp4`, `webm`, `avi`, `mov`, `mkv`, `flv`, `m4v`, `ogv` (outputs `mp4` or `webm`)

#### Public Link Plugin (`js/plugins/public-link-plugin.js`)
Allows users to generate and manage shareable download links (`public` or `registered` users only) with configurable expiration, wait countdown, and download limits.

---

## PHP Backend System

### Core PHP Files

#### `src/connector.php`
Main API endpoint that handles all requests:
- Authentication & Bearer/POST HMAC token validation
- Strict origin/CORS checking and IP whitelist enforcement
- Plugin loading and routing (`publiclink_*`, `video_process`, `upload`, and core commands)
- Pre-upload folder policies (`readOnlyFolders`, `uploadBlockedFolders`), quota checks, and ClamAV scanning

**API Commands:**
`open`, `upload`, `download`, `delete`, `rename`, `copy`, `cut`, `mkdir`, `search`, `info`, `restore`

#### `src/myfilemanager.php`
Core file manager class enforcing directory permissions, folder protections, quota calculations, safe RFC 5987 streaming downloads, and relative-path trash/restore handling.

#### `src/chunkuploader.php`
Handles single and chunked file uploads:
- Strict `uploadId` sanitization
- Streaming chunk assembly (`stream_copy_to_stream`) with concurrency locking
- Total assembled file size and quota enforcement across chunks
- Server-side MIME inspection (`finfo`) and automatic orphan chunk cleanup

#### `src/security.php`
Security utilities:
- `isPathSafe($path, $basePath)` - Validates relative and absolute paths against `realpath($basePath)` (blocks traversal and symlink escapes)
- `sanitizeFilename($filename)` - Strips path separators, null bytes, control chars, and trailing dots
- `sanitizeUploadId($uploadId)` - Enforces safe `[a-zA-Z0-9_-]` chunk IDs
- `generateToken($secret, $userId)` / `validateToken($token, $secret, $userId)` - Constant-time HMAC-SHA256 token generation and validation
- `isAllowedMimeType($mime, $allowed)` - Escaped wildcard MIME pattern matching
- `hasBlockedExtension($filename, $blocked)` - Detects trailing-dot and compound dangerous extensions (e.g., `aspx.cs`)

### PHP Plugin System

#### Plugin Interface (`src/plugins/plugininterface.php`)

Storage plugins implement `PluginInterface`:

```php
<?php
interface PluginInterface {
    public function __construct($config);
    public function listFiles($path);
    public function readFile($path);
    public function writeFile($path, $content);
    public function delete($path);
    public function createDirectory($path);
    public function rename($oldPath, $newPath);
    public function exists($path);
    public function getFileInfo($path);
}
```

### Creating Custom PHP Plugins

Place your plugin file in `src/plugins/<pluginname>.php` defining class `<pluginname>Plugin`, and enable it under `$config['plugins']['<pluginname>']['enabled'] = true` in `src/connector.php`.

**Built-in PHP Plugins:**
- **`clamav.php`**: Scans completed uploads using `clamdscan` and automatically destroys infected files.
- **`publiclinks.php`**: Manages 64-character hex tokens stored as `.htaccess`-protected JSON files, paired with `download.php`.
- **`video_editor.php`**: Processes video trim/crop/resize/blur via FFmpeg using strict format whitelists (`mp4`/`webm`) and atomic temporary files.
- **`rate_limiter.php`**: IP-based rate limiting for video processing using APCu or TTL-verified JSON cache files.
- **`ftpplugin.php`**: `PluginInterface` implementation for FTP/FTPS storage backends.

---

## Security Features

### End-to-End Encryption

My File Manager supports client-side encryption using **AES-256-GCM** (WebCrypto API) with **PBKDF2** (100,000 iterations, SHA-256) key derivation, and an automatic **CryptoJS** AES-256-CTR + HMAC-SHA256 fallback (loaded via `<script>` with Subresource Integrity, without `eval()`).

#### How It Works

1. **Key Derivation**: User password + random 16-byte salt → PBKDF2 (100,000 iterations) → 256-bit key
2. **Self-Contained Format**: Each encrypted file embeds an `MFMENC01` header + its own 16-byte salt + 12-byte IV + authenticated ciphertext, allowing decryption across sessions and devices using the same passphrase
3. **Storage**: Server stores only `.encrypted` ciphertext (`application/octet-stream`)
4. **Decryption**: Download → Decrypt in browser → Original file restored

#### Important Server Configuration Requirement

When `cryptFiles: true` is enabled on the client, encrypted files are uploaded with the `.encrypted` extension and MIME type `application/octet-stream`. You **must** include `'application/octet-stream'` in `$config['allowedMimeTypes']` inside `src/connector.php`, otherwise the server will reject encrypted uploads with HTTP 415:

```php
// In src/connector.php
'allowedMimeTypes' => [
    'image/*',
    'video/*',
    'audio/*',
    'text/*',
    'application/pdf',
    'application/octet-stream' // Required when cryptFiles is enabled
],
```

#### Enabling Encryption in JavaScript

Let the user enter their passphrase via the **File → Set Encryption Key** menu, or pass a pre-derived key at initialization:

```javascript
var fm = new MyFileManager('#filemanager', {
    url: 'src/connector.php',
    cryptFiles: true,
    // Optional: exclude large media files from client-side encryption
    cryptExclude: ['video/*', 'audio/*']
});
```

Or initialize programmatically with a key:

```javascript
var password = 'user-secure-password';
var salt = window.MyFileManagerCrypto.generateSalt();

window.MyFileManagerCrypto.deriveKeyFromPassword(password, salt)
    .then(function(key) {
        var fm = new MyFileManager('#filemanager', {
            url: 'src/connector.php',
            cryptFiles: true,
            encryptionKey: key,
            encryptionSalt: salt,
            cryptExclude: ['video/*', 'audio/*']
        });
    });
```

---

### Custom Authentication

Implement your own authentication logic with `authCallback` in `src/connector.php`:

```php
'authCallback' => function() {
    if (!isset($_SESSION['user_id'])) {
        return false; // Not authenticated (returns HTTP 401)
    }

    return [
        'id' => $_SESSION['user_id'],
        'username' => $_SESSION['username'],
        'quota' => 5368709120, // 5GB in bytes
        'permissions' => ['read', 'write', 'delete', 'upload', 'download']
    ];
}
```

---

### Token-Based Security

Enable HMAC-SHA256 token authentication in `src/connector.php`:

```php
'security' => [
    'enableTokenAuth' => true,
    'tokenSecret' => 'your-long-random-secret-key-change-this',
    'enableIPWhitelist' => false,
    'allowedIPs' => [],
    'allowedOrigins' => [] // Empty = same-origin only
]
```

Generate the token on the server (`src/security.php`):

```php
require_once 'src/security.php';

$userId = 'user123';
$secret = 'your-long-random-secret-key-change-this';
$token = Security::generateToken($secret, $userId);
```

Pass it to the JavaScript client (automatically sent via `Authorization: Bearer` header and POST body):

```javascript
var fm = new MyFileManager('#filemanager', {
    url: 'src/connector.php',
    token: '<?php echo htmlspecialchars($token, ENT_QUOTES, "UTF-8"); ?>'
});
```

---

### Server-Side Hardening

- **Path & Symlink Confinement**: All resolved paths (including chunk temporary directories, trash operations, video processing, and public link downloads) are verified to stay strictly inside `realpath($rootPath)`.
- **Safe File Serving**: Keep `files/` outside your web root or disable script execution (`.php`, `.html`, `.svg`) inside `files/` via web server rules.
- **CORS Policy**: Only same-origin requests or origins explicitly listed in `$config['security']['allowedOrigins']` receive credentialed CORS headers.
- **Public Links Security**: `download.php` validates 64-character hex tokens, enforces `flock()` atomic download counters, and verifies `wait_seconds` via PHP sessions.

---

## Customization

### Custom Menus and Context Menus

#### Custom Menu Bar Items

```javascript
var fm = new MyFileManager('#filemanager', {
    customMenus: [
        {
            id: 'custom',
            label: 'Tools',
            items: [
                { id: 'export', label: 'Export', icon: '📤', action: 'custom-export' },
                { id: 'settings', label: 'Settings', icon: '⚙️', action: 'custom-settings' }
            ]
        }
    ],
    onCustomMenuAction: function(action) {
        console.log('Custom action:', action);
    }
});
```

#### Custom Context Menu Items

```javascript
var fm = new MyFileManager('#filemanager', {
    customContextMenu: [
        {
            action: 'custom-share',
            label: '📧 Share Selected',
            condition: function(files) {
                return files.length > 0;
            },
            handler: function(files, fm) {
                var fileNames = files.map(function(f) { return f.name; }).join(', ');
                alert('Selected: ' + fileNames);
            }
        }
    ]
});
```

### Custom Icons

Replace default PNG icons in `assets/` with your own PNG files (`folder.png`, `file.png`, `picture.png`, `video.png`, `audio.png`, `text.png`, `archive.png`, `pdf.png`, `word.png`, `excel.png`, `powerpoint.png`, `trash-empty.png`, `trash-full.png`, and toolbar arrow icons).

### Brand Logo

```javascript
var fm = new MyFileManager('#filemanager', {
    brandLogo: 'assets/logo.png',
    brandLink: 'https://yourcompany.com',
    brandTarget: '_blank',
    homeLabel: 'My Files'
});
```

### Themes

```javascript
var fm = new MyFileManager('#filemanager', {
    theme: 'dark' // or 'light'
});

// Change theme dynamically
fm.options.theme = 'light';
fm.applyTheme();
```

---

## Internationalization (i18n)

My File Manager includes built-in translations for 18 languages:

- **en** - English
- **it** - Italian (Italiano)
- **es** - Spanish (Español)
- **de** - German (Deutsch)
- **fr** - French (Français)
- **pt** - Portuguese (Português)
- **zh** - Chinese (中文)
- **ja** - Japanese (日本語)
- **ru** - Russian (Русский)
- **ar** - Arabic
- **hi** - Hindi
- **ko** - Korean
- **nl** - Dutch
- **tr** - Turkish
- **pl** - Polish
- **sv** - Swedish
- **el** - Greek
- **vi** - Vietnamese

### Set Language

```javascript
var fm = new MyFileManager('#filemanager', {
    lang: 'it'
});
```

---

## Browser Compatibility

### Supported Browsers

| Browser | Version | Encryption Support |
|---------|---------|-------------------|
| Chrome | 90+ | ✅ WebCrypto |
| Firefox | 88+ | ✅ WebCrypto |
| Safari | 14+ | ✅ WebCrypto |
| Edge | 90+ | ✅ WebCrypto |
| Opera | 76+ | ✅ WebCrypto |
| Chrome Mobile | Latest | ✅ WebCrypto |
| Safari iOS | 14+ | ✅ WebCrypto |
| Samsung Internet | Latest | ✅ WebCrypto |

### Legacy Browser Support

For older browsers without WebCrypto API:
- CryptoJS 4.2.0 is loaded via `<script>` with Subresource Integrity (SRI) as fallback
- Uses authenticated AES-256-CTR + HMAC-SHA256 encryption
- All other features fully supported

### Required Features

- JavaScript enabled
- XMLHttpRequest / Fetch API
- File API & Blob API

---

## Contributing

We welcome contributions! Here's how you can help:

### Reporting Bugs

Open an issue on GitHub with:
- Browser and version
- PHP version
- Steps to reproduce
- Error messages
- Screenshots (if applicable)

### Suggesting Features

Open a feature request with:
- Clear description
- Use case
- Proposed implementation (optional)

### Pull Requests

1. Fork the repository
2. Create feature branch: `git checkout -b feature/amazing-feature`
3. Commit changes: `git commit -m 'Add amazing feature'`
4. Push to branch: `git push origin feature/amazing-feature`
5. Open Pull Request

#### Development Guidelines

- Follow existing code style
- Add comments in English
- Test thoroughly before submitting
- Update documentation if needed

---

## License

This project is licensed under the **MIT License**.

```
MIT License

Copyright (c) 2025 Oscar Cosimo & MYETV Team

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.
```

---

## Credits

**Developed by:**
- Oscar Cosimo
- MYETV Team

**Special Thanks:**
- Contributors and testers
- Open source community

**Built With:**
- Vanilla JavaScript (no frameworks)
- PHP 7.4+
- WebCrypto API
- CryptoJS (fallback)

---

## Support

- **Documentation**: [README.md](README.md)
- **Issues**: [GitHub Issues](https://github.com/OskarCosimo/My-File-Manager-Opensource/issues)
- **Discussions**: [GitHub Discussions](https://github.com/OskarCosimo/My-File-Manager-Opensource/discussions)

---

Made with ❤️ by Oscar Cosimo & MYETV Team

[⭐ Star us on GitHub](https://github.com/OskarCosimo/My-File-Manager-Opensource)
