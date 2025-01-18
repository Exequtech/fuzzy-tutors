# fuzzy-tutors
An Apache-focused web app for tutor, student and lesson management.

## Installation
### Dependencies
#### Apache
Reroute configurations for the API rely on .htaccess files in the document root, which are only properly supported by Apache.
For a similar environment to what was used in development, see how Apache is used in XAMPP.

#### MySQL
The codebase strictly uses MySQL. The most version-constricting feature noted during development was usage of "FOR SHARE" record locks.
This requires usage of MySQL v8.0.1 or later, with the InnoDB storage engine. Most MySQL manual installs use InnoDB, and the XAMPP package does not.

#### PHP
The site is compliant with PHP 8.2.12, and likely a few versions back. Access to the PHP CLI is also required for the execution of install scripts.

#### Composer
Composer is a package manager for PHP, and the project uses 2 Composer packages (namely json-schema for API validation and phpmailer for the forgot password feature). The composer will have to be used to install said packages in the api directory.

### Steps
1. Clone the codebase to a place of your choosing.
Only the doc_root folder will be needed after set-up, but it is recommended to keep the folder in-tact during installation (as the install script relies on its structure).

2. Set up a MySQL login for the program.
The program will need a user and a target database to do anything. Create a database with a user and password, and grant all privileges on the database to the user. The program only needs permissions on the database you give it.

3. Run the install script
The install script sets up a config file in the doc_root/api directory, and connects to the provided database to create all tables that don't exist yet.
If it exits while fields are being filled (e.g., the database credentials were wrong or Ctrl+C was pressed to terminate the process), nothing gets written to the config.
With that context provided, it's completely safe to delete doc_root/api/config.php and re-run the install script (even if the database already has all its objects set up), in the event that a reconfiguration is needed or something went wrong during the install process.
To run it, navigate to the scripts directory (the script could break if you run it from elsewhere), and execute the following command:
```
php install.php
```
This should interactively take you through the configuration process and ensure the database is ready for the program's use.
Configuration can also be double checked in `doc_root/config.php` afterward if you want to ensure everything went smoothly.

4. Install composer packages
Navigate to doc_root/api, and run `composer install` to install the required packages.

5. Serve with Apache
The program should function after step 3 if you copy the contents of doc_root directly into Apache's document root.
If possible, it is recommended that the folder be kept in-tact for easy updates in the future and simple reconfigurations. This requires configuring Apache to change the document root, the process of which depends on how it was installed.
Also consider configuring it to serve the following response in the event of errors (as normal errors can expose internal workings of the system):
```
{
    "status": 500,
    "message": "Internal server error"
}
```

## Architecture
The codebase utilizes a definitive split between back-end and front-end.
The website is served using fully static pages that instead use the API to get data, and client-side js makes up for dynamic interactivity at runtime.
Static files can be explored in `doc_root/public` and the back-end API code in `doc_root/api`. 
For a description of how implemented API endpoints work, the validation schemas can be viewed in `doc_root/api/schema`.
Currently, API key authentication is not supported and the API instead uses cookie based authentication, which is targeted at browsers. Data-changing requests also need a token requested from /api/auth/ot-token to be put into effect (this is for Cross-Site-Request-Forgery (CSRF) security, related to maliscious links sometimes being able to forge requests via the browser of an authorized user)
The toplevel .htaccess file maps /api requests to `doc_root/api/index.php`, which handles the request from there. Otherwise, it maps existing files to `doc_root/public/index.php` which serves the files with e-tags for caching.

## Security dangers
While best efforts were made in regards of security, this is a very young system written from scratch. There are likely no `glaring` security vulnerabilities (please notify if one is found), but the server lacks some control features.
Right now, the system has no API rate limits. This can be a problem for endpoints that carry consequences with them.
This makes it easier for an attacker to brute force against the login endpoint, for example.
And it means the owner of an account can get spammed with forgot-password emails endlessly if someone automates sending repeatedly to that endpoint.

