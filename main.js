// ===== NOTIFICATIONS =====
function toggleNotifPanel() {
    const panel = document.getElementById('notifPanel');
    if (panel) {
        panel.classList.toggle('open');
        if (panel.classList.contains('open')) {
            markNotifsRead();
        }
    }
}

function markNotifsRead() {
    fetch('?action=mark_notifs_read', {method:'POST'})
        .then(() => {
            const dot = document.querySelector('.notif-btn .dot');
            if (dot) dot.style.display = 'none';
            document.querySelectorAll('.notif-item.unread').forEach(el => el.classList.remove('unread'));
        });
}

document.addEventListener('click', function(e) {
    const panel = document.getElementById('notifPanel');
    const btn = document.querySelector('.notif-btn');
    if (panel && btn && !panel.contains(e.target) && !btn.contains(e.target)) {
        panel.classList.remove('open');
    }
});

// ===== MODALS =====
function openModal(id) {
    const m = document.getElementById(id);
    if (m) { m.classList.add('open'); document.body.style.overflow = 'hidden'; }
}
function closeModal(id) {
    const m = document.getElementById(id);
    if (m) { m.classList.remove('open'); document.body.style.overflow = ''; }
}
document.addEventListener('click', function(e) {
    if (e.target.classList.contains('modal-overlay')) {
        e.target.classList.remove('open');
        document.body.style.overflow = '';
    }
});

// ===== TABS =====
function switchTab(tabGroup, tabId) {
    document.querySelectorAll(`[data-tabgroup="${tabGroup}"].tab`).forEach(t => t.classList.remove('active'));
    document.querySelectorAll(`[data-tabgroup="${tabGroup}"].tab-content`).forEach(t => t.classList.remove('active'));
    const tab = document.querySelector(`[data-tabgroup="${tabGroup}"][data-tab="${tabId}"].tab`);
    const content = document.querySelector(`[data-tabgroup="${tabGroup}"][data-tab="${tabId}"].tab-content`);
    if (tab) tab.classList.add('active');
    if (content) content.classList.add('active');
}

// ===== FILE UPLOAD =====
function initFileUpload(inputId, areaId, nameId) {
    const input = document.getElementById(inputId);
    const area = document.getElementById(areaId);
    if (!input || !area) return;

    area.addEventListener('click', () => input.click());
    area.addEventListener('dragover', (e) => { e.preventDefault(); area.classList.add('drag'); });
    area.addEventListener('dragleave', () => area.classList.remove('drag'));
    area.addEventListener('drop', (e) => {
        e.preventDefault();
        area.classList.remove('drag');
        if (e.dataTransfer.files[0]) {
            input.files = e.dataTransfer.files;
            updateFileName(input, nameId, areaId);
        }
    });
    input.addEventListener('change', () => updateFileName(input, nameId, areaId));
}

function updateFileName(input, nameId, areaId) {
    if (input.files[0]) {
        const nameEl = document.getElementById(nameId);
        if (nameEl) nameEl.textContent = input.files[0].name;
        // If image, show preview
        if (input.files[0].type.startsWith('image/')) {
            const reader = new FileReader();
            reader.onload = (e) => {
                let preview = document.getElementById(areaId + '_preview');
                if (!preview) {
                    preview = document.createElement('img');
                    preview.id = areaId + '_preview';
                    preview.className = 'photo-preview';
                    document.getElementById(areaId).after(preview);
                }
                preview.src = e.target.result;
            };
            reader.readAsDataURL(input.files[0]);
        }
    }
}

// ===== ALERTS =====
function showAlert(msg, type = 'success') {
    const div = document.createElement('div');
    div.className = `alert alert-${type}`;
    div.textContent = msg;
    div.style.cssText = 'position:fixed;top:70px;right:20px;z-index:999;min-width:260px;box-shadow:0 4px 12px rgba(0,0,0,.15);';
    document.body.appendChild(div);
    setTimeout(() => div.remove(), 3500);
}

// ===== SIDEBAR MOBILE =====
function toggleSidebar() {
    document.querySelector('.sidebar').classList.toggle('open');
}

// ===== CONFIRM DELETE =====
function confirmAction(msg, formId) {
    if (confirm(msg)) {
        document.getElementById(formId).submit();
    }
}

// ===== HOURS CALCULATION =====
function calcWeeklyStatus(rendered, required) {
    const diff = rendered - required;
    return { diff, isDeficit: diff < 0, isOk: diff >= 0 };
}

