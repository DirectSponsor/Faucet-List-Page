<?php
header('Content-Type: application/json');

// Get the JSON posted data
$data = json_decode(file_get_contents('php://input'), true);

// Validate the email
if (!isset($data['email']) || !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
    http_response_code(400); // Bad Request
    echo json_encode(['status' => 'error', 'message' => 'A valid email address is required.']);
    exit;
}

$email = $data['email'];
$file = 'waitlist.json';
$waitlist = [];

// Read the existing list
if (file_exists($file)) {
    $waitlist = json_decode(file_get_contents($file), true);
    // Ensure it's an array, in case the file is corrupted or empty
    if (!is_array($waitlist)) {
        $waitlist = [];
    }
}

// Check for duplicates
if (in_array($email, $waitlist)) {
    http_response_code(409); // Conflict
    echo json_encode(['status' => 'error', 'message' => 'You are already on the waitlist.']);
    exit;
}

// Add new email
$waitlist[] = $email;

// Save the updated list
// LOCK_EX prevents race conditions if two users sign up at the exact same time
if (file_put_contents($file, json_encode($waitlist, JSON_PRETTY_PRINT), LOCK_EX)) {
    echo json_encode(['status' => 'success', 'message' => 'Thank you! You have been added to the waitlist.']);
} else {
    http_response_code(500); // Internal Server Error
    echo json_encode(['status' => 'error', 'message' => 'There was an error saving your request. Please try again.']);
}
?>
