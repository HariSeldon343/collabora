/**
 * Polling Manager for Nexio Solution V2 Chat
 * Handles long-polling for real-time message updates
 */

class PollingManager {
    constructor(chatModule) {
        this.chatModule = chatModule;
        this.lastMessageId = 0;
        this.lastEventId = 0;
        this.isPolling = false;
        this.errorCount = 0;
        this.pollTimeout = 2000; // 2 seconds base timeout
        this.maxPollTimeout = 60000; // 60 seconds max timeout
        this.pollController = null;
        this.retryTimer = null;
    }

    async startPolling() {
        if (this.isPolling) {
            console.log('Polling already active');
            return;
        }

        this.isPolling = true;
        this.errorCount = 0;
        console.log('Starting polling...');

        // Get initial last message ID
        await this.getInitialMessageId();

        // Start the polling loop
        this.poll();
    }

    stopPolling() {
        if (!this.isPolling) {
            console.log('Polling not active');
            return;
        }

        this.isPolling = false;
        console.log('Stopping polling...');

        // Abort current request if any
        if (this.pollController) {
            this.pollController.abort();
            this.pollController = null;
        }

        // Clear retry timer if any
        if (this.retryTimer) {
            clearTimeout(this.retryTimer);
            this.retryTimer = null;
        }
    }

    async poll() {
        if (!this.isPolling) {
            console.log('Polling stopped, not continuing');
            return;
        }

        try {
            // Create abort controller for this request
            this.pollController = new AbortController();

            // Build poll URL with parameters
            const params = new URLSearchParams({
                last_message_id: this.lastMessageId,
                last_event_id: this.lastEventId
            });

            const response = await fetch(`/Nexiosolution/collabora/api/chat-poll.php?${params}`, {
                method: 'GET',
                credentials: 'same-origin',
                signal: this.pollController.signal,
                headers: {
                    'Cache-Control': 'no-cache'
                }
            });

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const data = await response.json();

            if (data.success) {
                // Reset error count on successful response
                this.errorCount = 0;
                this.pollTimeout = 2000;

                // Process new messages
                if (data.messages && data.messages.length > 0) {
                    this.handleNewMessages(data.messages);
                }

                // Process typing indicators
                if (data.typing_users) {
                    this.handleTypingIndicators(data.typing_users);
                }

                // Process presence updates
                if (data.presence_updates) {
                    this.handlePresenceUpdates(data.presence_updates);
                }

                // Process other events
                if (data.events) {
                    this.handleEvents(data.events);
                }

                // Update last IDs
                if (data.last_message_id) {
                    this.lastMessageId = data.last_message_id;
                }
                if (data.last_event_id) {
                    this.lastEventId = data.last_event_id;
                }

                // Continue polling immediately
                this.scheduleNextPoll(0);
            } else {
                // Server returned error
                console.error('Poll error:', data.message);
                this.handleError();
            }
        } catch (error) {
            // Network or other error
            if (error.name === 'AbortError') {
                console.log('Poll request aborted');
            } else {
                console.error('Polling error:', error);
                this.handleError();
            }
        }
    }

    scheduleNextPoll(delay = null) {
        if (!this.isPolling) {
            console.log('Not scheduling next poll - polling stopped');
            return;
        }

        const pollDelay = delay !== null ? delay : this.pollTimeout;

        this.retryTimer = setTimeout(() => {
            this.poll();
        }, pollDelay);
    }

    handleError() {
        this.errorCount++;
        console.log(`Poll error #${this.errorCount}`);

        // Exponential backoff
        this.pollTimeout = Math.min(
            this.pollTimeout * 2,
            this.maxPollTimeout
        );

        console.log(`Next poll in ${this.pollTimeout}ms`);

        // Schedule retry with backoff
        this.scheduleNextPoll();

        // Show error notification after multiple failures
        if (this.errorCount === 3) {
            this.showConnectionWarning();
        }
    }

    handleNewMessages(messages) {
        console.log(`Received ${messages.length} new messages`);

        // Pass to chat module for processing
        this.chatModule.handleNewMessages(messages);

        // Update last message ID
        const lastMessage = messages[messages.length - 1];
        if (lastMessage && lastMessage.id > this.lastMessageId) {
            this.lastMessageId = lastMessage.id;
        }
    }

    handleTypingIndicators(typingUsers) {
        // Filter out current user
        const otherUsers = typingUsers.filter(user =>
            user.user_id !== this.chatModule.userId
        );

        // Extract usernames for current channel
        const currentChannelTyping = otherUsers
            .filter(user => user.channel_id == this.chatModule.currentChannel)
            .map(user => user.user_name);

        // Update typing indicator
        this.chatModule.updateTypingIndicator(currentChannelTyping);
    }

    handlePresenceUpdates(updates) {
        console.log('Presence updates:', updates);

        // Update user status in channel lists
        updates.forEach(update => {
            this.updateUserPresence(update.user_id, update.status);
        });
    }

    handleEvents(events) {
        events.forEach(event => {
            switch (event.type) {
                case 'channel_created':
                    this.handleChannelCreated(event);
                    break;
                case 'channel_deleted':
                    this.handleChannelDeleted(event);
                    break;
                case 'user_joined':
                    this.handleUserJoined(event);
                    break;
                case 'user_left':
                    this.handleUserLeft(event);
                    break;
                case 'message_edited':
                    this.handleMessageEdited(event);
                    break;
                case 'message_deleted':
                    this.handleMessageDeleted(event);
                    break;
                default:
                    console.log('Unknown event type:', event.type);
            }
        });
    }

