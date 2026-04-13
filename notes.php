<?php
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// Handle actions (same as before)
$action = $_POST['action'] ?? $_GET['action'] ?? '';
$note_id = $_POST['note_id'] ?? $_GET['note_id'] ?? 0;
$filter_category = $_GET['category'] ?? '';
$filter_favorite = isset($_GET['favorite']) && $_GET['favorite'] == '1' ? true : false;
$search = trim($_GET['search'] ?? '');

// --- Add Note ---
if ($action === 'add_note' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $category_id = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null;
    $tags_input = trim($_POST['tags'] ?? '');

    if (!empty($title)) {
        $stmt = $pdo->prepare("
            INSERT INTO notes (user_id, title, content, category_id, created_at, updated_at)
            VALUES (?, ?, ?, ?, NOW(), NOW())
        ");
        $stmt->execute([$user_id, $title, $content, $category_id]);
        $note_id = $pdo->lastInsertId();

        if (!empty($tags_input)) {
            $tags = array_map('trim', explode(',', $tags_input));
            foreach ($tags as $tag_name) {
                if (empty($tag_name)) continue;
                $stmt = $pdo->prepare("SELECT id FROM tags WHERE user_id = ? AND name = ?");
                $stmt->execute([$user_id, $tag_name]);
                $tag = $stmt->fetch();
                if ($tag) {
                    $tag_id = $tag['id'];
                } else {
                    $stmt = $pdo->prepare("INSERT INTO tags (user_id, name) VALUES (?, ?)");
                    $stmt->execute([$user_id, $tag_name]);
                    $tag_id = $pdo->lastInsertId();
                }
                $stmt = $pdo->prepare("INSERT INTO note_tags (note_id, tag_id) VALUES (?, ?)");
                $stmt->execute([$note_id, $tag_id]);
            }
        }
    }
    header("Location: notes.php" . ($filter_category ? "?category=$filter_category" : "") . ($filter_favorite ? "&favorite=1" : "") . ($search ? "&search=" . urlencode($search) : ""));
    exit;
}

// --- Edit Note ---
if ($action === 'edit_note' && $_SERVER['REQUEST_METHOD'] === 'POST' && $note_id) {
    $title = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $category_id = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null;
    $tags_input = trim($_POST['tags'] ?? '');

    if (!empty($title)) {
        $stmt = $pdo->prepare("
            UPDATE notes
            SET title = ?, content = ?, category_id = ?, updated_at = NOW()
            WHERE id = ? AND user_id = ?
        ");
        $stmt->execute([$title, $content, $category_id, $note_id, $user_id]);

        $stmt = $pdo->prepare("DELETE FROM note_tags WHERE note_id = ?");
        $stmt->execute([$note_id]);

        if (!empty($tags_input)) {
            $tags = array_map('trim', explode(',', $tags_input));
            foreach ($tags as $tag_name) {
                if (empty($tag_name)) continue;
                $stmt = $pdo->prepare("SELECT id FROM tags WHERE user_id = ? AND name = ?");
                $stmt->execute([$user_id, $tag_name]);
                $tag = $stmt->fetch();
                if ($tag) {
                    $tag_id = $tag['id'];
                } else {
                    $stmt = $pdo->prepare("INSERT INTO tags (user_id, name) VALUES (?, ?)");
                    $stmt->execute([$user_id, $tag_name]);
                    $tag_id = $pdo->lastInsertId();
                }
                $stmt = $pdo->prepare("INSERT INTO note_tags (note_id, tag_id) VALUES (?, ?)");
                $stmt->execute([$note_id, $tag_id]);
            }
        }
    }
    header("Location: notes.php" . ($filter_category ? "?category=$filter_category" : "") . ($filter_favorite ? "&favorite=1" : "") . ($search ? "&search=" . urlencode($search) : ""));
    exit;
}

// --- Delete Note ---
if ($action === 'delete_note' && $note_id) {
    $stmt = $pdo->prepare("DELETE FROM notes WHERE id = ? AND user_id = ?");
    $stmt->execute([$note_id, $user_id]);
    header("Location: notes.php" . ($filter_category ? "?category=$filter_category" : "") . ($filter_favorite ? "&favorite=1" : "") . ($search ? "&search=" . urlencode($search) : ""));
    exit;
}

// --- Toggle Favorite ---
if ($action === 'toggle_favorite' && $note_id) {
    $stmt = $pdo->prepare("UPDATE notes SET is_favorite = NOT is_favorite WHERE id = ? AND user_id = ?");
    $stmt->execute([$note_id, $user_id]);
    header("Location: notes.php" . ($filter_category ? "?category=$filter_category" : "") . ($filter_favorite ? "&favorite=1" : "") . ($search ? "&search=" . urlencode($search) : ""));
    exit;
}

// --- Add Category (for notes) ---
if ($action === 'add_category' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $cat_name = trim($_POST['category_name'] ?? '');
    if (!empty($cat_name)) {
        $stmt = $pdo->prepare("INSERT INTO categories (user_id, name, type) VALUES (?, ?, 'note')");
        $stmt->execute([$user_id, $cat_name]);
    }
    header("Location: notes.php" . ($filter_category ? "?category=$filter_category" : "") . ($filter_favorite ? "&favorite=1" : "") . ($search ? "&search=" . urlencode($search) : ""));
    exit;
}

// Fetch categories for dropdown
$stmt = $pdo->prepare("SELECT id, name FROM categories WHERE user_id = ? AND (type = 'note' OR type IS NULL) ORDER BY name");
$stmt->execute([$user_id]);
$categories = $stmt->fetchAll();

// Build notes query
$sql = "SELECT n.*, c.name as category_name 
        FROM notes n
        LEFT JOIN categories c ON n.category_id = c.id
        WHERE n.user_id = ?";
$params = [$user_id];

if ($filter_category) {
    $sql .= " AND n.category_id = ?";
    $params[] = $filter_category;
}
if ($filter_favorite) {
    $sql .= " AND n.is_favorite = 1";
}
if (!empty($search)) {
    $sql .= " AND (n.title LIKE ? OR n.content LIKE ?)";
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
}
$sql .= " ORDER BY n.updated_at DESC LIMIT 50";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$notes = $stmt->fetchAll();

// Fetch tags for each note
$notes_with_tags = [];
foreach ($notes as $note) {
    $stmt = $pdo->prepare("SELECT t.name FROM tags t JOIN note_tags nt ON t.id = nt.tag_id WHERE nt.note_id = ? ORDER BY t.name");
    $stmt->execute([$note['id']]);
    $tags = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $note['tags'] = $tags;
    $notes_with_tags[] = $note;
}

$pageTitle = 'Notes';
include 'header.php';
?>

<style>
    /* Notes-specific styles - includes modal and button styling */
    .notes-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 2rem;
        flex-wrap: wrap;
        gap: 1rem;
    }
    .notes-header h1 {
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
        box-shadow: 0 5px 15px rgba(124,58,237,0.3);
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
    }
    .btn-primary:hover {
        transform: translateY(-2px);
        box-shadow: 0 10px 25px rgba(124,58,237,0.5);
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
    .filter-bar {
        display: flex;
        gap: 1rem;
        flex-wrap: wrap;
        margin-bottom: 1.5rem;
        background: rgba(20,20,30,0.3);
        backdrop-filter: blur(5px);
        padding: 1rem;
        border-radius: 40px;
        border: 1px solid rgba(255,255,255,0.05);
    }
    .filter-bar input, .filter-bar select {
        padding: 0.5rem 1rem;
        background: #2a2a3a;
        border: 1px solid #333;
        border-radius: 40px;
        color: #e0e0e0;
        font-family: 'Inter', sans-serif;
    }
    .filter-bar button {
        background: #7c3aed;
        border: none;
        padding: 0.5rem 1.2rem;
        border-radius: 40px;
        color: white;
        cursor: pointer;
        transition: all 0.2s;
    }
    .filter-bar button:hover {
        background: #6d28d9;
    }
    .notes-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
        gap: 1.5rem;
    }
    .note-card {
        transition: transform 0.2s;
    }
    .note-card:hover {
        transform: translateY(-4px);
    }
    .note-title {
        font-size: 1.2rem;
        font-weight: 600;
        margin-bottom: 0.5rem;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .note-title a {
        color: #f0f0f0;
        text-decoration: none;
    }
    .note-title a:hover {
        color: #a78bfa;
    }
    .favorite-star {
        cursor: pointer;
        font-size: 1.3rem;
        transition: color 0.2s;
    }
    .favorite-star.active {
        color: #f59e0b;
    }
    .note-preview {
        color: #b0b0b0;
        font-size: 0.9rem;
        margin-bottom: 0.8rem;
        line-height: 1.4;
        max-height: 60px;
        overflow: hidden;
    }
    .note-meta {
        font-size: 0.75rem;
        color: #9ca3af;
        display: flex;
        flex-wrap: wrap;
        gap: 0.5rem;
        align-items: center;
        margin-top: 0.5rem;
    }
    .category-badge {
        background: rgba(124,58,237,0.2);
        padding: 0.2rem 0.5rem;
        border-radius: 40px;
        color: #a78bfa;
    }
    .tag {
        background: rgba(16,185,129,0.2);
        padding: 0.2rem 0.5rem;
        border-radius: 40px;
        color: #10b981;
        font-size: 0.7rem;
    }
    .note-actions {
        display: flex;
        gap: 0.5rem;
        margin-top: 0.5rem;
    }
    .note-actions a {
        color: #9ca3af;
        text-decoration: none;
        font-size: 0.8rem;
    }
    .note-actions a:hover {
        color: #ffffff;
    }
    .empty-state {
        text-align: center;
        padding: 3rem;
        color: #6b7280;
    }
    /* Modal styles (if not in header) */
    .modal {
        display: none;
        position: fixed;
        z-index: 100;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background: rgba(0,0,0,0.7);
        backdrop-filter: blur(5px);
        align-items: center;
        justify-content: center;
    }
    .modal.active {
        display: flex;
    }
    .modal-content {
        background: rgba(30,30,40,0.95);
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
        .notes-grid {
            grid-template-columns: 1fr;
        }
        .filter-bar {
            flex-direction: column;
        }
    }
</style>

<div class="notes-header">
    <h1>📝 Notes</h1>
    <button class="btn-primary" onclick="openAddNoteModal()">+ New Note</button>
</div>

<div class="filter-bar">
    <form method="get" action="notes.php" style="display: flex; gap: 0.5rem; flex-wrap: wrap; width: 100%;">
        <input type="text" name="search" placeholder="Search notes..." value="<?= htmlspecialchars($search) ?>" style="flex: 1;">
        <select name="category">
            <option value="">All Categories</option>
            <?php foreach ($categories as $cat): ?>
                <option value="<?= $cat['id'] ?>" <?= $filter_category == $cat['id'] ? 'selected' : '' ?>><?= htmlspecialchars($cat['name']) ?></option>
            <?php endforeach; ?>
        </select>
        <label style="display: flex; align-items: center; gap: 0.3rem; color: #d1d5db;">
            <input type="checkbox" name="favorite" value="1" <?= $filter_favorite ? 'checked' : '' ?>> Favorites only
        </label>
        <button type="submit">Apply Filters</button>
        <a href="notes.php" style="background: #2a2a3a; padding: 0.5rem 1rem; border-radius: 40px; text-decoration: none; color: #e0e0e0;">Clear</a>
    </form>
</div>

<div class="notes-grid">
    <?php if (count($notes_with_tags) > 0): ?>
        <?php foreach ($notes_with_tags as $note): ?>
            <div class="glass-card note-card">
                <div class="note-title">
                    <a href="#" onclick="openEditNoteModal(<?= $note['id'] ?>, '<?= htmlspecialchars(addslashes($note['title'])) ?>', '<?= htmlspecialchars(addslashes($note['content'])) ?>', <?= $note['category_id'] ?? 'null' ?>, '<?= htmlspecialchars(addslashes(implode(',', $note['tags']))) ?>')"><?= htmlspecialchars($note['title']) ?></a>
                    <a href="?action=toggle_favorite&note_id=<?= $note['id'] ?>&category=<?= $filter_category ?>&favorite=<?= $filter_favorite ?>&search=<?= urlencode($search) ?>" class="favorite-star <?= $note['is_favorite'] ? 'active' : '' ?>">★</a>
                </div>
                <div class="note-preview">
                    <?= nl2br(htmlspecialchars(substr($note['content'], 0, 120) . (strlen($note['content']) > 120 ? '...' : ''))) ?>
                </div>
                <div class="note-meta">
                    <?php if ($note['category_name']): ?>
                        <span class="category-badge"><?= htmlspecialchars($note['category_name']) ?></span>
                    <?php endif; ?>
                    <?php foreach ($note['tags'] as $tag): ?>
                        <span class="tag">#<?= htmlspecialchars($tag) ?></span>
                    <?php endforeach; ?>
                    <span class="date">Updated: <?= date('M j, Y', strtotime($note['updated_at'])) ?></span>
                </div>
                <div class="note-actions">
                    <a href="#" onclick="openEditNoteModal(<?= $note['id'] ?>, '<?= htmlspecialchars(addslashes($note['title'])) ?>', '<?= htmlspecialchars(addslashes($note['content'])) ?>', <?= $note['category_id'] ?? 'null' ?>, '<?= htmlspecialchars(addslashes(implode(',', $note['tags']))) ?>')">Edit</a>
                    <a href="?action=delete_note&note_id=<?= $note['id'] ?>&category=<?= $filter_category ?>&favorite=<?= $filter_favorite ?>&search=<?= urlencode($search) ?>" onclick="return confirm('Delete this note?')">Delete</a>
                </div>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="empty-state">
            <p>No notes found. Create your first note!</p>
        </div>
    <?php endif; ?>
</div>

<!-- Add Note Modal -->
<div id="addNoteModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>New Note</h2>
            <span class="close" onclick="closeAddNoteModal()">&times;</span>
        </div>
        <form method="post" action="notes.php">
            <input type="hidden" name="action" value="add_note">
            <div class="form-group">
                <label for="add_title">Title *</label>
                <input type="text" id="add_title" name="title" required>
            </div>
            <div class="form-group">
                <label for="add_content">Content</label>
                <textarea id="add_content" name="content" rows="6"></textarea>
            </div>
            <div class="form-group">
                <label for="add_category">Category</label>
                <select id="add_category" name="category_id">
                    <option value="">None</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <a href="#" class="add-link" onclick="openCategoryModal('add')">+ Create new</a>
            </div>
            <div class="form-group">
                <label for="add_tags">Tags (comma‑separated)</label>
                <input type="text" id="add_tags" name="tags" placeholder="e.g., work, idea, project">
            </div>
            <div class="modal-actions">
                <button type="button" class="btn-secondary" onclick="closeAddNoteModal()">Cancel</button>
                <button type="submit" class="btn-primary">Create Note</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Note Modal -->
<div id="editNoteModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Edit Note</h2>
            <span class="close" onclick="closeEditNoteModal()">&times;</span>
        </div>
        <form method="post" action="notes.php">
            <input type="hidden" name="action" value="edit_note">
            <input type="hidden" name="note_id" id="edit_note_id">
            <div class="form-group">
                <label for="edit_title">Title *</label>
                <input type="text" id="edit_title" name="title" required>
            </div>
            <div class="form-group">
                <label for="edit_content">Content</label>
                <textarea id="edit_content" name="content" rows="6"></textarea>
            </div>
            <div class="form-group">
                <label for="edit_category">Category</label>
                <select id="edit_category" name="category_id">
                    <option value="">None</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <a href="#" class="add-link" onclick="openCategoryModal('edit')">+ Create new</a>
            </div>
            <div class="form-group">
                <label for="edit_tags">Tags (comma‑separated)</label>
                <input type="text" id="edit_tags" name="tags" placeholder="e.g., work, idea, project">
            </div>
            <div class="modal-actions">
                <button type="button" class="btn-secondary" onclick="closeEditNoteModal()">Cancel</button>
                <button type="submit" class="btn-primary">Update Note</button>
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
        <form method="post" action="notes.php">
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

<script>
    function openAddNoteModal() {
        document.getElementById('addNoteModal').classList.add('active');
    }
    function closeAddNoteModal() {
        document.getElementById('addNoteModal').classList.remove('active');
    }

    function openEditNoteModal(id, title, content, category_id, tags) {
        document.getElementById('edit_note_id').value = id;
        document.getElementById('edit_title').value = title;
        document.getElementById('edit_content').value = content;
        document.getElementById('edit_category').value = category_id || '';
        document.getElementById('edit_tags').value = tags;
        document.getElementById('editNoteModal').classList.add('active');
    }
    function closeEditNoteModal() {
        document.getElementById('editNoteModal').classList.remove('active');
    }

    let currentModalContext = 'add';
    function openCategoryModal(context) {
        currentModalContext = context;
        document.getElementById('categoryModal').classList.add('active');
    }
    function closeCategoryModal() {
        document.getElementById('categoryModal').classList.remove('active');
    }

    window.onclick = function(event) {
        if (event.target.classList.contains('modal')) {
            event.target.classList.remove('active');
        }
    }
</script>

<?php include 'footer.php'; ?>