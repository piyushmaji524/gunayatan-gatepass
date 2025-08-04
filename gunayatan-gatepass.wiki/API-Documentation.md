# API Documentation

The Gunayatan Gatepass System provides a RESTful API for programmatic access to gatepass data and operations. This API is primarily used for the frontend JavaScript interactions and can be extended for third-party integrations.

## 🔑 Authentication

All API endpoints require valid session authentication. Users must be logged in through the web interface before making API calls.

### Headers
```http
Content-Type: application/json
X-Requested-With: XMLHttpRequest
```

## 📡 Base URL

```
https://yourdomain.com/gatepass/api/
```

## 🔔 Notification Endpoints

### Get Notifications
Retrieve notifications for the current user.

```http
GET /api/get_notifications.php
```

**Response:**
```json
{
    "success": true,
    "notifications": [
        {
            "id": 1,
            "title": "Gatepass Approved",
            "message": "Your gatepass #GP2025001 has been approved",
            "type": "success",
            "created_at": "2025-01-04 10:30:00",
            "is_read": false
        }
    ],
    "unread_count": 5
}
```

### Get Notification Count
Get the count of unread notifications.

```http
GET /api/get_notification_count.php
```

**Response:**
```json
{
    "success": true,
    "count": 3
}
```

### Mark Notification as Read
Mark a specific notification as read.

```http
POST /api/mark_notification_read.php
```

**Request Body:**
```json
{
    "notification_id": 1
}
```

**Response:**
```json
{
    "success": true,
    "message": "Notification marked as read"
}
```

### Mark All Notifications as Read
Mark all notifications for the current user as read.

```http
POST /api/mark_all_notifications_read.php
```

**Response:**
```json
{
    "success": true,
    "message": "All notifications marked as read"
}
```

## 🔍 Search and Suggestions

### Get Item Suggestions
Get autocomplete suggestions for item names.

```http
GET /api/get_item_suggestions.php?query=steel
```

**Parameters:**
- `query` (string): Search term for item suggestions

**Response:**
```json
{
    "success": true,
    "suggestions": [
        "Steel Rod",
        "Steel Pipe",
        "Steel Sheet",
        "Steel Wire"
    ]
}
```

## 🔔 Push Notifications

### Subscribe to Push Notifications
Subscribe user to push notifications.

```http
POST /api/push-subscribe.php
```

**Request Body:**
```json
{
    "subscription": {
        "endpoint": "https://fcm.googleapis.com/fcm/send/...",
        "keys": {
            "p256dh": "...",
            "auth": "..."
        }
    }
}
```

**Response:**
```json
{
    "success": true,
    "message": "Subscription saved successfully"
}
```

### Unsubscribe from Push Notifications
Unsubscribe user from push notifications.

```http
POST /api/push-unsubscribe.php
```

**Request Body:**
```json
{
    "endpoint": "https://fcm.googleapis.com/fcm/send/..."
}
```

**Response:**
```json
{
    "success": true,
    "message": "Unsubscribed successfully"
}
```

### Send Test Notification
Send a test push notification (Admin only).

```http
POST /api/send-test-notification.php
```

**Request Body:**
```json
{
    "title": "Test Notification",
    "message": "This is a test notification",
    "user_id": 1
}
```

**Response:**
```json
{
    "success": true,
    "message": "Test notification sent"
}
```

## 📋 Gatepass Operations

### Get Gatepass Details
Retrieve detailed information about a specific gatepass.

```http
GET /api/gatepass.php?id=123
```

**Parameters:**
- `id` (integer): Gatepass ID

**Response:**
```json
{
    "success": true,
    "gatepass": {
        "id": 123,
        "gatepass_number": "GP2025001",
        "from_location": "Warehouse A",
        "to_location": "Site B",
        "material_type": "Construction",
        "purpose": "Project requirements",
        "status": "approved_by_admin",
        "created_at": "2025-01-04 09:00:00",
        "creator_name": "John Doe",
        "admin_name": "Admin User",
        "items": [
            {
                "item_name": "Steel Rod",
                "quantity": 100,
                "unit": "Pieces"
            }
        ]
    }
}
```

### Create Gatepass
Create a new gatepass (User role required).

```http
POST /api/gatepass.php
```

**Request Body:**
```json
{
    "from_location": "Warehouse A",
    "to_location": "Site B",
    "material_type": "Construction",
    "purpose": "Project requirements",
    "requested_date": "2025-01-05",
    "requested_time": "10:00",
    "items": [
        {
            "item_name": "Steel Rod",
            "quantity": 100,
            "unit": "Pieces"
        },
        {
            "item_name": "Cement Bags",
            "quantity": 50,
            "unit": "Bags"
        }
    ]
}
```

**Response:**
```json
{
    "success": true,
    "gatepass_id": 124,
    "gatepass_number": "GP2025002",
    "message": "Gatepass created successfully"
}
```

### Update Gatepass Status
Update gatepass status (Admin/Security role required).

```http
PUT /api/gatepass.php
```