    handleChannelCreated(event) {
        // Reload channels list
        this.chatModule.loadChannels();

        // Show notification
        if (event.creator_id !== this.chatModule.userId) {
            this.showNotification(
                'New Channel',
                `${event.creator_name} created #${event.channel_name}`
            );
        }
    }

    handleChannelDeleted(event) {
        // Check if we're currently in the deleted channel
        if (event.channel_id == this.chatModule.currentChannel) {
            // Switch to first available channel
            this.chatModule.loadChannels().then(() => {
                if (this.chatModule.channels.length > 0) {
                    this.chatModule.selectChannel(
                        this.chatModule.channels[0].id,
                        this.chatModule.channels[0].type
                    );
                }
            });

            this.showNotification(
                'Channel Deleted',
                `The channel #${event.channel_name} has been deleted`
            );
        } else {
            // Just reload the channels list
            this.chatModule.loadChannels();
        }
    }

    handleUserJoined(event) {
        if (event.channel_id == this.chatModule.currentChannel) {
            // Reload channel members if sidebar is visible
            const membersSidebar = document.getElementById('membersSidebar');
            if (!membersSidebar.classList.contains('hidden')) {
                this.chatModule.loadChannelMembers();
            }

            // Show notification
            this.showNotification(
                'User Joined',
                `${event.user_name} joined the channel`
            );
        }
    }

    handleUserLeft(event) {
        if (event.channel_id == this.chatModule.currentChannel) {
            // Reload channel members if sidebar is visible
            const membersSidebar = document.getElementById('membersSidebar');
            if (!membersSidebar.classList.contains('hidden')) {
                this.chatModule.loadChannelMembers();
            }

            // Show notification
            this.showNotification(
                'User Left',
                `${event.user_name} left the channel`
            );
        }
    }

    handleMessageEdited(event) {
        // Update message in local state
        const message = this.chatModule.messages.find(m => m.id === event.message_id);
        if (message) {
            message.content = event.new_content;
            message.edited_at = event.edited_at;
            this.chatModule.renderMessages();
        }
    }

    handleMessageDeleted(event) {
        // Remove message from local state
        const index = this.chatModule.messages.findIndex(m => m.id === event.message_id);
        if (index !== -1) {
            this.chatModule.messages.splice(index, 1);
            this.chatModule.renderMessages();
        }
    }

    updateUserPresence(userId, status) {
        // Update in direct messages list
        const dmChannels = document.querySelectorAll('#directMessages .channel-item');
        dmChannels.forEach(channel => {
            const channelUserId = channel.dataset.userId;
            if (channelUserId == userId) {
                const statusIndicator = channel.querySelector('.status-indicator');
                if (statusIndicator) {
                    statusIndicator.className = `status-indicator ${status}`;
                }
            }
        });

        // Update in members sidebar if visible
        const membersSidebar = document.getElementById('membersSidebar');
        if (!membersSidebar.classList.contains('hidden')) {
            const memberItem = membersSidebar.querySelector(`[data-user-id="${userId}"]`);
            if (memberItem) {
                const statusDot = memberItem.querySelector('.status-dot');
                if (statusDot) {
                    statusDot.className = `status-dot ${status}`;
                }
                const statusText = memberItem.querySelector('.member-status');
                if (statusText) {
                    statusText.textContent = status;
                }
            }
        }
    }

    async getInitialMessageId() {
        try {
            const response = await fetch('/Nexiosolution/collabora/api/messages.php?action=get_last_id', {
                credentials: 'same-origin'
            });

            if (response.ok) {
                const data = await response.json();
                if (data.success && data.last_id) {
                    this.lastMessageId = data.last_id;
                    console.log('Initial message ID:', this.lastMessageId);
                }
            }
        } catch (error) {
            console.error('Error getting initial message ID:', error);
        }
    }

    showNotification(title, body) {
        // Browser notification if permitted
        if ('Notification' in window && Notification.permission === 'granted') {
            new Notification(title, {
                body: body,
                icon: '/assets/images/icon.png',
                tag: `chat-${Date.now()}`
            });
        }

        // Also show toast
        if (this.chatModule && this.chatModule.showToast) {
            this.chatModule.showToast('info', title, body);
        }
    }

    showConnectionWarning() {
        if (this.chatModule && this.chatModule.showToast) {
            this.chatModule.showToast(
                'warning',
                'Connection Issue',
                'Having trouble connecting to chat. Retrying...'
            );
        }
    }

    // Utility method to check if polling is active
    isActive() {
        return this.isPolling;
    }

    // Method to reset polling (useful after reconnection)
    async reset() {
        console.log('Resetting polling manager...');

        // Stop current polling
        this.stopPolling();

        // Reset state
        this.lastMessageId = 0;
        this.lastEventId = 0;
        this.errorCount = 0;
        this.pollTimeout = 2000;

        // Restart
        await this.startPolling();
    }

    // Method to handle page visibility changes
    handleVisibilityChange() {
        if (document.hidden) {
            console.log('Page hidden, stopping polling');
            this.stopPolling();
        } else {
            console.log('Page visible, starting polling');
            this.startPolling();
        }
    }

    // Method to manually trigger a poll (useful for testing)
    async triggerPoll() {
        if (!this.isPolling) {
            await this.poll();
        }
    }

    // Get polling statistics
    getStats() {
        return {
            isPolling: this.isPolling,
            errorCount: this.errorCount,
            lastMessageId: this.lastMessageId,
            lastEventId: this.lastEventId,
            currentTimeout: this.pollTimeout
        };
    }
}

// Export for module usage if needed
if (typeof module !== 'undefined' && module.exports) {
    module.exports = PollingManager;
}