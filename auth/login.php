<?php
// auth/login.php
session_start();
require_once __DIR__ . "/../includes/db.php";
// ---------------- Brute-force protection (session-based) ----------------
if (!isset($_SESSION["login_fail_count"])) $_SESSION["login_fail_count"] = 0;
if (!isset($_SESSION["login_fail_streak"])) $_SESSION["login_fail_streak"] = 0; // counts how many lockouts happened

$now = time();
$lockUntil = (int)($_SESSION["login_lock_until"] ?? 0);
$isLocked = ($lockUntil > $now);
$lockRemaining = max(0, $lockUntil - $now);

// Helper: set lock duration based on streak: 1st lock=3m, 2nd=6m, 3rd=12m...
function lock_duration_seconds(int $streak): int {
  $base = 3 * 60; // 3 minutes
  return $base * (2 ** max(0, $streak - 1));
}


// If already logged in, go dashboard
if (isset($_SESSION["user"])) {
  header("Location: /hospital/dashboard.php");
  exit;
}

$pageTitle = "Login • Hospital";
$flashSuccess = $_SESSION["flash_success"] ?? "";
if ($flashSuccess) unset($_SESSION["flash_success"]);


$error = "";
$email = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

  // If locked, do NOT attempt login
  if ($isLocked) {
    $mins = (int)ceil($lockRemaining / 60);
    $error = "Too many failed attempts. Try again in {$mins} minute(s).";
  } else {
    $email = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";

    if ($email === "" || $password === "") {
      $error = "Please enter email and password.";
      $_SESSION["login_fail_count"] += 1;
    } else {
      $stmt = $pdo->prepare("SELECT id, full_name, email, password_hash, role, is_active FROM users WHERE email = ? LIMIT 1");
      $stmt->execute([$email]);
      $user = $stmt->fetch(PDO::FETCH_ASSOC);

      $ok = true;

      if (!$user || (int)$user["is_active"] !== 1) {
        $ok = false;
        $error = "Invalid login or account disabled.";
      } elseif (!password_verify($password, $user["password_hash"])) {
        $ok = false;
        $error = "Invalid email or password.";
      }

      if ($ok) {
        // SUCCESS: reset protection
        $_SESSION["login_fail_count"] = 0;
        $_SESSION["login_lock_until"] = 0;
        $_SESSION["login_fail_streak"] = 0;

        $_SESSION["user"] = [
          "id" => $user["id"],
          "full_name" => $user["full_name"],
          "email" => $user["email"],
          "role" => $user["role"],
        ];

        header("Location: /hospital/dashboard.php");
        exit;
      } else {
        // FAIL: increment fail count
        $_SESSION["login_fail_count"] += 1;
      }
    }

    // If reached 3 fails -> lock and exponential backoff
    if ($_SESSION["login_fail_count"] >= 3) {
      $_SESSION["login_fail_count"] = 0; // reset for next round after lock
      $_SESSION["login_fail_streak"] = (int)$_SESSION["login_fail_streak"] + 1;

      $seconds = lock_duration_seconds((int)$_SESSION["login_fail_streak"]);
      $_SESSION["login_lock_until"] = time() + $seconds;

      $mins = (int)ceil($seconds / 60);
      $error = "Too many failed attempts. Login is locked for {$mins} minute(s).";
      
      // Refresh lock vars for this request
      $lockUntil = (int)$_SESSION["login_lock_until"];
      $isLocked = true;
      $lockRemaining = max(0, $lockUntil - time());
    }
  }
}


include_once __DIR__ . "/../includes/header.php";
include_once __DIR__ . "/../includes/navbar.php";
?>

