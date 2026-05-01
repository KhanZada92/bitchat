<?php
/**
 * cron_check_expiry.php - Run this daily via cron job
 * 
 * Setup cron: 0 9 * * * php /path/to/cron_check_expiry.php
 * (Runs every day at 9 AM)
 */

require_once __DIR__ . '/config/main_config.php';
require_once __DIR__ . '/email_notifications.php';

echo "Starting plan expiry check...\n";
echo "Time: " . date('Y-m-d H:i:s') . "\n\n";

// Get all users with active plans (not admin)
$query = "SELECT id, username, email, plan, plan_expiry_date, email_consent 
          FROM users 
          WHERE role = 'client' 
          AND plan IS NOT NULL 
          AND plan != 'none'
          AND plan_expiry_date IS NOT NULL";

$result = $conn->query($query);

if (!$result) {
    echo "Error: " . $conn->error . "\n";
    exit(1);
}

$users_processed = 0;
$reminders_sent = 0;
$expired_sent = 0;

while ($user = $result->fetch_assoc()) {
    $users_processed++;
    $expiry_date = new DateTime($user['plan_expiry_date']);
    $today = new DateTime();
    $today->setTime(0, 0, 0);
    
    $diff = $today->diff($expiry_date);
    $days_left = $diff->days;
    
    if ($expiry_date < $today) {
        // Plan has expired
        $days_expired = abs($days_left);
        echo "EXPIRED: {$user['username']} ({$user['email']}) - expired {$days_expired} days ago\n";
        
        // Send expired notification
        if (sendExpiredNotification($conn, $user)) {
            $expired_sent++;
            echo "  ✓ Expired email sent\n";
        }
        
    } elseif ($days_left === 3) {
        // 3 days before expiry
        echo "REMINDER: {$user['username']} ({$user['email']}) - {$days_left} days left\n";
        
        // Send reminder
        if (sendExpiryReminder($conn, $user, $days_left)) {
            $reminders_sent++;
            echo "  ✓ Reminder email sent\n";
        }
    } else {
        echo "OK: {$user['username']} - {$days_left} days left (no action needed)\n";
    }
}

echo "\n--- Summary ---\n";
echo "Users processed: {$users_processed}\n";
echo "Reminders sent: {$reminders_sent}\n";
echo "Expired notifications: {$expired_sent}\n";
echo "Done.\n";

$conn->close();
