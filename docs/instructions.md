# WiFi Management System - GitHub Copilot Instructions

## Code Style and Conventions

- Follow PHP PSR-12 coding standards
- Use camelCase for variables and functions
- Use PascalCase for classes
- Always use prepared statements for database queries
- Write meaningful variable and function names
- Include proper phpdoc comments for functions

## Security Requirements

- Always sanitize user input
- Use parameterized queries for database operations
- Validate form data on the server side
- Use password_hash() with PASSWORD_DEFAULT for storing passwords
- Always use HTTPS for sensitive data transmission
- Apply CSRF protection on forms

## Error Handling

- Log errors appropriately using error_log()
- Display user-friendly error messages
- Use try/catch blocks for critical operations
- Include proper transaction handling for database operations

## Database Operations

- Always use the safeQueryPrepare() function for prepared statements
- Begin transactions for multiple related operations
- Properly close connections and statements
- Use appropriate indexing for query optimization

## Session Management

- Use secure session configurations
- Include proper session validation
- Implement session timeout for security
- Store only necessary data in sessions

## Project Structure

- Separate business logic from presentation
- Use includes/components for reusable UI elements
- Place database operations in appropriate functions
- Follow the existing project organization pattern

## Feature Specific Guidelines

### Onboarding Process

- Support multiple user roles (manager, student)
- Maintain consistent step progression
- Validate all codes before proceeding
- Use transactions for account creation

### Dashboard Features

- Keep responsive design in mind
- Optimize queries for performance
- Follow access control patterns
- Use appropriate filtering for data display

### Daily Auto-Link Job

- Apply device migration first: `php db/migrations/apply_device_management_migration.php`
- Test safely: `php auto_link_devices.php --dry-run`
- Live run: `php auto_link_devices.php`
- First-use events are tracked in `voucher_logs.first_used_at` (and `first_used_mac` when available)
