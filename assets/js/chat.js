/**
 * Chat Module for Nexio Solution V2
 * Handles all chat functionality including channels, messages, reactions, and mentions
 */

class ChatModule {
    constructor() {
        this.currentChannel = null;
        this.currentChannelType = 'public';
        this.channels = [];
        this.messages = [];
        this.users = [];
        this.typingUsers = new Map();
        this.mentionAutocompleteActive = false;
        this.selectedMessageId = null;

        // Get user data from DOM
        const userData = document.getElementById('userData');
        this.userId = parseInt(userData.dataset.userId);
        this.userName = userData.dataset.userName;
        this.userRole = userData.dataset.userRole;
        this.tenantId = userData.dataset.tenantId;

        // Initialize
        this.init();
    }

    async init() {
        // Load channels
        await this.loadChannels();

        // Set up event listeners
        this.setupEventListeners();

        // Load first channel if available
        if (this.channels.length > 0) {
            this.selectChannel(this.channels[0].id, this.channels[0].type);
        }

        // Load users for mentions
        await this.loadUsers();

        // Initialize auto-resize for message input
        this.initMessageInputAutoResize();
    }

    setupEventListeners() {
        // Channel selection
        document.addEventListener('click', (e) => {
            if (e.target.closest('.channel-item')) {
                const channelItem = e.target.closest('.channel-item');
                const channelId = channelItem.dataset.channelId;
                const channelType = channelItem.dataset.channelType;
                this.selectChannel(channelId, channelType);
            }
        });

        // Send message
        const sendBtn = document.getElementById('sendBtn');
        const messageInput = document.getElementById('messageInput');

        sendBtn.addEventListener('click', () => this.sendMessage());

        messageInput.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                this.sendMessage();
            }
        });

        // Message input events
        messageInput.addEventListener('input', (e) => {
            this.handleMessageInput(e.target.value);
        });

        // Emoji picker
        document.getElementById('emojiBtn').addEventListener('click', () => {
            document.getElementById('emojiPicker').classList.toggle('hidden');
        });

        document.getElementById('closeEmojiPicker').addEventListener('click', () => {
            document.getElementById('emojiPicker').classList.add('hidden');
        });

        document.querySelectorAll('.emoji-item').forEach(item => {
            item.addEventListener('click', (e) => {
                const emoji = e.target.dataset.emoji;
                this.insertEmoji(emoji);
                document.getElementById('emojiPicker').classList.add('hidden');
            });
        });

        // Reaction handling
        document.addEventListener('click', (e) => {
            if (e.target.closest('.add-reaction-btn')) {
                const messageId = e.target.closest('.message').dataset.messageId;
                this.showReactionPicker(messageId, e.target);
            }

            if (e.target.closest('.reaction-badge')) {
                const messageId = e.target.closest('.message').dataset.messageId;
                const emoji = e.target.closest('.reaction-badge').dataset.emoji;
                this.toggleReaction(messageId, emoji);
            }
        });

        // New channel modal
        document.getElementById('newChannelBtn').addEventListener('click', () => {
            document.getElementById('newChannelModal').classList.remove('hidden');
        });

        document.getElementById('closeNewChannelModal').addEventListener('click', () => {
            document.getElementById('newChannelModal').classList.add('hidden');
        });

        document.getElementById('cancelNewChannel').addEventListener('click', () => {
            document.getElementById('newChannelModal').classList.add('hidden');
        });

        document.getElementById('createChannelBtn').addEventListener('click', () => {
            this.createChannel();
        });

        // Channel search
        document.getElementById('channelSearch').addEventListener('input', (e) => {
            this.filterChannels(e.target.value);
        });

        // Members sidebar toggle
        document.getElementById('toggleMembersBtn').addEventListener('click', () => {
            document.getElementById('membersSidebar').classList.toggle('hidden');
            if (!document.getElementById('membersSidebar').classList.contains('hidden')) {
                this.loadChannelMembers();
            }
        });

        document.getElementById('closeMembersBtn').addEventListener('click', () => {
            document.getElementById('membersSidebar').classList.add('hidden');
        });

        // Mobile channels toggle
        document.getElementById('mobileChannelsToggle').addEventListener('click', () => {
            document.getElementById('channelsSidebar').classList.toggle('mobile-visible');
        });

        // Attachment button
        document.getElementById('attachmentBtn').addEventListener('click', () => {
            this.openFileManager();
        });

        // Handle mention autocomplete clicks
        document.addEventListener('click', (e) => {
            if (e.target.closest('.mention-item')) {
                const userId = e.target.closest('.mention-item').dataset.userId;
                const userName = e.target.closest('.mention-item').dataset.userName;
                this.insertMention(userId, userName);
            }
        });
    }

    async loadChannels() {
        try {
            const response = await fetch('/Nexiosolution/collabora/api/channels.php', {
                credentials: 'same-origin'
            });

            if (!response.ok) throw new Error('Failed to load channels');

            const data = await response.json();
            if (data.success) {
                this.channels = data.channels || [];
                this.renderChannels();
            }
        } catch (error) {
            console.error('Error loading channels:', error);
            this.showToast('error', 'Error', 'Failed to load channels');
        }
    }

    renderChannels() {
        const publicChannelsList = document.getElementById('publicChannels');
        const privateChannelsList = document.getElementById('privateChannels');
        const directMessagesList = document.getElementById('directMessages');

        // Clear existing content
        publicChannelsList.innerHTML = '';
        privateChannelsList.innerHTML = '';
        directMessagesList.innerHTML = '';

        // Group channels by type
        const publicChannels = this.channels.filter(c => c.type === 'public');
        const privateChannels = this.channels.filter(c => c.type === 'private');
        const directMessages = this.channels.filter(c => c.type === 'direct');

        // Render each group
        publicChannels.forEach(channel => {
            publicChannelsList.appendChild(this.createChannelElement(channel));
        });

        privateChannels.forEach(channel => {
            privateChannelsList.appendChild(this.createChannelElement(channel));
        });

        directMessages.forEach(channel => {
            directMessagesList.appendChild(this.createChannelElement(channel));
        });
    }

    createChannelElement(channel) {
        const div = document.createElement('div');
        div.className = 'channel-item';
        div.dataset.channelId = channel.id;
        div.dataset.channelType = channel.type;

        if (this.currentChannel === channel.id) {
            div.classList.add('active');
        }

        const prefix = channel.type === 'public' ? '#' :
                      channel.type === 'private' ? '🔒' : '';

        // For direct messages, show online status
        let statusIndicator = '';
        if (channel.type === 'direct') {
            const isOnline = channel.online_status === 'online';
            statusIndicator = `<span class="status-indicator ${isOnline ? 'online' : 'offline'}"></span>`;
        }

        // Unread count badge
        const unreadBadge = channel.unread_count > 0 ?
            `<span class="unread-badge">${channel.unread_count}</span>` : '';

        div.innerHTML = `
            <div class="channel-name">
                ${statusIndicator}
                <span>${prefix} ${this.escapeHtml(channel.name)}</span>
            </div>
            ${unreadBadge}
        `;

        return div;
    }

    async selectChannel(channelId, channelType) {
        this.currentChannel = channelId;
        this.currentChannelType = channelType;

        // Update UI to show active channel
        document.querySelectorAll('.channel-item').forEach(item => {
            item.classList.remove('active');
        });

        const activeItem = document.querySelector(`[data-channel-id="${channelId}"]`);
        if (activeItem) {
            activeItem.classList.add('active');

            // Update channel header
            const channel = this.channels.find(c => c.id == channelId);
            if (channel) {
                const prefix = channel.type === 'public' ? '#' :
                              channel.type === 'private' ? '🔒' : '';
                document.getElementById('currentChannelName').textContent = `${prefix} ${channel.name}`;
                document.getElementById('currentChannelDescription').textContent = channel.description || '';
            }
        }

        // Load messages for this channel
        await this.loadMessages(channelId);

        // Clear unread count
        this.clearUnreadCount(channelId);

        // Hide mobile channels sidebar
        document.getElementById('channelsSidebar').classList.remove('mobile-visible');
    }

    async loadMessages(channelId) {
        const messagesContainer = document.getElementById('messagesList');
        messagesContainer.innerHTML = '<div class="loading-messages"><div class="spinner"></div><p>Loading messages...</p></div>';

        try {
            const response = await fetch(`/Nexiosolution/collabora/api/messages.php?channel_id=${channelId}`, {
                credentials: 'same-origin'
            });

            if (!response.ok) throw new Error('Failed to load messages');

            const data = await response.json();
            if (data.success) {
                this.messages = data.messages || [];
                this.renderMessages();
            }
        } catch (error) {
            console.error('Error loading messages:', error);
            messagesContainer.innerHTML = '<div class="error-message">Failed to load messages</div>';
        }
    }

    renderMessages() {
        const messagesContainer = document.getElementById('messagesList');
        messagesContainer.innerHTML = '';

        if (this.messages.length === 0) {
            messagesContainer.innerHTML = '<div class="no-messages">No messages yet. Start the conversation!</div>';
            return;
        }

        let lastDate = null;
        this.messages.forEach(message => {
            // Add date separator if needed
            const messageDate = new Date(message.created_at).toLocaleDateString();
            if (messageDate !== lastDate) {
                const separator = document.createElement('div');
                separator.className = 'date-separator';
                separator.innerHTML = `<span>${this.formatDate(message.created_at)}</span>`;
                messagesContainer.appendChild(separator);
                lastDate = messageDate;
            }

            // Create message element
            messagesContainer.appendChild(this.createMessageElement(message));
        });

        // Scroll to bottom
        messagesContainer.scrollTop = messagesContainer.scrollHeight;
    }

    createMessageElement(message) {
        const div = document.createElement('div');
        div.className = 'message';
        div.dataset.messageId = message.id;

        // Check if it's a threaded reply
        if (message.parent_id) {
            div.classList.add('threaded-reply');
        }

        // Check if it's the current user's message
        const isOwnMessage = message.user_id == this.userId;
        if (isOwnMessage) {
            div.classList.add('own-message');
        }

        // Process message content for mentions
        const processedContent = this.processMentions(message.content);

        // Build reactions HTML
        let reactionsHtml = '';
        if (message.reactions && Object.keys(message.reactions).length > 0) {
            reactionsHtml = '<div class="message-reactions">';
            for (const [emoji, users] of Object.entries(message.reactions)) {
                const hasReacted = users.includes(this.userId);
                reactionsHtml += `
                    <span class="reaction-badge ${hasReacted ? 'reacted' : ''}"
                          data-emoji="${emoji}"
                          title="${users.length} reaction(s)">
                        ${emoji} ${users.length}
                    </span>
                `;
            }
            reactionsHtml += `
                <button class="add-reaction-btn" title="Add reaction">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                    </svg>
                </button>
            `;
            reactionsHtml += '</div>';
        } else {
            reactionsHtml = `
                <div class="message-reactions">
                    <button class="add-reaction-btn" title="Add reaction">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.182 15.182a4.5 4.5 0 01-6.364 0M21 12a9 9 0 11-18 0 9 9 0 0118 0zM9.75 9.75c0 .414-.168.75-.375.75S9 10.164 9 9.75 9.168 9 9.375 9s.375.336.375.75zm-.375 0h.008v.015h-.008V9.75zm5.625 0c0 .414-.168.75-.375.75s-.375-.336-.375-.75.168-.75.375-.75.375.336.375.75zm-.375 0h.008v.015h-.008V9.75z" />
                        </svg>
                    </button>
                </div>
            `;
        }

        // Build attachment HTML if present
        let attachmentHtml = '';
        if (message.attachment_id) {
            attachmentHtml = `
                <div class="message-attachment">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M18.375 12.739l-7.693 7.693a4.5 4.5 0 01-6.364-6.364l10.94-10.94A3 3 0 1119.5 7.372L8.552 18.32m.009-.01l-.01.01m5.699-9.941l-7.81 7.81a1.5 1.5 0 002.112 2.13" />
                    </svg>
                    <span>${this.escapeHtml(message.attachment_name || 'Attachment')}</span>
                </div>
            `;
        }

        div.innerHTML = `
            <div class="message-avatar">
                <span>${message.user_name ? message.user_name.substring(0, 2).toUpperCase() : 'U'}</span>
            </div>
            <div class="message-content">
                <div class="message-header">
                    <span class="message-author">${this.escapeHtml(message.user_name || 'Unknown User')}</span>
                    <span class="message-time">${this.formatTime(message.created_at)}</span>
                </div>
                <div class="message-text">${processedContent}</div>
                ${attachmentHtml}
                ${reactionsHtml}
            </div>
        `;

        return div;
    }

    async sendMessage() {
        const messageInput = document.getElementById('messageInput');
        const content = messageInput.value.trim();

        if (!content || !this.currentChannel) return;

        // Extract mentions
        const mentions = this.extractMentions(content);

        try {
            const response = await fetch('/Nexiosolution/collabora/api/messages.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                credentials: 'same-origin',
                body: JSON.stringify({
                    channel_id: this.currentChannel,
                    content: content,
                    mentions: mentions,
                    attachment_id: this.selectedAttachmentId || null
                })
            });

            if (!response.ok) throw new Error('Failed to send message');

            const data = await response.json();
            if (data.success) {
                // Clear input
                messageInput.value = '';
                this.resetMessageInput();

                // Add message to list
                if (data.message) {
                    this.messages.push(data.message);
                    this.renderMessages();
                }

                // Clear selected attachment
                this.selectedAttachmentId = null;
            } else {
                throw new Error(data.message || 'Failed to send message');
            }
        } catch (error) {
            console.error('Error sending message:', error);
            this.showToast('error', 'Error', 'Failed to send message');
        }
    }

    handleMessageInput(value) {
        // Check for @mentions
        const lastAtIndex = value.lastIndexOf('@');
        if (lastAtIndex !== -1) {
            const textAfterAt = value.substring(lastAtIndex + 1);
            const spaceIndex = textAfterAt.indexOf(' ');

            if (spaceIndex === -1) {
                // Still typing the mention
                this.showMentionAutocomplete(textAfterAt);
            } else {
                this.hideMentionAutocomplete();
            }
        } else {
            this.hideMentionAutocomplete();
        }

        // Check for emoji shortcuts
        this.processEmojiShortcuts(value);
    }

    showMentionAutocomplete(searchTerm) {
        const autocomplete = document.getElementById('mentionAutocomplete');
        const filteredUsers = this.users.filter(user =>
            user.name.toLowerCase().includes(searchTerm.toLowerCase())
        );

        if (filteredUsers.length === 0) {
            autocomplete.classList.add('hidden');
            return;
        }

        let html = '';
        filteredUsers.slice(0, 5).forEach(user => {
            html += `
                <div class="mention-item" data-user-id="${user.id}" data-user-name="${user.name}">
                    <div class="mention-avatar">
                        <span>${user.name.substring(0, 2).toUpperCase()}</span>
                    </div>
                    <span class="mention-name">${this.escapeHtml(user.name)}</span>
                </div>
            `;
        });

        autocomplete.innerHTML = html;
        autocomplete.classList.remove('hidden');
        this.mentionAutocompleteActive = true;
    }

    hideMentionAutocomplete() {
        document.getElementById('mentionAutocomplete').classList.add('hidden');
        this.mentionAutocompleteActive = false;
    }

    insertMention(userId, userName) {
        const messageInput = document.getElementById('messageInput');
        const value = messageInput.value;
        const lastAtIndex = value.lastIndexOf('@');

        if (lastAtIndex !== -1) {
            const newValue = value.substring(0, lastAtIndex) + `@${userName} ` +
                           value.substring(value.length);
            messageInput.value = newValue;
            messageInput.focus();
            this.hideMentionAutocomplete();
        }
    }

    extractMentions(content) {
        const mentions = [];
        const regex = /@(\w+)/g;
        let match;

        while ((match = regex.exec(content)) !== null) {
            const username = match[1];
            const user = this.users.find(u =>
                u.name.toLowerCase() === username.toLowerCase()
            );
            if (user) {
                mentions.push(user.id);
            }
        }

        return mentions;
    }

    processMentions(content) {
        // Replace @mentions with highlighted spans
        return content.replace(/@(\w+)/g, (match, username) => {
            const user = this.users.find(u =>
                u.name.toLowerCase() === username.toLowerCase()
            );
            if (user) {
                const isCurrentUser = user.id == this.userId;
                return `<span class="mention ${isCurrentUser ? 'mention-self' : ''}">${match}</span>`;
            }
            return match;
        });
    }

    processEmojiShortcuts(value) {
        const shortcuts = {
            ':smile:': '😊',
            ':laugh:': '😂',
            ':heart:': '❤️',
            ':thumbsup:': '👍',
            ':thumbsdown:': '👎',
            ':fire:': '🔥',
            ':100:': '💯',
            ':star:': '⭐',
            ':think:': '🤔',
            ':cry:': '😢'
        };

        let processedValue = value;
        for (const [shortcut, emoji] of Object.entries(shortcuts)) {
            processedValue = processedValue.replace(shortcut, emoji);
        }

        if (processedValue !== value) {
            const messageInput = document.getElementById('messageInput');
            const cursorPos = messageInput.selectionStart;
            messageInput.value = processedValue;
            messageInput.setSelectionRange(cursorPos, cursorPos);
        }
    }

    insertEmoji(emoji) {
        const messageInput = document.getElementById('messageInput');
        const cursorPos = messageInput.selectionStart;
        const value = messageInput.value;

        const newValue = value.substring(0, cursorPos) + emoji + value.substring(cursorPos);
        messageInput.value = newValue;
        messageInput.focus();

        const newCursorPos = cursorPos + emoji.length;
        messageInput.setSelectionRange(newCursorPos, newCursorPos);
    }

    showReactionPicker(messageId, targetElement) {
        this.selectedMessageId = messageId;

        // Create a temporary reaction picker
        const picker = document.createElement('div');
        picker.className = 'quick-reaction-picker';
        picker.innerHTML = `
            <span class="quick-emoji" data-emoji="👍">👍</span>
            <span class="quick-emoji" data-emoji="❤️">❤️</span>
            <span class="quick-emoji" data-emoji="😊">😊</span>
            <span class="quick-emoji" data-emoji="😂">😂</span>
            <span class="quick-emoji" data-emoji="🎉">🎉</span>
            <span class="quick-emoji" data-emoji="🤔">🤔</span>
        `;

        // Position near the button
        const rect = targetElement.getBoundingClientRect();
        picker.style.position = 'absolute';
        picker.style.left = `${rect.left}px`;
        picker.style.top = `${rect.bottom + 5}px`;

        document.body.appendChild(picker);

        // Handle emoji selection
        picker.querySelectorAll('.quick-emoji').forEach(emoji => {
            emoji.addEventListener('click', async (e) => {
                await this.addReaction(messageId, e.target.dataset.emoji);
                picker.remove();
            });
        });

        // Remove picker when clicking outside
        setTimeout(() => {
            document.addEventListener('click', function removePicker(e) {
                if (!picker.contains(e.target)) {
                    picker.remove();
                    document.removeEventListener('click', removePicker);
                }
            });
        }, 100);
    }

    async addReaction(messageId, emoji) {
        try {
            const response = await fetch('/Nexiosolution/collabora/api/reactions.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                credentials: 'same-origin',
                body: JSON.stringify({
                    message_id: messageId,
                    emoji: emoji
                })
            });

            if (!response.ok) throw new Error('Failed to add reaction');

            const data = await response.json();
            if (data.success) {
                // Update the message in local state
                const message = this.messages.find(m => m.id == messageId);
                if (message) {
                    if (!message.reactions) message.reactions = {};
                    if (!message.reactions[emoji]) message.reactions[emoji] = [];

                    if (!message.reactions[emoji].includes(this.userId)) {
                        message.reactions[emoji].push(this.userId);
                    }

                    this.renderMessages();
                }
            }
        } catch (error) {
            console.error('Error adding reaction:', error);
        }
    }

    async toggleReaction(messageId, emoji) {
        try {
            const response = await fetch('/Nexiosolution/collabora/api/reactions.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                credentials: 'same-origin',
                body: JSON.stringify({
                    message_id: messageId,
                    emoji: emoji,
                    action: 'toggle'
                })
            });

            if (!response.ok) throw new Error('Failed to toggle reaction');

            const data = await response.json();
            if (data.success) {
                // Update the message in local state
                const message = this.messages.find(m => m.id == messageId);
                if (message && message.reactions && message.reactions[emoji]) {
                    const userIndex = message.reactions[emoji].indexOf(this.userId);
                    if (userIndex > -1) {
                        message.reactions[emoji].splice(userIndex, 1);
                        if (message.reactions[emoji].length === 0) {
                            delete message.reactions[emoji];
                        }
                    } else {
                        message.reactions[emoji].push(this.userId);
                    }
                    this.renderMessages();
                }
            }
        } catch (error) {
            console.error('Error toggling reaction:', error);
        }
    }

    async createChannel() {
        const name = document.getElementById('channelName').value.trim();
        const description = document.getElementById('channelDescription').value.trim();
        const isPrivate = document.getElementById('channelPrivate').checked;

        if (!name) {
            this.showToast('error', 'Error', 'Channel name is required');
            return;
        }

        try {
            const response = await fetch('/Nexiosolution/collabora/api/channels.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                credentials: 'same-origin',
                body: JSON.stringify({
                    name: name,
                    description: description,
                    type: isPrivate ? 'private' : 'public'
                })
            });

            if (!response.ok) throw new Error('Failed to create channel');

            const data = await response.json();
            if (data.success) {
                // Close modal
                document.getElementById('newChannelModal').classList.add('hidden');

                // Clear form
                document.getElementById('channelName').value = '';
                document.getElementById('channelDescription').value = '';
                document.getElementById('channelPrivate').checked = false;

                // Reload channels
                await this.loadChannels();

                // Select the new channel
                if (data.channel_id) {
                    this.selectChannel(data.channel_id, isPrivate ? 'private' : 'public');
                }

                this.showToast('success', 'Success', 'Channel created successfully');
            } else {
                throw new Error(data.message || 'Failed to create channel');
            }
        } catch (error) {
            console.error('Error creating channel:', error);
            this.showToast('error', 'Error', error.message);
        }
    }

    filterChannels(searchTerm) {
        const term = searchTerm.toLowerCase();
        document.querySelectorAll('.channel-item').forEach(item => {
            const name = item.querySelector('.channel-name span').textContent.toLowerCase();
            if (name.includes(term)) {
                item.style.display = '';
            } else {
                item.style.display = 'none';
            }
        });
    }

    async loadUsers() {
        try {
            const response = await fetch('/Nexiosolution/collabora/api/users.php', {
                credentials: 'same-origin'
            });

            if (!response.ok) throw new Error('Failed to load users');

            const data = await response.json();
            if (data.success) {
                this.users = data.users || [];
            }
        } catch (error) {
            console.error('Error loading users:', error);
        }
    }

    async loadChannelMembers() {
        if (!this.currentChannel) return;

        try {
            const response = await fetch(`/Nexiosolution/collabora/api/channels.php?id=${this.currentChannel}&include_members=true`, {
                credentials: 'same-origin'
            });

            if (!response.ok) throw new Error('Failed to load channel members');

            const data = await response.json();
            if (data.success && data.members) {
                this.renderMembers(data.members);
            }
        } catch (error) {
            console.error('Error loading channel members:', error);
        }
    }

    renderMembers(members) {
        const membersList = document.getElementById('membersList');
        membersList.innerHTML = '';

        members.forEach(member => {
            const div = document.createElement('div');
            div.className = 'member-item';

            const isOnline = member.presence_status === 'online';
            const statusClass = isOnline ? 'online' :
                               member.presence_status === 'away' ? 'away' : 'offline';

            div.innerHTML = `
                <div class="member-avatar">
                    <span>${member.name.substring(0, 2).toUpperCase()}</span>
                    <span class="status-dot ${statusClass}"></span>
                </div>
                <div class="member-info">
                    <div class="member-name">${this.escapeHtml(member.name)}</div>
                    <div class="member-status">${member.presence_status || 'offline'}</div>
                </div>
            `;

            membersList.appendChild(div);
        });
    }

    async clearUnreadCount(channelId) {
        try {
            await fetch('/Nexiosolution/collabora/api/channels.php', {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json'
                },
                credentials: 'same-origin',
                body: JSON.stringify({
                    channel_id: channelId,
                    action: 'mark_read'
                })
            });
        } catch (error) {
            console.error('Error clearing unread count:', error);
        }
    }

    openFileManager() {
        // This would open the existing file manager in a modal
        // For now, we'll just show a placeholder message
        this.showToast('info', 'File Manager', 'File attachment feature will integrate with existing file manager');
    }

    initMessageInputAutoResize() {
        const messageInput = document.getElementById('messageInput');

        messageInput.addEventListener('input', () => {
            messageInput.style.height = 'auto';
            messageInput.style.height = Math.min(messageInput.scrollHeight, 120) + 'px';
        });
    }

    resetMessageInput() {
        const messageInput = document.getElementById('messageInput');
        messageInput.style.height = 'auto';
    }

    updateTypingIndicator(typingUsers) {
        const indicator = document.getElementById('typingIndicator');
        const typingText = document.querySelector('.typing-text');

        if (typingUsers.length === 0) {
            indicator.classList.add('hidden');
            return;
        }

        indicator.classList.remove('hidden');

        if (typingUsers.length === 1) {
            typingText.textContent = `${typingUsers[0]} is typing...`;
        } else if (typingUsers.length === 2) {
            typingText.textContent = `${typingUsers[0]} and ${typingUsers[1]} are typing...`;
        } else {
            typingText.textContent = `${typingUsers.length} people are typing...`;
        }
    }

    formatTime(timestamp) {
        const date = new Date(timestamp);
        const now = new Date();
        const diff = now - date;
        const seconds = Math.floor(diff / 1000);
        const minutes = Math.floor(seconds / 60);
        const hours = Math.floor(minutes / 60);
        const days = Math.floor(hours / 24);

        if (days > 7) {
            return date.toLocaleDateString();
        } else if (days > 0) {
            return `${days}d ago`;
        } else if (hours > 0) {
            return `${hours}h ago`;
        } else if (minutes > 0) {
            return `${minutes}m ago`;
        } else {
            return 'Just now';
        }
    }

    formatDate(timestamp) {
        const date = new Date(timestamp);
        const today = new Date();
        const yesterday = new Date(today);
        yesterday.setDate(yesterday.getDate() - 1);

        if (date.toDateString() === today.toDateString()) {
            return 'Today';
        } else if (date.toDateString() === yesterday.toDateString()) {
            return 'Yesterday';
        } else {
            return date.toLocaleDateString('en-US', {
                month: 'long',
                day: 'numeric',
                year: date.getFullYear() !== today.getFullYear() ? 'numeric' : undefined
            });
        }
    }

    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    showToast(type, title, message) {
        // Use existing toast function if available
        if (typeof showToast === 'function') {
            showToast(type, title, message);
        } else {
            // Fallback to console
            console.log(`[${type.toUpperCase()}] ${title}: ${message}`);
        }
    }

    // Method to handle new messages from polling
    handleNewMessages(messages) {
        if (!messages || messages.length === 0) return;

        // Add new messages to the current channel
        messages.forEach(message => {
            // Only add if message is for current channel and not already present
            if (message.channel_id == this.currentChannel) {
                const exists = this.messages.find(m => m.id === message.id);
                if (!exists) {
                    this.messages.push(message);
                }
            }

            // Show notification for mentions
            if (message.mentions && message.mentions.includes(this.userId)) {
                this.showMentionNotification(message);
            }
        });

        // Re-render if we're viewing the channel
        if (messages.some(m => m.channel_id == this.currentChannel)) {
            this.renderMessages();
        }

        // Update unread counts for other channels
        this.updateUnreadCounts(messages);
    }

    showMentionNotification(message) {
        const title = `New mention from ${message.user_name}`;
        const body = message.content.substring(0, 100);

        // Show browser notification if permitted
        if ('Notification' in window && Notification.permission === 'granted') {
            new Notification(title, {
                body: body,
                icon: '/assets/images/icon.png',
                tag: `mention-${message.id}`
            });
        }

        // Show toast notification
        this.showToast('info', title, body);
    }

    updateUnreadCounts(messages) {
        // Group messages by channel
        const channelCounts = {};
        messages.forEach(message => {
            if (message.channel_id != this.currentChannel) {
                channelCounts[message.channel_id] = (channelCounts[message.channel_id] || 0) + 1;
            }
        });

        // Update UI
        for (const [channelId, count] of Object.entries(channelCounts)) {
            const channelItem = document.querySelector(`[data-channel-id="${channelId}"]`);
            if (channelItem) {
                let badge = channelItem.querySelector('.unread-badge');
                if (!badge) {
                    badge = document.createElement('span');
                    badge.className = 'unread-badge';
                    channelItem.appendChild(badge);
                }

                const channel = this.channels.find(c => c.id == channelId);
                if (channel) {
                    channel.unread_count = (channel.unread_count || 0) + count;
                    badge.textContent = channel.unread_count;
                }
            }
        }
    }
}

// Request notification permission on load
if ('Notification' in window && Notification.permission === 'default') {
    Notification.requestPermission();
}