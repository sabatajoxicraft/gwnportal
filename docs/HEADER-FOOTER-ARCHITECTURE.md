# Header & Footer Architecture

## Overview

The application now uses a **Smart Router System** with 3 context-aware header/footer variants:

- **Public** - Landing pages, marketing content
- **Auth** - Login, registration, password reset
- **App** - Dashboard, authenticated pages (DEFAULT)

## Auto-Detection

Both `header.php` and `footer.php` automatically detect the page context and load the appropriate variant:

```php
// Public pages (rich marketing layout)
- index.php

// Auth pages (minimal distraction-free)
- login.php
- reset_password.php
- forgot_password.php
- register.php
- verify_email.php

// App pages (full navigation, logged-in users)
- Everything else (DEFAULT)
```

## Usage

### Standard Usage (Auto-detection)

No changes required for existing pages! Backward compatible:

```php
<?php
require_once '../includes/config.php';
require_once '../includes/components/header.php';
?>

<!-- Your page content -->

<?php require_once '../includes/components/footer.php'; ?>
```

### Manual Override

Force a specific header/footer type:

```php
<?php
require_once '../includes/config.php';

// Override auto-detection
$headerType = 'public';  // or 'auth' or 'app'
$footerType = 'public';

require_once '../includes/components/header.php';
?>

<!-- Your page content -->

<?php require_once '../includes/components/footer.php'; ?>
```

## Component Files

### Headers

| File                | Purpose         | Features                                      |
| ------------------- | --------------- | --------------------------------------------- |
| `header.php`        | Smart router    | Auto-detects context, loads variant           |
| `header-public.php` | Landing pages   | Simple navbar, login button, public links     |
| `header-auth.php`   | Auth pages      | Minimal brand header, "Need help?" link       |
| `header-app.php`    | Logged-in pages | Full navigation, flash messages, role styling |

### Footers

| File                | Purpose         | Features                                         |
| ------------------- | --------------- | ------------------------------------------------ |
| `footer.php`        | Smart router    | Auto-detects context, loads variant              |
| `footer-public.php` | Landing pages   | 4-column layout, social links, features, contact |
| `footer-auth.php`   | Auth pages      | Minimal single-line, essential links only        |
| `footer-app.php`    | Logged-in pages | System status, role-based links, user info       |

## Design Characteristics

### Public Footer

- **Layout**: 4 columns (Branding, Quick Links, Features, Contact)
- **Background**: Dark (`bg-dark text-light`)
- **Content**: Social media, marketing features, full contact info
- **Spacing**: Large padding (`py-5`)

### Auth Footer

- **Layout**: Single horizontal line
- **Background**: Light (`bg-light border-top`)
- **Content**: Copyright, Help, Contact, Privacy
- **Spacing**: Compact (`py-3`)

### App Footer

- **Layout**: 3 columns (System Status, Quick Links, User Info)
- **Background**: White with shadow (`bg-white shadow-sm`)
- **Content**:
  - Left: "System Online" indicator
  - Center: Role-based links (Admin/Help/Support)
  - Right: User name + role badge + version
- **Spacing**: Compact (`py-3`)

## Adding New Pages

When creating new pages:

1. **Public Marketing Page**

   ```php
   $headerType = 'public';
   $footerType = 'public';
   ```

2. **Auth/Login Page**
   - Add filename to `$authPages` array in `header.php` and `footer.php`
   - OR use manual override

3. **App Page (logged-in)**
   - No changes needed! Uses 'app' by default

## CSS Styling

Custom styles added to `public/assets/css/custom.css`:

- `.hover-link` - Smooth hover effects for footer links
- `.footer-public` - Dark marketing footer styles
- `.footer-auth` - Minimal auth footer styles
- `.footer-app` - App footer with system info
- Flexbox sticky footer support

## Testing Checklist

- [x] Public footer on index.php (landing)
- [x] Auth footer on login.php
- [x] App footer on dashboard.php
- [x] App footer on managers.php
- [x] App footer on accommodations.php
- [x] Header variants load correctly
- [x] Flash messages display on auth and app pages
- [x] Navigation shows on app pages only
- [x] Role-based styling works
- [x] Auto-detection logic works
- [x] Manual override works
- [x] CSS hover effects work
- [x] Responsive layout on mobile

## Benefits

✅ **Context-appropriate design** - Each context has optimized layout  
✅ **Backward compatible** - Existing pages work without changes  
✅ **Maintainable** - Single include statement, variants managed centrally  
✅ **Flexible** - Manual override available when needed  
✅ **DRY principle** - No duplicate HTML across pages  
✅ **Role-aware** - App footer shows user role and personalization

## File Locations

```
includes/components/
├── header.php           (Smart Router)
├── header-public.php    (Landing pages)
├── header-auth.php      (Login/register)
├── header-app.php       (Dashboard/app)
├── footer.php           (Smart Router)
├── footer-public.php    (Landing pages)
├── footer-auth.php      (Login/register)
└── footer-app.php       (Dashboard/app)

public/assets/css/
└── custom.css           (Added header/footer styles)
```

## Version

- **Implemented**: 2026-02-18
- **Version**: 1.0.0
- **Status**: ✅ Production Ready
