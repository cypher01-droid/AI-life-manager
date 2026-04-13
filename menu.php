<?php
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$pageTitle = 'Menu';
include 'header.php';
?>

<style>
    .menu-header {
        margin-bottom: 2rem;
    }
    .menu-header h1 {
        font-size: 2rem;
        font-weight: 700;
        background: linear-gradient(to right, #ffffff, #c0c0ff);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
        margin-bottom: 0.5rem;
    }
    .menu-header p {
        color: #9ca3af;
    }
    .menu-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
        gap: 1.5rem;
    }
    .menu-card {
        transition: transform 0.2s, box-shadow 0.2s;
        text-decoration: none;
        display: block;
    }
    .menu-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 15px 30px -12px rgba(0,0,0,0.5);
    }
    .menu-icon {
        font-size: 2.5rem;
        margin-bottom: 1rem;
        display: inline-block;
    }
    .menu-card h3 {
        font-size: 1.2rem;
        font-weight: 600;
        color: #f0f0f0;
        margin-bottom: 0.5rem;
    }
    .menu-card p {
        color: #b0b0b0;
        font-size: 0.9rem;
        line-height: 1.4;
    }
    .menu-card .card-footer {
        margin-top: 1rem;
        color: #a78bfa;
        font-size: 0.85rem;
    }
</style>

<div class="menu-header">
    <h1>📋 Menu</h1>
    <p>Explore all the tools and features to manage your life with AI.</p>
</div>

<div class="menu-grid">
    <a href="dashboard.php" class="glass-card menu-card">
        <div class="menu-icon">📊</div>
        <h3>Dashboard</h3>
        <p>Your personalized overview – stats, recent activity, and quick actions.</p>
        <div class="card-footer">Go to Dashboard →</div>
    </a>
    
    <a href="tasks.php" class="glass-card menu-card">
        <div class="menu-icon">✅</div>
        <h3>Tasks</h3>
        <p>Manage your to‑do list, set priorities, due dates, and track progress.</p>
        <div class="card-footer">Manage Tasks →</div>
    </a>
    
    <a href="calendar.php" class="glass-card menu-card">
        <div class="menu-icon">📅</div>
        <h3>Calendar</h3>
        <p>Plan your schedule, add events, timetables, and view public holidays.</p>
        <div class="card-footer">Open Calendar →</div>
    </a>
    
    <a href="notes.php" class="glass-card menu-card">
        <div class="menu-icon">📝</div>
        <h3>Notes</h3>
        <p>Capture ideas, organize with categories and tags, and quickly search.</p>
        <div class="card-footer">Browse Notes →</div>
    </a>
    
    <a href="finance.php" class="glass-card menu-card">
        <div class="menu-icon">💰</div>
        <h3>Finance</h3>
        <p>Track expenses, manage budgets, and monitor your financial health.</p>
        <div class="card-footer">Go to Finance →</div>
    </a>
    
    <a href="school.php" class="glass-card menu-card">
        <div class="menu-icon">🎓</div>
        <h3>School</h3>
        <p>Organize courses, timetable, assignments, and track grades.</p>
        <div class="card-footer">Go to School →</div>
    </a>
    
    <a href="goals.php" class="glass-card menu-card">
        <div class="menu-icon">🎯</div>
        <h3>Goals</h3>
        <p>Set and track your personal and professional objectives.</p>
        <div class="card-footer">View Goals →</div>
    </a>
    
    <a href="profile.php" class="glass-card menu-card">
        <div class="menu-icon">⚙️</div>
        <h3>Profile</h3>
        <p>Update your account details, change password, and customize preferences.</p>
        <div class="card-footer">Edit Profile →</div>
    </a>
</div>

<?php include 'footer.php'; ?>