<?php
// /hospital/patients/view.php
$pageTitle = "Patients • Hospital";
require_once __DIR__ . "/../includes/auth_guard.php";
require_once __DIR__ . "/../includes/db.php";

if (session_status() === PHP_SESSION_NONE) session_start();

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function flash_set($type, $msg){ $_SESSION["flash"] = ["type"=>$type, "msg"=>$msg]; }
function flash_get(){ if (!isset($_SESSION["flash"])) return null; $f=$_SESSION["flash"]; unset($_SESSION["flash"]); return $f; }

// ---------------- POST actions ----------------
if ($_SERVER["REQUEST_METHOD"] === "POST") {
  $action = $_POST["action"] ?? "";

  // ADD / EDIT
  if ($action === "save_patient") {
    $id = trim($_POST["id"] ?? "");

    $patient_code = trim($_POST["patient_code"] ?? "");
    $full_name = trim($_POST["full_name"] ?? "");
    $gender = trim($_POST["gender"] ?? "");
    $date_of_birth = trim($_POST["date_of_birth"] ?? "");
    $phone = trim($_POST["phone"] ?? "");
    $address = trim($_POST["address"] ?? "");
    $emergency_contact_name = trim($_POST["emergency_contact_name"] ?? "");
    $emergency_contact_phone = trim($_POST["emergency_contact_phone"] ?? "");

    if ($full_name === "" || ($gender !== "Male" && $gender !== "Female")) {
      flash_set("error", "Full name and gender are required.");
      header("Location: /hospital/patients/view.php"); exit;
    }

    $dobDb = ($date_of_birth !== "") ? $date_of_birth : null;

    try {
      if ($id === "") {
        // INSERT
        $stmt = $pdo->prepare("
          INSERT INTO patients
            (patient_code, full_name, gender, date_of_birth, phone, address, emergency_contact_name, emergency_contact_phone)
          VALUES
            (:patient_code, :full_name, :gender, :date_of_birth, :phone, :address, :ecn, :ecp)
        ");
        $stmt->execute([
          ":patient_code" => ($patient_code !== "") ? $patient_code : null,
          ":full_name" => $full_name,
          ":gender" => $gender,
          ":date_of_birth" => $dobDb,
          ":phone" => ($phone !== "") ? $phone : null,
          ":address" => ($address !== "") ? $address : null,
          ":ecn" => ($emergency_contact_name !== "") ? $emergency_contact_name : null,
          ":ecp" => ($emergency_contact_phone !== "") ? $emergency_contact_phone : null,
        ]);

        flash_set("success", "Patient added successfully.");
        header("Location: /hospital/patients/view.php"); exit;
      } else {
        // UPDATE
        $stmt = $pdo->prepare("
          UPDATE patients SET
            patient_code = :patient_code,
            full_name = :full_name,
            gender = :gender,
            date_of_birth = :date_of_birth,
            phone = :phone,
            address = :address,
            emergency_contact_name = :ecn,
            emergency_contact_phone = :ecp
          WHERE id = :id
        ");
        $stmt->execute([
          ":patient_code" => ($patient_code !== "") ? $patient_code : null,
          ":full_name" => $full_name,
          ":gender" => $gender,
          ":date_of_birth" => $dobDb,
          ":phone" => ($phone !== "") ? $phone : null,
          ":address" => ($address !== "") ? $address : null,
          ":ecn" => ($emergency_contact_name !== "") ? $emergency_contact_name : null,
          ":ecp" => ($emergency_contact_phone !== "") ? $emergency_contact_phone : null,
          ":id" => (int)$id,
        ]);

        flash_set("success", "Patient updated successfully.");
        header("Location: /hospital/patients/view.php"); exit;
      }
    } catch (Throwable $e) {
      // Most common error: duplicate patient_code
      flash_set("error", "Save failed: " . $e->getMessage());
      header("Location: /hospital/patients/view.php"); exit;
    }
  }

  // DELETE
  if ($action === "delete_patient") {
    $id = (int)($_POST["id"] ?? 0);
    if ($id > 0) {
      $stmt = $pdo->prepare("DELETE FROM patients WHERE id=?");
      $stmt->execute([$id]);
      flash_set("success", "Patient deleted.");
    } else {
      flash_set("error", "Invalid patient.");
    }
    header("Location: /hospital/patients/view.php"); exit;
  }

  header("Location: /hospital/patients/view.php"); exit;
}

// ---------------- Filters (SMART SEARCH) ----------------
$q = trim($_GET["q"] ?? "");
$gender = trim($_GET["gender"] ?? "");
$sort = trim($_GET["sort"] ?? "");

// ---------- AJAX: search patients (used by other pages) ----------
if (isset($_GET["ajax"]) && $_GET["ajax"] === "search_patients") {
  if (ob_get_length()) ob_clean();
  header("Content-Type: application/json; charset=utf-8");
  $term = trim($_GET["term"] ?? "");
  if ($term === "" || mb_strlen($term) < 2) { echo json_encode(["ok"=>true, "rows"=>[]]); exit; }

  $stmt = $pdo->prepare("SELECT id, full_name, phone, patient_code FROM patients WHERE full_name LIKE :t OR phone LIKE :t OR patient_code LIKE :t ORDER BY full_name ASC LIMIT 30");
  $like = "%".$term."%";
  $stmt->execute([
    ":t" => $like,
  ]);
  $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
  echo json_encode(["ok"=>true, "rows"=>$rows]);
  exit;
}

// Build WHERE with multi-word search (React-style)
$where = [];
$params = [];

// Multi-term: "Ahmed Ali" => full_name LIKE %Ahmed% AND full_name LIKE %Ali%
if ($q !== "") {
  $terms = preg_split('/\s+/', $q);
  $terms = array_values(array_filter($terms, fn($t) => $t !== ""));

  $i = 0;
  foreach ($terms as $t) {
    $tn = ":t{$i}_name";
    $tp = ":t{$i}_phone";
    $tc = ":t{$i}_code";
    $clause = "(full_name LIKE $tn OR phone LIKE $tp OR patient_code LIKE $tc";
    if (is_numeric($t)) {
      $ik = ":id".$i;
      $clause .= " OR id = $ik";
      $params[$ik] = (int)$t;
    }
    $clause .= ")";
    $where[] = $clause;
    $params[$tn] = "%$t%";
    $params[$tp] = "%$t%";
    $params[$tc] = "%$t%";
    $i++;
  }
}

if ($gender !== "" && in_array($gender, ["Male","Female"], true)) {
  $where[] = "gender = :gender";
  $params[":gender"] = $gender;
}

$whereSql = $where ? ("WHERE " . implode(" AND ", $where)) : "";

// Stats
$totalPatients = (int)$pdo->query("SELECT COUNT(*) FROM patients")->fetchColumn();
$totalMale = (int)$pdo->query("SELECT COUNT(*) FROM patients WHERE gender='Male'")->fetchColumn();
$totalFemale = (int)$pdo->query("SELECT COUNT(*) FROM patients WHERE gender='Female'")->fetchColumn();

// List
$rows = [];
// Determine ordering from single `sort` param
$sort = strtolower($sort);
switch ($sort) {
  case 'id_up':
    $orderSql = 'ORDER BY id ASC';
    break;
  case 'id_down':
    $orderSql = 'ORDER BY id DESC';
    break;
  case 'name_az':
    $orderSql = 'ORDER BY full_name ASC';
    break;
  case 'name_za':
    $orderSql = 'ORDER BY full_name DESC';
    break;
  case 'oldest':
    $orderSql = 'ORDER BY created_at ASC';
    break;
  case 'newest':
  default:
    $orderSql = 'ORDER BY created_at DESC';
    break;
}

try {
  $sql = "SELECT * FROM patients $whereSql $orderSql LIMIT 300";
  $stmt = $pdo->prepare($sql);
  $stmt->execute($params);
  $rows = $stmt->fetchAll() ?: [];
} catch (Throwable $e) {
  $rows = [];
  flash_set("error", "Filter error: " . $e->getMessage());
}

$flash = flash_get();
include_once __DIR__ . "/../includes/header.php";
?>
 <div class="min-h-screen bg-slate-50 md:pl-72">
<div class="md:flex min-h-screen">
  <?php include __DIR__ . "/../includes/sidebar.php"; ?>

  <div class="flex-1">
    <div class="mx-auto max-w-6xl px-4 py-6">

      <!-- Header -->
      <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
        <div>
          <div class="text-2xl font-extrabold tracking-tight">Patients</div>
          <div class="text-sm font-semibold text-slate-500">Add and manage patient records</div>
        </div>
        <div class="flex items-center gap-2">
          <a href="/hospital/patients/view.php"
             class="rounded-2xl border bg-white px-4 py-2 text-sm font-extrabold hover:bg-slate-50">
            Refresh
          </a>
          <button id="openAddModal"
                  class="rounded-2xl bg-orange-500 px-4 py-2 text-sm font-extrabold text-white hover:bg-orange-600">
            + Add Patient
          </button>
        </div>
      </div>

      <?php if ($flash): ?>
        <div class="mt-4 rounded-2xl border px-4 py-3 text-sm font-semibold
          <?php echo $flash["type"] === "success" ? "border-green-200 bg-green-50 text-green-700" : "border-red-200 bg-red-50 text-red-700"; ?>">
          <?php echo h($flash["msg"]); ?>
        </div>
      <?php endif; ?>

      <!-- Stats -->
      <section class="mt-6 grid gap-4 md:grid-cols-3">
        <div class="rounded-3xl border bg-white p-5 shadow-sm">
          <div class="text-xs font-extrabold tracking-widest text-slate-500">TOTAL</div>
          <div class="mt-2 text-3xl font-extrabold"><?php echo $totalPatients; ?></div>
        </div>
        <div class="rounded-3xl border bg-sky-50 p-5 shadow-sm">
          <div class="text-xs font-extrabold tracking-widest text-slate-500">MALE</div>
          <div class="mt-2 text-3xl font-extrabold"><?php echo $totalMale; ?></div>
        </div>
        <div class="rounded-3xl border bg-rose-50 p-5 shadow-sm">
          <div class="text-xs font-extrabold tracking-widest text-slate-500">FEMALE</div>
          <div class="mt-2 text-3xl font-extrabold"><?php echo $totalFemale; ?></div>
        </div>
      </section>

      <!-- Filters -->
      <section class="mt-6 rounded-3xl border bg-white p-4 shadow-sm">
        <form class="grid gap-3 lg:grid-cols-12" method="GET">
          <div class="lg:col-span-6">
            <div class="flex items-center gap-2 rounded-2xl border bg-white px-4 py-3">
              <span class="text-slate-400">🔎</span>
              <input name="q" value="<?php echo h($q); ?>"
                     class="w-full bg-transparent text-sm outline-none"
                     placeholder="Search by name, phone, id, or code… (multi-word supported)" />
            </div>
            <div class="mt-2 text-xs font-semibold text-slate-500">
              Example: <span class="font-extrabold">Ahmed Ali</span> will match any order/spacing.
            </div>
          </div>

          <div class="lg:col-span-3">
            <select name="sort" class="w-full rounded-2xl border bg-white px-4 py-3 text-sm font-semibold outline-none">
              <option value="">Sort</option>
              <option value="id_up" <?php echo $sort==="id_up"?"selected":""; ?>>ID ↑</option>
              <option value="id_down" <?php echo $sort==="id_down"?"selected":""; ?>>ID ↓</option>
              <option value="name_az" <?php echo $sort==="name_az"?"selected":""; ?>>Name A–Z</option>
              <option value="name_za" <?php echo $sort==="name_za"?"selected":""; ?>>Name Z–A</option>
              <option value="newest" <?php echo ($sort==="newest" || $sort==="")?"selected":""; ?>>Newest</option>
              <option value="oldest" <?php echo $sort==="oldest"?"selected":""; ?>>Oldest</option>
            </select>
          </div>

          <div class="lg:col-span-2">
            <select name="gender" class="w-full rounded-2xl border bg-white px-4 py-3 text-sm font-semibold outline-none">
              <option value="">Gender</option>
              <option value="Male" <?php echo $gender==="Male"?"selected":""; ?>>Male</option>
              <option value="Female" <?php echo $gender==="Female"?"selected":""; ?>>Female</option>
            </select>
          </div>

          <div class="lg:col-span-1 flex gap-2">
            <button class="w-full rounded-2xl border bg-white px-4 py-3 text-sm font-extrabold hover:bg-slate-50" type="submit">
              Filter
            </button>
          </div>
        </form>

        <div class="mt-3 flex items-center justify-between text-sm">
          <div class="font-semibold text-slate-600">Showing: <span class="font-extrabold text-slate-900"><?php echo is_array($rows)?count($rows):0; ?></span></div>
          <a href="/hospital/patients/view.php" class="font-extrabold text-orange-600 hover:text-orange-700">Reset</a>
        </div>
      </section>

      <!-- Table -->
      <section class="mt-6 rounded-3xl border bg-white shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
          <table class="w-full text-left text-sm">
            <thead class="bg-slate-50 text-xs font-extrabold tracking-widest text-slate-500">
              <tr>
                <th class="px-5 py-4">PATIENT</th>
                <th class="px-5 py-4">GENDER</th>
                <th class="px-5 py-4">DOB</th>
                <th class="px-5 py-4">PHONE</th>
                <th class="px-5 py-4">EMERGENCY</th>
                <th class="px-5 py-4 text-right">ACTIONS</th>
              </tr>
            </thead>
            <tbody class="divide-y">
              <?php if (!$rows): ?>
                <tr>
                  <td colspan="6" class="px-5 py-10 text-center text-slate-500 font-semibold">
                    No patients found.
                  </td>
                </tr>
              <?php else: ?>
                <?php foreach ($rows as $r): ?>
                  <?php
                    // SAFE payload for edit modal
                    $payload = base64_encode(json_encode($r));
                  ?>
                  <tr class="hover:bg-slate-50/60">
                    <td class="px-5 py-4">
                      <div class="flex items-center gap-3">
                        <?php
                          $icon_name = 'person';
                          $bg = 'bg-orange-100';
                          $g = strtolower(trim((string)($r['gender'] ?? '')));
                          if ($g === 'female' || $g === 'f') { $icon_name = 'female'; $bg = 'bg-rose-100'; }
                          else if ($g === 'male' || $g === 'm') { $icon_name = 'male'; $bg = 'bg-sky-100'; }
                        ?>
                        <div class="h-10 w-10 rounded-2xl <?php echo $bg; ?> flex items-center justify-center">
                          <span class="material-icons text-slate-700 text-xl" aria-hidden="true"><?php echo $icon_name; ?></span>
                        </div>
                        <div class="min-w-0">
                          <div class="font-extrabold text-slate-900 truncate"><?php echo h($r["full_name"]); ?></div>
                          <div class="text-xs font-semibold text-slate-500">
                            <?php echo $r["patient_code"] ? "ID: ".h($r["patient_code"]) : "ID: PAT-".h($r["id"]); ?>
                          </div>
                        </div>
                      </div>
                    </td>

                    <td class="px-5 py-4 font-bold text-slate-800"><?php echo h($r["gender"]); ?></td>
                    <td class="px-5 py-4 font-semibold text-slate-700">
                      <?php
                        $dob = $r["date_of_birth"] ?? null;
                        if ($dob && $dob !== "") {
                          try {
                            $dobDt = new DateTime($dob);
                            $now = new DateTime();
                            $age = $dobDt->diff($now)->y;
                            echo h($age . ' yrs');
                          } catch (Throwable $e) {
                            echo h($dob);
                          }
                        } else {
                          echo '-';
                        }
                      ?>
                    </td>
                    <td class="px-5 py-4 font-semibold text-slate-700"><?php echo h($r["phone"] ?? "-"); ?></td>

                    <td class="px-5 py-4">
                      <div class="text-sm font-bold text-slate-800"><?php echo h($r["emergency_contact_name"] ?? "-"); ?></div>
                      <div class="text-xs font-semibold text-slate-500"><?php echo h($r["emergency_contact_phone"] ?? "-"); ?></div>
                    </td>

                    <td class="px-5 py-4">
                      <div class="flex justify-end gap-2">
                        <button type="button"
                          class="grid h-10 w-10 place-items-center rounded-2xl border bg-white hover:bg-slate-50"
                          title="Edit"
                          data-p="<?php echo h($payload); ?>"
                          onclick="openEdit(this)">✏️</button>

                        <form method="POST" onsubmit="return confirm('Delete this patient?');">
                          <input type="hidden" name="action" value="delete_patient">
                          <input type="hidden" name="id" value="<?php echo (int)$r["id"]; ?>">
                          <button type="submit"
                            class="grid h-10 w-10 place-items-center rounded-2xl border border-rose-200 bg-rose-50 hover:bg-rose-100"
                            title="Delete">🗑️</button>
                        </form>
                      </div>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </section>

    </div>
  </div>
</div>

<!-- Modal (Add/Edit Patient) -->
<div id="patModal" class="fixed inset-0 z-50 hidden">
  <div id="patOverlay" class="absolute inset-0 bg-slate-900/50 backdrop-blur-[1px]"></div>

  <div class="relative mx-auto flex min-h-full max-w-5xl items-center justify-center p-4">
    <div class="w-full max-w-4xl rounded-3xl bg-white shadow-2xl ring-1 ring-black/5 max-h-[90vh] overflow-auto">
      <div class="flex items-center justify-between border-b px-6 py-4">
        <div class="text-lg font-extrabold" id="patTitle">Add Patient</div>
        <button type="button" id="closePat"
          class="grid h-10 w-10 place-items-center rounded-2xl hover:bg-slate-50 text-slate-500">✕</button>
      </div>

      <form method="POST" class="p-6">
        <input type="hidden" name="action" value="save_patient">
        <input type="hidden" name="id" id="p_id" value="">

        <div class="grid gap-4 md:grid-cols-2">
          <div>
            <label class="text-xs font-extrabold tracking-widest text-slate-500">FULL NAME *</label>
            <input id="p_full_name" name="full_name" required
              class="mt-2 w-full rounded-2xl border bg-white px-4 py-3 text-sm outline-none"
              placeholder="e.g. Ahmed Ali Mohamed">
          </div>

          

          <div>
            <label class="text-xs font-extrabold tracking-widest text-slate-500">GENDER *</label>
            <select id="p_gender" name="gender" required
              class="mt-2 w-full rounded-2xl border bg-white px-4 py-3 text-sm font-semibold outline-none">
              <option value="">Select</option>
              <option value="Male">Male</option>
              <option value="Female">Female</option>
            </select>
          </div>

          <div>
            <label class="text-xs font-extrabold tracking-widest text-slate-500">DATE OF BIRTH (optional)</label>
            <input id="p_dob" type="date" name="date_of_birth"
              class="mt-2 w-full rounded-2xl border bg-white px-4 py-3 text-sm outline-none">
          </div>

          <div>
            <label class="text-xs font-extrabold tracking-widest text-slate-500">PHONE (optional)</label>
            <input id="p_phone" name="phone"
              class="mt-2 w-full rounded-2xl border bg-white px-4 py-3 text-sm outline-none"
              placeholder="+252 ...">
          </div>

          <div>
            <label class="text-xs font-extrabold tracking-widest text-slate-500">ADDRESS (optional)</label>
            <input id="p_address" name="address"
              class="mt-2 w-full rounded-2xl border bg-white px-4 py-3 text-sm outline-none"
              placeholder="Mogadishu, ...">
          </div>

          <div>
            <label class="text-xs font-extrabold tracking-widest text-slate-500">EMERGENCY CONTACT NAME (optional)</label>
            <input id="p_ecn" name="emergency_contact_name"
              class="mt-2 w-full rounded-2xl border bg-white px-4 py-3 text-sm outline-none"
              placeholder="e.g. Hassan Ali">
          </div>

          <div>
            <label class="text-xs font-extrabold tracking-widest text-slate-500">EMERGENCY CONTACT PHONE (optional)</label>
            <input id="p_ecp" name="emergency_contact_phone"
              class="mt-2 w-full rounded-2xl border bg-white px-4 py-3 text-sm outline-none"
              placeholder="+252 ...">
          </div>
        </div>

        <div class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
          <button type="button" id="cancelPat"
            class="rounded-2xl border bg-white px-5 py-3 text-sm font-extrabold hover:bg-slate-50">
            Cancel
          </button>
          <button type="submit"
            class="rounded-2xl bg-orange-500 px-5 py-3 text-sm font-extrabold text-white hover:bg-orange-600">
            Save Patient →
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
(function(){
  const modal = document.getElementById("patModal");
  const overlay = document.getElementById("patOverlay");
  const openAdd = document.getElementById("openAddModal");
  const closeBtn = document.getElementById("closePat");
  const cancelBtn = document.getElementById("cancelPat");
  const title = document.getElementById("patTitle");

  const f = {
    id: document.getElementById("p_id"),
    full: document.getElementById("p_full_name"),
    gender: document.getElementById("p_gender"),
    dob: document.getElementById("p_dob"),
    phone: document.getElementById("p_phone"),
    address: document.getElementById("p_address"),
    ecn: document.getElementById("p_ecn"),
    ecp: document.getElementById("p_ecp"),
  };

  function openM(){ modal.classList.remove("hidden"); document.body.style.overflow="hidden"; }
  function closeM(){ modal.classList.add("hidden"); document.body.style.overflow=""; }
  function reset(){
    f.id.value=""; f.full.value=""; f.gender.value="";
    f.dob.value=""; f.phone.value=""; f.address.value=""; f.ecn.value=""; f.ecp.value="";
  }

  openAdd?.addEventListener("click", () => {
    title.textContent = "Add Patient";
    reset(); openM();
    setTimeout(()=>f.full.focus(),50);
  });

  overlay?.addEventListener("click", closeM);
  closeBtn?.addEventListener("click", closeM);
  cancelBtn?.addEventListener("click", closeM);
  document.addEventListener("keydown", (e)=>{ if(e.key==="Escape" && !modal.classList.contains("hidden")) closeM(); });

  window.openEdit = function(btn){
    const raw = btn.getAttribute("data-p");
    if(!raw) return;
    let data = {};
    try { data = JSON.parse(atob(raw)); } catch(e){ return; }

    title.textContent = "Edit Patient";
    f.id.value = data.id || "";
    f.full.value = data.full_name || "";
    f.gender.value = data.gender || "";
    f.dob.value = data.date_of_birth || "";
    f.phone.value = data.phone || "";
    f.address.value = data.address || "";
    f.ecn.value = data.emergency_contact_name || "";
    f.ecp.value = data.emergency_contact_phone || "";

    openM();
    setTimeout(()=>f.full.focus(),50);
  }
})();
</script>

<?php include_once __DIR__ . "/../includes/footer.php"; ?>
