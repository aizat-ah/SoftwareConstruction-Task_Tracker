<?php
// This script is intended to be run via a daily cron job to remind users of overdue tasks.
// Example: 0 9 * * * /usr/bin/php /path/to/student-task-tracker/api/overdue_reminders.php

// Ensure this script can run indefinitely if there are many emails
set_time_limit(0);

require_once __DIR__ . '/config.php';

$conn = getDBConnection();

// Get today's date
$today = date('Y-m-d');

try {
    // Select tasks where the due date is strictly BEFORE today, and not completed
    $stmt = $conn->prepare("
        SELECT t.id, t.title, t.dueDate, u.email, u.username 
        FROM tasks t 
        JOIN users u ON t.user_id = u.id 
        WHERE t.dueDate < ? AND t.status != 'Completed'
    ");
    $stmt->execute([$today]);
    $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $count = 0;
    foreach ($tasks as $task) {
        $userEmail = $task['email'];
        $userName = htmlspecialchars($task['username']);
        $taskTitle = htmlspecialchars($task['title']);
        $dueDate = htmlspecialchars($task['dueDate']);
        
        $subject = "URGENT: Overdue Task Reminder - $taskTitle";
        $body = "
        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #ffcdd2; border-radius: 8px; background-color: #fffafb;'>
            <div style='text-align: center; margin-bottom: 20px;'>
                <h2 style='color: #e53935; margin: 0;'>⚠️ Overdue Task Reminder</h2>
            </div>
            <div style='background-color: #ffffff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(229,57,53,0.1);'>
                <p style='font-size: 16px; color: #333333;'>Hello <strong>{$userName}</strong>,</p>
                <p style='font-size: 16px; color: #333333;'>This is an urgent reminder that you have a task that is currently <strong>past its due date</strong>.</p>
                
                <div style='background-color: #ffebee; border-left: 4px solid #e53935; padding: 15px; margin: 20px 0;'>
                    <h3 style='margin: 0 0 10px 0; color: #333333;'>{$taskTitle}</h3>
                    <p style='margin: 0; color: #666666;'><strong>Original Due Date:</strong> {$dueDate}</p>
                </div>
                
                <p style='font-size: 16px; color: #333333;'>Please log in to your student task tracker to review and complete this task as soon as possible!</p>
            </div>
        </div>
        ";
        
        if (sendEmail($userEmail, $subject, $body)) {
            $count++;
            echo "Sent overdue reminder to $userEmail for task '{$task['title']}'\n";
        } else {
            echo "Failed to send overdue reminder to $userEmail for task '{$task['title']}'\n";
        }
    }

    echo "Completed processing overdue reminders. Total emails sent: $count\n";

} catch (Exception $e) {
    echo "Error processing overdue reminders: " . $e->getMessage() . "\n";
}
?>
