# Human Resource Management System (HRMS)

This repository contains the source code for the HRMS application. Follow the steps below to install, configure, and run the application locally using XAMPP.

## Prerequisites

1. Download and install [XAMPP](https://www.apachefriends.org/index.html) for Windows.
2. Git installed on your system (optional, if you clone the repo).
3. A basic code editor (like VS Code).

---

## 🚀 Installation & Setup Guide

### Step 1: Copy the Project to XAMPP
By default, XAMPP serves web applications from the `htdocs` folder. 
1. Navigate to your XAMPP installation directory (usually `C:\xampp\htdocs`).
2. Copy this entire project folder (`hrm26092026`) into the `htdocs` directory.
   - The path should look like this: `C:\xampp\htdocs\hrm26092026`

### Step 2: Start XAMPP Control Panel
1. Open the **XAMPP Control Panel**.
2. Start the following modules by clicking the **Start** button next to them:
   - **Apache** (Web Server)
   - **MySQL** (Database Server)
3. Ensure both modules turn green, indicating they are running successfully.

### Step 3: Database Configuration (phpMyAdmin)
1. Open your web browser and navigate to the XAMPP database manager: [http://localhost/phpmyadmin](http://localhost/phpmyadmin).
2. Click on the **Databases** tab at the top.
3. Under "Create database", enter a name for your database (e.g., `hrmodule`) and click **Create**.
4. Click on your newly created database in the left sidebar to select it.
5. Click on the **Import** tab at the top.
6. Click **Choose File** and select the SQL dump file located in the root of this project: `hrmodule (5).sql`.
7. Scroll down and click **Import** to populate your database with the required tables and initial data.

### Step 4: Configure the Application
The application needs to know how to connect to your local MySQL database.
1. In your code editor, navigate to the `config/` directory inside the project.
2. Duplicate the `database.local.example.php` file and rename the copy to `database.local.php`.
3. Open `database.local.php` and update the connection details. For a default XAMPP installation, it should look like this:
```php
<?php
return [
    'host' => 'localhost',
    'port' => 3306,
    'database' => 'hrmodule', // The name of the database you created in Step 3
    'username' => 'root',     // Default XAMPP username is 'root'
    'password' => '',         // Default XAMPP password is empty
    'charset' => 'utf8mb4',
];
```

### Step 5: Run the Application
1. Open your web browser.
2. Navigate to your local application URL. If you placed the folder inside `htdocs` as instructed, go to:
   - [http://localhost/hrm26092026](http://localhost/hrm26092026) (Update the URL if your folder name is different).
3. The HRMS login page should now be visible!

---

## Troubleshooting

- **Database Connection Error:** Double-check that `database.local.php` exists in the `config/` folder, and that the username is `root` with a blank password. Also, ensure MySQL is running in the XAMPP Control Panel.
- **Port Conflicts:** If Apache fails to start, it's usually because another application (like Skype or VMware) is using port 80. You can change Apache's port in the XAMPP configuration, but you will need to append the port to your URL (e.g., `http://localhost:8080/hrm26092026`).
- **File Permissions:** Ensure the `storage/` directory and its subdirectories have write permissions if the application needs to upload files or save session data.

## Project Structure Overview
- `config/` - Database and application configuration files.
- `public/` - Publicly accessible assets (CSS, JS, images).
- `templates/` - PHP views and HTML templates.
- `storage/` - Uploaded files, generated reports, and session data.
- `app/` - Core application logic and controllers.
