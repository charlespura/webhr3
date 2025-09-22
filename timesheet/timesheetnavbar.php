<div class="bg-gray-800 px-4 py-3 flex flex-wrap md:flex-nowrap gap-2 text-sm font-medium text-white rounded-b-md relative">
    <?php if ($roles !== 'Employee'): ?>
      
    <?php endif; ?>
      <a href="timesheet.php"
           class="flex items-center gap-2 px-3 py-2 rounded hover:bg-gray-700 text-white">
           <i data-lucide="calendar-range" class="w-4 h-4"></i>
           <span>Timesheet</span>
        </a>
              <a href="timesheetReport.php"
           class="flex items-center gap-2 px-3 py-2 rounded hover:bg-gray-700 text-white">
           <i data-lucide="calendar-range" class="w-4 h-4"></i>
           <span>Report</span>
        </a>
        
   
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
