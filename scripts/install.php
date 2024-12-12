<?php
$configPath = "../doc_root/api/config.php";
if(file_exists($configPath))
    include_once $configPath;
echo "This script checks for any missing database objects and creates them. Enter Y if you wish to continue.\n";

if(rtrim(fgets(STDIN)) !== "Y")
    exit;

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
        echo "Database name is required";
}
// Execute installation code
$conn = new mysqli($dbHostname, $dbUsername, $dbPassword, $dbName);

if($conn->connect_error)
{
    echo "Error occurred while connecting to database:  $conn->connect_error";
    exit;
}
echo "Connected successfully!\n";

$table_commands = [
    'User' =>
        "CREATE TABLE `User`
        (
            `UserID` INT NOT NULL UNIQUE AUTO_INCREMENT,
            `Username` VARCHAR(15) NOT NULL UNIQUE,
            `Email` VARCHAR(40) NOT NULL UNIQUE,
            `Password` CHAR(60),
            `UserType` TINYINT NOT NULL,
            `Authorized` BIT NOT NULL,
            `RecordDate` DATETIME NOT NULL,
            PRIMARY KEY(`UserID`)
        );",
    'Class' =>
        "CREATE TABLE `Class`
        (
            `ClassID` INT NOT NULL UNIQUE AUTO_INCREMENT,
            `ClassName` VARCHAR(30) NOT NULL UNIQUE,
            `RecordDate` DATETIME NOT NULL,
            PRIMARY KEY(`ClassID`)
        );",
    'StudentClass' =>
        "CREATE TABLE `StudentClass`
        (
            `StudentID` INT NOT NULL,
            `ClassID` INT NOT NULL,
            `RecordDate` DATETIME NOT NULL,
            FOREIGN KEY(`ClassID`) REFERENCES `Class`(`ClassID`),
            FOREIGN KEY(`StudentID`) REFERENCES `User`(`UserID`),
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
            `RecordDate` DATETIME NOT NULL DEFAULT NOW(),
            PRIMARY KEY(`TrackableName`)
        );",
    'Lesson' =>
        "CREATE TABLE `Lesson`
        (
            `LessonID` INT NOT NULL UNIQUE AUTO_INCREMENT,
            `TutorID` INT NOT NULL,
            `LocationID` INT,
            `LessonStart` DATETIME NOT NULL,
            `LessonEnd` DATETIME,
            `Notes` VARCHAR(1024),
            `RecordDate` DATETIME NOT NULL DEFAULT NOW(),
            PRIMARY KEY(`LessonID`),
            FOREIGN KEY(`TutorID`) REFERENCES `User`(`UserID`) ON DELETE CASCADE,
            FOREIGN KEY(`LocationID`) REFERENCES `Location`(`LocationID`) ON DELETE SET NULL
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
            exit;
        }
        if(gettype($result) != gettype(true))
            $result->free();
        echo "Added `$key` successfully\n";
    }
    if(gettype($result) !== gettype(true))
        $result->free();
}
echo "All tables checked\n";

// Store fetched configs for later
$configStr = "<?php\n\n";
$configStr .= '$dbHostname = ' . json_encode($dbHostname) . ";\n";
$configStr .= '$dbUsername = ' . json_encode($dbUsername) . ";\n";
$configStr .= '$dbPassword = ' . json_encode($dbPassword) . ";\n";
$configStr .= '$dbName = ' . json_encode($dbName) . ";\n";

$configfile = fopen($configPath, "w");
fwrite($configfile, $configStr);
fclose($configfile);