// ===== AUTO-HIDE ALERTS =====
document.addEventListener('DOMContentLoaded', function() {
    const alerts = document.querySelectorAll('.alert[data-auto-hide]');
    alerts.forEach(a => setTimeout(() => a.remove(), 4000));

    // Init file uploads if present
    initFileUpload('dtr_photo', 'dtr_upload_area', 'dtr_file_name');
    initFileUpload('doc_file', 'doc_upload_area', 'doc_file_name');

    // Activate first tab in each group
    document.querySelectorAll('.tabs').forEach(tabsEl => {
        const group = tabsEl.dataset.group;
        const first = tabsEl.querySelector('.tab');
        if (first && group) first.classList.add('active');
    });
});

// ===== TABLE SEARCH =====
function searchTable(inputId, tableId) {
    const input = document.getElementById(inputId);
    const table = document.getElementById(tableId);
    if (!input || !table) return;
    input.addEventListener('keyup', function() {
        const filter = this.value.toLowerCase();
        table.querySelectorAll('tbody tr').forEach(row => {
            row.style.display = row.textContent.toLowerCase().includes(filter) ? '' : 'none';
        });
    });
}
const OJT_BASE_URL =
    window.OJT_BASE_URL ||
    'http://localhost/ojt_system/';

function chatEndpoint(filename) {
    return OJT_BASE_URL + filename;
}
// ==========================================================
// CHAT MODULE
// ==========================================================
let chatConversationId = 0;
let chatRefreshTimer = null;
let chatShouldScroll = true;
/**
 * Initialize the chat module.
 * This only runs when the chat page elements are present.
 */
function initChatModule() {
    const chatBody = document.getElementById('chatBody');
    const messageInput = document.getElementById('message');
    const attachmentInput = document.getElementById('attachment');
    const searchInput = document.getElementById('searchStudent');
    const conversationInput = document.getElementById('conversationId');

    if (!chatBody || !messageInput) {
        return;
    }
    if (conversationInput) {
        chatConversationId = Number(conversationInput.value) || 0;
    } else if (typeof window.initialConversationId !== 'undefined') {
        chatConversationId = Number(window.initialConversationId) || 0;
    }
    loadChatConversations();
    if (chatConversationId > 0) {
        loadChatMessages(true);
    } else {
        showNoConversationSelected();
    }
    messageInput.addEventListener('keydown', function (event) {
        if (event.key === 'Enter' && !event.shiftKey) {
            event.preventDefault();
            sendChatMessage();
        }
    });
    messageInput.addEventListener('input', function () {
        autoResizeChatTextarea(this);
    });
    chatBody.addEventListener('scroll', function () {
        const distanceFromBottom =
            chatBody.scrollHeight -
            chatBody.scrollTop -
            chatBody.clientHeight;
        chatShouldScroll = distanceFromBottom < 120;
    });
    if (attachmentInput) {
        attachmentInput.addEventListener('change', function () {
            showChatAttachmentPreview(this);
        });
    }
    if (searchInput) {
        searchInput.addEventListener('input', function () {
            filterChatConversations(this.value);
        });
    }
    startChatRefresh();
}
function highlightActiveConversation() {
    document.querySelectorAll('.chat-user').forEach(item => {
        item.classList.remove('active');

        const itemConversationId =
            Number(item.dataset.conversationId || 0);

        if (itemConversationId === chatConversationId) {
            item.classList.add('active');
        }
    });
}
/**
 * Load the conversation list.
 */
function loadChatConversations() {
    const conversationList = document.getElementById('conversationList');
    if (!conversationList) {
        return;
    }
    fetch(chatEndpoint('load_conversations.php'), {
        method: 'GET',
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        },
        cache: 'no-store'
    })
        .then(response => {
            if (!response.ok) {
                throw new Error('Unable to load conversations.');
            }
            return response.text();
        })
        .then(html => {
            conversationList.innerHTML = html;
            highlightActiveConversation();
        })
        .catch(error => {
            console.error(error);
            conversationList.innerHTML = `
                <div class="chat-list-error">
                    Unable to load conversations.
                </div>
            `;
        });
}
/**
 * Open a selected conversation.
 */
/**
 * Open the selected student/coordinator conversation.
 */
