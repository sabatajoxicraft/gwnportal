# Database Operations

- Always use the safeQueryPrepare() function for prepared statements
- Begin transactions for multiple related operations
- Properly close connections and statements
- Use appropriate indexing for query optimization

## Database Structure

1. **Main Tables**:

   - `roles`: User role definitions (admin, owner, manager, student)
   - `users`: User accounts and authentication
   - `accommodations`: Housing/accommodation information
   - `students`: Student-specific details
   - `user_accommodation`: Links managers to accommodations
   - `user_devices`: Tracks devices registered to users
   - `onboarding_codes`: Codes for user registration
   - `voucher_logs`: Records of vouchers sent to users
   - `activity_log`: System activity tracking
   - `notifications`: User notification system

2. **Relationship Pattern**:
   - Users have one role (admin, owner, manager, or student)
   - Owners can manage multiple accommodations
   - Managers are associated with accommodations via user_accommodation
   - Students have user accounts and are linked to specific accommodations
   - Students can register multiple devices via user_devices
   - Onboarding codes are created by users and linked to accommodations
   - Activity and notifications are tracked for audit and communication
