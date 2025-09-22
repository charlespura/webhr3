<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);


// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit;
}

$employeeId = $_SESSION['employee_id'] ?? null;
$roles = $_SESSION['roles'] ?? 'Employee';

// ==========================
// DB Connections
// ==========================
include __DIR__ . '/dbconnection/dbEmployee.php';
$empConn = $conn;

include __DIR__ . '/dbconnection/mainDB.php';
$shiftConn = $conn;

// ==========================
// Fetch Employees
// ==========================
$employees = $empConn->query("SELECT employee_id, first_name, last_name, gender FROM employees")->fetch_all(MYSQLI_ASSOC);

// Logged-in employee gender
$employeeGender = 'Both';
foreach ($employees as $emp) {
    if ($emp['employee_id'] === $employeeId) {
        $employeeGender = $emp['gender'] ?? 'Both';
        break;
    }
}

// ==========================
// Fetch Leave Types
// ==========================
$leaveTypes = $shiftConn->query("SELECT leave_type_id, leave_name, gender FROM leave_types ORDER BY leave_name")->fetch_all(MYSQLI_ASSOC);
?>
<script>
const loggedInUserGender = "<?php echo $employeeGender; ?>";
</script>


<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Chatbot</title>
<script src="https://cdn.tailwindcss.com"></script>
<style>
  .chat-bubble {
    max-width: 75%;
    padding: 8px 12px;
    border-radius: 12px;
    font-size: 14px;
    line-height: 1.4;
    word-break: break-word;
  }
  .chat-bot {
    background: #374151; /* gray-700 */
    align-self: flex-start;
    border-bottom-left-radius: 0.4rem;
  }
  .chat-user {
    background: #2563eb; /* blue-600 */
    align-self: flex-end;
    border-bottom-right-radius: 0.4rem;
  }
  /* Typing dots */
  .typing {
    display: flex;
    gap: 4px;
    align-items: center;
  }
  .dot {
    width: 6px;
    height: 6px;
    background: #fff;
    border-radius: 50%;
    animation: blink 1.4s infinite;
  }
  .dot:nth-child(2) { animation-delay: 0.2s; }
  .dot:nth-child(3) { animation-delay: 0.4s; }
  @keyframes blink {
    0%, 80%, 100% { opacity: 0.2; }
    40% { opacity: 1; }
  }
</style>

<style>
  .fullscreen {
    width: 100vw !important;
    height: 97vh !important;
    bottom: 0 !important;
    right: 0 !important;
    border-radius: 0 !important;
    display: flex;
    flex-direction: column;
    z-index: 9999; /* make it appear above everything */
    background-color: #1f2937; /* solid background so page doesn't show */
  }
</style>

</head>

<body class="bg-gray-100 min-h-screen">

<!-- Chatbot Toggle Button -->
<div class="relative flex justify-end items-end">
  <button id="chatbotToggle" class="fixed bottom-6 right-6 bg-gray-800 rounded-full p-2 shadow-lg hover:bg-gray-700 transition group">
    <img src="/public_html/picture/logo2.png" alt="Chatbot" class="w-10 h-10 object-contain">
    <span class="absolute bottom-full mb-2 right-1/2 transform translate-x-1/2 
                 bg-gray-900 text-white text-xs rounded py-1 px-2 opacity-0 
                 pointer-events-none group-hover:opacity-100 transition-opacity
                 whitespace-nowrap shadow-lg">Chat with us</span>
  </button>
</div>


<div id="chatbotBox" class="fixed bottom-20 right-6 w-96 h-[30rem] 
  bg-gray-800 border border-gray-700 rounded-xl shadow-lg
  opacity-0 scale-95 pointer-events-none transition-all duration-300 
  overflow-hidden flex flex-col z-[9999]">

  <!-- Chatbot Header with Zoom Button -->
  <div class="flex items-center justify-between gap-3 p-4 border-b border-gray-700 bg-gray-900 text-white font-semibold">
    <div class="flex items-center gap-3">
      <img src="../picture/logo2.png" alt="Bot Logo" class="w-8 h-8 rounded-full">
      <span>ATIERAbot</span>
    </div>

<!-- Buttons container -->
<div class="flex items-center gap-2">
  <!-- Zoom / Fullscreen button -->
  <button id="zoomBtn" class="bg-gray-700 hover:bg-gray-600 p-2 rounded-full" title="Fullscreen">
    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
      <path stroke-linecap="round" stroke-linejoin="round" d="M8 3H5a2 2 0 00-2 2v3m0 8v3a2 2 0 002 2h3m8-16h3a2 2 0 012 2v3m0 8v3a2 2 0 01-2 2h-3"/>
    </svg>
  </button>

  <!-- Close button -->
  <button id="closeBtn" class="bg-gray-700 hover:bg-gray-600 p-2 rounded-full" title="Close Chat">
    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
      <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
    </svg>
  </button>
