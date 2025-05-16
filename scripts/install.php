<?php

// Automated flag makes it:
// - Fail silently
// - Not write to config
// - Not expect any user input
//
// In automated mode, the script will fail if ever the:
// - DB could not be fully initialized
$automated = in_array('--auto', $argv);

$configPath = __DIR__ . "/../doc_root/api/config.php";
if(file_exists($configPath))
    include_once $configPath;
else
  echo "No config file found";

if(!$automated) {
  echo "This script performs checks and setup for the web server. This includes adding database objects and storing configurations. Continue? (Y/N)\n";

  if(rtrim(fgets(STDIN)) !== "Y")
      exit(0);
}

// Escapes for injection into config.php
// Doesn't handle bytes past ensuring syntax is fine
function EscapePHPString($str) {
    return str_replace(
        ['\\', '\"'],
        ['\\\\', '\\'],
        $str,
    );
}


if($automated) {
  if(empty($dbHostname)) {
    fwrite(STDERR, "No dbHostname");
    exit(1);
  }
  if(empty($dbUsername)) {
    fwrite(STDERR, "No dbUsername");
    exit(1);
  }
  if(empty($dbPassword)) {
    fwrite(STDERR, "No dbPassword");
    exit(1);
  }
  if(empty($dbName)) {
    fwrite(STDERR, "No dbName");
    exit(1);
  }
  if(!isset($nrEmail)) {
    fwrite(STDERR, "No nrEmail");
    exit(1);
  }
  if(!isset($nrPassword)) {
    fwrite(STDERR, "No nrPassword");
    exit(1);
  }
  if(!isset($nrHost)) {
    fwrite(STDERR, "No nrHost");
    exit(1);
  }
  if(!isset($nrEncryption)) {
    fwrite(STDERR, "No nrEncryption");
    exit(1);
  }
  if(!isset($nrPort)) {
    fwrite(STDERR, "No nrPort");
    exit(1);
  }
  if(!isset($domain)) {
    fwrite(STDERR, "No domain");
    exit(1);
  }
} else {
  // Get any unknown config information
  if(!isset($dbHostname))
  {
      echo "Please enter database host name (if empty, localhost is assumed)\n";
      $dbHostname = rtrim(fgets(STDIN));
      if($dbHostname == "")
          $dbHostname = "localhost";
  }
  if(!isset($dbUsername))
  {
      echo "Enter the database account username\n";
      while(($dbUsername = rtrim(fgets(STDIN))) == "")
          echo "Database account username is required.\n";
  }
  if(!isset($dbPassword))
  {
      echo "Enter the database account password\n";
      while(($dbPassword = rtrim(fgets(STDIN))) == "")
          echo "Database account password is required\n";
  }
  if(!isset($dbName))
  {
      echo "Enter the database name\n";
      while(($dbName = rtrim(fgets(STDIN))) == "")
          echo "Database name is required\n";
  }
  if(!isset($nrEmail))
  {
      echo "Enter the noreply email (will be used to send forgotpassword emails)\n";
      while(($nrEmail = rtrim(fgets(STDIN))) == "")
          echo "Noreply email is required\n";
  }
  if(!isset($nrPassword))
  {
      echo "Enter the password for the noreply email\n";
      while(($nrPassword = rtrim(fgets(STDIN))) == "")
          echo "Noreply password is required\n";
  }
  if(!isset($nrHost))
  {
      echo "Enter the smtp host for the nr email (e.g., smtp.gmail.com)\n";
      while(($nrHost = rtrim(fgets(STDIN))) == "")
          echo "Noreply host is required\n";
  }
  if(!isset($nrEncryption))
  {
      echo "Please provide the type of encryption (ssl/tls) for the noreply email. (Blank for no encryption: NOT RECOMMENDED)\n";
      while(true)
      {
          $nrEncryption = rtrim(fgets(STDIN));
          if(!in_array($nrEncryption, ['','tls','ssl']))
          {
              echo "Please provide ssl, tls or don't provide anything\n";
              continue;
          }

          break;
      }
  }
  if(!isset($nrPort))
  {
      echo "Please provide the port for noreply auth. (TLS usually 587/2525, SSL 465)\n";
      while(true)
      {
          $response = rtrim(fgets(STDIN));
          if(!ctype_digit($response))
          {
              echo "Please provide a number.\n";
              continue;
          }
          $nrPort = (int)$response;
          if($nrPort < 1 || $nrPort > 65535)
          {
              echo "Port numbers are between 1 and 65535\n";
              continue;
          }
          break;
      }
  }
  if(!isset($domain))
  {
      echo "Please provide the domain name of the server. This is used for no-reply emails (e.g. www.example.com)\n";
      while(($domain = rtrim(fgets(STDIN))) == "")
          echo "Domain is required\n";
  }
}

