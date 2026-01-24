<?php
require_once 'config/main_config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: login.php');
    exit();
}

$username = $_SESSION['username'];
$email = $_SESSION['email'];
$user_id = $_SESSION['user_id'];

// Get stats (using a simpler query since chatbot conversations are removed)
$stats_query = "SELECT 
    COUNT(id) as total_users,
    COUNT(CASE WHEN status = 'active' THEN 1 END) as active_users,
    COUNT(CASE WHEN DATE(created_at) = CURDATE() THEN 1 END) as today_registrations
    FROM users";
$stmt = $conn->prepare($stats_query);
$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc();
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Auth System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .sidebar-hidden {
            transform: translateX(-100%);
        }
    </style>
</head>
<body class="bg-gray-50 min-h-screen">
    <!-- Top Bar -->
    <nav class="bg-white shadow-lg fixed w-full top-0 z-50">
        <div class="px-4 py-3 flex items-center justify-between">
            <div class="flex items-center space-x-4">
                <!-- Mobile Menu Toggle -->
                <button onclick="toggleSidebar()" class="lg:hidden text-gray-600 hover:text-gray-800 focus:outline-none">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                    </svg>
                </button>
                
                <!-- Site Icon and Name -->
                <div class="flex items-center space-x-3">
                    <div class="w-10 h-10 bg-gradient-to-br from-blue-500 to-purple-600 rounded-lg flex items-center justify-center">
                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                        </svg>
                    </div>
                    <span class="text-xl font-bold text-gray-800 hidden sm:block">BitChat Dashboard</span>
                </div>
            </div>

            <!-- Right Side - User Info and Logout -->
            <div class="flex items-center space-x-4">
                <div class="hidden md:block text-right">
                    <p class="text-sm font-semibold text-gray-800"><?php echo htmlspecialchars($username); ?></p>
                    <p class="text-xs text-gray-500"><?php echo htmlspecialchars($email); ?></p>
                </div>
                <button 
                    onclick="logout()"
                    class="bg-red-500 hover:bg-red-600 text-white font-semibold px-4 py-2 rounded-lg transition duration-200 flex items-center space-x-2"
                >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
                    </svg>
                    <span>Logout</span>
                </button>
            </div>
        </div>
    </nav>

    <div class="flex pt-16">
        <!-- Sidebar -->
        <aside id="sidebar" class="bg-gradient-to-b from-gray-800 to-gray-900 text-white w-64 min-h-screen fixed lg:static transition-transform duration-300 ease-in-out z-40">
            <div class="p-6">
                <div class="mb-8 pb-6 border-b border-gray-700">
                    <div class="flex items-center space-x-3">
                        <div class="w-12 h-12 bg-gradient-to-br from-blue-400 to-purple-500 rounded-full flex items-center justify-center text-xl font-bold">
                            <?php echo strtoupper(substr($username, 0, 1)); ?>
                        </div>
                        <div>
                            <p class="font-semibold"><?php echo htmlspecialchars($username); ?></p>
                            
                        </div>
                    </div>
                </div>

                <nav class="space-y-2">
                    <a href="#chatwidget" onclick="showSection('chatwidget')" id="nav-chatwidget" class="nav-link flex items-center space-x-3 px-4 py-3 rounded-lg hover:bg-gray-700 transition duration-200">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8h2a2 2 0 012 2v6a2 2 0 01-2 2h-2v4l-4-4H9a1.994 1.994 0 01-1.414-.586m0 0L11 14h4a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2v4l.586-.586z"></path>
                        </svg>
                        <span class="font-medium">Chat Widget</span>
                    </a>
                    
                    <a href="#chatconversation" onclick="showSection('chatconversation')" id="nav-chatconversation" class="nav-link flex items-center space-x-3 px-4 py-3 rounded-lg hover:bg-gray-700 transition duration-200">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
                        </svg>
                        <span class="font-medium">Chat Conversation</span>
                    </a>
                </nav>
            </div>
        </aside>

        <!-- Overlay for mobile -->
        <div id="overlay" class="fixed inset-0 bg-black bg-opacity-50 z-30 lg:hidden hidden" onclick="toggleSidebar()"></div>

        <!-- Main Content -->
        <main class="flex-1 p-6 lg:p-8">

            <!-- Chat Widget Section -->
            <section id="chatwidget-section" class="section-content">
                <div class="mb-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <h1 class="text-3xl font-bold text-gray-800">Widget Management</h1>
                            <p class="text-gray-600 mt-2">Manage your chat widget</p>
                        </div>
                        <button id="createWidgetBtn" onclick="checkWidgetLimit()" class="bg-gradient-to-r from-blue-500 to-purple-600 text-white px-6 py-2 rounded-lg font-semibold hover:opacity-90 transition">
                            + Create New Widget
                        </button>
                    </div>
                </div>

                <!-- Widgets Table -->
                <div class="bg-white rounded-xl shadow-md overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h2 class="text-lg font-semibold text-gray-900">Your Widget</h2>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Client Name</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Widget ID</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Webhook URL</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Created</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="widgetsTableBody" class="bg-white divide-y divide-gray-200">
                                <tr>
                                    <td colspan="6" class="px-6 py-8 text-center text-gray-500">
                                        Loading widgets...
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>

            <!-- Chat Conversation Section -->
            <section id="chatconversation-section" class="section-content hidden">
                <div class="mb-6">
                    <h1 class="text-3xl font-bold text-gray-800">Conversation Management</h1>                    
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <!-- Conversation List -->
                    <div class="lg:col-span-1 bg-white rounded-xl shadow-md overflow-hidden">
                        <div class="p-4 bg-gradient-to-r from-blue-500 to-purple-600 text-white">
                            <h3 class="font-semibold">Active Conversations</h3>
                        </div>
                        <div class="divide-y divide-gray-200">
                            <div class="p-4 hover:bg-gray-50 cursor-pointer transition">
                                <div class="flex items-center space-x-3">
                                    <div class="w-10 h-10 bg-blue-500 rounded-full flex items-center justify-center text-white font-semibold">
                                        C
                                    </div>
                                    <div class="flex-1">
                                        <p class="font-medium text-gray-800">Sample Conversation 1</p>
                                        <p class="text-sm text-gray-500 truncate">Sample Company ABC</p>
                                    </div>
                                </div>
                            </div>
                            <div class="p-4 hover:bg-gray-50 cursor-pointer transition">
                                <div class="flex items-center space-x-3">
                                    <div class="w-10 h-10 bg-purple-500 rounded-full flex items-center justify-center text-white font-semibold">
                                        D
                                    </div>
                                    <div class="flex-1">
                                        <p class="font-medium text-gray-800">Demo Conversation 2</p>
                                        <p class="text-sm text-gray-500 truncate">Demo Corporation XYZ</p>
                                    </div>
                                </div>
                            </div>
                            <div class="p-4 hover:bg-gray-50 cursor-pointer transition">
                                <div class="flex items-center space-x-3">
                                    <div class="w-10 h-10 bg-green-500 rounded-full flex items-center justify-center text-white font-semibold">
                                        T
                                    </div>
                                    <div class="flex-1">
                                        <p class="font-medium text-gray-800">Test Conversation 3</p>
                                        <p class="text-sm text-gray-500 truncate">Test Client 123</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Chat Conversation Preview -->
                    <div class="lg:col-span-2 bg-white rounded-xl shadow-md flex flex-col" style="height: 600px;">
                        <div class="p-4 bg-gradient-to-r from-blue-500 to-purple-600 text-white rounded-t-xl">
                            <div class="flex items-center space-x-3">
                                <div class="w-10 h-10 bg-white bg-opacity-20 rounded-full flex items-center justify-center font-semibold">
                                    C
                                </div>
                                <div>
                                    <p class="font-semibold">Conversation Preview</p>
                                    <p class="text-xs text-blue-100">Active</p>
                                </div>
                            </div>
                        </div>

                        <div class="flex-1 p-4 overflow-y-auto space-y-4">
                            <div class="flex">
                                <div class="bg-gray-100 rounded-lg rounded-tl-none px-4 py-2 max-w-xs">
                                    <p class="text-sm text-gray-800">Welcome to our chat! How can we assist you today?</p>
                                    <p class="text-xs text-gray-500 mt-1">10:30 AM</p>
                                </div>
                            </div>
                            <div class="flex justify-end">
                                <div class="bg-blue-500 text-white rounded-lg rounded-tr-none px-4 py-2 max-w-xs">
                                    <p class="text-sm">Hello! I have a question about your services.</p>
                                    <p class="text-xs text-blue-100 mt-1">10:31 AM</p>
                                </div>
                            </div>
                            <div class="flex">
                                <div class="bg-gray-100 rounded-lg rounded-tl-none px-4 py-2 max-w-xs">
                                    <p class="text-sm text-gray-800">Sure, I'd be happy to help. What do you need to know?</p>
                                    <p class="text-xs text-gray-500 mt-1">10:32 AM</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </main>
    </div>

    <!-- Create Widget Modal -->
    <div id="createModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-2xl mx-4">
            <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
                <h3 class="text-xl font-semibold text-gray-900">Create New Widget</h3>
                <button onclick="hideCreateModal()" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
            <form id="createWidgetForm" class="p-6 space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Client Name</label>
                    <input type="text" id="clientName" required 
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                           placeholder="e.g., ABC Company">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">n8n Webhook URL</label>
                    <input type="url" id="webhookUrl" required 
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                           placeholder="https://n8n.example.com/webhook/...">
                    <p class="text-xs text-gray-500 mt-1">Enter the n8n webhook URL for this client</p>
                </div>
                <div class="flex justify-end gap-3 pt-4">
                    <button type="button" onclick="hideCreateModal()" 
                            class="px-6 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition">
                        Cancel
                    </button>
                    <button type="submit" 
                            class="px-6 py-2 bg-gradient-to-r from-blue-500 to-purple-600 text-white rounded-lg hover:opacity-90 transition">
                        Create Widget
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Shortcode Modal -->
    <div id="shortcodeModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-2xl mx-4">
            <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
                <h3 class="text-xl font-semibold text-gray-900">Widget Shortcode</h3>
                <button onclick="hideShortcodeModal()" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
            <div class="p-6">
                <p class="text-gray-600 mb-4">Copy this shortcode and paste it in your website's HTML, just before the closing &lt;/body&gt; tag:</p>
                <div class="bg-gray-50 border border-gray-200 rounded-lg p-4 mb-4">
                    <code id="shortcodeText" class="text-sm text-gray-800 break-all"></code>
                </div>
                <button onclick="copyShortcode()" 
                        class="w-full px-6 py-3 bg-gradient-to-r from-blue-500 to-purple-600 text-white rounded-lg hover:opacity-90 transition font-semibold">
                    📋 Copy to Clipboard
                </button>
            </div>
        </div>
    </div>

    <script>
        // API Base URL
        const API_BASE = 'api';

        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('overlay');
            
            sidebar.classList.toggle('sidebar-hidden');
            overlay.classList.toggle('hidden');
        }

        function showSection(section) {
            document.querySelectorAll('.section-content').forEach(s => {
                s.classList.add('hidden');
            });
            
            document.getElementById(section + '-section').classList.remove('hidden');
            
            document.querySelectorAll('.nav-link').forEach(link => {
                link.classList.remove('bg-blue-600', 'bg-blue-700');
                link.classList.add('hover:bg-gray-700');
            });
            
            const activeLink = document.getElementById('nav-' + section);
            activeLink.classList.add('bg-blue-600');
            activeLink.classList.remove('hover:bg-gray-700');
            
            if (window.innerWidth < 1024) {
                toggleSidebar();
            }
            
            // Load widgets when chatwidget section is shown
            if (section === 'chatwidget') {
                loadWidgets();
            }
        }

        function logout() {
            if (confirm('Kya aap logout karna chahte hain?')) {
                window.location.href = 'logout.php';
            }
        }

        // Widget Management Functions
        document.addEventListener('DOMContentLoaded', () => {
            // Set default section to chatwidget
            showSection('chatwidget');
            
            // Form submission handler
            document.getElementById('createWidgetForm')?.addEventListener('submit', createWidget);
        });

        async function checkWidgetLimit() {
            try {
                const response = await fetch(`${API_BASE}/get-widgets.php`);
                const data = await response.json();

                if (data.success && data.widgets && data.widgets.length > 0) {
                    alert('You already have a widget created. Only one widget is allowed per user.');
                    return;
                }
                
                // If no widgets exist, show create modal
                showCreateModal();
            } catch (error) {
                console.error('Error checking widget limit:', error);
                // Still allow creation in case of error
                showCreateModal();
            }
        }

        function showCreateModal() {
            // Check limit again before showing modal
            checkExistingWidgets().then(hasWidget => {
                if (hasWidget) {
                    alert('You already have a widget created. Only one widget is allowed per user.');
                    return;
                }
                document.getElementById('createModal').classList.remove('hidden');
            });
        }

        async function checkExistingWidgets() {
            try {
                const response = await fetch(`${API_BASE}/get-widgets.php`);
                const data = await response.json();
                return data.success && data.widgets && data.widgets.length > 0;
            } catch (error) {
                console.error('Error checking existing widgets:', error);
                return false;
            }
        }

        function hideCreateModal() {
            document.getElementById('createModal').classList.add('hidden');
        }

        function showShortcode(shortcode) {
            document.getElementById('shortcodeText').textContent = shortcode;
            document.getElementById('shortcodeModal').classList.remove('hidden');
        }

        function hideShortcodeModal() {
            document.getElementById('shortcodeModal').classList.add('hidden');
        }

        function copyShortcode() {
            const shortcode = document.getElementById('shortcodeText').textContent;
            navigator.clipboard.writeText(shortcode).then(() => {
                alert('Shortcode copied to clipboard!');
            }).catch(() => {
                // Fallback for older browsers
                const textArea = document.createElement('textarea');
                textArea.value = shortcode;
                document.body.appendChild(textArea);
                textArea.select();
                document.execCommand('copy');
                document.body.removeChild(textArea);
                alert('Shortcode copied to clipboard!');
            });
        }

        async function createWidget(e) {
            e.preventDefault();
            
            // Double-check widget limit before creation
            const hasWidget = await checkExistingWidgets();
            if (hasWidget) {
                alert('You already have a widget created. Only one widget is allowed per user.');
                hideCreateModal();
                loadWidgets(); // Refresh the table
                return;
            }
            
            const clientName = document.getElementById('clientName').value;
            const webhookUrl = document.getElementById('webhookUrl').value;

            try {
                const response = await fetch(`${API_BASE}/create-widget.php`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        clientName: clientName,
                        webhookUrl: webhookUrl
                    })
                });

                const data = await response.json();

                if (data.success) {
                    hideCreateModal();
                    showShortcode(data.shortcode);
                    loadWidgets();
                    
                    // Reset form
                    document.getElementById('createWidgetForm').reset();
                    
                    // Disable create button since limit reached
                    const createBtn = document.getElementById('createWidgetBtn');
                    createBtn.disabled = true;
                    createBtn.classList.add('opacity-50', 'cursor-not-allowed');
                    createBtn.textContent = 'Widget Created';
                    
                    alert('Widget created successfully! You have reached the maximum limit of 1 widget.');
                } else {
                    alert('Error: ' + (data.error || 'Unknown error'));
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Failed to create widget. Check console for details.');
            }
        }

        async function loadWidgets() {
            try {
                const response = await fetch(`${API_BASE}/get-widgets.php`);
                const data = await response.json();

                if (data.success) {
                    // Update button state based on widget existence
                    const createBtn = document.getElementById('createWidgetBtn');
                    if (data.widgets && data.widgets.length > 0) {
                        createBtn.disabled = true;
                        createBtn.classList.add('opacity-50', 'cursor-not-allowed');
                        createBtn.textContent = 'Widget Created';
                    } else {
                        createBtn.disabled = false;
                        createBtn.classList.remove('opacity-50', 'cursor-not-allowed');
                        createBtn.textContent = '+ Create New Widget';
                    }
                    
                    populateTable(data.widgets);
                } else {
                    console.error('Error loading widgets:', data.error);
                    showEmptyState();
                }
            } catch (error) {
                console.error('Error:', error);
                showEmptyState();
            }
        }

        function updateStats(stats) {
            // Stats cards removed - single widget only
        }

        function populateTable(widgets) {
            const tbody = document.getElementById('widgetsTableBody');
            
            if (!widgets || widgets.length === 0) {
                showEmptyState();
                return;
            }

            tbody.innerHTML = widgets.map(widget => `
                <tr>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="text-sm font-medium text-gray-900">${escapeHtml(widget.client_name)}</div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="text-sm text-gray-500 font-mono" title="${escapeHtml(widget.widget_id)}">${escapeHtml(widget.widget_id).length > 5 ? escapeHtml(widget.widget_id).substring(0, 5) + '...' : escapeHtml(widget.widget_id)}</div>
                    </td>
                    <td class="px-6 py-4">
                        <div class="text-sm text-gray-500 truncate max-w-xs" title="${escapeHtml(widget.webhook_url)}">
                            ${escapeHtml(widget.webhook_url).length > 20 ? escapeHtml(widget.webhook_url).substring(0, 20) + '...' : escapeHtml(widget.webhook_url)}
                        </div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full ${
                            widget.status === 'active' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'
                        }">
                            ${widget.status}
                        </span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                        ${formatDate(widget.created_at)}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium space-x-2">
                        <button onclick="viewShortcode('${escapeHtml(widget.widget_id)}')" 
                                class="text-indigo-600 hover:text-indigo-900 text-xs px-2 py-1 rounded border border-indigo-200 hover:bg-indigo-50">Code</button>
                        <button onclick="toggleStatus('${escapeHtml(widget.widget_id)}', '${widget.status}')" 
                                class="text-yellow-600 hover:text-yellow-900 text-xs px-2 py-1 rounded border border-yellow-200 hover:bg-yellow-50">
                            ${widget.status === 'active' ? 'Deact' : 'Act'}
                        </button>
                        <button onclick="deleteWidget('${escapeHtml(widget.widget_id)}')" 
                                class="text-red-600 hover:text-red-900 text-xs px-2 py-1 rounded border border-red-200 hover:bg-red-50">Del</button>
                    </td>
                </tr>
            `).join('');
        }

        function showEmptyState() {
            const tbody = document.getElementById('widgetsTableBody');
            tbody.innerHTML = `
                <tr>
                    <td colspan="6" class="px-6 py-8 text-center text-gray-500">
                        No widgets yet. Create your first widget to get started!
                    </td>
                </tr>
            `;
            
            updateStats({ total: 0, active: 0, thisMonth: 0 });
        }

        function viewShortcode(widgetId) {
            const shortcode = `<script src="bitchat-widget.js" data-widget-id="${widgetId}"><\/script>`;
            showShortcode(shortcode);
        }

        async function toggleStatus(widgetId, currentStatus) {
            const newStatus = currentStatus === 'active' ? 'inactive' : 'active';
            
            try {
                const response = await fetch(`${API_BASE}/update-widget.php`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        widgetId: widgetId,
                        status: newStatus
                    })
                });

                const data = await response.json();

                if (data.success) {
                    loadWidgets();
                    alert(`Widget ${newStatus === 'active' ? 'activated' : 'deactivated'} successfully!`);
                } else {
                    alert('Error: ' + (data.error || 'Unknown error'));
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Failed to update widget status');
            }
        }

        async function deleteWidget(widgetId) {
            if (!confirm('Are you sure you want to delete this widget? This action cannot be undone.')) {
                return;
            }

            try {
                const response = await fetch(`${API_BASE}/delete-widget.php`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        widgetId: widgetId
                    })
                });

                const data = await response.json();

                if (data.success) {
                    loadWidgets(); // This will re-enable the create button
                    alert('Widget deleted successfully!');
                } else {
                    alert('Error: ' + (data.error || 'Unknown error'));
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Failed to delete widget');
            }
        }

        // Helper functions
        function escapeHtml(text) {
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return text.replace(/[&<>"']/g, m => map[m]);
        }

        function formatDate(dateString) {
            const date = new Date(dateString);
            return date.toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'short',
                day: 'numeric'
            });
        }

        // Search functionality
        document.getElementById('searchConversation')?.addEventListener('keyup', function(e) {
            const searchTerm = e.target.value.toLowerCase();
            const rows = document.querySelectorAll('tbody tr');
            
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(searchTerm) ? '' : 'none';
            });
        });
    </script>
</body>
</html>