<div class="bg-gray-800 px-4 py-3 flex flex-wrap md:flex-nowrap gap-2 text-sm font-medium text-white rounded-b-md relative">

    <!-- Actual Nav Items -->
    <div id="navbar-container" class="flex flex-wrap md:flex-nowrap gap-2">
        <?php if ($roles !== 'Employee'): ?>
        <?php endif; ?>
        
        <a href="time.php"
           class="flex items-center gap-2 px-3 py-2 rounded hover:bg-gray-700 text-white">
           <i data-lucide="calendar-range" class="w-4 h-4"></i>
           <span>Attendance Log</span>
        </a>
        
        <a href="timeAnomaly.php"
           class="flex items-center gap-2 px-3 py-2 rounded hover:bg-gray-700 text-white">
           <i data-lucide="calendar-range" class="w-4 h-4"></i>
           <span>Attendance Anomaly</span>
        </a>

        <!-- Dropdown wrapper -->
        <div class="relative inline-block text-left">
            <button id="configBtn" type="button"
                class="flex items-center gap-2 px-3 py-2 rounded hover:bg-gray-700 text-white">
                <i data-lucide="settings" class="w-4 h-4"></i>
                <span></span>
                <i data-lucide="chevron-down" class="w-4 h-4 transition-transform" id="configArrow"></i>
            </button>

            <!-- Dropdown menu -->
            <div id="configMenu"
                 class="hidden absolute mt-2 bg-gray-800 border border-gray-700 rounded-lg shadow-lg w-48 z-50">
                <a href="roleType.php"
                   class="block px-3 py-2 rounded hover:bg-gray-700 text-white"> </a>
                <a href="userProfile.php"
                   class="block px-3 py-2 rounded hover:bg-gray-700 text-white"> </a>
                <a href="permissions.php"
                   class="block px-3 py-2 rounded hover:bg-gray-700 text-white"></a>
            </div>
        </div>
    </div>

    <!-- Skeleton Loader (hidden by default) -->
    <div id="navbar-skeleton" class="hidden flex flex-wrap md:flex-nowrap gap-2 w-full md:w-auto">
        <div class="h-8 w-32 bg-gray-600 rounded animate-pulse"></div>
        <div class="h-8 w-40 bg-gray-600 rounded animate-pulse"></div>
        <div class="h-8 w-24 bg-gray-600 rounded animate-pulse"></div>
    </div>

</div>

<script>
const btn = document.getElementById("configBtn");
const menu = document.getElementById("configMenu");
const arrow = document.getElementById("configArrow");

btn.addEventListener("click", (e) => {
    e.preventDefault();
    menu.classList.toggle("hidden");
    arrow.classList.toggle("rotate-180");
});

// Close dropdown if clicked outside
document.addEventListener("click", (e) => {
    if (!btn.contains(e.target) && !menu.contains(e.target)) {
        menu.classList.add("hidden");
        arrow.classList.remove("rotate-180");
    }
});

// Initialize Lucide icons
if (typeof lucide !== "undefined" && lucide.createIcons) {
    lucide.createIcons();
}

// --- Skeleton Toggle for Offline Mode ---
function toggleNavbarSkeleton(showSkeleton) {
    const navbar = document.getElementById("navbar-container");
    const skeleton = document.getElementById("navbar-skeleton");

    if (showSkeleton) {
        navbar.classList.add("hidden");
        skeleton.classList.remove("hidden");
    } else {
        navbar.classList.remove("hidden");
        skeleton.classList.add("hidden");
    }
}

// Initial state
toggleNavbarSkeleton(!navigator.onLine);

// Listen for online/offline changes
window.addEventListener("offline", () => toggleNavbarSkeleton(true));
window.addEventListener("online", () => toggleNavbarSkeleton(false));
</script>
