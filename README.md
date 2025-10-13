# 🚀 Laravel Auto-Installer

A beautiful, automated installer that makes Laravel project setup as simple as clone-and-click. No more manual configuration!

![Laravel Auto-Installer](screenshots/process.png)

## ✨ What This Solves

Ever cloned a Laravel project and spent 30+ minutes on setup? This installer automates everything:

- 🔍 **System Requirements Check** - PHP, extensions, Composer, Node.js
- 🗃️ **Database Setup** - Connection testing, database creation
- ⚙️ **Environment Configuration** - Automatic .env file setup  
- 📦 **Dependency Installation** - Composer & NPM packages
- 🔑 **Security Setup** - App key generation
- 🗃️ **Database Migrations** - Schema setup
- 📊 **Real-time Progress** - Live installation tracking
- 🎨 **Beautiful UI** - Modern TailwindCSS interface

## 🚀 Quick Start

### For Project Owners:
1. 📁 **Include** `install-app.php` in your Laravel project's public directory
2. 🔧 **Update** your `index.php` with the installation check
3. 👥 **Users** can now clone and install with one click

### For Users:
```bash
# Clone any Laravel project with this installer
git clone https://github.com/alaminwebdev/laravel-auto-installer.git
cd project-name

# Access the installer via browser
# Visit: http://localhost/your-project/public/install-app.php
```

# Follow the step-by-step wizard
📁 Installation Guide

### Add to Existing Laravel Project
- Step 1: Place install-app.php in your public/ directory
- Step 2: Update your public/index.php:
```bash
php
<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Check if app is installed via .env variable
$envPath = __DIR__ . '/../.env';
$appInstalled = false;

if (file_exists($envPath)) {
    $envContent = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($envContent as $line) {
        if (strpos(trim($line), 'APP_INSTALLED=') === 0) {
            $value = trim(explode('=', $line, 2)[1] ?? '');
            $appInstalled = strtolower($value) === 'true';
            break;
        }
    }
}

if (!$appInstalled) {
    header('Location: install-app.php');
    exit;
}

// Continue with normal Laravel bootstrap...
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->handleRequest(Request::capture());
```
### Using with New Projects
- Simply include both files in your project repository. Users will be automatically redirected to the installer on first access.

###  Features
- Automated System Checks
- PHP version validation

### Required extensions (OpenSSL, PDO, Mbstring, etc.)

- Composer availability
- Node.js & npm detection
- Directory permissions
- Smart Database Setup
- Connection testing
- Automatic database creation
- Environment file configuration
- Migration execution

### Dependency Management
- Composer package installation
- NPM package installation (if package.json exists)
- Frontend asset building

###  Requirements
- PHP 8.1 or higher
- Composer (auto-detected)
- Node.js & npm (optional, for frontend assets)
- MySQL/PostgreSQL/SQLite database
- Laravel 9+ compatible


### 📸 Screenshots
Step 1: Requirements Check
![Requirements Check](screenshots/step_1.png)

Step 2: Database Setup
![Database Setup](screenshots/step_2.png)

Step 3: Installation Progress
![Installation Progress](screenshots/process.png)

Step 4: Installation Complete
![Installation Complete](screenshots/step_4.png)

