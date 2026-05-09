/**
 * Bark Bot — Chat Widget for Rent-a-Dog
 * A one-eyed pug mascot that helps users find dogs and experiences.
 *
 * States:
 *   idle   — Avatar visible in bottom-right, gentle bounce every 10s
 *   bubble — Speech bubble appears, auto-dismisses after 4s
 *   open   — Full chat panel with message history
 */

(function () {
  'use strict';

  /* -------------------------------------------------- */
  /*  Constants                                         */
  /* -------------------------------------------------- */
  const CHAT_URL =
    (typeof BASE_PATH !== 'undefined' ? BASE_PATH : '') + '/api/chat.php';

  const HELPDESK_URL =
    (typeof BASE_PATH !== 'undefined' ? BASE_PATH : '') + '/api/helpdesk_handler.php';
  const HELPDESK_PAGE =
    (typeof BASE_PATH !== 'undefined' ? BASE_PATH : '') + '/pages/helpdesk.php';

  const QUICK_REPLIES = [
    { text: 'Browse breeds', value: 'I want to browse breeds' },
    { text: 'Dog Cafe info', value: 'Tell me about the Dog Cafe' },
    { text: 'Pricing help', value: 'What are your prices?' },
    { text: "What's available?", value: 'What dogs are available?' },
  ];

  // Escalation keywords that suggest the user needs help desk support
  const ESCALATION_TRIGGERS = /\b(complaint|refund|broken|not working|issue|problem|help me|frustrated|angry|wrong|charged|missing|error|bug|cancel)\b/i;
  var messageCount = 0; // track exchanges for auto-escalation offer

  /* -------------------------------------------------- */
  /*  State                                             */
  /* -------------------------------------------------- */
  let state = 'idle'; // idle | bubble | open
  let hasGreeted = false; // only show greeting once per session
  let conversationHistory = []; // in-memory only, lost on refresh

  /* -------------------------------------------------- */
  /*  DOM References                                    */
  /* -------------------------------------------------- */
  let avatar, bubble, panel, closeBtn, messagesEl, input, sendBtn, quickRepliesEl;

  /* -------------------------------------------------- */
  /*  Initialisation                                    */
  /* -------------------------------------------------- */
  document.addEventListener('DOMContentLoaded', function () {
    avatar = document.getElementById('barkbotAvatar');
    bubble = document.getElementById('barkbotBubble');
    panel = document.getElementById('barkbotPanel');
    closeBtn = document.getElementById('barkbotClose');
    messagesEl = document.getElementById('barkbotMessages');
    input = document.getElementById('barkbotInput');
    sendBtn = document.getElementById('barkbotSend');
    quickRepliesEl = document.getElementById('barkbotQuickReplies');

    // Bail out if essential elements are missing
    if (!avatar || !panel) return;

    // --- Timed intro sequence ---
    // After 5s show speech bubble
    setTimeout(function () {
      if (state === 'idle' && bubble) {
        state = 'bubble';
        bubble.classList.add('visible');
      }
    }, 5000);

    // After 9s (4s after bubble) hide bubble
    setTimeout(function () {
      if (state === 'bubble' && bubble) {
        bubble.classList.remove('visible');
        state = 'idle';
      }
    }, 9000);

    // --- Idle bounce every 10s ---
    setInterval(function () {
      if (state === 'idle' && avatar) {
        avatar.classList.add('barkbot-bounce');
        setTimeout(function () {
          avatar.classList.remove('barkbot-bounce');
        }, 1000);
      }
    }, 10000);

    // --- Click handlers ---
    avatar.addEventListener('click', handleAvatarClick);

    if (bubble) {
      bubble.addEventListener('click', handleAvatarClick);
    }

    if (closeBtn) {
      closeBtn.addEventListener('click', closePanel);
    }

    if (sendBtn) {
      sendBtn.addEventListener('click', handleSend);
    }

    if (input) {
      input.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' && !e.shiftKey) {
          e.preventDefault();
          handleSend();
        }
      });
    }
  });

  /* -------------------------------------------------- */
  /*  Avatar Click                                      */
  /* -------------------------------------------------- */
  function handleAvatarClick() {
    if (state === 'open') {
      closePanel();
    } else {
      openPanel();
    }
  }

  /* -------------------------------------------------- */
  /*  Panel Open / Close                                */
  /* -------------------------------------------------- */
  function openPanel() {
    state = 'open';

    if (bubble) bubble.classList.remove('visible');
    if (panel) panel.classList.add('active');

    // Show greeting on first open
    if (!hasGreeted) {
      hasGreeted = true;
      addMessage(
        "Woof! \uD83D\uDC3E I'm Bark Bot \u2014 your personal pup advisor! I can help you find the perfect dog or experience. What are you looking for?",
        'bot'
      );
      showQuickReplies(QUICK_REPLIES);
    }

    // Focus the input
    if (input) input.focus();
  }

  function closePanel() {
    state = 'idle';
    if (panel) panel.classList.remove('active');
  }

  /* -------------------------------------------------- */
  /*  Message Rendering                                 */
  /* -------------------------------------------------- */
  function addMessage(text, sender) {
    if (!messagesEl) return;

    var msg = document.createElement('div');
    msg.className = 'barkbot-msg ' + sender;

    // Render \n as <br> for bot messages
    if (sender === 'bot') {
      msg.innerHTML = text.replace(/\n/g, '<br>');
    } else {
      msg.textContent = text;
    }

    messagesEl.appendChild(msg);
    messagesEl.scrollTop = messagesEl.scrollHeight;

    // Track in conversation history
    conversationHistory.push({ sender: sender, text: text });
  }

  function showTyping() {
    if (!messagesEl) return;

    var typing = document.createElement('div');
    typing.className = 'barkbot-typing';
    typing.id = 'barkbotTyping';
    typing.innerHTML = '<span></span><span></span><span></span>';
    messagesEl.appendChild(typing);
    messagesEl.scrollTop = messagesEl.scrollHeight;
  }

  function hideTyping() {
    var typing = document.getElementById('barkbotTyping');
    if (typing) typing.remove();
  }

  /* -------------------------------------------------- */
  /*  Quick Replies                                     */
  /* -------------------------------------------------- */
  function showQuickReplies(replies) {
    if (!quickRepliesEl) return;

    quickRepliesEl.innerHTML = '';

    replies.forEach(function (reply) {
      var btn = document.createElement('button');
      btn.className = 'barkbot-quick-reply';
      btn.textContent = reply.text;
      btn.addEventListener('click', function () {
        clearQuickReplies();

        // Handle special escalation actions
        if (reply.value === '__CREATE_TICKET__') {
          handleEscalation();
          return;
        }
        if (reply.value === '__VISIT_HELPDESK__') {
          window.location.href = HELPDESK_PAGE;
          return;
        }

        sendMessage(reply.value);
      });
      quickRepliesEl.appendChild(btn);
    });
  }

  function clearQuickReplies() {
    if (quickRepliesEl) quickRepliesEl.innerHTML = '';
  }

  /* -------------------------------------------------- */
  /*  Sending Messages                                  */
  /* -------------------------------------------------- */
  function handleSend() {
    if (!input) return;
    var text = input.value.trim();
    if (!text) return;

    input.value = '';

    // Intercept special escalation commands
    if (text === '__CREATE_TICKET__') {
      handleEscalation();
      return;
    }
    if (text === '__VISIT_HELPDESK__') {
      window.location.href = HELPDESK_PAGE;
      return;
    }

    sendMessage(text);
  }

  function sendMessage(text) {
    // Show user message
    addMessage(text, 'user');
    clearQuickReplies();
    messageCount++;

    // Intercept ticket status queries - bot cannot look these up
    var lowerText = text.toLowerCase();
    if (/ticket|status|tkt-|track my|check my/.test(lowerText)) {
      showTyping();
      setTimeout(function() {
        hideTyping();
        addMessage(
          "You can check your ticket status on our <a href='" + HELPDESK_PAGE + "' style='color:#8B5E3C;font-weight:600;'>Help Desk page</a>! Enter your ticket number (like TKT-00005) or email address to see the full status and timeline.",
          'bot'
        );
        showQuickReplies([
          { text: 'Visit Help Desk', value: '__VISIT_HELPDESK__' },
          { text: 'Something else', value: 'I have another question' },
        ]);
      }, 800);
      return;
    }

    // Show typing indicator
    showTyping();

    // Send to backend (AI-powered — may take a few seconds)
    fetch(CHAT_URL, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ message: text }),
    })
      .then(function (res) {
        return res.json();
      })
      .then(function (data) {
        hideTyping();
        addMessage(data.response || "Woof! Something went wrong. Try again!", 'bot');

        // Check if we should offer escalation
        var shouldEscalate = ESCALATION_TRIGGERS.test(text) || messageCount >= 5;

        if (shouldEscalate) {
          showEscalationOffer(text);
        } else {
          showFollowUpReplies(text);
        }
      })
      .catch(function () {
        hideTyping();
        addMessage(
          "Woof! I had trouble connecting. Please try again in a moment!",
          'bot'
        );
        showEscalationOffer(text);
      });
  }

  /* -------------------------------------------------- */
  /*  Help Desk Escalation                              */
  /* -------------------------------------------------- */
  function showEscalationOffer(lastMessage) {
    // Show a message offering to create a ticket
    setTimeout(function () {
      addMessage(
        "It sounds like this might need some extra attention from our team. Want me to create a support ticket so we can track this and get it resolved for you?",
        'bot'
      );
      showQuickReplies([
        { text: 'Create ticket', value: '__CREATE_TICKET__' },
        { text: 'No thanks', value: "No thanks, I'm good!" },
        { text: 'Visit Help Desk', value: '__VISIT_HELPDESK__' },
      ]);
    }, 500);
  }

  function handleEscalation() {
    clearQuickReplies();
    addMessage(
      "I'll set that up for you! Just need a couple details:",
      'bot'
    );

    // Build a mini form inside the chat
    var formHtml = '<div class="barkbot-ticket-form" id="barkbotTicketForm">' +
      '<input type="text" id="bbTicketName" placeholder="Your name" class="barkbot-form-input">' +
      '<input type="email" id="bbTicketEmail" placeholder="Your email" class="barkbot-form-input">' +
      '<button class="barkbot-form-submit" id="bbTicketSubmit">Submit Ticket</button>' +
      '</div>';

    var formEl = document.createElement('div');
    formEl.className = 'barkbot-msg bot';
    formEl.innerHTML = formHtml;
    messagesEl.appendChild(formEl);
    messagesEl.scrollTop = messagesEl.scrollHeight;

    // Handle submit
    setTimeout(function () {
      var submitBtn = document.getElementById('bbTicketSubmit');
      if (submitBtn) {
        submitBtn.addEventListener('click', function () {
          var nameVal = document.getElementById('bbTicketName').value.trim();
          var emailVal = document.getElementById('bbTicketEmail').value.trim();

          if (!nameVal || !emailVal) {
            addMessage("Please fill in both your name and email!", 'bot');
            return;
          }

          // Build description from conversation history
          var desc = 'Auto-created from Bark Bot chat.\n\nConversation summary:\n';
          conversationHistory.forEach(function (msg) {
            if (msg.sender === 'user') {
              desc += '  Customer: ' + msg.text + '\n';
            }
          });

          // Determine subject from last user messages
          var lastUserMsg = '';
          for (var i = conversationHistory.length - 1; i >= 0; i--) {
            if (conversationHistory[i].sender === 'user' && conversationHistory[i].text.indexOf('__') === -1) {
              lastUserMsg = conversationHistory[i].text;
              break;
            }
          }
          var subject = lastUserMsg.length > 80
            ? lastUserMsg.substring(0, 77) + '...'
            : (lastUserMsg || 'Bark Bot escalation');

          // Detect category from conversation
          var category = 'general';
          var fullText = desc.toLowerCase();
          if (/bill|charge|refund|payment/.test(fullText)) category = 'billing';
          else if (/broken|bug|error|not working|technical/.test(fullText)) category = 'technical';
          else if (/rental|dog|booking|pickup/.test(fullText)) category = 'rental';
          else if (/complaint|angry|frustrated/.test(fullText)) category = 'complaint';

          showTyping();

          fetch(HELPDESK_URL, {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({
              name: nameVal,
              email: emailVal,
              subject: subject,
              description: desc,
              category: category,
              priority: 'medium',
              source: 'barkbot',
            }),
          })
            .then(function (res) { return res.json(); })
            .then(function (data) {
              hideTyping();
              // Remove the form
              var formDiv = document.getElementById('barkbotTicketForm');
              if (formDiv) formDiv.closest('.barkbot-msg').remove();

              if (data.success) {
                addMessage(
                  "Your ticket " + data.ticket_number + " has been created! Our team will follow up via email. You can also track it anytime on our Help Desk page.\n\nIs there anything else I can help with?",
                  'bot'
                );
                showQuickReplies(QUICK_REPLIES);
                messageCount = 0;
              } else {
                addMessage(
                  "Hmm, I had trouble creating the ticket. You can submit one directly on our Help Desk page instead!",
                  'bot'
                );
                showQuickReplies([
                  { text: 'Visit Help Desk', value: '__VISIT_HELPDESK__' },
                ]);
              }
            })
            .catch(function () {
              hideTyping();
              addMessage(
                "Sorry, I couldn't create the ticket right now. Please visit our Help Desk page to submit it directly.",
                'bot'
              );
              showQuickReplies([
                { text: 'Visit Help Desk', value: '__VISIT_HELPDESK__' },
              ]);
            });
        });
      }
    }, 100);
  }

  /* -------------------------------------------------- */
  /*  Follow-up Quick Replies                           */
  /* -------------------------------------------------- */
  function showFollowUpReplies(lastMessage) {
    var lower = lastMessage.toLowerCase();

    // Suggest context-appropriate follow-ups
    if (/breed|dog|pup|tier/.test(lower)) {
      showQuickReplies([
        { text: 'Basic tier', value: 'Tell me about Basic tier dogs' },
        { text: 'Premium tier', value: 'Tell me about Premium tier dogs' },
        { text: 'VIP tier', value: 'Tell me about VIP tier dogs' },
      ]);
    } else if (/price|cost|rate/.test(lower)) {
      showQuickReplies([
        { text: 'Browse breeds', value: 'I want to browse breeds' },
        { text: 'See experiences', value: 'What experiences do you offer?' },
      ]);
    } else if (/experience|cafe|yoga|garden/.test(lower)) {
      showQuickReplies([
        { text: 'Dog Cafe', value: 'Tell me about the Dog Cafe' },
        { text: 'Dog Yoga', value: 'Tell me about Dog Yoga' },
        { text: 'Dog Garden', value: 'Tell me about the Dog Garden' },
      ]);
    } else if (/hello|hi|hey/.test(lower)) {
      showQuickReplies(QUICK_REPLIES);
    }
    // Otherwise no follow-ups
  }
})();
