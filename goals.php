<?php
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// --- Helper function to format value with unit ---
function formatValue($value, $unit) {
    if ($value === null) return '-';
    return number_format($value, 2) . ($unit ? ' ' . $unit : '');
}

// --- Handle actions ---
$action = $_POST['action'] ?? $_GET['action'] ?? '';
$goal_id = $_POST['goal_id'] ?? $_GET['goal_id'] ?? 0;

// Add goal
if ($action === 'add_goal' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $description = trim($_POST['description']);
    $target_date = $_POST['target_date'] ?: null;
    $target_value = !empty($_POST['target_value']) ? (float)$_POST['target_value'] : null;
    $current_value = !empty($_POST['current_value']) ? (float)$_POST['current_value'] : null;
    $unit = trim($_POST['unit']);
    $status = $_POST['status'];
    
    // Set completed_at if status is completed
    $completed_at = ($status === 'completed') ? date('Y-m-d H:i:s') : null;

    $stmt = $pdo->prepare("INSERT INTO goals (user_id, name, description, target_date, target_value, current_value, unit, status, completed_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$user_id, $name, $description, $target_date, $target_value, $current_value, $unit, $status, $completed_at]);
    header('Location: goals.php');
    exit;
}

// Edit goal
if ($action === 'edit_goal' && $_SERVER['REQUEST_METHOD'] === 'POST' && $goal_id) {
    $name = trim($_POST['name']);
    $description = trim($_POST['description']);
    $target_date = $_POST['target_date'] ?: null;
    $target_value = !empty($_POST['target_value']) ? (float)$_POST['target_value'] : null;
    $current_value = !empty($_POST['current_value']) ? (float)$_POST['current_value'] : null;
    $unit = trim($_POST['unit']);
    $status = $_POST['status'];
    
    // Set completed_at based on status
    $completed_at = ($status === 'completed') ? date('Y-m-d H:i:s') : null;

    $stmt = $pdo->prepare("UPDATE goals SET name=?, description=?, target_date=?, target_value=?, current_value=?, unit=?, status=?, completed_at=?, updated_at=NOW() WHERE id=? AND user_id=?");
    $stmt->execute([$name, $description, $target_date, $target_value, $current_value, $unit, $status, $completed_at, $goal_id, $user_id]);
    header('Location: goals.php');
    exit;
}

// Delete goal
if ($action === 'delete_goal' && $goal_id) {
    $stmt = $pdo->prepare("DELETE FROM goals WHERE id=? AND user_id=?");
    $stmt->execute([$goal_id, $user_id]);
    header('Location: goals.php');
    exit;
}

// Update progress (increment/decrement)
if ($action === 'update_progress' && isset($_POST['goal_id']) && isset($_POST['change'])) {
    $goal_id = (int)$_POST['goal_id'];
    $change = (float)$_POST['change'];
    $stmt = $pdo->prepare("UPDATE goals SET current_value = COALESCE(current_value, 0) + ?, updated_at = NOW() WHERE id=? AND user_id=?");
    $stmt->execute([$change, $goal_id, $user_id]);
    header('Location: goals.php');
    exit;
}

// Fetch all goals
$stmt = $pdo->prepare("SELECT * FROM goals WHERE user_id = ? ORDER BY 
    CASE status 
        WHEN 'active' THEN 1
        WHEN 'completed' THEN 2
        WHEN 'abandoned' THEN 3
    END,
    target_date ASC,
    created_at DESC");
$stmt->execute([$user_id]);
$goals = $stmt->fetchAll();

// Calculate stats
$total_goals = count($goals);
$completed_goals = count(array_filter($goals, fn($g) => $g['status'] === 'completed'));
$active_goals = count(array_filter($goals, fn($g) => $g['status'] === 'active'));

$pageTitle = 'Goals';
include 'header.php';
?>

<style>
    /* Goals specific styles */
    /* Button styles (if not already in header) */
.btn-primary {
    background: linear-gradient(145deg, #7c3aed, #4f46e5);
    color: white;
    border: none;
    padding: 0.7rem 1.5rem;
    border-radius: 40px;
    font-weight: 600;
    font-size: 0.95rem;
    cursor: pointer;
    transition: all 0.2s;
    box-shadow: 0 5px 15px rgba(124, 58, 237, 0.3);
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
}
.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 25px rgba(124, 58, 237, 0.5);
}
.btn-secondary {
    background: transparent;
    border: 1.5px solid #333;
    color: #e0e0e0;
    padding: 0.5rem 1rem;
    border-radius: 40px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s;
    text-decoration: none;
    font-size: 0.9rem;
}
.btn-secondary:hover {
    border-color: #a78bfa;
    color: #a78bfa;
}
.add-link {
    font-size: 0.85rem;
    margin-left: 0.5rem;
    color: #a78bfa;
    cursor: pointer;
    text-decoration: none;
}
.add-link:hover {
    text-decoration: underline;
}

/* Modal styles */
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
    background: rgba(30, 30, 40, 0.95);
    backdrop-filter: blur(12px);
    border: 1px solid rgba(255, 255, 255, 0.05);
    border-radius: 32px;
    padding: 2rem;
    max-width: 500px;
    width: 90%;
    max-height: 90vh;
    overflow-y: auto;
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
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
    transition: color 0.2s;
}
.close:hover {
    color: #ffffff;
}

/* Form elements */
.form-group {
    margin-bottom: 1.2rem;
}
label {
    display: block;
    margin-bottom: 0.3rem;
    font-weight: 500;
    color: #d1d5db;
    font-size: 0.9rem;
}
input, textarea, select {
    width: 100%;
    padding: 0.8rem 1rem;
    background: #2a2a3a;
    border: 1.5px solid #333;
    border-radius: 16px;
    font-size: 0.95rem;
    color: #f0f0f0;
    transition: all 0.2s;
    font-family: 'Inter', sans-serif;
}
input:focus, textarea:focus, select:focus {
    outline: none;
    border-color: #7c3aed;
    box-shadow: 0 0 0 3px rgba(124, 58, 237, 0.2);
}
textarea {
    resize: vertical;
}
.modal-actions {
    display: flex;
    justify-content: flex-end;
    gap: 1rem;
    margin-top: 1.5rem;
}
    .goals-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 2rem;
        flex-wrap: wrap;
        gap: 1rem;
    }
    .goals-header h1 {
        font-size: 2rem;
        font-weight: 700;
        background: linear-gradient(to right, #ffffff, #c0c0ff);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
    }
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
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
    .goals-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
        gap: 1.5rem;
    }
    .goal-card {
        transition: transform 0.2s;
    }
    .goal-card:hover {
        transform: translateY(-4px);
    }
    .goal-status {
        display: inline-block;
        padding: 0.2rem 0.6rem;
        border-radius: 40px;
        font-size: 0.7rem;
        font-weight: 600;
        text-transform: capitalize;
    }
    .status-active {
        background: rgba(16,185,129,0.2);
        color: #10b981;
    }
    .status-completed {
        background: rgba(124,58,237,0.2);
        color: #a78bfa;
    }
    .status-abandoned {
        background: rgba(239,68,68,0.2);
        color: #ef4444;
    }
    .goal-title {
        font-size: 1.2rem;
        font-weight: 600;
        margin-bottom: 0.5rem;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .goal-description {
        color: #b0b0b0;
        font-size: 0.9rem;
        margin-bottom: 1rem;
        line-height: 1.4;
    }
    .goal-meta {
        font-size: 0.8rem;
        color: #9ca3af;
        margin-bottom: 1rem;
        display: flex;
        flex-wrap: wrap;
        gap: 1rem;
    }
    .progress-bar-container {
        background: #2a2a3a;
        border-radius: 20px;
        height: 8px;
        margin: 0.8rem 0;
        overflow: hidden;
    }
    .progress-bar {
        background: linear-gradient(90deg, #7c3aed, #4f46e5);
        height: 100%;
        width: 0%;
        transition: width 0.3s;
    }
    .progress-text {
        font-size: 0.8rem;
        color: #9ca3af;
        text-align: right;
    }
    .goal-actions {
        display: flex;
        gap: 0.5rem;
        margin-top: 1rem;
    }
    .goal-actions a {
        color: #9ca3af;
        text-decoration: none;
        font-size: 0.85rem;
        transition: color 0.2s;
    }
    .goal-actions a:hover {
        color: #ffffff;
    }
    .goal-actions .delete:hover {
        color: #ef4444;
    }
    .progress-update {
        margin-top: 0.5rem;
        display: flex;
        gap: 0.5rem;
    }
    .progress-update input {
        width: 80px;
        padding: 0.3rem;
        background: #2a2a3a;
        border: 1px solid #333;
        border-radius: 20px;
        color: #f0f0f0;
        text-align: center;
    }
    .progress-update button {
        background: #2a2a3a;
        border: none;
        border-radius: 20px;
        padding: 0.3rem 0.8rem;
        cursor: pointer;
        transition: all 0.2s;
    }
    .progress-update button:hover {
        background: #3a3a4a;
    }
    @media (max-width: 768px) {
        .goals-grid {
            grid-template-columns: 1fr;
        }
        .stats-grid {
            grid-template-columns: 1fr 1fr;
        }
    }
</style>

<div class="goals-header">
    <h1>🎯 Goals & Objectives</h1>
    <button class="btn-primary" onclick="openGoalModal()">+ New Goal</button>
</div>

<div class="stats-grid">
    <div class="glass-card stat-card">
        <h3>Total Goals</h3>
        <div class="value"><?= $total_goals ?></div>
    </div>
    <div class="glass-card stat-card">
        <h3>Active Goals</h3>
        <div class="value"><?= $active_goals ?></div>
    </div>
    <div class="glass-card stat-card">
        <h3>Completed</h3>
        <div class="value"><?= $completed_goals ?></div>
    </div>
</div>

<div class="goals-grid">
    <?php if (count($goals) > 0): ?>
        <?php foreach ($goals as $goal): 
            $progress = 0;
            if ($goal['target_value'] && $goal['target_value'] > 0 && $goal['current_value'] !== null) {
                $progress = min(100, ($goal['current_value'] / $goal['target_value']) * 100);
            }
        ?>
            <div class="glass-card goal-card">
                <div class="goal-title">
                    <span><?= htmlspecialchars($goal['name']) ?></span>
                    <span class="goal-status status-<?= $goal['status'] ?>"><?= $goal['status'] ?></span>
                </div>
                <?php if ($goal['description']): ?>
                    <div class="goal-description"><?= nl2br(htmlspecialchars($goal['description'])) ?></div>
                <?php endif; ?>
                <div class="goal-meta">
                    <?php if ($goal['target_date']): ?>
                        <span>📅 Target: <?= date('M j, Y', strtotime($goal['target_date'])) ?></span>
                    <?php endif; ?>
                    <?php if ($goal['target_value'] !== null): ?>
                        <span>🎯 Target: <?= formatValue($goal['target_value'], $goal['unit']) ?></span>
                    <?php endif; ?>
                </div>
                
                <?php if ($goal['target_value'] !== null && $goal['target_value'] > 0): ?>
                    <div class="progress-bar-container">
                        <div class="progress-bar" style="width: <?= $progress ?>%"></div>
                    </div>
                    <div class="progress-text">
                        <?= formatValue($goal['current_value'] ?? 0, $goal['unit']) ?> / <?= formatValue($goal['target_value'], $goal['unit']) ?>
                    </div>
                    
                    <!-- Quick progress update form -->
                    <form method="post" action="goals.php" class="progress-update">
                        <input type="hidden" name="action" value="update_progress">
                        <input type="hidden" name="goal_id" value="<?= $goal['id'] ?>">
                        <input type="number" step="any" name="change" placeholder="+/- amount" required>
                        <button type="submit">Update</button>
                    </form>
                <?php endif; ?>
                
                <div class="goal-actions">
                    <a href="#" onclick="editGoal(<?= htmlspecialchars(json_encode($goal)) ?>)">✏️ Edit</a>
                    <a href="?action=delete_goal&goal_id=<?= $goal['id'] ?>" onclick="return confirm('Delete this goal?')" class="delete">🗑️ Delete</a>
                </div>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="glass-card" style="text-align: center; padding: 2rem;">
            <p>No goals yet. Create your first goal to start tracking progress!</p>
        </div>
    <?php endif; ?>
</div>

<!-- Add/Edit Goal Modal -->
<div id="goalModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 id="goalModalTitle">Add Goal</h2>
            <span class="close" onclick="closeGoalModal()">&times;</span>
        </div>
        <form method="post" action="goals.php">
            <input type="hidden" name="action" id="goalAction" value="add_goal">
            <input type="hidden" name="goal_id" id="goalId">
            <div class="form-group">
                <label for="goal_name">Goal Name *</label>
                <input type="text" id="goal_name" name="name" required>
            </div>
            <div class="form-group">
                <label for="goal_description">Description</label>
                <textarea id="goal_description" name="description" rows="3"></textarea>
            </div>
            <div class="form-group">
                <label for="goal_target_date">Target Date</label>
                <input type="date" id="goal_target_date" name="target_date">
            </div>
            <div class="form-group">
                <label for="goal_target_value">Target Value (optional)</label>
                <input type="number" step="any" id="goal_target_value" name="target_value">
            </div>
            <div class="form-group">
                <label for="goal_current_value">Current Value (optional)</label>
                <input type="number" step="any" id="goal_current_value" name="current_value">
            </div>
            <div class="form-group">
                <label for="goal_unit">Unit (e.g., kg, $, books)</label>
                <input type="text" id="goal_unit" name="unit" placeholder="e.g., kg, $, hours">
            </div>
            <div class="form-group">
                <label for="goal_status">Status</label>
                <select id="goal_status" name="status">
                    <option value="active">Active</option>
                    <option value="completed">Completed</option>
                    <option value="abandoned">Abandoned</option>
                </select>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn-secondary" onclick="closeGoalModal()">Cancel</button>
                <button type="submit" class="btn-primary" id="goalSubmit">Create Goal</button>
            </div>
        </form>
    </div>
</div>

<script>
    function openGoalModal() {
        resetGoalModal();
        document.getElementById('goalModal').classList.add('active');
    }
    function closeGoalModal() {
        document.getElementById('goalModal').classList.remove('active');
    }

    function resetGoalModal() {
        document.getElementById('goalModalTitle').innerText = 'Add Goal';
        document.getElementById('goalAction').value = 'add_goal';
        document.getElementById('goalId').value = '';
        document.getElementById('goal_name').value = '';
        document.getElementById('goal_description').value = '';
        document.getElementById('goal_target_date').value = '';
        document.getElementById('goal_target_value').value = '';
        document.getElementById('goal_current_value').value = '';
        document.getElementById('goal_unit').value = '';
        document.getElementById('goal_status').value = 'active';
        document.getElementById('goalSubmit').innerText = 'Create Goal';
    }

    function editGoal(goal) {
        document.getElementById('goalModalTitle').innerText = 'Edit Goal';
        document.getElementById('goalAction').value = 'edit_goal';
        document.getElementById('goalId').value = goal.id;
        document.getElementById('goal_name').value = goal.name;
        document.getElementById('goal_description').value = goal.description;
        document.getElementById('goal_target_date').value = goal.target_date;
        document.getElementById('goal_target_value').value = goal.target_value;
        document.getElementById('goal_current_value').value = goal.current_value;
        document.getElementById('goal_unit').value = goal.unit;
        document.getElementById('goal_status').value = goal.status;
        document.getElementById('goalSubmit').innerText = 'Update Goal';
        document.getElementById('goalModal').classList.add('active');
    }

    window.onclick = function(event) {
        if (event.target.classList.contains('modal')) {
            event.target.classList.remove('active');
        }
    }
</script>

<?php include 'footer.php'; ?>