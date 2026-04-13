# AI Life Manager

A modern, responsive web application to manage daily tasks, calendar events, notes, finances, school assignments, personal goals, and AI-powered assistance – all in one place. Built with PHP, HTML/CSS, and a glassmorphism UI design.

## ✨ Features

- **Dashboard** – Central overview of your activities.
- **Task Management** – Create, edit, and track to‑do items.
- **Calendar** – Schedule events and deadlines.
- **Notes** – Keep and organize your notes.
- **Finance Tracker** – Monitor income, expenses, and budgets.
- **School Module** – Manage courses, grades, and assignments.
- **Goals** – Set and track personal or professional goals.
- **AI Assistant** – Chat with an AI to get help and insights.
- **Analytics** – Visualize your productivity and financial trends.
- **User Profile** – Update personal information and avatar.

## 🧩 Tech Stack

- **Backend**: PHP (session‑based authentication, no external framework)
- **Frontend**: HTML5, CSS3 (flexbox, grid, glassmorphism, responsive)
- **Icons**: SVG sprite system
- **Fonts**: Google Fonts – Inter
- **Styling**: Custom CSS with dark theme, backdrop‑filter blur, and adaptive layouts

## 📱 Responsive Design

- **Desktop (≥769px)**: Horizontal header + collapsible sidebar.
- **Mobile (≤768px)**: Top bar with menu toggle, bottom navigation bar (iOS‑style), and hidden sidebar.
- All components adapt seamlessly to different screen sizes.

## 🔐 User Authentication (Session‑Based)

The `header.php` starts a PHP session and retrieves user data from `$_SESSION`:

```php
$user_name  = $_SESSION['user_name']  ?? '';
$user_email = $_SESSION['user_email'] ?? '';
$user_avatar = $_SESSION['user_avatar'] ?? '';
```

> **Note**: The commented redirect (`// if (!isset($_SESSION['user_id'])) header(...)`) can be uncommented to enforce login on every page that includes this header.

## 📂 Project Structure (Inferred)

```
project-root/
├── header.php            # Global header, navigation, and session logic
├── dashboard.php
├── tasks.php
├── calendar.php
├── notes.php
├── finance.php
├── school.php
├── goals.php
├── ai_chat.php
├── analytics.php
├── profile.php
├── login.php
└── ... (other backend files for DB, helpers)
```

Each page sets `$pageTitle` before including `header.php` to customise the `<title>` tag.

## 🎨 Visual Highlights

- **Glassmorphism** – Semi‑transparent panels with backdrop‑blur.
- **Dark Theme** – Deep blacks, purples, and neon accents.
- **Gradient Avatars** – Fallback initials with a purple/blue gradient.
- **Sticky Header** – Desktop header stays on top while scrolling.
- **Bottom Navigation** – Mobile users get a floating tab bar with a prominent centre action button.

## 🚀 Getting Started

### Prerequisites

- PHP 7.4+ (with `session` extension enabled)
- A web server (Apache, Nginx, or PHP built‑in server)

### Installation

1. Clone the repository into your web root.
2. Make sure `header.php` is placed in the same directory as your page files.
3. Implement your authentication logic (e.g., `login.php` sets `$_SESSION['user_id']`, `user_name`, `user_email`, `user_avatar`).
4. (Optional) Uncomment the login redirect in `header.php` to protect all pages.
5. Start the PHP server:

```bash
php -S localhost:8000
```

6. Open `http://localhost:8000/dashboard.php` in your browser.

## 🧩 Customisation

- **Icons** – Add or replace SVG symbols inside the hidden `<div>` at the top of `header.php`.
- **Navigation** – Modify the `<aside class="sidebar">` and `.bottom-nav` to add/remove links.
- **Colours** – Change CSS variables or gradient stops (e.g., `#7c3aed`, `#4f46e5`).
- **Search Bar** – Currently a UI placeholder; connect it to a search endpoint.

## 📄 License

This project is open‑source and free to use for personal or educational purposes.

---

**Built with ❤️ to help you manage your life smarter, not harder.**
