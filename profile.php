<?php
// profile.php
$pageTitle = "Profile • Hospital";
require_once __DIR__ . "/includes/auth_guard.php";
require_once __DIR__ . "/includes/db.php";

if (session_status() === PHP_SESSION_NONE) session_start();

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function flash_set($type, $msg){ $_SESSION["flash"] = ["type"=>$type, "msg"=>$msg]; }
function flash_get(){ if (!isset($_SESSION["flash"])) return null; $f=$_SESSION["flash"]; unset($_SESSION["flash"]); return $f; }

$me = $_SESSION["user"];
$myId = (int)$me["id"];
$myRole = $me["role"] ?? "STAFF";

// Refresh current user from DB (so page always up-to-date)
$stmt = $pdo->prepare("SELECT id, full_name, email, role, is_active, created_at FROM users WHERE id=? LIMIT 1");
$stmt->execute([$myId]);
$meDb = $stmt->fetch();
if (!$meDb) {
  // session user not found, force logout
  header("Location: /hospital/auth/logout.php");
  exit;
}

// ---- Handle POST actions (current user + admin) ----
if ($_SERVER["REQUEST_METHOD"] === "POST") {
  $action = $_POST["action"] ?? "";

  // -------- CURRENT USER: Update profile (name/email) --------
  if ($action === "update_me") {
    $full_name = trim($_POST["full_name"] ?? "");
    $email = trim($_POST["email"] ?? "");

    if ($full_name === "" || $email === "") {
      flash_set("error", "Full name and email are required.");
      header("Location: /hospital/profile.php");
      exit;
    }

    // prevent email duplicate (except my own)
    $check = $pdo->prepare("SELECT id FROM users WHERE email=? AND id<>? LIMIT 1");
    $check->execute([$email, $myId]);
    if ($check->fetch()) {
      flash_set("error", "This email is already used by another user.");
      header("Location: /hospital/profile.php");
      exit;
    }

    $upd = $pdo->prepare("UPDATE users SET full_name=?, email=? WHERE id=?");
    $upd->execute([$full_name, $email, $myId]);

    // update session too
    $_SESSION["user"]["full_name"] = $full_name;
    $_SESSION["user"]["email"] = $email;

    flash_set("success", "Profile updated.");
    header("Location: /hospital/profile.php");
    exit;
  }

  // -------- CURRENT USER: Change password --------
  if ($action === "change_password") {
    $current = $_POST["current_password"] ?? "";
    $new1 = $_POST["new_password"] ?? "";
    $new2 = $_POST["confirm_password"] ?? "";

    if ($current === "" || $new1 === "" || $new2 === "") {
      flash_set("error", "Please fill all password fields.");
      header("Location: /hospital/profile.php");
      exit;
    }
    if ($new1 !== $new2) {
      flash_set("error", "New passwords do not match.");
      header("Location: /hospital/profile.php");
      exit;
    }
    if (strlen($new1) < 6) {
      flash_set("error", "Password should be at least 6 characters.");
      header("Location: /hospital/profile.php");
      exit;
    }

    $pw = $pdo->prepare("SELECT password_hash FROM users WHERE id=? LIMIT 1");
    $pw->execute([$myId]);
    $row = $pw->fetch();
    if (!$row || !password_verify($current, $row["password_hash"])) {
      flash_set("error", "Current password is incorrect.");
      header("Location: /hospital/profile.php");
      exit;
    }

    $hash = password_hash($new1, PASSWORD_DEFAULT);
    $upd = $pdo->prepare("UPDATE users SET password_hash=? WHERE id=?");
    $upd->execute([$hash, $myId]);

    flash_set("success", "Password changed successfully.");
    header("Location: /hospital/profile.php");
    exit;
  }

  // -------- CURRENT USER: Deactivate my own account (optional) --------
  if ($action === "deactivate_me") {
    // (Safety) do not allow admin to lock themselves accidentally? you can remove if you want
    if ($myRole === "ADMIN") {
      flash_set("error", "Admin account cannot deactivate itself here.");
      header("Location: /hospital/profile.php");
      exit;
    }

    $upd = $pdo->prepare("UPDATE users SET is_active=0 WHERE id=?");
    $upd->execute([$myId]);

    // logout
    header("Location: /hospital/auth/logout.php");
    exit;
  }

  // ================= ADMIN USERS MANAGEMENT =================
  if ($myRole === "ADMIN") {

    // ADMIN: Create or Update user
    if ($action === "admin_save_user") {
      $id = trim($_POST["id"] ?? "");

      $full_name = trim($_POST["full_name"] ?? "");
      $email = trim($_POST["email"] ?? "");
      $role = trim($_POST["role"] ?? "STAFF");
      $is_active = (int)($_POST["is_active"] ?? 1);

      $password = $_POST["password"] ?? "";
      $password2 = $_POST["password2"] ?? "";

      if ($full_name === "" || $email === "") {
        flash_set("error", "Full name and email are required.");
        header("Location: /hospital/profile.php");
        exit;
      }

      // validate role
      $allowedRoles = ["ADMIN","RECEPTIONIST","STAFF"];
      if (!in_array($role, $allowedRoles, true)) $role = "STAFF";

      // email duplicate check
      if ($id === "") {
        $check = $pdo->prepare("SELECT id FROM users WHERE email=? LIMIT 1");
        $check->execute([$email]);
        if ($check->fetch()) {
          flash_set("error", "Email already exists.");
          header("Location: /hospital/profile.php");
          exit;
        }

        if ($password === "" || $password2 === "") {
          flash_set("error", "Password is required for new user.");
          header("Location: /hospital/profile.php");
          exit;
        }
        if ($password !== $password2) {
          flash_set("error", "Passwords do not match.");
          header("Location: /hospital/profile.php");
          exit;
        }
        if (strlen($password) < 6) {
          flash_set("error", "Password should be at least 6 characters.");
          header("Location: /hospital/profile.php");
          exit;
        }

        $hash = password_hash($password, PASSWORD_DEFAULT);

        $ins = $pdo->prepare("INSERT INTO users (full_name,email,password_hash,role,is_active) VALUES (?,?,?,?,?)");
        $ins->execute([$full_name, $email, $hash, $role, $is_active]);

        flash_set("success", "User created.");
        header("Location: /hospital/profile.php");
        exit;

      } else {
        $idInt = (int)$id;

        // cannot edit unknown
        $checkU = $pdo->prepare("SELECT id FROM users WHERE id=? LIMIT 1");
        $checkU->execute([$idInt]);
        if (!$checkU->fetch()) {
          flash_set("error", "User not found.");
          header("Location: /hospital/profile.php");
          exit;
        }

        // email duplicate check (except this user)
        $check = $pdo->prepare("SELECT id FROM users WHERE email=? AND id<>? LIMIT 1");
        $check->execute([$email, $idInt]);
        if ($check->fetch()) {
          flash_set("error", "Email already exists.");
          header("Location: /hospital/profile.php");
          exit;
        }

        // update base fields
        $upd = $pdo->prepare("UPDATE users SET full_name=?, email=?, role=?, is_active=? WHERE id=?");
        $upd->execute([$full_name, $email, $role, $is_active, $idInt]);

        // optional password update
        if ($password !== "" || $password2 !== "") {
          if ($password !== $password2) {
            flash_set("error", "Passwords do not match.");
            header("Location: /hospital/profile.php");
            exit;
          }
          if (strlen($password) < 6) {
            flash_set("error", "Password should be at least 6 characters.");
            header("Location: /hospital/profile.php");
            exit;
          }
          $hash = password_hash($password, PASSWORD_DEFAULT);
          $upw = $pdo->prepare("UPDATE users SET password_hash=? WHERE id=?");
          $upw->execute([$hash, $idInt]);
        }

        // if admin edited themselves, refresh session role/name/email
        if ($idInt === $myId) {
          $_SESSION["user"]["full_name"] = $full_name;
          $_SESSION["user"]["email"] = $email;
          $_SESSION["user"]["role"] = $role;
        }

        flash_set("success", "User updated.");
        header("Location: /hospital/profile.php");
        exit;
      }
    }

    // ADMIN: Delete user (prevent deleting self)
    if ($action === "admin_delete_user") {
      $id = (int)($_POST["id"] ?? 0);
      if ($id <= 0) {
        flash_set("error", "Invalid user.");
        header("Location: /hospital/profile.php");
        exit;
      }
      if ($id === $myId) {
        flash_set("error", "You cannot delete your own account.");
        header("Location: /hospital/profile.php");
        exit;
      }

      $del = $pdo->prepare("DELETE FROM users WHERE id=?");
      $del->execute([$id]);

      flash_set("success", "User deleted.");
      header("Location: /hospital/profile.php");
      exit;
    }
  }

  // fallback
  header("Location: /hospital/profile.php");
  exit;
}

