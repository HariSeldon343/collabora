<?php
session_start();
require_once 'config_v2.php';
require_once 'includes/SimpleAuth.php';

// Check if user is logged in
$auth = new SimpleAuth();
if (!$auth->isAuthenticated()) {
    header('Location: index_v2.php?next=' . urlencode($_SERVER['REQUEST_URI']));
    exit();
}

$user = $auth->getCurrentUser();
$currentTenant = $auth->getCurrentTenant();
$userRole = $user['role'] ?? 'standard_user';
$userId = $user['id'];
$currentTenantId = $currentTenant['id'] ?? null;
$userName = $user['name'] ?? 'User';
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chat - Nexio Solution</title>
    <link rel="stylesheet" href="assets/css/styles.css">
    <link rel="stylesheet" href="assets/css/chat.css">
</head>
<body>
    <div class="app-layout">
        <!-- Sidebar -->
        <?php include 'components/sidebar.php'; ?>

        <!-- Main Wrapper -->
        <div class="main-wrapper">
            <!-- Header -->
            <?php include 'components/header.php'; ?>

            <!-- Main Content -->
            <main class="main-content">
                <!-- Page Header -->
                <div class="page-header">
                    <h1 class="page-title">Chat</h1>
                </div>

                <!-- Chat Container -->
                <div class="chat-container">
            <!-- Channels Sidebar -->
            <div class="channels-sidebar" id="channelsSidebar">
                <div class="channels-header">
                    <h2>Channels</h2>
                    <button class="btn-icon" id="newChannelBtn" title="Create new channel">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                        </svg>
                    </button>
                </div>

                <div class="channels-search">
                    <input type="text" placeholder="Search channels..." id="channelSearch" class="channel-search-input">
                </div>

                <!-- Public Channels -->
                <div class="channel-section">
                    <div class="section-header">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                        </svg>
                        <span>Public Channels</span>
                    </div>
                    <div class="channel-list" id="publicChannels">
                        <!-- Channels will be loaded dynamically -->
                    </div>
                </div>

                <!-- Private Channels -->
                <div class="channel-section">
                    <div class="section-header">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                        </svg>
                        <span>Private Channels</span>
                    </div>
                    <div class="channel-list" id="privateChannels">
                        <!-- Channels will be loaded dynamically -->
                    </div>
                </div>

                <!-- Direct Messages -->
                <div class="channel-section">
                    <div class="section-header">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                        </svg>
                        <span>Direct Messages</span>
                    </div>
                    <div class="channel-list" id="directMessages">
                        <!-- DMs will be loaded dynamically -->
                    </div>
                </div>
            </div>

            <!-- Main Chat Area -->
            <div class="chat-main">
                <!-- Chat Header -->
                <div class="chat-header">
                    <div class="chat-header-left">
                        <button class="btn-icon mobile-channels-toggle" id="mobileChannelsToggle">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                            </svg>
                        </button>
                        <div class="channel-info">
                            <h3 id="currentChannelName"># general</h3>
                            <p id="currentChannelDescription">General discussion</p>
                        </div>
                    </div>
                    <div class="chat-header-right">
                        <button class="btn-icon" id="channelInfoBtn" title="Channel info">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z" />
                            </svg>
                        </button>
                        <button class="btn-icon" id="toggleMembersBtn" title="Toggle members">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z" />
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- Messages Area -->
                <div class="messages-container" id="messagesContainer">
                    <div class="messages-list" id="messagesList">
                        <!-- Messages will be loaded dynamically -->
                        <div class="loading-messages">
                            <div class="spinner"></div>
                            <p>Loading messages...</p>
                        </div>
                    </div>

                    <!-- Typing Indicators -->
                    <div class="typing-indicator hidden" id="typingIndicator">
                        <span class="typing-dots">
                            <span></span>
                            <span></span>
                            <span></span>
                        </span>
                        <span class="typing-text">Someone is typing...</span>
                    </div>
                </div>

                <!-- Message Input -->
                <div class="message-input-container">
                    <div class="message-input-wrapper">
                        <button class="btn-icon" id="attachmentBtn" title="Attach file">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M18.375 12.739l-7.693 7.693a4.5 4.5 0 01-6.364-6.364l10.94-10.94A3 3 0 1119.5 7.372L8.552 18.32m.009-.01l-.01.01m5.699-9.941l-7.81 7.81a1.5 1.5 0 002.112 2.13" />
                            </svg>
                        </button>
                        <div class="input-wrapper">
                            <textarea
                                id="messageInput"
                                class="message-input"
                                placeholder="Type a message... (@ to mention, : for emoji)"
                                rows="1"
                            ></textarea>
                            <div class="mention-autocomplete hidden" id="mentionAutocomplete">
                                <!-- Mention suggestions will appear here -->
                            </div>
                        </div>
                        <button class="btn-icon" id="emojiBtn" title="Add emoji">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.182 15.182a4.5 4.5 0 01-6.364 0M21 12a9 9 0 11-18 0 9 9 0 0118 0zM9.75 9.75c0 .414-.168.75-.375.75S9 10.164 9 9.75 9.168 9 9.375 9s.375.336.375.75zm-.375 0h.008v.015h-.008V9.75zm5.625 0c0 .414-.168.75-.375.75s-.375-.336-.375-.75.168-.75.375-.75.375.336.375.75zm-.375 0h.008v.015h-.008V9.75z" />
                            </svg>
                        </button>
                        <button class="btn-primary send-btn" id="sendBtn" title="Send message (Enter)">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 12L3.269 3.126A59.768 59.768 0 0121.485 12 59.77 59.77 0 013.27 20.876L5.999 12zm0 0h7.5" />
                            </svg>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Members Sidebar (Optional) -->
            <div class="members-sidebar hidden" id="membersSidebar">
                <div class="members-header">
                    <h3>Members</h3>
                    <button class="btn-icon" id="closeMembersBtn">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
                <div class="members-list" id="membersList">
                    <!-- Members will be loaded dynamically -->
                </div>
            </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Emoji Picker Modal -->
    <div class="emoji-picker-modal hidden" id="emojiPicker">
        <div class="emoji-picker">
            <div class="emoji-picker-header">
                <span>Select an emoji</span>
                <button class="btn-icon" id="closeEmojiPicker">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            <div class="emoji-grid">
                <span class="emoji-item" data-emoji="👍">👍</span>
                <span class="emoji-item" data-emoji="❤️">❤️</span>
                <span class="emoji-item" data-emoji="😊">😊</span>
                <span class="emoji-item" data-emoji="😂">😂</span>
                <span class="emoji-item" data-emoji="🎉">🎉</span>
                <span class="emoji-item" data-emoji="🤔">🤔</span>
                <span class="emoji-item" data-emoji="👏">👏</span>
                <span class="emoji-item" data-emoji="🔥">🔥</span>
                <span class="emoji-item" data-emoji="💯">💯</span>
                <span class="emoji-item" data-emoji="✨">✨</span>
                <span class="emoji-item" data-emoji="💪">💪</span>
                <span class="emoji-item" data-emoji="🙏">🙏</span>
                <span class="emoji-item" data-emoji="😍">😍</span>
                <span class="emoji-item" data-emoji="🤝">🤝</span>
                <span class="emoji-item" data-emoji="👌">👌</span>
                <span class="emoji-item" data-emoji="✅">✅</span>
                <span class="emoji-item" data-emoji="❌">❌</span>
                <span class="emoji-item" data-emoji="⚠️">⚠️</span>
                <span class="emoji-item" data-emoji="📌">📌</span>
                <span class="emoji-item" data-emoji="💡">💡</span>
            </div>
        </div>
    </div>

    <!-- New Channel Modal -->
    <div class="modal hidden" id="newChannelModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Create New Channel</h2>
                <button class="btn-icon" id="closeNewChannelModal">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label for="channelName">Channel Name</label>
                    <input type="text" id="channelName" placeholder="e.g., project-updates" required>
                </div>
                <div class="form-group">
                    <label for="channelDescription">Description (optional)</label>
                    <textarea id="channelDescription" placeholder="What's this channel about?" rows="3"></textarea>
                </div>
                <div class="form-group">
                    <label>
                        <input type="checkbox" id="channelPrivate">
                        Make this channel private
                    </label>
                    <small class="form-text">Private channels can only be joined by invitation</small>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" id="cancelNewChannel">Cancel</button>
                <button class="btn btn-primary" id="createChannelBtn">Create Channel</button>
            </div>
        </div>
    </div>

    <!-- Hidden user data for JavaScript -->
    <div id="userData"
         data-user-id="<?php echo $userId; ?>"
         data-user-name="<?php echo htmlspecialchars($userName); ?>"
         data-user-role="<?php echo $userRole; ?>"
         data-tenant-id="<?php echo $currentTenantId; ?>"
         style="display: none;">
    </div>

    <!-- Scripts -->
    <script src="assets/js/chat.js"></script>
    <script src="assets/js/polling.js"></script>
    <script>
        // Initialize chat when DOM is ready
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize chat module
            window.chatModule = new ChatModule();

            // Initialize polling manager
            window.pollingManager = new PollingManager(window.chatModule);

            // Start polling
            window.pollingManager.startPolling();

            // Handle page visibility changes
            document.addEventListener('visibilitychange', function() {
                if (document.hidden) {
                    window.pollingManager.stopPolling();
                } else {
                    window.pollingManager.startPolling();
                }
            });
        });
    </script>
    <script src="assets/js/navigation-helper.js"></script>
</body>
</html>