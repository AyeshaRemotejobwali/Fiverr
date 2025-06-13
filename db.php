<?php
// Database configuration
$host = 'localhost'; // Change to your actual database host (e.g., AWS RDS endpoint, '127.0.0.1', or other)
$dbname = 'db0zo8iq6rsdoj';
$username = 'uxgukysg8xcbd';
$password = '6imcip8yfmic';

try {
    // Create a MySQLi connection
    $conn = mysqli_connect($host, $username, $password, $dbname);
    
    // Check connection
    if (!$conn) {
        throw new Exception("Connection failed: " . mysqli_connect_error());
    }
    
    // Set charset to avoid encoding issues
    mysqli_set_charset($conn, 'utf8mb4');
} catch (Exception $e) {
    // Log error (in production, log to a file instead of displaying)
    error_log($e->getMessage());
    die("Database connection error. Please check your configuration or contact the administrator.");
}
?>
