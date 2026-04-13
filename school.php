<?php
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// --- Helper functions ---
function formatGrade($points, $maxPoints) {
    if ($maxPoints > 0) {
        $percent = ($points / $maxPoints) * 100;
        return number_format($percent, 1) . '%';
    }
    return 'N/A';
}

// --- Handle actions ---
$action = $_POST['action'] ?? $_GET['action'] ?? '';
$id = $_POST['id'] ?? $_GET['id'] ?? 0;

// Course actions
if ($action === 'add_course' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $code = trim($_POST['code']);
    $instructor = trim($_POST['instructor']);
    $credits = (float)$_POST['credits'];
    $color = $_POST['color'];
    $notes = trim($_POST['notes']);
    $stmt = $pdo->prepare("INSERT INTO school_courses (user_id, name, code, instructor, credits, color, notes) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$user_id, $name, $code, $instructor, $credits, $color, $notes]);
    header('Location: school.php');
    exit;
}

if ($action === 'edit_course' && $_SERVER['REQUEST_METHOD'] === 'POST' && $id) {
    $name = trim($_POST['name']);
    $code = trim($_POST['code']);
    $instructor = trim($_POST['instructor']);
    $credits = (float)$_POST['credits'];
    $color = $_POST['color'];
    $notes = trim($_POST['notes']);
    $stmt = $pdo->prepare("UPDATE school_courses SET name=?, code=?, instructor=?, credits=?, color=?, notes=? WHERE id=? AND user_id=?");
    $stmt->execute([$name, $code, $instructor, $credits, $color, $notes, $id, $user_id]);
    header('Location: school.php');
    exit;
}

if ($action === 'delete_course' && $id) {
    $stmt = $pdo->prepare("DELETE FROM school_courses WHERE id=? AND user_id=?");
    $stmt->execute([$id, $user_id]);
    header('Location: school.php');
    exit;
}

// Timetable actions
if ($action === 'add_timetable' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $day_of_week = $_POST['day_of_week'];
    $start_time = $_POST['start_time'];
    $end_time = $_POST['end_time'];
    $course_id = (int)$_POST['course_id'];
    $room = trim($_POST['room']);
    $semester = trim($_POST['semester']);
    $type = trim($_POST['type']);
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    $stmt = $pdo->prepare("INSERT INTO school_timetable (user_id, day_of_week, start_time, end_time, course_id, room, semester, type, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$user_id, $day_of_week, $start_time, $end_time, $course_id, $room, $semester, $type, $is_active]);
    header('Location: school.php');
    exit;
}

if ($action === 'edit_timetable' && $_SERVER['REQUEST_METHOD'] === 'POST' && $id) {
    $day_of_week = $_POST['day_of_week'];
    $start_time = $_POST['start_time'];
    $end_time = $_POST['end_time'];
    $course_id = (int)$_POST['course_id'];
    $room = trim($_POST['room']);
    $semester = trim($_POST['semester']);
    $type = trim($_POST['type']);
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    $stmt = $pdo->prepare("UPDATE school_timetable SET day_of_week=?, start_time=?, end_time=?, course_id=?, room=?, semester=?, type=?, is_active=? WHERE id=? AND user_id=?");
    $stmt->execute([$day_of_week, $start_time, $end_time, $course_id, $room, $semester, $type, $is_active, $id, $user_id]);
    header('Location: school.php');
    exit;
}

if ($action === 'delete_timetable' && $id) {
    $stmt = $pdo->prepare("DELETE FROM school_timetable WHERE id=? AND user_id=?");
    $stmt->execute([$id, $user_id]);
    header('Location: school.php');
    exit;
}

// Assignment actions
if ($action === 'add_assignment' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $course_id = (int)$_POST['course_id'];
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $type = $_POST['type'];
    $due_datetime = $_POST['due_datetime'];
    $max_points = !empty($_POST['max_points']) ? (int)$_POST['max_points'] : null;
    $weight = !empty($_POST['weight']) ? (float)$_POST['weight'] : null;
    $stmt = $pdo->prepare("INSERT INTO school_assignments (user_id, course_id, title, description, type, due_datetime, max_points, weight) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$user_id, $course_id, $title, $description, $type, $due_datetime, $max_points, $weight]);
    header('Location: school.php');
    exit;
}

if ($action === 'edit_assignment' && $_SERVER['REQUEST_METHOD'] === 'POST' && $id) {
    $course_id = (int)$_POST['course_id'];
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $type = $_POST['type'];
    $due_datetime = $_POST['due_datetime'];
    $max_points = !empty($_POST['max_points']) ? (int)$_POST['max_points'] : null;
    $weight = !empty($_POST['weight']) ? (float)$_POST['weight'] : null;
    $achieved_points = !empty($_POST['achieved_points']) ? (int)$_POST['achieved_points'] : null;
    $status = $_POST['status'];
    $stmt = $pdo->prepare("UPDATE school_assignments SET course_id=?, title=?, description=?, type=?, due_datetime=?, max_points=?, weight=?, achieved_points=?, status=? WHERE id=? AND user_id=?");
    $stmt->execute([$course_id, $title, $description, $type, $due_datetime, $max_points, $weight, $achieved_points, $status, $id, $user_id]);
    header('Location: school.php');
    exit;
}

if ($action === 'delete_assignment' && $id) {
    $stmt = $pdo->prepare("DELETE FROM school_assignments WHERE id=? AND user_id=?");
    $stmt->execute([$id, $user_id]);
    header('Location: school.php');
    exit;
}

if ($action === 'grade_assignment' && $_SERVER['REQUEST_METHOD'] === 'POST' && $id) {
    $achieved_points = (int)$_POST['achieved_points'];
    $status = ($achieved_points !== null && $_POST['max_points']) ? 'graded' : 'submitted';
    $stmt = $pdo->prepare("UPDATE school_assignments SET achieved_points=?, status=? WHERE id=? AND user_id=?");
    $stmt->execute([$achieved_points, $status, $id, $user_id]);
    header('Location: school.php');
    exit;
}

// Fetch data
$stmt = $pdo->prepare("SELECT * FROM school_courses WHERE user_id = ? ORDER BY name");
$stmt->execute([$user_id]);
$courses = $stmt->fetchAll();

$stmt = $pdo->prepare("
    SELECT t.*, c.name as course_name, c.color as course_color
    FROM school_timetable t
    LEFT JOIN school_courses c ON t.course_id = c.id
    WHERE t.user_id = ?
    ORDER BY FIELD(t.day_of_week, 'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'), t.start_time
");
$stmt->execute([$user_id]);
$timetable = $stmt->fetchAll();

$stmt = $pdo->prepare("
    SELECT a.*, c.name as course_name, c.color as course_color
    FROM school_assignments a
    LEFT JOIN school_courses c ON a.course_id = c.id
    WHERE a.user_id = ?
    ORDER BY a.due_datetime ASC
");
$stmt->execute([$user_id]);
$assignments = $stmt->fetchAll();

// Upcoming assignments (due in next 7 days, not completed)
$stmt = $pdo->prepare("
    SELECT a.*, c.name as course_name
    FROM school_assignments a
    LEFT JOIN school_courses c ON a.course_id = c.id
    WHERE a.user_id = ? AND a.status != 'graded' AND a.due_datetime BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 7 DAY)
    ORDER BY a.due_datetime ASC
    LIMIT 5
");
$stmt->execute([$user_id]);
$upcoming_assignments = $stmt->fetchAll();

// Next class today
$dayMap = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
$todayName = $dayMap[date('N') - 1];
$currentTime = date('H:i:s');
$stmt = $pdo->prepare("
    SELECT t.*, c.name as course_name, c.color as course_color
    FROM school_timetable t
    JOIN school_courses c ON t.course_id = c.id
    WHERE t.user_id = ? AND t.day_of_week = ? AND t.start_time > ? AND t.is_active = 1
    ORDER BY t.start_time ASC
    LIMIT 1
");
$stmt->execute([$user_id, $todayName, $currentTime]);
$next_class = $stmt->fetch();

$pageTitle = 'School';
include 'header.php';
?>

<style>
    /* School-specific styles */
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
    .school-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 2rem;
        flex-wrap: wrap;
        gap: 1rem;
    }
    .school-header h1 {
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
        font-size: 1.8rem;
        font-weight: 700;
        color: #f0f0f0;
    }
    .section {
        margin-bottom: 2rem;
    }
    .section-title {
        font-size: 1.3rem;
        font-weight: 600;
        margin-bottom: 1rem;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .table-container {
        overflow-x: auto;
    }
    table {
        width: 100%;
        border-collapse: collapse;
    }
    th, td {
        text-align: left;
        padding: 0.8rem;
        border-bottom: 1px solid rgba(255,255,255,0.05);
    }
    th {
        color: #a78bfa;
        font-weight: 500;
    }
    .course-badge {
        display: inline-block;
        padding: 0.2rem 0.6rem;
        border-radius: 40px;
        font-size: 0.75rem;
        font-weight: 600;
        background: rgba(124,58,237,0.2);
    }
    .grade {
        font-weight: 600;
    }
    .grade-good {
        color: #10b981;
    }
    .grade-average {
        color: #f59e0b;
    }
    .grade-poor {
        color: #ef4444;
    }
    .empty-state {
        text-align: center;
        padding: 2rem;
        color: #6b7280;
    }
    @media (max-width: 768px) {
        th, td {
            padding: 0.5rem;
        }
        .stats-grid {
            grid-template-columns: 1fr 1fr;
        }
    }
</style>

<div class="school-header">
    <h1>🎓 School Manager</h1>
    <div>
        <button class="btn-primary" onclick="openModal('addCourseModal')">+ New Course</button>
        <button class="btn-secondary" onclick="openModal('addTimetableModal')" style="margin-left: 0.5rem;">+ Timetable</button>
        <button class="btn-secondary" onclick="openModal('addAssignmentModal')" style="margin-left: 0.5rem;">+ Assignment</button>
    </div>
</div>

<!-- Quick stats -->
<div class="stats-grid">
    <div class="glass-card stat-card">
        <h3>Courses</h3>
        <div class="value"><?= count($courses) ?></div>
    </div>
    <div class="glass-card stat-card">
        <h3>Assignments</h3>
        <div class="value"><?= count($assignments) ?></div>
    </div>
    <div class="glass-card stat-card">
        <h3>Upcoming (7 days)</h3>
        <div class="value"><?= count($upcoming_assignments) ?></div>
    </div>
</div>

<!-- Next class today -->
<?php if ($next_class): ?>
<div class="glass-card" style="margin-bottom: 2rem; padding: 1rem;">
    <div style="display: flex; align-items: center; gap: 1rem;">
        <span style="font-size: 2rem;">⏰</span>
        <div>
            <strong>Next Class Today</strong><br>
            <?= htmlspecialchars($next_class['course_name']) ?> at <?= date('g:i a', strtotime($next_class['start_time'])) ?> in <?= htmlspecialchars($next_class['room'] ?: 'TBD') ?>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Timetable Section -->
<div class="section">
    <div class="section-title">
        Weekly Timetable
        <button class="btn-secondary" onclick="openModal('addTimetableModal')">+ Add</button>
    </div>
    <div class="glass-card" style="padding: 0;">
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Day</th>
                        <th>Time</th>
                        <th>Course</th>
                        <th>Room</th>
                        <th>Type</th>
                        <th>Active</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($timetable) > 0): ?>
                        <?php foreach ($timetable as $tt): ?>
                            <tr>
                                <td><?= htmlspecialchars($tt['day_of_week']) ?></td>
                                <td><?= substr($tt['start_time'], 0, 5) ?> – <?= substr($tt['end_time'], 0, 5) ?></td>
                                <td><span class="course-badge" style="background: <?= $tt['course_color'] ?>20; color: <?= $tt['course_color'] ?>;"><?= htmlspecialchars($tt['course_name']) ?></span></td>
                                <td><?= htmlspecialchars($tt['room'] ?: '-') ?></td>
                                <td><?= htmlspecialchars($tt['type']) ?></td>
                                <td><?= $tt['is_active'] ? '✅' : '❌' ?></td>
                                <td>
                                    <a href="#" onclick="editTimetable(<?= htmlspecialchars(json_encode($tt)) ?>)" style="color:#a78bfa;">✏️</a>
                                    <a href="?action=delete_timetable&id=<?= $tt['id'] ?>" onclick="return confirm('Delete this timetable entry?')" style="color:#ef4444; margin-left: 0.5rem;">🗑️</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="7" class="empty-state">No timetable entries. Add one to organize your week.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Courses Section -->
<div class="section">
    <div class="section-title">
        Courses
        <button class="btn-secondary" onclick="openModal('addCourseModal')">+ Add Course</button>
    </div>
    <div class="glass-card" style="padding: 0;">
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Code</th>
                        <th>Instructor</th>
                        <th>Credits</th>
                        <th>Notes</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($courses) > 0): ?>
                        <?php foreach ($courses as $course): ?>
                            <tr>
                                <td><span style="display: inline-block; width: 12px; height: 12px; border-radius: 50%; background: <?= htmlspecialchars($course['color']) ?>; margin-right: 0.5rem;"></span><?= htmlspecialchars($course['name']) ?></td>
                                <td><?= htmlspecialchars($course['code'] ?: '-') ?></td>
                                <td><?= htmlspecialchars($course['instructor'] ?: '-') ?></td>
                                <td><?= $course['credits'] ?: '-' ?></td>
                                <td><?= htmlspecialchars(substr($course['notes'], 0, 50)) ?: '-' ?></td>
                                <td>
                                    <a href="#" onclick="editCourse(<?= htmlspecialchars(json_encode($course)) ?>)" style="color:#a78bfa;">✏️</a>
                                    <a href="?action=delete_course&id=<?= $course['id'] ?>" onclick="return confirm('Delete this course? All associated assignments and timetable entries will also be deleted.')" style="color:#ef4444; margin-left: 0.5rem;">🗑️</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="6" class="empty-state">No courses yet. Add your first course.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Assignments Section -->
<div class="section">
    <div class="section-title">
        Assignments
        <button class="btn-secondary" onclick="openModal('addAssignmentModal')">+ Add Assignment</button>
    </div>
    <div class="glass-card" style="padding: 0;">
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Due Date</th>
                        <th>Course</th>
                        <th>Title</th>
                        <th>Type</th>
                        <th>Max Points</th>
                        <th>Grade</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($assignments) > 0): ?>
                        <?php foreach ($assignments as $assign): 
                            $grade = '';
                            $gradeClass = '';
                            if ($assign['max_points'] && $assign['achieved_points'] !== null) {
                                $percent = ($assign['achieved_points'] / $assign['max_points']) * 100;
                                $grade = $assign['achieved_points'] . '/' . $assign['max_points'] . ' (' . number_format($percent, 1) . '%)';
                                if ($percent >= 90) $gradeClass = 'grade-good';
                                elseif ($percent >= 70) $gradeClass = 'grade-average';
                                else $gradeClass = 'grade-poor';
                            } else {
                                $grade = '-';
                            }
                        ?>
                            <tr>
                                <td><?= date('M j, Y g:i a', strtotime($assign['due_datetime'])) ?></td>
                                <td><span class="course-badge" style="background: <?= $assign['course_color'] ?>20; color: <?= $assign['course_color'] ?>;"><?= htmlspecialchars($assign['course_name']) ?></span></td>
                                <td><?= htmlspecialchars($assign['title']) ?></td>
                                <td><?= ucfirst($assign['type']) ?></td>
                                <td><?= $assign['max_points'] ?? '-' ?></td>
                                <td class="grade <?= $gradeClass ?>"><?= $grade ?></td>
                                <td><?= ucfirst($assign['status']) ?></td>
                                <td>
                                    <a href="#" onclick="editAssignment(<?= htmlspecialchars(json_encode($assign)) ?>)" style="color:#a78bfa;">✏️</a>
                                    <a href="?action=delete_assignment&id=<?= $assign['id'] ?>" onclick="return confirm('Delete this assignment?')" style="color:#ef4444; margin-left: 0.5rem;">🗑️</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="8" class="empty-state">No assignments yet. Add one to track your tasks.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Upcoming assignments quick view -->
<?php if (count($upcoming_assignments) > 0): ?>
<div class="section">
    <div class="section-title">
        Upcoming (next 7 days)
    </div>
    <div class="glass-card" style="padding: 0;">
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Due Date</th>
                        <th>Course</th>
                        <th>Title</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($upcoming_assignments as $assign): ?>
                        <tr>
                            <td><?= date('M j, Y g:i a', strtotime($assign['due_datetime'])) ?></td>
                            <td><?= htmlspecialchars($assign['course_name']) ?></td>
                            <td><?= htmlspecialchars($assign['title']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- MODALS -->

<!-- Add/Edit Course Modal -->
<div id="courseModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 id="courseModalTitle">Add Course</h2>
            <span class="close" onclick="closeModal('courseModal')">&times;</span>
        </div>
        <form method="post" action="school.php">
            <input type="hidden" name="action" id="courseAction" value="add_course">
            <input type="hidden" name="id" id="courseId">
            <div class="form-group">
                <label for="course_name">Course Name *</label>
                <input type="text" id="course_name" name="name" required>
            </div>
            <div class="form-group">
                <label for="course_code">Course Code</label>
                <input type="text" id="course_code" name="code">
            </div>
            <div class="form-group">
                <label for="course_instructor">Instructor</label>
                <input type="text" id="course_instructor" name="instructor">
            </div>
            <div class="form-group">
                <label for="course_credits">Credits</label>
                <input type="number" step="0.5" id="course_credits" name="credits">
            </div>
            <div class="form-group">
                <label for="course_color">Color (hex)</label>
                <input type="color" id="course_color" name="color" value="#7c3aed">
            </div>
            <div class="form-group">
                <label for="course_notes">Notes</label>
                <textarea id="course_notes" name="notes" rows="3"></textarea>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn-secondary" onclick="closeModal('courseModal')">Cancel</button>
                <button type="submit" class="btn-primary">Save Course</button>
            </div>
        </form>
    </div>
</div>

<!-- Add/Edit Timetable Modal -->
<div id="timetableModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 id="timetableModalTitle">Add Timetable Entry</h2>
            <span class="close" onclick="closeModal('timetableModal')">&times;</span>
        </div>
        <form method="post" action="school.php">
            <input type="hidden" name="action" id="timetableAction" value="add_timetable">
            <input type="hidden" name="id" id="timetableId">
            <div class="form-group">
                <label for="tt_day">Day of Week</label>
                <select id="tt_day" name="day_of_week" required>
                    <option value="Monday">Monday</option>
                    <option value="Tuesday">Tuesday</option>
                    <option value="Wednesday">Wednesday</option>
                    <option value="Thursday">Thursday</option>
                    <option value="Friday">Friday</option>
                    <option value="Saturday">Saturday</option>
                    <option value="Sunday">Sunday</option>
                </select>
            </div>
            <div class="form-group">
                <label for="tt_start">Start Time</label>
                <input type="time" id="tt_start" name="start_time" required>
            </div>
            <div class="form-group">
                <label for="tt_end">End Time</label>
                <input type="time" id="tt_end" name="end_time" required>
            </div>
            <div class="form-group">
                <label for="tt_course">Course</label>
                <select id="tt_course" name="course_id" required>
                    <option value="">Select course</option>
                    <?php foreach ($courses as $c): ?>
                        <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <a href="#" class="add-link" onclick="openModal('courseModal'); return false;">+ Create new course</a>
            </div>
            <div class="form-group">
                <label for="tt_room">Room/Location</label>
                <input type="text" id="tt_room" name="room">
            </div>
            <div class="form-group">
                <label for="tt_semester">Semester (optional)</label>
                <input type="text" id="tt_semester" name="semester">
            </div>
            <div class="form-group">
                <label for="tt_type">Type</label>
                <input type="text" id="tt_type" name="type" value="school">
            </div>
            <div class="form-group">
                <label><input type="checkbox" name="is_active" checked> Active</label>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn-secondary" onclick="closeModal('timetableModal')">Cancel</button>
                <button type="submit" class="btn-primary">Save Entry</button>
            </div>
        </form>
    </div>
</div>

<!-- Add/Edit Assignment Modal -->
<div id="assignmentModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 id="assignmentModalTitle">Add Assignment</h2>
            <span class="close" onclick="closeModal('assignmentModal')">&times;</span>
        </div>
        <form method="post" action="school.php">
            <input type="hidden" name="action" id="assignmentAction" value="add_assignment">
            <input type="hidden" name="id" id="assignmentId">
            <div class="form-group">
                <label for="assign_course">Course</label>
                <select id="assign_course" name="course_id" required>
                    <option value="">Select course</option>
                    <?php foreach ($courses as $c): ?>
                        <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="assign_title">Title *</label>
                <input type="text" id="assign_title" name="title" required>
            </div>
            <div class="form-group">
                <label for="assign_description">Description</label>
                <textarea id="assign_description" name="description" rows="3"></textarea>
            </div>
            <div class="form-group">
                <label for="assign_type">Type</label>
                <select id="assign_type" name="type">
                    <option value="homework">Homework</option>
                    <option value="quiz">Quiz</option>
                    <option value="exam">Exam</option>
                    <option value="project">Project</option>
                    <option value="other">Other</option>
                </select>
            </div>
            <div class="form-group">
                <label for="assign_due">Due Date & Time</label>
                <input type="datetime-local" id="assign_due" name="due_datetime" required>
            </div>
            <div class="form-group">
                <label for="assign_max_points">Max Points (optional)</label>
                <input type="number" id="assign_max_points" name="max_points">
            </div>
            <div class="form-group">
                <label for="assign_weight">Weight (%) (optional)</label>
                <input type="number" step="0.1" id="assign_weight" name="weight">
            </div>
            <div class="form-group" id="gradeGroup" style="display: none;">
                <label for="assign_achieved">Achieved Points (for grading)</label>
                <input type="number" id="assign_achieved" name="achieved_points">
            </div>
            <div class="form-group" id="statusGroup" style="display: none;">
                <label for="assign_status">Status</label>
                <select id="assign_status" name="status">
                    <option value="pending">Pending</option>
                    <option value="submitted">Submitted</option>
                    <option value="graded">Graded</option>
                    <option value="missed">Missed</option>
                </select>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn-secondary" onclick="closeModal('assignmentModal')">Cancel</button>
                <button type="submit" class="btn-primary">Save Assignment</button>
            </div>
        </form>
    </div>
</div>

<script>
    // Modal helpers
    function openModal(id) {
        document.getElementById(id).classList.add('active');
    }
    function closeModal(id) {
        document.getElementById(id).classList.remove('active');
    }

    // Course edit
    function editCourse(course) {
        document.getElementById('courseModalTitle').innerText = 'Edit Course';
        document.getElementById('courseAction').value = 'edit_course';
        document.getElementById('courseId').value = course.id;
        document.getElementById('course_name').value = course.name;
        document.getElementById('course_code').value = course.code;
        document.getElementById('course_instructor').value = course.instructor;
        document.getElementById('course_credits').value = course.credits;
        document.getElementById('course_color').value = course.color;
        document.getElementById('course_notes').value = course.notes;
        openModal('courseModal');
    }

    // Reset course modal for add
    function resetCourseModal() {
        document.getElementById('courseModalTitle').innerText = 'Add Course';
        document.getElementById('courseAction').value = 'add_course';
        document.getElementById('courseId').value = '';
        document.getElementById('course_name').value = '';
        document.getElementById('course_code').value = '';
        document.getElementById('course_instructor').value = '';
        document.getElementById('course_credits').value = '';
        document.getElementById('course_color').value = '#7c3aed';
        document.getElementById('course_notes').value = '';
    }

    // Timetable edit
    function editTimetable(tt) {
        document.getElementById('timetableModalTitle').innerText = 'Edit Timetable Entry';
        document.getElementById('timetableAction').value = 'edit_timetable';
        document.getElementById('timetableId').value = tt.id;
        document.getElementById('tt_day').value = tt.day_of_week;
        document.getElementById('tt_start').value = tt.start_time;
        document.getElementById('tt_end').value = tt.end_time;
        document.getElementById('tt_course').value = tt.course_id;
        document.getElementById('tt_room').value = tt.room;
        document.getElementById('tt_semester').value = tt.semester;
        document.getElementById('tt_type').value = tt.type;
        document.getElementById('tt_is_active').checked = tt.is_active == 1;
        openModal('timetableModal');
    }

    function resetTimetableModal() {
        document.getElementById('timetableModalTitle').innerText = 'Add Timetable Entry';
        document.getElementById('timetableAction').value = 'add_timetable';
        document.getElementById('timetableId').value = '';
        document.getElementById('tt_day').value = 'Monday';
        document.getElementById('tt_start').value = '';
        document.getElementById('tt_end').value = '';
        document.getElementById('tt_course').value = '';
        document.getElementById('tt_room').value = '';
        document.getElementById('tt_semester').value = '';
        document.getElementById('tt_type').value = 'school';
        document.getElementById('tt_is_active').checked = true;
    }

    // Assignment edit
    function editAssignment(assign) {
        document.getElementById('assignmentModalTitle').innerText = 'Edit Assignment';
        document.getElementById('assignmentAction').value = 'edit_assignment';
        document.getElementById('assignmentId').value = assign.id;
        document.getElementById('assign_course').value = assign.course_id;
        document.getElementById('assign_title').value = assign.title;
        document.getElementById('assign_description').value = assign.description;
        document.getElementById('assign_type').value = assign.type;
        document.getElementById('assign_due').value = assign.due_datetime.slice(0,16);
        document.getElementById('assign_max_points').value = assign.max_points;
        document.getElementById('assign_weight').value = assign.weight;
        document.getElementById('assign_achieved').value = assign.achieved_points;
        document.getElementById('assign_status').value = assign.status;
        // Show extra fields for edit
        document.getElementById('gradeGroup').style.display = 'block';
        document.getElementById('statusGroup').style.display = 'block';
        openModal('assignmentModal');
    }

    function resetAssignmentModal() {
        document.getElementById('assignmentModalTitle').innerText = 'Add Assignment';
        document.getElementById('assignmentAction').value = 'add_assignment';
        document.getElementById('assignmentId').value = '';
        document.getElementById('assign_course').value = '';
        document.getElementById('assign_title').value = '';
        document.getElementById('assign_description').value = '';
        document.getElementById('assign_type').value = 'homework';
        document.getElementById('assign_due').value = '';
        document.getElementById('assign_max_points').value = '';
        document.getElementById('assign_weight').value = '';
        document.getElementById('assign_achieved').value = '';
        document.getElementById('assign_status').value = 'pending';
        document.getElementById('gradeGroup').style.display = 'none';
        document.getElementById('statusGroup').style.display = 'none';
    }

    // Override openModal for specific modals to reset if needed
    const originalOpen = openModal;
    window.openModal = function(id) {
        if (id === 'courseModal') resetCourseModal();
        if (id === 'timetableModal') resetTimetableModal();
        if (id === 'assignmentModal') resetAssignmentModal();
        originalOpen(id);
    }

    window.onclick = function(event) {
        if (event.target.classList.contains('modal')) {
            event.target.classList.remove('active');
        }
    }
</script>

<?php include 'footer.php'; ?>