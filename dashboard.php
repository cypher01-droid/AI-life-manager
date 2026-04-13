<?php
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];

// Fetch user preferences (including role)
$stmt = $pdo->prepare("SELECT preferences FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$userData = $stmt->fetch();
$preferences = $userData ? json_decode($userData['preferences'], true) : [];
$role = $preferences['role'] ?? 'user';

// Upcoming tasks (next 7 days, not completed)
$stmt = $pdo->prepare("
    SELECT id, title, due_date, priority
    FROM tasks
    WHERE user_id = ? AND status != 'completed' AND due_date BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 7 DAY)
    ORDER BY due_date ASC
    LIMIT 5
");
$stmt->execute([$user_id]);
$upcomingTasks = $stmt->fetchAll();

// Upcoming events (next 5)
$stmt = $pdo->prepare("
    SELECT id, title, start_datetime, end_datetime
    FROM events
    WHERE user_id = ? AND start_datetime >= NOW()
    ORDER BY start_datetime ASC
    LIMIT 5
");
$stmt->execute([$user_id]);
$upcomingEvents = $stmt->fetchAll();

// Recent notes (last 5 updated)
$stmt = $pdo->prepare("
    SELECT id, title, updated_at
    FROM notes
    WHERE user_id = ?
    ORDER BY updated_at DESC
    LIMIT 5
");
$stmt->execute([$user_id]);
$recentNotes = $stmt->fetchAll();

// Pending tasks count
$stmt = $pdo->prepare("SELECT COUNT(*) FROM tasks WHERE user_id = ? AND status != 'completed'");
$stmt->execute([$user_id]);
$pendingTasksCount = $stmt->fetchColumn();

// Events today
$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM events
    WHERE user_id = ? AND DATE(start_datetime) = CURDATE()
");
$stmt->execute([$user_id]);
$eventsTodayCount = $stmt->fetchColumn();

// Finance balance
$stmt = $pdo->prepare("
    SELECT SUM(current_balance) as total_balance
    FROM finance_accounts
    WHERE user_id = ? AND is_active = 1
");
$stmt->execute([$user_id]);
$totalBalance = $stmt->fetchColumn();
if ($totalBalance === null) $totalBalance = 0;

// Next class (if student/teacher)
$nextClass = null;
if ($role === 'student' || $role === 'teacher') {
    $dayMap = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
    $todayName = $dayMap[date('N') - 1];
    $currentTime = date('H:i:s');

    $stmt = $pdo->prepare("
        SELECT t.*, c.name as course_name
        FROM school_timetable t
        JOIN school_courses c ON t.course_id = c.id
        WHERE t.user_id = ? AND t.day_of_week = ? AND t.start_time > ? AND t.is_active = 1
        ORDER BY t.start_time ASC
        LIMIT 1
    ");
    $stmt->execute([$user_id, $todayName, $currentTime]);
    $nextClass = $stmt->fetch();
}

$pageTitle = 'Dashboard';
include 'header.php';
?>

<!-- Dashboard-specific styles -->
<style>
    /* Welcome section */
    .welcome-section {
        margin-bottom: 2rem;
    }

    .welcome-section h1 {
        font-size: 2rem;
        font-weight: 700;
        margin-bottom: 0.3rem;
        background: linear-gradient(to right, #ffffff, #c0c0ff);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
    }

    .welcome-section p {
        color: #9ca3af;
    }

    /* Stats grid */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1.5rem;
        margin-bottom: 2.5rem;
    }

    .stat-card {
        padding: 1.5rem;
    }

    .stat-card h3 {
        font-size: 0.9rem;
        font-weight: 500;
        color: #9ca3af;
        margin-bottom: 0.5rem;
    }

    .stat-card .value {
        font-size: 2.2rem;
        font-weight: 700;
        color: #f0f0f0;
    }

    /* Widget grid */
    .widget-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
        gap: 1.5rem;
    }

    .widget {
        padding: 1.5rem;
    }

    .widget h2 {
        font-size: 1.2rem;
        font-weight: 600;
        margin-bottom: 1.2rem;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .widget h2 a {
        color: #a78bfa;
        text-decoration: none;
        font-size: 0.9rem;
        font-weight: 500;
        transition: color 0.2s;
    }

    .widget h2 a:hover {
        color: #c4b5fd;
        text-decoration: underline;
    }

    .item-list {
        list-style: none;
    }

    .item-list li {
        padding: 0.8rem 0;
        border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .item-list li:last-child {
        border-bottom: none;
    }

    .item-title {
        font-weight: 500;
        color: #f0f0f0;
    }

    .item-meta {
        font-size: 0.85rem;
        color: #9ca3af;
    }

    .priority-high {
        color: #ef4444;
    }

    .priority-medium {
        color: #f59e0b;
    }

    .priority-low {
        color: #10b981;
    }

    .empty-state {
        color: #6b7280;
        text-align: center;
        padding: 1.5rem 0;
    }

    /* Mobile adjustments */
    @media (max-width: 768px) {
        .stats-grid {
            grid-template-columns: 1fr 1fr;
        }

        .widget-grid {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="welcome-section">
    <h1>Welcome back, <?= htmlspecialchars(explode(' ', $user_name)[0]) ?> 👋</h1>
    <p>Here's your overview for today.</p>
</div>

<!-- Quick Stats with glassmorphism -->
<div class="stats-grid">
    <div class="glass-card stat-card">
        <h3>Pending Tasks</h3>
        <div class="value"><?= $pendingTasksCount ?></div>
    </div>
    <div class="glass-card stat-card">
        <h3>Today's Events</h3>
        <div class="value"><?= $eventsTodayCount ?></div>
    </div>
    <div class="glass-card stat-card">
        <h3>Recent Notes</h3>
        <div class="value"><?= count($recentNotes) ?></div>
    </div>
    <div class="glass-card stat-card">
        <h3>Total Balance</h3>
        <div class="value">$<?= number_format($totalBalance, 2) ?></div>
    </div>
</div>

<!-- Widgets -->
<div class="widget-grid">
    <!-- Upcoming Tasks Widget -->
    <div class="glass-card widget">
        <h2>
            Upcoming Tasks
            <a href="tasks.php">View all →</a>
        </h2>
        <?php if (count($upcomingTasks) > 0): ?>
            <ul class="item-list">
                <?php foreach ($upcomingTasks as $task): ?>
                    <li>
                        <span class="item-title"><?= htmlspecialchars($task['title']) ?></span>
                        <span class="item-meta priority-<?= $task['priority'] ?>">
                            <?= date('M j', strtotime($task['due_date'])) ?>
                        </span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <p class="empty-state">No tasks due soon. You're all caught up!</p>
        <?php endif; ?>
    </div>

    <!-- Upcoming Events Widget -->
    <div class="glass-card widget">
        <h2>
            Upcoming Events
            <a href="calendar.php">View all →</a>
        </h2>
        <?php if (count($upcomingEvents) > 0): ?>
            <ul class="item-list">
                <?php foreach ($upcomingEvents as $event): ?>
                    <li>
                        <span class="item-title"><?= htmlspecialchars($event['title']) ?></span>
                        <span class="item-meta">
                            <?= date('M j, g:i a', strtotime($event['start_datetime'])) ?>
                        </span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <p class="empty-state">No upcoming events. Enjoy your free time!</p>
        <?php endif; ?>
    </div>

    <!-- Recent Notes Widget -->
    <div class="glass-card widget">
        <h2>
            Recent Notes
            <a href="notes.php">View all →</a>
        </h2>
        <?php if (count($recentNotes) > 0): ?>
            <ul class="item-list">
                <?php foreach ($recentNotes as $note): ?>
                    <li>
                        <span class="item-title"><?= htmlspecialchars($note['title']) ?></span>
                        <span class="item-meta">
                            <?= date('M j', strtotime($note['updated_at'])) ?>
                        </span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <p class="empty-state">No notes yet. Start writing!</p>
        <?php endif; ?>
    </div>

    <!-- Conditional Widget: Next Class (for students/teachers) -->
    <?php if ($role === 'student' || $role === 'teacher'): ?>
    <div class="glass-card widget">
        <h2>
            Next Class
            <a href="school.php">Schedule →</a>
        </h2>
        <?php if ($nextClass): ?>
            <ul class="item-list">
                <li>
                    <span class="item-title"><?= htmlspecialchars($nextClass['course_name']) ?></span>
                    <span class="item-meta">
                        <?= date('g:i a', strtotime($nextClass['start_time'])) ?> - <?= date('g:i a', strtotime($nextClass['end_time'])) ?>
                    </span>
                </li>
                <li style="border-bottom: none; padding-top: 0.3rem; color: #9ca3af;">
                    Room: <?= htmlspecialchars($nextClass['room'] ?? 'TBD') ?>
                </li>
            </ul>
        <?php else: ?>
            <p class="empty-state">No more classes today. Relax!</p>
        <?php endif; ?>
    </div>
    <?php else: ?>
    <!-- For other roles, show a quick tip -->
    <div class="glass-card widget">
        <h2>Quick Tip</h2>
        <p style="color: #b0b0b0; line-height: 1.6;">
            Use the sidebar to explore tasks, notes, finance, and more. You can customize your dashboard as you go!
        </p>
    </div>
    <?php endif; ?>
</div>

<?php include 'footer.php'; ?>