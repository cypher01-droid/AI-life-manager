<?php
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// Fetch data for analytics

// Task completion rate
$stmt = $pdo->prepare("SELECT COUNT(*) as total, SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed FROM tasks WHERE user_id = ?");
$stmt->execute([$user_id]);
$task_stats = $stmt->fetch();
$task_completion_rate = $task_stats['total'] > 0 ? round(($task_stats['completed'] / $task_stats['total']) * 100, 1) : 0;

// Monthly income/expense for current year
$current_year = date('Y');
$stmt = $pdo->prepare("
    SELECT MONTH(date) as month, 
           SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END) as income,
           SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END) as expense
    FROM finance_transactions
    WHERE user_id = ? AND YEAR(date) = ?
    GROUP BY MONTH(date)
    ORDER BY month
");
$stmt->execute([$user_id, $current_year]);
$monthly_data = $stmt->fetchAll();
$months = [];
$incomes = [];
$expenses = [];
for ($i = 1; $i <= 12; $i++) {
    $months[] = date('M', mktime(0,0,0,$i,1));
    $found = false;
    foreach ($monthly_data as $row) {
        if ($row['month'] == $i) {
            $incomes[] = (float)$row['income'];
            $expenses[] = (float)$row['expense'];
            $found = true;
            break;
        }
    }
    if (!$found) {
        $incomes[] = 0;
        $expenses[] = 0;
    }
}

// Goal progress: count active, completed, abandoned
$stmt = $pdo->prepare("SELECT status, COUNT(*) as count FROM goals WHERE user_id = ? GROUP BY status");
$stmt->execute([$user_id]);
$goal_status_counts = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
$active_goals = $goal_status_counts['active'] ?? 0;
$completed_goals = $goal_status_counts['completed'] ?? 0;
$abandoned_goals = $goal_status_counts['abandoned'] ?? 0;

// Upcoming assignments count (next 7 days)
$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM school_assignments WHERE user_id = ? AND due_datetime BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 7 DAY) AND status != 'graded'");
$stmt->execute([$user_id]);
$upcoming_assignments = $stmt->fetchColumn();

// Recent notes count (last 30 days)
$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM notes WHERE user_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
$stmt->execute([$user_id]);
$recent_notes = $stmt->fetchColumn();

// Total balance
$stmt = $pdo->prepare("SELECT SUM(current_balance) as total FROM finance_accounts WHERE user_id = ? AND is_active = 1");
$stmt->execute([$user_id]);
$total_balance = $stmt->fetchColumn();
if ($total_balance === null) $total_balance = 0;

$pageTitle = 'Analytics';
include 'header.php';
?>

<!-- Include Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
        gap: 1.5rem;
        margin-bottom: 2rem;
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
        font-size: 2rem;
        font-weight: 700;
        color: #f0f0f0;
    }
    .chart-container {
        background: rgba(20,20,30,0.5);
        backdrop-filter: blur(8px);
        border-radius: 24px;
        padding: 1.5rem;
        margin-bottom: 2rem;
        border: 1px solid rgba(255,255,255,0.05);
    }
    .chart-container h3 {
        margin-bottom: 1rem;
        font-size: 1.1rem;
        font-weight: 600;
    }
    canvas {
        max-height: 300px;
        width: 100%;
    }
</style>

<div class="page-header">
    <h1>📈 Analytics</h1>
    <p>Your life at a glance – stats and insights</p>
</div>

<div class="stats-grid">
    <div class="glass-card stat-card">
        <h3>Task Completion</h3>
        <div class="value"><?= $task_completion_rate ?>%</div>
        <small><?= $task_stats['completed'] ?> completed / <?= $task_stats['total'] ?> total</small>
    </div>
    <div class="glass-card stat-card">
        <h3>Total Balance</h3>
        <div class="value">$<?= number_format($total_balance, 2) ?></div>
    </div>
    <div class="glass-card stat-card">
        <h3>Upcoming Assignments</h3>
        <div class="value"><?= $upcoming_assignments ?></div>
        <small>Due in next 7 days</small>
    </div>
    <div class="glass-card stat-card">
        <h3>Recent Notes</h3>
        <div class="value"><?= $recent_notes ?></div>
        <small>Created in last 30 days</small>
    </div>
</div>

<div class="chart-container">
    <h3>Monthly Income vs Expense (<?= $current_year ?>)</h3>
    <canvas id="financeChart"></canvas>
</div>

<div class="chart-container">
    <h3>Goal Status</h3>
    <canvas id="goalChart"></canvas>
</div>

<script>
    // Finance chart
    const ctx1 = document.getElementById('financeChart').getContext('2d');
    new Chart(ctx1, {
        type: 'bar',
        data: {
            labels: <?= json_encode($months) ?>,
            datasets: [
                {
                    label: 'Income',
                    data: <?= json_encode($incomes) ?>,
                    backgroundColor: 'rgba(16, 185, 129, 0.5)',
                    borderColor: '#10b981',
                    borderWidth: 1
                },
                {
                    label: 'Expense',
                    data: <?= json_encode($expenses) ?>,
                    backgroundColor: 'rgba(239, 68, 68, 0.5)',
                    borderColor: '#ef4444',
                    borderWidth: 1
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: { color: '#e0e0e0' },
                    grid: { color: 'rgba(255,255,255,0.05)' }
                },
                x: {
                    ticks: { color: '#e0e0e0' },
                    grid: { color: 'rgba(255,255,255,0.05)' }
                }
            },
            plugins: {
                legend: { labels: { color: '#e0e0e0' } }
            }
        }
    });

    // Goal chart
    const ctx2 = document.getElementById('goalChart').getContext('2d');
    new Chart(ctx2, {
        type: 'pie',
        data: {
            labels: ['Active', 'Completed', 'Abandoned'],
            datasets: [{
                data: [<?= $active_goals ?>, <?= $completed_goals ?>, <?= $abandoned_goals ?>],
                backgroundColor: ['#10b981', '#7c3aed', '#ef4444'],
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
                legend: { labels: { color: '#e0e0e0' } }
            }
        }
    });
</script>

<?php include 'footer.php'; ?>