<?php
session_start();

if (!isset($_SESSION['login'])) {
    header("Location: ../login.php");
    exit;
}

include '../config/koneksi.php';
require_once '../proses/init_tables.php';
require_once '../proses/helpers.php';
chickguard_init_tables($koneksi);

$activePage = 'pengaturan';

$sessionUser = $_SESSION['username'] ?? 'admin';

// Query User Data
$uStmt = mysqli_prepare($koneksi, "SELECT * FROM users WHERE username = ? LIMIT 1");
mysqli_stmt_bind_param($uStmt, "s", $sessionUser);
mysqli_stmt_execute($uStmt);
$uRes = mysqli_stmt_get_result($uStmt);
$userData = mysqli_fetch_assoc($uRes);

if (!$userData) {
    $userData = [
        'id' => 1,
        'username' => 'admin',
        'email' => 'admin@chickguard.local',
        'role' => 'admin',
        'created_at' => date('Y-m-d H:i:s'),
    ];
}

// User Info for Topbar
$topbarTitle = 'Pengaturan Sistem & Akun';
$topbarSubtitle = 'Kelola profil akun (username & email), password, dan parameter kandang ayam IoT.';

// Query Pengaturan Kandang
$settingQuery = mysqli_query($koneksi, "SELECT * FROM pengaturan_kandang WHERE id = 1 LIMIT 1");
$settings = $settingQuery ? mysqli_fetch_assoc($settingQuery) : [
    'nama_kandang'        => 'Kandang 1 - Utama',
    'tanggal_masuk_ayam'  => date('Y-m-d', strtotime('-18 days')),
    'suhu_min'            => 24.0,
    'suhu_maks'           => 34.0,
    'kelembaban_min'      => 55.0,
    'kelembaban_maks'     => 75.0,
    'cahaya_min'          => 500,
    'level_air_min'       => 50.0,
];

if (empty($settings['tanggal_masuk_ayam'])) {
    $settings['tanggal_masuk_ayam'] = date('Y-m-d', strtotime('-18 days'));
}

// Hitung Umur Ayam
$tglMasukTs = strtotime($settings['tanggal_masuk_ayam']);
$umurAyamHari = max(0, (int) round((strtotime('today') - $tglMasukTs) / 86400));
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>ChickGuard - Pengaturan Sistem & Akun</title>
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
  <link rel="stylesheet" href="../assets/css/pengaturan.css">
