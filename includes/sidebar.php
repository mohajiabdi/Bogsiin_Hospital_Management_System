<?php
// includes/sidebar.php


if (session_status() === PHP_SESSION_NONE) session_start();

$u = $_SESSION["user"] ?? null;
$role = $u["role"] ?? "STAFF";

// active helper
$activePath = parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH) ?? "";
function nav_active($path, $activePath) {
  return ($activePath === $path)
    ? "bg-orange-500 text-white shadow-sm"
    : "text-slate-700 hover:bg-slate-50";
}

// NAV ONLY (no brand header here)
function sidebar_links($role, $activePath) { ?>
  <nav class="flex-1 px-4 pb-4 overflow-y-auto">
    <div class="px-2 text-[11px] font-extrabold tracking-widest text-slate-500">OVERVIEW</div>

    <a href="/hospital/dashboard.php"
       class="mt-2 flex items-center gap-3 rounded-2xl px-4 py-3 text-sm font-bold <?php echo nav_active('/hospital/dashboard.php',$activePath); ?>">
      <span class="text-base">🏠</span> Dashboard
    </a>

    <div class="mt-6 px-2 text-[11px] font-extrabold tracking-widest text-slate-500">HOSPITAL</div>

    <a href="/hospital/patients/view.php"
       class="mt-2 flex items-center gap-3 rounded-2xl px-4 py-3 text-sm font-bold <?php echo nav_active('/hospital/patients/view.php',$activePath); ?>">
      <span class="text-base">🧑‍🦽</span> Patients
    </a>

    <a href="/hospital/employees/view.php"
       class="mt-2 flex items-center gap-3 rounded-2xl px-4 py-3 text-sm font-bold <?php echo nav_active('/hospital/employees/view.php',$activePath); ?>">
      <span class="text-base">👨‍⚕️</span> Employees
    </a>

    <a href="/hospital/appointments/view.php"
       class="mt-2 flex items-center gap-3 rounded-2xl px-4 py-3 text-sm font-bold <?php echo nav_active('/hospital/appointments/view.php',$activePath); ?>">
      <span class="text-base">📅</span> Appointments
    </a>

    <a href="/hospital/billing/view.php"
       class="mt-2 flex items-center gap-3 rounded-2xl px-4 py-3 text-sm font-bold <?php echo nav_active('/hospital/billing/view.php',$activePath); ?>">
      <span class="text-base">🧾</span> Billing
    </a>

    <a href="/hospital/prescriptions/view.php"
       class="mt-2 flex items-center gap-3 rounded-2xl px-4 py-3 text-sm font-bold <?php echo nav_active('/hospital/prescriptions/view.php',$activePath); ?>">
      <span class="text-base">💊</span> Prescriptions
    </a>

    <?php if ($role === "ADMIN"): ?>
      <div class="mt-6 px-2 text-[11px] font-extrabold tracking-widest text-slate-500">SYSTEM</div>

      <a href="/hospital/reports/view.php"
         class="mt-2 flex items-center gap-3 rounded-2xl px-4 py-3 text-sm font-bold <?php echo nav_active('/hospital/reports/view.php',$activePath); ?>">
        <span class="text-base">📈</span> Reports
      </a>

      <a href="/hospital/users/view.php"
         class="mt-2 flex items-center gap-3 rounded-2xl px-4 py-3 text-sm font-bold <?php echo nav_active('/hospital/users/view.php',$activePath); ?>">
        <span class="text-base">⚙️</span> Users
      </a>
    <?php endif; ?>
  </nav>
<?php } ?>

<?php
// Bottom user card (reused)
function sidebar_user_card($u, $role) { ?>
  <div class="border-t px-5 py-4">
    <a href="/hospital/profile.php"
       class="group flex items-center gap-3 rounded-2xl p-2 hover:bg-slate-50">
      <div class="h-10 w-10 rounded-full bg-slate-200 group-hover:bg-slate-300"></div>
      <div class="min-w-0">
        <div class="truncate text-sm font-extrabold text-slate-900 group-hover:underline">
          <?php echo htmlspecialchars($u["full_name"] ?? "User"); ?>
        </div>
        <div class="truncate text-xs font-semibold text-slate-500">
          <?php echo htmlspecialchars(strtolower($role)); ?>
        </div>
      </div>
      <div class="ml-auto text-slate-400 group-hover:text-slate-700">›</div>
    </a>

    <div class="mt-3 flex gap-2">
      <a href="/hospital/index.php"
         class="flex-1 rounded-xl border bg-white px-3 py-2 text-center text-xs font-extrabold hover:bg-slate-50">
        Home
      </a>
      <a href="/hospital/auth/logout.php"
         class="flex-1 rounded-xl bg-orange-500 px-3 py-2 text-center text-xs font-extrabold text-white hover:bg-orange-600">
        Logout
      </a>
    </div>
  </div>
<?php } ?>

