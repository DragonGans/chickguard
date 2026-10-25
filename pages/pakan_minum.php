<?php
session_start();

if (!isset($_SESSION['login'])) {
    header("Location: ../login.php");
    exit;
}

include '../config/koneksi.php';
include '../proses/init_tables.php';
chickguard_init_tables($koneksi);

$limit = 6;
$page = max(1, (int) ($_GET['page'] ?? 1));
$countResult = mysqli_query($koneksi, "SELECT COUNT(*) AS total FROM jadwal_pakan_minum WHERE jenis = 'Pakan'");
$totalData = (int) (mysqli_fetch_assoc($countResult)['total'] ?? 0);
$totalPages = max(1, (int) ceil($totalData / $limit));
if ($page > $totalPages) {
    $page = $totalPages;
}
$offset = ($page - 1) * $limit;

$stmt = mysqli_prepare($koneksi, "SELECT * FROM jadwal_pakan_minum WHERE jenis = 'Pakan' ORDER BY waktu ASC, id ASC LIMIT ? OFFSET ?");
mysqli_stmt_bind_param($stmt, "ii", $limit, $offset);
mysqli_stmt_execute($stmt);
$query = mysqli_stmt_get_result($stmt);
$jadwal = [];
while ($row = mysqli_fetch_assoc($query)) {
    $jadwal[] = $row;
}
$startData = $totalData > 0 ? $offset + 1 : 0;
$endData = $totalData > 0 ? $offset + count($jadwal) : 0;

// Riwayat log pemberian pakan terbaru (10 data terakhir)
$feedLogsQuery = mysqli_query($koneksi, "SELECT * FROM log_pemberian_pakan ORDER BY waktu DESC, id DESC LIMIT 10");
$feedLogs = [];
if ($feedLogsQuery) {
    while ($fl = mysqli_fetch_assoc($feedLogsQuery)) {
        $feedLogs[] = $fl;
    }
}

function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function checkbox_id($prefix, $value): string
{
    return preg_replace('/[^a-zA-Z0-9_-]/', '-', $prefix . '-' . $value);
}

$statusMessages = [
    'created' => [
        'title' => 'Jadwal Ditambahkan',
        'message' => 'Jadwal pemberian pakan baru berhasil disimpan.',
        'tone' => 'success',
        'icon' => 'bi-check2-circle',
    ],
    'updated' => [
        'title' => 'Jadwal Diperbarui',
        'message' => 'Perubahan jadwal pakan berhasil diterapkan.',
        'tone' => 'success',
        'icon' => 'bi-pencil-square',
    ],
    'deleted' => [
        'title' => 'Jadwal Dihapus',
        'message' => 'Jadwal pakan yang dipilih sudah dihapus dari daftar.',
        'tone' => 'success',
        'icon' => 'bi-trash3',
    ],
    'invalid' => [
        'title' => 'Form Belum Lengkap',
        'message' => 'Periksa kembali isian jam dan hari sebelum disimpan.',
        'tone' => 'warning',
        'icon' => 'bi-exclamation-circle',
    ],
    'none_selected' => [
        'title' => 'Belum Ada yang Dipilih',
        'message' => 'Pilih minimal satu jadwal pakan terlebih dahulu sebelum menjalankan hapus massal.',
        'tone' => 'warning',
        'icon' => 'bi-exclamation-circle',
    ],
];

$activePage = 'pakan_minum';
$topbarTitle = 'Jadwal Pakan';
$topbarSubtitle = 'Kelola jadwal pemberian pakan otomatis kandang dan tinjau log riwayat pakan.';
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>ChickGuard - Jadwal Pakan & Log Pemberian</title>
  <link rel="icon" href="../assets/images/branding/icon.png" type="image/png">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link rel="stylesheet" href="../assets/css/style.css">
  <link rel="stylesheet" href="../assets/css/sidebar.css">
  <link rel="stylesheet" href="../assets/css/topbar.css">
  <link rel="stylesheet" href="../assets/css/pakan_minum.css">
