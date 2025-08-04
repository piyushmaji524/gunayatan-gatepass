# User Manual

Welcome to the Gunayatan Gatepass System! This comprehensive guide will help you navigate and use all features of the system effectively.

## 🚀 Getting Started

### Logging In

1. Open your web browser and navigate to the gatepass system URL
2. Enter your username and password
3. Click "Login" to access your dashboard

**Default Login Credentials** (change immediately after first login):
- **User Role**: username: `user`, password: `user123`
- **Admin Role**: username: `admin`, password: `admin123`
- **Security Role**: username: `security`, password: `security123`

### Understanding User Roles

The system has three distinct user roles:

#### 🙋‍♂️ User Role
- Create new gatepass requests
- View and track your own gatepasses
- Edit pending gatepasses
- Download approved gatepasses

#### 👨‍💼 Admin Role
- View all gatepasses in the system
- Approve or decline gatepass requests
- Manage user accounts
- Generate reports

#### 🛡️ Security Role
- Verify approved gatepasses
- Search gatepasses by number or details
- View verification history
- Access Hindi translations

## 👤 User Guide

### Dashboard Overview

When you log in as a user, you'll see your personal dashboard containing:

- **Quick Stats**: Summary of your gatepasses
- **Recent Gatepasses**: Your latest requests
- **Pending Actions**: Gatepasses awaiting your attention
- **Notifications**: Important updates and alerts

### Creating a New Gatepass

1. **Navigate to Create Gatepass**:
   - Click "Create New Gatepass" from the dashboard
   - Or use the navigation menu: "Gatepasses" → "Create New"

2. **Fill in Basic Information**:
   - **From Location**: Where materials are coming from
   - **To Location**: Destination for the materials
   - **Material Type**: Category of materials (Construction, Office Supplies, etc.)
   - **Purpose**: Reason for the material transfer
   - **Requested Date & Time**: When you need the materials to move

3. **Add Items**:
   - Click "Add Item" to add materials to your gatepass
   - **Item Name**: Use descriptive names (auto-suggestions available)
   - **Quantity**: Amount of the item
   - **Unit**: Measurement unit (Pieces, Kg, Meters, etc.)
   - Add multiple items as needed

4. **Review and Submit**:
   - Check all information is correct
   - Click "Submit Gatepass" to send for approval

### Managing Your Gatepasses

#### Viewing Gatepasses

- **All Gatepasses**: View complete list with filtering options
- **Filter by Status**: Pending, Approved, Verified, Declined
- **Search**: Find specific gatepasses by number or content

#### Gatepass Status Flow

```
Pending → Approved by Admin → Verified by Security → Complete
    ↓
Declined (if rejected at any stage)
```

#### Status Meanings

- 🟡 **Pending**: Waiting for admin approval
- 🔵 **Approved by Admin**: Ready for security verification
- 🟢 **Verified by Security**: Materials can exit, PDF available
- 🔴 **Declined**: Request rejected (see reason in details)

### Editing Gatepasses

You can edit gatepasses that are still **Pending**:

1. Go to "My Gatepasses"
2. Find the gatepass with "Pending" status
3. Click "Edit" button
4. Make necessary changes
5. Click "Update Gatepass"

⚠️ **Note**: Once approved, gatepasses cannot be edited.

### Downloading Gatepasses

For **Verified** gatepasses:

1. Go to the gatepass details page
2. Click "Download PDF" button
3. The PDF includes:
   - Complete gatepass details
   - Barcode for verification
   - Digital signatures
   - Approval timestamps

## 👨‍💼 Admin Guide

### Admin Dashboard

The admin dashboard provides comprehensive oversight:

- **System Statistics**: Total gatepasses, pending approvals, etc.
- **Recent Activity**: Latest system actions
- **Pending Approvals**: Gatepasses waiting for your decision
- **User Management**: Quick access to user controls

### Reviewing Gatepasses

1. **Navigate to Pending Gatepasses**:
   - Dashboard → "Pending Approvals"
   - Or "Gatepasses" → "All Gatepasses" → Filter by "Pending"

2. **Review Gatepass Details**:
   - Check all item details
   - Verify locations and purpose
   - Review requested timing
   - Check user's history if needed

3. **Make Decision**:
   - **Approve**: Click "Approve Gatepass"
   - **Decline**: Click "Decline" and provide a reason

### User Management

#### Creating New Users

1. Go to "Users" → "Manage Users"
2. Click "Add New User"
3. Fill in user details:
   - **Name**: Full name
   - **Username**: Login username (unique)
   - **Email**: Contact email
   - **Phone**: Contact number
   - **Role**: User, Admin, or Security
   - **Department**: User's department
4. Click "Create User"

#### Managing Existing Users

- **View All Users**: See complete user list
- **Edit User**: Update user information
- **Change Role**: Modify user permissions
- **Activate/Deactivate**: Enable or disable user accounts
- **Reset Password**: Generate new temporary password

### Generating Reports

1. **Navigate to Reports**:
   - Go to "Reports" in the main menu

