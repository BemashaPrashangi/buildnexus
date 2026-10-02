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
    <title>To-Do's - BuildNexus</title>
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
                <a href="work-orders.php" class="sidebar-link flex items-center gap-3 px-6 py-3 text-sm text-gray-600 transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                    Work Orders
                </a>
                <a href="inspections.php" class="sidebar-link flex items-center gap-3 px-6 py-3 text-sm text-gray-600 transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                    Inspections
                </a>
                <a href="punchlists.php" class="sidebar-link flex items-center gap-3 px-6 py-3 text-sm text-gray-600 transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" /></svg>
                    Punchlists
                </a>
                <a href="permit-manager.php" class="sidebar-link flex items-center gap-3 px-6 py-3 text-sm text-gray-600 transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                    Permit Manager
                </a>
                <a href="service-tickets.php" class="sidebar-link flex items-center gap-3 px-6 py-3 text-sm text-gray-600 transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                    Service Tickets
                </a>
                <a href="#" class="sidebar-link flex items-center gap-3 px-6 py-3 text-sm text-gray-600 transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5.121 17.804A13.937 13.937 0 0112 16c2.5 0 4.847.655 6.879 1.804M15 10a3 3 0 11-6 0 3 3 0 016 0zm6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                    Client Portal
                </a>
                <a href="to-dos.php" class="sidebar-link active flex items-center gap-3 px-6 py-3 text-sm text-gray-600 transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3" /></svg>
                    To-Do's
                </a>
            </nav>
        </aside>

        <main class="flex-grow p-6 md:p-12 md:pl-20">
            
            <div class="grid md:grid-cols-2 gap-12 mb-20 items-center">
                <div>
                    <span class="nexus-orange font-semibold text-sm mb-2 block">To-Do's</span>
                    <h1 class="text-4xl md:text-5xl font-bold text-gray-900 leading-tight mb-6">
                        Keep the ball rolling
                    </h1>
                    <p class="text-lg text-gray-600 mb-8 leading-relaxed">
                        To Do's are a great way to let your team members and clients know what is required from them. They can easily check the item once the task has been completed.
                    </p>
                </div>
                <div class="relative rounded-2xl overflow-hidden shadow-2xl">
                     <img src="https://images.unsplash.com/photo-1593642532744-d377ab507dc8?ixlib=rb-1.2.1&auto=format&fit=crop&w=800&q=80" alt="To Do List App" class="w-full h-auto object-cover">
                </div>
            </div>

            <div class="grid md:grid-cols-2 gap-x-8 gap-y-12 mb-24">
                
                <div class="bg-stone-50 p-8 rounded-lg">
                    <div class="flex items-center gap-2 mb-3">
                         <svg class="w-4 h-4 text-nexus-green" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                         <h4 class="font-bold text-nexus-orange text-sm">Task Management</h4>
                    </div>
                    <h3 class="font-bold text-lg text-gray-900 mb-2">Keep the ball rolling</h3>
                    <p class="text-xs text-gray-600 leading-relaxed">
                        To Do's are a great way to let your employees know what is required from them. They can easily check the item once the task has been completed.
                    </p>
                </div>

                <div class="bg-stone-50 p-8 rounded-lg">
                    <div class="flex items-center gap-2 mb-3">
                         <svg class="w-4 h-4 text-nexus-green" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                         <h4 class="font-bold text-nexus-orange text-sm">Custom Fields</h4>
                    </div>
                    <h3 class="font-bold text-lg text-gray-900 mb-2">Track the data that is important to you</h3>
                    <p class="text-xs text-gray-600 leading-relaxed">
                        To Do's do a lot! Do even more with custom fields. We give you the power to drive the data and info you need to succeed.
                    </p>
                </div>

                <div class="bg-stone-50 p-8 rounded-lg">
                    <div class="flex items-center gap-2 mb-3">
                         <svg class="w-4 h-4 text-nexus-green" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" /></svg>
                         <h4 class="font-bold text-nexus-orange text-sm">Manage from Phone</h4>
                    </div>
                    <h3 class="font-bold text-lg text-gray-900 mb-2">Views and filters to keep the ball rolling</h3>
                    <p class="text-xs text-gray-600 leading-relaxed">
                        Access, update, and manage your To-Do lists directly from your phone. Filter tasks by status, priority, or assignee to stay organized on the go.
                    </p>
                </div>

                <div class="bg-stone-50 p-8 rounded-lg">
                    <div class="flex items-center gap-2 mb-3">
                         <svg class="w-4 h-4 text-nexus-green" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" /></svg>
                         <h4 class="font-bold text-nexus-orange text-sm">Notifications of Completed Tasks</h4>
                    </div>
                    <h3 class="font-bold text-lg text-gray-900 mb-2">Tasks Complete Notifications</h3>
                    <p class="text-xs text-gray-600 leading-relaxed">
                        Our real-time tasks complete notifications keeps you from wondering if a task has been completed. Instantly know when it has been completed and what remains to be completed.
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