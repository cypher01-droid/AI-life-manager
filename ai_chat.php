<?php
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];

$pageTitle = 'AI Assistant';
include 'header.php';
?>

<style>
    .chat-container {
        max-width: 900px;
        margin: 0 auto;
        height: calc(100vh - 140px);
        display: flex;
        flex-direction: column;
    }
    .chat-messages {
        flex: 1;
        overflow-y: auto;
        padding: 1rem;
        background: rgba(20,20,30,0.3);
        border-radius: 24px;
        margin-bottom: 1rem;
    }
    .message {
        margin-bottom: 1rem;
        display: flex;
        align-items: flex-start;
        gap: 0.8rem;
    }
    .message.user {
        justify-content: flex-end;
    }
    .message.user .message-content {
        background: linear-gradient(145deg, #7c3aed, #4f46e5);
        color: white;
        border-radius: 20px 20px 4px 20px;
    }
    .message.assistant .message-content {
        background: rgba(30,30,40,0.8);
        backdrop-filter: blur(5px);
        border: 1px solid rgba(255,255,255,0.05);
        border-radius: 20px 20px 20px 4px;
    }
    .message-content {
        max-width: 70%;
        padding: 0.8rem 1.2rem;
        line-height: 1.5;
        word-wrap: break-word;
    }
    .message-content pre {
        background: #1e1e2e;
        padding: 0.5rem;
        border-radius: 8px;
        overflow-x: auto;
    }
    .chat-input {
        display: flex;
        gap: 0.5rem;
        background: rgba(20,20,30,0.8);
        padding: 1rem;
        border-radius: 60px;
        border: 1px solid rgba(255,255,255,0.05);
    }
    .chat-input input {
        flex: 1;
        background: transparent;
        border: none;
        padding: 0.8rem;
        color: #f0f0f0;
        font-size: 1rem;
        outline: none;
    }
    .chat-input button {
        background: #7c3aed;
        border: none;
        border-radius: 40px;
        padding: 0 1.5rem;
        color: white;
        cursor: pointer;
        transition: all 0.2s;
    }
    .chat-input button:hover {
        background: #6d28d9;
        transform: scale(1.02);
    }
    .typing-indicator {
        display: none;
        align-items: center;
        gap: 0.3rem;
        padding: 0.8rem 1.2rem;
        background: rgba(30,30,40,0.8);
        border-radius: 20px;
        width: fit-content;
    }
    .typing-indicator span {
        width: 8px;
        height: 8px;
        background: #a78bfa;
        border-radius: 50%;
        display: inline-block;
        animation: bounce 1.4s infinite ease-in-out both;
    }
    .typing-indicator span:nth-child(1) { animation-delay: -0.32s; }
    .typing-indicator span:nth-child(2) { animation-delay: -0.16s; }
    @keyframes bounce {
        0%, 80%, 100% { transform: scale(0); }
        40% { transform: scale(1); }
    }
    @media (max-width: 768px) {
        .message-content {
            max-width: 85%;
        }
    }
</style>

<div class="chat-container">
    <h1 style="margin-bottom: 1rem;">🤖 AI Assistant</h1>
    <p style="margin-bottom: 1rem; color: #9ca3af;">I can help you manage tasks, events, notes, finance, school, and goals. Just type what you want!</p>
    
    <div class="chat-messages" id="chatMessages">
        <div class="message assistant">
            <div class="message-content">
                Hello <?= htmlspecialchars($user_name) ?>! I'm your AI life manager. You can ask me to:
                <ul style="margin-top: 0.5rem; margin-left: 1rem;">
                    <li>Create tasks, events, notes, goals</li>
                    <li>Show upcoming items</li>
                    <li>Update progress on goals</li>
                    <li>Analyze your data</li>
                    <li>And much more!</li>
                </ul>
                Try something like: "Add a task to finish project by Friday" or "Show my tasks for this week".
            </div>
        </div>
    </div>
    
    <div class="chat-input">
        <input type="text" id="userInput" placeholder="Type your message..." autofocus>
        <button id="sendBtn">Send</button>
    </div>
    <div class="typing-indicator" id="typingIndicator">
        <span></span><span></span><span></span>
    </div>
</div>

<script>
    const chatMessages = document.getElementById('chatMessages');
    const userInput = document.getElementById('userInput');
    const sendBtn = document.getElementById('sendBtn');
    const typingIndicator = document.getElementById('typingIndicator');
    
    function addMessage(role, content) {
        const messageDiv = document.createElement('div');
        messageDiv.className = `message ${role}`;
        const contentDiv = document.createElement('div');
        contentDiv.className = 'message-content';
        // Convert markdown-like formatting (simple)
        contentDiv.innerHTML = content.replace(/\n/g, '<br>');
        messageDiv.appendChild(contentDiv);
        chatMessages.appendChild(messageDiv);
        chatMessages.scrollTop = chatMessages.scrollHeight;
    }
    
    async function sendMessage() {
        const message = userInput.value.trim();
        if (!message) return;
        
        addMessage('user', message);
        userInput.value = '';
        
        typingIndicator.style.display = 'flex';
        sendBtn.disabled = true;
        
        try {
            const response = await fetch('ai_api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ message: message })
            });
            const data = await response.json();
            typingIndicator.style.display = 'none';
            
            if (data.success) {
                addMessage('assistant', data.response);
            } else {
                addMessage('assistant', 'Sorry, I encountered an error: ' + (data.error || 'Unknown'));
            }
        } catch (error) {
            typingIndicator.style.display = 'none';
            addMessage('assistant', 'Network error. Please try again.');
        } finally {
            sendBtn.disabled = false;
            userInput.focus();
        }
    }
    
    sendBtn.addEventListener('click', sendMessage);
    userInput.addEventListener('keypress', (e) => {
        if (e.key === 'Enter') sendMessage();
    });
</script>

<?php include 'footer.php'; ?>