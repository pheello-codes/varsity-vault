# Varsity Vault - Study Notes Marketplace

A complete web application for buying and selling university study notes, built with PHP, MySQL, HTML, CSS (Tailwind), and JavaScript.

## Features

- **User Authentication**: Register and login with secure password hashing
- **Note Marketplace**: Browse, search, and filter study notes by module and university
- **Cart System**: Add notes to cart using localStorage
- **Secure Checkout**: Simulate payment processing
- **Dashboard**: View purchased notes and manage listings
- **File Upload**: Upload PDF notes with validation
- **Review System**: Rate and review purchased notes
- **Admin Panel**: Approve or reject note submissions
- **Responsive Design**: Mobile-friendly interface with Tailwind CSS

## Setup Instructions

### Prerequisites
- XAMPP (or any web server with PHP and MySQL)
- Web browser

### Installation Steps

1. **Download and Install XAMPP**
   - Download XAMPP from https://www.apachefriends.org/
   - Install XAMPP on your computer
   - Start Apache and MySQL services from the XAMPP control panel

2. **Place the Project Files**
   - Copy the entire `varsity-vault` folder to `C:\xampp\htdocs\`
   - The full path should be: `C:\xampp\htdocs\varsity-vault\`

3. **Set Up the Database**
   - Open your web browser and go to `http://localhost/phpmyadmin`
   - Create a new database named `varsity_vault`
   - Click on the `varsity_vault` database in the left sidebar
   - Click on the "Import" tab at the top
   - Click "Choose File" and select the `setup.sql` file from your project folder
   - Click "Go" to import the database schema and sample data

4. **Configure Database Connection**
   - Copy `includes/config.example.php` to `includes/config.php`
   - Edit `includes/config.php` and fill in your actual database credentials:
     ```php
     $host = 'localhost';
     $dbname = 'varsity_vault';
     $username = 'your_db_username';
     $password = 'your_db_password';
     ```
   - Default XAMPP settings are already in the example file

5. **Configure Paystack**
   - Set Paystack environment variables in your Apache or local environment:
     - `PAYSTACK_SECRET_KEY`
     - `PAYSTACK_PUBLIC_KEY`
   - Alternatively, update `includes/config.php` directly with your Paystack keys
   - The checkout flow uses Paystack inline payment and payout transfers in ZAR

6. **Access the Application**
   - Open your web browser
   - Go to: `http://localhost/varsity-vault/`
   - You should see the Varsity Vault homepage

## Default Accounts

### Admin Account
- Email: admin@varsityvault.com
- Password: password

### Sample User Accounts
- Email: john@example.com
- Password: password

- Email: jane@example.com
- Password: password

## Security Notes

- **Never commit secrets**: The `.gitignore` file excludes sensitive files like `config.php` and uploaded files
- **Database credentials**: Use `config.example.php` as a template for your local setup
- **Production deployment**: Use environment variables or secure credential management instead of hardcoded passwords

## Project Structure

```
varsity-vault/
│
├── index.php              # Homepage with note listings
├── product.php            # Individual product page
├── checkout.php           # Checkout process
├── dashboard.php          # User dashboard
├── upload.php             # Upload notes form
├── login.php              # Login page
├── register.php           # Registration page
├── profile.php            # User profile settings
├── review.php             # Handle review submissions
│
├── setup.sql              # Database schema and sample data
│
├── includes/              # Reusable components
│   ├── config.php         # Database connection
│   ├── header.php         # HTML head and navigation
│   ├── footer.php         # HTML footer
│   └── auth_check.php     # Authentication middleware
│
├── assets/                # Static assets
│   ├── css/style.css      # Custom styles
│   └── js/
│       ├── cart.js        # Cart functionality
│       └── validation.js  # Form validation
│
├── uploads/notes/         # Uploaded PDF files
│
└── admin/                 # Admin panel
    └── index.php          # Admin dashboard
```

## Security Features

- Password hashing with `password_hash()`
- Prepared statements for all database queries
- Input sanitization and validation
- File upload restrictions (PDF only, size limits)
- Session-based authentication
- Protection against unauthorized access

## Technologies Used

- **Frontend**: HTML5, CSS3 (Tailwind CSS), JavaScript (ES6)
- **Backend**: PHP 7+ (procedural)
- **Database**: MySQL 5.7+
- **Styling**: Tailwind CSS (CDN)
- **Icons**: Heroicons (via SVG)

## Browser Support

- Chrome 70+
- Firefox 65+
- Safari 12+
- Edge 79+

## Troubleshooting

### Common Issues

1. **Database Connection Error**
   - Ensure MySQL is running in XAMPP
   - Check database credentials in `includes/config.php`
   - Verify database name matches

2. **File Upload Issues**
   - Ensure `uploads/notes/` directory exists and is writable
   - Check file permissions (should be 755 or 777)
   - Verify PHP upload settings in `php.ini`

3. **Page Not Loading**
   - Ensure Apache is running in XAMPP
   - Check URL is correct: `http://localhost/varsity-vault/`
   - Clear browser cache

4. **JavaScript Not Working**
   - Ensure JavaScript is enabled in browser
   - Check browser console for errors
   - Verify file paths in HTML

### File Permissions

Make sure the following directories have proper write permissions:
- `uploads/notes/` (for file uploads)

On Windows with XAMPP, this should work by default. If you encounter permission issues, you may need to adjust folder permissions.

## Development Notes

This application is built as a learning project demonstrating:
- PHP procedural programming
- MySQL database operations
- Secure web development practices
- Responsive web design
- Client-side JavaScript functionality

For production use, consider:
- Using a PHP framework (Laravel, Symfony)
- Implementing proper payment processing
- Adding email verification
- Setting up proper logging
- Using environment variables for configuration

## License

This project is for educational purposes only.