</head>
<body class="dashboard-page page-pakan-minum">
  <div class="dashboard-shell d-lg-flex">
    <?php include '../components/sidebar.php'; ?>
    <main class="main-content">
      <?php include '../components/topbar.php'; ?>

      <section class="content-section">
        <div class="container-fluid px-0">
          <!-- Schedule Table Card -->
          <div class="card-soft log-card schedule-card bg-white rounded-4 p-4" style="box-shadow: 0 4px 20px rgba(77, 109, 120, 0.16); border: 1px solid rgba(255,255,255,0.85);">
            <div class="log-toolbar d-flex justify-content-between align-items-center flex-wrap gap-3 mb-3">
              <div>
                <h2 class="log-title fw-bold text-dark fs-5 mb-1">Jadwal Pemberian Pakan</h2>
                <p class="log-subtitle text-muted small mb-0">Kelola daftar waktu dan hari operasional pakan otomatis kandang</p>
              </div>
              <div class="d-flex align-items-center gap-2 flex-wrap">
                <button type="button" class="btn btn-outline-success fw-semibold px-4 py-2 rounded-pill shadow-sm d-inline-flex align-items-center gap-2" id="feedActionBtn">
                  <i class="fa-solid fa-play"></i>
                  <span>Beri Pakan Sekarang</span>
                </button>
                <button type="button" class="btn btn-success fw-semibold px-4 py-2 rounded-pill js-open-schedule-modal d-inline-flex align-items-center gap-2 shadow-sm">
                  <i class="bi bi-plus-circle-fill"></i>
                  <span>Tambah Jadwal Pakan</span>
                </button>
              </div>
            </div>

            <form method="post" action="../proses/proses_pakan_minum.php" id="bulkDeleteForm">
              <input type="hidden" name="action" value="delete_selected">

              <div class="table-wrap">
                <div class="table-responsive">
                  <table class="table data-table schedule-table align-middle">
                    <thead>
                      <tr>
                        <th class="check-col">
                          <label class="schedule-day log-check m-0">
                            <input type="checkbox" id="selectAllSchedules">
                            <span><span class="visually-hidden">Pilih semua jadwal pakan</span></span>
                          </label>
                        </th>
                        <th>Sesi Pakan</th>
                        <th>Waktu Pemberian</th>
                        <th>Hari Aktif</th>
                        <th>Status</th>
                        <th class="text-center">Aksi</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php if (count($jadwal) === 0): ?>
                        <tr><td colspan="6" class="text-center text-muted py-4">Belum ada jadwal pakan. Silakan klik tombol "Tambah Jadwal Pakan".</td></tr>
                      <?php endif; ?>

                      <?php foreach ($jadwal as $index => $item): ?>
                        <?php 
                          $rowCheckboxId = checkbox_id('schedule-row', $item['id'] ?? ''); 
                          $sesiLabel = !empty($item['catatan']) ? $item['catatan'] : ('Pakan ' . ($index + 1));
                          $isActive = !isset($item['is_active']) || $item['is_active'] == 1;
                        ?>
                        <tr>
                          <td class="check-col">
                            <label class="schedule-day log-check m-0" for="<?= e($rowCheckboxId) ?>">
                              <input type="checkbox" id="<?= e($rowCheckboxId) ?>" class="schedule-row-checkbox" name="selected_schedules[]" value="<?= e($item['id']) ?>">
                              <span><span class="visually-hidden">Pilih jadwal pukul <?= e(date('H:i', strtotime($item['waktu']))) ?></span></span>
                            </label>
                          </td>
                          <td>
                            <div class="d-flex align-items-center gap-2">
                              <div style="width: 32px; height: 32px; border-radius: 8px; background: #e6f9f0; color: #10b981; display: grid; place-items: center; font-size: 0.95rem;">
                                <i class="fa-solid fa-utensils"></i>
                              </div>
                              <strong class="text-dark"><?= e($sesiLabel) ?></strong>
                            </div>
                          </td>
                          <td>
                            <span class="badge bg-light text-dark border px-2.5 py-1.5 fw-semibold" style="font-size:0.85rem;">
                              <i class="bi bi-clock me-1 text-primary"></i><?= e(date('H:i', strtotime($item['waktu']))) ?> WIB
                            </span>
                          </td>
                          <td>
                            <span class="text-secondary fw-medium"><?= e($item['hari']) ?></span>
                          </td>
                          <td>
                            <?php if ($isActive): ?>
                              <span class="badge bg-success-subtle text-success border border-success-subtle px-2.5 py-1 rounded-pill fw-semibold">
                                <i class="bi bi-check-circle-fill me-1"></i> Aktif
                              </span>
                            <?php else: ?>
                              <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-2.5 py-1 rounded-pill fw-semibold">
                                <i class="bi bi-pause-circle-fill me-1"></i> Nonaktif
                              </span>
                            <?php endif; ?>
                          </td>
                          <td>
                            <div class="action-icons justify-content-center d-flex align-items-center gap-2">
                              <button type="button" class="btn btn-sm btn-light text-primary border rounded-circle js-edit-schedule" style="width:34px;height:34px;display:grid;place-items:center;"
                                 data-id="<?= e($item['id']) ?>"
                                 data-waktu="<?= e(date('H:i', strtotime($item['waktu']))) ?>"
                                 data-hari="<?= e($item['hari']) ?>"
                                 title="Edit Jadwal">
                                <i class="bi bi-pencil-square"></i>
                              </button>
                              <a href="../proses/proses_pakan_minum.php?action=delete&id=<?= e($item['id']) ?>" 
                                 class="btn btn-sm btn-light text-danger border rounded-circle js-open-delete-modal" style="width:34px;height:34px;display:grid;place-items:center;"
                                 data-delete-href="../proses/proses_pakan_minum.php?action=delete&id=<?= e($item['id']) ?>"
                                 title="Hapus Jadwal">
                                <i class="bi bi-trash3"></i>
                              </a>
                            </div>
                          </td>
                        </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                </div>
              </div>

              <div class="table-footer d-flex justify-content-between align-items-center flex-wrap gap-3 mt-3 pt-3 border-top">
                <button type="submit" class="btn btn-outline-danger btn-sm rounded-pill px-3 py-1.5 fw-semibold d-inline-flex align-items-center gap-2" id="deleteSelectedBtn" disabled>
                  <i class="bi bi-trash3-fill"></i>
                  <span>Hapus Terpilih</span>
                </button>

                <div class="footer-left text-muted small">
                  <span>Menampilkan <strong><?= e($startData) ?> - <?= e($endData) ?></strong> dari <strong><?= e($totalData) ?></strong> jadwal pakan</span>
                </div>

                <div class="pagination-wrap d-flex align-items-center gap-1">
                  <a class="page-chip <?= $page <= 1 ? 'muted' : '' ?>" href="?page=1">&laquo;</a>
                  <a class="page-chip <?= $page <= 1 ? 'muted' : '' ?>" href="?page=<?= e(max(1, $page - 1)) ?>">&lsaquo;</a>
                  <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <a class="page-chip <?= $page === $i ? 'active' : '' ?>" href="?page=<?= e($i) ?>"><?= e($i) ?></a>
                  <?php endfor; ?>
                  <a class="page-chip <?= $page >= $totalPages ? 'muted' : '' ?>" href="?page=<?= e(min($totalPages, $page + 1)) ?>">&rsaquo;</a>
                  <a class="page-chip <?= $page >= $totalPages ? 'muted' : '' ?>" href="?page=<?= e($totalPages) ?>">&raquo;</a>
                </div>
              </div>
            </form>
          </div>

          <!-- Card: Log Pemberian Pakan -->
          <div class="card-soft log-card bg-white rounded-4 p-4 mt-4" style="box-shadow: 0 4px 20px rgba(77, 109, 120, 0.16); border: 1px solid rgba(255,255,255,0.85);">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-3">
              <div>
                <div class="d-flex align-items-center gap-2 mb-1">
                  <div style="width: 38px; height: 38px; border-radius: 12px; background: #e6f9f0; color: #10b981; display: grid; place-items: center; font-size: 1.15rem;">
                    <i class="fa-solid fa-clock-rotate-left"></i>
                  </div>
                  <h2 class="log-title fw-bold text-dark fs-5 mb-0">Log Pemberian Pakan</h2>
                </div>
                <p class="log-subtitle text-muted small mb-0">Riwayat pencatatan aktivitas pakan otomatis dari jadwal dan pemberian manual</p>
              </div>
              <span class="badge bg-light text-secondary border px-3 py-2 rounded-pill fw-semibold">
                <i class="bi bi-broadcast text-success me-1"></i> Realtime Logger
              </span>
            </div>

            <div class="table-wrap">
              <div class="table-responsive">
                <table class="table data-table align-middle">
                  <thead>
                    <tr>
                      <th style="width: 60px;">No</th>
                      <th>Waktu & Tanggal</th>
                      <th>Metode</th>
                      <th>Status</th>
                      <th>Keterangan</th>
                    </tr>
                  </thead>
                  <tbody id="feedLogTableBody">
                    <?php if (count($feedLogs) === 0): ?>
                      <tr><td colspan="5" class="text-center text-muted py-4">Belum ada riwayat aktivitas pakan.</td></tr>
                    <?php endif; ?>

                    <?php foreach ($feedLogs as $idx => $flog): ?>
                      <?php 
                        $isAuto = strtolower($flog['metode'] ?? '') === 'otomatis';
                      ?>
                      <tr>
                        <td class="text-muted fw-semibold"><?= $idx + 1 ?></td>
                        <td>
                          <div class="d-flex align-items-center gap-2">
                            <span class="badge bg-light text-dark border px-2.5 py-1.5 fw-semibold" style="font-size:0.85rem;">
                              <i class="bi bi-clock me-1 text-primary"></i><?= date('H:i', strtotime($flog['waktu'])) ?> WIB
                            </span>
                            <span class="text-secondary small"><?= date('d M Y', strtotime($flog['waktu'])) ?></span>
                          </div>
                        </td>
                        <td>
                          <?php if ($isAuto): ?>
                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill px-3 py-1 fw-semibold">
                              <i class="bi bi-cpu me-1"></i> Otomatis (Jadwal)
                            </span>
                          <?php else: ?>
                            <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle rounded-pill px-3 py-1 fw-semibold">
                              <i class="bi bi-hand-index-thumb me-1"></i> Manual (Dispenser)
                            </span>
                          <?php endif; ?>
                        </td>
                        <td>
                          <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-3 py-1 fw-semibold">
                            <i class="bi bi-check-circle-fill me-1"></i> <?= e($flog['status'] ?? 'Berhasil') ?>
                          </span>
                        </td>
                        <td class="text-secondary small fw-medium">
                          <?= e($flog['keterangan'] ?? 'Pemberian pakan kandang') ?>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>
      </section>
    </main>
  </div>

  <?php if (isset($_GET['status'], $statusMessages[$_GET['status']])): ?>
    <?php $statusConfig = $statusMessages[$_GET['status']]; ?>
    <div class="log-status-toast <?= e($statusConfig['tone']) ?>" id="logStatusAlert" role="alert">
      <div class="log-status-icon">
        <i class="bi <?= e($statusConfig['icon']) ?>"></i>
      </div>
      <div class="log-status-body">
        <strong><?= e($statusConfig['title']) ?></strong>
        <p><?= e($statusConfig['message']) ?></p>
      </div>
      <button type="button" class="log-status-close" id="closeStatusAlert" aria-label="Tutup notifikasi">
        <i class="bi bi-x-lg"></i>
      </button>
    </div>
  <?php endif; ?>

  <!-- Modal Popup: Tambah / Edit Jadwal Pakan (Redesigned) -->
  <div class="modal fade" id="scheduleModal" tabindex="-1" aria-labelledby="scheduleModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content border-0 shadow-lg" style="border-radius: 24px; overflow: hidden;">
        <div class="modal-header border-0 pb-0 pt-4 px-4 d-flex align-items-center justify-content-between">
          <div class="d-flex align-items-center gap-3">
            <div style="width: 48px; height: 48px; border-radius: 14px; background: #e6f9f0; color: #0d9488; display: grid; place-items: center; font-size: 1.35rem;">
              <i class="fa-solid fa-bowl-food"></i>
            </div>
            <div>
              <h5 class="modal-title fw-bold text-dark mb-0" id="scheduleModalTitle">Tambah Jadwal Pakan</h5>
              <small class="text-muted" id="scheduleModalSubtitle">Atur waktu dan hari operasional pakan otomatis</small>
            </div>
          </div>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <form method="post" action="../proses/proses_pakan_minum.php" id="formSchedule">
          <input type="hidden" name="action" id="formAction" value="create">
          <input type="hidden" name="id" id="scheduleId">
          <input type="hidden" name="jenis" value="Pakan">
          
          <div class="modal-body px-4 py-3">
            <!-- Waktu Pemberian -->
            <div class="mb-4">
              <label for="scheduleWaktu" class="form-label fw-bold text-dark small d-flex align-items-center gap-1.5 mb-2">
                <i class="bi bi-clock-fill text-success"></i> Waktu Pemberian (WIB)
              </label>
              <div class="input-group">
                <input type="time" name="waktu" id="scheduleWaktu" class="form-control form-control-lg rounded-3 fw-bold text-dark" value="08:00" required style="border: 1.5px solid #cbd5e1; font-size: 1.35rem; letter-spacing: 1px; padding: 0.6rem 1rem;">
              </div>
            </div>

            <!-- Pilih Hari -->
            <div class="mb-2">
              <div class="d-flex justify-content-between align-items-center mb-2">
                <label class="form-label fw-bold text-dark small d-flex align-items-center gap-1.5 mb-0">
                  <i class="bi bi-calendar-week-fill text-success"></i> Pilih Hari Aktif
                </label>
                <button type="button" class="btn btn-sm btn-link text-decoration-none text-muted p-0 small" id="scheduleResetBtn" style="font-size: 0.8rem;">
                  <i class="bi bi-arrow-repeat me-1"></i>Pilih Ulang
                </button>
              </div>

              <div class="schedule-days-container p-3 rounded-3" style="background: #f8fafc; border: 1px solid #e2e8f0;">
                <div class="mb-3">
                  <label class="form-check d-flex align-items-center gap-2 m-0" style="cursor: pointer;">
                    <input class="form-check-input mt-0" type="checkbox" name="hari[]" value="Semua Hari" id="allDays" checked style="width: 1.15rem; height: 1.15rem;">
                    <span class="form-check-label fw-bold text-dark small">Pilih Semua Hari (Setiap Hari)</span>
                  </label>
                </div>
                <div class="d-flex flex-wrap gap-2 mt-2 pt-1" id="dayButtonsContainer">
                  <?php foreach (['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'] as $day): ?>
                    <label class="day-chip-item m-0">
                      <input type="checkbox" name="hari[]" value="<?= e($day) ?>" class="day-checkbox visually-hidden" checked>
                      <span class="day-chip-badge"><?= e($day) ?></span>
                    </label>
                  <?php endforeach; ?>
                </div>
              </div>
            </div>
          </div>
          
          <div class="modal-footer border-0 pt-0 pb-4 px-4 d-flex justify-content-end gap-2">
            <button type="button" class="btn btn-light rounded-pill px-4 fw-semibold text-secondary" data-bs-dismiss="modal">Batal</button>
            <button type="submit" class="btn btn-success rounded-pill px-4 fw-bold shadow-sm d-inline-flex align-items-center gap-2">
              <i class="bi bi-check2"></i>
              <span>Simpan Jadwal</span>
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Modal Konfirmasi Hapus (Redesigned) -->
  <div class="modal fade" id="deleteConfirmModal" tabindex="-1" aria-labelledby="deleteConfirmTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
      <div class="modal-content border-0 shadow-lg text-center p-4" style="border-radius: 24px;">
        <div class="mx-auto mb-3" style="width: 58px; height: 58px; border-radius: 18px; background: #fee2e2; color: #ef4444; display: grid; place-items: center; font-size: 1.6rem;">
          <i class="bi bi-trash3-fill"></i>
        </div>
        <h5 class="fw-bold text-dark mb-1" id="deleteConfirmTitle">Hapus Jadwal?</h5>
        <p class="text-muted small mb-4" id="deleteConfirmMessage">Apakah Anda yakin ingin menghapus jadwal pakan ini? Tindakan ini tidak dapat dibatalkan.</p>
        <div class="d-flex justify-content-center gap-2">
          <button type="button" class="btn btn-light rounded-pill px-4 fw-semibold text-secondary" data-bs-dismiss="modal">Batal</button>
          <a href="#" class="btn btn-danger rounded-pill px-4 fw-bold shadow-sm" id="confirmDeleteBtn">Ya, Hapus</a>
        </div>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    (() => {
      const scheduleModalEl = document.getElementById("scheduleModal");
      const scheduleModal = scheduleModalEl ? new bootstrap.Modal(scheduleModalEl) : null;
      const deleteConfirmModalEl = document.getElementById("deleteConfirmModal");
      const deleteConfirmModal = deleteConfirmModalEl ? new bootstrap.Modal(deleteConfirmModalEl) : null;

      const formAction = document.getElementById("formAction");
      const scheduleId = document.getElementById("scheduleId");
      const scheduleWaktu = document.getElementById("scheduleWaktu");
      const scheduleModalTitle = document.getElementById("scheduleModalTitle");
      const scheduleModalSubtitle = document.getElementById("scheduleModalSubtitle");
      const allDaysCheckbox = document.getElementById("allDays");
      const dayCheckboxes = document.querySelectorAll(".day-checkbox");
      const scheduleResetBtn = document.getElementById("scheduleResetBtn");

      const confirmDeleteBtn = document.getElementById("confirmDeleteBtn");
      const deleteConfirmMessage = document.getElementById("deleteConfirmMessage");
      const selectAllSchedules = document.getElementById("selectAllSchedules");
      const scheduleRowCheckboxes = document.querySelectorAll(".schedule-row-checkbox");
      const deleteSelectedBtn = document.getElementById("deleteSelectedBtn");
      const bulkDeleteForm = document.getElementById("bulkDeleteForm");
      const logStatusAlert = document.getElementById("logStatusAlert");
      const closeStatusAlert = document.getElementById("closeStatusAlert");
      const feedActionBtn = document.getElementById("feedActionBtn");
      if (feedActionBtn) {
        feedActionBtn.addEventListener("click", async () => {
          feedActionBtn.disabled = true;
          feedActionBtn.innerHTML = `<span class="spinner-border spinner-border-sm me-1"></span> Mengeluarkan Pakan...`;
          try {
            await fetch("../proses/toggle_aktuator.php", {
              method: "POST",
              headers: { "Content-Type": "application/json" },
              body: JSON.stringify({ actuator: "motor_pakan", value: 1 })
            });
            setTimeout(() => {
              feedActionBtn.disabled = false;
              feedActionBtn.innerHTML = `<i class="fa-solid fa-play me-2"></i>Beri Pakan Sekarang`;
              window.location.reload();
            }, 1200);
          } catch (e) {
            feedActionBtn.disabled = false;
            feedActionBtn.innerHTML = `<i class="fa-solid fa-play me-2"></i>Beri Pakan Sekarang`;
          }
        });
      }

      function clearForm() {
        formAction.value = "create";
        scheduleId.value = "";
        scheduleWaktu.value = "08:00";
        scheduleModalTitle.textContent = "Tambah Jadwal Pakan";
        if (scheduleModalSubtitle) scheduleModalSubtitle.textContent = "Atur waktu dan hari operasional pakan otomatis";
        if (allDaysCheckbox) allDaysCheckbox.checked = true;
        dayCheckboxes.forEach((cb) => cb.checked = true);
      }

      // Sync checkbox Hari
      if (allDaysCheckbox) {
        allDaysCheckbox.addEventListener("change", () => {
          dayCheckboxes.forEach((cb) => {
            cb.checked = allDaysCheckbox.checked;
          });
        });
      }

      dayCheckboxes.forEach((cb) => {
        cb.addEventListener("change", () => {
          const allChecked = Array.from(dayCheckboxes).every((item) => item.checked);
          if (allDaysCheckbox) {
            allDaysCheckbox.checked = allChecked;
          }
        });
      });

      if (scheduleResetBtn) {
        scheduleResetBtn.addEventListener("click", () => {
          if (allDaysCheckbox) allDaysCheckbox.checked = true;
          dayCheckboxes.forEach((cb) => cb.checked = true);
        });
      }

      // Buka modal Tambah Jadwal
      const openModalBtn = document.querySelector(".js-open-schedule-modal");
      if (openModalBtn) {
        openModalBtn.addEventListener("click", () => {
          clearForm();
          if (scheduleModal) scheduleModal.show();
        });
      }

      // Edit Jadwal
      document.querySelectorAll(".js-edit-schedule").forEach((button) => {
        button.addEventListener("click", () => {
          formAction.value = "update";
          scheduleId.value = button.dataset.id;
          scheduleWaktu.value = button.dataset.waktu;
          scheduleModalTitle.textContent = "Edit Jadwal Pakan";
          if (scheduleModalSubtitle) scheduleModalSubtitle.textContent = "Ubah waktu dan hari jadwal pakan";

          const rawHari = button.dataset.hari || "Semua Hari";
          const days = rawHari.split(",").map((d) => d.trim());
          const isAll = days.includes("Semua Hari") || days.length === 7;

          if (allDaysCheckbox) allDaysCheckbox.checked = isAll;
          dayCheckboxes.forEach((cb) => {
            cb.checked = isAll || days.includes(cb.value);
          });

          if (scheduleModal) scheduleModal.show();
        });
      });

      // Sync bulk delete
      function syncBulkDeleteState() {
        const checkedCount = Array.from(scheduleRowCheckboxes).filter((item) => item.checked).length;
        if (deleteSelectedBtn) {
          deleteSelectedBtn.disabled = checkedCount === 0;
          deleteSelectedBtn.querySelector("span").textContent = checkedCount > 0 ? `Hapus Terpilih (${checkedCount})` : "Hapus Terpilih";
        }
      }

      if (selectAllSchedules) {
        selectAllSchedules.addEventListener("change", () => {
          scheduleRowCheckboxes.forEach((checkbox) => {
            checkbox.checked = selectAllSchedules.checked;
          });
          syncBulkDeleteState();
        });
      }

      scheduleRowCheckboxes.forEach((checkbox) => {
        checkbox.addEventListener("change", () => {
          if (selectAllSchedules) {
            selectAllSchedules.checked = Array.from(scheduleRowCheckboxes).every((item) => item.checked);
          }
          syncBulkDeleteState();
        });
      });

      // Delete Modal
      document.querySelectorAll(".js-open-delete-modal").forEach((button) => {
        button.addEventListener("click", (event) => {
          event.preventDefault();
          const href = button.dataset.deleteHref || button.getAttribute("href");
          confirmDeleteBtn.setAttribute("href", href);
          confirmDeleteBtn.dataset.bulkDelete = "false";
          deleteConfirmMessage.textContent = "Apakah Anda yakin ingin menghapus jadwal pakan ini? Tindakan ini tidak dapat dibatalkan.";
          if (deleteConfirmModal) deleteConfirmModal.show();
        });
      });

      if (bulkDeleteForm) {
        bulkDeleteForm.addEventListener("submit", (event) => {
          event.preventDefault();
          const hasSelected = Array.from(scheduleRowCheckboxes).some((item) => item.checked);
          if (hasSelected) {
            confirmDeleteBtn.setAttribute("href", "#");
            confirmDeleteBtn.dataset.bulkDelete = "true";
            deleteConfirmMessage.textContent = "Apakah Anda yakin ingin menghapus semua jadwal pakan yang dipilih? Tindakan ini tidak dapat dibatalkan.";
            if (deleteConfirmModal) deleteConfirmModal.show();
          }
        });
      }

      if (confirmDeleteBtn) {
        confirmDeleteBtn.addEventListener("click", (event) => {
          if (confirmDeleteBtn.dataset.bulkDelete === "true") {
            event.preventDefault();
            if (deleteConfirmModal) deleteConfirmModal.hide();
            bulkDeleteForm.submit();
          }
        });
      }

      if (closeStatusAlert) {
        closeStatusAlert.addEventListener("click", () => {
          logStatusAlert.classList.add("is-closing");
          setTimeout(() => logStatusAlert.remove(), 260);
        });
      }
    })();
  </script>
</body>
</html>
