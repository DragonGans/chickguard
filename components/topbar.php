<?php
$username =$_SESSION['username'] ?? 'admin';
if (isset($koneksi) &&$koneksi instanceof mysqli) {
    $uStmt = mysqli_prepare($koneksi, "SELECT username, role FROM users WHERE username = ? LIMIT 1");
    if ($uStmt) {
        mysqli_stmt_bind_param($uStmt, "s", $username);
        mysqli_stmt_execute($uStmt);
        $uRes = mysqli_stmt_get_result($uStmt);
        if ($uRow = mysqli_fetch_assoc($uRes)) {
            if (!empty($uRow['username'])) {
                $username =$uRow['username'];
                $_SESSION['username'] =$username;
            }
            if (!empty($uRow['role'])) {
                $_SESSION['role'] =$uRow['role'];
            }
        }
    }
}
$userRole = ucfirst($_SESSION['role'] ?? 'Admin');

// Query notifikasi jika belum disiapkan di halaman pemanggil (muat hingga 100 riwayat terbaru)
if (!isset($notifs) || !is_array($notifs)) {
    $notifs = [];
    if (isset($koneksi) &&$koneksi instanceof mysqli) {
        $notifQuery = mysqli_query($koneksi, "SELECT * FROM notifikasi ORDER BY id DESC LIMIT 100");
        if ($notifQuery) {
            while ($row = mysqli_fetch_assoc($notifQuery)) {
                $notifs[] = $row;
            }
        }
    }
    if (empty($notifs)) {
        $notifs = [
            ['id' => 1, 'pesan' => 'Pakan diberikan otomatis', 'tipe' => 'success', 'waktu_label' => '16:00 - 10 Okt 2026', 'is_read' => 0],
            ['id' => 2, 'pesan' => 'Level air mulai rendah (72%)', 'tipe' => 'warning', 'waktu_label' => '14:32 - 10 Okt 2026', 'is_read' => 0],
            ['id' => 3, 'pesan' => 'Suhu kembali normal', 'tipe' => 'success', 'waktu_label' => '12:15 - 10 Okt 2026', 'is_read' => 1],
            ['id' => 4, 'pesan' => 'ESP32 terhubung', 'tipe' => 'success', 'waktu_label' => '08:07 - 10 Okt 2026', 'is_read' => 1],
        ];
    }
}

if (!isset($unreadCount)) {
    if (isset($koneksi) &&$koneksi instanceof mysqli) {
        $unreadQuery = mysqli_query($koneksi, "SELECT COUNT(*) AS unread FROM notifikasi WHERE is_read = 0");
        $unreadRow = $unreadQuery ? mysqli_fetch_assoc($unreadQuery) : null;
        $unreadCount = (int) ($unreadRow['unread'] ?? 0); } else {$unreadCount = 0;
        foreach ($notifs as $n) {
            if (empty($n['is_read']))$unreadCount++;
        }
    }
}
$unreadCount = (int)$unreadCount;

$topbarTitle =$topbarTitle ?? ('Selamat Datang, ' . $username . '!');
$topbarSubtitle =$topbarSubtitle ?? 'Pantau kondisi kandang ayam Anda secara real-time melalui dashboard IoT.';
?>
<style>
  /* Styling tambahan untuk Dropdown Notifikasi Scrollable */
  #notifDropdownList::-webkit-scrollbar {
    width: 6px;
  }
  #notifDropdownList::-webkit-scrollbar-track {
    background: #f8fafc;
  }
  #notifDropdownList::-webkit-scrollbar-thumb {
    background: #cbd5e1;
    border-radius: 10px;
  }
  #notifDropdownList::-webkit-scrollbar-thumb:hover {
    background: #94a3b8;
  }
  .notif-dropdown-item {
    transition: background-color 0.15s ease;
  }
  .notif-dropdown-item:hover {
    background-color: #f1f5f9 !important;
  }
</style>

