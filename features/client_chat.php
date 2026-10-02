<?php
require_once '../db.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Client') {
    header("Location: ../login.php");
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
    <title>Chat - BuildNexus</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #fcfcfc; padding: 3rem 1.5rem; }
        .chat-container { max-width: 700px; margin: 0 auto; }
        .nexus-chat-card { background: #fff; border: 1px solid #eee; border-radius: 12px; height: 650px; display: flex; flex-direction: column; }
        .chat-inner-header { padding: 1rem 1.5rem; border-bottom: 1px solid #f8fafc; display: flex; justify-content: space-between; align-items: center; }
        .chat-history { flex-grow: 1; overflow-y: auto; padding: 1.5rem; display: flex; flex-direction: column; gap: 1rem; }
        
        /* Toggle Styling */
        .form-check-input:checked { background-color: #16a34a; border-color: #16a34a; }
        
        /* Message Bubbles */
        .msg-assistant { align-self: flex-start; background: #f1f5f9; padding: 10px 15px; border-radius: 12px; max-width: 80%; font-size: 0.9rem; }
        .msg-client { align-self: flex-end; background: #16a34a; color: #fff; padding: 10px 15px; border-radius: 12px; max-width: 80%; font-size: 0.9rem; }
        .msg-pm { align-self: flex-start; background: #eff6ff; border: 1px solid #bfdbfe; padding: 10px 15px; border-radius: 12px; max-width: 80%; font-size: 0.9rem; }

        .chat-footer { padding: 1.5rem; border-top: 1px solid #f8fafc; }
        .btn-send { background-color: #16a34a; color: #fff; border: none; border-radius: 8px; width: 42px; height: 42px; }
    </style>
</head>
<body>

<div class="chat-container text-center">
    <h1 class="fw-extrabold mb-1">BuildNexus Chat</h1>
    <p class="text-muted mb-4">Connect with our Virtual Assistant or your Project Manager.</p>

    <div class="nexus-chat-card text-start">
        <div class="chat-inner-header">
            <div class="d-flex align-items-center gap-2">
                <i class="bi bi-robot fs-5" id="headerIcon"></i>
                <h5 class="mb-0" id="headerTitle">Virtual Assistant</h5>
            </div>
            <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" id="chatToggle" onchange="toggleChatMode()">
                <label class="form-check-label small fw-bold" for="chatToggle">Talk to PM</label>
            </div>
        </div>

        <div class="chat-history" id="chatHistory">
            <div class="msg-assistant">Hello! I am your BuildNexus Assistant. How can I help you today?</div>
        </div>

        <div class="chat-footer">
            <div class="d-flex gap-2">
                <input type="text" id="userInput" class="form-control" placeholder="Type your message..." onkeydown="if(event.key === 'Enter'){ event.preventDefault(); handleSendMessage(); }">
                <button class="btn-send" onclick="handleSendMessage()"><i class="bi bi-send-fill"></i></button>
            </div>
        </div>
    </div>
</div>

<script>
    let isHumanMode = false;

    function toggleChatMode() {
        isHumanMode = document.getElementById('chatToggle').checked;
        const history = document.getElementById('chatHistory');
        const title = document.getElementById('headerTitle');
        const icon = document.getElementById('headerIcon');

        history.innerHTML = ''; // Clear for mode switch
        if (isHumanMode) {
            title.innerText = 'Project Manager';
            icon.className = 'bi bi-person-circle fs-5';
            loadHumanChat();
        } else {
            title.innerText = 'Virtual Assistant';
            icon.className = 'bi bi-robot fs-5';
            history.innerHTML = '<div class="msg-assistant">Hello! I am your BuildNexus Assistant. How can I help you today?</div>';
        }
    }

    function formatChatText(text) {
        if (!text) return '';
        // Basic HTML entity escaping
        let div = document.createElement('div');
        div.textContent = text;
        let escaped = div.innerHTML;

        // Parse markdown bold **text** and bullet points
        escaped = escaped.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
        escaped = escaped.replace(/\*(.*?)\*/g, '<em>$1</em>');
        escaped = escaped.replace(/\n/g, '<br>');
        return escaped;
    }

    async function handleSendMessage() {
        const input = document.getElementById('userInput');
        const message = input.value.trim();
        if (!message) return;

        appendMessage(message, 'msg-client', false);
        input.value = '';

        if (!isHumanMode) {
            // Send to Assistant Logic
            try {
                const res = await fetch('chat_logic.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({'message': message})
                });
                const data = await res.json();
                appendMessage(data.reply || "I didn't quite get that.", 'msg-assistant', true);
            } catch (err) {
                appendMessage("Sorry, I'm currently having trouble connecting. Please try again.", 'msg-assistant', false);
            }
        } else {
            // Send to DB for Human PM
            try {
                await fetch('send_human_message.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({'message': message})
                });
            } catch (err) {
                console.error(err);
            }
        }
    }

    function appendMessage(text, className, isRich = false) {
        const history = document.getElementById('chatHistory');
        const msgDiv = document.createElement('div');
        msgDiv.className = className;
        if (isRich || className === 'msg-assistant') {
            msgDiv.innerHTML = formatChatText(text);
        } else {
            msgDiv.textContent = text;
        }
        history.appendChild(msgDiv);
        history.scrollTop = history.scrollHeight;
    }

    async function loadHumanChat() {
        try {
            const res = await fetch('get_chat_history.php');
            const messages = await res.json();
            if (Array.isArray(messages)) {
                messages.forEach(m => {
                    const cls = m.sender_role === 'Client' ? 'msg-client' : 'msg-pm';
                    appendMessage(m.message_text, cls, true);
                });
            }
        } catch (e) {
            console.warn('Could not load human chat history:', e);
        }
    }
</script>
</body>
</html>