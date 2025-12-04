<?php
header('Content-Type: application/json');

// Load configuration
$config = require __DIR__ . '/config.php';
$smtpConfig = $config['smtp'];

// Check Project Honey Pot before processing
function isSpamEmail($email, $apiKey) {
    if (empty($apiKey)) return false; // Skip if no API key
    
    $domain = substr(strrchr($email, "@"), 1);
    $url = "http://api.projecthoneypot.org/api?q=$domain&key=$apiKey&format=json";
    
    $response = file_get_contents($url);
    $data = json_decode($response, true);
    
    // Block suspicious domains
    return isset($data['response']['spam_score']) && $data['response']['spam_score'] > 30;
}

// Send email using PHP mail() function
function sendWelcomeEmail($email, $smtpConfig) {
    $subject = "Welcome to satoshihost.top - Free Web Hosting";
    $message = "
Hi there,

Thanks for signing up for satoshihost.top! We're excited to offer you free, full-featured web hosting in exchange for simple web tasks.

What happens next:
• We'll notify you as soon as servers are available
• Start earning points with our gamification system
• Get your first month completely free with 5000 points
• Complete simple tasks like surveys or PTC offers to maintain your account

Features you'll get:
• NVMe SSD storage with high-performance Ryzen CPUs
• DirectAdmin control panel with 1-click app installer
• Free SSL certificates and DDoS protection
• PHP 5.6+ support and MySQL databases
• Automated daily backups and malware scanning

Stay tuned - we'll email you when your hosting account is ready!

Best regards,
The satoshihost.top Team
A non-profit project by satoshihost.com, supporting clickforcharity.net
    ";
    
    $headers = "From: {$smtpConfig['from_name']} <{$smtpConfig['from_email']}>\r\n";
    $headers .= "Reply-To: {$smtpConfig['from_email']}\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
    
    return mail($email, $subject, $message, $headers);
}

// Rate limiting - check daily email limit
function checkDailyLimit($email, $dailyLimit) {
    $limitFile = 'email_limits.json';
    $limits = [];
    
    if (file_exists($limitFile)) {
        $limits = json_decode(file_get_contents($limitFile), true);
    }
    
    $today = date('Y-m-d');
    $todayCount = $limits[$today] ?? 0;
    
    if ($todayCount >= $dailyLimit) {
        return false;
    }
    
    $limits[$today] = $todayCount + 1;
    file_put_contents($limitFile, json_encode($limits), LOCK_EX);
    return true;
}

// Get the JSON posted data
$data = json_decode(file_get_contents('php://input'), true);

// Validate the email
if (!isset($data['email']) || !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'A valid email address is required.']);
    exit;
}

$email = $data['email'];

// Check against Project Honey Pot
if (isSpamEmail($email, $config['security']['honeypot_api_key'])) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'This email domain is not allowed.']);
    exit;
}

// Check daily email limit
if (!checkDailyLimit($email, $config['security']['daily_email_limit'])) {
    http_response_code(429);
    echo json_encode(['status' => 'error', 'message' => 'Daily email limit reached. Please try again tomorrow.']);
    exit;
}

$file = 'waitlist.json';
$waitlist = [];

// Read the existing list
if (file_exists($file)) {
    $waitlist = json_decode(file_get_contents($file), true);
    if (!is_array($waitlist)) {
        $waitlist = [];
    }
}

// Check for duplicates
if (in_array($email, $waitlist)) {
    http_response_code(409);
    echo json_encode(['status' => 'error', 'message' => 'You are already on the waitlist.']);
    exit;
}

// Add new email
$waitlist[] = $email;

// Save the updated list
if (file_put_contents($file, json_encode($waitlist, JSON_PRETTY_PRINT), LOCK_EX)) {
    // Send welcome email
    $emailSent = sendWelcomeEmail($email, $smtpConfig);
    
    if ($emailSent) {
        echo json_encode([
            'status' => 'success', 
            'message' => 'Thank you! You have been added to the waitlist. Check your email for details.',
            'email_sent' => true,
            'points_for_free_month' => $config['points']['first_month_free_points']
        ]);
    } else {
        echo json_encode([
            'status' => 'success', 
            'message' => 'Thank you! You have been added to the waitlist.',
            'email_sent' => false,
            'note' => 'Email delivery failed, but you\'re on the list.',
            'points_for_free_month' => $config['points']['first_month_free_points']
        ]);
    }
} else {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'There was an error saving your request. Please try again.']);
}
?>