</div>

  </div>

  <div id="chatContent" class="p-4 overflow-y-auto text-sm text-white flex flex-col gap-2 flex-1">
    <div class="chat-bubble chat-bot">Hello! How can I help you?</div>
  </div>

  <div id="quickActionsWrapper" class="p-2 border-t border-gray-700 bg-gray-900 overflow-hidden transition-all duration-300 ease-in-out">

    <div id="quickActions" class="flex flex-wrap gap-2 items-center">
      <button class="quickBtn bg-green-600 hover:bg-green-500 text-white px-3 py-1 rounded-lg text-sm" data-action="shift_today">My Shift Today</button>
      <button class="quickBtn bg-yellow-600 hover:bg-yellow-500 text-white px-3 py-1 rounded-lg text-sm" data-action="attendance">My Attendance</button>
      <input id="attendanceDate" type="date" placeholder="Select date" class="w-32 px-2 py-1 rounded text-black text-sm focus:outline-none focus:ring-1 focus:ring-blue-500">
      <button id="leaveBalanceBtn" class="quickBtn bg-blue-600 hover:bg-blue-500 text-white px-3 py-1 rounded-lg text-sm" data-action="leavebalance">My Leave Balance</button>

      <div id="leaveQuickActions" class="flex flex-wrap gap-2 hidden w-full mt-2">
        <?php foreach ($leaveTypes as $lt): 
          $gender = $lt['gender'] ?? 'Both';
          $name = htmlspecialchars($lt['leave_name']);
        ?>
          <button class="leaveBtn bg-blue-500 hover:bg-blue-400 text-white px-3 py-1 rounded-lg text-sm" 
                  data-leave="<?= $name ?>" data-gender="<?= $gender ?>">
            <?= $name ?>
          </button>
        <?php endforeach; ?>
        <button class="leaveBtn bg-blue-500 hover:bg-blue-400 text-white px-3 py-1 rounded-lg text-sm" data-leave="all">All</button>
      </div>
    </div>

    <div id="collapsedQuickActions" class="hidden text-center">
      <button class="text-blue-400 text-sm hover:underline">View more</button>
    </div>
  </div>

  <div class="p-3 border-t border-gray-700 bg-gray-900 flex gap-2">
    <input id="userInput" type="text" placeholder="Type a message..." class="flex-1 rounded-lg px-3 py-2 text-black text-sm focus:outline-none">
    <button id="sendBtn" class="bg-blue-600 hover:bg-blue-500 text-white px-3 py-2 rounded-lg">Send</button>
  </div>
</div>


<script>
  const chatbotBox = document.getElementById('chatbotBox');
  const zoomBtn = document.getElementById('zoomBtn');

  let fullscreen = false;

  zoomBtn.addEventListener('click', () => {
    fullscreen = !fullscreen;
    chatbotBox.classList.toggle('fullscreen');

    if (fullscreen) {
      // Prevent scrolling of the background page
      document.body.style.overflow = 'hidden';
    } else {
      document.body.style.overflow = 'auto';
    }
  });
</script>


<script>
const toggleBtn = document.getElementById('chatbotToggle');
const chatBox = document.getElementById('chatbotBox');
const chatContent = document.getElementById('chatContent');
const userInput = document.getElementById('userInput');
const sendBtn = document.getElementById('sendBtn');

const leaveBalanceBtn = document.getElementById("leaveBalanceBtn");
const leaveQuickActions = document.getElementById("leaveQuickActions");
const quickBtns = document.querySelectorAll(".quickBtn");
const attendanceDateInput = document.getElementById("attendanceDate");

const quickActionsWrapper = document.getElementById("quickActionsWrapper");

// --- Toggle chatbot ---
// --- Initialize chatbot state from localStorage ---
// --- Initialize chatbot state from localStorage ---
const isOpenStored = localStorage.getItem('chatbotOpen');
if (isOpenStored === 'true') {
    chatBox.classList.remove('opacity-0', 'scale-95', 'pointer-events-none');
    chatBox.classList.add('opacity-100', 'scale-100', 'pointer-events-auto');
}

// --- Close button functionality ---
closeBtn.addEventListener('click', () => {
    chatBox.classList.remove('opacity-100', 'scale-100', 'pointer-events-auto');
    chatBox.classList.add('opacity-0', 'scale-95', 'pointer-events-none');
    localStorage.setItem('chatbotOpen', 'false');
});
// --- Toggle chatbot ---
toggleBtn.addEventListener('click', () => {
  const isOpen = chatBox.classList.contains('opacity-100');
  if (isOpen) {
    chatBox.classList.remove('opacity-100', 'scale-100', 'pointer-events-auto');
    chatBox.classList.add('opacity-0', 'scale-95', 'pointer-events-none');
    localStorage.setItem('chatbotOpen', 'false'); // save state
  } else {
    chatBox.classList.remove('opacity-0', 'scale-95', 'pointer-events-none');
    chatBox.classList.add('opacity-100', 'scale-100', 'pointer-events-auto');
    localStorage.setItem('chatbotOpen', 'true'); // save state
  }
});

