# satoshihost.top Development Plan

## Table of Contents
- [Points System](#points-system)
- [Email & Security](#email--security)
- [Backend Infrastructure](#backend-infrastructure)
- [Frontend Features](#frontend-features)
- [Implementation Roadmap](#implementation-roadmap)

---

## Points System

### Overview
Implementation plan for a gamification points system similar to https://nimno.net/sites/gamification/points/ - increases 1 point per second for time spent on the site, with account-based persistence and promotional task multipliers.

## Features to Implement

### Core Functionality
- **Timer**: Increment 1 point per second while user stays on page
- **Account-based persistence**: Points stored in user account, not browser
- **Promo task multipliers**: Double points by completing promotional tasks
- **Display**: Show current points prominently on the page
- **Integration**: Award bonus points for joining waitlist

### Technical Implementation

#### JavaScript Components
```javascript
// Core timer logic with account integration
let currentPoints = 0;
let timerInterval;
let multiplier = 1; // Base multiplier
let userToken = null;

function startPointsTimer() {
    timerInterval = setInterval(() => {
        currentPoints += multiplier;
        updatePointsDisplay();
        syncPointsToServer();
    }, 1000);
}

function updatePointsDisplay() {
    // Update UI with current points and multiplier
}

function syncPointsToServer() {
    if (!userToken) return;
    
    fetch('api/points.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Authorization': `Bearer ${userToken}`
        },
        body: JSON.stringify({ 
            points: currentPoints,
            action: 'increment'
        })
    });
}

function applyMultiplier(duration, taskType) {
    multiplier = 2; // Double points
    updateMultiplierDisplay(taskType);
    
    setTimeout(() => {
        multiplier = 1; // Reset to base
        updateMultiplierDisplay(null);
    }, duration * 1000);
}

function completePromoTask(taskType) {
    // Award bonus points for completing promo tasks
    let bonusPoints = 0;
    switch(taskType) {
        case 'twitter_post':
            bonusPoints = 100;
            applyMultiplier(3600, 'twitter_post'); // 1 hour double points
            break;
        case 'facebook_share':
            bonusPoints = 150;
            applyMultiplier(7200, 'facebook_share'); // 2 hours double points
            break;
        case 'reddit_post':
            bonusPoints = 200;
            applyMultiplier(14400, 'reddit_post'); // 4 hours double points
            break;
    }
    
    currentPoints += bonusPoints;
    updatePointsDisplay();
    syncPointsToServer();
}
```

#### Backend Components
```php
// api/points.php - Points management API
<?php
header('Content-Type: application/json');

// Validate user token
$userToken = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
if (!validateUserToken($userToken)) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

// Check IP against Project Honey Pot
if (isSpamIP($_SERVER['REMOTE_ADDR'])) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Access denied']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$action = $data['action'] ?? '';

switch($action) {
    case 'increment':
        updatePoints($userToken, $data['points']);
        break;
    case 'promo_task':
        awardPromoBonus($userToken, $data['taskType']);
        break;
    case 'get_balance':
        echo json_encode(['points' => getPoints($userToken)]);
        break;
}

// Project Honey Pot integration
function isSpamIP($ip) {
    $apiKey = 'YOUR_HONEYPOT_API_KEY';
    $url = "http://api.projecthoneypot.org/api?q=$ip&key=$apiKey&format=json";
    
    $response = file_get_contents($url);
    $data = json_decode($response, true);
    
    // Block IPs with high spam score
    return isset($data['response']['spam_score']) && $data['response']['spam_score'] > 50;
}
?>
```

// Enhanced subscribe.php with spam protection
```php
<?php
header('Content-Type: application/json');

// Check Project Honey Pot before processing
function isSpamEmail($email) {
    $apiKey = 'YOUR_HONEYPOT_API_KEY';
    $domain = substr(strrchr($email, "@"), 1);
    $url = "http://api.projecthoneypot.org/api?q=$domain&key=$apiKey&format=json";
    
    $response = file_get_contents($url);
    $data = json_decode($response, true);
    
    // Block suspicious domains
    return isset($data['response']['spam_score']) && $data['response']['spam_score'] > 30;
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
if (isSpamEmail($email)) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'This email domain is not allowed.']);
    exit;
}

// Continue with existing waitlist logic...
?>
```

#### UI Elements
- Points counter display with current multiplier status
- Visual feedback when points increase
- Promo task completion buttons
- Bonus notification for waitlist signup
- Active multiplier indicator with timer

#### Integration Points
- **Waitlist signup**: Award 100 bonus points for joining
- **Twitter posts**: 100 bonus points + 1x multiplier for 1 hour
- **Facebook shares**: 150 bonus points + 1x multiplier for 2 hours  
- **Reddit posts**: 200 bonus points + 1x multiplier for 4 hours
- **Account system**: Points persist across devices and sessions

### File Modifications Required

#### index.html
- Add points display element with multiplier indicator
- Include points system JavaScript
- Add promo task buttons section
- Add login/signup integration

#### New Files
- `points.js` - Core points system logic
- `points.css` - Styling for points display (minimal)
- `api/points.php` - Backend points management API
- `users.json` - User account storage (temporary solution)

#### Database Schema (Future)
```sql
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    email VARCHAR(255) UNIQUE NOT NULL,
    token VARCHAR(255) UNIQUE NOT NULL,
    points INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE promo_tasks (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    task_type VARCHAR(50) NOT NULL,
    completed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    bonus_points INT NOT NULL,
    multiplier_duration INT NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id)
);
```

### Design Considerations

#### Philosophy Alignment
- **Account-based**: Points stored in user accounts, not localStorage
- **Lightweight**: Minimal JavaScript footprint
- **Progressive enhancement**: Basic functionality works without JavaScript
- **Privacy-conscious**: User data stored securely, not exposed

#### Performance
- Efficient timer management with server sync
- Minimal DOM updates
- Optimized API calls (batch updates)
- Caching for offline functionality

#### User Experience
- Clear points display with multiplier status
- Easy promo task completion flow
- Visual feedback for achievements
- Cross-device synchronization

### Implementation Steps

1. **Create user account system** (extend waitlist to accounts)
2. **Create points.js** with core timer and multiplier logic
3. **Add points display** to index.html with multiplier indicator
4. **Implement api/points.php** for server-side points management
5. **Add promo task buttons** with verification system
6. **Integrate with waitlist** to create accounts
7. **Add visual feedback** and animations
8. **Test across browsers** for compatibility

### Future Enhancements

#### Potential Features
- Leaderboard system (top users display)
- Point redemption for hosting features
- Referral bonuses for bringing new users
- Daily/weekly point challenges
- Premium promo tasks with higher multipliers

#### Technical Debt
- Implement proper database (MySQL/PostgreSQL)
- Add rate limiting for API endpoints
- Implement proper authentication system
- Add analytics for point system engagement
- Mobile app integration

### Security Considerations

- **API authentication**: Validate user tokens on all requests
- **Rate limiting**: Prevent abuse of point systems
- **Input validation**: Sanitize all user inputs
- **Anti-cheat measures**: Verify promo task completion
- **Secure token generation**: Use cryptographically secure tokens
- **Spam protection**: Integrate Project Honey Pot API to block known spam addresses
- **Email verification**: Verify user emails to prevent fake accounts
- **IP-based restrictions**: Limit multiple accounts from same IP
- **CAPTCHA integration**: Add CAPTCHA for form submissions

### Dependencies

#### Frontend
- **Zero external dependencies** - vanilla JavaScript only
- **Modern browser APIs**: fetch API, localStorage for caching
- **CSS Grid/Flexbox**: For responsive layout

#### Backend
- **PHP 7.4+**: For API endpoints
- **JSON storage**: Temporary solution (users.json)
- **Future database**: MySQL/PostgreSQL for production

### Testing Checklist

- [ ] Timer increments correctly (1 point/second)
- [ ] Multipliers apply and expire correctly
- [ ] Points persist across sessions via account
- [ ] Promo tasks award correct bonuses
- [ ] API endpoints handle authentication properly
- [ ] UI updates smoothly without performance issues
- [ ] Works without JavaScript (graceful degradation)
- [ ] Mobile-friendly display
- [ ] Cross-browser compatibility
- [ ] Points sync across multiple devices

---

## Email & Security

### Email System Requirements

**SMTP Configuration (Completed):**
- SMTP Server: mail.satoshihost.top:587
- Username: list@satoshihost.top
- Password: ljwW4LpG5We8ez4s
- Encryption: TLS
- From: list@satoshihost.top

**Email Features:**
- User verification emails
- Welcome confirmation with points system info
- Notification system for points milestones
- First month free at 5000 points incentive
- Daily email limit: 10 emails per day (DirectAdmin configurable)

**Email Templates:**
- **Welcome Email**: Thanks for signing up + notification when servers available
- **Verification Email**: Confirm email address to activate account
- **Milestone Email**: Points achievements and multiplier unlocks
- **Server Ready Email**: Invitation to claim hosting

### Security Implementation

**Project Honey Pot Integration:**
- IP-based spam detection for API access
- Email domain validation for signups
- Configurable spam score thresholds
- Real-time validation before processing

**Additional Security Measures:**
- Email verification system to prevent fake accounts
- IP-based restrictions for multiple accounts
- CAPTCHA integration for form submissions
- Rate limiting to prevent abuse
- Secure token generation for API authentication

**Security Testing:**
- [ ] Project Honey Pot blocks spam IPs/emails
- [ ] Email verification system works
- [ ] Rate limiting prevents abuse
- [ ] CAPTCHA integration functions
- [ ] API authentication is secure

---

## Backend Infrastructure

### Current Architecture
- **PHP 7.4+** for server-side processing
- **JSON file storage** for temporary data persistence
- **RESTful API design** for frontend-backend communication

### Required Files
- `subscribe.php` - Enhanced with spam protection
- `api/points.php` - Points management API
- `api/auth.php` - User authentication
- `users.json` - Temporary user storage
- `waitlist.json` - Email waitlist storage

### Database Schema (Future)
```sql
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    email VARCHAR(255) UNIQUE NOT NULL,
    token VARCHAR(255) UNIQUE NOT NULL,
    points INT DEFAULT 0,
    verified BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE promo_tasks (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    task_type VARCHAR(50) NOT NULL,
    completed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    bonus_points INT NOT NULL,
    multiplier_duration INT NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id)
);
```

---

## Frontend Features

### Current Implementation
- Modern responsive design with glassmorphism effects
- Professional tech/cloud provider aesthetic
- Smooth scrolling navigation
- Mobile-optimized layout

### Points System UI Elements
- Points counter display with current multiplier status
- Visual feedback when points increase
- Promo task completion buttons
- Bonus notification for waitlist signup
- Active multiplier indicator with timer

### User Account Features
- Login/signup forms
- Points dashboard
- Promo task interface
- Settings and preferences

---

## Implementation Roadmap

### Phase 1: Foundation
1. **Set up email service** (SMTP configuration) ✅ COMPLETED
2. **Enhance subscribe.php** with spam protection ✅ COMPLETED
3. **Create user account system** (extend waitlist) - PENDING
4. **Implement email verification** - PENDING

### Phase 2: Points System & Queue Logic
1. **Define points-to-queue system** (how points affect position)
2. **Create points.js** with core timer and multiplier logic
3. **Add points display** to index.html with multiplier indicator
4. **Implement api/points.php** for server-side points management
5. **Add visual feedback** and animations

### Phase 3: Promo Tasks
1. **Add promo task buttons** with verification system
2. **Implement task completion tracking**
3. **Add multiplier system** with timers
4. **Create task dashboard** for users

### Phase 4: Security & Optimization
1. **Implement Project Honey Pot** integration
2. **Add rate limiting** and CAPTCHA
3. **Optimize performance** and caching
4. **Cross-browser testing** and bug fixes

### Phase 5: Advanced Features
1. **Add leaderboards** (optional)
2. **Implement referral system**
3. **Add analytics** and reporting
4. **Mobile app considerations**

---

## Current Status

### ✅ Completed
- **Modern responsive design** with professional tech/cloud provider aesthetic
- **Accurate hosting features** based on provider specifications
- **Email system** with SMTP configuration and spam protection
- **Coming soon placeholder** (index-2.html) ready for deployment
- **Configuration system** with secure credential storage

### 🔄 In Progress
- **Points system logic** (needs queue mechanics definition)
- **User account system** design
- **Points-to-queue conversion** methodology

### 📋 Next Steps
1. **Define queue system**: How points translate to server access priority
2. **Determine point value**: What constitutes "higher place in queue"
3. **Design account system**: User registration and authentication
4. **Implement points timer**: 1 point per second system
5. **Add promo tasks**: Twitter/Facebook/Reddit multipliers

### Notes

- Account system replaces localStorage for persistence
- Multiplier system encourages promotional activity
- Keep implementation minimal and focused
- Prioritize performance and user experience
- Maintain existing site aesthetics
- Follow established coding patterns from waitlist system
