<?php
session_start();
require_once 'config/database.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Collect and sanitize inputs
    $username = trim($_POST['username'] ?? '');
    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $role = $_POST['role'] ?? '';

    // Basic validation
    if (empty($username) || empty($full_name) || empty($email) || empty($password) || empty($confirm_password)) {
        $error = 'All fields are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters.';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match.';
    } else {
        // Check if username or email already exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$username, $email]);
        if ($stmt->fetch()) {
            $error = 'Username or email already exists.';
        } else {
            // Hash password and insert new user
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            $preferences = json_encode(['role' => $role, 'onboarding_completed' => true]);
            
            $stmt = $pdo->prepare("INSERT INTO users (username, full_name, email, password_hash, preferences) VALUES (?, ?, ?, ?, ?)");
            
            if ($stmt->execute([$username, $full_name, $email, $password_hash, $preferences])) {
                $user_id = $pdo->lastInsertId();
                $_SESSION['user_id'] = $user_id;
                $_SESSION['user_name'] = $full_name;
                $_SESSION['user_email'] = $email;
                header('Location: dashboard.php');
                exit;
            } else {
                $error = 'Registration failed. Please try again.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Get Started – AI Life Manager</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:ital,opsz,wght@0,14..32,100..900;1,14..32,100..900&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: #0a0a0a;
            background-image: radial-gradient(circle at 10% 20%, #1a1a1a, #000000);
            color: #e0e0e0;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        .navbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1.5rem 5%;
            max-width: 1400px;
            margin: 0 auto;
            width: 100%;
        }

        .logo {
            font-size: 1.8rem;
            font-weight: 700;
            background: linear-gradient(135deg, #a78bfa, #60a5fa);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .back-link {
            color: #b0b0b0;
            text-decoration: none;
            font-weight: 500;
            transition: color 0.2s;
        }

        .back-link:hover {
            color: #a78bfa;
        }

        .main-content {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem 5%;
        }

        .register-card {
            background: rgba(20, 20, 30, 0.7);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid #2a2a3a;
            border-radius: 32px;
            padding: 2.5rem;
            width: 100%;
            max-width: 600px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.8);
        }

        .register-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .register-header h1 {
            font-size: 2.2rem;
            font-weight: 700;
            background: linear-gradient(to right, #ffffff, #c0c0ff);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 0.5rem;
        }

        .register-header p {
            color: #b0b0b0;
            font-size: 1rem;
        }

        .step-indicator {
            display: flex;
            justify-content: center;
            gap: 2rem;
            margin-bottom: 2rem;
        }

        .step {
            display: flex;
            flex-direction: column;
            align-items: center;
            color: #6b7280;
            font-size: 0.85rem;
        }

        .step.active {
            color: #a78bfa;
        }

        .step .circle {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: #2a2a3a;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 0.3rem;
            font-weight: 600;
            color: #e0e0e0;
            border: 2px solid transparent;
        }

        .step.active .circle {
            background: #4f46e5;
            color: white;
            border-color: #a78bfa;
            box-shadow: 0 0 15px #4f46e5;
        }

        .step.completed .circle {
            background: #10b981;
            color: white;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: #d1d5db;
            font-size: 0.95rem;
        }

        input, select {
            width: 100%;
            padding: 0.9rem 1.2rem;
            background: #1e1e2e;
            border: 1.5px solid #2a2a3a;
            border-radius: 16px;
            font-size: 1rem;
            color: #f0f0f0;
            transition: all 0.2s;
            font-family: 'Inter', sans-serif;
        }

        input:focus, select:focus {
            outline: none;
            border-color: #7c3aed;
            box-shadow: 0 0 0 3px rgba(124, 58, 237, 0.2);
        }

        select {
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%23a78bfa'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M19 9l-7 7-7-7'%3E%3C/path%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 1rem center;
            background-size: 1.2rem;
        }

        .error-message {
            background: rgba(239, 68, 68, 0.2);
            border-left: 4px solid #ef4444;
            padding: 0.8rem 1.2rem;
            border-radius: 12px;
            margin-bottom: 1.5rem;
            color: #fecaca;
            font-size: 0.95rem;
        }

        .register-btn {
            width: 100%;
            background: linear-gradient(145deg, #7c3aed, #4f46e5);
            color: white;
            border: none;
            padding: 1rem;
            border-radius: 40px;
            font-weight: 700;
            font-size: 1.1rem;
            cursor: pointer;
            transition: all 0.2s;
            box-shadow: 0 10px 25px -5px rgba(124, 58, 237, 0.4);
            margin-top: 1rem;
        }

        .register-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 15px 30px -5px rgba(124, 58, 237, 0.6);
        }

        .login-prompt {
            text-align: center;
            margin-top: 1.8rem;
            color: #9ca3af;
        }

        .login-prompt a {
            color: #a78bfa;
            text-decoration: none;
            font-weight: 600;
            transition: color 0.2s;
        }

        .login-prompt a:hover {
            color: #c4b5fd;
        }

        @media (max-width: 480px) {
            .register-card {
                padding: 1.8rem;
            }
            .step-indicator {
                gap: 1rem;
            }
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="logo">AI Life Manager</div>
        <a href="index.php" class="back-link">← Back to Home</a>
    </nav>

    <main class="main-content">
        <div class="register-card">
            <div class="register-header">
                <h1>Join the AI Revolution</h1>
                <p>Set up your intelligent life companion</p>
            </div>

            <div class="step-indicator">
                <div class="step active">
                    <span class="circle">1</span>
                    <span>Account</span>
                </div>
                <div class="step">
                    <span class="circle">2</span>
                    <span>Personalize</span>
                </div>
            </div>

            <?php if ($error): ?>
                <div class="error-message"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="post" action="">
                <!-- Username field (required) -->
                <div class="form-group">
                    <label for="username">Username *</label>
                    <input type="text" id="username" name="username" placeholder="e.g., alexjohnson" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" required>
                </div>

                <div class="form-group">
                    <label for="full_name">Full name</label>
                    <input type="text" id="full_name" name="full_name" placeholder="e.g., Alex Johnson" value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>" required>
                </div>

                <div class="form-group">
                    <label for="email">Email address</label>
                    <input type="email" id="email" name="email" placeholder="alex@example.com" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
                </div>

                <div class="form-group">
                    <label for="password">Password (min. 8 characters)</label>
                    <input type="password" id="password" name="password" placeholder="••••••••" required>
                </div>

                <div class="form-group">
                    <label for="confirm_password">Confirm password</label>
                    <input type="password" id="confirm_password" name="confirm_password" placeholder="••••••••" required>
                </div>

                <div class="form-group">
                    <label for="role">I am a...</label>
                    <select id="role" name="role" required>
                        <option value="" disabled <?= empty($_POST['role']) ? 'selected' : '' ?>>Select your primary role</option>
                        <option value="student" <?= ($_POST['role'] ?? '') === 'student' ? 'selected' : '' ?>>Student</option>
                        <option value="teacher" <?= ($_POST['role'] ?? '') === 'teacher' ? 'selected' : '' ?>>Teacher / Educator</option>
                        <option value="entrepreneur" <?= ($_POST['role'] ?? '') === 'entrepreneur' ? 'selected' : '' ?>>Entrepreneur</option>
                        <option value="webdev" <?= ($_POST['role'] ?? '') === 'webdev' ? 'selected' : '' ?>>Web Developer</option>
                        <option value="other" <?= ($_POST['role'] ?? '') === 'other' ? 'selected' : '' ?>>Other</option>
                    </select>
                </div>

                <button type="submit" class="register-btn">Create Account & Start Managing</button>
            </form>

            <div class="login-prompt">
                Already have an account? <a href="login.php">Log in</a>
            </div>
        </div>
    </main>
</body>
</html>