// --- Load chat history from server ---
async function loadChatHistory() {
  try {
    const res = await fetch('/public_html/get_chat_history.php');
    const history = await res.json();
    chatContent.innerHTML = '';
    history.forEach(msg => {
      const div = document.createElement("div");
      div.className = msg.sender === 'user' ? "chat-bubble chat-user" : "chat-bubble chat-bot";
      div.textContent = msg.message;
      chatContent.appendChild(div);
    });
    chatContent.scrollTop = chatContent.scrollHeight;
  } catch (err) {
    console.error('Failed to load chat history', err);
  }
}

// --- Typing indicator ---
const typingIndicator = document.createElement("div");
typingIndicator.className = "chat-bubble chat-bot typing";
typingIndicator.innerHTML = `<span class="dot"></span><span class="dot"></span><span class="dot"></span>`;

// --- Send message ---
async function sendMessage(message = null) {
  const text = message || userInput.value.trim();
  if (!text) return;

  if (!message) userInput.value = "";

  // Append user message immediately
  const userDiv = document.createElement("div");
  userDiv.className = "chat-bubble chat-user";
  userDiv.textContent = text;
  chatContent.appendChild(userDiv);
  chatContent.scrollTop = chatContent.scrollHeight;

  // Show typing dots
  chatContent.appendChild(typingIndicator);
  chatContent.scrollTop = chatContent.scrollHeight;

  try {
    const response = await fetch("/public_html/chatbot_api.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ message: text })
    });
    const data = await response.json();
    typingIndicator.remove();

    const botDiv = document.createElement("div");
    botDiv.className = "chat-bubble chat-bot";
    botDiv.textContent = data.answer || "No response";
    chatContent.appendChild(botDiv);
    chatContent.scrollTop = chatContent.scrollHeight;

  } catch (err) {
    console.error(err);
    typingIndicator.remove();
    const botDiv = document.createElement("div");
    botDiv.className = "chat-bubble chat-bot";
    botDiv.textContent = "Sorry, something went wrong.";
    chatContent.appendChild(botDiv);
    chatContent.scrollTop = chatContent.scrollHeight;
  }
}

// --- Event listeners ---
sendBtn.addEventListener("click", () => sendMessage());
userInput.addEventListener("keypress", e => { if (e.key === "Enter") sendMessage(); });

// --- Leave buttons ---
leaveBalanceBtn.addEventListener("click", () => {
  leaveQuickActions.classList.toggle("hidden");
  if (!leaveQuickActions.classList.contains("hidden")) {
    document.querySelectorAll(".leaveBtn").forEach(btn => {
      const btnGender = (btn.dataset.gender || "Both").toLowerCase();
      const userGender = loggedInUserGender.toLowerCase();
      if (btnGender === "both" || btnGender === userGender) btn.classList.remove("hidden");
      else btn.classList.add("hidden");
    });
  }
});

document.querySelectorAll(".leaveBtn").forEach(btn => {
  btn.addEventListener("click", () => {
    const leaveType = btn.dataset.leave;
    const message = leaveType === "all" ? "my leave balance" : `my leave balance in ${leaveType}`;
    sendMessage(message);
    leaveQuickActions.classList.add("hidden");
  });
});

// --- Quick buttons ---
quickBtns.forEach(btn => {
  btn.addEventListener("click", () => {
    const action = btn.dataset.action;
    let message = "";
    if (action === "shift_today") message = "get_schedule_today";
    else if (action === "attendance") {
      const date = attendanceDateInput.value;
      if (!date) { alert("Please select a date first!"); return; }
      message = `get_attendance on ${date}`;
    }
    if (message && action !== "leavebalance") sendMessage(message);
  });
});

// // --- Collapse quick actions on scroll ---
// let lastScrollTop = 0;
// chatContent.addEventListener("scroll", () => {
//   const currentScroll = chatContent.scrollTop;

//   if (currentScroll > lastScrollTop) {
//     quickActionsWrapper.style.height = "auto";
//     quickActionsWrapper.style.opacity = "1";
//     quickActionsWrapper.style.transform = "translateY(0)";
//   } else {
//     quickActionsWrapper.style.height = "0";
//     quickActionsWrapper.style.opacity = "0";
//     quickActionsWrapper.style.transform = "translateY(20px)";
//   }

//   lastScrollTop = currentScroll <= 0 ? 0 : currentScroll;
// });

// --- Load chat history from server ---
async function loadChatHistory() {
  try {
    const res = await fetch('/public_html/get_chat_history.php');
    const history = await res.json();
    chatContent.innerHTML = '';
    history.forEach(msg => {
      const div = document.createElement("div");
      div.className = msg.sender === 'user' ? "chat-bubble chat-user" : "chat-bubble chat-bot";
      div.textContent = msg.message;
      chatContent.appendChild(div);
    });
    // Scroll to the bottom
    chatContent.scrollTop = chatContent.scrollHeight;
  } catch (err) {
    console.error('Failed to load chat history', err);
  }
}
loadChatHistory();
</script>

</body>
</html>
