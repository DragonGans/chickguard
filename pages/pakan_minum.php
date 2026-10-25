<?php
session_start();

if (!isset($_SESSION['login'])) {
    header("Location: ../login.php");
    exit;
}

include '../config/koneksi.php';
include '../proses/init_tables.php';
chickguard_init_tables($koneksi);

$limit = 5;
$page = max(1, (int) ($_GET['page'] ?? 1));
$countResult = mysqli_query($koneksi, "SELECT COUNT(*) AS total FROM jadwal_pakan_minum");
$totalData = (int) (mysqli_fetch_assoc($countResult)['total'] ?? 0);
$totalPages = max(1, (int) ceil($totalData / $limit));
if ($page > $totalPages) {
    $page = $totalPages;
}
$offset = ($page - 1) * $limit;

$stmt = mysqli_prepare($koneksi, "SELECT * FROM jadwal_pakan_minum ORDER BY waktu ASC, id ASC LIMIT ? OFFSET ?");
mysqli_stmt_bind_param($stmt, "ii", $limit, $offset);
mysqli_stmt_execute($stmt);
$query = mysqli_stmt_get_result($stmt);
$jadwal = [];
while ($row = mysqli_fetch_assoc($query)) {
    $jadwal[] = $row;
}
$startData = $totalData > 0 ? $offset + 1 : 0;
$endData = $totalData > 0 ? $offset + count($jadwal) : 0;

function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function selected_days(string $hari): array
{
    if ($hari === 'Semua Hari') {
        return ['Semua Hari'];
    }

    return array_map('trim', explode(',', $hari));
}

function checkbox_id($prefix, $value): string
{
    return preg_replace('/[^a-zA-Z0-9_-]/', '-', $prefix . '-' . $value);
}

$statusMessages = [
    'created' => [
        'title' => 'Jadwal Ditambahkan',
        'message' => 'Jadwal pakan atau minum baru berhasil disimpan.',
        'tone' => 'success',
        'icon' => 'bi-check2-circle',
    ],
    'updated' => [
        'title' => 'Jadwal Diperbarui',
        'message' => 'Perubahan jadwal berhasil diterapkan.',
        'tone' => 'success',
        'icon' => 'bi-pencil-square',
    ],
    'deleted' => [
        'title' => 'Jadwal Dihapus',
        'message' => 'Jadwal yang dipilih sudah dihapus dari daftar.',
        'tone' => 'success',
        'icon' => 'bi-trash3',
    ],
    'invalid' => [
        'title' => 'Form Belum Lengkap',
        'message' => 'Periksa kembali isian jadwal sebelum disimpan.',
        'tone' => 'warning',
        'icon' => 'bi-exclamation-circle',
    ],
    'none_selected' => [
        'title' => 'Belum Ada yang Dipilih',
        'message' => 'Pilih minimal satu jadwal pakan atau minum terlebih dahulu sebelum menjalankan hapus massal.',
        'tone' => 'warning',
        'icon' => 'bi-exclamation-circle',
    ],
];

