
<?php
session_start();

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    // Redirect to login if session is empty
    header("Location: login.php");
    exit();
}
?><!DOCTYPE html>
<html lang="en">
<head>
    <!-- BuildNexus Favicon & Brand Icons -->
    <link rel="icon" type="image/png" sizes="32x32" href="/buildnexus/images/logo.png?v=2">
    <link rel="icon" type="image/png" sizes="16x16" href="/buildnexus/images/logo.png?v=2">
    <link rel="shortcut icon" href="/buildnexus/images/logo.png?v=2">
    <link rel="apple-touch-icon" href="/buildnexus/images/logo.png?v=2">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Subcontractor Dashboard - BuildNexus</title>
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
            overflow-y: auto;
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
        .hero-text h1 { font-size: 2.8rem; line-height: 1.1; margin-bottom: 1.5rem; color: var(--text-dark); font-weight: 800; }
        .hero-text p { color: var(--text-gray); font-size: 1rem; margin-bottom: 2rem; }
        .btn-demo { display: inline-block; background: var(--nexus-green); color: white; padding: 0.8rem 1.8rem; border-radius: 6px; text-decoration: none; font-weight: 700; font-size: 0.9rem; }

        .hero-img { border-radius: 12px; overflow: hidden; background: #e5e7eb; height: 350px; display: flex; align-items: center; justify-content: center; }
        .hero-img img { width: 100%; height: 100%; object-fit: cover; }

        /* TOOLS SECTION */
        .tools-section { text-align: center; margin-bottom: 8rem; }
        .tools-section h2 { font-size: 2.2rem; margin-bottom: 4rem; font-weight: 800; }
        .tools-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 3rem; }
        .tool-card { padding: 1rem; }
        .tool-card .icon { font-size: 1.5rem; color: var(--nexus-green); margin-bottom: 1rem; }
        .tool-card h3 { font-size: 1.1rem; margin-bottom: 0.8rem; font-weight: 800; }
        .tool-card p { font-size: 0.85rem; color: var(--text-gray); line-height: 1.6; }

        /* WIN MORE BIDS SECTION */
        .bids-section { display: grid; grid-template-columns: 1fr 1fr; gap: 4rem; align-items: center; margin-bottom: 8rem; }
        .bids-text h2 { font-size: 2.2rem; margin-bottom: 1.5rem; font-weight: 800; }
        .bids-text p { color: var(--text-gray); margin-bottom: 2rem; font-size: 0.9rem; }
        .bids-list { list-style: none; }
        .bids-list li { display: flex; align-items: flex-start; gap: 12px; margin-bottom: 1rem; font-size: 0.85rem; color: var(--text-dark); }
        .bids-list li::before { content: '✔'; color: var(--nexus-green); font-weight: bold; }

        /* CTA SECTION */
        .cta-section { text-align: center; padding: 6rem 0; background: #fff; margin-bottom: 4rem; }
        .cta-section h2 { font-size: 2.2rem; margin-bottom: 1rem; font-weight: 800; }
        .cta-section p { color: var(--text-gray); margin-bottom: 2.5rem; font-size: 1rem; }
        .btn-signup { display: inline-block; background: var(--nexus-green); color: white; padding: 0.8rem 2.5rem; border-radius: 6px; text-decoration: none; font-weight: 700; }

        /* FOOTER */
        .footer { background: var(--bg-light); padding: 4rem 5rem 2rem; border-top: 1px solid var(--border-color); }
        .footer-grid { display: grid; grid-template-columns: 2fr 1fr 1fr 1fr; gap: 4rem; margin-bottom: 3rem; }
        .footer-col h4 { font-size: 0.9rem; margin-bottom: 1.5rem; }
        .footer-col ul { list-style: none; }
        .footer-col li { margin-bottom: 0.75rem; font-size: 0.8rem; color: var(--text-gray); }
        .footer-bottom { border-top: 1px solid var(--border-color); padding-top: 2rem; text-align: left; font-size: 0.75rem; color: #9ca3af; }

        @media (max-width: 1024px) {
            .hero, .tools-grid, .bids-section { grid-template-columns: 1fr; }
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
                <li class="sidebar-item"><a href="forms-and-checklists.php">Forms and Checklists</a></li>
                <li class="sidebar-item"><a href="rfi.php">RFIs</a></li>
                <li class="sidebar-item"><a href="equipment-logs.php">Equipment & Vehicle Logs</a></li>
                <li class="sidebar-item"><a href="submittals.php">Submittals</a></li>
                <li class="sidebar-item active"><a href="subcontractor-dashboard.php">Subcontractor Dashboard</a></li>
            </ul>
        </aside>

        <main class="content">
            <section class="hero">
                <div class="hero-text">
                    <h1>Succeed in Sync With Our Subcontractor Dashboard</h1>
                    <p>Manage your bids, project information, and payments all in one place. Our easy-to-use platform streamlines communication so you can focus on your work.</p>
                    <a href="#" class="btn-demo">Get a Free Demo</a>
                </div>
                <div class="hero-img">
                    <img src="https://images.unsplash.com/photo-1504307651254-35680f356dfd?auto=format&fit=crop&w=800&q=80" alt="Subcontractor using tablet">
                </div>
            </section>

            <section class="tools-section">
                <h2>The Tools You Need to Get the Job Done</h2>
                <div class="tools-grid">
                    <div class="tool-card">
                        <div class="icon">📄</div>
                        <h3>Submit Bids Easily</h3>
                        <p>Receive bid invitations, access all project documents, and submit your proposals all in one place.</p>
                    </div>
                    <div class="tool-card">
                        <div class="icon">🗓️</div>
                        <h3>Stay on Schedule</h3>
                        <p>Always have access to the latest project schedule, ensuring you know your deadlines and can plan accordingly.</p>
                    </div>
                    <div class="tool-card">
                        <div class="icon">💰</div>
                        <h3>Track Your Payments</h3>
                        <p>Submit payment applications and see the status of your invoices so you always know when you're getting paid.</p>
                    </div>
                </div>
            </section>

            <section class="bids-section">
                <div class="bids-text">
                    <h2>Win More Bids</h2>
                    <p>Receive invitations to bid from top general contractors in your area. Access all project documents, ask questions, and submit your proposals through one simple portal.</p>
                    <ul class="bids-list">
                        <li>Get notified about new bidding opportunities.</li>
                        <li>View all plans and specifications in one place.</li>
                        <li>Track the status of your bids from submission to award.</li>
                    </ul>
                </div>
                <div class="hero-img" style="height: 280px;">
                    <img src="https://images.unsplash.com/photo-1517245386807-bb43f82c33c4?auto=format&fit=crop&w=800&q=80" alt="Construction plans">
                </div>
            </section>

            <section class="cta-section">
                <h2>Join the Network of Top Subcontractors</h2>
                <p>Create your free BuildNexus profile and start getting invited to bid on projects today.</p>
                <a href="#" class="btn-signup">Sign Up for Free</a>
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