// ---- Admin list ----
$adminUsers = [];
$uq = trim($_GET["uq"] ?? "");
if ($myRole === "ADMIN") {
  $w = "";
  $p = [];
  if ($uq !== "") {
    $w = "WHERE full_name LIKE :q OR email LIKE :q OR role LIKE :q";
    $p[":q"] = "%$uq%";
  }
  $stmt = $pdo->prepare("SELECT id, full_name, email, role, is_active, created_at FROM users $w ORDER BY created_at DESC LIMIT 300");
  $stmt->execute($p);
  $adminUsers = $stmt->fetchAll() ?: [];
}

$flash = flash_get();

include_once __DIR__ . "/includes/header.php";
?>

<div class="md:flex min-h-screen">
  <?php include __DIR__ . "/includes/sidebar.php"; ?>

  <div class="flex-1">
    <div class="mx-auto max-w-6xl px-4 py-6">

      <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
        <div>
          <div class="text-2xl font-extrabold tracking-tight">Profile</div>
          <div class="text-sm font-semibold text-slate-500">Manage your account settings</div>
        </div>
        <a href="/hospital/dashboard.php" class="rounded-2xl border bg-white px-4 py-2 text-sm font-extrabold hover:bg-slate-50">
          Back to Dashboard
        </a>
      </div>

      <?php if ($flash): ?>
        <div class="mt-4 rounded-2xl border px-4 py-3 text-sm font-semibold
          <?php echo $flash["type"] === "success" ? "border-green-200 bg-green-50 text-green-700" : "border-red-200 bg-red-50 text-red-700"; ?>">
          <?php echo h($flash["msg"]); ?>
        </div>
      <?php endif; ?>

      <!-- My Profile cards -->
      <section class="mt-6 grid gap-4 lg:grid-cols-3">
        <div class="lg:col-span-2 rounded-3xl border bg-white p-6 shadow-sm">
          <div class="font-extrabold text-lg">Edit profile</div>
          <div class="text-sm font-semibold text-slate-500">Update your name & email</div>

          <form method="POST" class="mt-5 grid gap-4">
            <input type="hidden" name="action" value="update_me">

            <div class="grid gap-4 md:grid-cols-2">
              <div>
                <label class="text-xs font-extrabold tracking-widest text-slate-500">FULL NAME</label>
                <input name="full_name" value="<?php echo h($meDb["full_name"]); ?>"
                  class="mt-2 w-full rounded-2xl border bg-white px-4 py-3 text-sm outline-none">
              </div>

              <div>
                <label class="text-xs font-extrabold tracking-widest text-slate-500">EMAIL</label>
                <input type="email" name="email" value="<?php echo h($meDb["email"]); ?>"
                  class="mt-2 w-full rounded-2xl border bg-white px-4 py-3 text-sm outline-none">
              </div>
            </div>

            <div class="flex justify-end">
              <button class="rounded-2xl bg-orange-500 px-5 py-3 text-sm font-extrabold text-white hover:bg-orange-600" type="submit">
                Save changes →
              </button>
            </div>
          </form>
        </div>

        <div class="rounded-3xl border bg-white p-6 shadow-sm">
          <div class="font-extrabold text-lg">My details</div>
          <div class="mt-4 space-y-3 text-sm">
            <div class="rounded-2xl border bg-slate-50 p-4">
              <div class="text-xs font-extrabold tracking-widest text-slate-500">ROLE</div>
              <div class="mt-2 font-extrabold text-slate-900"><?php echo h($meDb["role"]); ?></div>
            </div>
            <div class="rounded-2xl border bg-slate-50 p-4">
              <div class="text-xs font-extrabold tracking-widest text-slate-500">STATUS</div>
              <div class="mt-2 font-extrabold text-slate-900"><?php echo ((int)$meDb["is_active"]===1) ? "ACTIVE" : "DISABLED"; ?></div>
            </div>
            <div class="rounded-2xl border bg-slate-50 p-4">
              <div class="text-xs font-extrabold tracking-widest text-slate-500">CREATED</div>
              <div class="mt-2 font-extrabold text-slate-900"><?php echo h(date("Y-m-d", strtotime($meDb["created_at"]))); ?></div>
            </div>
          </div>
        </div>
      </section>

      <!-- Change password -->
      <section class="mt-6 rounded-3xl border bg-white p-6 shadow-sm">
        <div class="font-extrabold text-lg">Change password</div>
        <div class="text-sm font-semibold text-slate-500">Use a strong password</div>

        <form method="POST" class="mt-5 grid gap-4 md:grid-cols-3">
          <input type="hidden" name="action" value="change_password">
          <div>
            <label class="text-xs font-extrabold tracking-widest text-slate-500">CURRENT</label>
            <input type="password" name="current_password"
              class="mt-2 w-full rounded-2xl border bg-white px-4 py-3 text-sm outline-none" />
          </div>
          <div>
            <label class="text-xs font-extrabold tracking-widest text-slate-500">NEW</label>
            <input type="password" name="new_password"
              class="mt-2 w-full rounded-2xl border bg-white px-4 py-3 text-sm outline-none" />
          </div>
          <div>
            <label class="text-xs font-extrabold tracking-widest text-slate-500">CONFIRM</label>
            <input type="password" name="confirm_password"
              class="mt-2 w-full rounded-2xl border bg-white px-4 py-3 text-sm outline-none" />
          </div>

          <div class="md:col-span-3 flex justify-end">
            <button class="rounded-2xl bg-orange-500 px-5 py-3 text-sm font-extrabold text-white hover:bg-orange-600" type="submit">
              Update password →
            </button>
          </div>
        </form>
      </section>

      <?php if ($myRole === "ADMIN"): ?>
        <!-- Admin Users Management -->
        <section class="mt-8">
          <div class="flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
            <div>
              <div class="text-2xl font-extrabold tracking-tight">Users</div>
              <div class="text-sm font-semibold text-slate-500">Admin can create, edit, activate and delete users</div>
            </div>

            <div class="flex gap-2">
              <form method="GET" class="flex gap-2">
                <input name="uq" value="<?php echo h($uq); ?>"
                  class="w-64 rounded-2xl border bg-white px-4 py-2 text-sm outline-none"
                  placeholder="Search users..." />
                <button class="rounded-2xl border bg-white px-4 py-2 text-sm font-extrabold hover:bg-slate-50" type="submit">
                  Search
                </button>
              </form>

              <button id="openUserModal"
                class="rounded-2xl bg-orange-500 px-4 py-2 text-sm font-extrabold text-white hover:bg-orange-600">
                + Add User
              </button>
            </div>
          </div>

          <div class="mt-5 rounded-3xl border bg-white shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
              <table class="w-full text-left text-sm">
                <thead class="bg-slate-50 text-xs font-extrabold tracking-widest text-slate-500">
                  <tr>
                    <th class="px-5 py-4">NAME</th>
                    <th class="px-5 py-4">EMAIL</th>
                    <th class="px-5 py-4">ROLE</th>
                    <th class="px-5 py-4">STATUS</th>
                    <th class="px-5 py-4">CREATED</th>
                    <th class="px-5 py-4 text-right">ACTIONS</th>
                  </tr>
                </thead>
                <tbody class="divide-y">
                  <?php if (!$adminUsers): ?>
                    <tr><td colspan="6" class="px-5 py-10 text-center text-slate-500 font-semibold">No users found.</td></tr>
                  <?php else: ?>
                    <?php foreach ($adminUsers as $u): ?>
                      <?php
                        $badge = ((int)$u["is_active"]===1)
                          ? "bg-emerald-100 text-emerald-700"
                          : "bg-slate-100 text-slate-700";
                        $payload = base64_encode(json_encode($u));
                      ?>
                      <tr class="hover:bg-slate-50/60">
                        <td class="px-5 py-4 font-extrabold text-slate-900"><?php echo h($u["full_name"]); ?></td>
                        <td class="px-5 py-4 font-semibold text-slate-700"><?php echo h($u["email"]); ?></td>
                        <td class="px-5 py-4 font-bold text-slate-800"><?php echo h($u["role"]); ?></td>
                        <td class="px-5 py-4">
                          <span class="inline-flex rounded-full px-3 py-1 text-xs font-extrabold <?php echo $badge; ?>">
                            <?php echo ((int)$u["is_active"]===1) ? "ACTIVE" : "DISABLED"; ?>
                          </span>
                        </td>
                        <td class="px-5 py-4 font-semibold text-slate-700"><?php echo h(date("Y-m-d", strtotime($u["created_at"]))); ?></td>
                        <td class="px-5 py-4">
                          <div class="flex justify-end gap-2">
                            <button type="button"
                              class="grid h-10 w-10 place-items-center rounded-2xl border bg-white hover:bg-slate-50"
                              title="Edit"
                              data-u="<?php echo h($payload); ?>"
                              onclick="openUserEdit(this)">✏️</button>

                            <?php if ((int)$u["id"] !== $myId): ?>
                              <form method="POST" onsubmit="return confirm('Delete this user?');">
                                <input type="hidden" name="action" value="admin_delete_user">
                                <input type="hidden" name="id" value="<?php echo (int)$u["id"]; ?>">
                                <button type="submit"
                                  class="grid h-10 w-10 place-items-center rounded-2xl border border-rose-200 bg-rose-50 hover:bg-rose-100"
                                  title="Delete">🗑️</button>
                              </form>
                            <?php else: ?>
                              <div class="grid h-10 w-10 place-items-center rounded-2xl border bg-slate-50 text-slate-300" title="You">
                                ★
                              </div>
                            <?php endif; ?>
                          </div>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>
        </section>

        <!-- Admin User Modal -->
        <div id="userModal" class="fixed inset-0 z-50 hidden">
          <div id="userOverlay" class="absolute inset-0 bg-slate-900/50 backdrop-blur-[1px]"></div>
          <div class="relative mx-auto flex min-h-full max-w-3xl items-center justify-center p-4">
            <div class="w-full max-w-2xl rounded-3xl bg-white shadow-2xl ring-1 ring-black/5">
              <div class="flex items-center justify-between border-b px-6 py-4">
                <div class="text-lg font-extrabold" id="userModalTitle">Add User</div>
                <button type="button" id="closeUserModal"
                  class="grid h-10 w-10 place-items-center rounded-2xl hover:bg-slate-50 text-slate-500">✕</button>
              </div>

              <form method="POST" class="p-6">
                <input type="hidden" name="action" value="admin_save_user">
                <input type="hidden" name="id" id="u_id" value="">

                <div class="grid gap-4 md:grid-cols-2">
                  <div>
                    <label class="text-xs font-extrabold tracking-widest text-slate-500">FULL NAME *</label>
                    <input id="u_full_name" name="full_name" required
                      class="mt-2 w-full rounded-2xl border bg-white px-4 py-3 text-sm outline-none" />
                  </div>

                  <div>
                    <label class="text-xs font-extrabold tracking-widest text-slate-500">EMAIL *</label>
                    <input id="u_email" name="email" type="email" required
                      class="mt-2 w-full rounded-2xl border bg-white px-4 py-3 text-sm outline-none" />
                  </div>

                  <div>
                    <label class="text-xs font-extrabold tracking-widest text-slate-500">ROLE</label>
                    <select id="u_role" name="role"
                      class="mt-2 w-full rounded-2xl border bg-white px-4 py-3 text-sm font-semibold outline-none">
                      <option value="STAFF">STAFF</option>
                      <option value="RECEPTIONIST">RECEPTIONIST</option>
                      <option value="ADMIN">ADMIN</option>
                    </select>
                  </div>

                  <div>
                    <label class="text-xs font-extrabold tracking-widest text-slate-500">STATUS</label>
                    <select id="u_active" name="is_active"
                      class="mt-2 w-full rounded-2xl border bg-white px-4 py-3 text-sm font-semibold outline-none">
                      <option value="1">ACTIVE</option>
                      <option value="0">DISABLED</option>
                    </select>
                  </div>

                  <div>
                    <label class="text-xs font-extrabold tracking-widest text-slate-500">PASSWORD <?php echo "(new user required)"; ?></label>
                    <input id="u_password" name="password" type="password"
                      class="mt-2 w-full rounded-2xl border bg-white px-4 py-3 text-sm outline-none"
                      placeholder="Leave empty to keep unchanged (edit)" />
                  </div>

                  <div>
                    <label class="text-xs font-extrabold tracking-widest text-slate-500">CONFIRM</label>
                    <input id="u_password2" name="password2" type="password"
                      class="mt-2 w-full rounded-2xl border bg-white px-4 py-3 text-sm outline-none"
                      placeholder="Repeat password" />
                  </div>
                </div>

                <div class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                  <button type="button" id="cancelUser"
                    class="rounded-2xl border bg-white px-5 py-3 text-sm font-extrabold hover:bg-slate-50">Cancel</button>
                  <button type="submit"
                    class="rounded-2xl bg-orange-500 px-5 py-3 text-sm font-extrabold text-white hover:bg-orange-600">
                    Save User →
                  </button>
                </div>
              </form>
            </div>
          </div>
        </div>

        <script>
          (function () {
            const modal = document.getElementById("userModal");
            const overlay = document.getElementById("userOverlay");
            const openBtn = document.getElementById("openUserModal");
            const closeBtn = document.getElementById("closeUserModal");
            const cancelBtn = document.getElementById("cancelUser");
            const title = document.getElementById("userModalTitle");

            const f = {
              id: document.getElementById("u_id"),
              full_name: document.getElementById("u_full_name"),
              email: document.getElementById("u_email"),
              role: document.getElementById("u_role"),
              active: document.getElementById("u_active"),
              pw: document.getElementById("u_password"),
              pw2: document.getElementById("u_password2"),
            };

            function openM(){ modal.classList.remove("hidden"); document.body.style.overflow="hidden"; }
            function closeM(){ modal.classList.add("hidden"); document.body.style.overflow=""; }
            function resetForm(){
              f.id.value="";
              f.full_name.value="";
              f.email.value="";
              f.role.value="STAFF";
              f.active.value="1";
              f.pw.value="";
              f.pw2.value="";
            }

            openBtn?.addEventListener("click", () => {
              title.textContent = "Add User";
              resetForm();
              openM();
              setTimeout(() => f.full_name.focus(), 50);
            });

            overlay?.addEventListener("click", closeM);
            closeBtn?.addEventListener("click", closeM);
            cancelBtn?.addEventListener("click", closeM);
            document.addEventListener("keydown", (e) => {
              if (e.key === "Escape" && !modal.classList.contains("hidden")) closeM();
            });

            window.openUserEdit = function(btn){
              const raw = btn.getAttribute("data-u");
              if (!raw) return;
              let data = {};
              try { data = JSON.parse(atob(raw)); } catch(e){ return; }

              title.textContent = "Edit User";
              f.id.value = data.id || "";
              f.full_name.value = data.full_name || "";
              f.email.value = data.email || "";
              f.role.value = data.role || "STAFF";
              f.active.value = String(data.is_active ?? 1);
              f.pw.value = "";
              f.pw2.value = "";
              openM();
              setTimeout(() => f.full_name.focus(), 50);
            }
          })();
        </script>
      <?php endif; ?>

      <?php if ($myRole !== "ADMIN"): ?>
        <section class="mt-6 rounded-3xl border border-rose-200 bg-rose-50 p-6">
          <div class="font-extrabold text-rose-700">Danger zone</div>
          <div class="mt-1 text-sm font-semibold text-rose-700">You can disable your account (you will be logged out).</div>
          <form method="POST" class="mt-4" onsubmit="return confirm('Disable your account?');">
            <input type="hidden" name="action" value="deactivate_me">
            <button class="rounded-2xl bg-rose-600 px-5 py-3 text-sm font-extrabold text-white hover:bg-rose-700" type="submit">
              Disable my account
            </button>
          </form>
        </section>
      <?php endif; ?>

    </div>
  </div>
</div>

<?php include_once __DIR__ . "/includes/footer.php"; ?>