$activePage = 'pakan_minum';
$topbarTitle = 'Pakan Minum';
$topbarSubtitle = 'Pantau stok, aksi cepat, dan jadwal pemberian pakan minum';
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>ChickGuard - Pakan Minum</title>
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
          <div class="card-soft status-overview mb-4">
            <div class="row align-items-center g-4 status-overview-grid">
              <div class="col-12 col-lg-3 status-grid-item supply-panel-item">
                <div class="supply-group">
                  <div class="supply-icon feed"><i class="fa-solid fa-bowl-food"></i></div>
                  <div class="supply-body">
                    <div class="supply-title">Pakan</div>
                    <div class="supply-value" id="feedPercent">75%</div>
                    <div class="supply-stock-text" id="feedStockText">15.0 / 20.0 kg</div>
                    <div class="progress-slim"><div class="progress-bar bg-success" id="feedProgressBar" style="width: 75%"></div></div>
                  </div>
                </div>
              </div>

              <div class="col-auto d-none d-lg-flex status-grid-item divider-item"><div class="overview-divider"></div></div>

              <div class="col-12 col-lg-3 status-grid-item supply-panel-item">
                <div class="supply-group">
                  <div class="supply-icon water"><i class="bi bi-droplet-fill"></i></div>
                  <div class="supply-body">
                    <div class="supply-title">Air</div>
                    <div class="supply-value" id="waterPercent">60%</div>
                    <div class="supply-stock-text" id="waterStockText">12.0 / 20.0 L</div>
                    <div class="progress-slim"><div class="progress-bar bg-info" id="waterProgressBar" style="width: 60%"></div></div>
                  </div>
                </div>
              </div>

              <div class="col-12 col-md-6 col-lg-3 status-grid-item action-slot">
                <div class="d-flex justify-content-center">
                  <button type="button" class="pill-button action-btn feed" id="feedActionBtn">Beri Pakan</button>
                </div>
              </div>

              <div class="col-12 col-md-6 col-lg-3 status-grid-item action-slot">
                <div class="d-flex justify-content-center">
                  <button type="button" class="pill-button action-btn water" id="waterActionBtn">Beri Minum</button>
                </div>
              </div>
            </div>
          </div>

          <div class="card-soft log-card schedule-card">
            <div class="log-toolbar">
              <div>
                <h2 class="log-title">Jadwal Pemberian</h2>
                <p class="log-subtitle">Kelola jadwal pakan dan minum kandang tanpa keluar dari halaman ini</p>
              </div>
              <button type="button" class="pill-button add-btn js-open-schedule-modal">+ Tambah Jadwal</button>
            </div>

            <form method="post" action="../proses/proses_pakan_minum.php" id="bulkDeleteForm">
              <input type="hidden" name="action" value="delete_selected">

              <div class="table-wrap">
                <div class="table-responsive">
                  <table class="table data-table schedule-table align-middle">
                  <thead>
                    <tr>
                      <th class="check-col">
                        <label class="schedule-day log-check">
                          <input type="checkbox" id="selectAllSchedules">
                          <span><span class="visually-hidden">Pilih semua jadwal pakan minum</span></span>
                        </label>
                      </th>
                      <th>Jenis</th>
                      <th>Waktu</th>
                      <th>Jumlah</th>
                      <th>Hari</th>
                      <th>Catatan</th>
                      <th class="text-center">Aksi</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php if (count($jadwal) === 0): ?>
                      <tr><td colspan="7" class="text-center text-muted py-4">Belum ada jadwal.</td></tr>
                    <?php endif; ?>

                    <?php foreach ($jadwal as $item): ?>
                      <?php $unit = $item['jenis'] === 'Pakan' ? 'kg' : 'L'; ?>
                      <?php $rowCheckboxId = checkbox_id('schedule-row', $item['id'] ?? ''); ?>
                      <tr>
                        <td class="check-col">
                          <label class="schedule-day log-check" for="<?= e($rowCheckboxId) ?>">
                            <input type="checkbox" id="<?= e($rowCheckboxId) ?>" class="schedule-row-checkbox" name="selected_schedules[]" value="<?= e($item['id']) ?>">
                            <span><span class="visually-hidden">Pilih jadwal <?= e($item['jenis']) ?> pukul <?= e(date('H:i', strtotime($item['waktu']))) ?></span></span>
                          </label>
                        </td>
                        <td><?= e($item['jenis']) ?></td>
                        <td><?= e(date('H:i', strtotime($item['waktu']))) ?></td>
                        <td><?= e(number_format((float) $item['jumlah'], 1)) ?> <?= e($unit) ?></td>
                        <td><?= e($item['hari']) ?></td>
                        <td><?= e($item['catatan']) ?></td>
                        <td>
                          <div class="action-icons justify-content-center">
                            <i class="bi bi-pencil-square edit js-edit-schedule"
                               data-id="<?= e($item['id']) ?>"
                               data-jenis="<?= e($item['jenis']) ?>"
                               data-waktu="<?= e(date('H:i', strtotime($item['waktu']))) ?>"
                               data-jumlah="<?= e($item['jumlah']) ?>"
                               data-hari="<?= e($item['hari']) ?>"
                               data-catatan="<?= e($item['catatan']) ?>"></i>
                            <a href="../proses/proses_pakan_minum.php?action=delete&id=<?= e($item['id']) ?>" class="js-open-delete-modal" data-delete-href="../proses/proses_pakan_minum.php?action=delete&id=<?= e($item['id']) ?>">
                              <i class="bi bi-trash3 delete"></i>
                            </a>
                          </div>
                        </td>
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
                  <span>Menampilkan <strong><?= e($startData) ?> - <?= e($endData) ?></strong> dari <strong><?= e($totalData) ?></strong> entri</span>
                </div>

                <div class="pagination-wrap">
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
<!-- Ini modal popup -->
  <div class="schedule-modal-backdrop hidden" id="scheduleModal" aria-hidden="true">
    <div class="schedule-modal-card" role="dialog" aria-modal="true" aria-labelledby="scheduleModalTitle">
      <form class="schedule-form" method="post" action="../proses/proses_pakan_minum.php">
        <input type="hidden" name="action" id="formAction" value="create">
        <input type="hidden" name="id" id="scheduleId">

        <div class="schedule-modal-head">
          <div class="schedule-modal-icon" id="scheduleTypeIcon"><i class="fa-solid fa-bowl-food"></i></div>
          <div>
            <h2 id="scheduleModalTitle">Tambah Jadwal</h2>
            <p id="scheduleModalSubtitle">Atur jadwal pemberian pakan dan minum</p>
          </div>
        </div>

        <div class="schedule-group">
          <div class="schedule-group-title">Jenis</div>
          <div class="schedule-type-switch">
            <button type="button" class="schedule-type-btn active" data-type="Pakan">Pakan</button>
            <button type="button" class="schedule-type-btn" data-type="Minum">Minum</button>
          </div>
          <input type="hidden" name="jenis" id="scheduleJenis" value="Pakan">
        </div>

        <div class="schedule-fields-grid">
          <label class="schedule-field">
            <span class="schedule-field-label"><i class="bi bi-clock"></i> Waktu Pemberian</span>
            <input type="time" name="waktu" id="scheduleWaktu" value="08:00" required>
          </label>

          <div class="schedule-field">
            <span class="schedule-field-label"><i class="bi bi-sliders"></i> Jumlah</span>
            <div class="schedule-amount-wrap">
              <input type="number" name="jumlah" id="scheduleJumlah" min="0.1" step="0.1" required>
              <span class="schedule-unit" id="scheduleUnit">Kg</span>
            </div>
          </div>
        </div>

        <div class="schedule-group">
          <span class="schedule-field-label"><i class="bi bi-calendar3"></i> Pilih Hari</span>
          <div class="schedule-days">
            <?php foreach (['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'] as $day): ?>
              <label class="schedule-day"><input type="checkbox" name="hari[]" value="<?= e($day) ?>"><span><?= e($day) ?></span></label>
            <?php endforeach; ?>
            <label class="schedule-day"><input type="checkbox" name="hari[]" value="Semua Hari" id="allDays"><span>Semua Hari</span></label>
          </div>
          <button type="button" class="schedule-reset-btn" id="scheduleResetBtn"><i class="bi bi-arrow-repeat"></i><span>Reset</span></button>
        </div>

        <label class="schedule-field schedule-note-field">
          <span class="schedule-field-label"><i class="bi bi-check-circle"></i> Catatan (Opsional)</span>
          <input type="text" name="catatan" id="scheduleCatatan" placeholder="Contoh: Pagi hari">
        </label>

        <div class="schedule-actions">
          <button type="button" class="schedule-btn secondary" id="closeScheduleModal">Batal</button>
          <button type="submit" class="schedule-btn primary"><i class="bi bi-calendar-check"></i><span>Simpan</span></button>
        </div>
      </form>
    </div>
  </div>

  <div class="delete-modal-backdrop hidden" id="deleteConfirmModal" aria-hidden="true">
    <div class="delete-modal-card" role="dialog" aria-modal="true" aria-labelledby="deleteConfirmTitle">
      <div class="delete-modal-icon">
        <i class="bi bi-trash3-fill"></i>
      </div>
      <h2 id="deleteConfirmTitle">Konfirmasi Hapus</h2>
      <p id="deleteConfirmMessage">Apakah Anda yakin ingin menghapus data ini? Tindakan ini tidak dapat dibatalkan dan data akan hilang secara permanen.</p>
      <div class="delete-modal-actions">
        <button type="button" class="delete-modal-btn secondary" id="cancelDeleteBtn">Batal</button>
        <a href="#" class="delete-modal-btn primary" id="confirmDeleteBtn">Ya, Hapus</a>
      </div>
    </div>
  </div>
