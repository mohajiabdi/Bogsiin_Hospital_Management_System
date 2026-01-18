<?php
// /hospital/includes/footer.php
// Professional footer (orange theme) + 6+ social icons + same links as nav

$year = date("Y");
?>
<footer class="mt-16 bg-orange-600 text-white">
  <!-- top -->
  <div class="mx-auto max-w-6xl px-4 py-10">
    <div class="grid gap-8 md:grid-cols-3">
      <!-- Brand -->
      <div>
        <div class="flex items-center gap-3">
          <div class="h-11 w-11 rounded-2xl bg-white/15 grid place-items-center overflow-hidden ring-1 ring-white/20">
            <img src="/hospital/assets/images/logo.png" alt="Bogsiin Hospital" class="h-9 w-9 object-contain" />
          </div>
          <div>
            <div class="text-lg font-extrabold leading-tight">Bogsiin Hospital</div>
            <div class="text-xs font-semibold text-white/80 -mt-0.5">where Healing begins</div>
          </div>
        </div>

        <p class="mt-4 max-w-sm text-sm font-semibold text-white/90">
          University project demo system for managing hospital workflows:
          patients, appointments, billing, prescriptions, and reports.
        </p>

        <div class="mt-5 inline-flex items-center gap-2 rounded-2xl bg-white/10 px-4 py-3 text-sm font-extrabold ring-1 ring-white/15">
          <span class="inline-block h-2 w-2 rounded-full bg-white"></span>
          Secure • Simple • Fast
        </div>
      </div>

      <!-- Links -->
      <div>
        <div class="text-xs font-extrabold tracking-widest text-white/80">LINKS</div>
        <div class="mt-4 grid gap-2 text-sm font-extrabold">
          <a class="w-fit rounded-xl px-2 py-1 hover:bg-white/10" href="/hospital/index.php#features">Projects</a>
           <a class="w-fit rounded-xl px-2 py-1 hover:bg-white/10" href="/hospital/index.php#about">About Us</a>
           <a class="w-fit rounded-xl px-2 py-1 hover:bg-white/10" href="/hospital/index.php#features">Features</a>
          <a class="w-fit rounded-xl px-2 py-1 hover:bg-white/10" href="/hospital/auth/login.php">Login</a>
        </div>
      </div>

      <!-- Social -->
      <div>
        <div class="text-xs font-extrabold tracking-widest text-white/80">CONNECT</div>
        <p class="mt-4 text-sm font-semibold text-white/90">
          Follow our project pages and get updates. (Demo links — replace with your real pages.)
        </p>

        <div class="mt-4 flex flex-wrap gap-2">
          <!-- Each icon is inline SVG so no extra libraries needed -->
          <a href="https://www.facebook.com" target="_blank" rel="noopener"
             class="group inline-flex h-11 w-11 items-center justify-center rounded-2xl bg-white/10 ring-1 ring-white/15 hover:bg-white/20"
             aria-label="Facebook">
            <svg class="h-5 w-5 fill-white" viewBox="0 0 24 24" aria-hidden="true">
              <path d="M22 12a10 10 0 1 0-11.6 9.9v-7H7.8v-2.9h2.6V9.8c0-2.6 1.5-4 3.8-4 1.1 0 2.2.2 2.2.2v2.4h-1.2c-1.2 0-1.6.7-1.6 1.5v1.8h2.7l-.4 2.9h-2.3v7A10 10 0 0 0 22 12z"/>
            </svg>
          </a>

          <a href="https://www.instagram.com" target="_blank" rel="noopener"
             class="group inline-flex h-11 w-11 items-center justify-center rounded-2xl bg-white/10 ring-1 ring-white/15 hover:bg-white/20"
             aria-label="Instagram">
            <svg class="h-5 w-5 fill-white" viewBox="0 0 24 24" aria-hidden="true">
              <path d="M7 2h10a5 5 0 0 1 5 5v10a5 5 0 0 1-5 5H7a5 5 0 0 1-5-5V7a5 5 0 0 1 5-5zm10 2H7a3 3 0 0 0-3 3v10a3 3 0 0 0 3 3h10a3 3 0 0 0 3-3V7a3 3 0 0 0-3-3zm-5 4.5A3.5 3.5 0 1 1 8.5 12 3.5 3.5 0 0 1 12 8.5zm0 2A1.5 1.5 0 1 0 13.5 12 1.5 1.5 0 0 0 12 10.5zM18 6.7a1 1 0 1 1-1 1 1 1 0 0 1 1-1z"/>
            </svg>
          </a>

          <a href="https://twitter.com" target="_blank" rel="noopener"
             class="group inline-flex h-11 w-11 items-center justify-center rounded-2xl bg-white/10 ring-1 ring-white/15 hover:bg-white/20"
             aria-label="X (Twitter)">
            <svg class="h-5 w-5 fill-white" viewBox="0 0 24 24" aria-hidden="true">
              <path d="M18.9 2H22l-6.8 7.8L23 22h-6.6l-5.2-6.7L5.4 22H2.3l7.3-8.4L1 2h6.8l4.7 6.1L18.9 2zm-1.2 18h1.8L7.2 3.9H5.3L17.7 20z"/>
            </svg>
          </a>

          <a href="https://www.linkedin.com" target="_blank" rel="noopener"
             class="group inline-flex h-11 w-11 items-center justify-center rounded-2xl bg-white/10 ring-1 ring-white/15 hover:bg-white/20"
             aria-label="LinkedIn">
            <svg class="h-5 w-5 fill-white" viewBox="0 0 24 24" aria-hidden="true">
              <path d="M4.98 3.5A2.5 2.5 0 1 1 5 8.5a2.5 2.5 0 0 1-.02-5zM3 9h4v12H3V9zm7 0h3.8v1.7h.1A4.2 4.2 0 0 1 17.7 9c4 0 4.8 2.6 4.8 6v6h-4v-5.3c0-1.3 0-3-1.8-3s-2.1 1.4-2.1 2.9V21h-4V9z"/>
            </svg>
          </a>

          <a href="https://www.youtube.com" target="_blank" rel="noopener"
             class="group inline-flex h-11 w-11 items-center justify-center rounded-2xl bg-white/10 ring-1 ring-white/15 hover:bg-white/20"
             aria-label="YouTube">
            <svg class="h-5 w-5 fill-white" viewBox="0 0 24 24" aria-hidden="true">
              <path d="M21.6 7.2a3 3 0 0 0-2.1-2.1C17.6 4.6 12 4.6 12 4.6s-5.6 0-7.5.5A3 3 0 0 0 2.4 7.2 31.7 31.7 0 0 0 2 12a31.7 31.7 0 0 0 .4 4.8 3 3 0 0 0 2.1 2.1c1.9.5 7.5.5 7.5.5s5.6 0 7.5-.5a3 3 0 0 0 2.1-2.1A31.7 31.7 0 0 0 22 12a31.7 31.7 0 0 0-.4-4.8zM10 15.5v-7l6 3.5-6 3.5z"/>
            </svg>
          </a>

          <a href="https://github.com" target="_blank" rel="noopener"
             class="group inline-flex h-11 w-11 items-center justify-center rounded-2xl bg-white/10 ring-1 ring-white/15 hover:bg-white/20"
             aria-label="GitHub">
            <svg class="h-5 w-5 fill-white" viewBox="0 0 24 24" aria-hidden="true">
              <path d="M12 2a10 10 0 0 0-3.2 19.5c.5.1.7-.2.7-.5v-1.8c-3 .7-3.6-1.3-3.6-1.3-.5-1.2-1.2-1.5-1.2-1.5-1-.7.1-.7.1-.7 1.1.1 1.7 1.1 1.7 1.1 1 .1 1.6-.7 1.9-1.2.1-.7.4-1.2.7-1.5-2.4-.3-4.9-1.2-4.9-5.3 0-1.2.4-2.1 1.1-2.9-.1-.3-.5-1.4.1-2.9 0 0 .9-.3 3 .1a10.4 10.4 0 0 1 5.5 0c2.1-.4 3-.1 3-.1.6 1.5.2 2.6.1 2.9.7.8 1.1 1.7 1.1 2.9 0 4.1-2.5 5-4.9 5.3.4.3.7 1 .7 2v3c0 .3.2.6.7.5A10 10 0 0 0 12 2z"/>
            </svg>
          </a>
        </div>
      </div>
    </div>
  </div>

  <!-- bottom bar -->
  <div class="border-t border-white/20">
    <div class="mx-auto flex max-w-6xl flex-col gap-2 px-4 py-4 text-xs font-semibold text-white/90 md:flex-row md:items-center md:justify-between">
      <div>© <?= $year; ?> Bogsiin Hospital • Hospital Management System</div>
      <div class="flex flex-wrap gap-3">
        <a class="hover:underline" href="/hospital/index.php">Home</a>
        <a class="hover:underline" href="/hospital/index.php#about">About</a>
        <a class="hover:underline" href="/hospital/index.php#features">Projects</a>
      </div>
    </div>
  </div>
</footer>
