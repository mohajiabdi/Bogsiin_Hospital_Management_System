<?php
// includes/db.php
$DB_HOST = "localhost";
$DB_NAME = "hospital_db";
$DB_USER = "root";
$DB_PASS = ""; // change if you set password

try {
  $pdo = new PDO(
    "mysql:host=$DB_HOST;dbname=$DB_NAME;charset=utf8mb4",
    $DB_USER,
    $DB_PASS,
    [
      PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
      PDO::ATTR_EMULATE_PREPARES => false,
      
    ]
  );
} catch (PDOException $e) {
  die("Database connection failed: " . $e->getMessage());
}


  // "dev": "tailwindcss@3.4.17 -i assets/input.css -o assets/style.css --watch",
  //   "build": "tailwindcss@3.4.17 -i assets/input.css -o assets/style.css --minify"
  // },