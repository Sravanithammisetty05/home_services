# Home Services Booking Platform

Core PHP + MySQL + Bootstrap 5 marketplace with three roles: **Customer**, **Worker**, **Administrator**.

## Install on XAMPP (Windows)
1. Copy this folder to `C:\xampp\htdocs\home_services` (so `index.php` is at `C:\xampp\htdocs\home_services\index.php`).
2. Open the XAMPP Control Panel and **Start** Apache and MySQL.
3. Open http://localhost/phpmyadmin, click **Import**, choose `database.sql`, and click **Import**. This creates the `home_services` database, tables and sample services.
4. Create the first administrator (one time). Open Command Prompt and run:
   `C:\xampp\php\php.exe C:\xampp\htdocs\home_services\create_admin.php`
   Enter name, email, phone and a password of 10+ characters. Then you may delete `create_admin.php`.
5. Visit http://localhost/home_services/

The site loads Bootstrap and icons from a CDN, so the computer needs internet access.

## Addresses
| Who | URL |
|---|---|
| Public site | http://localhost/home_services/ |
| Customer / worker login | http://localhost/home_services/login.php |
| Administrator login (not linked anywhere on the public site) | http://localhost/home_services/admin/admin_login.php |

## Demo accounts (password for all: `Demo@1234`)
customer@example.com (customer), ravi.plumber@example.com, anita.electric@example.com, suresh.ac@example.com (approved workers), meena.clean@example.com (worker waiting for approval). **Delete these before real use.**

## Typical workflow
Customer registers, books a service -> worker accepts (or the admin assigns one) -> worker starts and completes the job -> admin records the payment -> revenue and earnings update.

## Settings
`config/database.php` holds the database settings. XAMPP defaults (root, no password) are used unless the environment variables `HS_DB_HOST`, `HS_DB_NAME`, `HS_DB_USER`, `HS_DB_PASS`, `HS_BASE_URL`, `HS_TZ` are set. Currency symbol and default timezone are in the same file.
If you put the folder somewhere other than `htdocs/home_services`, change `HS_BASE_URL` (or the default in that file).
