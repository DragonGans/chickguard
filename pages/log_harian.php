<?php
session_start();

if (!isset($_SESSION['login'])) {
    header("Location: ../login.php");
    exit;
}

include '../config/koneksi.php';
include '../proses/init_tables.php';
include '../proses/filter_log_harian.php';
chickguard_init_tables($koneksi);

$search = $_GET['search'] ?? '';
$page = max(1, (int) ($_GET['page'] ?? 1));
$rowsPerPage = (int) ($_GET['rows'] ?? 10);
$allowedRowsPerPage = [5, 10, 20, 50];
if (!in_array($rowsPerPage, $allowedRowsPerPage, true)) {
    $rowsPerPage = 10;
}

$logData = ambil_log_harian($koneksi, $search, $page, $rowsPerPage);
$rows = $logData['rows'];
$total = $logData['total'];
$limit = $logData['limit'];
$page = $logData['page'];
$totalPages = $logData['total_pages'];
$search = $logData['search'];
$shown = count($rows);
$startData = $total > 0 ? (($page - 1) * $limit) + 1 : 0;
$endData = $total > 0 ? $startData + $shown - 1 : 0;

$statusMessages = [
    'deleted' => [
        'title' => 'Berhasil Dihapus',
        'message' => 'Log sensor yang dipilih berhasil dihapus dari daftar.',
        'tone' => 'success',
        'icon' => 'bi-check2-circle',
    ],
    'invalid' => [
        'title' => 'Belum Ada yang Dipilih',
        'message' => 'Pilih minimal satu log sensor terlebih dahulu sebelum menjalankan hapus massal.',
        'tone' => 'warning',
        'icon' => 'bi-exclamation-circle',
    ],
];

function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function hari_log($createdAt): string
{
    $timestamp = strtotime((string) $createdAt);
    $hari = [
        'Sunday' => 'Minggu',
        'Monday' => 'Senin',
        'Tuesday' => 'Selasa',
        'Wednesday' => 'Rabu',
        'Thursday' => 'Kamis',
        'Friday' => 'Jumat',
        'Saturday' => 'Sabtu',
    ];

    return $hari[date('l', $timestamp)] ?? 'Senin';
}

function checkbox_id($prefix, $value): string
{
    return preg_replace('/[^a-zA-Z0-9_-]/', '-', $prefix . '-' . $value);
}

function log_page_url(int $page, string $search, int $rows): string
{
    $params = ['page' => $page, 'rows' => $rows];
    if ($search !== '') {
        $params['search'] = $search;
    }

    return 'log_harian.php?' . http_build_query($params);
}

$activePage = 'log_harian';
$topbarTitle = 'Riwayat Data Sensor';
$topbarSubtitle = 'Tinjau riwayat pencatatan suhu, kelembaban, intensitas cahaya, level air, dan status lampu kandang.';
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>ChickGuard - Riwayat Data Sensor</title>
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
  <link rel="stylesheet" href="../assets/css/log_harian.css">
