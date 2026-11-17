const chatToggle = document.getElementById("einq-chat-toggle");
const chatBox = document.getElementById("einq-chat-box");
const closeChat = document.getElementById("einq-close-chat");
const sendBtn = document.getElementById("einq-send-btn");
const chatInput = document.getElementById("einq-chat-input");
const chatBody = document.getElementById("einq-chat-body");
const chatFooter = document.getElementById("einq-chat-footer");

// STATE
let greeted = false;
let forgotPasswordFlow = false;
let trackRequestFlow = false;
let awaitingResponse = false;

const JAZMINE_AVATAR = "images/jazmine.png";
const SUPPORT_ENDPOINT = "/cityofsantarosa/action/support_widget";

const SUPPORT_MAINTENANCE_MODE = window.MAINTENANCE_MODE === true || window.MAINTENANCE_MODE === 'true';
const SUPPORT_MAINTENANCE_MESSAGE = window.MAINTENANCE_MESSAGE || 'The system is currently under maintenance. Please try again later.';

/* --------------------------------------------------
   HELPERS
-------------------------------------------------- */

function escapeHTML(str) {
    return str
        .replaceAll("&", "&amp;")
        .replaceAll("<", "&lt;")
        .replaceAll(">", "&gt;");
}

/* BOT MESSAGE (FACEBOOK STYLE ROW) */
function createBotMessage(content, isHTML = true) {
    let row = document.createElement("div");
    row.classList.add("bot-row");

    row.innerHTML = `
        <img class="bot-avatar" src="${JAZMINE_AVATAR}" alt="Jazmine"><div class="bot-bubble">${isHTML ? content : escapeHTML(content)}</div>`;

    chatBody.appendChild(row);
    chatBody.scrollTop = chatBody.scrollHeight;
    return row;
}

/* USER MESSAGE (used both for typed text and for the label of a tapped button) */
function createUserMessage(text) {
    let userDiv = document.createElement("div");
    userDiv.classList.add("user-message");
    userDiv.innerText = text;
    chatBody.appendChild(userDiv);
    chatBody.scrollTop = chatBody.scrollHeight;
}

/* Renders a row of tappable quick-reply buttons in the chat FOOTER
   (above the input box), instead of inline in the chat-body.
   `buttons` is the quick_replies array the backend returns:
   [{ label: '📄 Track My Request', intent: 'TRACK_REQUEST' }, ...] */
function renderQuickReplies(buttons) {
    removeQuickReplies(); // only one active button set on screen at a time

    if (!Array.isArray(buttons) || buttons.length === 0) return;

    let wrap = document.createElement("div");
    wrap.classList.add("quick-reply-row");
    wrap.id = "active-quick-replies";

    buttons.forEach(btn => {
        let b = document.createElement("button");
        b.type = "button";
        b.classList.add("quick-btn");
        b.textContent = btn.label;
        b.addEventListener("click", () => handleQuickReplyClick(btn));
        wrap.appendChild(b);
    });

    // Prepend so it sits above the input/send button inside the footer
    chatFooter.prepend(wrap);
}

function removeQuickReplies() {
    const existing = document.getElementById("active-quick-replies");
    if (existing) existing.remove();
}

const DEFAULT_INPUT_PLACEHOLDER = chatInput ? (chatInput.placeholder || "Type a message...") : "Type a message...";

function setInputPrompt(text) {
    if (!chatInput) return;
    chatInput.placeholder = text;
    chatInput.classList.add("awaiting-input");
    document.getElementById("einq-input-row")?.classList.add("active");
    chatInput.focus();
}

function resetInputPrompt() {
    if (!chatInput) return;
    chatInput.placeholder = DEFAULT_INPUT_PLACEHOLDER;
    chatInput.classList.remove("awaiting-input");
    document.getElementById("einq-input-row")?.classList.remove("active");
}

