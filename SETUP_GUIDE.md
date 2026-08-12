# Graduate Job Connect - Setup Guide

## Prerequisites
- XAMPP (Apache + MySQL + PHP)
- Web browser
- Text editor (optional, for modifications)

## Step-by-Step Installation

### 1. Install XAMPP
1. Download XAMPP from https://www.apachefriends.org/
2. Install XAMPP with Apache, MySQL, and PHP components
3. Start XAMPP Control Panel

### 2. Setup Project Files
1. Copy the entire project folder to `C:\xampp\htdocs\`
2. Rename the folder to `job-portal` (or your preferred name)
3. The project should be located at: `C:\xampp\htdocs\job-portal\`

### 3. Start Services
1. Open XAMPP Control Panel
2. Start **Apache** service
3. Start **MySQL** service
4. Ensure both services show "Running" status

### 4. Create Database
1. Open web browser and go to: `http://localhost/phpmyadmin`
2. Click "New" to create a new database
3. Name it: `job_portal`
4. Click "Create"
5. Select the `job_portal` database
6. Click "Import" tab
7. Choose file: `database/job_portal.sql`
8. Click "Go" to import

### 5. Verify Installation
1. Open browser and go to: `http://localhost/job-portal`
2. You should see the Graduate Job Connect homepage
3. Test login with demo accounts:
   - **Admin**: admin@jobportal.com / password
   - **Employer**: employer@test.com / password
   - **Graduate**: graduate@test.com / password

## Project Structure
```
job-portal/
├── index.php              # Homepage
├── login.php              # Login page
├── register.php           # Registration
├── logout.php             # Logout handler
├── config/
│   └── database.php       # Database connection
├── includes/
│   ├── header.php         # Common header
│   ├── footer.php         # Common footer
│   └── functions.php      # Helper functions
├── admin/                 # Admin panel
├── employer/              # Employer dashboard
├── graduate/              # Graduate dashboard
├── uploads/cv/            # CV file storage
├── assets/                # CSS and JavaScript
└── database/              # SQL schema
```

## Features Included

### For Graduates:
- User registration and login
- Profile creation with skills and CV upload
- Browse available jobs
- Apply for jobs
- Track application status

### For Employers:
- Company registration and login
- Company profile management
- Post job vacancies
- View and manage applications
- Accept/reject candidates

### For Admins:
- View all users and statistics
- Manage job postings
- Delete users or jobs if needed

## Security Features
- Password hashing using PHP's password_hash()
- Prepared statements to prevent SQL injection
- Session-based authentication
- File upload validation (PDF only, 5MB limit)
- Input sanitization and validation

## Troubleshooting

### Common Issues:

1. **"Database connection failed"**
   - Ensure MySQL service is running in XAMPP
   - Check database name is `job_portal`
   - Verify database credentials in `config/database.php`

2. **"Page not found" errors**
   - Ensure Apache service is running
   - Check project is in correct htdocs folder
   - Verify file permissions

3. **CV upload not working**
   - Check `uploads/cv/` folder exists and is writable
   - Ensure file is PDF format and under 5MB
   - Verify PHP file upload settings

4. **Login issues**
   - Ensure database was imported correctly
   - Use exact demo credentials (case-sensitive)
   - Check browser cookies are enabled

### File Permissions:
- Ensure `uploads/cv/` folder is writable
- On Windows with XAMPP, this is usually automatic
- On Linux/Mac, you may need: `chmod 755 uploads/cv/`

## Demo Accounts
- **Admin**: admin@jobportal.com / password
- **Test Employer**: employer@test.com / password
- **Test Graduate**: graduate@test.com / password

## University Project Notes
- This is a complete, working job portal system
- Uses only HTML, CSS, JavaScript, PHP, and MySQL
- No frameworks used (as per requirements)
- Includes proper security measures
- Ready for demonstration and viva presentation
- Code is well-commented for easy understanding

## Presentation Points
1. **System Architecture**: 3-tier architecture (Presentation, Application, Data)
2. **Security**: Password hashing, prepared statements, input validation
3. **User Roles**: Role-based access control (Admin, Employer, Graduate)
4. **Database Design**: Normalized tables with proper relationships
5. **File Management**: Secure CV upload and storage
6. **User Experience**: Clean, responsive interface

## Support
If you encounter any issues:
1. Check XAMPP services are running
2. Verify database import was successful
3. Check file permissions
4. Review error logs in XAMPP control panel

Good luck with your university project presentation!