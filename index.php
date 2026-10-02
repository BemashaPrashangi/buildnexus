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
    <title>BuildNexus - Construction Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="style.css"> 
</head>
<body>

    <nav class="navbar navbar-expand-lg navbar-light py-4">
        <div class="container position-relative">
            <a class="navbar-brand fw-bold d-flex align-items-center" href="index.php">
                <img src="images/logo.png" alt="BuildNexus Logo" style="width: 45px; height: 45px; margin-right: 12px;">
                BuildNexus
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav mx-auto">
                    
                    <li class="nav-item dropdown position-static">
                        <a class="nav-link px-3 dropdown-toggle" href="#" id="featuresDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            Features
                        </a>
                        <div class="dropdown-menu mt-2 border-0 shadow-lg mega-menu-content w-100" aria-labelledby="featuresDropdown">
                            <div class="p-4">
                                <h6 class="fw-bold mb-4 pb-3 border-bottom" style="font-size: 1.1rem;">All-In-One Platform for Construction and Design</h6>
                                <div class="row g-4">
                                    <div class="col-lg-3 border-end">
                                        <span class="text-uppercase text-muted fw-bold small mb-3 d-block spacing-wide">Planning</span>
                                        <ul class="list-unstyled mb-0">
                                            <li><a href="features/floor-plans.php" class="dropdown-item">Floor Plans & Blueprints</a></li>
                                            <li><a href="features/3d-floor-plans.php" class="dropdown-item">3D Floor Plans</a></li>
                                            <li><a href="features/takeoffs.php" class="dropdown-item">Takeoffs</a></li>
                                            <li><a href="features/contracts.php" class="dropdown-item">Contracts</a></li>
                                            <li><a href="features/bid-management.php" class="dropdown-item">Bid Management</a></li>
                                            <li><a href="features/product-clipper.php" class="dropdown-item">Product Clipper & Library</a></li>
                                            <li><a href="features/selections.php" class="dropdown-item">Selections</a></li>
                                            <li><a href="features/crm.php" class="dropdown-item">CRM</a></li>
                                            <li><a href="features/directory.php" class="dropdown-item">Directory</a></li>
                                            <li><a href="features/mood-boards.php" class="dropdown-item">Mood Boards</a></li>
                                        </ul>
                                    </div>

                                    <div class="col-lg-2 border-end">
                                        <span class="text-uppercase text-muted fw-bold small mb-3 d-block spacing-wide">Financials</span>
                                        <ul class="list-unstyled mb-0">
                                            <li><a href="features/interactive-estimates.php" class="dropdown-item">Interactive Estimates</a></li>
                                            <li><a href="features/proposals.php" class="dropdown-item">Proposals</a></li>
                                            <li><a href="features/change-orders.php" class="dropdown-item">Change Orders</a></li>
                                            <li><a href="features/invoicing.php" class="dropdown-item">Invoicing</a></li>
                                            <li><a href="features/online-payments.php" class="dropdown-item">Online Payments</a></li>
                                            <li><a href="features/reports.php" class="dropdown-item">Reports</a></li>
                                        </ul>
                                    </div>

                                    <div class="col-lg-4 border-end">
                                        <span class="text-uppercase text-muted fw-bold small mb-3 d-block spacing-wide">Project Management</span>
                                        <div class="row">
                                            <div class="col-6">
                                                <ul class="list-unstyled mb-0">
                                                    <li><a href="features/projects.php" class="dropdown-item">Projects</a></li>
                                                    <li><a href="features/opportunities.php" class="dropdown-item">Opportunities</a></li>
                                                    <li><a href="features/daily-logs.php" class="dropdown-item">Calendar</a></li>
                                                    <li><a href="features/schedule.php" class="dropdown-item">Schedule</a></li>
                                                    <li><a href="features/work-orders.php" class="dropdown-item">Work Orders</a></li>
                                                    <li><a href="features/inspections.php" class="dropdown-item">Inspections</a></li>
                                                    <li><a href="features/punchlists.php" class="dropdown-item">Punchlists</a></li>
                                                    <li><a href="features/permit-manager.php" class="dropdown-item">Permit Manager</a></li>
                                                    <li><a href="features/service-tickets.php" class="dropdown-item">Service Tickets</a></li>
                                                    <li><a href="features/team-chat.php" class="dropdown-item">Team Chat</a></li>
                                                    <li><a href="features/to-dos.php" class="dropdown-item">To-Do's</a></li>
                                                </ul>
                                            </div>
                                            <div class="col-6">
                                                <ul class="list-unstyled mb-0">
                                                    <li><a href="procurement_info.php" class="dropdown-item">Procurement</a></li>
                                                    <li><a href="features/time-cards.php" class="dropdown-item">Time Cards</a></li>
                                                    <li><a href="features/client-dashboard.php" class="dropdown-item">Client Dashboard</a></li>
                                                    <li><a href="features/drawings-pdf-markup.php" class="dropdown-item">Drawings & PDF Markup</a></li>
                                                    <li><a href="features/safety-meetings.php" class="dropdown-item">Safety Meetings</a></li>
                                                    <li><a href="features/forms-and-checklists.php" class="dropdown-item">Forms and Checklists</a></li>
                                                    <li><a href="features/rfi.php" class="dropdown-item">RFIs</a></li>
                                                    <li><a href="features/equipment-logs.php" class="dropdown-item">Equipment & Vehicle Logs</a></li>
                                                    <li><a href="features/submittals.php" class="dropdown-item">Submittals</a></li>
                                                    <li><a href="features/subcontractor-dashboard.php" class="dropdown-item">Subcontractor Dashboard</a></li>
                                                </ul>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-lg-3">
                                        <span class="text-uppercase text-muted fw-bold small mb-3 d-block spacing-wide">Marketing</span>
                                        <ul class="list-unstyled mb-0">
                                            <li><a href="features/email-marketing.php" class="dropdown-item">Email Marketing</a></li>
                                            <li><a href="features/lead-generation.php" class="dropdown-item">Lead Generation</a></li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </li>
                    <li class="nav-item"><a class="nav-link px-3" href="who-we-serve.php">Who We Serve</a></li>
                    <li class="nav-item"><a class="nav-link px-3" href="pricing.php">Pricing</a></li>
                    <li class="nav-item"><a class="nav-link px-3" href="contact.php">Contact Us</a></li>
                </ul>

                <div class="d-flex align-items-center">
                    <a href="login.php" class="text-dark text-decoration-none fw-bold me-4">Login</a>
                    <a href="register.php" class="btn btn-success px-4 rounded-1">Sign Up</a>
                </div>
            </div>
        </div>
    </nav>

    <header class="hero-section container my-5">
        <div class="row align-items-center">
            <div class="col-lg-6 pe-lg-5">
                <h1 class="display-4 fw-bolder mb-4 text-dark">Simplify Your Construction Projects with BuildNexus</h1>
                <p class="lead text-secondary mb-5" style="font-size: 1.1rem;">The all-in-one platform designed for Sri Lankan contractors. Manage quotes, track progress, control costs, and improve client communication.</p>
                <a href="#" class="btn btn-success btn-lg px-5 rounded-1">Get Started</a>
            </div>
            <div class="col-lg-6 mt-5 mt-lg-0">
                <img src="https://images.unsplash.com/photo-1512917774080-9991f1c4c750?ixlib=rb-1.2.1&auto=format&fit=crop&w=800&q=80" alt="Modern House" class="img-fluid rounded-4 shadow-lg hero-img">
            </div>
        </div>
    </header>

    <section class="container py-5 mt-5">
        <h3 class="text-center fw-bold mb-5">Why Choose BuildNexus?</h3>
        <div class="row g-4">
            <div class="col-md-3">
                <div class="card h-100 border-0 shadow-sm p-4 text-center feature-card">
                    <div class="icon-circle mb-3 mx-auto text-success bg-light-green"><i class="fas fa-dollar-sign"></i></div>
                    <h6 class="fw-bold">Control Costs Effectively</h6>
                    <p class="small text-muted mt-2">Track budget vs actuals in real-time to prevent overruns.</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card h-100 border-0 shadow-sm p-4 text-center feature-card">
                    <div class="icon-circle mb-3 mx-auto text-success bg-light-green"><i class="fas fa-bolt"></i></div>
                    <h6 class="fw-bold">Streamline Field Operations</h6>
                    <p class="small text-muted mt-2">Manage field operations and daily logs from one place.</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card h-100 border-0 shadow-sm p-4 text-center feature-card">
                    <div class="icon-circle mb-3 mx-auto text-success bg-light-green"><i class="fas fa-check-circle"></i></div>
                    <h6 class="fw-bold">Win More Bids Faster</h6>
                    <p class="small text-muted mt-2">Create professional quotes faster with templates.</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card h-100 border-0 shadow-sm p-4 text-center feature-card">
                    <div class="icon-circle mb-3 mx-auto text-success bg-light-green"><i class="fas fa-users"></i></div>
                    <h6 class="fw-bold">Enhance Client Transparency</h6>
                    <p class="small text-muted mt-2">Give clients a clear view of progress and milestones.</p>
                </div>
            </div>
        </div>
    </section>

    <section class="container-fluid py-5 my-5 bg-white">
        <div class="container">
            <h3 class="text-center fw-bold mb-5">How BuildNexus Transforms Your Workflow</h3>
            <div class="slider-container d-flex gap-4 overflow-auto pb-4 px-2">
                <div class="dark-card flex-shrink-0 p-4 rounded-4 text-white position-relative" style="width: 350px; background-color: #0b0f19;">
                    <div class="d-flex justify-content-between align-items-center mb-3"><h5 class="mb-0">Integrated Quoting</h5><i class="fas fa-arrow-right"></i></div>
                    <p class="small text-secondary mb-4">Create accurate estimates in minutes using templates.</p>
                    <div class="card-image-wrapper bg-white rounded-3 overflow-hidden mt-auto">
                        <img src="images/workflow-1.jpg" class="img-fluid" alt="Quoting">
                    </div>
                </div>
                <div class="dark-card flex-shrink-0 p-4 rounded-4 text-white position-relative" style="width: 350px; background-color: #2c0b0e;">
                    <div class="d-flex justify-content-between align-items-center mb-3"><h5 class="mb-0">Task Management</h5><i class="fas fa-arrow-right"></i></div>
                    <p class="small text-secondary mb-4">Assign tasks and track schedules instantly.</p>
                    <div class="card-image-wrapper bg-white rounded-3 overflow-hidden mt-auto">
                        <img src="images/workflow-2.jpg" class="img-fluid" alt="Tasks">
                    </div>
                </div>
                <div class="dark-card flex-shrink-0 p-4 rounded-4 text-white position-relative" style="width: 350px; background-color: #1a1a1a;">
                    <div class="d-flex justify-content-between align-items-center mb-3"><h5 class="mb-0">Financials at a Glance</h5><i class="fas fa-arrow-right"></i></div>
                    <p class="small text-secondary mb-4">Monitor budget vs actual costs in real-time.</p>
                    <div class="card-image-wrapper bg-white rounded-3 overflow-hidden mt-auto">
                        <img src="images/workflow-3.jpg" class="img-fluid" alt="Financials">
                    </div>
                </div>
                 <div class="dark-card flex-shrink-0 p-4 rounded-4 text-white position-relative" style="width: 350px; background-color: #0f2d25;">
                    <div class="d-flex justify-content-between align-items-center mb-3"><h5 class="mb-0">Collaborative Portal</h5><i class="fas fa-arrow-right"></i></div>
                    <p class="small text-secondary mb-4">Share progress and documents securely.</p>
                    <div class="card-image-wrapper bg-white rounded-3 overflow-hidden mt-auto">
                        <img src="images/workflow-4.jpg" class="img-fluid" alt="Client Portal">
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="container py-5 text-center">
        <h4 class="fw-bold mb-4">Trusted by Builders Like You</h4>
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="p-5 bg-light-yellow rounded-3 border-0" style="background-color: #fff9e6;">
                    <p class="fst-italic fs-5 mb-4">"BuildNexus has been a game-changer for our projects. The real-time tracking has saved us countless hours of administrative work."</p>
                    <h6 class="fw-bold text-dark">- Nimal Perera</h6>
                    <p class="small text-muted">Director, Tharaka Construction</p>
                </div>
            </div>
        </div>
    </section>

    <section class="container py-5">
        <div class="text-center mb-5">
            <h2 class="fw-bold mb-4">Ready to Modernize Your Construction<br>Business?</h2>
            <a href="#" class="btn btn-success btn-lg px-5 rounded-1">Get Started</a>
        </div>
    </section>

    <footer class="py-5 mt-5" style="border-top: 1px solid #eee;">
        <div class="container">
            <div class="row">
                <div class="col-lg-4 mb-4">
                    <a class="navbar-brand fw-bold d-flex align-items-center mb-3" href="index.php">
                        <img src="images/logo.png" alt="BuildNexus Logo" style="width: 40px; height: 40px; margin-right: 10px;">
                        BuildNexus
                    </a>
                    <p class="text-muted small">The complete system for construction professionals.</p>
                </div>
                <div class="col-lg-2 col-6 mb-4">
                    <h6 class="fw-bold mb-3 small">Solutions</h6>
                    <ul class="list-unstyled small text-muted">
                        <li class="mb-2"><a href="#" class="text-decoration-none text-muted">Features</a></li>
                        <li class="mb-2"><a href="pricing.php" class="text-decoration-none text-muted">Pricing</a></li>
                        <li class="mb-2"><a href="contact.php" class="text-decoration-none text-muted">Contact</a></li>
                    </ul>
                </div>
                <div class="col-lg-2 col-6 mb-4">
                    <h6 class="fw-bold mb-3 small">Legal</h6>
                    <ul class="list-unstyled small text-muted">
                        <li class="mb-2"><a href="#" class="text-decoration-none text-muted">Privacy Policy</a></li>
                        <li class="mb-2"><a href="#" class="text-decoration-none text-muted">Terms of Service</a></li>
                    </ul>
                </div>
                <div class="col-lg-4 mb-4">
                    <h6 class="fw-bold mb-3 small">Contact</h6>
                    <p class="small text-muted mb-1">123 Main Street, Colombo 03, Sri Lanka</p>
                    <p class="small text-muted">+94 11 234 5678</p>
                </div>
            </div>
            <div class="text-center mt-4 pt-4 border-top">
                <p class="small text-muted mb-0">&copy; <?php echo date("Y"); ?> BuildNexus. Developed for Tharaka Construction.</p>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>