<!-- ========== MOBILE TOP BAR (small screens only) ========== -->
<div class="md:hidden  sticky top-0 z-40 bg-white border-b">
  <div class="flex items-center gap-3 px-4 py-3">
    <button type="button" id="mobileMenuBtn"
            class="inline-flex items-center justify-center h-10 w-10 rounded-2xl border bg-white hover:bg-slate-50"
            aria-label="Open menu">
      <span class="text-lg">☰</span>
    </button>

    <div class="flex items-center gap-2">
      <img src="/hospital/assets/images/logo.png" alt="Bogsiin Hospital" class="h-8 w-8 object-contain" />
      <div class="font-extrabold text-slate-900 text-sm">Bogsiin Hospital</div>
    </div>

    <div class="ml-auto">
  <a href="/hospital/profile.php"
     class="inline-flex h-10 w-10 items-center justify-center rounded-2xl border bg-white hover:bg-slate-50"
     aria-label="Profile">
    <span class="text-lg">👤</span>
  </a>
</div>

  </div>
</div>

<!-- ========== DESKTOP SIDEBAR (md+) ========== -->
<aside class="hidden md:flex md:w-72 md:flex-col md:border-r md:bg-white md:fixed md:top-0 md:left-0 md:h-screen md:z-40 md:overflow-auto">
  <!-- Brand -->
  <div class="flex items-center gap-3 px-6 py-5">
    <img src="/hospital/assets/images/logo.png" alt="Bogsiin Hospital" class="h-12 w-12 object-contain rounded" />
    <div>
      <div class="font-extrabold leading-tight">Bogsiin Hospital</div>
      <div class="text-xs text-slate-500 -mt-0.5">where Healing begins</div>
    </div>
  </div>

  <?php sidebar_links($role, $activePath); ?>
  <?php sidebar_user_card($u, $role); ?>
</aside>

<!-- ========== MOBILE DRAWER SIDEBAR (small screens) ========== -->
<div id="mobileSidebarOverlay" class="fixed inset-0 z-50 hidden md:hidden ">
  <div id="mobileSidebarBackdrop" class="absolute inset-0 bg-black/40"></div>

  <aside class="absolute left-0 top-0 h-full w-80 max-w-[85%] bg-white border-r shadow-xl">
    <!-- Drawer Header (only here, not duplicated) -->
    <div class="flex items-center justify-between px-4 py-3 border-b">
      <div class="flex items-center gap-2">
        <img src="/hospital/assets/images/logo.png" alt="Bogsiin Hospital" class="h-9 w-9 object-contain rounded" />
        <div>
          <div class="font-extrabold leading-tight">Bogsiin Hospital</div>
          <div class="text-[11px] text-slate-500 -mt-0.5">Menu</div>
        </div>
      </div>

      <button type="button" id="mobileMenuClose"
              class="h-10 w-10 rounded-2xl border bg-white hover:bg-slate-50"
              aria-label="Close menu">✕</button>
    </div>

    <div class="flex h-[calc(100%-56px)] flex-col">
      <?php sidebar_links($role, $activePath); ?>
      <?php sidebar_user_card($u, $role); ?>
    </div>
  </aside>
</div>

<script>
(function () {
  const btn = document.getElementById('mobileMenuBtn');
  const overlay = document.getElementById('mobileSidebarOverlay');
  const closeBtn = document.getElementById('mobileMenuClose');
  const backdrop = document.getElementById('mobileSidebarBackdrop');

  function openMenu() {
    if (!overlay) return;
    overlay.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
  }
  function closeMenu() {
    if (!overlay) return;
    overlay.classList.add('hidden');
    document.body.style.overflow = '';
  }

  if (btn) btn.addEventListener('click', openMenu);
  if (closeBtn) closeBtn.addEventListener('click', closeMenu);
  if (backdrop) backdrop.addEventListener('click', closeMenu);

  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') closeMenu();
  });

  // close on link click inside drawer
  if (overlay) {
    overlay.addEventListener('click', (e) => {
      const a = e.target.closest('a');
      if (a) closeMenu();
    });
  }
})();
</script>