// Execute installation code
$conn = new mysqli($dbHostname, $dbUsername, $dbPassword, $dbName);

if($conn->connect_error)
{
    echo "Error occurred while connecting to database:  $conn->connect_error";
    exit(1);
}
echo "Connected successfully!\n";

$table_commands = [
    'User' =>
        "CREATE TABLE `User`
        (
            `UserID` INT NOT NULL UNIQUE AUTO_INCREMENT,
            `Username` VARCHAR(60) NOT NULL UNIQUE,
            `Email` VARCHAR(40) NOT NULL UNIQUE,
            `Password` CHAR(60),
            `UserType` TINYINT NOT NULL,
            `Authorized` BIT NOT NULL,
            `RecordDate` DATETIME NOT NULL DEFAULT NOW(),
            PRIMARY KEY(`UserID`)
        );",
    'PassToken' =>
        "CREATE TABLE `PassToken`
        (
            `Token` CHAR(64) NOT NULL,
            `UserID` INT NOT NULL,
            `RecordDate` DATETIME NOT NULL DEFAULT NOW(),
            PRIMARY KEY(`Token`),
            FOREIGN KEY(`UserID`) REFERENCES `User`(`UserID`) ON DELETE CASCADE
        );",
    'Class' =>
        "CREATE TABLE `Class`
        (
            `ClassID` INT NOT NULL UNIQUE AUTO_INCREMENT,
            `ClassName` VARCHAR(30) NOT NULL UNIQUE,
            `RecordDate` DATETIME NOT NULL DEFAULT NOW(),
            PRIMARY KEY(`ClassID`)
        );",
    'StudentClass' =>
        "CREATE TABLE `StudentClass`
        (
            `StudentID` INT NOT NULL,
            `ClassID` INT NOT NULL,
            `RecordDate` DATETIME NOT NULL DEFAULT NOW(),
            FOREIGN KEY(`ClassID`) REFERENCES `Class`(`ClassID`) ON DELETE CASCADE,
            FOREIGN KEY(`StudentID`) REFERENCES `User`(`UserID`) ON DELETE CASCADE,
            PRIMARY KEY(`StudentID`, `ClassID`)
        );",
    'Location' =>
        "CREATE TABLE `Location`
        (
            `LocationID` INT NOT NULL UNIQUE AUTO_INCREMENT,
            `LocationName` VARCHAR(30) NOT NULL UNIQUE,
            `Address` VARCHAR(255),
            `Description` VARCHAR(255),
            `RecordDate` DATETIME NOT NULL DEFAULT NOW(),
            PRIMARY KEY(`LocationID`)
        );",
    'Topic' =>
        "CREATE TABLE `Topic`
        (
            `TopicID` INT NOT NULL UNIQUE AUTO_INCREMENT,
            `SubjectID` INT,
            `TopicName` VARCHAR(30) NOT NULL,
            `Description` VARCHAR(255),
            `RecordDate` DATETIME NOT NULL DEFAULT NOW(),
            CONSTRAINT `unique_topicnames` UNIQUE (`SubjectID`, `TopicName`),
            FOREIGN KEY(`SubjectID`) REFERENCES `Topic`(`TopicID`) ON DELETE CASCADE,
            PRIMARY KEY(`TopicID`)
        );",
    'Trackable' =>
        "CREATE TABLE `Trackable`
        (
            `TrackableName` VARCHAR(30) NOT NULL UNIQUE,
            `Description` VARCHAR(255),
            `RecordDate` DATETIME NOT NULL DEFAULT NOW(),
            PRIMARY KEY(`TrackableName`)
        );",
    'Lesson' =>
        "CREATE TABLE `Lesson`
        (
            `LessonID` INT NOT NULL UNIQUE AUTO_INCREMENT,
            `TutorID` INT NOT NULL,
            `SubjectID` INT,
            `LocationID` INT,
            `LessonStart` DATETIME NOT NULL,
            `LessonEnd` DATETIME NOT NULL,
            `Notes` VARCHAR(1024),
            `RecordDate` DATETIME NOT NULL DEFAULT NOW(),
            PRIMARY KEY(`LessonID`),
            FOREIGN KEY(`TutorID`) REFERENCES `User`(`UserID`) ON DELETE CASCADE,
            FOREIGN KEY(`LocationID`) REFERENCES `Location`(`LocationID`) ON DELETE SET NULL,
            FOREIGN KEY(`SubjectID`) REFERENCES `Topic`(`TopicID`) ON DELETE SET NULL
        );",
    'Attendance' =>
        "CREATE TABLE `Attendance`
        (
            `AttendanceID` INT NOT NULL UNIQUE AUTO_INCREMENT,
            `StudentID` INT NOT NULL,
            `LessonID` INT NOT NULL,
            `Attended` BIT NOT NULL,
            `Notes` VARCHAR(1024),
            `RecordDate` DATETIME NOT NULL DEFAULT NOW(),
            CONSTRAINT `unique_attendance` UNIQUE (`StudentID`, `LessonID`),
            PRIMARY KEY(`AttendanceID`),
            FOREIGN KEY(`StudentID`) REFERENCES `User`(`UserID`) ON DELETE CASCADE,
            FOREIGN KEY(`LessonID`) REFERENCES `Lesson`(`LessonID`) ON DELETE CASCADE
        );",
    'LessonTrackable' =>
        "CREATE TABLE `LessonTrackable`
        (
            `LessonID` INT NOT NULL,
            `TrackableName` VARCHAR(30) NOT NULL,
            `RecordDate` DATETIME NOT NULL DEFAULT NOW(),
            PRIMARY KEY(`LessonID`, `TrackableName`),
            FOREIGN KEY(`LessonID`) REFERENCES `Lesson`(`LessonID`) ON DELETE CASCADE,
            FOREIGN KEY(`TrackableName`) REFERENCES `Trackable`(`TrackableName`) ON DELETE CASCADE ON UPDATE CASCADE
        );",
    'TrackableValue' =>
        "CREATE TABLE `TrackableValue`
        (
            `AttendanceID` INT NOT NULL,
            `TrackableName` VARCHAR(30) NOT NULL,
            `Value` BIT NOT NULL,
            `RecordDate` DATETIME NOT NULL DEFAULT NOW(),
            PRIMARY KEY(`AttendanceID`, `TrackableName`),
            FOREIGN KEY(`AttendanceID`) REFERENCES `Attendance`(`AttendanceID`) ON DELETE CASCADE,
            FOREIGN KEY(`TrackableName`) REFERENCES `Trackable`(`TrackableName`) ON DELETE CASCADE ON UPDATE CASCADE
        );",
    'LessonTopic' =>
        "CREATE TABLE `LessonTopic`
        (
            `LessonID` INT NOT NULL,
            `TopicID` INT NOT NULL,
            `RecordDate` DATETIME NOT NULL DEFAULT NOW(),
            PRIMARY KEY(`LessonID`, `TopicID`),
            FOREIGN KEY(`LessonID`) REFERENCES `Lesson`(`LessonID`) ON DELETE CASCADE,
            FOREIGN KEY(`TopicID`) REFERENCES `Topic`(`TopicID`) ON DELETE CASCADE
        );"
];
foreach($table_commands as $key => $value)
{
    $stmt = $conn->prepare("SHOW TABLES LIKE '$key';");
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    if($row == null)
    {
        $result = $conn->query($value);
        if(!$result)
        {
            echo "Failed to create $key table\n";
            if($conn->error)
                echo "Connection error: " . $conn->error;
            exit(1);
        }
        if(gettype($result) != gettype(true))
            $result->free();
        echo "Added `$key` table successfully\n";
    }
    if(gettype($result) !== gettype(true))
        $result->free();

    // Create user if one does not exist
    if($key === 'User') {
        $result = $conn->query("SELECT 1 FROM `User` WHERE NOT (`UserType` = 13) AND `Authorized` = 1;");
        if($result === false) {
            echo "Failed to check for user existence\n";
        } else {
            $row = $result->fetch_assoc();
            if($row === null) {
                if($automated) {
                  $username = getenv('BOOTSTRAP_USER') ?: '';
                  $email = getenv('BOOTSTRAP_EMAIL') ?: '';
                  $password = getenv('BOOTSTRAP_PASSWORD') ?: '';
                  if(empty($username))
                    echo "BOOTSTRAP_USER missing";
                  if(empty($email))
                    echo "BOOTSTRAP_EMAIL missing";
                  if(empty($password))
                    echo "BOOTSTRAP_PASSWORD missing";

                  if(empty($username) || empty($email) || empty($password)) {
                    echo "WARNING: Not enough bootstrap user info. No users will exist in db.";
                  } else {

                    $hashed = password_hash($password, PASSWORD_BCRYPT);

                    $stmt = $conn->prepare("INSERT INTO `User`(`Username`, `Email`, `UserType`, `Password`, `Authorized`) VALUES(?, ?, 42, ?, 1);");
                    $stmt->bind_param('sss', $username, $email, $hashed);
                    if(!$stmt->execute())
                        echo "Failed to create user: \n$stmt->error";
                    else
                        echo "User added successfully\n";
                  }
                } else {
                  echo "No authorized non-student users exist. Would you like to create an owner account? (Y/N)\n";
                  $answer = rtrim(fgets(STDIN));
                  if($answer === "Y" || $answer === "y")
                  {
                      echo "Username: ";
                      $username;
                      while(true)
                      {
                          $username = rtrim(fgets(STDIN));
                          if($username === "")
                          {
                              echo "Username is required.\nUsername: ";
                              continue;
                          }
                          if(!preg_match('/^[\w\-\s]{2,60}$/', $username))
                          {
                              echo "Username was invalid. Validation requirements:\n -The username must be between 2 and 60 characters long\n -The username may only contain alphanumeric characters (a-zA-Z0-9), spaces and dashes(-)\nUsername:";
                              continue;
                          }
                          break;
                      }

                      echo "Email: ";
                      $email;
                      while(true)
                      {
                          $email = rtrim(fgets(STDIN));
                          if($email === "")
                          {
                              echo "Email is required.\n";
                              continue;
                          }
                          if(!preg_match('/^(?=[^\.]+(\.[^\.]+)*@[^\.]+(\.[^\.]+)+)[\w\.]+@[\w\.]+$/', $email))
                          {
                              echo "Email was invalid.\n";
                              continue;
                          }
                          break;
                      }

                      echo "Password: ";
                      $password;
                      while(true)
                      {
                          $password = rtrim(fgets(STDIN));
                          if($password === "")
                          {
                              echo "Password is required.\nPassword: ";
                              continue;
                          }
                          echo json_encode($password);
                          if(!preg_match('/^(?=.*[A-Z])(?=.*[a-z])(?=.*[\d])(?=.*[^\w\s])[\x00-\x7F]{8,50}$/', $password))
                          {
                              echo "Password was invalid. Validation requirements:\n -The password must be between 8 and 50 characters long\n -The password must contain a capital and lower case char, a digit, and a special character\n -The password may only contain ASCII characters (no emoji's and stuff).\nPassword: ";
                              continue;
                          }
                          break;
                      }
                      $hashed = password_hash($password, PASSWORD_BCRYPT);

                      $stmt = $conn->prepare("INSERT INTO `User`(`Username`, `Email`, `UserType`, `Password`, `Authorized`) VALUES(?, ?, 42, ?, 1);");
                      $stmt->bind_param('sss', $username, $email, $hashed);
                      if(!$stmt->execute())
                          echo "Failed to create user: \n$stmt->error";
                      else
                          echo "User added successfully\n";
                  }
                }
            }
        }
    }
}
echo "All tables checked\n";

if(!$automated) {
  // Store fetched configs for later
  $configStr = "<?php\n\n";
  $configStr .= '$dbHostname = "' . EscapePHPString($dbHostname) . "\";\n";
  $configStr .= '$dbUsername = "' . EscapePHPString($dbUsername) . "\";\n";
  $configStr .= '$dbPassword = "' . EscapePHPString($dbPassword) . "\";\n";
  $configStr .= '$dbName = "' . EscapePHPString($dbName) . "\";\n";
  $configStr .= '$nrEmail = "' . EscapePHPString($nrEmail) . "\";\n";
  $configStr .= '$nrPassword = "' . EscapePHPString($nrPassword) . "\";\n";
  $configStr .= '$nrHost = "' . EscapePHPString($nrHost) . "\";\n";
  $configStr .= '$nrEncryption = "' . EscapePHPString($nrEncryption) . "\";\n";
  $configStr .= '$nrPort = ' . "$nrPort;\n";
  $configStr .= '$domain = "' . EscapePHPString($domain) . "\";\n";

  $configfile = fopen($configPath, "w");
  fwrite($configfile, $configStr);
  fclose($configfile);
}
