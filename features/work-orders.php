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
    <title>Work Orders - BuildNexus</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        .nexus-green { background-color: #22c55e; }
        .nexus-green:hover { background-color: #16a34a; }
        .text-nexus-green { color: #22c55e; }
        .nexus-orange { color: #f97316; }
        .text-nexus-orange { color: #f97316; }
        .sidebar-link:hover { background-color: #f3f4f6; color: #111827; }
        /* Active state for sidebar */
        .sidebar-link.active { background-color: #f3f4f6; font-weight: 600; color: #111827; border-right: 3px solid #22c55e; }
    </style>
</head>
<body class="bg-white text-gray-800 antialiased flex flex-col min-h-screen">

    <nav class="bg-white py-4 px-6 md:px-12 flex justify-between items-center shadow-sm sticky top-0 z-50 border-b border-gray-100">
        <div class="flex items-center gap-2 cursor-pointer">
            <a href="../index.php" class="flex items-center gap-2">
                <div class="w-8 h-8 rounded bg-orange-400 flex items-center justify-center">
                    <svg class="w-5 h-5 text-white" fill="currentColor" viewBox="0 0 20 20"><path d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 3.414L15.172 6.586a2 2 0 01.586 1.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4z" /></svg>
                </div>
                <span class="font-bold text-lg tracking-tight text-gray-900">BuildNexus</span>
            </a>
        </div>

        <div class="hidden md:flex gap-8 text-sm font-medium text-gray-600">
            <a href="#" class="hover:text-gray-900">Features <span class="text-xs">▼</span></a>
            <a href="../who-we-serve.php" class="hover:text-gray-900">Who We Serve</a>
            <a href="../pricing.php" class="hover:text-gray-900">Pricing</a>
            <a href="../contact.php" class="hover:text-gray-900">Contact Us</a>
        </div>

        <div class="flex items-center gap-4">
            <a href="../login.php" class="text-sm font-semibold text-gray-600 hover:text-gray-900">Login</a>
            <a href="../register.php" class="nexus-green text-white text-sm font-semibold px-4 py-2 rounded shadow hover:shadow-md transition">Get Started</a>
        </div>
    </nav>

    <div class="flex flex-col md:flex-row flex-grow max-w-7xl mx-auto w-full">
        
        <aside class="w-full md:w-64 bg-stone-50 border-r border-gray-200 py-8 hidden md:block flex-shrink-0">
            <nav class="space-y-1">
                <a href="projects.php" class="sidebar-link flex items-center gap-3 px-6 py-3 text-sm text-gray-600 transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z" /></svg>
                    Projects
                </a>
                <a href="opportunities.php" class="sidebar-link flex items-center gap-3 px-6 py-3 text-sm text-gray-600 transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" /></svg>
                    Opportunities
                </a>
                <a href="daily-logs.php" class="sidebar-link flex items-center gap-3 px-6 py-3 text-sm text-gray-600 transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" /></svg>
                    Daily Logs
                </a>
                <a href="schedule.php" class="sidebar-link flex items-center gap-3 px-6 py-3 text-sm text-gray-600 transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                    Scheduling
                </a>
                <a href="work-orders.php" class="sidebar-link active flex items-center gap-3 px-6 py-3 text-sm text-gray-600 transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                    Work Orders
                </a>
                <a href="#" class="sidebar-link flex items-center gap-3 px-6 py-3 text-sm text-gray-600 transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                    Inspections
                </a>
                <a href="#" class="sidebar-link flex items-center gap-3 px-6 py-3 text-sm text-gray-600 transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" /></svg>
                    Punchlists
                </a>
                <a href="#" class="sidebar-link flex items-center gap-3 px-6 py-3 text-sm text-gray-600 transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                    Permit Manager
                </a>
                <a href="#" class="sidebar-link flex items-center gap-3 px-6 py-3 text-sm text-gray-600 transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                    Service Tickets
                </a>
                <a href="#" class="sidebar-link flex items-center gap-3 px-6 py-3 text-sm text-gray-600 transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5.121 17.804A13.937 13.937 0 0112 16c2.5 0 4.847.655 6.879 1.804M15 10a3 3 0 11-6 0 3 3 0 016 0zm6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                    Client Portal
                </a>
                <a href="#" class="sidebar-link flex items-center gap-3 px-6 py-3 text-sm text-gray-600 transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3" /></svg>
                    To-Do's
                </a>
            </nav>
        </aside>

        <main class="flex-grow p-6 md:p-12 md:pl-20">
            
            <div class="grid md:grid-cols-2 gap-12 mb-20 items-center">
                <div>
                    <span class="nexus-orange font-semibold text-sm mb-2 block">Work Orders</span>
                    <h1 class="text-4xl md:text-5xl font-bold text-gray-900 leading-tight mb-6">
                        Perfect for small projects
                    </h1>
                    <p class="text-lg text-gray-600 mb-8 leading-relaxed">
                        Giving you the flexibility you need to work smart. Built with the flexibility to work for you not against you. Clients, Employees, Owners, they all love our work orders.
                    </p>
                </div>
                <div class="relative rounded-2xl overflow-hidden shadow-2xl">
                     <img src="https://images.unsplash.com/photo-1593642532744-d377ab507dc8?ixlib=rb-1.2.1&auto=format&fit=crop&w=800&q=80" alt="Work Orders Tablet Interface" class="w-full h-auto object-cover">
                </div>
            </div>

            <div class="grid md:grid-cols-2 gap-x-8 gap-y-12 mb-24">
                
                <div class="bg-stone-50 p-8 rounded-lg">
                    <div class="flex items-center gap-2 mb-3">
                         <svg class="w-4 h-4 text-nexus-green" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" /></svg>
                         <h4 class="font-bold text-nexus-orange text-sm">Electronic Approvals and Signatures</h4>
                    </div>
                    <p class="text-xs text-gray-500 leading-relaxed">
                        Our electronic approvals get you more work. Fully electronic approvals with signature capture. Get approvals faster and win more work, easily see status of what's Bidding, Pending, Approved, Completed, etc.
                    </p>
                </div>

                <div class="bg-stone-50 p-8 rounded-lg">
                    <div class="flex items-center gap-2 mb-3">
                         <svg class="w-4 h-4 text-nexus-green" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                         <h4 class="font-bold text-nexus-orange text-sm">Complete with Start and end Dates</h4>
                    </div>
                    <p class="text-xs text-gray-500 leading-relaxed">
                        Track Work Order dates with calendar integration and notifications. Schedule your crews or technicians with ease; view-able on calendar for easy scheduling.
                    </p>
                </div>

                <div class="bg-stone-50 p-8 rounded-lg">
                    <div class="flex items-center gap-2 mb-3">
                         <svg class="w-4 h-4 text-nexus-green" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                         <h4 class="font-bold text-nexus-orange text-sm">Invoice Straight from the Work Order</h4>
                    </div>
                    <p class="text-xs text-gray-500 leading-relaxed">
                        Friends don't let friends do double data entry. Save time, Save Clicks, Less Mistakes. Create the invoice right from the Work Order. Brilliant!
                    </p>
                </div>

                <div class="bg-stone-50 p-8 rounded-lg">
                    <div class="flex items-center gap-2 mb-3">
                         <svg class="w-4 h-4 text-nexus-green" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" /></svg>
                         <h4 class="font-bold text-nexus-orange text-sm">Assign Who's Responsible</h4>
                    </div>
                    <p class="text-xs text-gray-500 leading-relaxed">
                        Who's doing what and when? Now you know. Assign who's responsible then automatically send them a email, text etc. Notification.
                    </p>
                </div>

                <div class="bg-stone-50 p-8 rounded-lg">
                    <div class="flex items-center gap-2 mb-3">
                         <svg class="w-4 h-4 text-nexus-green" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>
                         <h4 class="font-bold text-nexus-orange text-sm">Custom Fields</h4>
                    </div>
                    <p class="text-xs text-gray-500 leading-relaxed">
                        Track the data that is important to you. Custom fields in Work Orders give you the flexibility to work the way you want to work. Track what you want to track. And all custom Fields are available for reporting.
                    </p>
                </div>

            </div>

        </main>
    </div>

    <footer class="bg-stone-50 border-t border-gray-200 pt-16 pb-8 mt-auto">
        <div class="max-w-7xl mx-auto px-6 grid grid-cols-1 md:grid-cols-4 gap-8 mb-12">
            <div>
                <div class="flex items-center gap-2 mb-4">
                    <div class="w-6 h-6 rounded bg-orange-400"></div>
                    <span class="font-bold text-gray-900">BuildNexus</span>
                </div>
                <p class="text-xs text-gray-500 leading-relaxed">
                    The complete platform for construction professionals.
                </p>
            </div>
            <div>
                <h4 class="font-bold text-sm text-gray-900 mb-4">BuildNexus</h4>
                <ul class="space-y-2 text-xs text-gray-600">
                    <li><a href="#" class="hover:text-gray-900">Features</a></li>
                    <li><a href="#" class="hover:text-gray-900">Benefits</a></li>
                    <li><a href="../contact.php" class="hover:text-gray-900">Contact</a></li>
                    <li><a href="#" class="hover:text-gray-900">Live Chat</a></li>
                    <li><a href="#" class="hover:text-gray-900">Estimate Generator</a></li>
                </ul>
            </div>
            <div>
                <h4 class="font-bold text-sm text-gray-900 mb-4">Legal</h4>
                <ul class="space-y-2 text-xs text-gray-600">
                    <li><a href="#" class="hover:text-gray-900">Privacy Policy</a></li>
                    <li><a href="#" class="hover:text-gray-900">Terms of Service</a></li>
                </ul>
            </div>
            <div>
                <h4 class="font-bold text-sm text-gray-900 mb-4">Contact</h4>
                <ul class="space-y-2 text-xs text-gray-600">
                    <li>123 Main Street</li>
                    <li>Colombo, Sri Lanka</li>
                    <li>+94 11 234 5678</li>
                </ul>
            </div>
        </div>

        <div class="max-w-7xl mx-auto px-6 pt-8 border-t border-gray-200">
            <p class="text-xs text-gray-400">
                &copy; <?php echo date("Y"); ?> BuildNexus. Developed for Tharaka Construction.
            </p>
        </div>
    </footer>

</body>
</html>