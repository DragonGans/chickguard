<?php
$username = $_SESSION['username'] ?? 'admin';
if (isset($koneksi) && $koneksi instanceof mysqli) {
    $uStmt = mysqli_prepare($koneksi, "SELECT username, role FROM users WHERE username = ? LIMIT 1");
    if ($uStmt) {
        mysqli_stmt_bind_param($uStmt, "s", $username);
        mysqli_stmt_execute($uStmt);
        $uRes = mysqli_stmt_get_result($uStmt);
        if ($uRow = mysqli_fetch_assoc($uRes)) {
            if (!empty($uRow['username'])) {
                $username = $uRow['username'];
                $_SESSION['username'] = $username;
            }
            if (!empty($uRow['role'])) {
                $_SESSION['role'] = $uRow['role'];
            }
        }
    }
}
$userRole = ucfirst($_SESSION['role'] ?? 'Admin');

// Query notifikasi jika belum disiapkan di halaman pemanggil
if (!isset($notifs) || !is_array($notifs)) {
    $notifs = [];
    if (isset($koneksi) && $koneksi instanceof mysqli) {
        $notifQuery = mysqli_query($koneksi, "SELECT * FROM notifikasi ORDER BY id DESC LIMIT 8");
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
    if (isset($koneksi) && $koneksi instanceof mysqli) {
        $unreadQuery = mysqli_query($koneksi, "SELECT COUNT(*) AS unread FROM notifikasi WHERE is_read = 0");
        $unreadRow = $unreadQuery ? mysqli_fetch_assoc($unreadQuery) : null;
        $unreadCount = (int) ($unreadRow['unread'] ?? 0);
    } else {
        $unreadCount = 0;
        foreach ($notifs as $n) {
            if (empty($n['is_read'])) $unreadCount++;
        }
    }
}
$unreadCount = (int) $unreadCount;

$topbarTitle = $topbarTitle ?? ('Selamat Datang, ' . $username . '!');
$topbarSubtitle = $topbarSubtitle ?? 'Pantau kondisi kandang ayam Anda secara real-time melalui dashboard IoT.';
?>
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

      <!-- Notification Bell -->
      <button type="button" class="topbar-action-btn js-toggle-notifications" title="Notifikasi" aria-label="Notifikasi">
        <i class="bi bi-bell"></i>
        <span class="notif-badge-dot" id="topbarNotifDot" <?= !empty($unreadCount) ? '' : 'hidden' ?>></span>
      </button>

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

<!-- Modal: Notifikasi Lengkap (Universal) -->
<div class="modal fade" id="modalNotifikasi" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow-lg" style="border-radius: 20px;">
      <div class="modal-header border-0 pb-0">
        <h5 class="modal-title fw-bold">
          <i class="bi bi-bell-fill text-warning me-2"></i>Pusat Notifikasi
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="list-group list-group-flush" id="modalNotifList">
          <?php foreach ($notifs as $nt): ?>
            <div class="list-group-item px-0 py-2 border-light d-flex gap-3 align-items-center">
              <span class="notif-dot <?= ($nt['tipe'] ?? '') === 'warning' ? 'bg-warning' : (($nt['tipe'] ?? '') === 'danger' ? 'bg-danger' : 'bg-success') ?> <?= empty($nt['is_read']) ? '' : 'bg-opacity-50' ?>"></span>
              <div class="flex-grow-1">
                <div class="fw-semibold text-dark fs-6"><?= htmlspecialchars($nt['pesan'] ?? '', ENT_QUOTES, 'UTF-8') ?></div>
                <div class="text-muted small"><?= htmlspecialchars($nt['waktu_label'] ?? '', ENT_QUOTES, 'UTF-8') ?></div>
              </div>
              <?= empty($nt['is_read']) ? '<span class="badge bg-success-subtle text-success rounded-pill">Baru</span>' : '' ?>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="modal-footer border-0 pt-0 d-flex gap-2">
        <button type="button" class="btn btn-success rounded-pill px-4" id="btnMarkAllRead">
          <i class="bi bi-check2-all me-1"></i> Tandai Semua Dibaca
        </button>
        <button type="button" class="btn btn-light rounded-pill px-4 flex-grow-1" data-bs-dismiss="modal">Tutup</button>
      </div>
    </div>
  </div>
</div>

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

    function initTopbarNotifications() {
      const modalNotifEl = document.getElementById("modalNotifikasi");
      function getModal() {
        if (modalNotifEl && window.bootstrap && window.bootstrap.Modal) {
          return bootstrap.Modal.getOrCreateInstance(modalNotifEl);
        }
        return null;
      }

      document.querySelectorAll(".js-toggle-notifications").forEach(btn => {
        if (btn.dataset.topbarBound) return;
        btn.dataset.topbarBound = "1";
        btn.addEventListener("click", (e) => {
          e.preventDefault();
          const modal = getModal();
          if (modal) modal.show();
        });
      });

      const btnMarkAllRead = document.getElementById("btnMarkAllRead");
      if (btnMarkAllRead && !btnMarkAllRead.dataset.topbarBound) {
        btnMarkAllRead.dataset.topbarBound = "1";
        btnMarkAllRead.addEventListener("click", async () => {
          try {
            const res = await fetch("../proses/notifikasi_tandai.php", {
              method: "POST",
              headers: { "Content-Type": "application/json" },
              body: JSON.stringify({ semua: true })
            });
            const data = await res.json();
            if (data && data.success) {
              const dot = document.getElementById("topbarNotifDot");
              if (dot) dot.hidden = true;
              document.querySelectorAll("#modalNotifList .badge").forEach(b => b.remove());
              document.querySelectorAll("#modalNotifList .notif-dot, #notifListContainer .notif-dot").forEach(d => d.classList.add("bg-opacity-50"));
              document.querySelectorAll("#notifListContainer .notif-title").forEach(t => {
                t.style.fontWeight = "500";
                t.style.color = "#64748b";
              });
              if (typeof setUnread === "function") {
                setUnread(data.unread_count || 0);
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