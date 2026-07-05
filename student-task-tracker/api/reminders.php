<?php
// This script is intended to be run via a daily cron job.
// Example: 0 9 * * * /usr/bin/php /path/to/student-task-tracker/api/reminders.php

// Ensure this script can run indefinitely if there are many emails
set_time_limit(0);

require_once __DIR__ . '/config.php';

$conn = getDBConnection();

// Get tomorrow's date
$tomorrow = date('Y-m-d', strtotime('+1 day'));

try {
    // Select tasks due tomorrow that are not completed
    $stmt = $conn->prepare("
        SELECT t.id, t.title, t.dueDate, u.email, u.username 
        FROM tasks t 
        JOIN users u ON t.user_id = u.id 
        WHERE t.dueDate = ? AND t.status != 'Completed'
    ");
    $stmt->execute([$tomorrow]);
    $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $count = 0;
    foreach ($tasks as $task) {
        $userEmail = $task['email'];
        $userName = htmlspecialchars($task['username']);
        $taskTitle = htmlspecialchars($task['title']);
        $dueDate = htmlspecialchars($task['dueDate']);
        
        $subject = "Task Reminder: $taskTitle is due tomorrow!";
        $body = "
        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #e1e1e1; border-radius: 8px; background-color: #f9f9f9;'>
            <div style='text-align: center; margin-bottom: 20px;'>
                <h2 style='color: #4a90e2; margin: 0;'>Task Reminder</h2>
            </div>
            <div style='background-color: #ffffff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.05);'>
                <p style='font-size: 16px; color: #333333;'>Hello <strong>{$userName}</strong>,</p>
                <p style='font-size: 16px; color: #333333;'>This is a friendly reminder that your task is due tomorrow.</p>
                
                <div style='background-color: #f0f7ff; border-left: 4px solid #4a90e2; padding: 15px; margin: 20px 0;'>
                    <h3 style='margin: 0 0 10px 0; color: #333333;'>{$taskTitle}</h3>
                    <p style='margin: 0; color: #666666;'><strong>Due Date:</strong> {$dueDate}</p>
                </div>
                
                <p style='font-size: 16px; color: #333333;'>Please log in to your student task tracker to review and complete it on time.</p>
            </div>
        </div>
        ";
        
        if (sendEmail($userEmail, $subject, $body)) {
            $count++;
            echo "Sent reminder to $userEmail for task '{$task['title']}'\n";
        } else {
            echo "Failed to send reminder to $userEmail for task '{$task['title']}'\n";
        }
    }

    echo "Completed processing reminders. Total emails sent: $count\n";

} catch (Exception $e) {
    echo "Error processing reminders: " . $e->getMessage() . "\n";
}
?>
