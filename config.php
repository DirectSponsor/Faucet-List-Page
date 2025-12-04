<?php
// Configuration file - store this outside web root in production
return [
    'smtp' => [
        'host' => 'mail.satoshihost.top',
        'port' => 587,
        'username' => 'list@satoshihost.top',
        'password' => 'ljwW4LpG5We8ez4s',
        'encryption' => 'tls',
        'from_email' => 'list@satoshihost.top',
        'from_name' => 'satoshihost.top'
    ],
    'security' => [
        'honeypot_api_key' => '', // Add your Project Honey Pot API key here
        'daily_email_limit' => 10,
        'spam_score_threshold' => 30
    ],
    'points' => [
        'first_month_free_points' => 5000,
        'base_points_per_second' => 1,
        'promo_multiplier' => 2
    ]
];
?>
