# Feature Specific Guidelines

## Onboarding Process

- Support multiple user roles (admin, owner, manager, student)
- Maintain consistent step progression
- Validate all codes before proceeding
- Use transactions for account creation
- Onboarding codes are tied to specific accommodations
- Codes have expiration dates and track usage status
- Support role-specific onboarding flows
- Collect appropriate user information based on role

## Dashboard Features

- Keep responsive design in mind
- Optimize queries for performance
- Follow access control patterns
- Use appropriate filtering for data display
- Display relevant notifications to users
- Show accommodation-specific information
- Track and display user activity logs
- Support device management for students

## Role-Based Access

- Different user roles have different permissions:
  - Admin: Full system access
  - Owner: Manages multiple accommodations
  - Manager: Manages specific accommodations
  - Student: Access to personal details and device management
- Owners can assign managers to accommodations
- Managers have access to student management within their accommodations
- Students have limited access to their own details and device registration

## Device Management

- Students can register multiple devices with MAC addresses
- Track device types for network management
- Enforce device limits based on accommodation policies
- Support device addition/removal workflows

## Communication System

- Support both SMS and WhatsApp communication channels
- Allow users to set preferred communication method
- Track message delivery status
- Send notifications for important system events
- Deliver WiFi vouchers through preferred channels

## Voucher System

- Generate monthly vouchers for students
- Track voucher distribution and status
- Support multiple delivery methods (SMS/WhatsApp)
- Log all voucher-related activities
