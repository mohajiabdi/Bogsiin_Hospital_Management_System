<?php

if (session_status() === PHP_SESSION_NONE) session_start();
$user = $_SESSION["user"] ?? null;

$initials = "U";
if ($user && !empty($user["full_name"])) {
  $parts = preg_split('/\s+/', trim($user["full_name"]));
  $initials = strtoupper(substr($parts[0], 0, 1) . (isset($parts[1]) ? substr($parts[1], 0, 1) : ""));
}
?>
<header class="border-b bg-white">
  <div class="mx-auto flex max-w-6xl items-center justify-between px-4 py-4">
    <div class="flex items-center gap-3">
      <div class="h-12 w-12 rounded-2xl bg-white grid place-items-center overflow-hidden">
        <img src="/hospital/assets/images/logo.png" alt="Bogsiin Hospital logo" class="h-full w-full object-contain" />
      </div>
      <div>
        <div class="font-extrabold leading-tight">
          <a href="/hospital/index.php" class="hover:opacity-90">Bogsiin Hospital</a>
        </div>
        <div class="text-xs text-slate-500 -mt-0.5">where Healing begins</div>
      </div>
    </div>

    <!-- Desktop nav -->
    <nav class="hidden items-center gap-6 text-sm font-semibold text-slate-600 md:flex">
      <a class="hover:text-slate-900" href="/hospital/index.php#about">Projects</a>
      <a class="hover:text-slate-900" href="/hospital/index.php#features">Features</a>
      <a class="hover:text-slate-900" href="/hospital/index.php#team">Team</a>
    </nav>

    <div class="flex items-center gap-2">
      <?php if (!$user): ?>
        <!-- Not logged in -->
        <a href="/hospital/auth/login.php"
           class="rounded-xl border px-4 py-2 text-sm font-bold hover:bg-slate-50">
          Login
        </a>
        <a href="/hospital/auth/signup.php"
           class="rounded-xl bg-orange-500 px-4 py-2 text-sm font-extrabold text-white hover:bg-orange-600">
          Get Started →
        </a>
      <?php else: ?>
        <!-- Logged in: icon-only button, name shows inside dropdown -->
        <div class="relative">
          <button id="profileBtn" type="button"
            class="flex items-center gap-2 rounded-2xl border bg-white px-3 py-2 hover:bg-slate-50"
            aria-label="Open profile menu">
            <!-- avatar circle -->
            <div class="grid h-9 w-9 place-items-center rounded-full bg-slate-200 text-sm font-extrabold text-slate-700">
              <?php echo htmlspecialchars($initials); ?>
            </div>
            <span class="text-slate-400">▾</span>
          </button>

          <!-- dropdown -->
          <div id="profileMenu"
            class="absolute right-0 mt-2 hidden w-64 overflow-hidden rounded-2xl border bg-white shadow-lg">

            <!-- User header (name only inside dropdown) -->
            <div class="px-4 py-3 bg-slate-50">
              <div class="text-sm font-extrabold text-slate-900 truncate">
                <?php echo htmlspecialchars($user["full_name"]); ?>
              </div>
              <div class="text-xs font-semibold text-slate-500 truncate">
                <?php echo htmlspecialchars(strtolower($user["role"] ?? "staff")); ?>
              </div>
            </div>

            <div class="h-px bg-slate-100"></div>

            <a href="/hospital/dashboard.php"
              class="flex items-center gap-3 px-4 py-3 text-sm font-bold text-slate-700 hover:bg-slate-50">
              🏠 Dashboard
            </a>

            <a href="/hospital/profile.php"
              class="flex items-center gap-3 px-4 py-3 text-sm font-bold text-slate-700 hover:bg-slate-50">
              👤 Profile
            </a>

            <div class="h-px bg-slate-100"></div>

            <a href="/hospital/auth/logout.php"
              class="flex items-center gap-3 px-4 py-3 text-sm font-extrabold text-rose-600 hover:bg-rose-50">
              🚪 Logout
            </a>
          </div>
        </div>
      <?php endif; ?>
    </div>
  </div>
</header>

<script>
  (function () {
    const btn = document.getElementById("profileBtn");
    const menu = document.getElementById("profileMenu");
    if (!btn || !menu) return;

    function closeMenu() {
      menu.classList.add("hidden");
    }
    function toggleMenu() {
      menu.classList.toggle("hidden");
    }

    btn.addEventListener("click", (e) => {
      e.stopPropagation();
      toggleMenu();
    });

    document.addEventListener("click", closeMenu);
    document.addEventListener("keydown", (e) => {
      if (e.key === "Escape") closeMenu();
    });
  })();
</script>