2. **Select Report Type**:
   - **Gatepass Summary**: Overview of all gatepasses
   - **User Activity**: User-wise statistics
   - **Department-wise**: Department breakdown
   - **Time-based**: Date range analysis

3. **Apply Filters**:
   - Date range
   - User/Department
   - Status
   - Material type

4. **Export Options**:
   - View online
   - Download PDF
   - Export to Excel

## 🛡️ Security Guide

### Security Dashboard

Security personnel have specialized tools:

- **Verification Queue**: Gatepasses ready for verification
- **Search Tools**: Quick gatepass lookup
- **Hindi Translation**: Bilingual support
- **Verification History**: Past verification records

### Verifying Gatepasses

1. **Access Pending Verifications**:
   - Dashboard → "Pending Verifications"
   - Or scan barcode if available

2. **Review Gatepass**:
   - Check all details match physical materials
   - Verify quantities and descriptions
   - Use Hindi translations if needed
   - Confirm person presenting the gatepass

3. **Verification Actions**:
   - **Verify**: Materials can exit the premises
   - **Decline**: If discrepancies found (provide reason)

### Search and Lookup

#### Search by Gatepass Number
1. Use the search box on dashboard
2. Enter gatepass number (e.g., GP2025001)
3. Press Enter to view details

#### Advanced Search
1. Go to "Search Gatepasses"
2. Use multiple criteria:
   - Date range
   - User name
   - Material type
   - Status

### Hindi Translation Features

The system automatically provides Hindi translations for:

- **Item Names**: Materials and equipment
- **Locations**: From and to locations
- **Material Types**: Categories
- **Purpose**: Reasons for transfer

**Using Hindi Translations**:
1. Look for the 🌐 language icon
2. Hindi text appears below English
3. Both languages are displayed simultaneously

## 🔔 Notifications

### Types of Notifications

- **Gatepass Status Updates**: When status changes
- **Approval Requests**: For admins when new gatepasses are created
- **Verification Requests**: For security when gatepasses are approved
- **System Alerts**: Important system messages

### Managing Notifications

- **View All**: Click the bell icon in the header
- **Mark as Read**: Click on individual notifications
- **Mark All Read**: Use the "Mark All Read" option
- **Email Notifications**: Configured in your profile

## 📱 Mobile Usage

### Progressive Web App (PWA)

The system can be installed as a mobile app:

1. **Android/Chrome**:
   - Open the site in Chrome
   - Tap "Add to Home Screen" when prompted
   - Or use browser menu → "Add to Home Screen"

2. **iOS/Safari**:
   - Open the site in Safari
   - Tap the share button
   - Select "Add to Home Screen"

### Mobile Features

- **Responsive Design**: Works on all screen sizes
- **Touch-friendly**: Optimized for touch interactions
- **Offline Support**: Basic functionality works offline
- **Push Notifications**: Receive alerts on your device

## ⚙️ Profile Settings

### Updating Your Profile

1. Click your name in the top-right corner
2. Select "Profile" from the dropdown
3. Update information:
   - Name and contact details
   - Password (recommended to change default)
   - Notification preferences
   - Language settings

### Changing Password

1. Go to your profile
2. Click "Change Password"
3. Enter current password
4. Enter new password (use strong password)
5. Confirm new password
6. Click "Update Password"

### Notification Preferences

Configure how you receive notifications:
- **Email Notifications**: On/Off
- **Push Notifications**: Enable for mobile
- **Notification Types**: Choose which events to receive

## 🔍 Tips and Best Practices

### Creating Effective Gatepasses

1. **Be Specific**: Use detailed item descriptions
2. **Accurate Quantities**: Double-check all numbers
3. **Clear Purpose**: Explain why materials are needed
4. **Realistic Timing**: Allow adequate time for approvals

### Efficient Workflow

1. **Plan Ahead**: Create gatepasses in advance
2. **Batch Similar Items**: Group related materials together
3. **Regular Updates**: Check status regularly
4. **Keep Records**: Download PDFs for your records

### Security Best Practices

1. **Strong Passwords**: Use complex, unique passwords
2. **Logout**: Always logout when finished
3. **Verify Information**: Double-check all details
4. **Report Issues**: Contact admin for any problems

## ❓ Frequently Asked Questions

### Can I edit a gatepass after submission?
Only pending gatepasses can be edited. Once approved, no changes are allowed.

### How long does approval take?
Approval times depend on admin availability, typically within 24 hours.

### Can I create gatepasses for others?
No, each user can only create gatepasses for themselves.

### What if my gatepass is declined?
Review the decline reason and create a new gatepass addressing the issues.

### How do I get help?
Contact your system administrator or check the troubleshooting guide.

## 🆘 Getting Help

- **System Admin**: Contact your organization's admin
- **Technical Issues**: Check the [Troubleshooting Guide](Troubleshooting)
- **Feature Requests**: Submit through your admin
- **Training**: Ask admin about additional training sessions

---

**Next Steps**: Explore the [FAQ](FAQ) for common questions or check the [Troubleshooting Guide](Troubleshooting) for technical issues.