<main class="mx-auto max-w-6xl px-4 py-10">
  <div class="grid gap-6 md:grid-cols-2 md:items-start">
    <!-- Left info card -->
    <section class="rounded-3xl border bg-white p-8 shadow-sm">
      <div class="inline-flex items-center gap-2 rounded-full bg-orange-50 px-3 py-1 text-xs font-extrabold text-orange-700">
        🔒 Secure login
      </div>

      <h1 class="mt-4 text-4xl font-extrabold tracking-tight">Welcome back</h1>
      <p class="mt-2 text-slate-600">
        Sign in to manage patients, employees, appointments, prescriptions, and billing.
      </p>

      <div class="mt-6 grid gap-3">
        <?php
          $points = [
            "Fast appointments workflow",
            "Patient records and history",
            "Invoices and receipts",
          ];
          foreach ($points as $p) {
            echo '<div class="flex items-center gap-3 rounded-2xl border bg-white px-4 py-3">
                    <span class="grid h-8 w-8 place-items-center rounded-full bg-orange-100 text-orange-700 font-extrabold">✓</span>
                    <span class="font-semibold text-slate-700">'.htmlspecialchars($p).'</span>
                  </div>';
          }
        ?>
      </div>

    <div class="mt-6 rounded-2xl border bg-slate-50 p-4">
  <div class="text-xs font-extrabold tracking-widest text-slate-500">NOTICE</div>
  <div class="mt-1 text-sm text-slate-700">
    Access is restricted to authorized hospital staff only. 
    All activities are logged for security and accountability.
  </div>
</div>

    </section>

    <!-- Right login card -->
    <section class="rounded-3xl border bg-white p-8 shadow-sm">
      <h2 class="text-2xl font-extrabold">Login</h2>
      <p class="mt-1 text-sm text-slate-600">Enter your account details to continue</p>
<?php if ($flashSuccess): ?>
  <div class="mt-4 rounded-2xl border border-green-200 bg-green-50 p-3 text-sm font-semibold text-green-700">
    <?php echo htmlspecialchars($flashSuccess); ?>
  </div>
<?php endif; ?>

      <?php if ($error): ?>
        <div class="mt-4 rounded-2xl border border-red-200 bg-red-50 p-3 text-sm font-semibold text-red-700">
          <?php echo htmlspecialchars($error); ?>
        </div>
      <?php endif; ?>

      <form class="mt-6 grid gap-4" method="POST" novalidate>
        <div>
          <label class="text-xs font-extrabold tracking-widest text-slate-500">EMAIL</label>
          <div class="mt-2 flex items-center gap-2 rounded-2xl border bg-white px-4 py-3">
            <svg class="h-5 w-5 text-slate-400" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
  <path d="M20 4H4a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2Zm0 4-8 5L4 8V6l8 5 8-5v2Z"/>
</svg>

            <input
              class="w-full outline-none text-sm"
              type="email"
              name="email"
              placeholder="admin@hospital.com"
              value="<?php echo htmlspecialchars($email); ?>"
              required
            />
          </div>
        </div>
<div>
  <label class="text-xs font-extrabold tracking-widest text-slate-500">PASSWORD</label>

  <div class="mt-2 flex items-center gap-2 rounded-2xl border bg-white px-4 py-3">
    <!-- lock icon -->
    <svg class="h-5 w-5 text-slate-400" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
      <path d="M12 2a5 5 0 0 0-5 5v3H6a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-8a2 2 0 0 0-2-2h-1V7a5 5 0 0 0-5-5Zm-3 8V7a3 3 0 0 1 6 0v3H9Z"/>
    </svg>

    <input
      id="passwordInput"
      class="w-full outline-none text-sm"
      type="password"
      name="password"
      placeholder="••••••••"
      required
    />

    <!-- eye toggle button -->
    <button type="button" id="togglePassword"
      class="grid h-9 w-9 place-items-center rounded-xl hover:bg-slate-50 text-slate-500"
      aria-label="Show password">
      <svg id="eyeIcon" class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
        <path d="M12 5c5 0 9.27 3.11 11 7-1.73 3.89-6 7-11 7S2.73 15.89 1 12c1.73-3.89 6-7 11-7Zm0 2C8.13 7 4.64 9.28 3.2 12 4.64 14.72 8.13 17 12 17s7.36-2.28 8.8-5C19.36 9.28 15.87 7 12 7Zm0 2.5A2.5 2.5 0 1 1 9.5 12 2.5 2.5 0 0 1 12 9.5Z"/>
      </svg>
    </button>
  </div>
