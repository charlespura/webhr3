<?php
// Assume $roles contains the role of the currently logged-in user
// e.g., $roles = $_SESSION['user_role'];

if (in_array($roles, ['Admin', 'Manager'])): 
?>


<div class="bg-gray-800 px-4 py-3 flex flex-wrap md:flex-nowrap gap-2 text-sm font-medium text-white rounded-b-md relative">
     

      

      <a href="userProfile.php"
           class="flex items-center gap-2 px-3 py-2 rounded hover:bg-gray-700 text-white">
           <i data-lucide="calendar-range" class="w-4 h-4"></i>
           <span>User Profile</span>
        </a>
        


<?php
// Assume $roles contains the role of the currently logged-in user
// e.g., $roles = $_SESSION['user_role'];

if (in_array($roles, ['Admin'])): 
?>

    <!-- Dropdown wrapper -->
    <div class="relative inline-block text-left">
        <button id="configBtn" type="button"
            class="flex items-center gap-2 px-3 py-2 rounded hover:bg-gray-700 text-white">
            <i data-lucide="settings" class="w-4 h-4"></i>
            <span>Configure</span>
            <i data-lucide="chevron-down" class="w-4 h-4 transition-transform" id="configArrow"></i>
        </button>

        <!-- Dropdown menu -->
        <div id="configMenu"
             class="hidden absolute mt-2 bg-gray-800 border border-gray-700 rounded-lg shadow-lg w-48 z-50">
            <a href="roleType.php"
               class="block px-3 py-2 rounded hover:bg-gray-700 text-white">Role Type</a>
        
        </div>
    </div>

        <?php 
else: 
  
endif; 
?>
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
</script>
  <?php 
else: 
  
endif; 
?>