**Request Body:**
```json
{
    "gatepass_id": 123,
    "action": "approve",
    "notes": "Approved for immediate dispatch"
}
```

**Possible actions:**
- `approve` - Approve gatepass (Admin only)
- `decline` - Decline gatepass (Admin only)
- `verify` - Verify gatepass (Security only)

**Response:**
```json
{
    "success": true,
    "message": "Gatepass approved successfully"
}
```

## 📊 Reports and Analytics

### Get Dashboard Statistics
Get statistics for dashboard display.

```http
GET /api/stats.php
```

**Response:**
```json
{
    "success": true,
    "stats": {
        "total_gatepasses": 247,
        "pending_approval": 12,
        "approved_today": 8,
        "verified_today": 15,
        "declined_this_month": 3
    }
}
```

### Export Gatepass Data
Export gatepass data in various formats.

```http
GET /api/export.php?format=json&date_from=2025-01-01&date_to=2025-01-31
```

**Parameters:**
- `format` (string): Export format (json, csv, pdf)
- `date_from` (date): Start date filter
- `date_to` (date): End date filter
- `status` (string): Status filter (optional)

**Response:**
```json
{
    "success": true,
    "data": [...],
    "total_records": 45,
    "export_url": "/exports/gatepasses_20250104.csv"
}
```

## 🔒 Error Handling

All API endpoints return consistent error responses:

### Error Response Format
```json
{
    "success": false,
    "error": "Error message",
    "error_code": "INVALID_REQUEST",
    "details": {
        "field": "validation error details"
    }
}
```

### Common Error Codes

| Error Code | HTTP Status | Description |
|------------|-------------|-------------|
| `UNAUTHORIZED` | 401 | User not logged in |
| `FORBIDDEN` | 403 | Insufficient permissions |
| `INVALID_REQUEST` | 400 | Invalid request data |
| `NOT_FOUND` | 404 | Resource not found |
| `VALIDATION_ERROR` | 422 | Data validation failed |
| `INTERNAL_ERROR` | 500 | Server error |

## 📝 Request/Response Examples

### Complete Gatepass Creation Example

**Request:**
```bash
curl -X POST https://yourdomain.com/gatepass/api/gatepass.php \
  -H "Content-Type: application/json" \
  -H "X-Requested-With: XMLHttpRequest" \
  -d '{
    "from_location": "Main Warehouse",
    "to_location": "Construction Site A",
    "material_type": "Construction Materials",
    "purpose": "Building foundation work",
    "requested_date": "2025-01-05",
    "requested_time": "08:00",
    "items": [
      {
        "item_name": "Portland Cement",
        "quantity": 100,
        "unit": "Bags"
      },
      {
        "item_name": "Steel Reinforcement Bars",
        "quantity": 50,
        "unit": "Pieces"
      }
    ]
  }'
```

**Response:**
```json
{
    "success": true,
    "gatepass_id": 125,
    "gatepass_number": "GP2025003",
    "message": "Gatepass created successfully",
    "status": "pending",
    "created_at": "2025-01-04 14:30:00"
}
```

## 🔧 API Rate Limiting

To prevent abuse, the API implements rate limiting:

- **General endpoints**: 100 requests per minute per user
- **Notification endpoints**: 200 requests per minute per user
- **Export endpoints**: 10 requests per hour per user

Rate limit headers are included in responses:
```http
X-RateLimit-Limit: 100
X-RateLimit-Remaining: 95
X-RateLimit-Reset: 1641308400
```

## 🧪 Testing the API

### Using JavaScript (Frontend)

```javascript
// Get notifications
async function getNotifications() {
    try {
        const response = await fetch('/api/get_notifications.php', {
            method: 'GET',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        });
        
        const data = await response.json();
        
        if (data.success) {
            console.log('Notifications:', data.notifications);
        } else {
            console.error('Error:', data.error);
        }
    } catch (error) {
        console.error('Network error:', error);
    }
}

// Create gatepass
async function createGatepass(gatpassData) {
    try {
        const response = await fetch('/api/gatepass.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify(gatpassData)
        });
        
        const data = await response.json();
        return data;
    } catch (error) {
        console.error('Error creating gatepass:', error);
        throw error;
    }
}
```

### Using PHP (Server-side)

```php
// Example API client
class GatepassAPI {
    private $base_url;
    private $session_id;
    
    public function __construct($base_url) {
        $this->base_url = rtrim($base_url, '/') . '/api/';
    }
    
    public function getNotifications() {
        $url = $this->base_url . 'get_notifications.php';
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'X-Requested-With: XMLHttpRequest'
        ]);
        
        $response = curl_exec($ch);
        curl_close($ch);
        
        return json_decode($response, true);
    }
}
```

## 📖 Related Documentation

- **[User Manual](User-Manual)** - Frontend usage guide
- **[Database Schema](Database-Schema)** - Database structure
- **[Security Features](Security-Features)** - API security measures

---

For questions about the API, please check our **[FAQ](FAQ)** or create an issue on GitHub.
