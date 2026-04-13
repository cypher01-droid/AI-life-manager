<?php
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// Handle actions
$action = $_POST['action'] ?? $_GET['action'] ?? '';
$task_id = $_POST['task_id'] ?? $_GET['task_id'] ?? 0;
$filter = $_GET['filter'] ?? 'pending';

// --- Category creation (inline) ---
if ($action === 'add_category' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $cat_name = trim($_POST['category_name'] ?? '');
    if (!empty($cat_name)) {
        $stmt = $pdo->prepare("INSERT INTO categories (user_id, name, type) VALUES (?, ?, 'task')");
        $stmt->execute([$user_id, $cat_name]);
    }
    header("Location: tasks.php?filter=$filter");
    exit;
}

// --- Project creation (inline) ---
if ($action === 'add_project' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $proj_name = trim($_POST['project_name'] ?? '');
    if (!empty($proj_name)) {
        $stmt = $pdo->prepare("INSERT INTO projects (user_id, name, status) VALUES (?, ?, 'active')");
        $stmt->execute([$user_id, $proj_name]);
    }
    header("Location: tasks.php?filter=$filter");
    exit;
}

// Add task
if ($action === 'add' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $due_date_raw = $_POST['due_date'] ?? null;
    // Convert date-only to datetime (set time to 00:00:00)
    $due_date = !empty($due_date_raw) ? $due_date_raw . ' 00:00:00' : null;
    $priority = $_POST['priority'] ?? 'medium';
    $category_id = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null;
    $project_id = !empty($_POST['project_id']) ? (int)$_POST['project_id'] : null;

    if (!empty($title)) {
        $stmt = $pdo->prepare("
            INSERT INTO tasks (user_id, title, description, due_date, priority, category_id, project_id)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$user_id, $title, $description, $due_date, $priority, $category_id, $project_id]);
    }
    header("Location: tasks.php?filter=$filter");
    exit;
}

