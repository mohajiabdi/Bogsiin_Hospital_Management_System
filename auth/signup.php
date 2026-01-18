<?php
// auth/signup.php
session_start();
require_once __DIR__ . "/../includes/db.php";

$error = "";
$success = "";
$full_name = "";
$email = "";
$role = "ADMIN"; // for university demo: first user can be ADMIN

if ($_SERVER["REQUEST_METHOD"] === "POST") {
  $full_name = trim($_POST["full_name"] ?? "");
  $email = trim($_POST["email"] ?? "");
  $password = $_POST["password"] ?? "";
  $agree = isset($_POST["agree"]);

  if ($full_name === "" || $email === "" || $password === "") {
    $error = "Please fill all required fields.";
  } elseif (!$agree) {
    $error = "You must agree to the Terms and Privacy Policy.";
  } elseif (strlen($password) < 6) {
    $error = "Password must be at least 6 characters.";
  } else {
    // check email exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
      $error = "Email already exists. Please login.";
    } else {
      $hash = password_hash($password, PASSWORD_DEFAULT);

      // If this is the first user, make ADMIN; otherwise STAFF
      $count = (int)$pdo->query("SELECT COUNT(*) AS c FROM users")->fetch()["c"];
      $roleToUse = ($count === 0) ? "ADMIN" : "STAFF";

      $ins = $pdo->prepare("INSERT INTO users (full_name, email, password_hash, role, is_active) VALUES (?, ?, ?, ?, 1)");
      $ins->execute([$full_name, $email, $hash, $roleToUse]);

     $_SESSION["flash_success"] = "Account created successfully. Please login.";
header("Location: /hospital/auth/login.php");
exit;

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
        ✨ Create your account
      </div>

      <h1 class="mt-4 text-4xl font-extrabold tracking-tight">Start managing your hospital</h1>
      <p class="mt-2 text-slate-600">
        Setup your account, then login to access the dashboard and modules.
      </p>

      <div class="mt-6 grid gap-3">
        <?php
          $points = [
            "Add patients and keep history",
            "Manage employees & schedules",
            "Create prescriptions and bills",
            "View basic reports",
          ];
          foreach ($points as $p) {
            echo '<div class="flex items-center gap-3 rounded-2xl border bg-white px-4 py-3">
                    <span class="grid h-8 w-8 place-items-center rounded-full bg-orange-100 text-orange-700 font-extrabold">✓</span>
                    <span class="font-semibold text-slate-700">'.$p.'</span>
                  </div>';
          }
        ?>
      </div>
    </section>

    <!-- Right signup card -->
    <section class="rounded-3xl border bg-white p-8 shadow-sm">
      <h2 class="text-2xl font-extrabold">Sign up</h2>
      <p class="mt-1 text-sm text-slate-600">Fill in your details to create an account</p>

      <?php if ($error): ?>
        <div class="mt-4 rounded-2xl border border-red-200 bg-red-50 p-3 text-sm font-semibold text-red-700">
          <?php echo htmlspecialchars($error); ?>
        </div>
      <?php endif; ?>

      <?php if ($success): ?>
        <div class="mt-4 rounded-2xl border border-green-200 bg-green-50 p-3 text-sm font-semibold text-green-700">
          <?php echo htmlspecialchars($success); ?>
        </div>
      <?php endif; ?>

      <form class="mt-6 grid gap-4" method="POST">
        <div>
          <label class="text-xs font-extrabold tracking-widest text-slate-500">FULL NAME</label>
          <div class="mt-2 flex items-center gap-2 rounded-2xl border bg-white px-4 py-3">
            <span class="text-slate-400">👤</span>
            <input class="w-full outline-none text-sm" name="full_name" placeholder="Your name"
                   value="<?php echo htmlspecialchars($full_name); ?>" required />
          </div>
        </div>

        <div>
          <label class="text-xs font-extrabold tracking-widest text-slate-500">EMAIL</label>
          <div class="mt-2 flex items-center gap-2 rounded-2xl border bg-white px-4 py-3">
              <svg class="h-5 w-5 text-slate-400" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
  <path d="M20 4H4a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2Zm0 4-8 5L4 8V6l8 5 8-5v2Z"/>
</svg>
            <input class="w-full outline-none text-sm" type="email" name="email" placeholder="admin@hospital.com"
                   value="<?php echo htmlspecialchars($email); ?>" required />
          </div>
        </div>

        <div>
          <label class="text-xs font-extrabold tracking-widest text-slate-500">PASSWORD</label>
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

          <p class="mt-1 text-xs text-slate-500">Tip: use a strong password for real deployment.</p>
        </div>

      <label class="flex items-center gap-2 rounded-2xl border bg-slate-50 px-4 py-3 text-sm font-semibold text-slate-700">
 <input
  type="checkbox"
  name="agree"
  id="agreeCheckbox"
  required
  class="h-4 w-4 rounded border-slate-300"
/>

  <span>
    I agree to the
    <button type="button" id="openTerms" class="font-extrabold text-orange-600 hover:text-orange-700 underline underline-offset-2">
      Terms
    </button>
    and
    <button type="button" id="openPrivacy" class="font-extrabold text-orange-600 hover:text-orange-700 underline underline-offset-2">
      Privacy Policy
    </button>.
  </span>
</label>


        <button class="mt-2 w-full rounded-2xl bg-orange-500 px-5 py-3 text-sm font-extrabold text-white hover:bg-orange-600" type="submit">
          Create account →
        </button>

        <p class="text-center text-sm text-slate-600">
          Already have an account?
          <a href="/hospital/auth/login.php" class="font-extrabold text-orange-600 hover:text-orange-700">Login</a>
        </p>
      </form>
    </section>
  </div>
</main>
<!-- Terms Modal -->
<div id="termsModal" class="fixed inset-0 z-50 hidden">
  <div id="termsOverlay" class="absolute inset-0 bg-slate-900/50 backdrop-blur-[1px]"></div>

  <div class="relative mx-auto flex min-h-full max-w-2xl items-center justify-center p-4">
    <div class="w-full max-w-xl overflow-hidden rounded-3xl bg-white shadow-2xl ring-1 ring-black/5">
      <div class="flex items-start justify-between gap-4 border-b px-6 py-4">
        <div>
          <div class="text-lg font-extrabold">Terms & Conditions</div>
          <div class="mt-0.5 text-sm text-slate-600">Rules for using Bogsiin Hospital System</div>
        </div>
        <button type="button" id="closeTerms"
          class="grid h-10 w-10 place-items-center rounded-2xl hover:bg-slate-50 text-slate-500">✕</button>
      </div>

      <div class="px-6 py-5 text-sm text-slate-700 space-y-4 max-h-[65vh] overflow-auto">
        <div>
          <div class="text-xs font-extrabold tracking-widest text-slate-500">1. ACCEPTANCE</div>
          <p class="mt-1">
            By creating an account or using this system, you agree to follow these Terms.
            If you do not agree, please do not use the system.
          </p>
        </div>

        <div>
          <div class="text-xs font-extrabold tracking-widest text-slate-500">2. AUTHORIZED USE</div>
          <p class="mt-1">
            This system is intended for authorized hospital staff only. You must keep your login credentials confidential
            and you are responsible for all activity under your account.
          </p>
        </div>

        <div>
          <div class="text-xs font-extrabold tracking-widest text-slate-500">3. DATA & ACCURACY</div>
          <p class="mt-1">
            Users must enter accurate patient and operational data. The system may be used for learning and workflow simulation,
            but it should be treated as a serious healthcare information tool.
          </p>
        </div>

        <div>
          <div class="text-xs font-extrabold tracking-widest text-slate-500">4. SECURITY</div>
          <p class="mt-1">
            Do not attempt to access information outside your role. Any misuse, unauthorized access, or tampering may result
            in account suspension and administrative action.
          </p>
        </div>

        <div>
          <div class="text-xs font-extrabold tracking-widest text-slate-500">5. CHANGES</div>
          <p class="mt-1">
            The system administrators may update these Terms to improve compliance and safety. Continued use means you accept
            the updated Terms.
          </p>
        </div>

        <div class="rounded-2xl border bg-slate-50 p-4">
          <div class="text-xs font-extrabold tracking-widest text-slate-500">CONTACT</div>
          <p class="mt-1">
            For questions about system rules or access, contact the system administrator.
          </p>
        </div>
      </div>

      <div class="border-t px-6 py-4 flex justify-end">
        <button type="button" id="okTerms"
          class="rounded-2xl bg-slate-900 px-5 py-2.5 text-sm font-extrabold text-white hover:bg-slate-800">
          I Understand
        </button>
      </div>
    </div>
  </div>
</div>

<!-- Privacy Modal -->
<div id="privacyModal" class="fixed inset-0 z-50 hidden">
  <div id="privacyOverlay" class="absolute inset-0 bg-slate-900/50 backdrop-blur-[1px]"></div>

  <div class="relative mx-auto flex min-h-full max-w-2xl items-center justify-center p-4">
    <div class="w-full max-w-xl overflow-hidden rounded-3xl bg-white shadow-2xl ring-1 ring-black/5">
      <div class="flex items-start justify-between gap-4 border-b px-6 py-4">
        <div>
          <div class="text-lg font-extrabold">Privacy Policy</div>
          <div class="mt-0.5 text-sm text-slate-600">How we collect and use information</div>
        </div>
        <button type="button" id="closePrivacy"
          class="grid h-10 w-10 place-items-center rounded-2xl hover:bg-slate-50 text-slate-500">✕</button>
      </div>

      <div class="px-6 py-5 text-sm text-slate-700 space-y-4 max-h-[65vh] overflow-auto">
        <div>
          <div class="text-xs font-extrabold tracking-widest text-slate-500">1. INFORMATION WE STORE</div>
          <p class="mt-1">
            We store account details (name, email, role) and hospital workflow data (patients, appointments, prescriptions, billing)
            according to the permissions of the user roles.
          </p>
        </div>

        <div>
          <div class="text-xs font-extrabold tracking-widest text-slate-500">2. PURPOSE</div>
          <p class="mt-1">
            Data is used to support hospital operations: tracking patients, scheduling appointments, generating invoices/receipts,
            and creating administrative reports.
          </p>
        </div>

        <div>
          <div class="text-xs font-extrabold tracking-widest text-slate-500">3. ACCESS CONTROL</div>
          <p class="mt-1">
            Access is controlled by user roles. Users should only view or modify data required for their job responsibilities.
          </p>
        </div>

        <div>
          <div class="text-xs font-extrabold tracking-widest text-slate-500">4. DATA SHARING</div>
          <p class="mt-1">
            We do not share data with third parties by default. If integrations are added later, they must follow security and privacy rules.
          </p>
        </div>

        <div>
          <div class="text-xs font-extrabold tracking-widest text-slate-500">5. SECURITY PRACTICES</div>
          <p class="mt-1">
            Accounts use password hashing, and sensitive actions should be protected by role permissions. Users should log out from shared devices.
          </p>
        </div>

        <div class="rounded-2xl border bg-slate-50 p-4">
          <div class="text-xs font-extrabold tracking-widest text-slate-500">YOUR RESPONSIBILITY</div>
          <p class="mt-1">
            Do not upload unnecessary personal information. Keep patient data confidential and follow hospital policies.
          </p>
        </div>
      </div>

      <div class="border-t px-6 py-4 flex justify-end">
        <button type="button" id="okPrivacy"
          class="rounded-2xl bg-slate-900 px-5 py-2.5 text-sm font-extrabold text-white hover:bg-slate-800">
          Close
        </button>
      </div>
    </div>
  </div>
</div>

<script>
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
  const termsModal = document.getElementById('termsModal');
  const privacyModal = document.getElementById('privacyModal');

  const openTerms = document.getElementById('openTerms');
  const openPrivacy = document.getElementById('openPrivacy');

  const closeTerms = document.getElementById('closeTerms');
  const okTerms = document.getElementById('okTerms');
  const termsOverlay = document.getElementById('termsOverlay');

  const closePrivacy = document.getElementById('closePrivacy');
  const okPrivacy = document.getElementById('okPrivacy');
  const privacyOverlay = document.getElementById('privacyOverlay');

  function openM(m){
    if(!m) return;
    m.classList.remove('hidden');
    document.body.style.overflow='hidden';
  }
  function closeM(m){
    if(!m) return;
    m.classList.add('hidden');
    document.body.style.overflow='';
  }

  openTerms?.addEventListener('click', ()=> openM(termsModal));
  openPrivacy?.addEventListener('click', ()=> openM(privacyModal));

  closeTerms?.addEventListener('click', ()=> closeM(termsModal));
  okTerms?.addEventListener('click', ()=> closeM(termsModal));
  termsOverlay?.addEventListener('click', ()=> closeM(termsModal));

  closePrivacy?.addEventListener('click', ()=> closeM(privacyModal));
  okPrivacy?.addEventListener('click', ()=> closeM(privacyModal));
  privacyOverlay?.addEventListener('click', ()=> closeM(privacyModal));

  document.addEventListener('keydown', (e)=>{
    if(e.key === 'Escape'){
      closeM(termsModal);
      closeM(privacyModal);
    }
  });
})();


(function () {
  const form = document.querySelector('form');
  const agree = document.getElementById('agreeCheckbox');
  const err = document.getElementById('agreeError');

  if (!form || !agree || !err) return;

  form.addEventListener('submit', (e) => {
    if (!agree.checked) {
      e.preventDefault();
      err.classList.remove('hidden');
      agree.focus();
    } else {
      err.classList.add('hidden');
    }
  });
})();
</script>

</body>
</html>
<?php include __DIR__ . "/../includes/footer.php"; ?>