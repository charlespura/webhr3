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

<!-- Chatbot Box -->
<div id="chatbotBox" class="fixed bottom-20 right-6 w-80 bg-gray-800 border border-gray-700 rounded-xl shadow-lg opacity-0 scale-95 pointer-events-none transition-all duration-300 overflow-hidden">

  <!-- Header -->
  <div class="p-4 border-b border-gray-700 font-semibold bg-gray-900 text-white">Chatbot</div>

  <!-- Chat content -->
  <div id="chatContent" class="p-4 h-60 overflow-y-auto text-sm text-white flex flex-col gap-2">
    <p>Hello! How can I help you?</p>
  </div>

  <!-- Quick Actions -->
  <div id="quickActions" class="p-2 border-t border-gray-700 bg-gray-900 flex flex-wrap gap-2 items-center">
    <button class="quickBtn bg-green-600 hover:bg-green-500 text-white px-3 py-1 rounded-lg text-sm" data-action="shift_today">My Shift Today</button>
    <button class="quickBtn bg-yellow-600 hover:bg-yellow-500 text-white px-3 py-1 rounded-lg text-sm" data-action="attendance">My Attendance</button>
    <input id="attendanceDate" type="date" placeholder="Select date" class="w-32 px-2 py-1 rounded text-black text-sm focus:outline-none focus:ring-1 focus:ring-blue-500">
    <button id="leaveBalanceBtn" class="quickBtn bg-blue-600 hover:bg-blue-500 text-white px-3 py-1 rounded-lg text-sm" data-action="leavebalance">My Leave Balance</button>

    <!-- Leave Quick Actions -->
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

  <!-- Input -->
  <div class="p-3 border-t border-gray-700 bg-gray-900 flex gap-2">
    <input id="userInput" type="text" placeholder="Type a message..." class="flex-1 rounded-lg px-3 py-2 text-black text-sm focus:outline-none">
    <button id="sendBtn" class="bg-blue-600 hover:bg-blue-500 text-white px-3 py-2 rounded-lg">Send</button>
  </div>
</div>

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

// --- Toggle chatbot ---
toggleBtn.addEventListener('click', () => {
  const isOpen = chatBox.classList.contains('opacity-100');
  if (isOpen) {
    chatBox.classList.remove('opacity-100', 'scale-100', 'pointer-events-auto');
    chatBox.classList.add('opacity-0', 'scale-95', 'pointer-events-none');
  } else {
    chatBox.classList.remove('opacity-0', 'scale-95', 'pointer-events-none');
    chatBox.classList.add('opacity-100', 'scale-100', 'pointer-events-auto');
  }
});

// --- Load chat history ---
function loadChatHistory() {
  const history = JSON.parse(localStorage.getItem('chatHistory') || '[]');
  chatContent.innerHTML = '';
  history.forEach(msg => {
    const p = document.createElement("p");
    p.textContent = msg;
    chatContent.appendChild(p);
  });
  chatContent.scrollTop = chatContent.scrollHeight;
}

// --- Save chat message ---
function saveChatMessage(text) {
  const history = JSON.parse(localStorage.getItem('chatHistory') || '[]');
  history.push(text);
  localStorage.setItem('chatHistory', JSON.stringify(history));
}

// --- Send message ---
async function sendMessage() {
  const message = userInput.value.trim();
  if (!message) return;

  const userMsg = "You: " + message;
  const userP = document.createElement("p");
  userP.textContent = userMsg;
  chatContent.appendChild(userP);
  chatContent.scrollTop = chatContent.scrollHeight;
  saveChatMessage(userMsg);

  userInput.value = "";

  try {
    const response = await fetch("/public_html/chatbot_api.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ message })
    });
    const data = await response.json();
    const reply = "Bot: " + (data.answer || "No response");
    const botP = document.createElement("p");
    botP.textContent = reply;
    chatContent.appendChild(botP);
    chatContent.scrollTop = chatContent.scrollHeight;
    saveChatMessage(reply);
  } catch (err) {
    console.error(err);
    const botMsg = "Bot: Sorry, something went wrong.";
    const botP = document.createElement("p");
    botP.textContent = botMsg;
    chatContent.appendChild(botP);
    chatContent.scrollTop = chatContent.scrollHeight;
    saveChatMessage(botMsg);
  }
}

sendBtn.addEventListener("click", sendMessage);
userInput.addEventListener("keypress", e => { if(e.key==="Enter") sendMessage(); });

// --- Leave buttons ---
leaveBalanceBtn.addEventListener("click", () => {
  leaveQuickActions.classList.toggle("hidden");
  if (!leaveQuickActions.classList.contains("hidden")) {
    document.querySelectorAll(".leaveBtn").forEach(btn => {
      const btnGender = (btn.dataset.gender || "Both").toLowerCase();
      const userGender = loggedInUserGender.toLowerCase();
      if (btnGender === "both" || btnGender === userGender) {
        btn.classList.remove("hidden");
      } else {
        btn.classList.add("hidden");
      }
    });
  }
});

document.querySelectorAll(".leaveBtn").forEach(btn => {
  btn.addEventListener("click", () => {
    const leaveType = btn.dataset.leave;
    const message = leaveType === "all" ? "my leave balance" : `my leave balance in ${leaveType}`;
    userInput.value = message;
    sendMessage();
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
    if (message && action !== "leavebalance") {
      userInput.value = message;
      sendMessage();
    }
  });
});

loadChatHistory();
</script>
</body>
</html>