</head>
<body class="dashboard-page page-pengaturan">
  <div class="dashboard-shell d-lg-flex">
    <?php include '../components/sidebar.php'; ?>

    <main class="main-content">
      <?php include '../components/topbar.php'; ?>

      <!-- User Profile Header Banner -->
      <div class="user-banner-card d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">
        <div class="d-flex align-items-center gap-3">
          <div class="user-banner-avatar">
            <i class="bi bi-person-fill"></i>
          </div>
          <div>
            <div class="d-flex align-items-center gap-2 flex-wrap">
              <h2 class="user-banner-name mb-0" id="bannerUsernameDisplay"><?= htmlspecialchars($userData['username'], ENT_QUOTES, 'UTF-8') ?></h2>
              <span class="user-badge-role"><?= htmlspecialchars(strtoupper($userData['role'] ?? 'Admin'), ENT_QUOTES, 'UTF-8') ?></span>
            </div>
            <div class="user-banner-meta mt-1">
              <span><i class="bi bi-envelope me-1"></i><span id="bannerEmail"><?= htmlspecialchars($userData['email'] ?? 'admin@chickguard.local', ENT_QUOTES, 'UTF-8') ?></span></span>
              <span class="mx-2">&bull;</span>
              <span><i class="bi bi-shield-check text-success me-1"></i>Status: Terverifikasi</span>
            </div>
          </div>
        </div>
        <div class="d-flex align-items-center gap-2">
          <span class="badge rounded-pill px-3 py-2 fw-semibold" style="background:#e6f7f8;color:#0d9488;border:1px solid #bce6eb;">
            <i class="bi bi-cpu me-1"></i> ChickGuard IoT Manager
          </span>
        </div>
      </div>

      <!-- Settings Grid Sections -->
      <div class="row g-3 g-xl-4 mb-4">
        <!-- 1. Pengaturan User (Username & Email) -->
        <div class="col-12 col-lg-6">
          <div class="settings-card">
            <div class="settings-header">
              <div>
                <h3 class="settings-title">
                  <i class="bi bi-person-badge" style="color:#0d9488;"></i>
                  <span>Pengaturan User</span>
                </h3>
                <p class="settings-subtitle">Atur username akun dan alamat email terdaftar</p>
              </div>
            </div>

            <form id="formProfilUser" autocomplete="off">
              <input type="hidden" name="action" value="profil">

              <div class="form-group-modern">
                <label for="inputUsername" class="form-label-modern">
                  <i class="bi bi-person text-muted"></i> Username Akun
                </label>
                <input type="text" class="form-control-modern" id="inputUsername" name="username" 
                       value="<?= htmlspecialchars($userData['username'], ENT_QUOTES, 'UTF-8') ?>" required>
                <div class="settings-help-text">Digunakan untuk login ke panel sistem (huruf, angka, underscore).</div>
              </div>

              <div class="form-group-modern">
                <label for="inputEmail" class="form-label-modern">
                  <i class="bi bi-envelope text-muted"></i> Alamat Email
                </label>
                <input type="email" class="form-control-modern" id="inputEmail" name="email" 
                       value="<?= htmlspecialchars($userData['email'] ?? 'admin@chickguard.local', ENT_QUOTES, 'UTF-8') ?>" required>
                <div class="settings-help-text">Alamat email aktif untuk pemberitahuan dan pemulihan akun.</div>
              </div>

              <div class="mt-auto pt-3">
                <button type="submit" class="btn-settings-save" id="btnSaveProfil">
                  <i class="bi bi-check-circle-fill"></i> Simpan Pengaturan User
                </button>
              </div>
            </form>
          </div>
        </div>

        <!-- 2. Keamanan & Password -->
        <div class="col-12 col-lg-6">
          <div class="settings-card">
            <div class="settings-header">
              <div>
                <h3 class="settings-title">
                  <i class="bi bi-shield-lock-fill" style="color:#496d74;"></i>
                  <span>Keamanan & Password</span>
                </h3>
                <p class="settings-subtitle">Perbarui password akun untuk menjaga keamanan akses</p>
              </div>
            </div>

            <form id="formGantiPassword" autocomplete="off">
              <input type="hidden" name="action" value="password">

              <div class="form-group-modern">
                <label for="inputPasswordLama" class="form-label-modern">
                  <i class="bi bi-lock text-muted"></i> Password Saat Ini
                </label>
                <div class="input-group-modern">
                  <input type="password" class="form-control-modern" id="inputPasswordLama" name="password_lama" 
                         placeholder="Masukkan password saat ini" required>
                  <button type="button" class="btn-toggle-eye" data-target="inputPasswordLama" aria-label="Toggle password">
                    <i class="bi bi-eye"></i>
                  </button>
                </div>
              </div>

              <div class="form-group-modern">
                <label for="inputPasswordBaru" class="form-label-modern">
                  <i class="bi bi-shield-plus text-muted"></i> Password Baru
                </label>
                <div class="input-group-modern">
                  <input type="password" class="form-control-modern" id="inputPasswordBaru" name="password_baru" 
                         placeholder="Minimal 6 karakter" required minlength="6">
                  <button type="button" class="btn-toggle-eye" data-target="inputPasswordBaru" aria-label="Toggle password">
                    <i class="bi bi-eye"></i>
                  </button>
                </div>
              </div>

              <div class="form-group-modern">
                <label for="inputKonfirmasiPassword" class="form-label-modern">
                  <i class="bi bi-check-all text-muted"></i> Konfirmasi Password Baru
                </label>
                <div class="input-group-modern">
                  <input type="password" class="form-control-modern" id="inputKonfirmasiPassword" name="konfirmasi_password" 
                         placeholder="Ulangi password baru" required minlength="6">
                  <button type="button" class="btn-toggle-eye" data-target="inputKonfirmasiPassword" aria-label="Toggle password">
                    <i class="bi bi-eye"></i>
                  </button>
                </div>
              </div>

              <div class="mt-auto pt-3">
                <button type="submit" class="btn-settings-secondary" id="btnSavePassword">
                  <i class="bi bi-shield-lock-fill"></i> Perbarui Password
                </button>
              </div>
            </form>
          </div>
        </div>

        <!-- 3. Pengaturan Kandang (Nama Kandang, Tanggal Masuk Ayam, Ambang Batas Sensor) -->
        <div class="col-12">
          <div class="settings-card">
            <div class="settings-header">
              <div>
                <h3 class="settings-title">
                  <i class="bi bi-house-gear-fill" style="color:#0d9488;"></i>
                  <span>Pengaturan Kandang & Umur Ayam</span>
                </h3>
                <p class="settings-subtitle">Atur nama kandang, tanggal masuk ayam untuk pelacakan umur ayam di dashboard, serta batas aman sensor</p>
              </div>
            </div>

            <form id="formPengaturanKandang" autocomplete="off">
              <div class="row g-3">
                <!-- Nama Kandang -->
                <div class="col-12 col-md-6 col-lg-4">
                  <div class="form-group-modern">
                    <label for="inputNamaKandang" class="form-label-modern">
                      <i class="bi bi-house-door text-muted"></i> Nama Kandang
                    </label>
                    <input type="text" class="form-control-modern" id="inputNamaKandang" name="nama_kandang" 
                           value="<?= htmlspecialchars($settings['nama_kandang'], ENT_QUOTES, 'UTF-8') ?>" required>
                  </div>
                </div>

                <!-- Tanggal Masuk Ayam -->
                <div class="col-12 col-md-6 col-lg-4">
                  <div class="form-group-modern">
                    <label for="inputTanggalMasuk" class="form-label-modern">
                      <i class="bi bi-calendar-event text-success"></i> Tanggal Masuk Ayam
                    </label>
                    <input type="date" class="form-control-modern" id="inputTanggalMasuk" name="tanggal_masuk_ayam" 
                           value="<?= htmlspecialchars($settings['tanggal_masuk_ayam'], ENT_QUOTES, 'UTF-8') ?>" required>
                    <div class="settings-help-text">Umur ayam saat ini: <strong id="previewUmurAyam"><?= $umurAyamHari ?> Hari</strong> (otomatis tampil di dashboard).</div>
                  </div>
                </div>

                <!-- Ambang Batas Sensor -->
                <div class="col-6 col-md-3 col-lg-2">
                  <div class="form-group-modern">
                    <label for="inputSuhuMin" class="form-label-modern">
                      <i class="bi bi-thermometer-snow text-primary"></i> Suhu Min (&deg;C)
                    </label>
                    <input type="number" step="0.1" class="form-control-modern" id="inputSuhuMin" name="suhu_min" 
                           value="<?= (float) $settings['suhu_min'] ?>" required>
                  </div>
                </div>

                <div class="col-6 col-md-3 col-lg-2">
                  <div class="form-group-modern">
                    <label for="inputSuhuMaks" class="form-label-modern">
                      <i class="bi bi-thermometer-sun text-danger"></i> Suhu Maks (&deg;C)
                    </label>
                    <input type="number" step="0.1" class="form-control-modern" id="inputSuhuMaks" name="suhu_maks" 
                           value="<?= (float) $settings['suhu_maks'] ?>" required>
                  </div>
                </div>

                <div class="col-6 col-md-3 col-lg-2">
                  <div class="form-group-modern">
                    <label for="inputLembapMin" class="form-label-modern">
                      <i class="bi bi-droplet text-warning"></i> Lembap Min (%)
                    </label>
                    <input type="number" step="0.1" class="form-control-modern" id="inputLembapMin" name="kelembaban_min" 
                           value="<?= (float) $settings['kelembaban_min'] ?>" required>
                  </div>
                </div>

                <div class="col-6 col-md-3 col-lg-2">
                  <div class="form-group-modern">
                    <label for="inputLembapMaks" class="form-label-modern">
                      <i class="bi bi-droplet-half text-info"></i> Lembap Maks (%)
                    </label>
                    <input type="number" step="0.1" class="form-control-modern" id="inputLembapMaks" name="kelembaban_maks" 
                           value="<?= (float) $settings['kelembaban_maks'] ?>" required>
                  </div>
                </div>

                <div class="col-6 col-md-3 col-lg-2">
                  <div class="form-group-modern">
                    <label for="inputCahayaMin" class="form-label-modern">
                      <i class="bi bi-brightness-high text-warning"></i> Cahaya Min (lux)
                    </label>
                    <input type="number" step="1" class="form-control-modern" id="inputCahayaMin" name="cahaya_min" 
                           value="<?= (float) $settings['cahaya_min'] ?>" required>
                  </div>
                </div>

                <div class="col-6 col-md-3 col-lg-2">
                  <div class="form-group-modern">
                    <label for="inputAirMin" class="form-label-modern">
                      <i class="bi bi-water text-primary"></i> Level Air Min (%)
                    </label>
                    <input type="number" step="1" class="form-control-modern" id="inputAirMin" name="level_air_min" 
                           value="<?= (float) $settings['level_air_min'] ?>" required>
                  </div>
                </div>
              </div>

              <div class="pt-3">
                <button type="submit" class="btn-settings-save" id="btnSaveKandang">
                  <i class="bi bi-check2-circle"></i> Simpan Pengaturan Kandang
                </button>
              </div>
            </form>
          </div>
        </div>
      </div>
    </main>
  </div>

  <!-- Floating Toast Notification -->
  <div class="dashboard-toast" id="dashToast" role="alert" aria-live="assertive" aria-atomic="true">
    <i class="bi bi-check-circle-fill text-success fs-5" id="toastIcon"></i>
    <span id="toastMsg">Pengaturan berhasil disimpan.</span>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    document.addEventListener("DOMContentLoaded", () => {
      /* ================= Toast Notification ================= */
      const toastEl = document.getElementById("dashToast");
      const toastMsg = document.getElementById("toastMsg");
      const toastIcon = document.getElementById("toastIcon");
      let toastTimer = null;

      function showToast(message, type = "success") {
        if (!toastEl) return;
        toastMsg.textContent = message;
        if (type === "success") {
          toastIcon.className = "bi bi-check-circle-fill text-success fs-5";
        } else if (type === "warning") {
          toastIcon.className = "bi bi-exclamation-triangle-fill text-warning fs-5";
        } else {
          toastIcon.className = "bi bi-info-circle-fill text-info fs-5";
        }
        toastEl.classList.add("show");
        clearTimeout(toastTimer);
        toastTimer = setTimeout(() => {
          toastEl.classList.remove("show");
        }, 3400);
      }

      /* ================= Password Toggle Visibility ================= */
      document.querySelectorAll(".btn-toggle-eye").forEach(btn => {
        btn.addEventListener("click", () => {
          const targetId = btn.getAttribute("data-target");
          const input = document.getElementById(targetId);
          const icon = btn.querySelector("i");
          if (!input || !icon) return;

          if (input.type === "password") {
            input.type = "text";
            icon.className = "bi bi-eye-slash";
          } else {
            input.type = "password";
            icon.className = "bi bi-eye";
          }
        });
      });

      /* ================= Live Preview Umur Ayam ================= */
      const tglMasukInput = document.getElementById("inputTanggalMasuk");
      const previewUmurEl = document.getElementById("previewUmurAyam");

      function updateUmurPreview() {
        if (!tglMasukInput || !previewUmurEl) return;
        const val = tglMasukInput.value;
        if (!val) return;
        const enteredDate = new Date(val + "T00:00:00");
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        const diffDays = Math.max(0, Math.floor((today - enteredDate) / (1000 * 60 * 60 * 24)));
        previewUmurEl.textContent = `${diffDays} Hari`;
      }

      if (tglMasukInput) {
        tglMasukInput.addEventListener("change", updateUmurPreview);
      }

      /* ================= Submit: Pengaturan User (Username & Email) ================= */
      const formProfil = document.getElementById("formProfilUser");
      const btnSaveProfil = document.getElementById("btnSaveProfil");
      if (formProfil) {
        formProfil.addEventListener("submit", async (e) => {
          e.preventDefault();
          const origText = btnSaveProfil.innerHTML;
          btnSaveProfil.disabled = true;
          btnSaveProfil.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Menyimpan...';

          const values = {
            action: "profil",
            username: document.getElementById("inputUsername").value.trim(),
            email: document.getElementById("inputEmail").value.trim()
          };

          try {
            const res = await fetch("../proses/simpan_pengaturan_user.php", {
              method: "POST",
              headers: { "Content-Type": "application/json" },
              body: JSON.stringify(values)
            });
            const data = await res.json();
            if (data && data.success) {
              showToast(data.message || "Pengaturan user berhasil diperbarui!", "success");
              // Update banner UI
              if (data.user) {
                const bannerUsername = document.getElementById("bannerUsernameDisplay");
                const bannerEmail = document.getElementById("bannerEmail");
                if (bannerUsername) bannerUsername.textContent = data.user.username;
                if (bannerEmail) bannerEmail.textContent = data.user.email;
              }
            } else {
              showToast(data.message || "Gagal menyimpan pengaturan user.", "warning");
            }
          } catch (err) {
            console.error(err);
            showToast("Terjadi kendala jaringan saat menyimpan.", "warning");
          } finally {
            btnSaveProfil.disabled = false;
            btnSaveProfil.innerHTML = origText;
          }
        });
      }

      /* ================= Submit: Ganti Password ================= */
      const formPass = document.getElementById("formGantiPassword");
      const btnSavePassword = document.getElementById("btnSavePassword");
      if (formPass) {
        formPass.addEventListener("submit", async (e) => {
          e.preventDefault();
          const pLama = document.getElementById("inputPasswordLama").value;
          const pBaru = document.getElementById("inputPasswordBaru").value;
          const pKonf = document.getElementById("inputKonfirmasiPassword").value;

          if (pBaru.length < 6) {
            showToast("Password baru minimal 6 karakter.", "warning");
            return;
          }

          if (pBaru !== pKonf) {
            showToast("Konfirmasi password baru tidak cocok.", "warning");
            return;
          }

          const origText = btnSavePassword.innerHTML;
          btnSavePassword.disabled = true;
          btnSavePassword.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Memproses...';

          const values = {
            action: "password",
            password_lama: pLama,
            password_baru: pBaru,
            konfirmasi_password: pKonf
          };

          try {
            const res = await fetch("../proses/simpan_pengaturan_user.php", {
              method: "POST",
              headers: { "Content-Type": "application/json" },
              body: JSON.stringify(values)
            });
            const data = await res.json();
            if (data && data.success) {
              showToast(data.message || "Password berhasil diubah!", "success");
              formPass.reset();
            } else {
              showToast(data.message || "Gagal mengubah password.", "warning");
            }
          } catch (err) {
            console.error(err);
            showToast("Terjadi kendala jaringan saat mengubah password.", "warning");
          } finally {
            btnSavePassword.disabled = false;
            btnSavePassword.innerHTML = origText;
          }
        });
      }

      /* ================= Submit: Pengaturan Kandang ================= */
      const formKandang = document.getElementById("formPengaturanKandang");
      const btnSaveKandang = document.getElementById("btnSaveKandang");
      if (formKandang) {
        formKandang.addEventListener("submit", async (e) => {
          e.preventDefault();
          const origText = btnSaveKandang.innerHTML;
          btnSaveKandang.disabled = true;
          btnSaveKandang.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Menyimpan...';

          const values = {
            nama_kandang: document.getElementById("inputNamaKandang").value.trim(),
            tanggal_masuk_ayam: document.getElementById("inputTanggalMasuk").value,
            suhu_min: document.getElementById("inputSuhuMin").value,
            suhu_maks: document.getElementById("inputSuhuMaks").value,
            kelembaban_min: document.getElementById("inputLembapMin").value,
            kelembaban_maks: document.getElementById("inputLembapMaks").value,
            cahaya_min: document.getElementById("inputCahayaMin").value,
            level_air_min: document.getElementById("inputAirMin").value
          };

          try {
            const res = await fetch("../proses/simpan_pengaturan.php", {
              method: "POST",
              headers: { "Content-Type": "application/json" },
              body: JSON.stringify(values)
            });
            const data = await res.json();
            if (data && data.success) {
              showToast(data.message || "Pengaturan kandang berhasil disimpan!", "success");
              updateUmurPreview();
            } else {
              showToast(data.message || "Gagal menyimpan pengaturan kandang.", "warning");
            }
          } catch (err) {
            console.error(err);
            showToast("Terjadi kendala jaringan saat menyimpan.", "warning");
          } finally {
            btnSaveKandang.disabled = false;
            btnSaveKandang.innerHTML = origText;
          }
        });
      }
    });
  </script>
</body>
</html>