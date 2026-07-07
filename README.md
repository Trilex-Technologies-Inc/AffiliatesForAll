# AffiliatesForAll

Affiliates for All is an old PHP/MySQL affiliate-program web app, originally
written for Magento-era hosting.

The full original manual is in [doc/manual.pdf](doc/manual.pdf). This file is a
short practical setup guide.

## Requirements

- Apache, preferably with `.htaccess` support.
- PHP 5.x era runtime. The original manual says PHP 5.2.
- MySQL 5.x or MariaDB.
- PHP extensions: PDO MySQL, GD, gettext, session.

Important: this code is not ready for modern PHP 8 without compatibility work.
On PHP 8.2 it will hit removed PHP functions such as `set_magic_quotes_runtime`,
`eregi`, `split`, and `each`, plus old-style constructors in bundled libraries.

## Install

1. Create a database and database user.

   Example:

   ```sql
   CREATE DATABASE affiliates CHARACTER SET utf8;
   CREATE USER 'affiliates'@'localhost' IDENTIFIED BY 'change-this-password';
   GRANT ALL PRIVILEGES ON affiliates.* TO 'affiliates'@'localhost';
   FLUSH PRIVILEGES;
   ```

2. Import the database schema.

   ```bash
   mysql -u affiliates -p affiliates < affiliates.sql
   ```

3. Create the local config file with the browser installer.

   Open:

   ```text
   http://your-domain-or-localhost/install.php
   ```

   The installer asks for the programme, database, affiliate, and system
   settings, then writes `config.inc` in the project root.

4. Or create `config.inc` manually.

   ```bash
   cp config.inc.sample config.inc
   ```

   At minimum, change these values:

   ```php
   $store_home = 'http://your-shop.example.com';
   $administrator_email_address = 'you@example.com';
   $notification_email_address = '';
   $rpc_secret = 'choose-a-long-random-secret';
   $database_dsn = 'mysql:dbname=affiliates;host=127.0.0.1';
   $database_username = 'affiliates';
   $database_password = 'change-this-password';
   ```

5. Point your web server at this project directory.

   The top-level `.htaccess` rewrites requests into `public/`, so the app opens
   at:

   ```text
   http://your-domain-or-localhost/
   ```

   If your web server uses `public/` as the document root directly, open:

   ```text
   http://your-domain-or-localhost/index.php
   ```

## First Login

The initial admin account is created by `affiliates.sql`:

```text
Username: Admin
Password: Admin
```

Change this password immediately after logging in.

## How To Use It

- Affiliates sign up from the login page.
- The admin account can manage affiliates, banners, orders, and payments.
- Affiliate links use the configured query parameters. By default:

  ```text
  ?ref=123&data=campaign-name
  ```

- Cart/order integration is done through XML-RPC at `public/xmlrpc-cart.php`.
- The bundled Magento module is under `carts/magento/`.
