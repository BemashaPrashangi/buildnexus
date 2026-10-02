<?php
session_start();

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    // Redirect to login if session is empty
    header("Location: login.php");
    exit();
}
?>
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
    <title>Project Calendar - BuildNexus</title>
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
        .btn-green { background: var(--nexus-green); color: white; padding: 0.5rem 1.2rem; border-radius: 4px; text-decoration: none; font-weight: 600; border: none; cursor: pointer; }

        /* LAYOUT CONTAINER */
        .main-container { display: flex; max-width: 1440px; margin: 0 auto; }

        /* SIDEBAR (Project Management Context) */
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

        /* HERO SECTION */
        .hero { display: grid; grid-template-columns: 1fr 1fr; gap: 4rem; align-items: center; margin-bottom: 6rem; }
        .hero-text span { color: var(--nexus-green); font-weight: 700; font-size: 0.8rem; text-transform: uppercase; }
        .hero-text h1 { font-size: 2.5rem; line-height: 1.2; margin: 1rem 0; font-weight: 800; }
        .hero-text p { color: var(--text-gray); font-size: 1rem; margin-bottom: 2rem; }

        .hero-img { border-radius: 12px; overflow: hidden; box-shadow: 0 10px 30px rgba(0,0,0,0.1); border: 1px solid var(--border-color); }
        .hero-img img { width: 100%; display: block; }

        /* CALENDAR MOCKUP GRID */
        .calendar-visual {
            background: #fff;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 2rem;
            margin-bottom: 4rem;
        }
        .cal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; }
        .cal-grid { display: grid; grid-template-columns: repeat(7, 1fr); gap: 1px; background: var(--border-color); border: 1px solid var(--border-color); }
        .cal-day-label { background: var(--bg-light); padding: 10px; text-align: center; font-size: 0.75rem; font-weight: 700; color: var(--text-gray); }
        .cal-cell { background: #fff; height: 100px; padding: 10px; position: relative; font-size: 0.8rem; }
        .cal-event { background: #dcfce7; color: #166534; padding: 4px 8px; border-radius: 4px; font-size: 0.7rem; margin-top: 5px; font-weight: 600; border-left: 3px solid var(--nexus-green); }
        .cal-event.orange { background: #ffedd5; color: #9a3412; border-left-color: var(--nexus-orange); }

        /* FEATURES GRID */
        .features-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 2rem; }
        .feature-card { background: #fff; padding: 2rem; border-radius: 8px; border: 1px solid var(--border-color); }
        .feature-card h3 { font-size: 1.1rem; margin-bottom: 1rem; font-weight: 800; }
        .feature-card p { font-size: 0.85rem; color: var(--text-gray); line-height: 1.6; }

        /* FOOTER */
        .footer { background: var(--bg-light); padding: 4rem 5rem 2rem; border-top: 1px solid var(--border-color); margin-top: 4rem;}
        .footer-grid { display: grid; grid-template-columns: 2fr 1fr 1fr 1fr; gap: 4rem; margin-bottom: 3rem; }
        .footer-col h4 { font-size: 0.9rem; margin-bottom: 1.5rem; }
        .footer-col ul { list-style: none; }
        .footer-col li { margin-bottom: 0.75rem; font-size: 0.8rem; color: var(--text-gray); }
        .footer-bottom { border-top: 1px solid var(--border-color); padding-top: 2rem; text-align: left; font-size: 0.75rem; color: #9ca3af; }

        @media (max-width: 1024px) {
            .hero, .features-grid, .footer-grid { grid-template-columns: 1fr; }
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
                <li class="sidebar-item active"><a href="calendar.php">Calendar</a></li>
                <li class="sidebar-item"><a href="schedule.php">Schedule</a></li>
                <li class="sidebar-item"><a href="work-orders.php">Work Orders</a></li>
                <li class="sidebar-item"><a href="inspections.php">Inspections</a></li>
                <li class="sidebar-item"><a href="punchlists.php">Punchlists</a></li>
                <li class="sidebar-item"><a href="time-cards.php">Time Cards</a></li>
            </ul>
        </aside>

        <main class="content">
            <section class="hero">
                <div class="hero-text">
                    <span>Calendar</span>
                    <h1>Stay on Top of Every Deadline</h1>
                    <p>Sync your project schedules, appointments, and deadlines into one powerful view. Coordinate teams and equipment with ease across multiple jobsites.</p>
                    <a href="#" class="btn-green" style="padding: 0.8rem 1.5rem;">View Live Calendar</a>
                </div>
                <div class="hero-img">
                    <img src="https://images.unsplash.com/photo-1506784919141-105156a0d24c?auto=format&fit=crop&w=800&q=80" alt="Calendar Interface">
                </div>
            </section>

            <div class="calendar-visual">
                <div class="cal-header">
                    <h2 id="currentMonth">December 2025</h2>
                    <div>
                        <button style="padding: 5px 15px; border: 1px solid var(--border-color); background: #fff; cursor: pointer;">Today</button>
                    </div>
                </div>
                <div class="cal-grid">
                    <div class="cal-day-label">Sun</div><div class="cal-day-label">Mon</div><div class="cal-day-label">Tue</div><div class="cal-day-label">Wed</div><div class="cal-day-label">Thu</div><div class="cal-day-label">Fri</div><div class="cal-day-label">Sat</div>
                    
                    <div class="cal-cell">14</div>
                    <div class="cal-cell">15 <div class="cal-event">Foundation Pour</div></div>
                    <div class="cal-cell">16</div>
                    <div class="cal-cell">17 <div class="cal-event orange">Site Inspection</div></div>
                    <div class="cal-cell">18</div>
                    <div class="cal-cell">19 <div class="cal-event">Framing Start</div></div>
                    <div class="cal-cell">20</div>
                </div>
            </div>

            <section class="features-grid">
                <div class="feature-card">
                    <h3>Multi-Project View</h3>
                    <p>See all your project milestones in a single master calendar or filter by specific projects to prevent scheduling conflicts.</p>
                </div>
                <div class="feature-card">
                    <h3>Mobile Sync</h3>
                    <p>Sync your BuildNexus calendar with Google Calendar, Outlook, or iCal so your field crew always has the latest updates.</p>
                </div>
                <div class="feature-card">
                    <h3>Drag & Drop Scheduling</h3>
                    <p>Easily move tasks and appointments. When you move an item, all related team members are notified automatically.</p>
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