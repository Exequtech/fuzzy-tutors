# fuzzy-tutors
An Apache-focused web app for tutor, student and lesson management.

## License

This project is licensed under the Apache License 2.0 - see the [LICENSE](LICENSE) file for details.

## Installation
### Dependencies
#### Apache
Reroute configurations for the API rely on .htaccess files in the document root, which are only properly supported by Apache.
For a similar environment to what was used in development, see how Apache is used in XAMPP.

#### MySQL
The codebase strictly uses MySQL. The most restrictive feature regarding MySQL versions was the use of 'FOR SHARE' record locks.
This requires usage of MySQL v8.0.1 or later (however, the server was only tested on versions 8.1 and 9.1), with the InnoDB storage engine. Most MySQL installs use InnoDB. Some XAMPP packages do not.

#### PHP
The site is compliant with PHP 8.2.12, and likely a few versions back. Access to the PHP CLI is also required for the execution of install scripts.
The PHP CLI must have the `mysqli` extension enabled to run the install script.

#### Composer
Composer is a package manager for PHP, and the project uses 2 Composer packages (namely json-schema for API validation and phpmailer for the forgot password feature). The composer will have to be used to install said packages in the `doc_root/api` directory.

### Steps
After dependencies are installed, the following steps can be taken from the command line.

#### 1. Clone the codebase to a place of your choosing.
Only the `doc_root` folder's contents are required for deployment, but the full repository should be intact during installation, as the install script depends on its structure.
Cloning can be done with **git**. The following command creates a `fuzzy-tutors` folder in your current directory with the source code:
```bash
git clone https://github.com/RoundRobinHood/fuzzy-tutors.git
```

#### 2. Set up a MySQL login for the program.
The program will need a user and a target database to do anything. Create a database with a user and password, and grant all privileges on the database to the user. The program only needs permissions on the database you give it. Keep the information nearby, it will be necessary for the next step.
The following example commands assume MySQL is hosted on the same machine:

Create database
```mysql
CREATE DATABASE FuzzyDB;
```
Create user
```mysql
CREATE USER 'Fuzzer'@'localhost' IDENTIFIED BY 'MyPassword';
```
Grant database privileges
```mysql
GRANT ALL PRIVILEGES ON FuzzyDB.* TO 'Fuzzer'@'localhost';
```

#### 3. Run the install script
The install script connects to the provided database to create all tables that don't exist yet, and sets up a config file in the `doc_root/api` directory.
If the script exits prematurely (e.g., due to incorrect database credentials or manual termination with Ctrl+C), no changes are made to the configuration.

With that context provided, it's completely safe to delete `doc_root/api/config.php` and re-run the install script (even if the database already has all its tables), in the event that a reconfiguration is needed or something went wrong during the install process. Doing so will require re-entering all install information.

To run it, navigate to `scripts` (the script could break if you run it from elsewhere), and execute the following command:
```
php install.php
```
This should interactively take you through the configuration process and ensure the database is ready for the server's use.

#### 4. Install composer packages
Navigate to `doc_root/api`, and run `composer install` to install the required packages.

#### 5. Serve with Apache
The program should function after step 4 if you copy the contents of `doc_root` directly into Apache's document root.
If possible, it is recommended that the folder be kept in-tact for easy updates in the future and simple reconfigurations. This requires configuring Apache to change the document root, the process of which depends on how it was installed.
Also consider configuring it to serve the following response in the event of errors (as normal errors can expose internal workings of the system):
```
{
    "status": 500,
    "message": "Internal Server Error"
}
```

## Architecture
The codebase utilizes a definitive split between back-end and front-end.
The website is served using fully static pages that instead use the API to get data, and client-side JavaScript handles dynamic interactions at runtime.
Static files can be explored in `doc_root/public` and the back-end API code in `doc_root/api`. 

For descriptions of how implemented API endpoints work, the validation schemas can be viewed in `doc_root/api/schema`.

Currently, API key authentication is not supported and the API instead uses cookie based authentication, which is targeted at browsers.
Data-changing requests require a CSRF token, which must be obtained from `/api/auth/ot-token`. This helps protect against Cross-Site Request Forgery (CSRF) attacks, where a malicious link could trick an authenticated user’s browser into making unauthorized requests.

The toplevel .htaccess file maps /api requests to `doc_root/api/index.php`, which handles the request from there. Otherwise, it maps existing files to `doc_root/public/index.php` which serves the files with e-tags for caching. `doc_root/public` can also be statically served (do delete index.php if setting it up for that).

## Security dangers
While best efforts were made in regards of security, this is a very young system written from scratch. There are likely no `glaring` security vulnerabilities (please notify if one is found), but the server lacks some control features.
Right now, the system has no API rate limits. This can be a problem for endpoints that carry consequences with them.
This makes it easier for an attacker to brute force against the login endpoint, for example.
It also means the owner of an account can get spammed with forgot-password emails endlessly if someone automates sending repeatedly to that endpoint.

To mediate this, the following changes are recommended for contributors in the future:
- Rate limits on all endpoints
- Extra login security
- A low-rate limit on emails with a large measurement window to block spam attempts
