<?php
// prescriptions/view.php
$pageTitle = "Prescriptions • Hospital";
require_once __DIR__ . "/../includes/auth_guard.php";
require_once __DIR__ . "/../includes/db.php";

if (session_status() === PHP_SESSION_NONE) session_start();

// ---------- helpers ----------
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function flash_set($type, $msg){ $_SESSION["flash"] = ["type"=>$type, "msg"=>$msg]; }
function flash_get(){
  if (!isset($_SESSION["flash"])) return null;
  $f = $_SESSION["flash"]; unset($_SESSION["flash"]); return $f;
}
$flash = flash_get();

// ---------- AJAX: search patients (name/phone) ----------
if (isset($_GET["ajax"]) && $_GET["ajax"] === "search_patients") {
    if (ob_get_length()) ob_clean();

  header("Content-Type: application/json; charset=utf-8");

  $term = trim($_GET["term"] ?? "");
  if ($term === "" || mb_strlen($term) < 2) {
    echo json_encode(["ok"=>true, "rows"=>[]]);
    exit;
  }

 $stmt = $pdo->prepare("
  SELECT id, full_name, phone, gender, date_of_birth
  FROM patients
  WHERE full_name LIKE :t1 OR phone LIKE :t2
  ORDER BY full_name ASC
  LIMIT 30
");
$stmt->execute([
  ":t1" => "%".$term."%",
  ":t2" => "%".$term."%",
]);

  $rows = $stmt->fetchAll() ?: [];

  echo json_encode(["ok"=>true, "rows"=>$rows]);
  exit;
}

// ---------- AJAX: prescription details (for edit) ----------
// ---------- AJAX: prescription details (for edit) ----------
if (isset($_GET["ajax"]) && $_GET["ajax"] === "prescription_details") {
  if (ob_get_length()) ob_clean();
  header("Content-Type: application/json; charset=utf-8");

  $pid = (int)($_GET["id"] ?? 0);
  if ($pid <= 0) { echo json_encode(["ok"=>false]); exit; }

  $stmt = $pdo->prepare("
    SELECT id, patient_id, prescribed_by_employee_id, notes
    FROM prescriptions
    WHERE id=?
    LIMIT 1
  ");
  $stmt->execute([$pid]);
  $pr = $stmt->fetch(PDO::FETCH_ASSOC);
  if (!$pr) { echo json_encode(["ok"=>false]); exit; }

  // ✅ return item_name too
  $stmt2 = $pdo->prepare("
    SELECT
      pri.pharmacy_item_id AS item_id,
      pri.quantity AS qty,
      pi.item_name
    FROM prescription_items pri
    JOIN pharmacy_items pi ON pi.id = pri.pharmacy_item_id
    WHERE pri.prescription_id=?
    ORDER BY pri.id ASC
  ");
  $stmt2->execute([$pid]);
  $items = $stmt2->fetchAll(PDO::FETCH_ASSOC) ?: [];

  $pstmt = $pdo->prepare("SELECT id, full_name, phone FROM patients WHERE id=?");
  $pstmt->execute([(int)$pr["patient_id"]]);
  $pat = $pstmt->fetch(PDO::FETCH_ASSOC);

  echo json_encode([
    "ok"=>true,
    "prescription"=>$pr,
    "items"=>$items,
    "patient"=>$pat ?: null
  ]);
  exit;
}


// ---------- handle POST actions ----------
if ($_SERVER["REQUEST_METHOD"] === "POST") {
  $action = $_POST["action"] ?? "";

  if ($action === "save_prescription") {
    $id = trim($_POST["id"] ?? "");

    $patient_id = (int)($_POST["patient_id"] ?? 0);
    $prescribed_by_employee_id = (int)($_POST["prescribed_by_employee_id"] ?? 0);
    $notes = trim($_POST["notes"] ?? "");

    // items arrays
    $item_ids = $_POST["item_id"] ?? [];
    $qtys = $_POST["qty"] ?? [];

    if ($patient_id <= 0 || $prescribed_by_employee_id <= 0) {
      flash_set("error", "Patient and Prescribed By are required.");
      header("Location: /hospital/prescriptions/view.php");
      exit;
    }

    // Validate at least 1 item
    $validItems = [];
    for ($i=0; $i<count($item_ids); $i++) {
      $pid = (int)($item_ids[$i] ?? 0);
      $q = (int)($qtys[$i] ?? 0);
      if ($pid > 0 && $q > 0) {
        $validItems[] = ["item_id"=>$pid, "qty"=>$q];
      }
    }
    if (!$validItems) {
      flash_set("error", "Please add at least one pharmacy item with quantity.");
      header("Location: /hospital/prescriptions/view.php");
      exit;
    }

    // validate patient exists
    $chkP = $pdo->prepare("SELECT id FROM patients WHERE id=?");
    $chkP->execute([$patient_id]);
    if (!$chkP->fetchColumn()) {
      flash_set("error", "Invalid patient selected.");
      header("Location: /hospital/prescriptions/view.php");
      exit;
    }

    // validate employee exists
  // validate employee exists + allowed role
$chkE = $pdo->prepare("
  SELECT id, job_title
  FROM employees
  WHERE id=?
  LIMIT 1
");
$chkE->execute([$prescribed_by_employee_id]);
$empRow = $chkE->fetch(PDO::FETCH_ASSOC);

if (!$empRow) {
  flash_set("error", "Invalid employee selected.");
  header("Location: /hospital/prescriptions/view.php");
  exit;
}

// Only DOCTOR can prescribe
$allowed = ["DOCTOR"]; // add "NURSE" if needed

if (!in_array($empRow["job_title"], $allowed, true)) {
  flash_set("error", "Only doctors can create prescriptions.");
  header("Location: /hospital/prescriptions/view.php");
  exit;
}

    // validate pharmacy items exist (bulk check)
    $ids = array_map(fn($x)=> (int)$x["item_id"], $validItems);
    $placeholders = implode(",", array_fill(0, count($ids), "?"));
    $chkI = $pdo->prepare("SELECT id FROM pharmacy_items WHERE id IN ($placeholders)");
    $chkI->execute($ids);
    $found = $chkI->fetchAll(PDO::FETCH_COLUMN) ?: [];
    if (count($found) !== count(array_unique($ids))) {
      flash_set("error", "One or more pharmacy items are invalid.");
      header("Location: /hospital/prescriptions/view.php");
      exit;
    }

    try {
      $pdo->beginTransaction();

      if ($id === "") {
        // INSERT prescription
        $stmt = $pdo->prepare("
          INSERT INTO prescriptions (patient_id, prescribed_by_employee_id, notes)
          VALUES (:patient_id, :emp_id, :notes)
        ");
        $stmt->execute([
          ":patient_id" => $patient_id,
          ":emp_id" => $prescribed_by_employee_id,
          ":notes" => ($notes !== "") ? $notes : null,
        ]);
        $presc_id = (int)$pdo->lastInsertId();

      } else {
        $presc_id = (int)$id;

        // ensure exists
        $chk = $pdo->prepare("SELECT id FROM prescriptions WHERE id=?");
        $chk->execute([$presc_id]);
        if (!$chk->fetchColumn()) {
          $pdo->rollBack();
          flash_set("error", "Prescription not found.");
          header("Location: /hospital/prescriptions/view.php");
          exit;
        }

        // UPDATE prescription
        $stmt = $pdo->prepare("
          UPDATE prescriptions SET
            patient_id = :patient_id,
            prescribed_by_employee_id = :emp_id,
            notes = :notes
          WHERE id = :id
        ");
        $stmt->execute([
          ":patient_id" => $patient_id,
          ":emp_id" => $prescribed_by_employee_id,
          ":notes" => ($notes !== "") ? $notes : null,
          ":id" => $presc_id,
        ]);

        // Replace items
        $pdo->prepare("DELETE FROM prescription_items WHERE prescription_id=?")->execute([$presc_id]);
      }

      // Insert items
      $ins = $pdo->prepare("
        INSERT INTO prescription_items (prescription_id, pharmacy_item_id, quantity, unit_price_at_time)
        VALUES (:presc_id, :item_id, :qty, NULL)
      ");
      foreach ($validItems as $v) {
        $ins->execute([
          ":presc_id" => $presc_id,
          ":item_id" => (int)$v["item_id"],
          ":qty" => (int)$v["qty"],
        ]);
      }

      $pdo->commit();

      flash_set("success", $id==="" ? "Prescription created successfully." : "Prescription updated successfully.");
      header("Location: /hospital/prescriptions/view.php");
      exit;

    } catch (Throwable $e) {
      if ($pdo->inTransaction()) $pdo->rollBack();
      flash_set("error", "Save error: ".$e->getMessage());
      header("Location: /hospital/prescriptions/view.php");
      exit;
    }
  }

  if ($action === "delete_prescription") {
    $id = (int)($_POST["id"] ?? 0);
    if ($id > 0) {
      $stmt = $pdo->prepare("DELETE FROM prescriptions WHERE id=?");
      $stmt->execute([$id]);
      flash_set("success", "Prescription deleted.");
    } else {
      flash_set("error", "Invalid prescription.");
    }
    header("Location: /hospital/prescriptions/view.php");
    exit;
  }
}

// ---------- filters ----------
$q = trim($_GET["q"] ?? "");
$emp = trim($_GET["emp"] ?? "");
$from = trim($_GET["from"] ?? "");
$to = trim($_GET["to"] ?? "");
$bill_st = trim($_GET["bill_st"] ?? "");

$where = [];
$params = [];

if ($q !== "") {
  $where[] = "(p.full_name LIKE :q1 OR p.phone LIKE :q2 OR e2.full_name LIKE :q3 OR pi.item_name LIKE :q4)";
  $params[":q1"] = "%$q%";
  $params[":q2"] = "%$q%";
  $params[":q3"] = "%$q%";
  $params[":q4"] = "%$q%";
}

if ($emp !== "") {
  $where[] = "pr.prescribed_by_employee_id = :emp";
  $params[":emp"] = (int)$emp;
}
if ($bill_st !== "" && in_array($bill_st, ["UNPAID","PAID"], true)) {
  if ($bill_st === "UNPAID") {
    // Treat prescriptions with no bill as UNPAID as well
    $where[] = "(b.status = :bill_st OR b.id IS NULL)";
    $params[":bill_st"] = "UNPAID";
  } else {
    $where[] = "b.status = :bill_st";
    $params[":bill_st"] = $bill_st;
  }
}
if ($from !== "") {
  $where[] = "DATE(pr.created_at) >= :from";
  $params[":from"] = $from;
}
if ($to !== "") {
  $where[] = "DATE(pr.created_at) <= :to";
  $params[":to"] = $to;
}
$whereSql = $where ? ("WHERE " . implode(" AND ", $where)) : "";

// stats
$totalPrescriptions = (int)$pdo->query("SELECT COUNT(*) FROM prescriptions")->fetchColumn();
$todayPrescriptions = (int)$pdo->query("SELECT COUNT(*) FROM prescriptions WHERE DATE(created_at)=CURDATE()")->fetchColumn();
$totalPatientsWithPresc = (int)$pdo->query("SELECT COUNT(DISTINCT patient_id) FROM prescriptions")->fetchColumn();

// dropdowns
$employees = $pdo->query("
  SELECT id, full_name, job_title
  FROM employees
  WHERE job_title='DOCTOR'
  ORDER BY full_name ASC
")->fetchAll() ?: [];

$pharmacyItems = $pdo->query("
  SELECT id, item_name, company_name, unit_price
  FROM pharmacy_items
  ORDER BY item_name ASC
  LIMIT 700
")->fetchAll() ?: [];

// list
$stmt = $pdo->prepare("
  SELECT
    pr.id,
    pr.patient_id,
    pr.prescribed_by_employee_id,
    pr.notes,
    pr.created_at,
    p.full_name AS patient_name,
    p.phone AS patient_phone,
    e2.full_name AS prescribed_by_name,
    -- if there are multiple bills linkable, prefer any PAID bill id
    MAX(CASE WHEN b.status = 'PAID' THEN b.id ELSE NULL END) AS paid_bill_id,
    CASE WHEN SUM(CASE WHEN b.status='PAID' THEN 1 ELSE 0 END) > 0 THEN 'PAID' ELSE 'UNPAID' END AS presc_bill_status,
    GROUP_CONCAT(CONCAT(pi.item_name, ' x', pri.quantity) ORDER BY pri.id SEPARATOR ' | ') AS items_preview
  FROM prescriptions pr
  JOIN patients p ON p.id = pr.patient_id
  JOIN employees e2 ON e2.id = pr.prescribed_by_employee_id
  LEFT JOIN bills b ON b.prescription_id = pr.id AND b.bill_type = 'PRESCRIPTION'
  LEFT JOIN prescription_items pri ON pri.prescription_id = pr.id
  LEFT JOIN pharmacy_items pi ON pi.id = pri.pharmacy_item_id
  $whereSql
  GROUP BY pr.id
  ORDER BY pr.created_at DESC
  LIMIT 200
");
$stmt->execute($params);
$rows = $stmt->fetchAll() ?: [];

include_once __DIR__ . "/../includes/header.php";
?>
 <div class="min-h-screen bg-slate-50 md:pl-72">
<div class="md:flex min-h-screen">
  <?php include __DIR__ . "/../includes/sidebar.php"; ?>

  <div class="flex-1">
    <div class="mx-auto max-w-6xl px-4 py-6">

      <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
        <div>
          <div class="text-2xl font-extrabold tracking-tight">Prescriptions</div>
          <div class="text-sm font-semibold text-slate-500">Create prescriptions directly for patients (no appointment needed)</div>
        </div>

        <div class="flex items-center gap-2">
          <a href="/hospital/prescriptions/view.php"
             class="rounded-2xl border bg-white px-4 py-2 text-sm font-extrabold hover:bg-slate-50">
            Refresh
          </a>
          <button id="openAddModal" type="button"
                  class="rounded-2xl bg-orange-500 px-4 py-2 text-sm font-extrabold text-white hover:bg-orange-600">
            + Add Prescription
          </button>
        </div>
      </div>

      <?php if ($flash): ?>
        <div class="mt-4 rounded-2xl border px-4 py-3 text-sm font-semibold
          <?php echo $flash["type"] === "success" ? "border-green-200 bg-green-50 text-green-700" : "border-red-200 bg-red-50 text-red-700"; ?>">
          <?php echo h($flash["msg"]); ?>
        </div>
      <?php endif; ?>

      <section class="mt-6 grid gap-4 md:grid-cols-3">
        <div class="rounded-3xl border bg-white p-5 shadow-sm">
          <div class="text-xs font-extrabold tracking-widest text-slate-500">TOTAL</div>
          <div class="mt-2 text-3xl font-extrabold"><?php echo (int)$totalPrescriptions; ?></div>
        </div>
        <div class="rounded-3xl border bg-orange-50 p-5 shadow-sm">
          <div class="text-xs font-extrabold tracking-widest text-slate-500">TODAY</div>
          <div class="mt-2 text-3xl font-extrabold"><?php echo (int)$todayPrescriptions; ?></div>
        </div>
        <div class="rounded-3xl border bg-sky-50 p-5 shadow-sm">
          <div class="text-xs font-extrabold tracking-widest text-slate-500">PATIENTS WITH PRESC</div>
          <div class="mt-2 text-3xl font-extrabold"><?php echo (int)$totalPatientsWithPresc; ?></div>
        </div>
      </section>

      <section class="mt-6 rounded-3xl border bg-white p-4 shadow-sm">
        <form class="grid gap-3 lg:grid-cols-12" method="GET">
          <div class="lg:col-span-6">
            <div class="flex items-center gap-2 rounded-2xl border bg-white px-4 py-3">
              <span class="text-slate-400">🔎</span>
              <input name="q" value="<?php echo h($q); ?>"
                     class="w-full bg-transparent text-sm outline-none"
                     placeholder="Search patient name/phone, prescriber, item..." />
            </div>
          </div>

          <div class="lg:col-span-2">
            <select name="emp" class="w-full rounded-2xl border bg-white px-4 py-3 text-sm font-semibold outline-none">
              <option value="">Prescribed By (All)</option>
              <?php foreach ($employees as $e): ?>
                <option value="<?php echo (int)$e["id"]; ?>" <?php echo ((string)$emp === (string)$e["id"]) ? "selected" : ""; ?>>
                  <?php echo h($e["full_name"]); ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="lg:col-span-1">
            <select name="bill_st" class="w-full rounded-2xl border bg-white px-4 py-3 text-sm font-semibold outline-none">
              <option value="">Billing</option>
              <option value="UNPAID" <?php echo $bill_st==="UNPAID"?"selected":""; ?>>UNPAID</option>
              <option value="PAID" <?php echo $bill_st==="PAID"?"selected":""; ?>>PAID</option>
            </select>
          </div>

          <div class="lg:col-span-1">
            <input type="date" name="from" value="<?php echo h($from); ?>"
                   class="w-full rounded-2xl border bg-white px-3 py-3 text-sm font-semibold outline-none" />
          </div>

          <div class="lg:col-span-1">
            <input type="date" name="to" value="<?php echo h($to); ?>"
                   class="w-full rounded-2xl border bg-white px-3 py-3 text-sm font-semibold outline-none" />
          </div>

          <div class="lg:col-span-1">
            <button class="w-full rounded-2xl border bg-white px-4 py-3 text-sm font-extrabold hover:bg-slate-50" type="submit">
              Filter
            </button>
          </div>
        </form>

        <div class="mt-3 flex items-center justify-between text-sm">
          <div class="font-semibold text-slate-600">Showing: <span class="font-extrabold text-slate-900"><?php echo count($rows); ?></span></div>
          <a href="/hospital/prescriptions/view.php" class="font-extrabold text-orange-600 hover:text-orange-700">Reset</a>
        </div>
      </section>

      <section class="mt-6 rounded-3xl border bg-white shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
          <table class="w-full text-left text-sm">
            <thead class="bg-slate-50 text-xs font-extrabold tracking-widest text-slate-500">
              <tr>
                <th class="px-5 py-4">PATIENT</th>
                <th class="px-5 py-4">PRESCRIBED BY</th>
                <th class="px-5 py-4">ITEMS</th>
                <th class="px-5 py-4">DATE</th>
                <th class="px-5 py-4 text-right">ACTIONS</th>
              </tr>
            </thead>
            <tbody class="divide-y">
              <?php if (!$rows): ?>
                <tr><td colspan="5" class="px-5 py-10 text-center text-slate-500 font-semibold">No prescriptions found.</td></tr>
              <?php else: ?>
                <?php foreach ($rows as $r): ?>
                  <?php
                    $preview = (string)($r["items_preview"] ?? "");
                    if ($preview === "") $preview = "-";
                    if (mb_strlen($preview) > 95) $preview = mb_substr($preview, 0, 95) . "…";
                  ?>
                  <tr class="hover:bg-slate-50/60">
                    <td class="px-5 py-4">
                      <div class="font-extrabold text-slate-900"><?php echo h($r["patient_name"]); ?></div>
                      <div class="text-xs font-semibold text-slate-500">
                        <?php echo h($r["patient_phone"] ?? ""); ?> • #PR-<?php echo (int)$r["id"]; ?>
                      </div>
                    </td>

                    <td class="px-5 py-4 font-bold text-slate-800"><?php echo h($r["prescribed_by_name"]); ?></td>

                    <td class="px-5 py-4">
                      <div class="font-semibold text-slate-800"><?php echo h($preview); ?></div>
                      <?php if (!empty($r["notes"])): ?>
                        <div class="text-xs font-semibold text-slate-500">
                          Notes: <?php echo h(mb_substr((string)$r["notes"],0,70)); ?><?php echo (mb_strlen((string)$r["notes"])>70)?"…":""; ?>
                        </div>
                      <?php endif; ?>
                    </td>

                    <td class="px-5 py-4 font-semibold text-slate-700">
                      <?php echo h(date("Y-m-d H:i", strtotime($r["created_at"]))); ?>
                    </td>

                    <td class="px-5 py-4">
                      <div class="flex justify-end gap-2">
                        <?php if (!empty($r['presc_bill_status']) && $r['presc_bill_status'] === 'PAID' && !empty($r['paid_bill_id'])): ?>
                          <a href="/hospital/billing/receipt.php?id=<?php echo (int)$r['paid_bill_id']; ?>" target="_blank"
                             class="grid h-10 place-items-center rounded-2xl bg-sky-600 px-3 text-xs font-extrabold text-white hover:bg-sky-700">
                            RECEIPT
                          </a>

                          <span class="inline-flex h-10 items-center rounded-2xl border bg-slate-100 px-4 py-2 text-sm font-extrabold text-slate-700">LOCKED</span>

                        <?php else: ?>
                          <a href="/hospital/billing/view.php?prescription_id=<?php echo (int)$r["id"]; ?>"
                             class="grid h-10 place-items-center rounded-2xl bg-emerald-600 px-3 text-xs font-extrabold text-white hover:bg-emerald-700">
                            BILLING
                          </a>

                          <button type="button"
                            class="grid h-10 w-10 place-items-center rounded-2xl border bg-white hover:bg-slate-50"
                            title="Edit"
                            data-id="<?php echo (int)$r["id"]; ?>"
                            onclick="openEditById(this)">✏️</button>

                          <form method="POST" onsubmit="return confirm('Delete this prescription?');" style="display:inline-block;margin:0;">
                            <input type="hidden" name="action" value="delete_prescription">
                            <input type="hidden" name="id" value="<?php echo (int)$r["id"]; ?>">
                            <button type="submit"
                              class="grid h-10 w-10 place-items-center rounded-2xl border border-rose-200 bg-rose-50 hover:bg-rose-100"
                              title="Delete">🗑️</button>
                          </form>

                        <?php endif; ?>
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

<!-- Modal -->
<div id="prescModal" class="fixed inset-0 z-50 hidden">
  <div id="prescOverlay" class="absolute inset-0 bg-slate-900/50 backdrop-blur-[1px]"></div>

<div class="relative mx-auto flex min-h-screen max-w-5xl items-start justify-center p-4">

  <div class="w-full max-w-4xl rounded-3xl bg-white shadow-2xl ring-1 ring-black/5 max-h-[90vh] overflow-hidden flex flex-col">

      <div class="flex items-center justify-between border-b px-6 py-4">
        <div class="text-lg font-extrabold" id="prescTitle">Add Prescription</div>
        <button type="button" id="closePresc"
                class="grid h-10 w-10 place-items-center rounded-2xl hover:bg-slate-50 text-slate-500">✕</button>
      </div>

   <form method="POST" class="p-6 overflow-y-auto" id="prescForm">
        <input type="hidden" name="action" value="save_prescription">
        <input type="hidden" name="id" id="presc_id" value="">
        <input type="hidden" name="patient_id" id="patient_id" value="">

        <!-- Patient search -->
        <div class="rounded-3xl border bg-slate-50 p-4">
          <div class="text-xs font-extrabold tracking-widest text-slate-500">PATIENT *</div>

          <div class="mt-2 grid gap-3 md:grid-cols-3">
            <div class="md:col-span-2">
              <input id="patientSearch" type="text"
                class="w-full rounded-2xl border bg-white px-4 py-3 text-sm outline-none"
                placeholder="Search by patient name or phone..." />
              <div class="mt-2 text-xs font-semibold text-slate-500">
                Tip: type at least 2 characters.
              </div>
            </div>

            <div class="md:col-span-1">
              <div class="rounded-2xl border bg-white px-4 py-3">
                <div class="text-[11px] font-extrabold tracking-widest text-slate-500">SELECTED</div>
                <div class="mt-1 text-sm font-extrabold text-slate-900" id="patientSelectedLabel">None</div>
              </div>
            </div>
          </div>

          <div id="patientResults" class="mt-3 hidden overflow-hidden rounded-2xl border bg-white">
            <div class="max-h-56 overflow-y-auto divide-y" id="patientResultsList"></div>
          </div>

          <div id="patientError" class="mt-3 hidden rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-semibold text-rose-700"></div>
        </div>

        <!-- Prescribed by -->
        <div class="mt-4">
          <label class="text-xs font-extrabold tracking-widest text-slate-500">PRESCRIBED BY *</label>
          <select id="prescribed_by_employee_id" name="prescribed_by_employee_id" required
                  class="mt-2 w-full rounded-2xl border bg-white px-4 py-3 text-sm font-semibold outline-none">
            <option value="">Select employee</option>
            <?php foreach ($employees as $e): ?>
              <option value="<?php echo (int)$e["id"]; ?>">
                <?php echo h($e["full_name"]); ?><?php echo $e["job_title"] ? " • ".h($e["job_title"]) : ""; ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <!-- Items repeater -->
        <div class="mt-4">
          <div class="flex items-center justify-between">
            <div class="text-xs font-extrabold tracking-widest text-slate-500">PHARMACY ITEMS *</div>
            <button type="button" id="addItemRow"
              class="rounded-2xl border bg-white px-4 py-2 text-xs font-extrabold hover:bg-slate-50">
              + Add item
            </button>
          </div>

          <div id="itemsWrap" class="mt-3 space-y-3"></div>

          <div class="mt-2 text-xs font-semibold text-slate-500">
            Add item + quantity.
          </div>
        </div>

        <!-- Notes -->
        <div class="mt-4">
          <label class="text-xs font-extrabold tracking-widest text-slate-500">NOTES (optional)</label>
          <textarea id="notes" name="notes" rows="3"
                    class="mt-2 w-full rounded-2xl border bg-white px-4 py-3 text-sm outline-none"></textarea>
        </div>

        <div class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
          <button type="button" id="cancelPresc"
                  class="rounded-2xl border bg-white px-5 py-3 text-sm font-extrabold hover:bg-slate-50">Cancel</button>
          <button type="submit"
                  class="rounded-2xl bg-orange-500 px-5 py-3 text-sm font-extrabold text-white hover:bg-orange-600">
            Save Prescription →
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
    const BASE = "/hospital/prescriptions/view.php";

  const PHARMACY_ITEMS = <?php echo json_encode($pharmacyItems, JSON_UNESCAPED_UNICODE); ?>;

  // modal
  const prescModal = document.getElementById("prescModal");
  const prescOverlay = document.getElementById("prescOverlay");
  const openAdd = document.getElementById("openAddModal");
  const closeBtn = document.getElementById("closePresc");
  const cancelBtn = document.getElementById("cancelPresc");
  const title = document.getElementById("prescTitle");

  // fields
  const f = {
    id: document.getElementById("presc_id"),
    patient_id: document.getElementById("patient_id"),
    emp_id: document.getElementById("prescribed_by_employee_id"),
    notes: document.getElementById("notes"),
  };

  // patient search ui
  const patientSearch = document.getElementById("patientSearch");
  const patientResults = document.getElementById("patientResults");
  const patientResultsList = document.getElementById("patientResultsList");
  const patientSelectedLabel = document.getElementById("patientSelectedLabel");
  const patientError = document.getElementById("patientError");

  // items
  const itemsWrap = document.getElementById("itemsWrap");
  const addItemRowBtn = document.getElementById("addItemRow");

  function openModal(){ prescModal.classList.remove("hidden"); document.body.style.overflow = "hidden"; }
  function closeModal(){ prescModal.classList.add("hidden"); document.body.style.overflow = ""; }

  function escapeHtml(str){
    return String(str)
      .replaceAll("&","&amp;")
      .replaceAll("<","&lt;")
      .replaceAll(">","&gt;")
      .replaceAll('"',"&quot;")
      .replaceAll("'","&#039;");
  }

  function showPatientError(msg){
    patientError.textContent = msg;
    patientError.classList.remove("hidden");
  }
  function clearPatientError(){
    patientError.classList.add("hidden");
    patientError.textContent = "";
  }

  function buildItemOptions(selectedId){
    let html = `<option value="">Select item</option>`;
    for (const it of PHARMACY_ITEMS) {
      const label = `${it.item_name} • ${it.company_name ?? "-"} • $${it.unit_price}`;
      const sel = (String(selectedId) === String(it.id)) ? "selected" : "";
      html += `<option ${sel} value="${it.id}">${escapeHtml(label)}</option>`;
    }
    return html;
  }
function addItemRow(itemId="", qty="1", itemLabel="") {
  const row = document.createElement("div");
  row.className = "grid gap-3 md:grid-cols-12 items-start";

  row.innerHTML = `
    <div class="md:col-span-9">
      <div class="relative">
        <input type="hidden" name="item_id[]" value="${escapeHtml(itemId)}" class="itemIdField" />

        <input type="text"
          class="itemSearch w-full rounded-2xl border bg-white px-4 py-3 text-sm font-semibold outline-none"
          placeholder="Search medicine / item name..."
          autocomplete="off"
          value="${escapeHtml(itemLabel)}"
        />

        <div class="itemDropdown absolute left-0 right-0 top-[calc(100%+6px)] z-50 hidden overflow-hidden rounded-2xl border bg-white shadow-xl">
          <div class="itemList max-h-60 overflow-y-auto divide-y"></div>
        </div>
      </div>
    </div>

    <div class="md:col-span-2">
      <input name="qty[]" type="number" min="1" value="${escapeHtml(qty)}"
        class="w-full rounded-2xl border bg-white px-4 py-3 text-sm font-semibold outline-none"
        placeholder="Qty" />
    </div>

    <div class="md:col-span-1 flex justify-end">
      <button type="button"
        class="grid h-11 w-11 place-items-center rounded-2xl border bg-white hover:bg-slate-50"
        title="Remove">🗑️</button>
    </div>
  `;

  // remove row
  row.querySelector('button[title="Remove"]').addEventListener("click", () => {
    row.remove();
    if (itemsWrap.children.length === 0) addItemRow();
  });

  // hook search behavior
  wireItemSearch(row);

  itemsWrap.appendChild(row);
}
function wireItemSearch(row) {
  const input = row.querySelector(".itemSearch");
  const hidden = row.querySelector(".itemIdField");
  const dropdown = row.querySelector(".itemDropdown");
  const list = row.querySelector(".itemList");

  function close() {
    dropdown.classList.add("hidden");
    list.innerHTML = "";
  }

  function open() {
    dropdown.classList.remove("hidden");
  }

  function render(term) {
    const t = (term || "").trim().toLowerCase();
    list.innerHTML = "";

    // show top 30 matches
    let matches = PHARMACY_ITEMS.filter(it => {
      const name = (it.item_name || "").toLowerCase();
      const comp = (it.company_name || "").toLowerCase();
      return !t || name.includes(t) || comp.includes(t);
    }).slice(0, 30);

    if (matches.length === 0) {
      list.innerHTML = `<div class="px-4 py-3 text-sm font-semibold text-slate-500">No items found</div>`;
      open();
      return;
    }

    for (const it of matches) {
      const label = `${it.item_name} • ${it.company_name ?? "-"} • $${it.unit_price}`;
      const btn = document.createElement("button");
      btn.type = "button";
      btn.className = "w-full text-left px-4 py-3 hover:bg-slate-50";
      btn.innerHTML = `
        <div class="text-sm font-extrabold text-slate-900">${escapeHtml(it.item_name)}</div>
        <div class="text-xs font-semibold text-slate-500">${escapeHtml(label)}</div>
      `;
      btn.addEventListener("click", () => {
        hidden.value = it.id;
        input.value = it.item_name; // show name in input
        close();
      });
      list.appendChild(btn);
    }

    open();
  }

  input.addEventListener("focus", () => render(input.value));
  input.addEventListener("input", () => render(input.value));

  // keyboard: ESC closes
  input.addEventListener("keydown", (e) => {
    if (e.key === "Escape") close();
  });

  // click outside closes
  document.addEventListener("click", (e) => {
    if (!row.contains(e.target)) close();
  });
}


  function resetForm(){
    f.id.value="";
    f.patient_id.value="";
    f.emp_id.value="";
    f.notes.value="";
    patientSearch.value="";
    patientSelectedLabel.textContent="None";
    patientResults.classList.add("hidden");
    patientResultsList.innerHTML="";
    clearPatientError();
    itemsWrap.innerHTML="";
    addItemRow();
  }

  addItemRowBtn?.addEventListener("click", () => addItemRow());

  // patient search (debounced)
  let searchTimer = null;
  
  async function searchPatients(term){
    const BASE = "/hospital/prescriptions/view.php";
    const url = `${BASE}?ajax=search_patients&term=${encodeURIComponent(term)}`;

  const res = await fetch(url, { headers: { "Accept": "application/json" } });

  // if server returns HTML or error page, show it
  const text = await res.text();

  try {
    return JSON.parse(text);
  } catch (e) {
    console.error("AJAX raw response:", text);
    throw new Error("Bad JSON response");
  }
}


  function renderPatientResults(rows){
    patientResultsList.innerHTML = "";

    if (!rows || rows.length === 0) {
      patientResults.classList.add("hidden");
      return;
    }

    for (const r of rows) {
      const label = `${r.full_name} • ${r.phone ?? "-"} • #${r.id}`;

      const btn = document.createElement("button");
      btn.type = "button";
      btn.className = "w-full text-left px-4 py-3 hover:bg-slate-50";
      btn.innerHTML = `
        <div class="text-sm font-extrabold text-slate-900">${escapeHtml(r.full_name)} <span class="text-xs font-semibold text-slate-500">• #${r.id}</span></div>
        <div class="text-xs font-semibold text-slate-500">${escapeHtml(label)}</div>
      `;

      btn.addEventListener("click", () => {
        f.patient_id.value = r.id;
        patientSelectedLabel.textContent = `${r.full_name} (${r.phone ?? "-"})`;
        patientResults.classList.add("hidden");
        patientResultsList.innerHTML = "";
        clearPatientError();
      });

      patientResultsList.appendChild(btn);
    }

    patientResults.classList.remove("hidden");
  }

  patientSearch?.addEventListener("input", () => {
    const term = patientSearch.value.trim();
    clearTimeout(searchTimer);

    if (term.length < 2) {
      patientResults.classList.add("hidden");
      patientResultsList.innerHTML = "";
      return;
    }

    searchTimer = setTimeout(async () => {
      try {
        const data = await searchPatients(term);
        if (!data.ok) { showPatientError("Search failed."); return; }
        renderPatientResults(data.rows);
      } catch (e) {
        showPatientError("Network error while searching.");
      }
    }, 250);
  });

  // modal open/close
  openAdd?.addEventListener("click", () => {
    title.textContent="Add Prescription";
    resetForm();
    openModal();
    setTimeout(() => patientSearch.focus(), 50);
  });

  prescOverlay?.addEventListener("click", closeModal);
  closeBtn?.addEventListener("click", closeModal);
  cancelBtn?.addEventListener("click", closeModal);
  document.addEventListener("keydown", (e) => {
    if (e.key==="Escape" && !prescModal.classList.contains("hidden")) closeModal();
  });

  // edit
  async function fetchPrescriptionDetails(id){
    const BASE = "/hospital/prescriptions/view.php";

  const url = `${BASE}?ajax=prescription_details&id=${encodeURIComponent(id)}`;
  const res = await fetch(url, { headers: { "Accept": "application/json" } });
  const text = await res.text();
  try { return JSON.parse(text); }
  catch(e){ console.error("DETAILS raw:", text); throw new Error("Bad JSON"); }
}


  window.openEditById = async function(btn){
    const id = btn.getAttribute("data-id");
    if (!id) return;

    title.textContent="Edit Prescription";
    resetForm();
    f.id.value = id;

    try {
      const data = await fetchPrescriptionDetails(id);
      if (!data.ok) { showPatientError("Cannot load prescription details."); openModal(); return; }

      f.patient_id.value = data.prescription.patient_id || "";
      f.emp_id.value = data.prescription.prescribed_by_employee_id || "";
      f.notes.value = data.prescription.notes || "";

      if (data.patient) {
        patientSelectedLabel.textContent = `${data.patient.full_name} (${data.patient.phone ?? "-"})`;
      } else {
        patientSelectedLabel.textContent = `Patient #${data.prescription.patient_id}`;
      }

      itemsWrap.innerHTML = "";
   if (data.items && data.items.length) {
  for (const it of data.items) addItemRow(it.item_id, it.qty, it.item_name || "");
} else {
  addItemRow();
}


      openModal();
      setTimeout(() => patientSearch.focus(), 50);
    } catch (e) {
      showPatientError("Network error while loading.");
      openModal();
    }
  };

  // validate before submit
  document.getElementById("prescForm")?.addEventListener("submit", (e) => {
    if (!f.patient_id.value) {
      e.preventDefault();
      showPatientError("Please select a patient from the search results.");
      return;
    }
    clearPatientError();
  });
</script>
