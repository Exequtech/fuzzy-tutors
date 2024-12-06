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
echo "Connected successfully!";

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
    'Topic' =>
        "CREATE TABLE `Topic`
        (
            `TopicID` INT NOT NULL UNIQUE AUTO_INCREMENT,
            `SubjectID` INT,
            `TopicName` VARCHAR(30) NOT NULL,
            `Description` VARCHAR(255),
            `RecordDate` DATETIME NOT NULL DEFAULT NOW(),
            FOREIGN KEY(`SubjectID`) REFERENCES `Topic`(`TopicID`),
            PRIMARY KEY(`TopicID`)
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