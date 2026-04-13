<?php
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// --- Helper functions ---
function formatMoney($amount) {
    return '$' . number_format($amount, 2);
}

// --- Handle actions ---
$action = $_POST['action'] ?? $_GET['action'] ?? '';
$id = $_POST['id'] ?? $_GET['id'] ?? 0;

// Account actions
if ($action === 'add_account' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $type = $_POST['type'];
    $currency = $_POST['currency'];
    $initial_balance = (float)$_POST['initial_balance'];
    $institution = trim($_POST['institution']);
    $stmt = $pdo->prepare("INSERT INTO finance_accounts (user_id, name, type, currency, initial_balance, current_balance, institution) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$user_id, $name, $type, $currency, $initial_balance, $initial_balance, $institution]);
    header('Location: finance.php');
    exit;
}

if ($action === 'edit_account' && $_SERVER['REQUEST_METHOD'] === 'POST' && $id) {
    $name = trim($_POST['name']);
    $type = $_POST['type'];
    $currency = $_POST['currency'];
    $institution = trim($_POST['institution']);
    $stmt = $pdo->prepare("UPDATE finance_accounts SET name=?, type=?, currency=?, institution=? WHERE id=? AND user_id=?");
    $stmt->execute([$name, $type, $currency, $institution, $id, $user_id]);
    header('Location: finance.php');
    exit;
}

if ($action === 'delete_account' && $id) {
    $stmt = $pdo->prepare("DELETE FROM finance_accounts WHERE id=? AND user_id=?");
    $stmt->execute([$id, $user_id]);
    header('Location: finance.php');
    exit;
}

// Category actions
if ($action === 'add_category' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $type = $_POST['type'];
    $parent_id = !empty($_POST['parent_id']) ? (int)$_POST['parent_id'] : null;
    $stmt = $pdo->prepare("INSERT INTO finance_categories (user_id, name, type, parent_id) VALUES (?, ?, ?, ?)");
    $stmt->execute([$user_id, $name, $type, $parent_id]);
    header('Location: finance.php');
    exit;
}

if ($action === 'edit_category' && $_SERVER['REQUEST_METHOD'] === 'POST' && $id) {
    $name = trim($_POST['name']);
    $type = $_POST['type'];
    $parent_id = !empty($_POST['parent_id']) ? (int)$_POST['parent_id'] : null;
    $stmt = $pdo->prepare("UPDATE finance_categories SET name=?, type=?, parent_id=? WHERE id=? AND user_id=?");
    $stmt->execute([$name, $type, $parent_id, $id, $user_id]);
    header('Location: finance.php');
    exit;
}

if ($action === 'delete_category' && $id) {
    $stmt = $pdo->prepare("DELETE FROM finance_categories WHERE id=? AND user_id=?");
    $stmt->execute([$id, $user_id]);
    header('Location: finance.php');
    exit;
}

// Transaction actions
if ($action === 'add_transaction' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $account_id = (int)$_POST['account_id'];
    $category_id = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null;
    $amount = (float)$_POST['amount'];
    $type = $_POST['type'];
    $description = trim($_POST['description']);
    $date = $_POST['date'];
    $to_account_id = !empty($_POST['to_account_id']) ? (int)$_POST['to_account_id'] : null;

    $stmt = $pdo->prepare("INSERT INTO finance_transactions (user_id, account_id, category_id, amount, type, description, date, to_account_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$user_id, $account_id, $category_id, $amount, $type, $description, $date, $to_account_id]);

    // Update account balances
    if ($type === 'income') {
        $stmt = $pdo->prepare("UPDATE finance_accounts SET current_balance = current_balance + ? WHERE id = ?");
        $stmt->execute([$amount, $account_id]);
    } elseif ($type === 'expense') {
        $stmt = $pdo->prepare("UPDATE finance_accounts SET current_balance = current_balance - ? WHERE id = ?");
        $stmt->execute([$amount, $account_id]);
    } elseif ($type === 'transfer' && $to_account_id) {
        $stmt = $pdo->prepare("UPDATE finance_accounts SET current_balance = current_balance - ? WHERE id = ?");
        $stmt->execute([$amount, $account_id]);
        $stmt = $pdo->prepare("UPDATE finance_accounts SET current_balance = current_balance + ? WHERE id = ?");
        $stmt->execute([$amount, $to_account_id]);
    }
    header('Location: finance.php');
    exit;
}

if ($action === 'edit_transaction' && $_SERVER['REQUEST_METHOD'] === 'POST' && $id) {
    // For simplicity, we'll not implement full edit with balance adjustments; you can add later.
    // Better to delete and re-add, but for brevity we'll just update without re-calc.
    // In production, you'd need to revert old effect and apply new.
    // We'll skip edit for now to keep code manageable.
    header('Location: finance.php');
    exit;
}

if ($action === 'delete_transaction' && $id) {
    // First get transaction details to revert balance
    $stmt = $pdo->prepare("SELECT * FROM finance_transactions WHERE id = ? AND user_id = ?");
    $stmt->execute([$id, $user_id]);
    $txn = $stmt->fetch();
    if ($txn) {
        // Reverse balance effect
        if ($txn['type'] === 'income') {
            $stmt = $pdo->prepare("UPDATE finance_accounts SET current_balance = current_balance - ? WHERE id = ?");
            $stmt->execute([$txn['amount'], $txn['account_id']]);
        } elseif ($txn['type'] === 'expense') {
            $stmt = $pdo->prepare("UPDATE finance_accounts SET current_balance = current_balance + ? WHERE id = ?");
            $stmt->execute([$txn['amount'], $txn['account_id']]);
        } elseif ($txn['type'] === 'transfer' && $txn['to_account_id']) {
            $stmt = $pdo->prepare("UPDATE finance_accounts SET current_balance = current_balance + ? WHERE id = ?");
            $stmt->execute([$txn['amount'], $txn['account_id']]);
            $stmt = $pdo->prepare("UPDATE finance_accounts SET current_balance = current_balance - ? WHERE id = ?");
            $stmt->execute([$txn['amount'], $txn['to_account_id']]);
        }
        $stmt = $pdo->prepare("DELETE FROM finance_transactions WHERE id = ?");
        $stmt->execute([$id]);
    }
    header('Location: finance.php');
    exit;
}

// Budget actions
if ($action === 'add_budget' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $category_id = (int)$_POST['category_id'];
    $period = $_POST['period'];
    $amount = (float)$_POST['amount'];
    $start_date = $_POST['start_date'];
    $end_date = !empty($_POST['end_date']) ? $_POST['end_date'] : null;
    $stmt = $pdo->prepare("INSERT INTO finance_budgets (user_id, category_id, period, amount, start_date, end_date) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$user_id, $category_id, $period, $amount, $start_date, $end_date]);
    header('Location: finance.php');
    exit;
}

if ($action === 'delete_budget' && $id) {
    $stmt = $pdo->prepare("DELETE FROM finance_budgets WHERE id=? AND user_id=?");
    $stmt->execute([$id, $user_id]);
    header('Location: finance.php');
    exit;
}

// --- Fetch data for display ---

// Accounts
$stmt = $pdo->prepare("SELECT * FROM finance_accounts WHERE user_id = ? ORDER BY name");
$stmt->execute([$user_id]);
$accounts = $stmt->fetchAll();

// Total balance
$total_balance = array_sum(array_column($accounts, 'current_balance'));

// Categories (for transactions)
$stmt = $pdo->prepare("SELECT * FROM finance_categories WHERE user_id = ? ORDER BY type, name");
$stmt->execute([$user_id]);
$categories = $stmt->fetchAll();

// Current month income/expense
$current_month_start = date('Y-m-01');
$current_month_end = date('Y-m-t');
$stmt = $pdo->prepare("SELECT type, SUM(amount) as total FROM finance_transactions WHERE user_id = ? AND date BETWEEN ? AND ? GROUP BY type");
$stmt->execute([$user_id, $current_month_start, $current_month_end]);
$monthly_totals = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
$income = $monthly_totals['income'] ?? 0;
$expense = $monthly_totals['expense'] ?? 0;
$net = $income - $expense;

// Recent transactions (last 10)
$stmt = $pdo->prepare("
    SELECT t.*, a.name as account_name, c.name as category_name
    FROM finance_transactions t
    LEFT JOIN finance_accounts a ON t.account_id = a.id
    LEFT JOIN finance_categories c ON t.category_id = c.id
    WHERE t.user_id = ?
    ORDER BY t.date DESC, t.id DESC
    LIMIT 10
");
$stmt->execute([$user_id]);
$recent_transactions = $stmt->fetchAll();

// Budgets with spent amounts (for current month)
$stmt = $pdo->prepare("
    SELECT b.*, 
           (SELECT COALESCE(SUM(amount), 0) FROM finance_transactions 
            WHERE category_id = b.category_id AND type = 'expense' AND date BETWEEN ? AND ?) as spent
    FROM finance_budgets b
    WHERE b.user_id = ? AND b.period = 'monthly' AND b.start_date <= ? AND (b.end_date IS NULL OR b.end_date >= ?)
");
$stmt->execute([$user_id, $current_month_start, $current_month_end, $current_month_end, $current_month_end]);
$budgets = $stmt->fetchAll();

// For categories dropdown (parent-child not fully used, but ok)
$income_cats = array_filter($categories, fn($c) => $c['type'] === 'income');
$expense_cats = array_filter($categories, fn($c) => $c['type'] === 'expense');

$pageTitle = 'Finance';
include 'header.php';
?>

<style>
    /* Finance specific styles */
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
    .finance-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 2rem;
        flex-wrap: wrap;
        gap: 1rem;
    }
    .finance-header h1 {
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
    .stat-card .small {
        font-size: 0.8rem;
        color: #6b7280;
    }
    .positive {
        color: #10b981;
    }
    .negative {
        color: #ef4444;
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
    .budget-progress {
        background: #2a2a3a;
        border-radius: 20px;
        overflow: hidden;
        height: 6px;
        width: 100px;
    }
    .budget-fill {
        background: #7c3aed;
        height: 100%;
        width: 0%;
    }
    .budget-fill.warning {
        background: #f59e0b;
    }
    .budget-fill.danger {
        background: #ef4444;
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

<div class="finance-header">
    <h1>💰 Finance Manager</h1>
    <div>
        <button class="btn-primary" onclick="openModal('addTransactionModal')">+ Add Transaction</button>
        <button class="btn-secondary" onclick="openModal('addAccountModal')" style="margin-left: 0.5rem;">Manage Accounts</button>
        <button class="btn-secondary" onclick="openModal('addCategoryModal')" style="margin-left: 0.5rem;">Categories</button>
    </div>
</div>

<!-- Quick stats -->
<div class="stats-grid">
    <div class="glass-card stat-card">
        <h3>Total Balance</h3>
        <div class="value"><?= formatMoney($total_balance) ?></div>
    </div>
    <div class="glass-card stat-card">
        <h3>This Month</h3>
        <div class="value positive">+<?= formatMoney($income) ?></div>
        <div class="value negative">-<?= formatMoney($expense) ?></div>
        <div class="small">Net: <?= formatMoney($net) ?></div>
    </div>
    <div class="glass-card stat-card">
        <h3>Accounts</h3>
        <div class="value"><?= count($accounts) ?></div>
    </div>
</div>

<!-- Recent transactions -->
<div class="section">
    <div class="section-title">
        Recent Transactions
        <a href="#" onclick="openModal('addTransactionModal')" class="add-link">+ Add</a>
    </div>
    <div class="glass-card" style="padding: 0;">
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Description</th>
                        <th>Category</th>
                        <th>Account</th>
                        <th>Amount</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($recent_transactions) > 0): ?>
                        <?php foreach ($recent_transactions as $t): ?>
                            <tr>
                                <td><?= date('M j, Y', strtotime($t['date'])) ?></td>
                                <td><?= htmlspecialchars($t['description']) ?></td>
                                <td><?= htmlspecialchars($t['category_name'] ?? 'Uncategorized') ?></td>
                                <td><?= htmlspecialchars($t['account_name']) ?></td>
                                <td class="<?= $t['type'] === 'income' ? 'positive' : ($t['type'] === 'expense' ? 'negative' : '') ?>">
                                    <?= $t['type'] === 'income' ? '+' : ($t['type'] === 'expense' ? '-' : '') ?><?= formatMoney($t['amount']) ?>
                                </td>
                                <td>
                                    <a href="?action=delete_transaction&id=<?= $t['id'] ?>" onclick="return confirm('Delete this transaction?')" style="color:#ef4444;">🗑️</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="6" style="text-align:center;">No transactions yet.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Budgets (if any) -->
<?php if (count($budgets) > 0): ?>
<div class="section">
    <div class="section-title">
        Monthly Budgets
        <a href="#" onclick="openModal('addBudgetModal')" class="add-link">+ Add Budget</a>
    </div>
    <div class="glass-card" style="padding: 0;">
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Category</th>
                        <th>Budget</th>
                        <th>Spent</th>
                        <th>Remaining</th>
                        <th>Progress</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($budgets as $b):
                        $category = array_filter($categories, fn($c) => $c['id'] == $b['category_id']);
                        $category_name = !empty($category) ? current($category)['name'] : 'Unknown';
                        $spent = $b['spent'];
                        $remaining = $b['amount'] - $spent;
                        $percent = ($spent / $b['amount']) * 100;
                        $fill_class = '';
                        if ($percent > 90) $fill_class = 'danger';
                        elseif ($percent > 70) $fill_class = 'warning';
                    ?>
                    <tr>
                        <td><?= htmlspecialchars($category_name) ?></td>
                        <td><?= formatMoney($b['amount']) ?></td>
                        <td class="negative"><?= formatMoney($spent) ?></td>
                        <td class="<?= $remaining >= 0 ? 'positive' : 'negative' ?>"><?= formatMoney($remaining) ?></td>
                        <td>
                            <div class="budget-progress">
                                <div class="budget-fill <?= $fill_class ?>" style="width: <?= min(100, $percent) ?>%"></div>
                            </div>
                        </td>
                        <td>
                            <a href="?action=delete_budget&id=<?= $b['id'] ?>" onclick="return confirm('Delete this budget?')" style="color:#ef4444;">🗑️</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php else: ?>
<div class="glass-card" style="margin-bottom: 1.5rem; text-align: center; padding: 1rem;">
    <p>No budgets set. <a href="#" onclick="openModal('addBudgetModal')" class="add-link">Create one</a> to track spending.</p>
</div>
<?php endif; ?>

<!-- Account list (quick view) -->
<div class="section">
    <div class="section-title">
        Accounts
        <button class="btn-secondary" onclick="openModal('addAccountModal')">+ Add Account</button>
    </div>
    <div class="glass-card" style="padding: 0;">
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Type</th>
                        <th>Institution</th>
                        <th>Balance</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($accounts as $acc): ?>
                    <tr>
                        <td><?= htmlspecialchars($acc['name']) ?></td>
                        <td><?= ucfirst($acc['type']) ?></td>
                        <td><?= htmlspecialchars($acc['institution'] ?: '-') ?></td>
                        <td><?= formatMoney($acc['current_balance']) ?></td>
                        <td>
                            <a href="?action=delete_account&id=<?= $acc['id'] ?>" onclick="return confirm('Delete this account? This will also delete all its transactions!')" style="color:#ef4444;">🗑️</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- MODALS -->

<!-- Add Transaction Modal -->
<div id="addTransactionModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Add Transaction</h2>
            <span class="close" onclick="closeModal('addTransactionModal')">&times;</span>
        </div>
        <form method="post" action="finance.php">
            <input type="hidden" name="action" value="add_transaction">
            <div class="form-group">
                <label for="txn_date">Date</label>
                <input type="date" id="txn_date" name="date" value="<?= date('Y-m-d') ?>" required>
            </div>
            <div class="form-group">
                <label for="txn_type">Type</label>
                <select id="txn_type" name="type" onchange="toggleTransferFields()">
                    <option value="expense">Expense</option>
                    <option value="income">Income</option>
                    <option value="transfer">Transfer</option>
                </select>
            </div>
            <div class="form-group">
                <label for="txn_account">Account</label>
                <select id="txn_account" name="account_id" required>
                    <option value="">Select account</option>
                    <?php foreach ($accounts as $acc): ?>
                        <option value="<?= $acc['id'] ?>"><?= htmlspecialchars($acc['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group" id="transfer_to_group" style="display: none;">
                <label for="txn_to_account">Transfer to Account</label>
                <select id="txn_to_account" name="to_account_id">
                    <option value="">Select account</option>
                    <?php foreach ($accounts as $acc): ?>
                        <option value="<?= $acc['id'] ?>"><?= htmlspecialchars($acc['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="txn_category">Category</label>
                <select id="txn_category" name="category_id">
                    <option value="">Select category</option>
                    <optgroup label="Expense">
                        <?php foreach ($expense_cats as $cat): ?>
                            <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                        <?php endforeach; ?>
                    </optgroup>
                    <optgroup label="Income">
                        <?php foreach ($income_cats as $cat): ?>
                            <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                        <?php endforeach; ?>
                    </optgroup>
                </select>
                <a href="#" class="add-link" onclick="openModal('addCategoryModal'); return false;">+ Create new category</a>
            </div>
            <div class="form-group">
                <label for="txn_amount">Amount</label>
                <input type="number" step="0.01" id="txn_amount" name="amount" required>
            </div>
            <div class="form-group">
                <label for="txn_description">Description</label>
                <input type="text" id="txn_description" name="description">
            </div>
            <div class="modal-actions">
                <button type="button" class="btn-secondary" onclick="closeModal('addTransactionModal')">Cancel</button>
                <button type="submit" class="btn-primary">Add Transaction</button>
            </div>
        </form>
    </div>
</div>

<!-- Add Account Modal -->
<div id="addAccountModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Add Account</h2>
            <span class="close" onclick="closeModal('addAccountModal')">&times;</span>
        </div>
        <form method="post" action="finance.php">
            <input type="hidden" name="action" value="add_account">
            <div class="form-group">
                <label for="acc_name">Account Name</label>
                <input type="text" id="acc_name" name="name" required>
            </div>
            <div class="form-group">
                <label for="acc_type">Type</label>
                <select id="acc_type" name="type">
                    <option value="checking">Checking</option>
                    <option value="savings">Savings</option>
                    <option value="credit">Credit Card</option>
                    <option value="cash">Cash</option>
                    <option value="investment">Investment</option>
                    <option value="other">Other</option>
                </select>
            </div>
            <div class="form-group">
                <label for="acc_currency">Currency</label>
                <input type="text" id="acc_currency" name="currency" value="USD" maxlength="3">
            </div>
            <div class="form-group">
                <label for="acc_initial">Initial Balance</label>
                <input type="number" step="0.01" id="acc_initial" name="initial_balance" value="0.00">
            </div>
            <div class="form-group">
                <label for="acc_institution">Institution (optional)</label>
                <input type="text" id="acc_institution" name="institution">
            </div>
            <div class="modal-actions">
                <button type="button" class="btn-secondary" onclick="closeModal('addAccountModal')">Cancel</button>
                <button type="submit" class="btn-primary">Create Account</button>
            </div>
        </form>
    </div>
</div>

<!-- Add Category Modal -->
<div id="addCategoryModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Add Category</h2>
            <span class="close" onclick="closeModal('addCategoryModal')">&times;</span>
        </div>
        <form method="post" action="finance.php">
            <input type="hidden" name="action" value="add_category">
            <div class="form-group">
                <label for="cat_name">Category Name</label>
                <input type="text" id="cat_name" name="name" required>
            </div>
            <div class="form-group">
                <label for="cat_type">Type</label>
                <select id="cat_type" name="type">
                    <option value="expense">Expense</option>
                    <option value="income">Income</option>
                </select>
            </div>
            <div class="form-group">
                <label for="cat_parent">Parent Category (optional)</label>
                <select id="cat_parent" name="parent_id">
                    <option value="">None</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn-secondary" onclick="closeModal('addCategoryModal')">Cancel</button>
                <button type="submit" class="btn-primary">Create Category</button>
            </div>
        </form>
    </div>
</div>

<!-- Add Budget Modal -->
<div id="addBudgetModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Add Monthly Budget</h2>
            <span class="close" onclick="closeModal('addBudgetModal')">&times;</span>
        </div>
        <form method="post" action="finance.php">
            <input type="hidden" name="action" value="add_budget">
            <div class="form-group">
                <label for="budget_category">Category</label>
                <select id="budget_category" name="category_id" required>
                    <option value="">Select category</option>
                    <?php foreach ($expense_cats as $cat): ?>
                        <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="budget_amount">Amount</label>
                <input type="number" step="0.01" id="budget_amount" name="amount" required>
            </div>
            <div class="form-group">
                <label for="budget_start">Start Month (YYYY-MM-DD)</label>
                <input type="date" id="budget_start" name="start_date" value="<?= date('Y-m-01') ?>" required>
            </div>
            <div class="form-group">
                <label for="budget_end">End Month (optional, leave blank for ongoing)</label>
                <input type="date" id="budget_end" name="end_date">
            </div>
            <div class="modal-actions">
                <button type="button" class="btn-secondary" onclick="closeModal('addBudgetModal')">Cancel</button>
                <button type="submit" class="btn-primary">Set Budget</button>
            </div>
        </form>
    </div>
</div>

<script>
    function openModal(id) {
        document.getElementById(id).classList.add('active');
    }
    function closeModal(id) {
        document.getElementById(id).classList.remove('active');
    }

    function toggleTransferFields() {
        const type = document.getElementById('txn_type').value;
        const transferGroup = document.getElementById('transfer_to_group');
        const categorySelect = document.getElementById('txn_category');
        if (type === 'transfer') {
            transferGroup.style.display = 'block';
            categorySelect.disabled = true;
            categorySelect.value = '';
        } else {
            transferGroup.style.display = 'none';
            categorySelect.disabled = false;
        }
    }

    window.onclick = function(event) {
        if (event.target.classList.contains('modal')) {
            event.target.classList.remove('active');
        }
    }
</script>

<?php include 'footer.php'; ?>