function disableQuickReplies() {
    const existing = document.getElementById("active-quick-replies");
    if (!existing) return;
    existing.querySelectorAll(".quick-btn").forEach(b => {
        b.disabled = true;
        b.style.opacity = "0.5";
        b.style.pointerEvents = "none";
    });
}

/* --------------------------------------------------
   OPEN / CLOSE
-------------------------------------------------- */

chatToggle.addEventListener("click", () => {
    chatBox.style.display = "flex";
    if (!greeted) {
        greeted = true;
        showTyping();
        // Ask the backend for the main menu itself so the greeting text
        // and buttons always match whatever's in $INTENT_MAP server-side.
        sendIntentRequest("MENU_MAIN").then(data => {
            removeTyping();
            if (data) {
                createBotMessage(data.reply.replace(/\n/g, '<br>'), true);
                renderQuickReplies(data.quick_replies);
            }
        });
    }
});

closeChat.addEventListener("click", () => {
    chatBox.style.display = "none";
});

/* --------------------------------------------------
   TYPING INDICATOR
-------------------------------------------------- */

function showTyping() {
    removeTyping();
    let typingDiv = document.createElement("div");
    typingDiv.classList.add("bot-row");
    typingDiv.id = "typing-indicator";
    typingDiv.innerHTML = `
        <img class="bot-avatar" src="${JAZMINE_AVATAR}" alt="Jazmine">
        <div class="bot-bubble"><span class="typing-text">Jazmine is typing </span><span class="typing-dots"><span>.</span><span>.</span><span>.</span></span></div>
    `;
    chatBody.appendChild(typingDiv);
    chatBody.scrollTop = chatBody.scrollHeight;
}

function removeTyping() {
    let typing = document.getElementById("typing-indicator");
    if (typing) typing.remove();
}

/* --------------------------------------------------
   BUTTON (INTENT) HANDLING — the main tap-driven path
-------------------------------------------------- */

function handleQuickReplyClick(btn) {
    if (awaitingResponse) return;

    // Determine flow based on the button's LABEL rather than its intent
    // code, since the intent key configured in the admin dashboard may
    // not exactly match "FORGOT_PASSWORD" / "TRACK_REQUEST".
    const labelLower = (btn.label || "").toLowerCase();
    const isForgotPassword = labelLower.includes("password");
    const isTrackRequest = labelLower.includes("track");

    // Cancel the forgot-password flow if the user tapped anything
    // else instead of actually typing an email — prevents a stray
    // typed message later from being wrongly treated as a reset email.
    if (!isForgotPassword) {
        forgotPasswordFlow = false;
    }
    
    if (!isTrackRequest) {
        trackRequestFlow = false;
    }

    awaitingResponse = true;
    disableQuickReplies();
    createUserMessage(btn.label);

    if (isForgotPassword) {
        forgotPasswordFlow = true;
        setInputPrompt("Enter your email address...");
    } else if (isTrackRequest) {
        trackRequestFlow = true;
        setInputPrompt("Enter your tracking code (e.g. REQ20260908...)");
    } else {
        resetInputPrompt();
    }

    showTyping();
    sendIntentRequest(btn.intent).then(data => {
        removeTyping();
        awaitingResponse = false;

        if (!data) {
            createBotMessage("Sorry 😕 Something went wrong while contacting Jazmine.", false);
            renderQuickReplies([{ label: '🏠 Main Menu', intent: 'MENU_MAIN' }]);
            return;
        }
        createBotMessage(data.reply.replace(/\n/g, '<br>'), true);
        renderQuickReplies(data.quick_replies);
    });
}

function sendIntentRequest(intent) {
    return fetch(SUPPORT_ENDPOINT, {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: "intent=" + encodeURIComponent(intent)
    })
    .then(r => r.json())
    .catch(() => null);
}

/* --------------------------------------------------
   TYPED MESSAGE HANDLING — still needed for tracking codes,
   password-reset emails, or anything not covered by a button
-------------------------------------------------- */

sendBtn.addEventListener("click", sendMessage);

chatInput.addEventListener("keypress", e => {
    if (e.key === "Enter") sendMessage();
});

