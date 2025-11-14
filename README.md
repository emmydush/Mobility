# HTML Version of Inventory Management System

This directory contains the pure HTML/CSS/JavaScript implementation of the Inventory Management System, converted from the original React/TypeScript version.

## Structure

- `index.html` - Main entry point
- `login.html` - Login page
- `register.html` - Registration page
- `dashboard.html` - Dashboard page
- `products.html` - Product management page
- `stock-movements.html` - Stock movements page
- `customers.html` - Customer management page
- `reports.html` - Reports page
- `users.html` - User management page (admin only)
- `permissions.html` - Permissions management page (admin only)
- `js/` - Directory containing JavaScript files for each page

## Features

1. **Authentication** - Login and registration functionality
2. **Dashboard** - Overview of system metrics
3. **Product Management** - View, add, edit, and delete products
4. **Stock Movements** - Track inventory changes
5. **Customer Management** - Manage customer information
6. **Reports** - View system reports
7. **User Management** - Admin-only user management
8. **Permissions Management** - Fine-grained permission control for users
9. **Email Configuration** - Configure SMTP settings for email notifications
10. **System Settings** - Configure system-wide settings

## How to Run

1. Make sure you have Node.js installed
2. Install dependencies: `npm install`
3. Start the development server: `npm run dev`
4. The application will open automatically in your browser

## API Integration

The HTML version communicates with the same PHP backend API as the React version:
- Authentication: `/api/auth/login.php` and `/api/auth/register.php`
- Products: `/api/products/index.php`
- Stock Movements: `/api/stock-movements/index.php`
- Customers: `/api/customers/index.php`

## Local Storage

User authentication is handled via localStorage:
- `authToken` - User authentication token
- `user` - User information (JSON string)

## Browser Support

This implementation uses modern JavaScript features and should work in all modern browsers.