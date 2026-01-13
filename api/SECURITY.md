# SRMS API Security Implementation

## Overview
The SRMS API implements comprehensive security measures including session management, authentication, authorization, and protection against common vulnerabilities.

## Security Features

### 1. Session Security
- **HttpOnly Cookies**: Prevents XSS attacks by making session cookies inaccessible to JavaScript
- **Secure Flag**: Ensures cookies are only transmitted over HTTPS (in production)
- **Strict Mode**: Prevents session fixation attacks
- **SameSite Attribute**: CSRF protection
- **IP and User Agent Validation**: Detects and prevents session hijacking

### 2. Authentication
- **Multi-role Support**: Admin, Student, Lecturer roles with different permissions
- **Secure Password Hashing**: Uses PHP's password_hash() and password_verify() functions
- **Token-based Authentication**: Secure tokens with expiration
- **CSRF Protection**: Token validation for state-changing operations

### 3. Authorization
- **Role-based Permissions**: Different access levels for different user roles
- **Resource-level Controls**: Granular permissions for specific resources
- **Data Isolation**: Users can only access their own data when applicable

### 4. API Endpoints

#### Public Endpoints
- `GET /api/` - API information (no authentication required)

#### Authentication Endpoints
- `POST /api/login` - User authentication
- `POST /api/logout` - User logout

#### Protected Endpoints
- `GET /api/students` - Get all students (requires auth)
- `GET /api/students/{id}` - Get specific student (requires auth and permissions)
- `GET /api/programmes` - Get all programmes (requires auth)
- `GET /api/courses` - Get all courses (requires auth)
- `GET /api/results` - Get results (requires auth)
- `GET /api/results/{student_id}` - Get student results (requires auth and permissions)
- `GET /api/fees` - Get fees (requires auth)
- `GET /api/fees/{student_id}` - Get student fees (requires auth and permissions)

## Usage Examples

### Authentication
```bash
curl -X POST http://localhost/srms/api/login \
  -H "Content-Type: application/json" \
  -d '{"username": "your_username", "password": "your_password"}'
```

### Making Authenticated Requests
```bash
curl -X GET http://localhost/srms/api/students \
  -H "Authorization: Bearer YOUR_AUTH_TOKEN"
```

## Security Best Practices Implemented

1. **Input Validation**: All inputs are validated and sanitized
2. **Output Encoding**: All outputs are properly encoded to prevent XSS
3. **SQL Injection Prevention**: Prepared statements are used for all database queries
4. **Rate Limiting**: Built-in rate limiting to prevent abuse
5. **Error Handling**: Generic error messages to prevent information disclosure
6. **Secure Defaults**: Principle of least privilege for all permissions

## Security Headers Applied
- `X-Content-Type-Options: nosniff` - Prevents MIME type confusion
- `X-Frame-Options: SAMEORIGIN` - Prevents clickjacking
- `X-XSS-Protection: 1; mode=block` - Basic XSS protection