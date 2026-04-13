<?php
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$event_id = $_POST['event_id'] ?? $_GET['event_id'] ?? 0;

// --- Add Event (unchanged) ---
if ($action === 'add_event' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $start_datetime = $_POST['start_datetime'] ?? null;
    $end_datetime = $_POST['end_datetime'] ?? null;
    $all_day = isset($_POST['all_day']) ? 1 : 0;
    $category_id = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null;
    $recurrence_rule = trim($_POST['recurrence_rule'] ?? '');

    if (!empty($title) && $start_datetime && $end_datetime) {
        $stmt = $pdo->prepare("
            INSERT INTO events (user_id, title, description, location, start_datetime, end_datetime, all_day, category_id, recurrence_rule)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$user_id, $title, $description, $location, $start_datetime, $end_datetime, $all_day, $category_id, $recurrence_rule]);
    }
    header("Location: calendar.php");
    exit;
}

// --- Edit Event (unchanged) ---
if ($action === 'edit_event' && $_SERVER['REQUEST_METHOD'] === 'POST' && $event_id) {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $start_datetime = $_POST['start_datetime'] ?? null;
    $end_datetime = $_POST['end_datetime'] ?? null;
    $all_day = isset($_POST['all_day']) ? 1 : 0;
    $category_id = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null;
    $recurrence_rule = trim($_POST['recurrence_rule'] ?? '');

    if (!empty($title) && $start_datetime && $end_datetime) {
        $stmt = $pdo->prepare("
            UPDATE events
            SET title = ?, description = ?, location = ?, start_datetime = ?, end_datetime = ?, all_day = ?, category_id = ?, recurrence_rule = ?
            WHERE id = ? AND user_id = ?
        ");
        $stmt->execute([$title, $description, $location, $start_datetime, $end_datetime, $all_day, $category_id, $recurrence_rule, $event_id, $user_id]);
    }
    header("Location: calendar.php");
    exit;
}

// --- Delete Event (unchanged) ---
if ($action === 'delete_event' && $event_id) {
    $stmt = $pdo->prepare("DELETE FROM events WHERE id = ? AND user_id = ?");
    $stmt->execute([$event_id, $user_id]);
    header("Location: calendar.php");
    exit;
}

// --- Add Course (for timetables) ---
if ($action === 'add_course' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $course_name = trim($_POST['course_name'] ?? '');
    if (!empty($course_name)) {
        $stmt = $pdo->prepare("INSERT INTO school_courses (user_id, name) VALUES (?, ?)");
        $stmt->execute([$user_id, $course_name]);
    }
    header("Location: calendar.php");
    exit;
}

// --- Add Timetable (now requires course_id) ---
if ($action === 'add_timetable' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $day_of_week = $_POST['day_of_week'] ?? '';
    $start_time = $_POST['start_time'] ?? null;
    $end_time = $_POST['end_time'] ?? null;
    $course_id = !empty($_POST['course_id']) ? (int)$_POST['course_id'] : null;
    $room = trim($_POST['room'] ?? '');
    $semester = trim($_POST['semester'] ?? '');
    $type = trim($_POST['timetable_type'] ?? 'school');
    $is_active = 1;

    if ($day_of_week && $start_time && $end_time && $course_id) {
        $stmt = $pdo->prepare("
            INSERT INTO school_timetable (user_id, day_of_week, start_time, end_time, course_id, room, semester, type, is_active)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$user_id, $day_of_week, $start_time, $end_time, $course_id, $room, $semester, $type, $is_active]);
    }
    header("Location: calendar.php");
    exit;
}

// --- Delete Timetable ---
if ($action === 'delete_timetable' && isset($_GET['tt_id'])) {
    $tt_id = (int)$_GET['tt_id'];
    $stmt = $pdo->prepare("DELETE FROM school_timetable WHERE id = ? AND user_id = ?");
    $stmt->execute([$tt_id, $user_id]);
    header("Location: calendar.php");
    exit;
}

// --- Calendar data (same as before) ---
$year = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');
$month = isset($_GET['month']) ? (int)$_GET['month'] : date('m');
$current_timestamp = mktime(0, 0, 0, $month, 1, $year);
$first_day_of_month = date('w', $current_timestamp);
$days_in_month = date('t', $current_timestamp);

$prev_month = $month - 1;
$prev_year = $year;
if ($prev_month < 1) { $prev_month = 12; $prev_year--; }
$next_month = $month + 1;
$next_year = $year;
if ($next_month > 12) { $next_month = 1; $next_year++; }