function openChatConversation(conversationId, displayName) {
    const id = Number(conversationId);

    if (!Number.isInteger(id) || id <= 0) {
        console.error('Invalid conversation ID:', conversationId);
        return;
    }
    chatConversationId = id;
    chatShouldScroll = true;
    const conversationInput =
        document.getElementById('conversationId');
    const chatName =
        document.getElementById('chatName');
    const messageInput =
        document.getElementById('message');
    const sendButton =
        document.getElementById('chatSendButton');
    const attachmentButton =
        document.getElementById('chatAttachmentButton');
    if (conversationInput) {
        conversationInput.value = String(id);
    }
    if (chatName) {
        chatName.textContent =
            displayName || 'Conversation';
    }
    if (messageInput) {
        messageInput.disabled = false;
        messageInput.focus();
    }
    if (sendButton) {
        sendButton.disabled = false;
    }
    if (attachmentButton) {
        attachmentButton.disabled = false;
    }
    highlightActiveConversation();
    loadChatMessages(true);
    markChatMessagesRead();
    updateChatUnreadCount();
}
/**
 * Compatibility for old conversation item onclick code.
 */
function openConversation(conversationId, displayName) {
    openChatConversation(conversationId, displayName);
}
/**
 * Load all messages for the selected conversation.
 */
function loadChatMessages(forceScroll = false) {
    const chatBody = document.getElementById('chatBody');

    if (!chatBody || chatConversationId <= 0) {
        return;
    }
    fetch(
        chatEndpoint('fetch_messages.php') +
        '?conversation_id=' +
        encodeURIComponent(chatConversationId),
        {
            method: 'GET',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            },
            cache: 'no-store'
        }
    )
    .then(async response => {
        const html = await response.text();

        if (!response.ok) {
            throw new Error(
                html || 'Unable to load messages.'
            );
        }
        return html;
    })
    .then(html => {
        const shouldScroll =
            forceScroll || chatShouldScroll;

        chatBody.innerHTML = html;

        if (shouldScroll) {
            scrollChatToBottom();
        }
        markChatMessagesRead();
    })
    .catch(error => {
        console.error(
            'Message loading error:',
            error
        );
        chatBody.innerHTML = `
            <div class="chat-empty-state">
                <strong>Unable to load messages</strong>
                <span>${escapeChatHtml(error.message)}</span>
            </div>
        `;
    });
}
/**
 * Compatibility function for older chat.php versions.
 */
function loadMessages() {
    loadChatMessages(false);
}
/**
 * Send a text message or attachment.
 */
function sendChatMessage() {
    const messageInput = document.getElementById('message');
    const attachmentInput = document.getElementById('attachment');
    const sendButton = document.getElementById('chatSendButton');
    if (!messageInput || chatConversationId <= 0) {
        showAlert('Please select a conversation first.', 'warning');
        return;
    }
    const message = messageInput.value.trim();
    const attachment =
        attachmentInput && attachmentInput.files.length > 0
            ? attachmentInput.files[0]
            : null;
    if (message === '' && !attachment) {
        return;
    }
    if (message.length > 5000) {
        showAlert('Message must not exceed 5,000 characters.', 'warning');
        return;
    }
    const formData = new FormData();
    formData.append('conversation_id', String(chatConversationId));
    formData.append('message', message);

    if (attachment) {
        formData.append('attachment', attachment);
    }
    setChatSendingState(true, sendButton);
    fetch(chatEndpoint('send_message.php'), {
    method: 'POST',
    headers: {
        'X-Requested-With': 'XMLHttpRequest'
    },
    body: formData
        })
        .then(async response => {
            const responseText = await response.text();

            console.log(
                'send_message.php response:',
                responseText
            );

            let data;

            try {
                data = JSON.parse(responseText);
            } catch (error) {
                throw new Error(
                    responseText ||
                    'The server returned an invalid response.'
                );
            }

            if (!response.ok || !data.success) {
                throw new Error(
                    data.message || 'Unable to send message.'
                );
            }

            return data;
        })
        .then(() => {
            messageInput.value = '';
            autoResizeChatTextarea(messageInput);

            if (attachmentInput) {
                attachmentInput.value = '';
            }

            clearChatAttachmentPreview();
            chatShouldScroll = true;

            loadChatMessages(true);
            loadChatConversations();
            updateChatUnreadCount();
        })
        .catch(error => {
            console.error('Send message error:', error);
            showAlert(
                error.message || 'Unable to send message.',
                'danger'
            );
        })
        .finally(() => {
            setChatSendingState(false, sendButton);
            messageInput.focus();
        });
}
/**
 * Compatibility function for older onclick="sendMessage()".
 */
function sendMessage() {
    sendChatMessage();
}
/**
 * Disable the send controls while sending.
 */
