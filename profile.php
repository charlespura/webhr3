<?php
include __DIR__ . '/dbconnection/mainDb.php';

// Fetch user data for header (only if logged in)
$fullName = "Guest";
$roleName = "";
$profileImage = "/default-avatar.png"; // fallback

if (!empty($_SESSION['user_id'])) {
    $userId = $_SESSION['user_id'];

    // Get profile
    $stmt = $conn->prepare("
        SELECT u.username, u.reference_image, r.name AS role_name, p.first_name, p.last_name
        FROM users u
        LEFT JOIN user_roles ur ON u.user_id = ur.user_id
        LEFT JOIN roles r ON ur.role_id = r.role_id
        LEFT JOIN user_profiles p ON u.user_id = p.user_id
        WHERE u.user_id = ?
        LIMIT 1
    ");
    $stmt->bind_param("s", $userId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && $result->num_rows > 0) {
        $userRow = $result->fetch_assoc();

        $fullName = trim(($userRow['first_name'] ?? '') . ' ' . ($userRow['last_name'] ?? ''));
        if ($fullName === "") {
            $fullName = $userRow['username']; // fallback if no first/last name
        }

        $roleName = $userRow['role_name'] ?? 'Employee';

        if (!empty($userRow['reference_image'])) {
            $profileImage = "/" . $userRow['reference_image']; // stored path
        }
    }
}
?>
<?php include '../loader.php'; ?>

<!-- Right: User Info -->
<div class="relative flex items-center gap-4" id="headerContainer">

  <!-- Clock + SVG -->
  <div class="flex items-center gap-2">
    <span id="clock" class="text-sm text-gray-600 font-mono"></span>

    <!-- Zoomable SVG with tooltip (hover only on SVG) -->
    <div class="relative group">
      <svg id="clockIcon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" class="w-6 h-6 text-gray-600 cursor-pointer">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4"></path>
      </svg>

      <!-- Tooltip only shows when hovering the SVG -->
      <span class="absolute bottom-full mb-2 left-1/2 -translate-x-1/2 w-max bg-gray-700 text-white text-xs rounded px-2 py-1 opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none">
        Toggle Fullscreen
      </span>
    </div>
  </div>




  <!-- User Dropdown Toggle -->
  <button id="userDropdownToggle" class="flex items-center gap-2 focus:outline-none">
    <img src="/public_html/<?php echo htmlspecialchars($profileImage); ?>" 
         alt="profile picture" 
         class="w-8 h-8 rounded-full border object-cover" />
    <div class="flex flex-col items-start">
        <span class="text-sm text-gray-800 font-medium">
            <?php echo htmlspecialchars($fullName); ?>
        </span>
        <span class="text-xs text-gray-500">
            <?php echo htmlspecialchars($roleName); ?>
        </span>
    </div>
    <i data-lucide="chevron-down" class="w-4 h-4 text-gray-600"></i>
  </button>

  <!-- Dropdown -->
  <div id="userDropdown" class="absolute right-0 mt-52 w-40 bg-white rounded shadow-lg hidden z-20">
      <a href="/public_html/user/createUser.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 <?php echo ($currentPage == '/user/createUser.php') ? 'bg-gray-700 text-white' : ''; ?>">Profile</a>
      <a href="/public_html/user/changePassword.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Change Password</a>
      <a href="/public_html/user/setting.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Settings</a>
      <a href="/public_html/logout.php" class="flex items-center gap-3 px-3 py-2 rounded hover:bg-red-600 hover:text-white transition-colors <?php echo ($currentPage == '/logout.php') ? 'bg-red-500 text-white' : 'text-red-500'; ?>">
          <i data-lucide="log-out" class="w-5 h-5"></i>
          <span class="sidebar-text">Logout</span>
      </a>
  </div>
</div>

<!-- Fullscreen Overlay -->
<div id="fullscreenOverlay" class="fixed inset-0 bg-white z-50 hidden flex items-center justify-center transition-all duration-300">
  <div class="flex items-center gap-6 text-4xl font-bold" id="overlayContent">
    <span id="overlayClock"></span>
    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" class="w-16 h-16 text-gray-800">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4"></path>
    </svg>
  </div>
</div>

<script>function updateClock() {
  const now = new Date();

  // Time
  let hours = now.getHours().toString().padStart(2,'0');
  let minutes = now.getMinutes().toString().padStart(2,'0');
  let seconds = now.getSeconds().toString().padStart(2,'0');
  let timeString = `${hours}:${minutes}:${seconds}`;

  // Day + Date
  const days = ["Sunday","Monday","Tuesday","Wednesday","Thursday","Friday","Saturday"];
  const months = ["Jan","Feb","Mar","Apr","May","Jun","Jul","Aug","Sep","Oct","Nov","Dec"];
  
  let dayName = days[now.getDay()];
  let day = now.getDate().toString().padStart(2,'0');
  let month = months[now.getMonth()];
  let year = now.getFullYear();
  let dateString = `${dayName}, ${day} ${month} ${year}`;

  // Update HTML
  document.getElementById('clock').textContent = `${dateString} | ${timeString}`;
  document.getElementById('overlayClock').textContent = `${dateString} | ${timeString}`;
}

setInterval(updateClock, 1000);
updateClock();


// Fullscreen toggle
const clockIcon = document.getElementById("clockIcon");
let isFullscreen = false;

clockIcon.addEventListener("click", () => {
  if (!document.fullscreenElement) {
    document.documentElement.requestFullscreen().catch(err => {
      console.error(`Error attempting to enable full-screen mode: ${err.message}`);
    });
    isFullscreen = true;
  } else {
    document.exitFullscreen();
    isFullscreen = false;
  }
});

// Listen for fullscreen changes
document.addEventListener('fullscreenchange', () => {
  isFullscreen = !!document.fullscreenElement;
});

// Handle dropdown clicks without exiting fullscreen
document.addEventListener("DOMContentLoaded", function () {
    const userDropdownToggle = document.getElementById("userDropdownToggle");
    const userDropdown = document.getElementById("userDropdown");
    const dropdownLinks = userDropdown.querySelectorAll('a');

    if(userDropdownToggle && userDropdown) {
        userDropdownToggle.addEventListener("click", function (event) {
            event.stopPropagation();
            userDropdown.classList.toggle("hidden");
        });

        // Close dropdown when clicking outside
        document.addEventListener("click", function (event) {
            if (!userDropdown.contains(event.target) && !userDropdownToggle.contains(event.target)) {
                userDropdown.classList.add("hidden");
            }
        });
        
        // Handle dropdown link clicks
        dropdownLinks.forEach(link => {
            link.addEventListener('click', function(e) {
                // For logout, allow normal behavior
                if (this.href.includes('logout.php')) {
                    return true;
                }
                
                // For other links, prevent default and handle with AJAX
                e.preventDefault();
                
                // Close dropdown
                userDropdown.classList.add("hidden");
                
                // If in fullscreen, load content via AJAX instead of navigating
                if (isFullscreen) {
                    loadContent(this.href);
                } else {
                    // Not in fullscreen, normal navigation
                    window.location.href = this.href;
                }
            });
        });
    }
});

// Function to load content via AJAX
function loadContent(url) {
    fetch(url)
        .then(response => response.text())
        .then(html => {
            // This would need to be customized based on your page structure
            // For demonstration, we're just updating the main content area
            const parser = new DOMParser();
            const doc = parser.parseFromString(html, 'text/html');
            const newContent = doc.querySelector('main') || doc.body;
            
            // Update the page content without reloading
            document.querySelector('main').innerHTML = newContent.innerHTML;
            
            // Update the page title
            document.title = doc.title;
            
            // Update browser history
            window.history.pushState({}, '', url);
            
            // Reinitialize any necessary scripts
            initializePageScripts();
        })
        .catch(err => {
            console.error('Failed to load page: ', err);
        });
}

// Function to reinitialize scripts after AJAX load
function initializePageScripts() {
    // Reinitialize any scripts that need to run after content load
    // This will depend on your specific page functionality
}

// Handle browser back/forward buttons
window.addEventListener('popstate', function(event) {
    if (isFullscreen) {
        loadContent(window.location.href);
    }
});
</script>

