<?php
// Database connection and session must be established at the top of your file
require_once 'db.php';

// Fetch the pending estimate for this client's linked project
$stmt_pending = $pdo->prepare("
    SELECT e.id, e.estimate_number, e.total_amount 
    FROM estimates e 
    JOIN clients c ON e.project_id = c.project_id 
    WHERE c.id = ? AND e.status = 'Sent' 
    LIMIT 1
");
$stmt_pending->execute([$_SESSION['user_id']]);
$pending_est = $stmt_pending->fetch();
?>

<?php if ($pending_est): ?>
    <div style="background: #fffbe6; border: 1px solid #ffe58f; padding: 1.5rem; border-radius: 8px; margin-bottom: 2rem; display: flex; justify-content: space-between; align-items: center;">
        <div>
            <span style="color: #856404; font-size: 0.8rem; font-weight: 700; text-transform: uppercase;">Action Required</span>
            <h3 style="margin: 0.5rem 0;">New Proposal: <?= $pending_est['estimate_number'] ?></h3>
            <p style="margin: 0; color: #666;">Total Amount: <strong>RS. <?= number_format($pending_est['total_amount'], 2) ?></strong></p>
        </div>
        <a href="view_estimate_client.php?id=<?= $pending_est['id'] ?>" class="btn btn-success btn-sm w-100 mt-2 fw-bold">Review & Approve</a>

        <a href="features/view_estimate_client.php?id=<?= $pending_est['id'] ?>" class="btn btn-success btn-sm w-100 mt-2 fw-bold">Review & Approve</a>
    </div>
<?php endif; ?>
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
    <title>Client Dashboard - BuildNexus</title>
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

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

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

        .logo {
            font-weight: 700;
            font-size: 1.25rem;
            display: flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            color: inherit;
        }

        .logo-box {
            width: 20px;
            height: 20px;
            background: var(--nexus-orange);
            border-radius: 4px;
        }

        .nav-links {
            display: flex;
            gap: 2rem;
            list-style: none;
        }

        .nav-links a {
            text-decoration: none;
            color: var(--text-gray);
            font-size: 0.9rem;
            font-weight: 500;
        }

        .nav-actions {
            display: flex;
            align-items: center;
            gap: 1.5rem;
        }

        .btn-green {
            background: var(--nexus-green);
            color: white;
            padding: 0.5rem 1.2rem;
            border-radius: 4px;
            text-decoration: none;
            font-weight: 600;
        }

        /* LAYOUT CONTAINER */
        .main-container {
            display: flex;
            max-width: 1440px;
            margin: 0 auto;
        }

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

        .sidebar-menu {
            list-style: none;
        }

        .sidebar-item a {
            display: block;
            padding: 0.75rem 2rem;
            color: var(--text-gray);
            text-decoration: none;
            font-size: 0.85rem;
            transition: all 0.2s;
        }

        .sidebar-item a:hover {
            background: #f3f4f6;
            color: var(--text-dark);
        }

        .sidebar-item.active a {
            background: #f3f4f6;
            color: var(--text-dark);
            font-weight: 600;
            border-right: 3px solid var(--nexus-green);
        }

        /* CONTENT AREA */
        .content {
            flex: 1;
            padding: 4rem;
        }

        .hero {
            margin-bottom: 4rem;
            text-align: center;
        }

        .hero span {
            color: var(--nexus-green);
            font-weight: 600;
            font-size: 0.9rem;
            text-transform: uppercase;
        }

        .hero h1 {
            font-size: 2.8rem;
            line-height: 1.2;
            margin: 1rem 0;
        }

        .hero p {
            color: var(--text-gray);
            max-width: 800px;
            margin: 0 auto;
        }

        /* SECTION TITLE */
        .section-header {
            text-align: center;
            margin-bottom: 4rem;
        }

        .section-header h2 {
            font-size: 2.2rem;
            font-weight: 700;
            color: var(--text-dark);
        }

        /* FEATURES GRID */
        .features-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 2.5rem;
            margin-bottom: 5rem;
        }

        .feature-card {
            background: #fff;
            padding: 1.5rem;
            border-radius: 8px;
        }

        .feature-icon {
            color: var(--nexus-green);
            margin-bottom: 1rem;
            font-size: 1.2rem;
        }

        .feature-card h3 {
            font-size: 1rem;
            margin-bottom: 0.75rem;
            color: var(--text-dark);
            font-weight: 700;
        }

        .feature-card p {
            font-size: 0.85rem;
            color: var(--text-gray);
            line-height: 1.6;
        }

        /* FOOTER */
        .footer {
            background: var(--bg-light);
            padding: 4rem 5rem 2rem;
            border-top: 1px solid var(--border-color);
        }

        .footer-grid {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr 1fr;
            gap: 4rem;
            margin-bottom: 3rem;
        }

        .footer-col h4 {
            font-size: 0.9rem;
            margin-bottom: 1.5rem;
        }

        .footer-col ul {
            list-style: none;
        }

        .footer-col li {
            margin-bottom: 0.75rem;
            font-size: 0.8rem;
            color: var(--text-gray);
        }

        .footer-bottom {
            border-top: 1px solid var(--border-color);
            padding-top: 2rem;
            text-align: left;
            font-size: 0.75rem;
            color: #9ca3af;
        }

        /* Mobile Responsive */
        @media (max-width: 1024px) {
            .features-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .navbar {
                padding: 1rem 2rem;
            }
        }

        @media (max-width: 768px) {
            .main-container {
                flex-direction: column;
            }

            .sidebar {
                width: 100%;
                height: auto;
                position: static;
            }

            .features-grid {
                grid-template-columns: 1fr;
            }
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
                <li class="sidebar-item active"><a href="client-dashboard.php">Client Dashboard</a></li>
                <li class="sidebar-item"><a href="subcontractor-dashboard.php">Subcontractor Dashboard</a></li>
            </ul>
        </aside>

        <main class="content">
            <section class="section-header">
                <h2>Powerful Features for Your Clients</h2>
            </section>

            <section class="features-grid">
                <div class="feature-card">
                    <div class="feature-icon">💳</div>
                    <h3>Pay Online</h3>
                    <p>No longer do you need to mail invoices or wait for your clients to send you a check. They can easily pay their invoice online using multiple credit cards or using ACH transfers.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">👥</div>
                    <h3>Multi-Project Login</h3>
                    <p>Your customer can access all of their projects from one client portal account. As they change the project within the dropdown, the layout will change to show a customized experience.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">📅</div>
                    <h3>Project Schedule</h3>
                    <p>No longer does your client have to guess or ask 'what's next'. When you share your schedule with them, they can see the project schedule.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">📊</div>
                    <h3>Financial Summary</h3>
                    <p>The financial summary provides an easy to understand overview that helps your client see the original contract amount, billed amount, and any balance due.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">📝</div>
                    <h3>Notes</h3>
                    <p>Store copies of your email conversations, preferences or anything else in the Notes section so that you and your client have access to it later, creating an important paper trail.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">✅</div>
                    <h3>To-Do's</h3>
                    <p>A great way to let your client know what is required from them. They can easily check the item once the task has been completed.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">📑</div>
                    <h3>Daily Logs</h3>
                    <p>Share your customized Daily Logs with your client and keep them informed on the progress made each day.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">🔒</div>
                    <h3>Customize Clients Access</h3>
                    <p>You can share product details with your clients the way that you want to share it. Choose the photos, layouts, and types of items that can be shared.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">📤</div>
                    <h3>Easy File Sharing</h3>
                    <p>Clients needs files? Your client can send you files through the Client Chat feature or they can upload them directly to the files section.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">🖼️</div>
                    <h3>Photo Gallery</h3>
                    <p>Let all your progress photos to speak for you. Clients love to see photos of the progress being made.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">💬</div>
                    <h3>RFI & Submittals</h3>
                    <p>Your client can respond to Submittals or RFI's directly from the client portal and you are notified when they respond.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">🗨️</div>
                    <h3>Client Chat</h3>
                    <p>Chat with your client from the app or the website. Not only is it simple, but it keeps a history going forward. Say goodbye to floods of emails and text.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">📄</div>
                    <h3>File Viewing & Upload</h3>
                    <p>Share files with your client so that you and them have access to the same documents at all times to clarify any questions that may come up.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">🔔</div>
                    <h3>Action Items Requiring Immediate Attention</h3>
                    <p>Your client will see any action items that need their attention on the dashboard. This helps reduce delays by making sure items are being reviewed and approved.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">✍️</div>
                    <h3>Electronic Approvals & Signatures</h3>
                    <p>Clients can Approve and Request Change Orders Right From Client Portal. Keep the Paper Trail Clear and Concise. Know Within Minutes When a Change Order is Approved.</p>
                </div>
            </section>
        </main>
    </div>

    <footer class="footer">
        <div class="footer-grid">
            <div class="footer-col">
                <a href="../index.php" class="logo" style="margin-bottom: 1rem;">
                    <div class="logo-box"></div> BuildNexus
                </a>
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
        // Use Vanilla JS for interactivity
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