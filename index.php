<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Life Manager – Your Intelligent Companion</title>
    <!-- Google Fonts -->
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
            background-image: radial-gradient(circle at 15% 30%, #1a1a1a 0%, #000000 90%);
            color: #e0e0e0;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* Simple header with logo and auth buttons */
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
            letter-spacing: -0.5px;
        }

        .auth-buttons {
            display: flex;
            gap: 1rem;
        }

        .btn {
            padding: 0.6rem 1.5rem;
            border-radius: 40px;
            font-weight: 600;
            font-size: 0.95rem;
            transition: all 0.2s ease;
            cursor: pointer;
            border: none;
            text-decoration: none;
            display: inline-block;
        }

        .btn-outline {
            background: transparent;
            color: #e0e0e0;
            border: 1.5px solid #333;
        }

        .btn-outline:hover {
            border-color: #a78bfa;
            color: #a78bfa;
            transform: translateY(-2px);
        }

        .btn-primary {
            background: linear-gradient(145deg, #7c3aed, #4f46e5);
            color: white;
            box-shadow: 0 8px 20px rgba(124, 58, 237, 0.3);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 28px rgba(124, 58, 237, 0.5);
        }

        /* Main hero section (single section) */
        .hero {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem 5%;
            max-width: 1400px;
            margin: 0 auto;
            width: 100%;
        }

        .hero-container {
            display: flex;
            flex-direction: row;
            align-items: center;
            gap: 4rem;
            width: 100%;
        }

        /* Left column – text and CTA */
        .hero-content {
            flex: 1;
        }

        .hero-title {
            font-size: clamp(2.5rem, 5vw, 4rem);
            font-weight: 800;
            line-height: 1.2;
            margin-bottom: 1.5rem;
            background: linear-gradient(to right, #ffffff, #c0c0ff);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .hero-description {
            font-size: 1.2rem;
            color: #b0b0b0;
            margin-bottom: 2.5rem;
            max-width: 550px;
            line-height: 1.6;
        }

        .hero-cta {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .cta-primary {
            background: linear-gradient(145deg, #7c3aed, #4f46e5);
            color: white;
            padding: 0.9rem 2.5rem;
            border-radius: 50px;
            font-weight: 600;
            font-size: 1.1rem;
            border: none;
            cursor: pointer;
            transition: all 0.2s;
            box-shadow: 0 8px 20px rgba(124, 58, 237, 0.4);
            text-decoration: none;
        }

        .cta-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 30px rgba(124, 58, 237, 0.6);
        }

        .cta-secondary {
            background: transparent;
            color: #e0e0e0;
            padding: 0.9rem 2.5rem;
            border-radius: 50px;
            font-weight: 600;
            font-size: 1.1rem;
            border: 1.5px solid #333;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
        }

        .cta-secondary:hover {
            border-color: #a78bfa;
            color: #a78bfa;
            transform: translateY(-3px);
        }

        /* Right column – SVG illustration */
        .hero-image {
            flex: 1;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        svg {
            width: 100%;
            max-width: 500px;
            height: auto;
            filter: drop-shadow(0 20px 30px rgba(0, 0, 0, 0.8));
        }

        /* Responsive adjustments */
        @media (max-width: 900px) {
            .hero-container {
                flex-direction: column;
                text-align: center;
            }

            .hero-description {
                margin-left: auto;
                margin-right: auto;
            }

            .hero-cta {
                justify-content: center;
            }

            .navbar {
                padding: 1rem 5%;
            }
        }

        @media (max-width: 480px) {
            .auth-buttons .btn {
                padding: 0.5rem 1rem;
                font-size: 0.85rem;
            }

            .logo {
                font-size: 1.4rem;
            }

            .hero-title {
                font-size: 2.2rem;
            }
        }

        /* Optional subtle footer (not part of main section but keeps body full height) */
        .footer {
            text-align: center;
            padding: 1.5rem;
            color: #505050;
            font-size: 0.9rem;
        }
    </style>
</head>
<body>
    <!-- Simple navigation with logo and login/register -->
    <nav class="navbar">
        <div class="logo">AI Life Manager</div>
        <div class="auth-buttons">
            <a href="login.php" class="btn btn-outline">Log in</a>
            <a href="register.php" class="btn btn-primary">Sign up</a>
        </div>
    </nav>

    <!-- Main hero section – discussing AI -->
    <main class="hero">
        <div class="hero-container">
            <div class="hero-content">
                <h1 class="hero-title">
                    Your AI companion <br>for a balanced life
                </h1>
                <p class="hero-description">
                    AI Life Manager intelligently organizes your tasks, finances, studies, and notes — adapting to your unique routine as a student, teacher, entrepreneur, or creator.
                </p>
                <div class="hero-cta">
                    <a href="register.php" class="cta-primary">Get Started – It's Free</a>
                    <a href="#learn-more" class="cta-secondary">Learn More</a>
                </div>
            </div>
            <div class="hero-image">
                <!-- Custom SVG illustration representing AI / life management -->
                <svg viewBox="0 0 500 400" xmlns="http://www.w3.org/2000/svg">
                    <!-- Background abstract shapes -->
                    <circle cx="250" cy="200" r="150" fill="url(#grad1)" opacity="0.15" />
                    <circle cx="300" cy="150" r="80" fill="url(#grad2)" opacity="0.2" />
                    
                    <!-- Central glowing brain / AI symbol -->
                    <path d="M250 150 Q 280 120 310 150 Q 340 180 310 210 Q 280 240 250 210 Q 220 180 190 210 Q 160 180 190 150 Q 220 120 250 150" fill="url(#gradBrain)" stroke="#a78bfa" stroke-width="2" />
                    <circle cx="220" cy="160" r="8" fill="#4f46e5" opacity="0.8" />
                    <circle cx="280" cy="160" r="8" fill="#7c3aed" opacity="0.8" />
                    
                    <!-- Orbiting dots representing tasks/events -->
                    <g opacity="0.7">
                        <circle cx="250" cy="100" r="6" fill="#60a5fa">
                            <animate attributeName="r" values="6;10;6" dur="3s" repeatCount="indefinite" />
                        </circle>
                        <circle cx="370" cy="200" r="5" fill="#a78bfa">
                            <animate attributeName="r" values="5;9;5" dur="2.5s" repeatCount="indefinite" />
                        </circle>
                        <circle cx="130" cy="200" r="7" fill="#f472b6">
                            <animate attributeName="r" values="7;11;7" dur="3.2s" repeatCount="indefinite" />
                        </circle>
                        <circle cx="250" cy="300" r="5" fill="#4f46e5">
                            <animate attributeName="r" values="5;9;5" dur="2.8s" repeatCount="indefinite" />
                        </circle>
                    </g>

                    <!-- Abstract lines suggesting connectivity -->
                    <path d="M250 150 L 310 210 M250 150 L 190 210 M310 150 L 250 210 M190 150 L 250 210" stroke="#333" stroke-width="1.5" stroke-dasharray="4 4" />
                    
                    <!-- Floating document/note shapes -->
                    <rect x="380" y="270" width="40" height="50" rx="6" fill="#2d2d2d" stroke="#a78bfa" stroke-width="1.5" />
                    <line x1="390" y1="280" x2="410" y2="280" stroke="#a78bfa" stroke-width="2" />
                    <line x1="390" y1="290" x2="410" y2="290" stroke="#a78bfa" stroke-width="2" />
                    <line x1="390" y1="300" x2="405" y2="300" stroke="#a78bfa" stroke-width="2" />
                    
                    <rect x="80" y="260" width="35" height="45" rx="5" fill="#2d2d2d" stroke="#4f46e5" stroke-width="1.5" />
                    <line x1="90" y1="275" x2="105" y2="275" stroke="#4f46e5" stroke-width="2" />
                    <line x1="90" y1="285" x2="105" y2="285" stroke="#4f46e5" stroke-width="2" />
                    
                    <!-- Gradients -->
                    <defs>
                        <linearGradient id="grad1" x1="0%" y1="0%" x2="100%" y2="100%">
                            <stop offset="0%" stop-color="#7c3aed" stop-opacity="0.3" />
                            <stop offset="100%" stop-color="#4f46e5" stop-opacity="0.1" />
                        </linearGradient>
                        <linearGradient id="grad2" x1="0%" y1="0%" x2="100%" y2="100%">
                            <stop offset="0%" stop-color="#60a5fa" stop-opacity="0.2" />
                            <stop offset="100%" stop-color="#a78bfa" stop-opacity="0.1" />
                        </linearGradient>
                        <linearGradient id="gradBrain" x1="0%" y1="0%" x2="100%" y2="100%">
                            <stop offset="0%" stop-color="#4f46e5" stop-opacity="0.9" />
                            <stop offset="100%" stop-color="#7c3aed" stop-opacity="0.7" />
                        </linearGradient>
                    </defs>
                </svg>
            </div>
        </div>
    </main>

    <!-- Optional small footer (outside the main section) -->
    <footer class="footer">
        © 2025 AI Life Manager. All rights reserved.
    </footer>
</body>
</html>