$start_date = date('Y-m-d', strtotime("-{$first_day_of_month} days", $current_timestamp));
$end_date = date('Y-m-d', strtotime("+" . (42 - $days_in_month - $first_day_of_month) . " days", $current_timestamp));

$stmt = $pdo->prepare("SELECT * FROM events WHERE user_id = ? AND start_datetime BETWEEN ? AND ? ORDER BY start_datetime");
$stmt->execute([$user_id, $start_date . ' 00:00:00', $end_date . ' 23:59:59']);
$events = $stmt->fetchAll();

// Fetch timetables with course names
$stmt = $pdo->prepare("
    SELECT t.*, c.name as course_name 
    FROM school_timetable t
    LEFT JOIN school_courses c ON t.course_id = c.id
    WHERE t.user_id = ? AND t.is_active = 1
");
$stmt->execute([$user_id]);
$timetables = $stmt->fetchAll();

$stmt = $pdo->prepare("SELECT id, name, target_date FROM goals WHERE user_id = ? AND target_date BETWEEN ? AND ? AND status = 'active'");
$stmt->execute([$user_id, $start_date, $end_date]);
$goals = $stmt->fetchAll();

$stmt = $pdo->prepare("SELECT * FROM events WHERE user_id = ? AND start_datetime >= NOW() ORDER BY start_datetime ASC LIMIT 10");
$stmt->execute([$user_id]);
$upcoming_events = $stmt->fetchAll();

$stmt = $pdo->prepare("SELECT id, name, color FROM categories WHERE user_id = ? AND (type = 'event' OR type IS NULL) ORDER BY name");
$stmt->execute([$user_id]);
$categories = $stmt->fetchAll();

// Fetch courses for dropdown in timetable modal
$stmt = $pdo->prepare("SELECT id, name FROM school_courses WHERE user_id = ? ORDER BY name");
$stmt->execute([$user_id]);
$courses = $stmt->fetchAll();

$pageTitle = 'Calendar';
include 'header.php';
?>

<style>
    /* (All CSS styles from previous version – unchanged) */
    .calendar-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem; }
    .calendar-header h1 { font-size: 2rem; font-weight: 700; background: linear-gradient(to right, #ffffff, #c0c0ff); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; }
    .month-nav { display: flex; gap: 0.5rem; }
    .month-nav a { background: rgba(30,30,40,0.6); backdrop-filter: blur(5px); border: 1px solid rgba(255,255,255,0.05); padding: 0.5rem 1rem; border-radius: 40px; color: #e0e0e0; text-decoration: none; transition: all 0.2s; }
    .month-nav a:hover { background: #2a2a3a; }
    .calendar-grid { display: grid; grid-template-columns: repeat(7, 1fr); background: rgba(20,20,30,0.5); backdrop-filter: blur(8px); border-radius: 24px; overflow: hidden; margin-bottom: 2rem; border: 1px solid rgba(255,255,255,0.05); }
    .calendar-weekday { padding: 0.8rem; text-align: center; font-weight: 600; color: #a78bfa; border-bottom: 1px solid rgba(255,255,255,0.05); background: rgba(30,30,40,0.3); }
    .calendar-day { min-height: 100px; padding: 0.5rem; border: 1px solid rgba(255,255,255,0.05); background: rgba(0,0,0,0.2); transition: background 0.2s; position: relative; }
    .calendar-day:hover { background: rgba(255,255,255,0.05); }
    .day-number { font-size: 0.9rem; font-weight: 500; color: #d1d5db; margin-bottom: 0.3rem; display: inline-block; width: 28px; height: 28px; line-height: 28px; text-align: center; border-radius: 50%; }
    .day-number.today { background: linear-gradient(145deg, #7c3aed, #4f46e5); color: white; box-shadow: 0 0 10px rgba(124,58,237,0.5); }
    .event-badge { background: rgba(124,58,237,0.2); border-left: 3px solid #7c3aed; padding: 0.2rem 0.3rem; margin-bottom: 0.2rem; font-size: 0.7rem; border-radius: 4px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; cursor: pointer; transition: all 0.2s; }
    .event-badge:hover { background: rgba(124,58,237,0.4); }
    .event-badge.timetable { border-left-color: #10b981; background: rgba(16,185,129,0.1); }
    .event-badge.goal { border-left-color: #f59e0b; background: rgba(245,158,11,0.1); }
    .event-badge.holiday { border-left-color: #ef4444; background: rgba(239,68,68,0.1); }
    .agenda-section { background: rgba(20,20,30,0.5); backdrop-filter: blur(8px); border-radius: 24px; padding: 1.5rem; margin-top: 1rem; border: 1px solid rgba(255,255,255,0.05); }
    .agenda-section h2 { font-size: 1.2rem; margin-bottom: 1rem; display: flex; justify-content: space-between; align-items: center; }
    .agenda-list { list-style: none; }
    .agenda-list li { padding: 0.6rem 0; border-bottom: 1px solid rgba(255,255,255,0.05); display: flex; justify-content: space-between; }
    .agenda-list .date { font-size: 0.8rem; color: #9ca3af; }
    .add-button { background: #7c3aed; border: none; border-radius: 40px; padding: 0.3rem 0.8rem; color: white; cursor: pointer; transition: all 0.2s; }
    .add-button:hover { background: #6d28d9; transform: scale(1.02); }
    .modal { display: none; position: fixed; z-index: 100; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); backdrop-filter: blur(5px); align-items: center; justify-content: center; }
    .modal.active { display: flex; }
    .modal-content { background: rgba(30,30,40,0.95); backdrop-filter: blur(12px); border: 1px solid rgba(255,255,255,0.05); border-radius: 32px; padding: 2rem; max-width: 500px; width: 90%; max-height: 90vh; overflow-y: auto; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.5); }
    .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; }
    .modal-header h2 { font-size: 1.5rem; font-weight: 700; color: #f0f0f0; }
    .close { font-size: 1.5rem; cursor: pointer; color: #9ca3af; transition: color 0.2s; }
    .close:hover { color: #ffffff; }
    .form-group { margin-bottom: 1.2rem; }
    label { display: block; margin-bottom: 0.3rem; font-weight: 500; color: #d1d5db; font-size: 0.9rem; }
    input, textarea, select { width: 100%; padding: 0.8rem 1rem; background: #2a2a3a; border: 1.5px solid #333; border-radius: 16px; font-size: 0.95rem; color: #f0f0f0; transition: all 0.2s; font-family: 'Inter', sans-serif; }
    input:focus, textarea:focus, select:focus { outline: none; border-color: #7c3aed; box-shadow: 0 0 0 3px rgba(124,58,237,0.2); }
    textarea { min-height: 100px; resize: vertical; }
    .modal-actions { display: flex; justify-content: flex-end; gap: 1rem; margin-top: 1.5rem; }
    @media (max-width: 768px) { .calendar-day { min-height: 70px; font-size: 0.8rem; } .event-badge { font-size: 0.6rem; } .calendar-header h1 { font-size: 1.5rem; } .month-nav a { padding: 0.3rem 0.8rem; font-size: 0.9rem; } .agenda-list li { flex-direction: column; gap: 0.3rem; } }
</style>

<div class="calendar-header">
    <h1>📅 Calendar</h1>
    <div class="month-nav">
        <a href="?year=<?= $prev_year ?>&month=<?= $prev_month ?>">← Prev</a>
        <a href="?year=<?= date('Y') ?>&month=<?= date('m') ?>">Today</a>
        <a href="?year=<?= $next_year ?>&month=<?= $next_month ?>">Next →</a>
    </div>
    <button class="btn-primary" onclick="openEventModal()">+ New Event</button>
</div>

<?php
// Group events by date (same as before)
$events_by_date = [];
foreach ($events as $e) {
    $date = date('Y-m-d', strtotime($e['start_datetime']));
    $events_by_date[$date][] = ['type' => 'event', 'title' => $e['title'], 'id' => $e['id'], 'start' => $e['start_datetime']];
}
// Add timetables (now with course_name)
$current_day = strtotime($start_date);
$end_ts = strtotime($end_date);
while ($current_day <= $end_ts) {
    $day_name = date('l', $current_day);
    foreach ($timetables as $tt) {
        if ($tt['day_of_week'] === $day_name) {
            $date = date('Y-m-d', $current_day);
            $events_by_date[$date][] = [
                'type' => 'timetable',
                'title' => $tt['course_name'] ?? 'Class',
                'id' => $tt['id'],
                'start_time' => substr($tt['start_time'], 0, 5),
                'end_time' => substr($tt['end_time'], 0, 5),
                'room' => $tt['room']
            ];
        }
    }
    $current_day = strtotime('+1 day', $current_day);
}
// Add goals
foreach ($goals as $g) {
    $events_by_date[$g['target_date']][] = ['type' => 'goal', 'title' => $g['name'], 'id' => $g['id']];
}
// Public holidays (simple static list)
$holidays = [
    '2025-01-01' => 'New Year\'s Day',
    '2025-12-25' => 'Christmas Day',
    '2025-07-04' => 'Independence Day',
];
foreach ($holidays as $hol_date => $hol_name) {
    if (substr($hol_date, 0, 4) == $year) {
        $events_by_date[$hol_date][] = ['type' => 'holiday', 'title' => $hol_name];
    }
}
?>

<!-- Calendar Grid (unchanged) -->
<div class="calendar-grid">
    <?php
    $weekdays = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
    foreach ($weekdays as $wd) echo "<div class='calendar-weekday'>$wd</div>";

    $today = date('Y-m-d');
    for ($i = 0; $i < 42; $i++) {
        $cell_date = strtotime("+$i days", strtotime($start_date));
        $date_str = date('Y-m-d', $cell_date);
        $day_num = date('j', $cell_date);
        $is_today = ($date_str == $today);
        $class = 'calendar-day';
        if (date('m', $cell_date) != $month) $class .= ' opacity-50';
        echo "<div class='$class'>";
        echo "<div class='day-number" . ($is_today ? ' today' : '') . "'>$day_num</div>";
        if (isset($events_by_date[$date_str])) {
            foreach ($events_by_date[$date_str] as $event) {
                $badge_class = 'event-badge';
                if ($event['type'] == 'timetable') $badge_class .= ' timetable';
                elseif ($event['type'] == 'goal') $badge_class .= ' goal';
                elseif ($event['type'] == 'holiday') $badge_class .= ' holiday';
                echo "<div class='$badge_class' onclick='showEventDetails(" . json_encode($event) . ")'>";
                echo htmlspecialchars($event['title']);
                if ($event['type'] == 'timetable' && isset($event['start_time'])) echo " ({$event['start_time']})";
                echo "</div>";
            }
        }
        echo "</div>";
    }
    ?>
</div>

<!-- Agenda -->
<div class="agenda-section">
    <h2>
        Upcoming Events
        <button class="add-button" onclick="openTimetableModal()">+ Add Timetable</button>
    </h2>
    <ul class="agenda-list">
        <?php if (count($upcoming_events) > 0): ?>
            <?php foreach ($upcoming_events as $ev): ?>
                <li>
                    <span><?= htmlspecialchars($ev['title']) ?></span>
                    <span class="date"><?= date('M j, g:i a', strtotime($ev['start_datetime'])) ?></span>
                </li>
            <?php endforeach; ?>
        <?php else: ?>
            <li>No upcoming events.</li>
        <?php endif; ?>
    </ul>
</div>

<div class="agenda-section">
    <h2>Your Timetables (Weekly)</h2>
    <ul class="agenda-list">
        <?php if (count($timetables) > 0): ?>
            <?php foreach ($timetables as $tt): ?>
                <li>
                    <span><?= $tt['day_of_week'] ?>: <?= htmlspecialchars($tt['course_name']) ?> (<?= substr($tt['start_time'],0,5) ?> - <?= substr($tt['end_time'],0,5) ?>)</span>
                    <a href="?action=delete_timetable&tt_id=<?= $tt['id'] ?>" onclick="return confirm('Delete this timetable entry?')" style="color:#ef4444;">🗑️</a>
                </li>
            <?php endforeach; ?>
        <?php else: ?>
            <li>No timetables set. <a href="#" onclick="openTimetableModal()" style="color:#a78bfa;">Add one</a>.</li>
        <?php endif; ?>
    </ul>
</div>

<!-- Event Modal (unchanged) -->
<div id="eventModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 id="eventModalTitle">Add Event</h2>
            <span class="close" onclick="closeEventModal()">&times;</span>
        </div>
        <form method="post" action="calendar.php">
            <input type="hidden" name="action" id="eventAction" value="add_event">
            <input type="hidden" name="event_id" id="event_id">
            <div class="form-group">
                <label for="event_title">Title *</label>
                <input type="text" id="event_title" name="title" required>
            </div>
            <div class="form-group">
                <label for="event_description">Description</label>
                <textarea id="event_description" name="description"></textarea>
            </div>
            <div class="form-group">
                <label for="event_location">Location</label>
                <input type="text" id="event_location" name="location">
            </div>
            <div class="form-group">
                <label for="event_start">Start *</label>
                <input type="datetime-local" id="event_start" name="start_datetime" required>
            </div>
            <div class="form-group">
                <label for="event_end">End *</label>
                <input type="datetime-local" id="event_end" name="end_datetime" required>
            </div>
            <div class="form-group">
                <label><input type="checkbox" name="all_day" id="event_all_day"> All day</label>
            </div>
            <div class="form-group">
                <label for="event_category">Category</label>
                <select id="event_category" name="category_id">
                    <option value="">None</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="event_recurrence">Recurrence (optional)</label>
                <input type="text" id="event_recurrence" name="recurrence_rule" placeholder="e.g., weekly">
            </div>
            <div class="modal-actions">
                <button type="button" class="btn-secondary" onclick="closeEventModal()">Cancel</button>
                <button type="submit" class="btn-primary" id="eventSubmit">Create Event</button>
            </div>
        </form>
    </div>
</div>

<!-- Timetable Modal (updated with course dropdown) -->
<div id="timetableModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Add Weekly Timetable</h2>
            <span class="close" onclick="closeTimetableModal()">&times;</span>
        </div>
        <form method="post" action="calendar.php">
            <input type="hidden" name="action" value="add_timetable">
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
                    <option value="">Select a course</option>
                    <?php foreach ($courses as $c): ?>
                        <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <a href="#" class="add-link" onclick="openCourseModal()">+ Create new course</a>
            </div>
            <div class="form-group">
                <label for="tt_room">Room/Location</label>
                <input type="text" id="tt_room" name="room">
            </div>
            <div class="form-group">
                <label for="tt_semester">Semester (optional)</label>
                <input type="text" id="tt_semester" name="semester" placeholder="e.g., Fall 2025">
            </div>
            <div class="form-group">
                <label for="tt_type">Type</label>
                <select id="tt_type" name="timetable_type">
                    <option value="school">School</option>
                    <option value="work">Work</option>
                    <option value="personal">Personal</option>
                    <option value="other">Other</option>
                </select>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn-secondary" onclick="closeTimetableModal()">Cancel</button>
                <button type="submit" class="btn-primary">Add to Timetable</button>
            </div>
        </form>
    </div>
</div>

<!-- Course Creation Modal -->
<div id="courseModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Create New Course</h2>
            <span class="close" onclick="closeCourseModal()">&times;</span>
        </div>
        <form method="post" action="calendar.php">
            <input type="hidden" name="action" value="add_course">
            <div class="form-group">
                <label for="course_name">Course Name</label>
                <input type="text" id="course_name" name="course_name" required>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn-secondary" onclick="closeCourseModal()">Cancel</button>
                <button type="submit" class="btn-primary">Create Course</button>
            </div>
        </form>
    </div>
</div>

<script>
    // Event modal functions (unchanged)
    function openEventModal(eventData = null) {
        if (eventData) {
            document.getElementById('eventModalTitle').innerText = 'Edit Event';
            document.getElementById('eventAction').value = 'edit_event';
            document.getElementById('event_id').value = eventData.id;
            document.getElementById('event_title').value = eventData.title;
            document.getElementById('event_description').value = eventData.description || '';
            document.getElementById('event_location').value = eventData.location || '';
            document.getElementById('event_start').value = eventData.start_datetime.slice(0,16);
            document.getElementById('event_end').value = eventData.end_datetime.slice(0,16);
            document.getElementById('event_all_day').checked = eventData.all_day == 1;
            document.getElementById('event_category').value = eventData.category_id || '';
            document.getElementById('event_recurrence').value = eventData.recurrence_rule || '';
            document.getElementById('eventSubmit').innerText = 'Update Event';
        } else {
            document.getElementById('eventModalTitle').innerText = 'Add Event';
            document.getElementById('eventAction').value = 'add_event';
            document.getElementById('event_id').value = '';
            document.getElementById('event_title').value = '';
            document.getElementById('event_description').value = '';
            document.getElementById('event_location').value = '';
            document.getElementById('event_start').value = '';
            document.getElementById('event_end').value = '';
            document.getElementById('event_all_day').checked = false;
            document.getElementById('event_category').value = '';
            document.getElementById('event_recurrence').value = '';
            document.getElementById('eventSubmit').innerText = 'Create Event';
        }
        document.getElementById('eventModal').classList.add('active');
    }
    function closeEventModal() { document.getElementById('eventModal').classList.remove('active'); }

    function openTimetableModal() { document.getElementById('timetableModal').classList.add('active'); }
    function closeTimetableModal() { document.getElementById('timetableModal').classList.remove('active'); }

    function openCourseModal() { document.getElementById('courseModal').classList.add('active'); }
    function closeCourseModal() { document.getElementById('courseModal').classList.remove('active'); }

    function showEventDetails(event) { alert(event.title + (event.start_time ? ' at ' + event.start_time : '')); }

    window.onclick = function(event) {
        if (event.target.classList.contains('modal')) {
            event.target.classList.remove('active');
        }
    }
</script>

<?php include 'footer.php'; ?>