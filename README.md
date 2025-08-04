# Gunayatan Gatepass System

[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)
[![PHP Version](https://img.shields.io/badge/PHP-7.4%2B-blue.svg)](https://php.net)
[![MySQL Version](https://img.shields.io/badge/MySQL-5.7%2B-orange.svg)](https://mysql.com)
[![Bootstrap](https://img.shields.io/badge/Bootstrap-5.x-purple.svg)](https://getbootstrap.com)

A comprehensive web-based system for managing material exit requests within an organization. This system facilitates a secure and efficient workflow for requesting, approving, and verifying gatepasses for materials leaving the premises.

## 🚀 Features

### 👥 Multi-Role Support

## Features

### 👥 Multi-Role Support

#### 🙋‍♂️ User Role
- ✅ Create new gatepass requests
- 📊 View and track their own gatepasses  
- ✏️ Edit pending gatepasses
- 📄 Download approved gatepasses

#### 👨‍💼 Admin Role
- 🔍 View all gatepasses in the system
- ✅❌ Approve or decline gatepass requests
- 👥 Manage user accounts (create, approve, deactivate)
- 📈 Generate comprehensive reports

#### 🛡️ Security Role
- ✅ Verify approved gatepasses
- 🔍 Search for gatepasses by number or details
- 📱 Scan barcode for quick verification
- 📋 View verification history

### 🎯 Core Features

- 🔐 Secure authentication and role-based access control
- ⏱️ Real-time status tracking of gatepasses
- 📄 PDF generation with barcode for verified gatepasses
- 📝 Activity logging for audit purposes
- 📱 Responsive design for desktop and mobile use
- 🌐 Hindi translation support for security personnel
- 🔔 Real-time notifications system
- 📊 Advanced reporting and analytics

## 🛠️ Tech Stack

- **Backend**: PHP 7.4+ ![PHP](https://img.shields.io/badge/PHP-777BB4?style=flat&logo=php&logoColor=white)
- **Database**: MySQL 5.7+ / MariaDB 10+ ![MySQL](https://img.shields.io/badge/MySQL-005C84?style=flat&logo=mysql&logoColor=white)
- **Frontend**: HTML5, CSS3, JavaScript ![HTML5](https://img.shields.io/badge/HTML5-E34F26?style=flat&logo=html5&logoColor=white) ![CSS3](https://img.shields.io/badge/CSS3-1572B6?style=flat&logo=css3&logoColor=white) ![JavaScript](https://img.shields.io/badge/JavaScript-F7DF1E?style=flat&logo=javascript&logoColor=black)
- **CSS Framework**: Bootstrap 5 ![Bootstrap](https://img.shields.io/badge/Bootstrap-563D7C?style=flat&logo=bootstrap&logoColor=white)
- **Icons**: Font Awesome 6 ![Font Awesome](https://img.shields.io/badge/Font%20Awesome-339AF0?style=flat&logo=fontawesome&logoColor=white)
- **PDF Generation**: FPDF Library

## 📦 Quick Installation

### Prerequisites
- PHP 7.4+ with mysqli extension
- MySQL 5.7+ or MariaDB 10+
- Web server (Apache/Nginx)

### Quick Start

1. **Clone the repository**
   ```bash
   git clone https://github.com/piyushmaji524/gunayatan-gatepass.git
   cd gunayatan-gatepass
   ```

2. **Database Setup**
   ```sql
   CREATE DATABASE gatepass;
   ```
   ```bash
   mysql -u root -p gatepass < database.sql
   ```

3. **Configuration**
   ```bash
   cp includes/config.php.template includes/config.php
   # Edit config.php with your database credentials
   ```

4. **Set Permissions**
   ```bash
   chmod 777 uploads/
   ```

5. **Access Application**
   - Open: `http://your-domain/gatepass-system/`
   - Login with default credentials (change immediately!)

📖 **For detailed installation instructions, see [INSTALLATION.md](INSTALLATION.md)**

## 🔑 Default Accounts

The system comes with three default accounts for testing:

| Role | Username | Password |
|------|----------|----------|
| 👨‍💼 Admin | `admin` | `admin123` |
| 🛡️ Security | `security` | `security123` |
| 🙋‍♂️ User | `user` | `user123` |

> ⚠️ **Security Notice**: Change these default passwords immediately after installation!

## 📁 Project Structure

```
gunayatan-gatepass/
├── 📁 admin/              # Admin role pages
├── 📁 assets/             # CSS, JavaScript, images
│   ├── 🎨 css/           # Stylesheets
│   ├── 📜 js/            # JavaScript files
│   └── 🖼️ img/           # Images and icons
├── 📁 api/               # REST API endpoints
├── 📁 fpdf/              # PDF generation library
├── 📁 includes/          # Core PHP files and configuration
├── 📁 security/          # Security role pages
├── 📁 templates/         # Reusable HTML templates
├── 📁 uploads/           # File uploads storage
├── 📁 user/              # User role pages
├── 🗃️ database.sql       # Database schema and sample data
├── 🏠 index.php          # Application entry point
├── 🚪 logout.php         # Logout functionality
└── 📖 README.md          # Project documentation
```

## 🔄 Workflow Process

1. **🙋‍♂️ User** creates a new gatepass request with material details
2. **👨‍💼 Admin** reviews and approves/declines the request  
3. **🔔 User** receives notification of approval/decline status
4. **🛡️ Security** verifies the approved gatepass during material exit
5. **📄 User** can download the verified gatepass PDF with barcode

## 🌟 Key Features in Detail

### 🔐 Security Features
- Role-based access control (RBAC)
- Session management with timeout
- SQL injection prevention via prepared statements
- XSS protection through output escaping
- CSRF token validation
- Secure file upload handling

### 🌐 Internationalization
- Hindi translation support for security personnel
- Automatic translation using Google Translate API
- Bilingual display (English + Hindi)
- Translation caching for improved performance

### 📱 Progressive Web App (PWA)
- Installable on mobile devices
- Offline capability for basic functions
- Push notifications support
- Responsive design for all screen sizes

### 📊 Reporting & Analytics
- Comprehensive gatepass reports
- User activity tracking
- Export functionality (PDF, Excel)
- Real-time dashboard statistics

## 🤝 Contributing

We welcome contributions! Please see our [Contributing Guidelines](CONTRIBUTING.md) for details.

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/AmazingFeature`)
3. Commit your changes (`git commit -m 'Add some AmazingFeature'`)
4. Push to the branch (`git push origin feature/AmazingFeature`)
5. Open a Pull Request

## 🐛 Bug Reports & Feature Requests

Please use [GitHub Issues](https://github.com/piyushmaji524/gunayatan-gatepass/issues) to report bugs or request features.

## 📄 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## 🔒 Security

For security-related issues, please see our [Security Policy](SECURITY.md).

## 👨‍💻 Author

**Piyush Maji**
- GitHub: [@piyushmaji524](https://github.com/piyushmaji524)
- Email: [Contact via GitHub](https://github.com/piyushmaji524)

## 🙏 Acknowledgments

- [Bootstrap](https://getbootstrap.com/) for the responsive UI framework
- [Font Awesome](https://fontawesome.com/) for the beautiful icons
- [FPDF](http://www.fpdf.org/) for PDF generation capabilities
- [Google Translate API](https://cloud.google.com/translate) for Hindi translation features

## 📞 Support

If you find this project helpful, please give it a ⭐ on GitHub!

For support and questions:
- 📚 Check the [Installation Guide](INSTALLATION.md)
- 🐛 Report bugs via [GitHub Issues](https://github.com/piyushmaji524/gunayatan-gatepass/issues)
- 💬 Start a [Discussion](https://github.com/piyushmaji524/gunayatan-gatepass/discussions) for general questions

---

Made with ❤️ for efficient organizational material management

This project is licensed under the MIT License - see the LICENSE file for details.

## Acknowledgments

- Developed for Gunayatan Organization
- Bootstrap team for the excellent CSS framework
- FPDF library for PDF generation capabilities