function setChatSendingState(isSending, sendButton = null) {
    const button =
        sendButton || document.getElementById('chatSendButton');
    const messageInput = document.getElementById('message');
    const attachmentButton =
        document.getElementById('chatAttachmentButton');
    if (button) {
        button.disabled = isSending;
        button.textContent = isSending ? 'Sending...' : 'Send';
    }
    if (messageInput) {
        messageInput.disabled = isSending;
    }
    if (attachmentButton) {
        attachmentButton.disabled = isSending;
    }
}
/**
 * Mark received messages as seen.
 */
function markChatMessagesRead() {
    if (chatConversationId <= 0) {
        return;
    }
    const body =
        'conversation_id=' +
        encodeURIComponent(chatConversationId);
    fetch(chatEndpoint('mark_read.php'), {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: body
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                updateChatUnreadCount();
            }
        })
        .catch(error => {
            console.error('Unable to mark messages as read:', error);
        });
}
/**
 * Update all visible chat unread badges.
 */
function updateChatUnreadCount() {
    fetch(chatEndpoint('get_unread_count.php'), {
        method: 'GET',
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        },
        cache: 'no-store'
    })
        .then(response => response.json())
        .then(data => {
            if (!data.success) {
                return;
            }
            const count = Number(data.count) || 0;
            document
                .querySelectorAll(
                    '.chat-unread-badge, .message-unread-badge'
                )
                .forEach(badge => {
                    badge.textContent = String(count);
                    badge.style.display = count > 0 ? 'inline-flex' : 'none';
                });
            const topbarDot =
                document.querySelector('.message-btn .dot');

            if (topbarDot) {
                topbarDot.style.display =
                    count > 0 ? 'block' : 'none';
            }
        })
        .catch(error => {
            console.error('Unable to update unread count:', error);
        });
}
/**
 * Filter loaded conversations using the search box.
 */
function filterChatConversations(keyword) {
    const normalizedKeyword =
        String(keyword || '').trim().toLowerCase();

    document.querySelectorAll('.chat-user').forEach(item => {
        const searchableText =
            (
                item.dataset.search ||
                item.textContent ||
                ''
            ).toLowerCase();
        item.style.display =
            searchableText.includes(normalizedKeyword)
                ? ''
                : 'none';
    });
    const visibleItems = Array.from(
        document.querySelectorAll('.chat-user')
    ).filter(item => item.style.display !== 'none');
    const noResults =
        document.getElementById('chatNoSearchResults');
    if (noResults) {
        noResults.style.display =
            visibleItems.length === 0 ? 'block' : 'none';
    }
}
/**
 * Show selected attachment before sending.
 */
function showChatAttachmentPreview(input) {
    clearChatAttachmentPreview();
    if (!input || !input.files || !input.files[0]) {
        return;
    }
    const file = input.files[0];
    const maxSize = 10 * 1024 * 1024;
    const allowedExtensions = [
        'jpg',
        'jpeg',
        'png',
        'gif',
        'webp',
        'pdf',
        'doc',
        'docx'
    ];
    const extension =
        file.name.includes('.')
            ? file.name.split('.').pop().toLowerCase()
            : '';
    if (!allowedExtensions.includes(extension)) {
        input.value = '';
        showAlert(
            'Allowed files: JPG, PNG, GIF, WEBP, PDF, DOC, and DOCX.',
            'warning'
        );
        return;
    }
    if (file.size > maxSize) {
        input.value = '';
        showAlert(
            'Attachment must not exceed 10 MB.',
            'warning'
        );
        return;
    }
    const preview =
        document.getElementById('chatAttachmentPreview');
    if (!preview) {
        return;
    }
    preview.innerHTML = `
        <div class="chat-selected-file">
            <span class="chat-selected-file-icon">📎</span>
            <div class="chat-selected-file-info">
                <strong>${escapeChatHtml(file.name)}</strong>
                <small>${formatChatFileSize(file.size)}</small>
            </div>
            <button
                type="button"
                class="chat-remove-file"
                onclick="removeChatAttachment()"
                aria-label="Remove attachment"
            >
                ×
            </button>
        </div>
    `;
    preview.style.display = 'block';
}
/**
 * Remove selected attachment.
 */
function removeChatAttachment() {
    const attachmentInput =
        document.getElementById('attachment');
    if (attachmentInput) {
        attachmentInput.value = '';
    }
    clearChatAttachmentPreview();
}
/**
 * Clear attachment preview.
 */
