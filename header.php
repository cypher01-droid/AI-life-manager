<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// If user is not logged in, redirect to login page (optional – you can handle per page)
// if (!isset($_SESSION['user_id'])) {
//     header('Location: login.php');
//     exit;
// }

// Retrieve user data from session with defaults (but no hardcoded examples)
$user_name  = $_SESSION['user_name']  ?? '';
$user_email = $_SESSION['user_email'] ?? '';
$user_avatar = $_SESSION['user_avatar'] ?? ''; // could be a URL or empty

// Fallback initials if name is not available
$name_parts = explode(' ', $user_name);
$initials = '';
if (!empty($user_name)) {
    $initials = strtoupper(substr($name_parts[0], 0, 1) . (isset($name_parts[1]) ? substr($name_parts[1], 0, 1) : ''));
} else {
    $initials = '?';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <title><?php echo isset($pageTitle) ? $pageTitle . ' – ' : ''; ?>AI Life Manager</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:ital,opsz,wght@0,14..32,100..900;1,14..32,100..900&display=swap" rel="stylesheet">
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body {
            font-family: 'Inter', sans-serif;
            background-color: #0a0a0a;
            background-image: radial-gradient(circle at 30% 10%, #1a1a1a, #000000);
            color: #e0e0e0;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* ----- DESKTOP LAYOUT (≥769px) ----- */
        .desktop-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0.8rem 2rem;
            background: rgba(15, 15, 20, 0.7);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border-bottom: 1px solid rgba(255,255,255,0.05);
            position: sticky;
            top: 0;
            z-index: 20;
        }

        .search-bar {
            flex: 1;
            max-width: 400px;
            display: flex;
            align-items: center;
            background: rgba(30,30,40,0.6);
            border-radius: 40px;
            padding: 0.4rem 1rem;
            border: 1px solid rgba(255,255,255,0.05);
        }

        .search-bar svg {
            width: 18px;
            height: 18px;
            stroke: #9ca3af;
            margin-right: 0.5rem;
            fill: none;
        }

        .search-bar input {
            background: transparent;
            border: none;
            color: #f0f0f0;
            font-size: 0.95rem;
            width: 100%;
            outline: none;
        }

        .search-bar input::placeholder {
            color: #6b7280;
        }

        .header-actions {
            display: flex;
            align-items: center;
            gap: 1.5rem;
        }

        .notification-btn {
            background: rgba(30,30,40,0.6);
            border: 1px solid rgba(255,255,255,0.05);
            border-radius: 50%;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s;
        }

        .notification-btn:hover {
            background: rgba(50,50,60,0.8);
        }

        .notification-btn svg {
            width: 20px;
            height: 20px;
            stroke: #d1d5db;
            fill: none;
        }

        .user-profile {
            display: flex;
            align-items: center;
            gap: 0.8rem;
            background: rgba(30,30,40,0.6);
            padding: 0.4rem 0.8rem 0.4rem 0.4rem;
            border-radius: 40px;
            border: 1px solid rgba(255,255,255,0.05);
            cursor: pointer;
        }

        .avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: linear-gradient(145deg, #7c3aed, #4f46e5);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 1rem;
            overflow: hidden;
        }

        .avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .user-info {
            display: none;
        }

        @media (min-width: 1024px) {
            .user-info {
                display: block;
                line-height: 1.3;
            }
            .user-info .name {
                font-size: 0.9rem;
                font-weight: 600;
                color: #f0f0f0;
            }
            .user-info .email {
                font-size: 0.75rem;
                color: #9ca3af;
            }
        }

        /* Dashboard container (sidebar + main) */
        .dashboard-container {
            display: flex;
            flex: 1;
        }

        /* Sidebar - desktop only */
        .sidebar {
            width: 260px;
            background: rgba(15,15,20,0.7);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border-right: 1px solid rgba(255,255,255,0.05);
            padding: 2rem 1rem;
            display: flex;
            flex-direction: column;
            gap: 2rem;
        }

        .sidebar-logo {
            font-size: 1.5rem;
            font-weight: 700;
            background: linear-gradient(135deg, #a78bfa, #60a5fa);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            padding-left: 0.8rem;
        }

        .sidebar-nav {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .sidebar-nav a {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 0.8rem 1rem;
            color: #b0b0b0;
            text-decoration: none;
            border-radius: 16px;
            transition: all 0.2s;
            font-weight: 500;
        }

        .sidebar-nav a svg {
            width: 22px;
            height: 22px;
            stroke: currentColor;
            fill: none;
            stroke-width: 2;
        }

        .sidebar-nav a:hover {
            background: rgba(50,50,60,0.5);
            color: #ffffff;
        }

        .sidebar-nav a.active {
            background: linear-gradient(145deg, #7c3aed, #4f46e5);
            color: white;
            box-shadow: 0 8px 20px rgba(124,58,237,0.3);
        }

        /* Main content area */
        .main-content {
            flex: 1;
            padding: 2rem;
            overflow-y: auto;
        }

        /* ----- MOBILE LAYOUT (≤768px) ----- */
        .mobile-top-bar {
            display: none;
            align-items: center;
            justify-content: space-between;
            padding: 0.8rem 1.2rem;
            background: rgba(15,15,20,0.8);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border-bottom: 1px solid rgba(255,255,255,0.05);
            position: sticky;
            top: 0;
            z-index: 15;
        }

        .menu-toggle {
            background: transparent;
            border: none;
            cursor: pointer;
            padding: 0.5rem;
        }

        .menu-toggle svg {
            width: 24px;
            height: 24px;
            stroke: #e0e0e0;
            fill: none;
        }

        .mobile-profile .avatar {
            width: 32px;
            height: 32px;
        }

        /* Bottom navigation (iOS style) */
        .bottom-nav {
            display: none;
            background: rgba(15,15,20,0.9);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border-top: 1px solid rgba(255,255,255,0.05);
            position: fixed;
            bottom: 0;
            left: 0;
            width: 100%;
            justify-content: space-around;
            align-items: center;
            padding: 0.3rem 0 0.7rem;
            z-index: 20;
        }

        .bottom-nav a {
            display: flex;
            flex-direction: column;
            align-items: center;
            color: #9ca3af;
            text-decoration: none;
            font-size: 0.7rem;
            gap: 0.2rem;
            transition: color 0.2s;
            flex: 1;
            padding: 0.3rem 0;
        }

        .bottom-nav a svg {
            width: 24px;
            height: 24px;
            stroke: currentColor;
            fill: none;
            stroke-width: 2;
        }

        .bottom-nav a.active {
            color: #a78bfa;
        }

        .bottom-nav .center-btn {
            position: relative;
            top: -10px;
            background: linear-gradient(145deg, #7c3aed, #4f46e5);
            border-radius: 50%;
            width: 56px;
            height: 56px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            box-shadow: 0 8px 20px rgba(124,58,237,0.5);
        }

        .bottom-nav .center-btn svg {
            width: 28px;
            height: 28px;
            stroke: white;
            stroke-width: 2;
        }

        /* Responsive breakpoints */
        @media (max-width: 768px) {
            .desktop-header, .sidebar {
                display: none;
            }
            .mobile-top-bar {
                display: flex;
            }
            .bottom-nav {
                display: flex;
            }
            .main-content {
                padding: 1.5rem 1rem 5rem; /* bottom nav space */
            }
        }

        /* Glassmorphism card style */
        .glass-card {
            background: rgba(20,20,30,0.5);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            border: 1px solid rgba(255,255,255,0.03);
            border-radius: 24px;
            padding: 1.5rem;
            box-shadow: 0 10px 30px -10px rgba(0,0,0,0.5);
        }
        .modal {
    display: none;
    position: fixed;
    z-index: 100;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.7);
    backdrop-filter: blur(5px);
    align-items: center;
    justify-content: center;
}
.modal.active {
    display: flex;
}
.modal-content {
    background: #1e1e2e;
    border: 1px solid #2a2a3a;
    border-radius: 32px;
    padding: 2rem;
    max-width: 500px;
    width: 90%;
    max-height: 90vh;
    overflow-y: auto;
}
.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
}
.modal-header h2 {
    font-size: 1.5rem;
    font-weight: 700;
    color: #f0f0f0;
}
.close {
    font-size: 1.5rem;
    cursor: pointer;
    color: #9ca3af;
}
.close:hover {
    color: #fff;
}
    </style>
</head>
<body>
    <!-- Hidden SVG sprite for icons -->
    <div style="display: none;">
        <svg xmlns="http://www.w3.org/2000/svg">
            <symbol id="icon-search" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
            </symbol>
            <symbol id="icon-bell" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/>
            </symbol>
            <symbol id="icon-home" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/>
            </symbol>
            <symbol id="icon-tasks" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>
            </symbol>
            <symbol id="icon-calendar" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>
            </symbol>
            <symbol id="icon-notes" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/>
            </symbol>
            <symbol id="icon-finance" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
            </symbol>
            <symbol id="icon-school" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M2 22h20"/><path d="M4 18v-8"/><path d="M8 18v-8"/><path d="M12 18v-8"/><path d="M16 18v-8"/><path d="M20 18v-8"/><path d="M12 2L2 7v1h20V7l-10-5z"/>
            </symbol>
            <symbol id="icon-goals" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/>
            </symbol>
            <symbol id="icon-profile" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>
            </symbol>
            <symbol id="icon-menu" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/>
            </symbol>
            <symbol id="icon-analytics" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M21 12v-2a5 5 0 0 0-5-5H8a5 5 0 0 0-5 5v2"/><circle cx="12" cy="16" r="5"/><path d="M12 11v5"/>
            </symbol>
            <symbol id="icon-ai" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="10"/><path d="M12 8v8"/><path d="M8 12h8"/>
            </symbol>
        </svg>
    </div>

    <!-- Desktop Header -->
    <header class="desktop-header">
        <div class="search-bar">
            <svg><use xlink:href="#icon-search"></use></svg>
            <input type="text" placeholder="Search tasks, notes, events...">
        </div>
        <div class="header-actions">
            <div class="notification-btn">
                <svg><use xlink:href="#icon-bell"></use></svg>
            </div>
            <div class="user-profile">
                <div class="avatar">
                    <?php if ($user_avatar): ?>
                        <img src="<?php echo htmlspecialchars($user_avatar); ?>" alt="avatar">
                    <?php else: ?>
                        <?php echo htmlspecialchars($initials); ?>
                    <?php endif; ?>
                </div>
                <div class="user-info">
                    <div class="name"><?php echo htmlspecialchars($user_name); ?></div>
                    <div class="email"><?php echo htmlspecialchars($user_email); ?></div>
                </div>
            </div>
        </div>
    </header>

    <!-- Mobile Top Bar -->
    <div class="mobile-top-bar">
        <button class="menu-toggle" id="menuToggle">
            <svg><use xlink:href="#icon-menu"></use></svg>
        </button>
        <div class="mobile-profile">
            <div class="avatar">
                <?php if ($user_avatar): ?>
                    <img src="<?php echo htmlspecialchars($user_avatar); ?>" alt="avatar">
                <?php else: ?>
                    <?php echo htmlspecialchars($initials); ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="dashboard-container">
        <!-- Desktop Sidebar -->
       <aside class="sidebar">
    <div class="sidebar-logo">AI Life Manager</div>
    <nav class="sidebar-nav">
        <a href="dashboard.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>">
            <svg><use xlink:href="#icon-home"></use></svg> Dashboard
        </a>
        <a href="tasks.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'tasks.php' ? 'active' : ''; ?>">
            <svg><use xlink:href="#icon-tasks"></use></svg> Tasks
        </a>
        <a href="calendar.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'calendar.php' ? 'active' : ''; ?>">
            <svg><use xlink:href="#icon-calendar"></use></svg> Calendar
        </a>
        <a href="notes.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'notes.php' ? 'active' : ''; ?>">
            <svg><use xlink:href="#icon-notes"></use></svg> Notes
        </a>
        <a href="finance.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'finance.php' ? 'active' : ''; ?>">
            <svg><use xlink:href="#icon-finance"></use></svg> Finance
        </a>
        <a href="school.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'school.php' ? 'active' : ''; ?>">
            <svg><use xlink:href="#icon-school"></use></svg> School
        </a>
        <a href="goals.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'goals.php' ? 'active' : ''; ?>">
            <svg><use xlink:href="#icon-goals"></use></svg> Goals
        </a>
        <a href="ai_chat.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'ai_chat.php' ? 'active' : ''; ?>">
            <svg><use xlink:href="#icon-ai"></use></svg> AI Assistant
        </a>
        <a href="analytics.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'analytics.php' ? 'active' : ''; ?>">
            <svg><use xlink:href="#icon-analytics"></use></svg> Analytics
        </a>
        <a href="profile.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'profile.php' ? 'active' : ''; ?>">
            <svg><use xlink:href="#icon-profile"></use></svg> Profile
        </a>
    </nav>
</aside>

        <!-- Main content area starts here -->
        <main class="main-content">