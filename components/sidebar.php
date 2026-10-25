<?php
$activePage = $activePage ?? '';

$menuItems = [
    [
        'key' => 'dashboard',
        'href' => 'dashboard.php',
        'icon' => '<i class="bi bi-house-door-fill"></i>',
        'label' => 'Dashboard',
    ],
    [
        'key' => 'aktuator',
        'href' => 'aktuator.php',
        'icon' => '<i class="fa-solid fa-sliders"></i>',
        'label' => 'Kontrol Aktuator',
    ],
    [
        'key' => 'pakan_minum',
        'href' => 'pakan_minum.php',
        'icon' => '<i class="fa-solid fa-calendar-check"></i>',
        'label' => 'Jadwal Pakan',
    ],
    [
        'key' => 'log_harian',
        'href' => 'log_harian.php',
        'icon' => '<i class="fa-solid fa-box-archive"></i>',
        'label' => 'Riwayat Data',
    ],
    [
        'key' => 'pengaturan',
        'href' => 'pengaturan.php',
        'icon' => '<i class="fa-solid fa-gear"></i>',
        'label' => 'Pengaturan',
    ],
];
?>
<aside class="sidebar">
  <div class="sidebar-panel">
    <div class="brand-wrap">
      <a href="dashboard.php" class="brand-link-modern d-flex align-items-center gap-2 text-decoration-none">
        <img src="../assets/images/branding/icon.png" alt="chickgurd" class="brand-icon-main">
        <div class="brand-meta-wrap">
          <div class="brand-title-main">chickgurd</div>
          <div class="brand-sub-main">Smart Chicken Coop IoT</div>
        </div>
      </a>
    </div>

    <div class="sidebar-nav-wrap">
      <ul class="nav nav-pills flex-column gap-2 sidebar-menu">
        <?php foreach ($menuItems as $item): ?>
          <li class="nav-item">
            <a class="nav-link <?= $activePage === $item['key'] ? 'active' : '' ?>" href="<?= htmlspecialchars($item['href'], ENT_QUOTES, 'UTF-8') ?>">
              <?= $item['icon'] ?>
              <span><?= htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') ?></span>
            </a>
          </li>
        <?php endforeach; ?>
      </ul>

      <div class="sidebar-bottom-section mt-auto">
        <div class="sidebar-logout">
          <a class="nav-link logout-link js-open-logout-modal" href="../proses/logout.php">
            <i class="bi bi-power"></i>
            <span>Logout</span>
          </a>
        </div>
      </div>
    </div>
  </div>
</aside>

<div class="logout-modal-backdrop hidden" id="logoutConfirmModal" aria-hidden="true">
  <div class="logout-modal-card" role="dialog" aria-modal="true" aria-labelledby="logoutConfirmTitle">
    <div class="logout-modal-icon">
      <i class="bi bi-box-arrow-right"></i>
    </div>
    <h2 id="logoutConfirmTitle">Konfirmasi Logout</h2>
    <p>Apakah Anda yakin ingin keluar dari sistem? Anda perlu login kembali untuk mengakses dashboard.</p>
    <div class="logout-modal-actions">
      <button type="button" class="logout-modal-btn secondary" id="cancelLogoutBtn">Batal</button>
      <a href="../proses/logout.php" class="logout-modal-btn primary">Ya, Logout</a>
    </div>
  </div>
</div>

<script>
  (() => {
    const logoutModal = document.getElementById("logoutConfirmModal");
    const openLogoutModalBtn = document.querySelector(".js-open-logout-modal");
    const cancelLogoutBtn = document.getElementById("cancelLogoutBtn");

    if (!logoutModal || !openLogoutModalBtn || !cancelLogoutBtn) {
      return;
    }

    function openLogoutModal(event) {
      event.preventDefault();
      logoutModal.classList.remove("hidden");
      logoutModal.setAttribute("aria-hidden", "false");
      document.body.classList.add("modal-open");
    }

    function closeLogoutModal() {
      logoutModal.classList.add("hidden");
      logoutModal.setAttribute("aria-hidden", "true");
      document.body.classList.remove("modal-open");
    }

    openLogoutModalBtn.addEventListener("click", openLogoutModal);
    cancelLogoutBtn.addEventListener("click", closeLogoutModal);

    logoutModal.addEventListener("click", (event) => {
      if (event.target === logoutModal) {
        closeLogoutModal();
      }
    });

    document.addEventListener("keydown", (event) => {
      if (event.key === "Escape" && !logoutModal.classList.contains("hidden")) {
        closeLogoutModal();
      }
    });
  })();
</script>