function clearChatAttachmentPreview() {
    const preview =
        document.getElementById('chatAttachmentPreview');
    if (preview) {
        preview.innerHTML = '';
        preview.style.display = 'none';
    }
}
/**
 * Automatically grow the message textarea.
 */
function autoResizeChatTextarea(textarea) {
    if (!textarea) {
        return;
    }
    textarea.style.height = 'auto';
    const nextHeight = Math.min(textarea.scrollHeight, 120);
    textarea.style.height = nextHeight + 'px';
}
/**
 * Scroll the messages container to the latest message.
 */
function scrollChatToBottom() {
    const chatBody = document.getElementById('chatBody');
    if (!chatBody) {
        return;
    }
    requestAnimationFrame(() => {
        chatBody.scrollTop = chatBody.scrollHeight;
    });
}
/**
 * Show an empty state when no conversation is selected.
 */
function showNoConversationSelected() {
    const chatBody = document.getElementById('chatBody');
    const messageInput = document.getElementById('message');
    if (chatBody) {
        chatBody.innerHTML = `
            <div class="chat-empty-state">
                <div class="chat-empty-icon">💬</div>
                <strong>Select a conversation</strong>
                <span>
                    Choose a student or coordinator to start messaging.
                </span>
            </div>
        `;
    }
    if (messageInput) {
        messageInput.disabled = true;
    }
}
/**
 * Refresh chat data every three seconds.
 */
function startChatRefresh() {
    stopChatRefresh();
    chatRefreshTimer = window.setInterval(() => {
        if (document.hidden) {
            return;
        }
        loadChatConversations();
        if (chatConversationId > 0) {
            loadChatMessages(false);
        }
        updateChatUnreadCount();
    }, 3000);
}
/**
 * Stop the refresh timer.
 */
function stopChatRefresh() {
    if (chatRefreshTimer) {
        window.clearInterval(chatRefreshTimer);
        chatRefreshTimer = null;
    }
}
/**
 * Format attachment file size.
 */
function formatChatFileSize(bytes) {
    const size = Number(bytes) || 0;
    if (size < 1024) {
        return size + ' bytes';
    }
    if (size < 1024 * 1024) {
        return (size / 1024).toFixed(1) + ' KB';
    }
    return (size / (1024 * 1024)).toFixed(1) + ' MB';
}
/**
 * Escape file names before placing them into innerHTML.
 */
function escapeChatHtml(value) {
    const div = document.createElement('div');
    div.textContent = String(value || '');
    return div.innerHTML;
}
/*
|--------------------------------------------------------------------------
| Initialize
|--------------------------------------------------------------------------
*/
document.addEventListener('DOMContentLoaded', function () {
    initChatModule();
});
window.addEventListener('beforeunload', function () {
    stopChatRefresh();
});
/* =========================================================
   MOBILE SIDEBAR
   ========================================================= */

document.addEventListener(
    'DOMContentLoaded',
    function () {

        const sidebar =
            document.getElementById('sidebar');

        const menuButton =
            document.getElementById('mobileMenuBtn');

        const overlay =
            document.getElementById('sidebarOverlay');


        if (
            !sidebar ||
            !menuButton ||
            !overlay
        ) {
            return;
        }


        function openMobileSidebar() {

            sidebar.classList.add(
                'mobile-open'
            );

            overlay.classList.add(
                'open'
            );

            document.body.style.overflow =
                'hidden';
        }


        function closeMobileSidebar() {

            sidebar.classList.remove(
                'mobile-open'
            );

            overlay.classList.remove(
                'open'
            );

            document.body.style.overflow =
                '';
        }


        menuButton.addEventListener(
            'click',
            function () {

                if (
                    sidebar.classList.contains(
                        'mobile-open'
                    )
                ) {

                    closeMobileSidebar();

                } else {

                    openMobileSidebar();

                }
            }
        );


        overlay.addEventListener(
            'click',
            closeMobileSidebar
        );


        /*
         * Close sidebar after selecting
         * a menu item on mobile.
         */

        sidebar
            .querySelectorAll('.nav-item')
            .forEach(function (link) {

                link.addEventListener(
                    'click',
                    function () {

                        if (
                            window.innerWidth <=
                            768
                        ) {

                            closeMobileSidebar();

                        }
                    }
                );

            });


        /*
         * Return to desktop layout
         * if screen becomes wider.
         */

        window.addEventListener(
            'resize',
            function () {

                if (
                    window.innerWidth >
                    768
                ) {

                    closeMobileSidebar();

                }
            }
        );

    }
);