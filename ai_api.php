<?php
session_start();
require_once 'config/database.php';
require_once 'config_api.php'; // must define GEMINI_API_KEY

// Only logged-in users can use this
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

$user_id = $_SESSION['user_id'];

// Get user data for context
$stmt = $pdo->prepare("SELECT full_name, preferences FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();
$preferences = json_decode($user['preferences'], true) ?? [];
$role = $preferences['role'] ?? 'user';

// Get input
$input = json_decode(file_get_contents('php://input'), true);
$message = $input['message'] ?? '';

if (empty($message)) {
    echo json_encode(['success' => false, 'error' => 'Empty message']);
    exit;
}

// --- Build system prompt with database schema and available actions ---
$systemPrompt = "You are an AI life manager assistant that helps users manage their tasks, calendar, notes, finance, school, and goals.
You can have natural conversations and also perform actions by outputting a JSON command when needed.

The user's role is: {$role}.

**Important**: If the user asks a question that can be answered by querying their data (e.g., 'what's on my timetable?', 'what tasks do I have today?'), use the appropriate action (list_timetable, list_tasks, list_events). If the user asks for advice, suggestions, or general help, just respond with helpful text (no JSON). You can also ask clarifying questions.

Available actions with JSON format:

1. **Create Task**
   {\"action\":\"create_task\",\"data\":{\"title\":\"...\",\"description\":\"...\",\"due_date\":\"YYYY-MM-DD\",\"priority\":\"low/medium/high/urgent\",\"category_id\":null,\"project_id\":null}}

2. **List Tasks** (filter by status)
   {\"action\":\"list_tasks\",\"data\":{\"filter\":\"all/pending/completed\",\"limit\":5}}

3. **Update Task** (mark complete, change details)
   {\"action\":\"update_task\",\"data\":{\"task_id\":1,\"status\":\"completed\",\"title\":\"...\",\"description\":\"...\",\"due_date\":\"...\",\"priority\":\"...\"}}

4. **Delete Task**
   {\"action\":\"delete_task\",\"data\":{\"task_id\":1}}

5. **Create Event**
   {\"action\":\"create_event\",\"data\":{\"title\":\"...\",\"description\":\"...\",\"location\":\"...\",\"start_datetime\":\"YYYY-MM-DD HH:MM:SS\",\"end_datetime\":\"...\",\"all_day\":true/false}}

6. **List Events** (upcoming, today, etc.)
   {\"action\":\"list_events\",\"data\":{\"upcoming\":true,\"limit\":5,\"day\":\"today\"}}  (day can be 'today' or a date)

7. **Create Note**
   {\"action\":\"create_note\",\"data\":{\"title\":\"...\",\"content\":\"...\",\"category_id\":null,\"tags\":\"comma,separated\"}}

8. **List Notes**
   {\"action\":\"list_notes\",\"data\":{\"limit\":5}}

9. **Create Goal**
   {\"action\":\"create_goal\",\"data\":{\"name\":\"...\",\"description\":\"...\",\"target_value\":100,\"current_value\":0,\"unit\":\"kg\",\"target_date\":\"YYYY-MM-DD\",\"status\":\"active\"}}

10. **Update Goal Progress**
    {\"action\":\"update_goal\",\"data\":{\"goal_id\":1,\"current_value\":50}}

11. **Add Transaction**
    {\"action\":\"add_transaction\",\"data\":{\"account_id\":1,\"category_id\":1,\"amount\":25.50,\"type\":\"expense\",\"description\":\"...\",\"date\":\"YYYY-MM-DD\"}}

12. **Analyze**
    {\"action\":\"analyze\",\"data\":{\"type\":\"tasks/events/finance\",\"period\":\"week/month\"}}

13. **List Timetable** (school timetable)
    {\"action\":\"list_timetable\",\"data\":{\"day\":\"Monday\"}}   (omit day for today, or specify a day)

When you need to answer a question about the user's data, output the corresponding JSON command. For general questions, respond with text.

Current date: " . date('Y-m-d') . "
Current time: " . date('H:i:s') . "
User ID: {$user_id}

Respond with JSON only when performing an action; otherwise respond with plain text.";

// Call Gemini API
$apiKey = GEMINI_API_KEY;
$url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-flash-latest:generateContent?key={$apiKey}";

$postData = [
    'contents' => [
        [
            'parts' => [
                ['text' => $systemPrompt],
                ['text' => "User: {$message}"]
            ]
        ]
    ],
    'generationConfig' => [
        'temperature' => 0.2,
        'maxOutputTokens' => 1024,
    ]
];

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 200) {
    echo json_encode(['success' => false, 'error' => "Gemini API error: HTTP {$httpCode}"]);
    exit;
}

$result = json_decode($response, true);
$aiText = $result['candidates'][0]['content']['parts'][0]['text'] ?? '';

// Try to parse JSON from AI response
$aiText = trim($aiText);
$commands = null;
if (str_starts_with($aiText, '[')) {
    $commands = json_decode($aiText, true);
} elseif (str_starts_with($aiText, '{')) {
    $commands = json_decode($aiText, true);
    if (isset($commands['action'])) $commands = [$commands];
}

if (!$commands) {
    // Not JSON, treat as normal response
    echo json_encode(['success' => true, 'response' => $aiText]);
    exit;
}

// Execute commands
$responses = [];
foreach ($commands as $cmd) {
    $action = $cmd['action'] ?? '';
    $data = $cmd['data'] ?? [];
    $result = executeAction($pdo, $user_id, $action, $data);
    $responses[] = $result;
}

$finalResponse = implode("\n\n", array_filter($responses));
echo json_encode(['success' => true, 'response' => $finalResponse]);

// ---------------------------------------------------------------------
// Helper functions to execute each action
// ---------------------------------------------------------------------
function executeAction($pdo, $user_id, $action, $data) {
    switch ($action) {
        case 'create_task':
            $title = $data['title'] ?? '';
            $desc = $data['description'] ?? '';
            $due = !empty($data['due_date']) ? $data['due_date'] . ' 00:00:00' : null;
            $priority = $data['priority'] ?? 'medium';
            $cat_id = !empty($data['category_id']) ? (int)$data['category_id'] : null;
            $proj_id = !empty($data['project_id']) ? (int)$data['project_id'] : null;
            if (!$title) return '❌ Task title is required.';
            $stmt = $pdo->prepare("INSERT INTO tasks (user_id, title, description, due_date, priority, category_id, project_id) VALUES (?,?,?,?,?,?,?)");
            $stmt->execute([$user_id, $title, $desc, $due, $priority, $cat_id, $proj_id]);
            return "✅ Task \"{$title}\" created.";

        case 'list_tasks':
            $filter = $data['filter'] ?? 'pending';
            $limit = isset($data['limit']) ? (int)$data['limit'] : 5;
            $sql = "SELECT id, title, due_date, priority FROM tasks WHERE user_id = ?";
            $params = [$user_id];
            if ($filter === 'pending') $sql .= " AND status != 'completed'";
            elseif ($filter === 'completed') $sql .= " AND status = 'completed'";
            $sql .= " ORDER BY due_date ASC LIMIT ?";
            $params[] = $limit;
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $tasks = $stmt->fetchAll();
            if (empty($tasks)) return "No tasks found.";
            $list = "Here are your tasks:\n";
            foreach ($tasks as $t) {
                $due = $t['due_date'] ? date('M j', strtotime($t['due_date'])) : 'No date';
                $list .= "- {$t['title']} (Due: {$due}, Priority: {$t['priority']})\n";
            }
            return $list;

        case 'update_task':
            $id = $data['task_id'] ?? 0;
            if (!$id) return '❌ Task ID required.';
            $fields = [];
            $params = [];
            if (isset($data['status'])) { $fields[] = "status = ?"; $params[] = $data['status']; }
            if (isset($data['title'])) { $fields[] = "title = ?"; $params[] = $data['title']; }
            if (isset($data['description'])) { $fields[] = "description = ?"; $params[] = $data['description']; }
            if (isset($data['due_date'])) { $fields[] = "due_date = ?"; $params[] = $data['due_date'] . ' 00:00:00'; }
            if (isset($data['priority'])) { $fields[] = "priority = ?"; $params[] = $data['priority']; }
            if (empty($fields)) return '❌ No fields to update.';
            $params[] = $id;
            $params[] = $user_id;
            $sql = "UPDATE tasks SET " . implode(', ', $fields) . " WHERE id = ? AND user_id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            return "✅ Task updated.";

        case 'delete_task':
            $id = $data['task_id'] ?? 0;
            if (!$id) return '❌ Task ID required.';
            $stmt = $pdo->prepare("DELETE FROM tasks WHERE id = ? AND user_id = ?");
            $stmt->execute([$id, $user_id]);
            return "✅ Task deleted.";

        case 'create_event':
            $title = $data['title'] ?? '';
            $desc = $data['description'] ?? '';
            $loc = $data['location'] ?? '';
            $start = $data['start_datetime'] ?? '';
            $end = $data['end_datetime'] ?? '';
            $all_day = !empty($data['all_day']) ? 1 : 0;
            if (!$title || !$start || !$end) return '❌ Missing required fields (title, start, end).';
            $stmt = $pdo->prepare("INSERT INTO events (user_id, title, description, location, start_datetime, end_datetime, all_day) VALUES (?,?,?,?,?,?,?)");
            $stmt->execute([$user_id, $title, $desc, $loc, $start, $end, $all_day]);
            return "✅ Event \"{$title}\" created.";

        case 'list_events':
            $day = $data['day'] ?? null;
            $upcoming = $data['upcoming'] ?? true;
            $limit = isset($data['limit']) ? (int)$data['limit'] : 5;
            $sql = "SELECT id, title, start_datetime FROM events WHERE user_id = ?";
            $params = [$user_id];
            if ($day === 'today') {
                $sql .= " AND DATE(start_datetime) = CURDATE()";
                $upcoming = false; // override
            } elseif ($day && $day !== 'today') {
                $sql .= " AND DATE(start_datetime) = ?";
                $params[] = $day;
                $upcoming = false;
            }
            if ($upcoming) {
                $sql .= " AND start_datetime >= NOW()";
            }
            $sql .= " ORDER BY start_datetime ASC LIMIT ?";
            $params[] = $limit;
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $events = $stmt->fetchAll();
            if (empty($events)) return "No events found.";
            $list = "Here are your events:\n";
            foreach ($events as $e) {
                $list .= "- {$e['title']} at " . date('M j, g:i a', strtotime($e['start_datetime'])) . "\n";
            }
            return $list;

        case 'create_note':
            $title = $data['title'] ?? '';
            $content = $data['content'] ?? '';
            $cat_id = !empty($data['category_id']) ? (int)$data['category_id'] : null;
            if (!$title) return '❌ Note title required.';
            $stmt = $pdo->prepare("INSERT INTO notes (user_id, title, content, category_id) VALUES (?,?,?,?)");
            $stmt->execute([$user_id, $title, $content, $cat_id]);
            $note_id = $pdo->lastInsertId();
            if (!empty($data['tags'])) {
                $tags = array_map('trim', explode(',', $data['tags']));
                foreach ($tags as $tag) {
                    if (empty($tag)) continue;
                    $stmt = $pdo->prepare("SELECT id FROM tags WHERE user_id = ? AND name = ?");
                    $stmt->execute([$user_id, $tag]);
                    $t = $stmt->fetch();
                    if ($t) $tag_id = $t['id'];
                    else {
                        $stmt = $pdo->prepare("INSERT INTO tags (user_id, name) VALUES (?,?)");
                        $stmt->execute([$user_id, $tag]);
                        $tag_id = $pdo->lastInsertId();
                    }
                    $stmt = $pdo->prepare("INSERT INTO note_tags (note_id, tag_id) VALUES (?,?)");
                    $stmt->execute([$note_id, $tag_id]);
                }
            }
            return "✅ Note \"{$title}\" created.";

        case 'list_notes':
            $limit = isset($data['limit']) ? (int)$data['limit'] : 5;
            $stmt = $pdo->prepare("SELECT id, title, updated_at FROM notes WHERE user_id = ? ORDER BY updated_at DESC LIMIT ?");
            $stmt->execute([$user_id, $limit]);
            $notes = $stmt->fetchAll();
            if (empty($notes)) return "No notes found.";
            $list = "Recent notes:\n";
            foreach ($notes as $n) {
                $list .= "- {$n['title']} (updated " . date('M j', strtotime($n['updated_at'])) . ")\n";
            }
            return $list;

        case 'create_goal':
            $name = $data['name'] ?? '';
            if (!$name) return '❌ Goal name required.';
            $desc = $data['description'] ?? '';
            $target_value = !empty($data['target_value']) ? (float)$data['target_value'] : null;
            $current_value = !empty($data['current_value']) ? (float)$data['current_value'] : null;
            $unit = $data['unit'] ?? '';
            $target_date = $data['target_date'] ?? null;
            $status = $data['status'] ?? 'active';
            $stmt = $pdo->prepare("INSERT INTO goals (user_id, name, description, target_value, current_value, unit, target_date, status) VALUES (?,?,?,?,?,?,?,?)");
            $stmt->execute([$user_id, $name, $desc, $target_value, $current_value, $unit, $target_date, $status]);
            return "✅ Goal \"{$name}\" created.";

        case 'update_goal':
            $id = $data['goal_id'] ?? 0;
            if (!$id) return '❌ Goal ID required.';
            $fields = [];
            $params = [];
            if (isset($data['current_value'])) { $fields[] = "current_value = ?"; $params[] = $data['current_value']; }
            if (isset($data['status'])) { $fields[] = "status = ?"; $params[] = $data['status']; }
            if (empty($fields)) return '❌ No fields to update.';
            $params[] = $id;
            $params[] = $user_id;
            $sql = "UPDATE goals SET " . implode(', ', $fields) . " WHERE id = ? AND user_id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            return "✅ Goal updated.";

        case 'add_transaction':
            $account_id = $data['account_id'] ?? 0;
            $category_id = $data['category_id'] ?? null;
            $amount = $data['amount'] ?? 0;
            $type = $data['type'] ?? 'expense';
            $desc = $data['description'] ?? '';
            $date = $data['date'] ?? date('Y-m-d');
            if (!$account_id || !$amount) return '❌ Account and amount required.';
            $stmt = $pdo->prepare("INSERT INTO finance_transactions (user_id, account_id, category_id, amount, type, description, date) VALUES (?,?,?,?,?,?,?)");
            $stmt->execute([$user_id, $account_id, $category_id, $amount, $type, $desc, $date]);
            $change = ($type === 'income') ? $amount : -$amount;
            $stmt = $pdo->prepare("UPDATE finance_accounts SET current_balance = current_balance + ? WHERE id = ? AND user_id = ?");
            $stmt->execute([$change, $account_id, $user_id]);
            return "✅ {$type} of {$amount} added.";

        case 'analyze':
            $type = $data['type'] ?? 'tasks';
            if ($type === 'tasks') {
                $stmt = $pdo->prepare("SELECT status, COUNT(*) as count FROM tasks WHERE user_id = ? GROUP BY status");
                $stmt->execute([$user_id]);
                $stats = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
                $completed = $stats['completed'] ?? 0;
                $pending = $stats['pending'] ?? 0;
                $in_progress = $stats['in_progress'] ?? 0;
                return "📊 Task analysis: {$pending} pending, {$in_progress} in progress, {$completed} completed.";
            } elseif ($type === 'events') {
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM events WHERE user_id = ? AND start_datetime >= NOW()");
                $stmt->execute([$user_id]);
                $upcoming = $stmt->fetchColumn();
                return "📅 You have {$upcoming} upcoming events.";
            } elseif ($type === 'finance') {
                $stmt = $pdo->prepare("SELECT SUM(amount) as total, type FROM finance_transactions WHERE user_id = ? AND date >= DATE_SUB(NOW(), INTERVAL 1 MONTH) GROUP BY type");
                $stmt->execute([$user_id]);
                $totals = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
                $income = $totals['income'] ?? 0;
                $expense = $totals['expense'] ?? 0;
                return "💰 Last 30 days: Income: {$income}, Expenses: {$expense}, Net: " . ($income - $expense);
            }
            return "Analysis not available for that type.";

        case 'list_timetable':
            $day = $data['day'] ?? date('l'); // default today
            // Allow "today" alias
            if (strtolower($day) === 'today') {
                $day = date('l');
            }
            // Validate day name
            $validDays = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
            if (!in_array($day, $validDays)) {
                return "Invalid day. Please use a day name like Monday.";
            }
            $stmt = $pdo->prepare("
                SELECT t.*, c.name as course_name
                FROM school_timetable t
                LEFT JOIN school_courses c ON t.course_id = c.id
                WHERE t.user_id = ? AND t.day_of_week = ? AND t.is_active = 1
                ORDER BY t.start_time
            ");
            $stmt->execute([$user_id, $day]);
            $entries = $stmt->fetchAll();
            if (empty($entries)) {
                return "No timetable entries for {$day}.";
            }
            $list = "Your timetable for {$day}:\n";
            foreach ($entries as $e) {
                $list .= "- {$e['course_name']} from " . substr($e['start_time'],0,5) . " to " . substr($e['end_time'],0,5);
                if ($e['room']) $list .= " (Room: {$e['room']})";
                $list .= "\n";
            }
            return $list;

        default:
            return "❌ Unknown action: {$action}";
    }
}
?>