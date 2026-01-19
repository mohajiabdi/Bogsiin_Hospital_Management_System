<?php
require __DIR__ . "/../includes/db.php";

try {
    $pdo->exec("
        UPDATE appointments
        SET status = 'NO_SHOW'
        WHERE status IN ('PENDING','CONFIRMED')
          AND appointment_datetime <= NOW() - INTERVAL 24 HOUR
    ");
} catch (Throwable $e) {
    error_log("Failed to update NO_SHOW appointments: " . $e->getMessage());
}