</div>


        <div class="flex items-center justify-between">
          <label class="flex items-center gap-2 text-sm font-semibold text-slate-700">
            <input type="checkbox" name="remember" class="h-4 w-4 rounded border-slate-300" />
            Remember me
          </label>
         <!-- <button type="button"
  id="openResetModal"
  class="text-sm font-extrabold text-orange-600 hover:text-orange-700">
  Forgot password?
</button> -->

        </div>

       <button
  id="loginBtn"
  class="mt-2 w-full rounded-2xl bg-orange-500 px-5 py-3 text-sm font-extrabold text-white hover:bg-orange-600 disabled:cursor-not-allowed disabled:opacity-60"
  type="submit"
  <?php echo $isLocked ? "disabled" : ""; ?>
>
  <?php echo $isLocked ? "Locked..." : "Login →"; ?>
</button>

<?php if ($isLocked): ?>
  <div id="lockInfo" class="mt-3 rounded-2xl border border-red-200 bg-red-50 p-3 text-sm font-semibold text-red-700"
       data-remaining="<?php echo (int)$lockRemaining; ?>">
    Too many failed attempts. Try again in <span id="lockTimer"><?php echo (int)ceil($lockRemaining/60); ?>m</span>.
  </div>
<?php endif; ?>

          
        </button>

        <p class="text-center text-sm text-slate-600">
          Don’t have an account?
          <a href="/hospital/auth/signup.php" class="font-extrabold text-orange-600 hover:text-orange-700">Sign up</a>
        </p>
      </form>
    </section>
  </div>
</main>



<!-- Reset Password Modal -->
<div id="resetModal" class="fixed inset-0 z-50 hidden">
  <!-- overlay -->
  <div id="resetOverlay" class="absolute inset-0 bg-slate-900/50 backdrop-blur-[1px]"></div>

  <!-- modal -->
  <div class="relative mx-auto flex min-h-full max-w-xl items-center justify-center p-4">
    <div class="w-full max-w-lg rounded-3xl bg-white shadow-2xl ring-1 ring-black/5">
      <!-- header -->
      <div class="flex items-start justify-between gap-4 p-6">
        <div class="flex items-start gap-3">
          <div class="grid h-10 w-10 place-items-center rounded-2xl bg-orange-100">
            <span class="text-orange-700">🔑</span>
          </div>
          <div>
            <div class="text-lg font-extrabold">Reset password</div>
            <div class="mt-0.5 text-sm text-slate-600">We’ll send a reset link to your email.</div>
          </div>
        </div>

        <button type="button" id="closeResetModal"
          class="grid h-10 w-10 place-items-center rounded-2xl hover:bg-slate-50 text-slate-500">
          ✕
        </button>
      </div>

      <!-- body -->
      <div class="px-6 pb-6">
        <label class="text-xs font-extrabold tracking-widest text-slate-500">EMAIL</label>
        <div class="mt-2 flex items-center gap-2 rounded-2xl border bg-white px-4 py-3">
          <span class="text-slate-400">✉️</span>
          <input id="resetEmail" type="email"
            class="w-full outline-none text-sm"
            placeholder="your@email.com" />
        </div>

        <div id="resetMsg" class="mt-3 hidden rounded-2xl border px-4 py-3 text-sm font-semibold"></div>

        <div class="mt-5 grid gap-3">
          <button type="button" id="sendResetLink"
            class="w-full rounded-2xl bg-orange-500 px-5 py-3 text-sm font-extrabold text-white hover:bg-orange-600">
            Send reset link →
          </button>

          <button type="button" id="cancelReset"
            class="w-full rounded-2xl border bg-white px-5 py-3 text-sm font-extrabold hover:bg-slate-50">
            Cancel
          </button>
        </div>
      </div>
    </div>
  </div>
