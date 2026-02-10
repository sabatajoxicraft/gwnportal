# Security Requirements

- Always sanitize user input
- Use parameterized queries for database operations
- Validate form data on the server side
- Use password_hash() with PASSWORD_DEFAULT for storing passwords
- Always use HTTPS for sensitive data transmission
- Apply CSRF protection on forms

## Authentication Flow

1. Users receive an onboarding code
2. Code validation determines user role
3. User creates account with personal details
4. System associates user with appropriate role and entity
5. Session stores user ID, role, and relevant entity IDs
