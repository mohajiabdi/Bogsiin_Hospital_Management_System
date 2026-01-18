<?php
$pageTitle = "Hospital Management System";
include_once __DIR__ . "/includes/header.php";
include_once __DIR__ . "/includes/navbar.php";

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// Team (static - university project)
$team = [
  ["id"=>"C1221277", "name"=>"Mohamed Mahad Abdi"],
  ["id"=>"C1220186", "name"=>"Najma Mohamud Abdulle"],
  ["id"=>"C1220204", "name"=>"Mohamed Hassan Ahmed"],
  ["id"=>"C1221155", "name"=>"Duale Mohamed Ali"],
  ["id"=>"C1221298", "name"=>"Maryan Mohamed Abdullahi"],
];

$skills = ["JavaScript", "HTML", "CSS", "PHP", "Node.js", "SQL", "PostgreSQL", "Flutter"];
?>

<main class="mx-auto max-w-6xl px-4 py-12">
  <!-- Hero -->
  <div class="grid gap-10 md:grid-cols-2 md:items-center">
    <section>
      <div class="inline-flex items-center gap-2 rounded-full border bg-white px-3 py-1 text-xs font-bold text-slate-600">
        <span class="h-2 w-2 rounded-full bg-orange-500"></span>
        Secure • Simple • Fast
      </div>

      <h1 class="mt-4 text-4xl font-extrabold tracking-tight md:text-5xl">
        Hospital Management System
      </h1>

      <p class="mt-4 max-w-xl text-slate-600">
        A clean, modern university project system to manage core hospital workflows —
        patients, employees, appointments, billing, prescriptions, and reporting.
        Built with a simple structure, clear UI, and real-world logic.
      </p>

      <div class="mt-6 flex flex-wrap gap-3">
        <a href="/hospital/auth/login.php"
           class="rounded-2xl bg-orange-500 px-6 py-3 text-sm font-extrabold text-white hover:bg-orange-600">
          Login →
        </a>
        <a href="#about"
           class="rounded-2xl border bg-white px-6 py-3 text-sm font-extrabold hover:bg-slate-50">
          About Project
        </a>
        <a href="#team"
           class="rounded-2xl border bg-white px-6 py-3 text-sm font-extrabold hover:bg-slate-50">
          Our Team
        </a>
      </div>

      <!-- Highlights (no real stats) -->
      <div class="mt-8 grid grid-cols-2 gap-3">
        <div class="rounded-2xl border bg-white p-4">
          <div class="text-xs font-bold text-slate-500">Goal</div>
          <div class="mt-1 text-lg font-extrabold">Fast daily workflow</div>
          <div class="mt-1 text-xs font-semibold text-slate-500">Reduce manual tracking & errors</div>
        </div>
        <div class="rounded-2xl border bg-white p-4">
          <div class="text-xs font-bold text-slate-500">Focus</div>
          <div class="mt-1 text-lg font-extrabold">Clean & simple UI</div>
          <div class="mt-1 text-xs font-semibold text-slate-500">Easy for staff to use</div>
        </div>
      </div>

      <!-- Tech chips -->
      <div class="mt-6">
        <div class="text-xs font-extrabold tracking-widest text-slate-500">TECH SKILLS (TEAM)</div>
        <div class="mt-3 flex flex-wrap gap-2">
          <?php foreach ($skills as $s): ?>
            <span class="rounded-full border bg-white px-3 py-1 text-xs font-extrabold text-slate-700">
              <?= h($s); ?>
            </span>
          <?php endforeach; ?>
        </div>
      </div>
    </section>

    <!-- Right card -->
    <aside class="rounded-3xl border bg-white p-6 shadow-sm">
      <div class="flex items-center justify-between">
        <div class="font-extrabold">System Snapshot</div>
        <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-extrabold text-slate-700">Demo</span>
      </div>

      <p class="mt-3 text-sm font-semibold text-slate-600">
        This landing page shows a demo overview only — it does not display real hospital data.
      </p>

      <div class="mt-5 grid gap-3 sm:grid-cols-2">
        <div class="rounded-2xl border p-4 bg-slate-50">
          <div class="text-xs font-bold text-slate-500">Patients</div>
          <div class="mt-1 text-base font-extrabold text-slate-900">Records module</div>
          <div class="mt-1 text-xs font-semibold text-slate-500">Registration • Search • History</div>
        </div>
        <div class="rounded-2xl border p-4 bg-slate-50">
          <div class="text-xs font-bold text-slate-500">Appointments</div>
          <div class="mt-1 text-base font-extrabold text-slate-900">Scheduling module</div>
          <div class="mt-1 text-xs font-semibold text-slate-500">Pending • Confirmed • Completed</div>
        </div>
        <div class="rounded-2xl border p-4 bg-slate-50">
          <div class="text-xs font-bold text-slate-500">Billing</div>
          <div class="mt-1 text-base font-extrabold text-slate-900">Invoices module</div>
          <div class="mt-1 text-xs font-semibold text-slate-500">Payments • Receipts • Reports</div>
        </div>
        <div class="rounded-2xl border p-4 bg-slate-50">
          <div class="text-xs font-bold text-slate-500">Prescriptions</div>
          <div class="mt-1 text-base font-extrabold text-slate-900">Medication module</div>
          <div class="mt-1 text-xs font-semibold text-slate-500">Doctor notes • Items • Status</div>
        </div>
      </div>

      <div class="mt-5 rounded-2xl border p-4">
        <div class="text-xs font-extrabold tracking-widest text-slate-500">WHAT THIS SYSTEM SOLVES</div>
        <ul class="mt-3 grid gap-2 text-sm font-semibold text-slate-700 list-disc pl-5">
          <li>Centralize patient and staff information</li>
          <li>Prevent appointment conflicts and double booking</li>
          <li>Track billing, paid/unpaid status, and receipts</li>
          <li>Support reporting for admins (income, activity, and trends)</li>
        </ul>
      </div>
    </aside>
  </div>

  <!-- About -->
  <section id="about" class="mt-16">
    <div class="text-xs font-extrabold tracking-widest text-slate-500">ABOUT</div>
    <h2 class="mt-2 text-2xl font-extrabold">About this project</h2>
    <p class="mt-2 text-slate-600 max-w-3xl">
      This Hospital Management System is a university project built to demonstrate real hospital workflows in a simple,
      maintainable structure. It focuses on usability, clean interfaces, and practical business logic such as status tracking,
      searching, filtering, and admin reporting — without over-complicating the system.
    </p>

    <div class="mt-6 grid gap-4 md:grid-cols-3">
      <div class="rounded-3xl border bg-white p-6 shadow-sm">
        <div class="font-extrabold">Core Modules</div>
        <div class="mt-2 text-sm text-slate-600">
          Patients, Employees, Appointments, Billing, Prescriptions, and Admin Reports.
        </div>
      </div>

      <div class="rounded-3xl border bg-white p-6 shadow-sm">
        <div class="font-extrabold">Clean Workflow</div>
        <div class="mt-2 text-sm text-slate-600">
          Search, filters, status badges, and modals designed for fast daily operations.
        </div>
      </div>

      <div class="rounded-3xl border bg-white p-6 shadow-sm">
        <div class="font-extrabold">Secure Access</div>
        <div class="mt-2 text-sm text-slate-600">
          Auth guard protection, role-based pages (Admin vs Staff), and safe server-side validation.
        </div>
      </div>
    </div>
  </section>

  <!-- Features -->
  <section id="features" class="mt-16">
    <div class="text-xs font-extrabold tracking-widest text-slate-500">FEATURES</div>
    <h2 class="mt-2 text-2xl font-extrabold">Everything needed for daily operations</h2>
    <p class="mt-2 text-slate-600">Simple modules, clean UI, and real hospital workflow.</p>

    <div class="mt-6 grid gap-4 md:grid-cols-3">
      <?php
      $cards = [
        ["Patients Management", "Register, search, and manage patient records with clean listings and fast filters."],
        ["Appointments Scheduling", "Book visits, prevent conflicts, track statuses, and view doctor taken times."],
        ["Billing & Receipts", "Generate bills, apply discounts, record payments, and keep receipts for reporting."],
        ["Employees & Roles", "Manage staff and doctors with roles and access control (Admin-only where needed)."],
        ["Prescriptions", "Create prescriptions, attach items, and track unpaid/paid states cleanly."],
        ["Reports (Admin)", "Income summary, paid bills statements, appointments analytics, and export options."],
      ];
      foreach ($cards as $c) { ?>
        <div class="rounded-3xl border bg-white p-6 shadow-sm">
          <div class="font-extrabold"><?php echo h($c[0]); ?></div>
          <div class="mt-2 text-sm text-slate-600"><?php echo h($c[1]); ?></div>
        </div>
      <?php } ?>
    </div>
  </section>

  <!-- Team -->
  <section id="team" class="mt-16">
    <div class="text-xs font-extrabold tracking-widest text-slate-500">TEAM</div>
    <h2 class="mt-2 text-2xl font-extrabold">About our group</h2>

    <div class="mt-4 rounded-3xl border bg-white p-6 shadow-sm">
      <p class="text-slate-600 max-w-3xl">
        We are a team of programmers who collaborated to design and build this system.
        Our skills cover front-end and back-end development, database design, and mobile app development.
      </p>

      <div class="mt-6 overflow-x-auto rounded-2xl border">
        <table class="w-full text-left text-sm">
          <thead class="bg-slate-50 text-xs font-extrabold tracking-widest text-slate-500">
            <tr>
              <th class="px-4 py-3">STUDENT ID</th>
              <th class="px-4 py-3">FULL NAME</th>
              <th class="px-4 py-3">ROLE</th>
            </tr>
          </thead>
          <tbody class="divide-y">
            <?php foreach ($team as $i => $m): ?>
              <tr class="hover:bg-slate-50/60">
                <td class="px-4 py-3 font-extrabold text-slate-900"><?= h($m["id"]); ?></td>
                <td class="px-4 py-3 font-semibold text-slate-700"><?= h($m["name"]); ?></td>
                <td class="px-4 py-3 text-slate-600 font-semibold">
                  Programmer (Web & Systems)
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <div class="mt-6 grid gap-3 md:grid-cols-2">
        <div class="rounded-2xl border bg-slate-50 p-4">
          <div class="text-xs font-extrabold tracking-widest text-slate-500">WHAT WE USED</div>
          <ul class="mt-2 grid gap-1 text-sm font-semibold text-slate-700 list-disc pl-5">
            <li>JavaScript, HTML, CSS for UI</li>
            <li>PHP for server-side pages and validation</li>
            <li>Node.js knowledge for API and backend concepts</li>
            <li>SQL + PostgreSQL for databases</li>
            <li>Flutter knowledge for mobile development</li>
          </ul>
        </div>

        <div class="rounded-2xl border bg-slate-50 p-4">
          <div class="text-xs font-extrabold tracking-widest text-slate-500">OUR GOAL</div>
          <div class="mt-2 text-sm font-semibold text-slate-700">
            Deliver a professional, easy-to-use hospital workflow system that demonstrates real software engineering skills:
            clean design, structured code, strong validation, and practical reporting.
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- Contact -->
  <!-- <section id="contact" class="mt-16">
    <div class="rounded-3xl border bg-white p-6 shadow-sm">
      <div class="font-extrabold">Contact</div>
      <p class="mt-2 text-slate-600">
        University project demo system. You can add your contact details here (email / phone / supervisor name).
      </p>
    </div>
  </section> -->
</main>

<?php include __DIR__ . "/includes/footer.php"; ?>