</div>
<?php include_once __DIR__ . "/../includes/footer.php"; ?>
<script>
  (function () {
    const modal = document.getElementById("resetModal");
    const overlay = document.getElementById("resetOverlay");
    const openBtn = document.getElementById("openResetModal");
    const closeBtn = document.getElementById("closeResetModal");
    const cancelBtn = document.getElementById("cancelReset");
    const sendBtn = document.getElementById("sendResetLink");

    const emailInput = document.getElementById("resetEmail");
    const msg = document.getElementById("resetMsg");

    function openModal() {
      modal.classList.remove("hidden");
      document.body.style.overflow = "hidden";
      msg.classList.add("hidden");
      msg.textContent = "";
      emailInput.value = "";
      setTimeout(() => emailInput.focus(), 50);
    }

    function closeModal() {
      modal.classList.add("hidden");
      document.body.style.overflow = "";
    }

    function showMsg(type, text) {
      msg.classList.remove("hidden");
      msg.classList.remove("border-red-200", "bg-red-50", "text-red-700", "border-green-200", "bg-green-50", "text-green-700");
      if (type === "error") msg.classList.add("border-red-200", "bg-red-50", "text-red-700");
      if (type === "success") msg.classList.add("border-green-200", "bg-green-50", "text-green-700");
      msg.textContent = text;
    }

    openBtn?.addEventListener("click", openModal);
    closeBtn?.addEventListener("click", closeModal);
    cancelBtn?.addEventListener("click", closeModal);
    overlay?.addEventListener("click", closeModal);

    document.addEventListener("keydown", (e) => {
      if (e.key === "Escape" && !modal.classList.contains("hidden")) closeModal();
    });

    sendBtn?.addEventListener("click", async () => {
      const email = (emailInput.value || "").trim();
      if (!email) return showMsg("error", "Please enter your email.");
      if (!/^\S+@\S+\.\S+$/.test(email)) return showMsg("error", "Please enter a valid email address.");

      // For now: UI only (demo). Later we connect to PHP mail/PHPMailer.
      showMsg("success", "If this email exists, a reset link will be sent.");
    });
  })();


  (function(){
  const pw = document.getElementById('passwordInput');
  const btn = document.getElementById('togglePassword');
  const eye = document.getElementById('eyeIcon');

  if (!pw || !btn || !eye) return;

  btn.addEventListener('click', () => {
    const isHidden = pw.type === 'password';
    pw.type = isHidden ? 'text' : 'password';
    btn.setAttribute('aria-label', isHidden ? 'Hide password' : 'Show password');

    // swap icon (open eye vs crossed eye)
    eye.innerHTML = isHidden
      ? '<path d="M3 4.3 4.3 3 21 19.7 19.7 21l-3.1-3.1A11.8 11.8 0 0 1 12 19C7 19 2.73 15.89 1 12a12.8 12.8 0 0 1 5.2-5.9L3 4.3Zm8.9 8.9a2.5 2.5 0 0 1-3.1-3.1l3.1 3.1Zm-1.7-7.4A11.7 11.7 0 0 1 12 5c5 0 9.27 3.11 11 7a13 13 0 0 1-4.4 5.2l-2.5-2.5a4.5 4.5 0 0 0-5.6-5.6l-.3-.3Z"/>'
      : '<path d="M12 5c5 0 9.27 3.11 11 7-1.73 3.89-6 7-11 7S2.73 15.89 1 12c1.73-3.89 6-7 11-7Zm0 2C8.13 7 4.64 9.28 3.2 12 4.64 14.72 8.13 17 12 17s7.36-2.28 8.8-5C19.36 9.28 15.87 7 12 7Zm0 2.5A2.5 2.5 0 1 1 9.5 12 2.5 2.5 0 0 1 12 9.5Z"/>';
  });
})();


(function(){
  const lockInfo = document.getElementById('lockInfo');
  const timerEl = document.getElementById('lockTimer');
  const btn = document.getElementById('loginBtn');
  if (!lockInfo || !timerEl || !btn) return;

  let remaining = parseInt(lockInfo.getAttribute('data-remaining') || '0', 10);
  if (remaining <= 0) return;

  const tick = () => {
    remaining -= 1;
    if (remaining <= 0) {
      timerEl.textContent = "0s";
      // easiest: reload so PHP unlocks UI
      window.location.reload();
      return;
    }
    const m = Math.floor(remaining / 60);
    const s = remaining % 60;
    timerEl.textContent = m > 0 ? `${m}m ${s}s` : `${s}s`;
  };

  setInterval(tick, 1000);
})();

</script>

