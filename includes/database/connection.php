<?php
// MySQL server configuration
$servername = "localhost"; // or IP address if not local
$username = "root";
$password = "";
$database = "sql_queries";

// Create connection
$conn = new mysqli($servername, $username, $password);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Check if database exists
$result = $conn->query("SHOW DATABASES LIKE '$database'");
if ($result) {
    if ($result->num_rows == 0) {
        // Database doesn't exist, so create it
        $sql = "CREATE DATABASE $database";
        if ($conn->query($sql) === TRUE) {
            echo "Database created successfully";
            // Reconnect to the newly created database
            $conn->close();
            $conn = new mysqli($servername, $username, $password, $database);
            if ($conn->connect_error) {
                die("Connection failed: " . $conn->connect_error);
            } else {
                echo " Connected successfully to the newly created database";
            }
        } else {
            echo "Error creating database: " . $conn->error;
        }
    } else {
        // Database exists, so connect to it
        $conn = new mysqli($servername, $username, $password, $database);
        if ($conn->connect_error) {
            die("Connection failed: " . $conn->connect_error);
        } else {
            echo "Connected successfully to existing database";
        }
    }
} else {
    echo "Error checking database existence: " . $conn->error;
}

?>