// Edit task
if ($action === 'edit' && $_SERVER['REQUEST_METHOD'] === 'POST' && $task_id) {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $due_date_raw = $_POST['due_date'] ?? null;
    $due_date = !empty($due_date_raw) ? $due_date_raw . ' 00:00:00' : null;
    $priority = $_POST['priority'] ?? 'medium';
    $category_id = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null;
    $project_id = !empty($_POST['project_id']) ? (int)$_POST['project_id'] : null;

    if (!empty($title)) {
        $stmt = $pdo->prepare("
            UPDATE tasks
            SET title = ?, description = ?, due_date = ?, priority = ?, category_id = ?, project_id = ?
            WHERE id = ? AND user_id = ?
        ");
        $stmt->execute([$title, $description, $due_date, $priority, $category_id, $project_id, $task_id, $user_id]);
    }
    header("Location: tasks.php?filter=$filter");
    exit;
}

// Delete task
if ($action === 'delete' && $task_id) {
    $stmt = $pdo->prepare("DELETE FROM tasks WHERE id = ? AND user_id = ?");
    $stmt->execute([$task_id, $user_id]);
    header("Location: tasks.php?filter=$filter");
    exit;
}

// Toggle complete
if ($action === 'toggle' && $task_id) {
    $stmt = $pdo->prepare("SELECT status FROM tasks WHERE id = ? AND user_id = ?");
    $stmt->execute([$task_id, $user_id]);
    $task = $stmt->fetch();
    if ($task) {
        $new_status = $task['status'] === 'completed' ? 'pending' : 'completed';
        $completed_at = $new_status === 'completed' ? date('Y-m-d H:i:s') : null;
        $stmt = $pdo->prepare("UPDATE tasks SET status = ?, completed_at = ? WHERE id = ?");
        $stmt->execute([$new_status, $completed_at, $task_id]);
    }
    header("Location: tasks.php?filter=$filter");
    exit;
}

// Fetch categories for dropdown
$stmt = $pdo->prepare("SELECT id, name FROM categories WHERE user_id = ? AND (type = 'task' OR type IS NULL) ORDER BY name");
$stmt->execute([$user_id]);
$categories = $stmt->fetchAll();

// Fetch projects for dropdown
$stmt = $pdo->prepare("SELECT id, name FROM projects WHERE user_id = ? AND status != 'archived' ORDER BY name");
$stmt->execute([$user_id]);
$projects = $stmt->fetchAll();

// Build tasks query based on filter
$sql = "SELECT t.*, c.name as category_name, p.name as project_name
        FROM tasks t
        LEFT JOIN categories c ON t.category_id = c.id
        LEFT JOIN projects p ON t.project_id = p.id
        WHERE t.user_id = ?";
$params = [$user_id];

if ($filter === 'pending') {
    $sql .= " AND t.status != 'completed'";
} elseif ($filter === 'completed') {
    $sql .= " AND t.status = 'completed'";
}

$sql .= " ORDER BY 
            CASE t.status 
                WHEN 'pending' THEN 1
                WHEN 'in_progress' THEN 2
                WHEN 'completed' THEN 3
                ELSE 4
            END,
            (t.due_date IS NULL) ASC,
            t.due_date ASC,
            t.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$tasks = $stmt->fetchAll();

$pageTitle = 'Tasks';
include 'header.php';
?>

<style>
    /* (All styles remain the same as previous version) */
    .page-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 2rem;
        flex-wrap: wrap;
        gap: 1rem;
    }
    .page-header h1 {
        font-size: 2rem;
        font-weight: 700;
        background: linear-gradient(to right, #ffffff, #c0c0ff);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
    }
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
    .filter-tabs {
        display: flex;
        gap: 0.5rem;
        margin-bottom: 1.5rem;
        border-bottom: 1px solid rgba(255,255,255,0.05);
        padding-bottom: 0.5rem;
    }
    .filter-tab {
        padding: 0.5rem 1.2rem;
        border-radius: 40px;
        color: #9ca3af;
        text-decoration: none;
        font-weight: 500;
        transition: all 0.2s;
    }
    .filter-tab:hover {
        color: #ffffff;
        background: #2a2a3a;
    }
    .filter-tab.active {
        background: linear-gradient(145deg, #7c3aed, #4f46e5);
        color: white;
    }
    .task-list {
        padding: 1rem;
    }
    .task-item {
        display: flex;
        align-items: center;
        padding: 1rem;
        border-bottom: 1px solid rgba(255,255,255,0.05);
        gap: 1rem;
        transition: background 0.2s;
    }
    .task-item:hover {
        background: rgba(255,255,255,0.02);
    }
    .task-item:last-child {
        border-bottom: none;
    }
    .task-check {
        flex-shrink: 0;
    }
    .toggle-form {
        margin: 0;
    }
    .task-check input[type="checkbox"] {
        width: 20px;
        height: 20px;
        cursor: pointer;
        accent-color: #7c3aed;
    }
    .task-content {
        flex: 1;
        min-width: 0;
    }
    .task-title {
        font-weight: 600;
        color: #f0f0f0;
        margin-bottom: 0.3rem;
        word-break: break-word;
    }
    .task-title.completed {
        text-decoration: line-through;
        color: #6b7280;
    }
    .task-meta {
        display: flex;
        gap: 1rem;
        font-size: 0.85rem;
        color: #9ca3af;
        flex-wrap: wrap;
    }
    .task-meta span {
        display: flex;
        align-items: center;
        gap: 0.3rem;
    }
    .priority-badge {
        padding: 0.2rem 0.6rem;
        border-radius: 40px;
        font-size: 0.75rem;
        font-weight: 600;
    }
    .priority-high {
        background: rgba(239,68,68,0.2);
        color: #fecaca;
    }
    .priority-medium {
        background: rgba(245,158,11,0.2);
        color: #fde68a;
    }
    .priority-low {
        background: rgba(16,185,129,0.2);
        color: #a7f3d0;
    }
    .task-actions {
        display: flex;
        gap: 0.5rem;
        flex-shrink: 0;
    }
    .task-actions a {
        color: #9ca3af;
        text-decoration: none;
        padding: 0.3rem;
        border-radius: 8px;
        transition: all 0.2s;
    }
    .task-actions a:hover {
        background: #2a2a3a;
        color: #ffffff;
    }
    .task-actions .delete:hover {
        color: #ef4444;
    }
    .empty-state {
        text-align: center;
        padding: 3rem;
        color: #6b7280;
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
        background: rgba(30, 30, 40, 0.95);
        backdrop-filter: blur(12px);
        border: 1px solid rgba(255,255,255,0.05);
        border-radius: 32px;
        padding: 2rem;
        max-width: 500px;
        width: 90%;
        max-height: 90vh;
        overflow-y: auto;
        box-shadow: 0 25px 50px -12px rgba(0,0,0,0.5);
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
        box-shadow: 0 0 0 3px rgba(124,58,237,0.2);
    }
    textarea {
        min-height: 100px;
        resize: vertical;
    }
    .modal-actions {
        display: flex;
        justify-content: flex-end;
        gap: 1rem;
        margin-top: 1.5rem;
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
    @media (max-width: 768px) {
        .task-item {
            flex-wrap: wrap;
        }
        .task-actions {
            margin-left: auto;
        }
    }
</style>

<div class="page-header">
    <h1>Tasks</h1>
    <button class="btn-primary" onclick="openAddModal()">+ New Task</button>
</div>

<div class="filter-tabs">
    <a href="?filter=all" class="filter-tab <?= $filter === 'all' ? 'active' : '' ?>">All</a>
    <a href="?filter=pending" class="filter-tab <?= $filter === 'pending' ? 'active' : '' ?>">Pending</a>
    <a href="?filter=completed" class="filter-tab <?= $filter === 'completed' ? 'active' : '' ?>">Completed</a>
</div>

<div class="glass-card task-list">
    <?php if (count($tasks) > 0): ?>
        <?php foreach ($tasks as $task): ?>
            <div class="task-item">
                <div class="task-check">
                    <form method="post" action="tasks.php" class="toggle-form">
                        <input type="hidden" name="action" value="toggle">
                        <input type="hidden" name="task_id" value="<?= $task['id'] ?>">
                        <input type="hidden" name="filter" value="<?= $filter ?>">
                        <input type="checkbox" <?= $task['status'] === 'completed' ? 'checked' : '' ?> 
                               onchange="this.form.submit()">
                    </form>
                </div>
                <div class="task-content">
                    <div class="task-title <?= $task['status'] === 'completed' ? 'completed' : '' ?>">
                        <?= htmlspecialchars($task['title']) ?>
                    </div>
                    <div class="task-meta">
                        <?php if ($task['due_date']): ?>
                            <span>📅 <?= date('M j, Y', strtotime($task['due_date'])) ?></span>
                        <?php endif; ?>
                        <span class="priority-badge priority-<?= $task['priority'] ?>">
                            <?= ucfirst($task['priority']) ?>
                        </span>
                        <?php if ($task['category_name']): ?>
                            <span>🏷️ <?= htmlspecialchars($task['category_name']) ?></span>
                        <?php endif; ?>
                        <?php if ($task['project_name']): ?>
                            <span>📁 <?= htmlspecialchars($task['project_name']) ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="task-actions">
                    <a href="#" onclick="openEditModal(<?= $task['id'] ?>, '<?= htmlspecialchars(addslashes($task['title'])) ?>', '<?= htmlspecialchars(addslashes($task['description'])) ?>', '<?= $task['due_date'] ?>', '<?= $task['priority'] ?>', <?= $task['category_id'] ?: 'null' ?>, <?= $task['project_id'] ?: 'null' ?>)" title="Edit">✏️</a>
                    <a href="?action=delete&task_id=<?= $task['id'] ?>&filter=<?= $filter ?>" onclick="return confirm('Delete this task?')" class="delete" title="Delete">🗑️</a>
                </div>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="empty-state">
            <p>No tasks found. Create your first task!</p>
        </div>
    <?php endif; ?>
</div>

<!-- Add Task Modal -->
<div id="addModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>New Task</h2>
            <span class="close" onclick="closeAddModal()">&times;</span>
        </div>
        <form method="post" action="tasks.php?filter=<?= $filter ?>">
            <input type="hidden" name="action" value="add">
            <div class="form-group">
                <label for="add_title">Title *</label>
                <input type="text" id="add_title" name="title" required>
            </div>
            <div class="form-group">
                <label for="add_description">Description</label>
                <textarea id="add_description" name="description"></textarea>
            </div>
            <div class="form-group">
                <label for="add_due_date">Due Date</label>
                <input type="date" id="add_due_date" name="due_date">
            </div>
            <div class="form-group">
                <label for="add_priority">Priority</label>
                <select id="add_priority" name="priority">
                    <option value="low">Low</option>
                    <option value="medium" selected>Medium</option>
                    <option value="high">High</option>
                    <option value="urgent">Urgent</option>
                </select>
            </div>
            <div class="form-group">
                <label for="add_category">Category</label>
                <select id="add_category" name="category_id">
                    <option value="">None</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <a href="#" class="add-link" onclick="openCategoryModal()">+ Create new</a>
            </div>
            <div class="form-group">
                <label for="add_project">Project</label>
                <select id="add_project" name="project_id">
                    <option value="">None</option>
                    <?php foreach ($projects as $proj): ?>
                        <option value="<?= $proj['id'] ?>"><?= htmlspecialchars($proj['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <a href="#" class="add-link" onclick="openProjectModal()">+ Create new</a>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn-secondary" onclick="closeAddModal()">Cancel</button>
                <button type="submit" class="btn-primary">Create Task</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Task Modal -->
<div id="editModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Edit Task</h2>
            <span class="close" onclick="closeEditModal()">&times;</span>
        </div>
        <form method="post" action="tasks.php?filter=<?= $filter ?>">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="task_id" id="edit_task_id">
            <div class="form-group">
                <label for="edit_title">Title *</label>
                <input type="text" id="edit_title" name="title" required>
            </div>
            <div class="form-group">
                <label for="edit_description">Description</label>
                <textarea id="edit_description" name="description"></textarea>
            </div>
            <div class="form-group">
                <label for="edit_due_date">Due Date</label>
                <input type="date" id="edit_due_date" name="due_date">
            </div>
            <div class="form-group">
                <label for="edit_priority">Priority</label>
                <select id="edit_priority" name="priority">
                    <option value="low">Low</option>
                    <option value="medium">Medium</option>
                    <option value="high">High</option>
                    <option value="urgent">Urgent</option>
                </select>
            </div>
            <div class="form-group">
                <label for="edit_category">Category</label>
                <select id="edit_category" name="category_id">
                    <option value="">None</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <a href="#" class="add-link" onclick="openCategoryModalForEdit()">+ Create new</a>
            </div>
            <div class="form-group">
                <label for="edit_project">Project</label>
                <select id="edit_project" name="project_id">
                    <option value="">None</option>
                    <?php foreach ($projects as $proj): ?>
                        <option value="<?= $proj['id'] ?>"><?= htmlspecialchars($proj['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <a href="#" class="add-link" onclick="openProjectModalForEdit()">+ Create new</a>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn-secondary" onclick="closeEditModal()">Cancel</button>
                <button type="submit" class="btn-primary">Update Task</button>
            </div>
        </form>
    </div>
</div>

<!-- Category Creation Modal -->
<div id="categoryModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Create New Category</h2>
            <span class="close" onclick="closeCategoryModal()">&times;</span>
        </div>
        <form method="post" action="tasks.php?filter=<?= $filter ?>">
            <input type="hidden" name="action" value="add_category">
            <div class="form-group">
                <label for="category_name">Category Name</label>
                <input type="text" id="category_name" name="category_name" required>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn-secondary" onclick="closeCategoryModal()">Cancel</button>
                <button type="submit" class="btn-primary">Create</button>
            </div>
        </form>
    </div>
</div>

<!-- Project Creation Modal -->
<div id="projectModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Create New Project</h2>
            <span class="close" onclick="closeProjectModal()">&times;</span>
        </div>
        <form method="post" action="tasks.php?filter=<?= $filter ?>">
            <input type="hidden" name="action" value="add_project">
            <div class="form-group">
                <label for="project_name">Project Name</label>
                <input type="text" id="project_name" name="project_name" required>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn-secondary" onclick="closeProjectModal()">Cancel</button>
                <button type="submit" class="btn-primary">Create</button>
            </div>
        </form>
    </div>
</div>

<script>
    function openAddModal() {
        document.getElementById('addModal').classList.add('active');
    }
    function closeAddModal() {
        document.getElementById('addModal').classList.remove('active');
    }

    function openEditModal(id, title, description, due_date, priority, category_id, project_id) {
        document.getElementById('edit_task_id').value = id;
        document.getElementById('edit_title').value = title;
        document.getElementById('edit_description').value = description;
        if (due_date) {
            // Extract YYYY-MM-DD from datetime string
            let datePart = due_date.split(' ')[0];
            document.getElementById('edit_due_date').value = datePart;
        } else {
            document.getElementById('edit_due_date').value = '';
        }
        document.getElementById('edit_priority').value = priority;
        document.getElementById('edit_category').value = category_id || '';
        document.getElementById('edit_project').value = project_id || '';
        document.getElementById('editModal').classList.add('active');
    }
    function closeEditModal() {
        document.getElementById('editModal').classList.remove('active');
    }

    // Category modal functions
    function openCategoryModal() {
        document.getElementById('categoryModal').classList.add('active');
    }
    function closeCategoryModal() {
        document.getElementById('categoryModal').classList.remove('active');
    }
    function openCategoryModalForEdit() {
        window.returnToModal = 'edit';
        openCategoryModal();
    }

    // Project modal functions
    function openProjectModal() {
        document.getElementById('projectModal').classList.add('active');
    }
    function closeProjectModal() {
        document.getElementById('projectModal').classList.remove('active');
    }
    function openProjectModalForEdit() {
        window.returnToModal = 'edit';
        openProjectModal();
    }

    window.onclick = function(event) {
        if (event.target.classList.contains('modal')) {
            event.target.classList.remove('active');
        }
    }
</script>

<?php include 'footer.php'; ?>