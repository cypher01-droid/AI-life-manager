        </main>
    </div> <!-- close dashboard-container -->

    <!-- Bottom Navigation (mobile) with 5 tabs -->
    <nav class="bottom-nav">
        <a href="dashboard.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>">
            <svg><use xlink:href="#icon-home"></use></svg>
            <span>Home</span>
        </a>
        <a href="profile.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'profile.php' ? 'active' : ''; ?>">
            <svg><use xlink:href="#icon-profile"></use></svg>
            <span>Profile</span>
        </a>
        <!-- Center AI Discuss Button -->
        <a href="ai_chat.php" class="center-btn">
            <svg><use xlink:href="#icon-ai"></use></svg>
        </a>
        <a href="menu.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'menu.php' ? 'active' : ''; ?>">
            <svg><use xlink:href="#icon-menu"></use></svg>
            <span>Menu</span>
        </a>
        <a href="analytics.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'analytics.php' ? 'active' : ''; ?>">
            <svg><use xlink:href="#icon-analytics"></use></svg>
            <span>Analytics</span>
        </a>
    </nav>

    <div style="height: env(safe-area-inset-bottom);"></div>

    <script>
        // Simple toggle for mobile menu (you can expand later)
        document.getElementById('menuToggle')?.addEventListener('click', function() {
            // You could toggle a sidebar drawer here
            alert('Mobile menu clicked – implement your drawer');
        });
    </script>
</body>
</html>