<!-- JavaScript -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    function updateWaktu() {
      const now = new Date();
      document.getElementById("jam").textContent = now.toLocaleTimeString("id-ID", { hour: "2-digit", minute: "2-digit" });
      document.getElementById("tanggal").textContent = now.toLocaleDateString("id-ID", { weekday: "long", day: "numeric", month: "long", year: "numeric" });
    }

    setInterval(updateWaktu, 1000);
    updateWaktu();

    const scheduleModal = document.getElementById("scheduleModal");
    const closeScheduleModalBtn = document.getElementById("closeScheduleModal");
    const scheduleResetBtn = document.getElementById("scheduleResetBtn");
    const scheduleTypeButtons = document.querySelectorAll(".schedule-type-btn");
    const scheduleTypeIcon = document.getElementById("scheduleTypeIcon");
    const scheduleUnit = document.getElementById("scheduleUnit");
    const scheduleCheckboxes = document.querySelectorAll(".schedule-day input");
    const allDaysCheckbox = document.getElementById("allDays");
    const scheduleJenis = document.getElementById("scheduleJenis");
    const formAction = document.getElementById("formAction");
    const scheduleId = document.getElementById("scheduleId");
    const scheduleWaktu = document.getElementById("scheduleWaktu");
    const scheduleJumlah = document.getElementById("scheduleJumlah");
    const scheduleCatatan = document.getElementById("scheduleCatatan");
    const scheduleModalTitle = document.getElementById("scheduleModalTitle");
    const deleteConfirmModal = document.getElementById("deleteConfirmModal");
    const cancelDeleteBtn = document.getElementById("cancelDeleteBtn");
    const confirmDeleteBtn = document.getElementById("confirmDeleteBtn");
    const deleteConfirmMessage = document.getElementById("deleteConfirmMessage");
    const selectAllSchedules = document.getElementById("selectAllSchedules");
    const scheduleRowCheckboxes = document.querySelectorAll(".schedule-row-checkbox");
    const deleteSelectedBtn = document.getElementById("deleteSelectedBtn");
    const bulkDeleteForm = document.getElementById("bulkDeleteForm");
    const logStatusAlert = document.getElementById("logStatusAlert");
    const closeStatusAlert = document.getElementById("closeStatusAlert");
    const feedPercent = document.getElementById("feedPercent");
    const waterPercent = document.getElementById("waterPercent");
    const feedStockText = document.getElementById("feedStockText");
    const waterStockText = document.getElementById("waterStockText");
    const feedProgressBar = document.getElementById("feedProgressBar");
    const waterProgressBar = document.getElementById("waterProgressBar");

    const stockState = {
      feed: { current: 15, max: 20, step: 1, unit: "kg", percentEl: feedPercent, stockEl: feedStockText, progressEl: feedProgressBar },
      water: { current: 12, max: 20, step: 0.8, unit: "L", percentEl: waterPercent, stockEl: waterStockText, progressEl: waterProgressBar }
    };

    function renderStock(type) {
      const stock = stockState[type];
      const percent = Math.max(0, (stock.current / stock.max) * 100);
      stock.percentEl.textContent = `${Math.round(percent)}%`;
      stock.stockEl.textContent = `${stock.current.toFixed(1)} / ${stock.max.toFixed(1)} ${stock.unit}`;
      stock.progressEl.style.width = `${percent}%`;
    }

    function useStock(type) {
      stockState[type].current = Math.max(0, stockState[type].current - stockState[type].step);
      renderStock(type);
    }

    function setScheduleType(type) {
      const isPakan = type === "Pakan";
      scheduleJenis.value = type;
      scheduleTypeButtons.forEach((button) => button.classList.toggle("active", button.dataset.type === type));
      scheduleModal.classList.toggle("is-minum", !isPakan);
      scheduleTypeIcon.innerHTML = isPakan ? '<i class="fa-solid fa-bowl-food"></i>' : '<i class="bi bi-droplet-fill"></i>';
      scheduleUnit.textContent = isPakan ? "Kg" : "L";
    }

    function clearForm() {
      formAction.value = "create";
      scheduleId.value = "";
      scheduleWaktu.value = "08:00";
      scheduleJumlah.value = "";
      scheduleCatatan.value = "";
      scheduleModalTitle.textContent = "Tambah Jadwal";
      scheduleCheckboxes.forEach((checkbox) => checkbox.checked = false);
      setScheduleType("Pakan");
    }

    function syncScheduleDayState(changedCheckbox = null) {
      const dayCheckboxes = Array.from(scheduleCheckboxes).filter((checkbox) => checkbox.value !== "Semua Hari");
      if (!allDaysCheckbox) {
        return;
      }

      if (changedCheckbox === allDaysCheckbox) {
        dayCheckboxes.forEach((checkbox) => {
          checkbox.checked = allDaysCheckbox.checked;
        });
        return;
      }

      const allChecked = dayCheckboxes.every((checkbox) => checkbox.checked);
      const anyUnchecked = dayCheckboxes.some((checkbox) => !checkbox.checked);

      if (allChecked) {
        allDaysCheckbox.checked = true;
      } else if (anyUnchecked) {
        allDaysCheckbox.checked = false;
      }
    }

    function openScheduleModal() {
      scheduleModal.classList.remove("hidden");
      document.body.classList.add("modal-open");
      scheduleModal.setAttribute("aria-hidden", "false");
    }

    function closeScheduleModal() {
      scheduleModal.classList.add("hidden");
      document.body.classList.remove("modal-open");
      scheduleModal.setAttribute("aria-hidden", "true");
    }

    function syncBulkDeleteState() {
      const checkedCount = Array.from(scheduleRowCheckboxes).filter((item) => item.checked).length;

      if (deleteSelectedBtn) {
        deleteSelectedBtn.disabled = checkedCount === 0;
        deleteSelectedBtn.querySelector("span").textContent = checkedCount > 0 ? `Hapus (${checkedCount})` : "Hapus";
      }
    }

    function openDeleteModal(href, isBulkDelete = false) {
      confirmDeleteBtn.setAttribute("href", href);
      confirmDeleteBtn.dataset.bulkDelete = isBulkDelete ? "true" : "false";
      deleteConfirmMessage.textContent = isBulkDelete
        ? "Apakah Anda yakin ingin menghapus semua jadwal yang dipilih? Tindakan ini tidak dapat dibatalkan dan data akan hilang secara permanen."
        : "Apakah Anda yakin ingin menghapus data ini? Tindakan ini tidak dapat dibatalkan dan data akan hilang secara permanen.";
      deleteConfirmModal.classList.remove("hidden");
      deleteConfirmModal.setAttribute("aria-hidden", "false");
      document.body.classList.add("modal-open");
    }

    function closeDeleteModal() {
      deleteConfirmModal.classList.add("hidden");
      deleteConfirmModal.setAttribute("aria-hidden", "true");
      confirmDeleteBtn.setAttribute("href", "#");
      confirmDeleteBtn.dataset.bulkDelete = "false";
      document.body.classList.remove("modal-open");
    }

    document.querySelector(".js-open-schedule-modal").addEventListener("click", () => {
      clearForm();
      openScheduleModal();
    });
    closeScheduleModalBtn.addEventListener("click", closeScheduleModal);
    scheduleModal.addEventListener("click", (event) => { if (event.target === scheduleModal) closeScheduleModal(); });
    document.addEventListener("keydown", (event) => { if (event.key === "Escape" && !scheduleModal.classList.contains("hidden")) closeScheduleModal(); });
    scheduleTypeButtons.forEach((button) => button.addEventListener("click", () => setScheduleType(button.dataset.type)));
    scheduleResetBtn.addEventListener("click", () => {
      scheduleCheckboxes.forEach((checkbox) => checkbox.checked = false);
      syncScheduleDayState();
    });
    scheduleCheckboxes.forEach((checkbox) => {
      checkbox.addEventListener("change", () => syncScheduleDayState(checkbox));
    });
    document.getElementById("feedActionBtn").addEventListener("click", () => useStock("feed"));
    document.getElementById("waterActionBtn").addEventListener("click", () => useStock("water"));
    document.querySelectorAll(".js-open-delete-modal").forEach((button) => {
      button.addEventListener("click", (event) => {
        event.preventDefault();
        openDeleteModal(button.dataset.deleteHref);
      });
    });
    cancelDeleteBtn.addEventListener("click", closeDeleteModal);
    deleteConfirmModal.addEventListener("click", (event) => { if (event.target === deleteConfirmModal) closeDeleteModal(); });
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
    if (bulkDeleteForm) {
      bulkDeleteForm.addEventListener("submit", (event) => {
        event.preventDefault();
        const hasSelected = Array.from(scheduleRowCheckboxes).some((item) => item.checked);
        if (hasSelected) {
          openDeleteModal("#", true);
        }
      });
    }
    confirmDeleteBtn.addEventListener("click", (event) => {
      if (confirmDeleteBtn.dataset.bulkDelete === "true") {
        event.preventDefault();
        closeDeleteModal();
        bulkDeleteForm.submit();
      }
    });

    document.querySelectorAll(".js-edit-schedule").forEach((button) => {
      button.addEventListener("click", () => {
        formAction.value = "update";
        scheduleId.value = button.dataset.id;
        scheduleWaktu.value = button.dataset.waktu;
        scheduleJumlah.value = button.dataset.jumlah;
        scheduleCatatan.value = button.dataset.catatan;
        scheduleModalTitle.textContent = "Edit Jadwal";
        setScheduleType(button.dataset.jenis);
        const days = button.dataset.hari.split(",").map((day) => day.trim());
        scheduleCheckboxes.forEach((checkbox) => checkbox.checked = days.includes(checkbox.value));
        syncScheduleDayState();
        openScheduleModal();
      });
    });

    document.addEventListener("keydown", (event) => {
      if (event.key === "Escape" && !deleteConfirmModal.classList.contains("hidden")) closeDeleteModal();
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

    renderStock("feed");
    renderStock("water");
  </script>
</body>
</html>
