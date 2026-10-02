<!DOCTYPE html>
<html lang="en">
<head>
    <!-- BuildNexus Favicon & Brand Icons -->
    <link rel="icon" type="image/png" sizes="32x32" href="/buildnexus/images/logo.png?v=2">
    <link rel="icon" type="image/png" sizes="16x16" href="/buildnexus/images/logo.png?v=2">
    <link rel="shortcut icon" href="/buildnexus/images/logo.png?v=2">
    <link rel="apple-touch-icon" href="/buildnexus/images/logo.png?v=2">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Drawings & PDF Markup - BuildNexus</title>
    <style>
        /* GLOBAL STYLES */
        :root {
            --nexus-green: #22c55e;
            --nexus-orange: #f97316;
            --bg-light: #fafafa;
            --text-dark: #111827;
            --text-gray: #4b5563;
            --border-color: #e5e7eb;
            --font-main: 'Inter', system-ui, -apple-system, sans-serif;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: var(--font-main);
            background-color: #ffffff;
            color: var(--text-dark);
            line-height: 1.6;
        }

        /* NAVIGATION */
        .navbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem 5rem;
            border-bottom: 1px solid var(--border-color);
            position: sticky;
            top: 0;
            background: #fff;
            z-index: 100;
        }

        .logo { font-weight: 700; font-size: 1.25rem; display: flex; align-items: center; gap: 8px; text-decoration: none; color: inherit; }
        .logo-box { width: 20px; height: 20px; background: var(--nexus-orange); border-radius: 4px; }
        
        .nav-links { display: flex; gap: 2rem; list-style: none; }
        .nav-links a { text-decoration: none; color: var(--text-gray); font-size: 0.9rem; font-weight: 500; }

        .nav-actions { display: flex; align-items: center; gap: 1.5rem; }
        .btn-green { background: var(--nexus-green); color: white; padding: 0.5rem 1.2rem; border-radius: 4px; text-decoration: none; font-weight: 600; }

        /* LAYOUT CONTAINER */
        .main-container { display: flex; max-width: 1440px; margin: 0 auto; }

        /* SIDEBAR */
        .sidebar {
            width: 260px;
            background: var(--bg-light);
            border-right: 1px solid var(--border-color);
            padding: 2rem 0;
            height: calc(100vh - 70px);
            position: sticky;
            top: 70px;
        }

        .sidebar-menu { list-style: none; }
        .sidebar-item a {
            display: block;
            padding: 0.75rem 2rem;
            color: var(--text-gray);
            text-decoration: none;
            font-size: 0.85rem;
            transition: all 0.2s;
        }

        .sidebar-item a:hover { background: #f3f4f6; color: var(--text-dark); }
        .sidebar-item.active a {
            background: #f3f4f6;
            color: var(--text-dark);
            font-weight: 600;
            border-right: 3px solid var(--nexus-green);
        }

        /* CONTENT AREA */
        .content { flex: 1; padding: 4rem; }

        .hero { display: grid; grid-template-columns: 1fr 1fr; gap: 4rem; align-items: center; margin-bottom: 5rem; }
        .hero-text span { color: var(--nexus-green); font-weight: 600; font-size: 0.9rem; }
        .hero-text h1 { font-size: 2.5rem; line-height: 1.2; margin: 1rem 0; }
        .hero-text p { color: var(--text-gray); font-size: 1rem; }

        .hero-img { border-radius: 12px; overflow: hidden; box-shadow: 0 10px 30px rgba(0,0,0,0.1); }
        .hero-img img { width: 100%; display: block; }

        /* FEATURES GRID */
        .features-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 2rem;
            margin-bottom: 5rem;
        }

        .feature-card {
            background: var(--bg-light);
            padding: 2rem;
            border-radius: 8px;
        }
        
        .feature-header { display: flex; align-items: center; gap: 10px; margin-bottom: 1rem; }
        .feature-header span { color: var(--nexus-green); font-weight: 600; font-size: 0.8rem; }

        .feature-card h3 { font-size: 1.1rem; margin-bottom: 0.5rem; color: var(--text-dark); }
        .feature-card p { font-size: 0.85rem; color: var(--text-gray); line-height: 1.6; }

        /* FOOTER */
        .footer { background: var(--bg-light); padding: 4rem 5rem 2rem; border-top: 1px solid var(--border-color); }
        .footer-grid { display: grid; grid-template-columns: 2fr 1fr 1fr 1fr; gap: 4rem; margin-bottom: 3rem; }
        .footer-col h4 { font-size: 0.9rem; margin-bottom: 1.5rem; }
        .footer-col ul { list-style: none; }
        .footer-col li { margin-bottom: 0.75rem; font-size: 0.8rem; color: var(--text-gray); }
        .footer-bottom { border-top: 1px solid var(--border-color); padding-top: 2rem; text-align: left; font-size: 0.75rem; color: #9ca3af; }

        /* Mobile Responsive */
        @media (max-width: 1024px) {
            .hero { grid-template-columns: 1fr; }
            .navbar { padding: 1rem 2rem; }
        }
    </style>
</head>
<body>

    <nav class="navbar">
        <a href="../index.php" class="logo">
            <div class="logo-box"></div> BuildNexus
        </a>
        <ul class="nav-links">
            <li><a href="#">Features ▼</a></li>
            <li><a href="../who-we-serve.php">Who We Serve</a></li>
            <li><a href="../pricing.php">Pricing</a></li>
            <li><a href="../contact.php">Contact Us</a></li>
        </ul>
        <div class="nav-actions">
            <a href="../login.php" style="text-decoration: none; color: var(--text-gray); font-size: 0.9rem; font-weight: 600;">Log In</a>
            <a href="../register.php" class="btn-green">Get Started</a>
        </div>
    </nav>

    <div class="main-container">
        <aside class="sidebar">
            <ul class="sidebar-menu">
                <li class="sidebar-item"><a href="projects.php">Projects</a></li>
                <li class="sidebar-item"><a href="opportunities.php">Opportunities</a></li>
                <li class="sidebar-item"><a href="calendar.php">Calendar</a></li>
                <li class="sidebar-item"><a href="schedule.php">Schedule</a></li>
                <li class="sidebar-item"><a href="work-orders.php">Work Orders</a></li>
                <li class="sidebar-item"><a href="inspections.php">Inspections</a></li>
                <li class="sidebar-item"><a href="punchlists.php">Punchlists</a></li>
                <li class="sidebar-item"><a href="permit-manager.php">Permit Manager</a></li>
                <li class="sidebar-item"><a href="service-tickets.php">Service Tickets</a></li>
                <li class="sidebar-item"><a href="team-chat.php">Team Chat</a></li>
                <li class="sidebar-item"><a href="to-dos.php">To-Do's</a></li>
                <li class="sidebar-item"><a href="procurement.php">Procurement</a></li>
                <li class="sidebar-item"><a href="time-cards.php">Time Cards</a></li>
                <li class="sidebar-item"><a href="client-dashboard.php">Client Dashboard</a></li>
                <li class="sidebar-item active"><a href="drawings-pdf-markup.php">Drawings & PDF Markup</a></li>
                <li class="sidebar-item"><a href="subcontractor-dashboard.php">Subcontractor Dashboard</a></li>
            </ul>
        </aside>

        <main class="content">
            <section class="hero">
                <div class="hero-text">
                    <span>Drawings & PDF Markup</span>
                    <h1>Marking up your drawings and other PDF's just got simple</h1>
                    <p>Have a set of plans you need to markup or draw attention to? Easily use our redline tool to highlight areas in the drawings. Annotate and markup plans, drawings, and other PDF's.</p>
                </div>
                <div class="hero-img">
                    <img src="https://images.unsplash.com/photo-1503387762-592dea58ef21?auto=format&fit=crop&w=800&q=80" alt="Plans and Blueprints">
                </div>
            </section>

            <section class="features-grid">
                <div class="feature-card">
                    <div class="feature-header"><span>Red Line Markups</span></div>
                    <h3>Avoid confusion - draw directly on the plan</h3>
                    <p>Have a set of plans you need to markup or draw attention to? Easily use our redline tool to highlight areas in the drawings. Annotate and markup plans, drawings, and other PDF's.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-header"><span>Plan Measurements</span></div>
                    <h3>Stop guessing and start knowing</h3>
                    <p>In just a matter of minutes you can determine the square footage, distance, or area on your uploading plans. No longer do you need to export them to another system or print them to get accurate readings. Adjust the scale and select the measurement tool and bam - you've got your numbers per room.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-header"><span>Annotations and Comments</span></div>
                    <h3>Clearly communicate the point you are making</h3>
                    <p>Clear communication is critical in the construction industry or else it will cost you a lot of money and time. Easily add comments and annotations so that others can review your comments and add their replies. No longer do you have to match the comment up to the specific place in the plan being discussed.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-header"><span>Full App Control</span></div>
                    <h3>All features from the app</h3>
                    <p>We get it - sometimes you do not make it back to the office before the end of the day. You need a solution that allows you to view plans, add comments, get measurements, and annotate plans from your phone.</p>
                </div>
            </section>
        </main>
    </div>

    <footer class="footer">
        <div class="footer-grid">
            <div class="footer-col">
                <a href="../index.php" class="logo" style="margin-bottom: 1rem;"><div class="logo-box"></div> BuildNexus</a>
                <p style="font-size: 0.8rem; color: var(--text-gray);">The complete platform for construction professionals.</p>
            </div>
            <div class="footer-col">
                <h4>BuildNexus</h4>
                <ul>
                    <li>Features</li>
                    <li>Benefits</li>
                    <li>Contact</li>
                    <li>Live Chat</li>
                    <li>Estimate Generator</li>
                </ul>
            </div>
            <div class="footer-col">
                <h4>Legal</h4>
                <ul>
                    <li>Privacy Policy</li>
                    <li>Terms of Service</li>
                </ul>
            </div>
            <div class="footer-col">
                <h4>Contact</h4>
                <ul>
                    <li>123 Main Street</li>
                    <li>Colombo, Sri Lanka</li>
                    <li>+94 11 234 5678</li>
                </ul>
            </div>
        </div>
        <div class="footer-bottom">
            &copy; 2025 BuildNexus. Developed for Tharaka Construction.
        </div>
    </footer>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const sidebarLinks = document.querySelectorAll('.sidebar-item');
            sidebarLinks.forEach(link => {
                link.addEventListener('click', function() {
                    sidebarLinks.forEach(l => l.classList.remove('active'));
                    this.classList.add('active');
                });
            });
        });
    </script>
</body>
</html>