<?php
/**
 * Get Email Log (Development Mode)
 * Reads and parses the email log file
 */

header('Content-Type: application/json');

$log_file = __DIR__ . '/../logs/emails.log';

if (!file_exists($log_file)) {
    echo json_encode([
        'success' => false,
        'message' => 'Email log file not found',
        'emails' => []
    ]);
    exit;
}

$content = file_get_contents($log_file);
$emails = parseEmailLog($content);

echo json_encode([
    'success' => true,
    'emails' => $emails,
    'count' => count($emails)
]);

function parseEmailLog($content) {
    $emails = [];
    $entries = explode('================================================================================', $content);
    
    foreach ($entries as $entry) {
        $entry = trim($entry);
        if (empty($entry)) continue;
        
        $email = [];
        
        // Extract date
        if (preg_match('/Date: (.+)/', $entry, $matches)) {
            $email['date'] = $matches[1];
        }
        
        // Extract to
        if (preg_match('/To: (.+)/', $entry, $matches)) {
            $email['to'] = $matches[1];
        }
        
        // Extract subject
        if (preg_match('/Subject: (.+)/', $entry, $matches)) {
            $email['subject'] = $matches[1];
        }
        
        // Extract content (everything after the separator line)
        $parts = explode('--------------------------------------------------------------------------------', $entry);
        if (count($parts) > 1) {
            $email['content'] = trim($parts[1]);
            // Limit content length for display
            if (strlen($email['content']) > 500) {
                $email['content'] = substr($email['content'], 0, 500) . '...';
            }
        }
        
        if (isset($email['subject'])) {
            $emails[] = $email;
        }
    }
    
    // Return newest first
    return array_reverse($emails);
}