<header class="topbar-modern topbar">
  <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3">
    <!-- Left greeting -->
    <div class="topbar-greeting">
      <h1 class="topbar-title"><?= htmlspecialchars($topbarTitle, ENT_QUOTES, 'UTF-8') ?></h1>
      <p class="topbar-subtitle"><?= htmlspecialchars($topbarSubtitle, ENT_QUOTES, 'UTF-8') ?></p>
    </div>

    <!-- Right widgets in single row -->
    <div class="topbar-right-cluster d-flex align-items-center gap-2 gap-md-3 ms-lg-auto">
      <!-- Date & Clock Card -->
      <div class="topbar-widget-card d-flex align-items-center gap-2">
        <div class="widget-icon text-muted">
          <i class="bi bi-calendar3"></i>
        </div>
        <div class="widget-meta">
          <div class="widget-primary" id="topbarDate">10 Oktober 2026</div>
          <div class="widget-secondary" id="topbarClock">15:42:17</div>
        </div>
      </div>

      <!-- Weather Card -->
      <div class="topbar-widget-card d-flex align-items-center gap-2">
        <div class="widget-icon weather-cloud" id="weatherIcon">
          <i class="bi bi-cloud"></i>
        </div>
        <div class="widget-meta">
          <div class="widget-primary" id="weatherLabel">Berawan</div>
          <div class="widget-secondary" id="weatherTemp">28&deg;C</div>
        </div>
      </div>

      <!-- Notification Bell Dropdown (Bootstrap 5.3) -->
      <div class="dropdown notif-dropdown-wrap position-relative">
        <button type="button"
                class="topbar-action-btn position-relative"
                id="topbarNotifBellBtn"
                data-bs-toggle="dropdown"
                data-bs-auto-close="outside"
                aria-expanded="false"
                title="Notifikasi"
                aria-label="Notifikasi">
          <i class="bi bi-bell"></i>
          <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger border border-2 border-white"
                id="topbarNotifDot"
                style="font-size: 0.65rem; min-width: 1.25rem; padding: 0.22em 0.45em; <?= $unreadCount > 0 ? '' : 'display: none;' ?>">
            <?= $unreadCount > 99 ? '99+' :$unreadCount ?>
          </span>
        </button>

        <!-- Kotak Popover / Dropdown Notifikasi -->
        <div class="dropdown-menu dropdown-menu-end border-0 shadow-lg p-0 mt-2"
             id="notifDropdownMenu"
             aria-labelledby="topbarNotifBellBtn"
             style="width: 360px; max-width: calc(100vw - 24px); border-radius: 18px; overflow: hidden; z-index: 1060;">
          
          <!-- Header Dropdown -->
          <div class="px-3 py-3 bg-white border-bottom d-flex align-items-center justify-content-between">
            <div class="d-flex align-items-center gap-2">
              <i class="bi bi-bell-fill text-warning"></i>
              <span class="fw-bold text-dark" style="font-size: 0.95rem;">Pusat Notifikasi</span>
            </div>
            <span class="badge bg-success-subtle text-success rounded-pill px-2 py-1 fw-semibold" id="notifUnreadBadge" style="font-size: 0.72rem;">
              <?= $unreadCount ?> Baru
            </span>
          </div>

          <!-- Scrollable List Container -->
          <div class="list-group list-group-flush" id="notifDropdownList" style="max-height: 340px; overflow-y: auto;">
            <?php if (empty($notifs)): ?>
              <div class="text-center py-4 text-muted small" id="notifEmptyState">
                <i class="bi bi-bell-slash fs-2 d-block mb-1 text-secondary opacity-50"></i>
                Belum ada notifikasi
              </div>
            <?php else: ?>
              <?php foreach ($notifs as $nt): ?>
                <?php
                  $isRead = !empty($nt['is_read']);$tipe = $nt['tipe'] ?? 'success';$dotColor = $tipe === 'warning' ? 'bg-warning' : ($tipe === 'danger' ? 'bg-danger' : 'bg-success');
                ?>
                <div class="list-group-item list-group-item-action border-light px-3 py-2 d-flex gap-2 align-items-start notif-dropdown-item <?= $isRead ? '' : 'bg-success-subtle bg-opacity-10' ?>"
                     data-id="<?= (int) ($nt['id'] ?? 0) ?>"
                     style="cursor: pointer;">
                  <span class="notif-dot <?= $dotColor ?> mt-1 flex-shrink-0 <?= $isRead ? 'bg-opacity-50' : '' ?>"
                        style="width: 9px; height: 9px; border-radius: 50%; display: inline-block;"></span>
                  <div class="flex-grow-1" style="min-width: 0;">
                    <div class="notif-item-msg <?= $isRead ? 'fw-medium text-secondary' : 'fw-semibold text-dark' ?>" style="font-size: 0.85rem; line-height: 1.35;">
                      <?= htmlspecialchars($nt['pesan'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                    </div>
                    <div class="text-muted mt-1" style="font-size: 0.72rem;">
                      <?= htmlspecialchars($nt['waktu_label'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                    </div>
                  </div>
                  <?php if (!$isRead): ?>
                    <span class="badge bg-success-subtle text-success rounded-pill flex-shrink-0 notif-new-pill" style="font-size: 0.65rem;">Baru</span>
                  <?php endif; ?>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>

          <!-- Footer Dropdown -->
          <div class="px-3 py-2 bg-light border-top">
            <button type="button" class="btn btn-sm btn-success rounded-pill px-3 fw-semibold w-100" id="btnMarkAllRead">
              <i class="bi bi-check2-all me-1"></i> Tandai Semua Dibaca
            </button>
          </div>
        </div>
      </div>

      <!-- User Profile -->
      <div class="user-profile-badge d-flex align-items-center gap-2">
        <div class="user-avatar-circle">
          <i class="bi bi-person-fill"></i>
        </div>
        <div class="user-details">
          <div class="user-name"><?= htmlspecialchars($username, ENT_QUOTES, 'UTF-8') ?></div>
        </div>
      </div>
    </div>
  </div>
</header>

<!-- Bootstrap 5 Toast Container untuk Notifikasi Baru (Auto-hide 4 detik) -->
<div class="toast-container position-fixed top-0 end-0 p-3" id="notifToastContainer" style="z-index: 1090;"></div>

<script>
  (() => {
    function updateClock() {
      const now = new Date();
      const months = [
        "Januari", "Februari", "Maret", "April", "Mei", "Juni",
        "Juli", "Agustus", "September", "Oktober", "November", "Desember"
      ];
      
      const day = now.getDate();
      const month = months[now.getMonth()];
      const year = now.getFullYear();
      
      const hours = String(now.getHours()).padStart(2, '0');
      const minutes = String(now.getMinutes()).padStart(2, '0');
      const seconds = String(now.getSeconds()).padStart(2, '0');

      const dateEl = document.getElementById("topbarDate");
      const clockEl = document.getElementById("topbarClock");
      const cctvClockEl = document.getElementById("cctvLiveTime");

      if (dateEl) dateEl.textContent = `${day} ${month} ${year}`;
      if (clockEl) clockEl.textContent = `${hours}:${minutes}:${seconds}`;
      if (cctvClockEl) cctvClockEl.textContent = `${hours}:${minutes}:${seconds}`;
    }

    updateClock();
    setInterval(updateClock, 1000);

    // Helper global untuk membuka dropdown lonceng + scroll ke atas
    window.openTopbarNotificationDropdown = function () {
      const bellBtn = document.getElementById("topbarNotifBellBtn");
      if (!bellBtn) return;

      if (window.scrollY > 80) {
        window.scrollTo({ top: 0, behavior: "smooth" });
      }

      if (window.bootstrap && window.bootstrap.Dropdown) {
        const dropdownInstance = bootstrap.Dropdown.getOrCreateInstance(bellBtn);
        dropdownInstance.show();
      }
    };

    function initTopbarNotifications() {
      // Hubungkan tombol "Lihat Semua" (.js-toggle-notifications) untuk membuka dropdown lonceng
      document.querySelectorAll(".js-toggle-notifications").forEach(btn => {
        if (btn.dataset.topbarBound) return;
        btn.dataset.topbarBound = "1";
        btn.addEventListener("click", (e) => {
          e.preventDefault();
          e.stopPropagation(); // Mencegah event bubbling menutup dropdown Bootstrap
          window.openTopbarNotificationDropdown();
        });
      });

      // Tombol Tandai Semua Dibaca
      const btnMarkAllRead = document.getElementById("btnMarkAllRead");
      if (btnMarkAllRead && !btnMarkAllRead.dataset.topbarBound) {
        btnMarkAllRead.dataset.topbarBound = "1";
        btnMarkAllRead.addEventListener("click", async (e) => {
          e.stopPropagation();
          try {
            const res = await fetch("../proses/notifikasi_tandai.php", {
              method: "POST",
              headers: { "Content-Type": "application/json" },
              body: JSON.stringify({ semua: true })
            });
            const data = await res.json();
            if (data && data.success) {
              const badge = document.getElementById("topbarNotifDot");
              const headerBadge = document.getElementById("notifUnreadBadge");
              if (badge) {
                badge.textContent = "0";
                badge.style.display = "none";
              }
              if (headerBadge) {
                headerBadge.textContent = "0 Baru";
              }

              document.querySelectorAll("#notifDropdownList .notif-new-pill").forEach(b => b.remove());
              document.querySelectorAll("#notifDropdownList .notif-dropdown-item").forEach(item => {
                item.classList.remove("bg-success-subtle", "bg-opacity-10");
              });
              document.querySelectorAll("#notifDropdownList .notif-item-msg").forEach(msg => {
                msg.classList.remove("fw-semibold", "text-dark");
                msg.classList.add("fw-medium", "text-secondary");
              });
              document.querySelectorAll("#notifDropdownList .notif-dot, #notifListContainer .notif-dot").forEach(d => {
                d.classList.add("bg-opacity-50");
              });
              document.querySelectorAll("#notifListContainer .notif-title").forEach(t => {
                t.style.fontWeight = "500";
                t.style.color = "#64748b";
              });

              if (typeof window.onAllNotifsMarkedRead === "function") {
                window.onAllNotifsMarkedRead();
              }
              if (typeof showToast === "function") {
                showToast(data.message || "Semua notifikasi ditandai dibaca.", "success");
              }
            }
          } catch (err) {
            console.error("Gagal menandai notifikasi:", err);
          }
        });
      }
    }

    if (document.readyState === "loading") {
      document.addEventListener("DOMContentLoaded", initTopbarNotifications);
    } else {
      initTopbarNotifications();
    }
  })();
</script>