</head>
<body class="dashboard-page page-log-harian">
  <div class="dashboard-shell d-lg-flex">
    <?php include '../components/sidebar.php'; ?>

    <main class="main-content">
      <?php include '../components/topbar.php'; ?>

      <section class="content-section">
        <div class="container-fluid px-0">
          <div class="card-soft log-card bg-white rounded-4 p-4" style="box-shadow: 0 4px 20px rgba(77, 109, 120, 0.16); border: 1px solid rgba(255,255,255,0.85);">
            <div class="log-toolbar d-flex justify-content-between align-items-center flex-wrap gap-3 mb-3">
              <div>
                <h2 class="log-title fw-bold text-dark fs-5 mb-1">Riwayat Data Sensor Kandang</h2>
                <p class="log-subtitle text-muted small mb-0">Data pencatatan berkala mikroklimat: suhu, kelembaban, intensitas cahaya, level air & lampu</p>
              </div>

              <form class="toolbar-actions d-flex align-items-center gap-2" method="get" action="log_harian.php">
                <input type="hidden" name="rows" value="<?= e($limit) ?>">
                <div class="search-box">
                  <input type="text" name="search" class="search-input" value="<?= e($search) ?>" placeholder="Cari waktu, tanggal, lampu...">
                </div>

                <button type="submit" class="search-submit-btn" aria-label="Cari">
                  <i class="bi bi-search"></i>
                </button>
                <?php if ($search !== ''): ?>
                  <a href="log_harian.php?rows=<?= e($limit) ?>" class="btn btn-sm btn-light fw-semibold rounded-pill px-3">Reset</a>
                <?php endif; ?>
              </form>
            </div>

            <form method="post" action="../proses/proses_log_harian.php" id="bulkDeleteForm">
              <input type="hidden" name="action" value="delete_selected">

              <div class="table-wrap">
                <div class="table-responsive">
                  <table class="table data-table log-table align-middle">
                    <thead>
                      <tr>
                        <th class="check-col">
                          <label class="schedule-day log-check m-0">
                            <input type="checkbox" id="selectAllLogs">
                            <span><span class="visually-hidden">Pilih semua log</span></span>
                          </label>
                        </th>
                        <th>Hari & Tanggal</th>
                        <th>Waktu</th>
                        <th>Suhu Kandang</th>
                        <th>Kelembapan</th>
                        <th>Intensitas Cahaya</th>
                        <th>Level Air</th>
                        <th class="text-center">Status Lampu</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php if ($shown === 0): ?>
                        <tr>
                          <td colspan="8" class="text-center text-muted py-4">Data riwayat sensor tidak ditemukan.</td>
                        </tr>
                      <?php endif; ?>

                      <?php foreach ($rows as $item): ?>
                        <?php 
                          $lampuOn = ($item['lampu'] ?? '') === 'Hidup';
                          $rowCheckboxId = checkbox_id('log-row', ($item['id'] ?? '') . '-' . ($item['waktu'] ?? ''));
                          $cahayaVal = isset($item['cahaya']) ? (float)$item['cahaya'] : 1240.0;
                          $airVal = isset($item['level_air']) ? (float)$item['level_air'] : 72.0;
                        ?>
                        <tr>
                          <td class="check-col">
                            <label class="schedule-day log-check m-0" for="<?= e($rowCheckboxId) ?>">
                              <input type="checkbox" id="<?= e($rowCheckboxId) ?>" class="log-row-checkbox" name="selected_logs[]" value="<?= e($item['id'] ?? $item['waktu']) ?>">
                              <span><span class="visually-hidden">Pilih log pada <?= e(date('H:i', strtotime($item['waktu']))) ?></span></span>
                            </label>
                          </td>
                          <td>
                            <div class="fw-bold text-dark"><?= e(hari_log($item['created_at'] ?? '')) ?></div>
                            <small class="text-muted"><?= date('d M Y', strtotime($item['created_at'] ?? $item['waktu'])) ?></small>
                          </td>
                          <td>
                            <span class="badge bg-light text-dark border px-2.5 py-1.5 fw-semibold" style="font-size:0.85rem;">
                              <i class="bi bi-clock me-1 text-primary"></i><?= e(date('H:i', strtotime($item['waktu']))) ?> WIB
                            </span>
                          </td>
                          <td>
                            <span class="fw-bold fs-6 text-dark"><?= e(number_format((float) $item['suhu'], 1)) ?></span> <span class="text-secondary small">&deg;C</span>
                          </td>
                          <td>
                            <span class="fw-bold fs-6 text-dark"><?= e(number_format((float) $item['kelembaban'], 1)) ?></span> <span class="text-secondary small">%</span>
                          </td>
                          <td>
                            <span class="fw-bold fs-6 text-dark"><?= e(number_format($cahayaVal, 0)) ?></span> <span class="text-secondary small">lux</span>
                          </td>
                          <td>
                            <span class="fw-bold fs-6 text-dark"><?= e(number_format($airVal, 0)) ?></span> <span class="text-secondary small">%</span>
                          </td>
                          <td class="text-center">
                            <?php if ($lampuOn): ?>
                              <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle rounded-pill px-3 py-1 fw-bold">
                                <i class="bi bi-lightbulb-fill me-1"></i> Hidup
                              </span>
                            <?php else: ?>
                              <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle rounded-pill px-3 py-1 fw-bold">
                                <i class="bi bi-lightbulb-off me-1"></i> Mati
                              </span>
                            <?php endif; ?>
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
                  <span>Menampilkan <strong><?= e($startData) ?> - <?= e($endData) ?></strong> dari <strong><?= e($total) ?></strong> data sensor</span>
                </div>

                <div class="footer-right d-flex align-items-center gap-3">
                  <div class="pagination-wrap d-flex align-items-center gap-1">
                    <a class="page-chip <?= $page <= 1 ? 'muted' : '' ?>" href="<?= e(log_page_url(1, $search, $limit)) ?>">&laquo;</a>
                    <a class="page-chip <?= $page <= 1 ? 'muted' : '' ?>" href="<?= e(log_page_url(max(1, $page - 1), $search, $limit)) ?>">&lsaquo;</a>
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                      <a class="page-chip <?= $page === $i ? 'active' : '' ?>" href="<?= e(log_page_url($i, $search, $limit)) ?>"><?= e($i) ?></a>
                    <?php endfor; ?>
                    <a class="page-chip <?= $page >= $totalPages ? 'muted' : '' ?>" href="<?= e(log_page_url(min($totalPages, $page + 1), $search, $limit)) ?>">&rsaquo;</a>
                    <a class="page-chip <?= $page >= $totalPages ? 'muted' : '' ?>" href="<?= e(log_page_url($totalPages, $search, $limit)) ?>">&raquo;</a>
                  </div>

                  <div class="rows-box d-flex align-items-center gap-2">
                    <label for="rowsPerPageSelect" class="small text-muted mb-0">Baris:</label>
                    <select class="form-select form-select-sm rounded-pill px-3 py-1" id="rowsPerPageSelect" aria-label="Jumlah baris per halaman" style="width: auto;">
                      <?php foreach ($allowedRowsPerPage as $rowOption): ?>
                        <option value="<?= e($rowOption) ?>" <?= $limit === $rowOption ? 'selected' : '' ?>><?= e($rowOption) ?></option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                </div>
              </div>
            </form>
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

  <!-- Modal Konfirmasi Hapus -->
  <div class="modal fade" id="deleteConfirmModal" tabindex="-1" aria-labelledby="deleteConfirmTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
      <div class="modal-content border-0 shadow-lg text-center p-4" style="border-radius: 24px;">
        <div class="mx-auto mb-3" style="width: 58px; height: 58px; border-radius: 18px; background: #fee2e2; color: #ef4444; display: grid; place-items: center; font-size: 1.6rem;">
          <i class="bi bi-trash3-fill"></i>
        </div>
        <h5 class="fw-bold text-dark mb-1" id="deleteConfirmTitle">Hapus Log Sensor?</h5>
        <p class="text-muted small mb-4" id="deleteConfirmMessage">Apakah Anda yakin ingin menghapus data log yang dipilih? Tindakan ini tidak dapat dibatalkan.</p>
        <div class="d-flex justify-content-center gap-2">
          <button type="button" class="btn btn-light rounded-pill px-4 fw-semibold text-secondary" data-bs-dismiss="modal">Batal</button>
          <button type="button" class="btn btn-danger rounded-pill px-4 fw-bold shadow-sm" id="confirmDeleteBtn">Ya, Hapus</button>
        </div>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    (() => {
      const selectAllLogs = document.getElementById("selectAllLogs");
      const logRowCheckboxes = document.querySelectorAll(".log-row-checkbox");
      const deleteSelectedBtn = document.getElementById("deleteSelectedBtn");
      const bulkDeleteForm = document.getElementById("bulkDeleteForm");
      const rowsPerPageSelect = document.getElementById("rowsPerPageSelect");
      const deleteModalEl = document.getElementById("deleteConfirmModal");
      const deleteModal = deleteModalEl ? new bootstrap.Modal(deleteModalEl) : null;
      const confirmDeleteBtn = document.getElementById("confirmDeleteBtn");
      const logStatusAlert = document.getElementById("logStatusAlert");
      const closeStatusAlert = document.getElementById("closeStatusAlert");

      function syncBulkDeleteState() {
        const checkedCount = Array.from(logRowCheckboxes).filter((item) => item.checked).length;
        if (deleteSelectedBtn) {
          deleteSelectedBtn.disabled = checkedCount === 0;
          deleteSelectedBtn.querySelector("span").textContent = checkedCount > 0 ? `Hapus (${checkedCount})` : "Hapus Terpilih";
        }
      }

      if (selectAllLogs) {
        selectAllLogs.addEventListener("change", () => {
          logRowCheckboxes.forEach((checkbox) => {
            checkbox.checked = selectAllLogs.checked;
          });
          syncBulkDeleteState();
        });
      }

      logRowCheckboxes.forEach((checkbox) => {
        checkbox.addEventListener("change", () => {
          if (selectAllLogs) {
            selectAllLogs.checked = Array.from(logRowCheckboxes).every((item) => item.checked);
          }
          syncBulkDeleteState();
        });
      });

      if (bulkDeleteForm) {
        bulkDeleteForm.addEventListener("submit", (event) => {
          event.preventDefault();
          const hasSelected = Array.from(logRowCheckboxes).some((item) => item.checked);
          if (hasSelected && deleteModal) {
            deleteModal.show();
          }
        });
      }

      if (confirmDeleteBtn) {
        confirmDeleteBtn.addEventListener("click", () => {
          if (deleteModal) deleteModal.hide();
          bulkDeleteForm.submit();
        });
      }

      if (closeStatusAlert && logStatusAlert) {
        closeStatusAlert.addEventListener("click", () => {
          logStatusAlert.classList.add("is-closing");
          setTimeout(() => logStatusAlert.remove(), 200);
        });
      }

      if (rowsPerPageSelect) {
        rowsPerPageSelect.addEventListener("change", () => {
          const url = new URL(window.location.href);
          url.searchParams.set("rows", rowsPerPageSelect.value);
          url.searchParams.set("page", "1");
          if (!url.searchParams.get("search")) {
            url.searchParams.delete("search");
          }
          window.location.href = url.toString();
        });
      }

      syncBulkDeleteState();
    })();
  </script>
</body>
</html>
