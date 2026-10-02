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
    <title>Forms and Checklists - BuildNexus</title>
    <style>
        /* GLOBAL STYLES - PURE CSS */
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
            grid-template-columns: repeat(3, 1fr);
            gap: 2rem;
            margin-bottom: 5rem;
        }

        .feature-card {
            background: var(--bg-light);
            padding: 2rem;
            border-radius: 8px;
        }
        
        .feature-header { display: flex; align-items: center; gap: 10px; margin-bottom: 1rem; }
        .feature-dot { width: 10px; height: 10px; background-color: var(--nexus-green); border-radius: 50%; }
        .feature-header span { color: var(--nexus-green); font-weight: 600; font-size: 0.8rem; }

        .feature-card h3 { font-size: 1.1rem; margin-bottom: 1rem; color: var(--text-dark); font-weight: 700; }
        .feature-card p { font-size: 0.85rem; color: var(--text-gray); line-height: 1.6; }

        /* FOOTER */
        .footer { background: var(--bg-light); padding: 4rem 5rem 2rem; border-top: 1px solid var(--border-color); }
        .footer-grid { display: grid; grid-template-columns: 2fr 1fr 1fr 1fr; gap: 4rem; margin-bottom: 3rem; }
        .footer-col h4 { font-size: 0.9rem; margin-bottom: 1.5rem; }
        .footer-col ul { list-style: none; }
        .footer-col li { margin-bottom: 0.75rem; font-size: 0.8rem; color: var(--text-gray); }
        .footer-bottom { border-top: 1px solid var(--border-color); padding-top: 2rem; text-align: left; font-size: 0.75rem; color: #9ca3af; }

        /* Responsive */
        @media (max-width: 1024px) {
            .hero { grid-template-columns: 1fr; text-align: center; }
            .features-grid { grid-template-columns: 1fr 1fr; }
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
                <li class="sidebar-item"><a href="drawings-pdf-markup.php">Drawings & PDF Markup</a></li>
                <li class="sidebar-item"><a href="safety-meetings.php">Safety Meetings</a></li>
                <li class="sidebar-item active"><a href="forms-and-checklists.php">Forms and Checklists</a></li>
                <li class="sidebar-item"><a href="subcontractor-dashboard.php">Subcontractor Dashboard</a></li>
            </ul>
        </aside>

        <main class="content">
            <section class="hero">
                <div class="hero-text">
                    <span>Forms and Checklists</span>
                    <h1>Create the forms you need in minutes</h1>
                    <p>Have a form that works well for your company? Great - use it within BuildNexus or use one of our templates; modify it or add your own form or checklist. Our form builder makes it easy to create forms and checklists in minutes. No coding required.</p>
                </div>
                <div class="hero-img">
                    <img src="https://images.unsplash.com/photo-1581291518633-83b4ebd1d83e?auto=format&fit=crop&w=800&q=80" alt="Tablet with forms">
                </div>
            </section>

            <section class="features-grid">
                <div class="feature-card">
                    <div class="feature-header">
                        <div class="feature-dot"></div>
                        <span>Checklist Templates</span>
                    </div>
                    <h3>Save time using Checklist templates</h3>
                    <p>Do you have checklists that you tend to use for multiple projects? Don't recreate it each time - save it as a template and use it over and over.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-header">
                        <div class="feature-dot"></div>
                        <span>Custom Checklists</span>
                    </div>
                    <h3>Easy to learn and use custom forms</h3>
                    <p>Need a Custom form, we got the tools. What you need is done in minutes. Start with one of our examples and revise it to meet your needs or create your own from scratch.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-header">
                        <div class="feature-dot"></div>
                        <span>50+ Stock Forms</span>
                    </div>
                    <h3>We Pre-Built 50+ forms for you</h3>
                    <p>We get you started with over fifty forms prebuilt, ready to go. No extra work needed. Use it as-is or make changes or create your own custom form.</p>
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
        // Vanilla JavaScript for sidebar active states
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