function sendMessage() {
    if (awaitingResponse) return;
    
    let message = chatInput.value.trim();
    if (message === "") return;

    awaitingResponse = true; 
    removeQuickReplies();
    createUserMessage(message);
    chatInput.value = "";

    /* EMAIL RESET STEP */
    let emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

    // Currently inside the "forgot password" flow but what was typed
    // doesn't look like an email at all — don't proceed to the server,
    // just complain and let them try typing again without exiting the flow.
    if (forgotPasswordFlow && !emailRegex.test(message)) {
        showTyping();
        setTimeout(() => {
            removeTyping();
            awaitingResponse = false;
            setInputPrompt("Enter your email address...");
            createBotMessage("Hmm, that doesn't look like a valid email 😕 Please enter your email address (e.g. juan@example.com).", false);
            renderQuickReplies([{ label: '🏠 Main Menu', intent: 'MENU_MAIN' }]);
        }, 500);
        return;
    }

    // Currently inside the "track request" flow but what was typed
    // doesn't look like a tracking code — same deal, ask again.
    let trackingCodeRegex = /^REQ\d/i;
    if (trackRequestFlow && !trackingCodeRegex.test(message)) {
        showTyping();
        setTimeout(() => {
            removeTyping();
            awaitingResponse = false;
            setInputPrompt("Enter your tracking code (e.g. REQ20260908...)");
            createBotMessage("Hmm, that doesn't look like a tracking code 😕 It should start with \"REQ\" followed by numbers (e.g. REQ202609081 - 003).", false);
            renderQuickReplies([{ label: '🏠 Main Menu', intent: 'MENU_MAIN' }]);
        }, 500);
        return;
    }

    if (forgotPasswordFlow && emailRegex.test(message)) {
        if (SUPPORT_MAINTENANCE_MODE) {
            showTyping();
            setTimeout(() => {
                removeTyping();
                awaitingResponse = false;
                resetInputPrompt();
                createBotMessage(SUPPORT_MAINTENANCE_MESSAGE);
                renderQuickReplies([{ label: '🏠 Main Menu', intent: 'MENU_MAIN' }]);
            }, 1200);
            forgotPasswordFlow = false;
            return;
        }

        showTyping();
        forgotPasswordFlow = false;

        fetch("/cityofsantarosa/auth/forgot_password", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ email: message })
        })
        .then(res => res.json())
        .then(data => {
            removeTyping();
            awaitingResponse = false;
            resetInputPrompt();
            createBotMessage(data.message || data.reply || "Please check your email inbox", false);
            renderQuickReplies([{ label: '🏠 Main Menu', intent: 'MENU_MAIN' }]);
        })
        .catch(() => {
            removeTyping();
            awaitingResponse = false;
            resetInputPrompt();
            createBotMessage("Something went wrong while sending reset instructions 😕", false);
            renderQuickReplies([{ label: '🏠 Main Menu', intent: 'MENU_MAIN' }]);
        });

        return;
    }

    // Valid tracking code typed — clear the flow and let it fall through
    // to the normal message path below, which already handles tracking
    // lookups via SUPPORT_ENDPOINT.
    if (trackRequestFlow) {
        trackRequestFlow = false;
    }

    /* NORMAL / TRACKING-CODE MESSAGE */
    showTyping();

    fetch(SUPPORT_ENDPOINT, {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: "message=" + encodeURIComponent(message)
    })
    .then(res => res.json())
    .then(data => {
        let delay = Math.min(1200 + (data.reply || "").length * 10, 3000);
        setTimeout(() => {
            removeTyping();
            awaitingResponse = false;
            resetInputPrompt();
            createBotMessage((data.reply || "").replace(/\n/g, '<br>'), true);
            renderQuickReplies(data.quick_replies);
        }, delay);
    })
    .catch(() => {
        removeTyping();
        awaitingResponse = false;
        resetInputPrompt();
        createBotMessage("Sorry 😕 Something went wrong while contacting Jazmine.", false);
    });
}