# Security Policy

## Supported Versions

| Version | Supported          |
| ------- | ------------------ |
| 3.0.x   | :white_check_mark: |
| 2.0.x   | :x:                |
| 1.0.x   | :x:                |

## Reporting a Vulnerability

If you discover a security vulnerability, please report it privately to protect users:

1. **Do not** create a public GitHub issue for security vulnerabilities
2. Send an email to: [your-email@example.com] with:
   - A description of the vulnerability
   - Steps to reproduce the issue
   - Potential impact assessment
   - Any suggested fixes (if available)

## Security Response Timeline

- **Initial Response**: Within 48 hours of receiving your report
- **Status Update**: Within 7 days with an initial assessment
- **Resolution**: Security fixes will be prioritized and released as soon as possible

## Security Best Practices

When using this application:

1. **Change Default Passwords**: Always change the default admin, security, and user passwords
2. **Use HTTPS**: Deploy the application over HTTPS in production
3. **Regular Updates**: Keep your PHP, MySQL, and web server software updated
4. **File Permissions**: Ensure proper file permissions are set on the server
5. **Database Security**: Use strong database passwords and restrict database access
6. **Input Validation**: The application includes input validation, but always sanitize data at the server level
7. **Session Security**: Configure PHP sessions securely with proper timeout values

## Known Security Considerations

- File uploads are restricted to specific types and stored outside the web root
- SQL injection protection through prepared statements
- XSS protection through output escaping
- CSRF protection on forms
- Role-based access control implemented throughout the application

## Responsible Disclosure

We appreciate security researchers who responsibly disclose vulnerabilities. We commit to:

- Acknowledging your contribution in our release notes (if desired)
- Working with you to understand and fix the issue
- Keeping you informed about the progress of the fix
- Crediting you appropriately for the discovery (unless you prefer anonymity)
