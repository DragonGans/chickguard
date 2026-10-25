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
$rowsPerPage = (int) ($_GET['rows'] ?? 5);
$allowedRowsPerPage = [5, 10, 20, 50];
if (!in_array($rowsPerPage, $allowedRowsPerPage, true)) {
    $rowsPerPage = 5;
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
        'message' => 'Log harian yang dipilih berhasil dihapus dari daftar.',
        'tone' => 'success',
        'icon' => 'bi-check2-circle',
    ],
    'invalid' => [
        'title' => 'Belum Ada yang Dipilih',
        'message' => 'Pilih minimal satu log harian terlebih dahulu sebelum menjalankan hapus massal.',
        'tone' => 'warning',
        'icon' => 'bi-exclamation-circle',
    ],
];

function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function format_number_or_dash($value, string $unit): string
{
    if ($value === null || $value === '') {
        return '-';
    }

    return number_format((float) $value, 1) . ' ' . $unit;
}

function dash_if_empty($value): string
{
    $value = trim((string) $value);
    return $value === '' ? '-' : $value;
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

    return $hari[date('l', $timestamp)] ?? '-';
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
$topbarTitle = 'Log Harian';
$topbarSubtitle = 'Tinjau riwayat suhu, kelembaban, pakan, minum, dan status lampu kandang';
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>ChickGuard - Log Harian</title>
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
          <div class="card-soft log-card">
            <div class="log-toolbar">
              <div>
                <h2 class="log-title">Log Harian Kandang</h2>
                <p class="log-subtitle">Data pencatatan harian suhu, kelembaban, pakan, minum & lampu</p>
              </div>

              <form class="toolbar-actions" method="get" action="log_harian.php">
                <input type="hidden" name="rows" value="<?= e($limit) ?>">
                <div class="search-box">
                  <input type="text" name="search" class="search-input" value="<?= e($search) ?>" placeholder="Cari waktu, status, lampu...">
                </div>

                <button type="submit" class="search-submit-btn" aria-label="Cari">
                  <i class="bi bi-search"></i>
                </button>
                <?php if ($search !== ''): ?>
                  <a href="log_harian.php?rows=<?= e($limit) ?>" class="btn btn-sm btn-light fw-semibold">Reset</a>
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
                          <label class="schedule-day log-check">
                            <input type="checkbox" id="selectAllLogs">
                            <span><span class="visually-hidden">Pilih semua log</span></span>
                          </label>
                        </th>
                        <th>Hari</th>
                        <th>Waktu</th>
                        <th>Suhu & Kelembapan</th>
                        <th>Pakan & Minum</th>
                        <th class="text-center">Lampu</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php if ($shown === 0): ?>
                        <tr>
                          <td colspan="6" class="text-center text-muted py-4">Data log tidak ditemukan.</td>
                        </tr>
                      <?php endif; ?>

                      <?php foreach ($rows as $item): ?>
                        <?php $lampuClass = $item['lampu'] === 'Hidup' ? 'on' : 'off'; ?>
                        <?php $rowCheckboxId = checkbox_id('log-row', ($item['id'] ?? '') . '-' . ($item['waktu'] ?? '')); ?>
                        <tr>
                          <td class="check-col">
                            <label class="schedule-day log-check" for="<?= e($rowCheckboxId) ?>">
                              <input type="checkbox" id="<?= e($rowCheckboxId) ?>" class="log-row-checkbox" name="selected_logs[]" value="<?= e($item['id'] ?? $item['waktu']) ?>">
                              <span><span class="visually-hidden">Pilih log pada <?= e(date('H:i', strtotime($item['waktu']))) ?></span></span>
                            </label>
                          </td>
                          <td><?= e(hari_log($item['created_at'] ?? '')) ?></td>
                          <td><?= e(date('H:i', strtotime($item['waktu']))) ?></td>
                          <td class="cell-strong">
                            <?= e(number_format((float) $item['suhu'], 0)) ?>&deg;C /
                            <?= e(number_format((float) $item['kelembaban'], 0)) ?>%
                          </td>
                          <td>
                            Pakan: <?= e(format_number_or_dash($item['pakan'] ?? null, 'kg')) ?><br>
                            Minum: <?= e(format_number_or_dash($item['minum'] ?? null, 'L')) ?>
                          </td>
                          <td class="text-center"><span class="lamp-badge <?= e($lampuClass) ?>"><?= e($item['lampu']) ?></span></td>
                        </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                </div>
              </div>

              <div class="table-footer">
                <button type="submit" class="log-delete-btn" id="deleteSelectedBtn" disabled>
                  <i class="bi bi-trash3-fill"></i>
                  <span>Hapus</span>
                </button>

                <div class="footer-left">
                  <span>Menampilkan <strong><?= e($startData) ?> - <?= e($endData) ?></strong> dari <strong><?= e($total) ?></strong> entri</span>
                </div>

                <div class="footer-right">
                  <div class="pagination-wrap">
                    <a class="page-chip <?= $page <= 1 ? 'muted' : '' ?>" href="<?= e(log_page_url(1, $search, $limit)) ?>">&laquo;</a>
                    <a class="page-chip <?= $page <= 1 ? 'muted' : '' ?>" href="<?= e(log_page_url(max(1, $page - 1), $search, $limit)) ?>">&lsaquo;</a>
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                      <a class="page-chip <?= $page === $i ? 'active' : '' ?>" href="<?= e(log_page_url($i, $search, $limit)) ?>"><?= e($i) ?></a>
                    <?php endfor; ?>
                    <a class="page-chip <?= $page >= $totalPages ? 'muted' : '' ?>" href="<?= e(log_page_url(min($totalPages, $page + 1), $search, $limit)) ?>">&rsaquo;</a>
                    <a class="page-chip <?= $page >= $totalPages ? 'muted' : '' ?>" href="<?= e(log_page_url($totalPages, $search, $limit)) ?>">&raquo;</a>
                  </div>

                  <div class="rows-box">
                    <label for="rowsPerPageSelect">Baris</label>
                    <select class="rows-select" id="rowsPerPageSelect" aria-label="Jumlah baris per halaman">
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

  <div class="delete-modal-backdrop hidden" id="deleteConfirmModal" aria-hidden="true">
    <div class="delete-modal-card" role="dialog" aria-modal="true" aria-labelledby="deleteConfirmTitle">
      <div class="delete-modal-icon">
        <i class="bi bi-trash3-fill"></i>
      </div>
      <h2 id="deleteConfirmTitle">Konfirmasi Hapus</h2>
      <p>Apakah Anda yakin ingin menghapus semua log yang dipilih? Tindakan ini tidak dapat dibatalkan dan data akan hilang secara permanen.</p>
      <div class="delete-modal-actions">
        <button type="button" class="delete-modal-btn secondary" id="cancelDeleteBtn">Batal</button>
        <button type="button" class="delete-modal-btn primary" id="confirmDeleteBtn">Ya, Hapus</button>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    function updateWaktu() {
      const now = new Date();
      document.getElementById("jam").textContent = now.toLocaleTimeString("id-ID", { hour: "2-digit", minute: "2-digit" });
      document.getElementById("tanggal").textContent = now.toLocaleDateString("id-ID", { weekday: "long", day: "numeric", month: "long", year: "numeric" });
    }

    setInterval(updateWaktu, 1000);
    updateWaktu();

    const selectAllLogs = document.getElementById("selectAllLogs");
    const logRowCheckboxes = document.querySelectorAll(".log-row-checkbox");
    const deleteSelectedBtn = document.getElementById("deleteSelectedBtn");
    const bulkDeleteForm = document.getElementById("bulkDeleteForm");
    const rowsPerPageSelect = document.getElementById("rowsPerPageSelect");
    const deleteConfirmModal = document.getElementById("deleteConfirmModal");
    const cancelDeleteBtn = document.getElementById("cancelDeleteBtn");
    const confirmDeleteBtn = document.getElementById("confirmDeleteBtn");
    const logStatusAlert = document.getElementById("logStatusAlert");
    const closeStatusAlert = document.getElementById("closeStatusAlert");

    function syncBulkDeleteState() {
      const checkedCount = Array.from(logRowCheckboxes).filter((item) => item.checked).length;

      if (deleteSelectedBtn) {
        deleteSelectedBtn.disabled = checkedCount === 0;
        deleteSelectedBtn.querySelector("span").textContent = checkedCount > 0 ? `Hapus (${checkedCount})` : "Hapus";
      }
    }

    function openDeleteModal() {
      deleteConfirmModal.classList.remove("hidden");
      deleteConfirmModal.setAttribute("aria-hidden", "false");
      document.body.classList.add("modal-open");
    }

    function closeDeleteModal() {
      deleteConfirmModal.classList.add("hidden");
      deleteConfirmModal.setAttribute("aria-hidden", "true");
      document.body.classList.remove("modal-open");
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
        if (!selectAllLogs) {
          return;
        }

        const allChecked = Array.from(logRowCheckboxes).every((item) => item.checked);
        selectAllLogs.checked = allChecked;
        syncBulkDeleteState();
      });
    });

    if (bulkDeleteForm) {
      bulkDeleteForm.addEventListener("submit", (event) => {
        const hasSelected = Array.from(logRowCheckboxes).some((item) => item.checked);

        if (!hasSelected) {
          event.preventDefault();
          return;
        }
        event.preventDefault();
        openDeleteModal();
      });
    }

    if (confirmDeleteBtn) {
      confirmDeleteBtn.addEventListener("click", () => {
        closeDeleteModal();
        bulkDeleteForm.submit();
      });
    }

    if (cancelDeleteBtn) {
      cancelDeleteBtn.addEventListener("click", closeDeleteModal);
    }

    if (deleteConfirmModal) {
      deleteConfirmModal.addEventListener("click", (event) => {
        if (event.target === deleteConfirmModal) {
          closeDeleteModal();
        }
      });
    }

    document.addEventListener("keydown", (event) => {
      if (event.key === "Escape" && deleteConfirmModal && !deleteConfirmModal.classList.contains("hidden")) {
        closeDeleteModal();
      }
    });

    if (closeStatusAlert && logStatusAlert) {
      closeStatusAlert.addEventListener("click", () => {
        logStatusAlert.classList.add("is-closing");
        window.setTimeout(() => {
          logStatusAlert.remove();
        }, 180);
      });

      window.setTimeout(() => {
        if (document.body.contains(logStatusAlert)) {
          logStatusAlert.classList.add("is-closing");
          window.setTimeout(() => {
            if (document.body.contains(logStatusAlert)) {
              logStatusAlert.remove();
            }
          }, 180);
        }
      }, 4200);
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
  </script>
</body>
</html>
