<?php
session_start();

$accounts = [
    'CIF' => 'CIF123',
    'SSL' => 'SSL123',
    'SJL' => 'SJL123',
    'SCG' => 'SCG123',
    'SCB' => 'SCB123'
];

$account_mappings = [
    'CIF' => 'Chief Of Store',
    'SSL' => 'Store Senior Leader',
    'SJL' => 'Store Junior Leader',
    'SCG' => 'Store Crew Girl',
    'SCB' => 'Store Crew Boy'
];

if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: index.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $user = $_POST['username'];
    $pass = $_POST['password'];
    
    if (isset($accounts[$user]) && $accounts[$user] === $pass) {
        $_SESSION['username'] = $user;
        header("Location: index.php");
        exit;
    } else {
        $login_error = "Username atau Password salah!";
    }
}

if (!isset($_SESSION['username'])) {
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Stock Opname</title>
    <style>
        body { font-family: sans-serif; background-color: #f8f9fa; display: flex; flex-direction: column; justify-content: center; align-items: center; height: 100vh; margin: 0; }
        .login-logo { width: 180px; height: auto; margin-bottom: 15px; }
        .login-box { background: #fff; padding: 30px; border-radius: 10px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); width: 100%; max-width: 300px; text-align: center; box-sizing: border-box; }
        .login-box h3 { margin-top: 0; color: #2c3e50; }
        .login-box input { width: 100%; padding: 12px; margin: 10px 0; border: 1px solid #ddd; border-radius: 5px; box-sizing: border-box; font-size: 16px; }
        .login-box button { width: 100%; padding: 12px; background: #3498db; color: white; border: none; border-radius: 5px; font-weight: bold; cursor: pointer; font-size: 16px; }
        .error { color: #e74c3c; font-size: 14px; margin-bottom: 10px; }
        .login-footer { margin-top: 15px; font-size: 13px; color: #7f8c8d; font-weight: 500; }
    </style>
</head>
<body>
    <img src="indomaret.PNG" alt="Logo Indomaret" class="login-logo">
    
    <div class="login-box">
        <h3>Login Stock Opname</h3>
        <?php if(isset($login_error)) echo "<div class='error'>$login_error</div>"; ?>
        <form method="POST">
            <input type="text" name="username" placeholder="Username" required>
            <input type="password" name="password" placeholder="Password" required>
            <button type="submit" name="login">LOGIN</button>
        </form>
    </div>

    <div class="login-footer">~ Stock Opname Via HP By M.H.R ~</div>
</body>
</html>
<?php
    exit;
}

$host = 'db.fr-roub1.bengt.wasmernet.com';
$port = '20184';
$dbname = 'stock_opname';
$dbuser = 'user_a2e7c23a';
$dbpass = 'pw_XVc32h58LGUKszLr1XCGg8R8FVDzTAcy';

try {
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4", $dbuser, $dbpass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Koneksi gagal: " . $e->getMessage());
}

$currentUser = $_SESSION['username'];
$displayName = isset($account_mappings[$currentUser]) ? $account_mappings[$currentUser] : $currentUser;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    if ($_POST['action'] === 'save') {
        $plumd = $_POST['plumd'];
        $stok = (int)$_POST['stok'];
        
        $stmt = $pdo->prepare("INSERT INTO stok_fisik_user (plumd, username, stok_fisik) VALUES (:plumd, :username, :stok) ON DUPLICATE KEY UPDATE stok_fisik = :stok");
        $stmt->execute(['plumd' => $plumd, 'username' => $currentUser, 'stok' => $stok]);
        echo json_encode(['success' => true]);
        exit;
    }
    
    if ($_POST['action'] === 'reset') {
        $stmt = $pdo->prepare("DELETE FROM stok_fisik_user WHERE username = :username");
        $stmt->execute(['username' => $currentUser]);
        echo json_encode(['success' => true]);
        exit;
    }
}

$stmt = $pdo->prepare("SELECT plumd, stok_fisik FROM stok_fisik_user WHERE username = :username");
$stmt->execute(['username' => $currentUser]);
$savedData = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>STOCK OPNAME - <?php echo $displayName; ?></title>
    <script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>
    <style>
        :root { --primary: #2c3e50; --accent: #3498db; --danger: #e74c3c; --success: #27ae60; }
        body { font-family: sans-serif; margin: 0; padding-bottom: 60px; background-color: #f8f9fa; display: flex; flex-direction: column; min-height: 100vh; box-sizing: border-box; }
        
        .main-header { background: #fff; height: 50px; padding: 0 15px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); position: sticky; top: 0; z-index: 998; display: flex; align-items: center; justify-content: space-between; }
        .menu-toggle { background: transparent; border: none; padding: 0; cursor: pointer; display: flex; align-items: center; justify-content: center; }
        .menu-toggle svg { fill: var(--primary); width: 24px; height: 24px; }
        .header-title-container { position: absolute; left: 50%; transform: translateX(-50%); text-align: center; pointer-events: none; }
        .main-header h2 { margin: 0; font-size: 16px; color: var(--primary); font-weight: bold; letter-spacing: 0.5px; }
        
        .sidebar { position: fixed; top: 0; left: -280px; width: 280px; height: 100%; background: #1e2b37; z-index: 1000; transition: left 0.3s cubic-bezier(0.4, 0, 0.2, 1); display: flex; flex-direction: column; }
        .sidebar.open { left: 0; }
        .sidebar-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 999; display: none; backdrop-filter: blur(2px); }
        .sidebar-overlay.show { display: block; }
        
        .sidebar-header { padding: 24px 20px; border-bottom: 1px solid rgba(255,255,255,0.08); display: flex; justify-content: space-between; align-items: center; background: #17212a; }
        .user-info { display: flex; align-items: center; gap: 12px; color: #fff; }
        .user-info svg { fill: var(--accent); width: 26px; height: 26px; }
        .user-info span { font-weight: 600; font-size: 15px; letter-spacing: 0.3px; }
        .close-sidebar { background: transparent; border: none; cursor: pointer; display: flex; align-items: center; padding: 4px; border-radius: 50%; transition: background 0.2s; }
        .close-sidebar:hover { background: rgba(255,255,255,0.08); }
        .close-sidebar svg { fill: #95a5a6; width: 20px; height: 20px; }
        
        .sidebar-menu { flex: 1; padding: 20px 0; overflow-y: auto; }
        .tab-btn { width: 90%; text-align: left; padding: 12px 15px; margin: 6px auto; cursor: pointer; background: transparent; border: none; font-size: 13px; font-weight: 600; color: #a0aec0; display: flex; align-items: center; gap: 10px; border-radius: 8px; transition: all 0.2s; box-sizing: border-box; }
        .tab-btn svg { width: 18px; height: 18px; fill: currentColor; flex-shrink: 0; }
        .tab-btn:hover:not(:disabled) { color: #fff; background: rgba(255,255,255,0.04); }
        .tab-btn.active { color: #fff; background: var(--accent); box-shadow: 0 4px 12px rgba(52, 152, 219, 0.3); }
        .tab-btn:disabled { opacity: 0.25; cursor: not-allowed; }
        
        .sidebar-footer { padding: 15px; border-top: 1px solid rgba(255,255,255,0.08); background: #17212a; text-align: center; }
        .sidebar-time { font-size: 12px; color: #a0aec0; font-weight: 500; line-height: 1.4; }
        
        .btn-header-logout { background: var(--danger); color: white; text-decoration: none; padding: 5px 10px; border-radius: 4px; font-weight: bold; font-size: 11px; transition: background 0.2s; }
        .btn-header-logout:hover { background: #c0392b; }
        
        .content-container { padding: 15px; flex: 1; }
        
        .main-footer { background: #fff; height: 50px; box-shadow: 0 -2px 8px rgba(0,0,0,0.05); position: fixed; bottom: 0; left: 0; width: 100%; z-index: 997; display: flex; align-items: center; justify-content: center; border-top: 1px solid #eee; }
        .footer-text { font-size: 13px; color: var(--primary); font-weight: 600; letter-spacing: 0.5px; }
        
        #loader { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(255,255,255,0.95); z-index: 99999; }
        .loader-content { position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); text-align: center; font-weight: bold; font-size: 18px; color: var(--primary); }
        .spinner { border: 6px solid #f3f3f3; border-top: 6px solid var(--accent); border-radius: 50%; width: 50px; height: 50px; animation: spin 1s linear infinite; margin: 0 auto 15px; }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
        
        .tab-content { display: none; opacity: 0; transform: translateY(15px); transition: opacity 0.3s ease-out, transform 0.3s ease-out; }
        .tab-content.active { display: block; }
        .tab-content.fade-in { opacity: 1; transform: translateY(0); }
        
        .filter-section { background: #fff; padding: 15px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); margin-bottom: 10px; }
        input, select, textarea { padding: 12px; border: 1px solid #ddd; border-radius: 5px; width: 100%; box-sizing: border-box; margin-bottom: 10px; font-size: 16px; }
        button { padding: 12px; border: none; border-radius: 5px; cursor: pointer; color: white; font-weight: bold; transition: all 0.3s; }
        .btn-cari { background-color: var(--accent); width: 100%; }
        .btn-download { background-color: var(--primary); width: 100%; margin-bottom: 12px; }
        .btn-upload { background-color: var(--success); width: 100%; }
        
        #popup { display: none; position: fixed; top: 25%; left: 50%; transform: translate(-50%, -20px) scale(0.9); opacity: 0; background: white; padding: 20px; border-radius: 15px; box-shadow: 0 10px 30px rgba(0,0,0,0.2); z-index: 10000; width: 85%; max-width: 400px; transition: opacity 0.3s cubic-bezier(0.34, 1.56, 0.64, 1), transform 0.3s cubic-bezier(0.34, 1.56, 0.64, 1); }
        #popup.show { display: block; }
        #popup.pop-in { opacity: 1; transform: translate(-50%, 0) scale(1); }
        
        .popup-action-group { display: flex; gap: 10px; margin-bottom: 5px; }
        .btn-kurang { background-color: var(--danger); width: 30%; }
        .btn-tambah { background-color: var(--accent); width: 70%; }
        
        .table-container { overflow: auto; background: #fff; padding: 10px; border-radius: 8px; margin-top: 10px; max-height: 450px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); }
        table { width: 100%; border-collapse: collapse; font-size: 11px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background: var(--primary); color: white; position: sticky; top: 0; }
        .selisih-title { margin-top: 20px; font-weight: bold; color: var(--primary); padding-left: 5px; }
        .status-info { font-size: 12px; color: #555; margin-top: 10px; text-align: center; font-style: italic; }
    </style>
</head>
<body>

    <div id="loader"><div class="loader-content"><div class="spinner"></div>Sedang memuat data...</div></div>

    <div id="sidebarOverlay" class="sidebar-overlay" onclick="toggleSidebar()"></div>

    <header class="main-header">
        <button class="menu-toggle" onclick="toggleSidebar()">
            <svg viewBox="0 0 24 24"><path d="M3 18h18v-2H3v2zm0-5h18v-2H3v2zm0-7v2h18V6H3z"/></svg>
        </button>
        <div class="header-title-container">
            <h2>SO VIA HP</h2>
        </div>
        <a href="?logout=true" class="btn-header-logout">Logout</a>
    </header>

    <div id="sidebar" class="sidebar">
        <div class="sidebar-header">
            <div class="user-info">
                <svg viewBox="0 0 24 24">
                    <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 4c1.93 0 3.5 1.57 3.5 3.5S13.93 13 12 13s-3.5-1.57-3.5-3.5S10.07 6 12 6zm0 14c-2.03 0-4.43-.82-6.14-2.88C7.55 15.8 9.68 15 12 15s4.45.8 6.14 2.12C16.43 19.18 14.03 20 12 20z"/>
                </svg>
                <span><?php echo $displayName; ?></span>
            </div>
            <button class="close-sidebar" onclick="toggleSidebar()">
                <svg viewBox="0 0 24 24"><path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/></svg>
            </button>
        </div>
        
        <div class="sidebar-menu">
            <button class="tab-btn active" id="btn0" onclick="switchTab(0)">
                <svg viewBox="0 0 24 24"><path d="M14 2H6c-1.1 0-1.99.9-1.99 2L4 20c0 1.1.89 2 1.99 2H18c1.1 0 2-.9 2-2V8l-6-6zm2 16H8v-2h8v2zm0-4H8v-2h8v2zm-3-5V3.5L18.5 9H13z"/></svg>
                Input Data Stok
            </button>
            <button class="tab-btn" id="btn1" onclick="switchTab(1)">
                <svg viewBox="0 0 24 24"><path d="M10 18h4v-2h-4v2zM3 6v2h18V6H3zm3 7h12v-2H6v2z"/></svg>
                Pilih Modis SO
            </button>
            <button class="tab-btn" id="btn2" onclick="switchTab(2)" disabled>
                <svg viewBox="0 0 24 24"><path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/></svg>
                Input Stok SO
            </button>
            <button class="tab-btn" id="btn3" onclick="switchTab(3)" disabled>
                <svg viewBox="0 0 24 24"><path d="M4 14h4v-4H4v4zm0 5h4v-4H4v4zM4 9h4V5H4v4zm5 5h12v-4H9v4zm0 5h12v-4H9v4zM9 5v4h12V5H9z"/></svg>
                Daftar Listing Modis
            </button>
            <button class="tab-btn" id="btn4" onclick="switchTab(4)" disabled>
                <svg viewBox="0 0 24 24"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-2 10h-4v4h-2v-4H7v-2h4V7h2v4h4v2z"/></svg>
                Hitung Selisih Stok
            </button>
        </div>
        
        <div class="sidebar-footer">
            <div id="sidebarTime" class="sidebar-time"></div>
        </div>
    </div>

    <div class="content-container">
        <div id="tab0" class="tab-content active fade-in">
            <div class="filter-section">
                <h3 style="margin-top:0; color: var(--primary); text-align:center;">Sambung ke Wifi PC kasir lalu buka ( <u>http://192.168.137.1:3000/so_hp.html</u> ) dibrowser hp untuk download data stok</h3>
                <p style="font-size:12px; color:#666; text-align:center; margin-bottom:15px;"></p>
                
                <button style="display: none;" class="btn-download" onclick="downloadDataSO()">1. Download Data Server (JSON)</button>
                
                <div style="border-top: 1px dashed #ccc; margin: 15px 0;"></div>
                
                <input type="file" id="fileJsonInput" accept=".json" style="display:none;" onchange="handleOfflineJson(this)">
                <button class="btn-upload" onclick="document.getElementById('fileJsonInput').click()">- Input Data Stok disini -</button>
                
                <div id="statusData" class="status-info">Silakan input file .json yg sudah di download</div>
            </div>
        </div>

        <div id="tab1" class="tab-content">
            <div class="filter-section">
                <label>Pilih Modis</label> <select id="rakSelect" onchange="checkFilter()"><option value="">Pilih...</option></select>
                <label>Dari Shelfing</label> <select id="shelfStart" onchange="checkFilter()"><option value="">Pilih...</option></select>
                <label>Sampai Shelfing</label> <select id="shelfEnd" onchange="checkFilter()"><option value="">Pilih...</option></select>
                <button class="btn-cari" onclick="confirmFilter()">Simpan & Lanjut</button>
            </div>
        </div>

        <div id="tab2" class="tab-content">
            <div class="filter-section">
                <div style="display: flex; gap: 5px; margin-bottom: 10px;">
                    <input type="text" id="searchInput" inputmode="numeric" pattern="[0-8]*" oninput="this.value = this.value.replace(/[^0-9]/g, '')" placeholder="Ketik PLU / Barcode ..." style="margin-bottom: 0;">
                    <button onclick="toggleScanner()" style="background: var(--primary); padding: 12px; border: none; border-radius: 5px; cursor: pointer; display: flex; align-items: center; justify-content: center; width: auto;">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 7V5a2 2 0 0 1 2-2h2"></path><path d="M17 3h2a2 2 0 0 1 2 2v2"></path><path d="M21 17v2a2 2 0 0 1-2 2h-2"></path><path d="M7 21H5a2 2 0 0 1-2-2v-2"></path><rect x="7" y="7" width="10" height="10"></rect></svg>
                    </button>
                </div>
                <button class="btn-cari" onclick="searchAction()">Cari</button>
                <div id="reader" style="display: none; margin-top: 10px;"></div>
            </div>
            <div id="searchResultContainer" class="table-container" style="display:none;">
                <table><thead><tr><th>Deskripsi</th><th>Input</th></tr></thead><tbody id="searchResultTable"></tbody></table>
            </div>
        </div>

        <div id="tab3" class="tab-content">
            <div class="table-container">
                <table>
                    <thead><tr><th>Rak</th><th>PLU</th><th>Deskripsi</th><th>Harga</th><th>Stok LPP</th><th>Stok Fisik</th></tr></thead>
                    <tbody id="tableInput"></tbody>
                </table>
            </div>
        </div>

        <div id="tab4" class="tab-content">
            <div class="filter-section" style="display: flex; gap: 5px;">
                <button class="btn-cari" onclick="calculateSelisih()">Proses</button>
                <button class="btn-cari" style="background-color: #27ae60;" onclick="copyAllResults()">Salin Hasil</button>
                <button class="btn-cari" style="background-color: var(--danger);" onclick="resetUserProgress()">Reset Hasil</button>
            </div>
            <div id="hasilProses"></div>
        </div>
    </div>

    <footer class="main-footer">
        <div class="footer-text">~ &copy; m.h.r ~</div>
    </footer>

    <div id="popup">
        <p id="popText" style="margin-top:0; font-weight:bold;"></p>
        <input type="text" id="stokInput" inputmode="numeric" pattern="[0-8]*" oninput="this.value = this.value.replace(/[^0-9]/g, '')" placeholder="Input Stok SO...">
        <div class="popup-action-group">
            <button class="btn-kurang" onclick="kurangStok()">Kurang</button>
            <button class="btn-tambah" onclick="simpanStok()">Tambah</button>
        </div>
        <button style="background: #95a5a6; width: 100%; margin-top: 5px; padding: 12px; border:none; border-radius:5px; color:white; font-weight:bold;" onclick="closePopup()">Tutup</button>
    </div>

    <script>
        let fullData = [], dataInputan = new Map(Object.entries(<?php echo json_encode($savedData); ?>)), currentResults = { plus: [], minus: [], belum: [] };
        let html5QrcodeScanner;

        function updateRealtimeTime() {
            const days = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
            const months = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
            
            const now = new Date();
            const dayName = days[now.getDay()];
            const date = now.getDate();
            const monthName = months[now.getMonth()];
            const year = now.getFullYear();
            
            const hours = String(now.getHours()).padStart(2, '0');
            const minutes = String(now.getMinutes()).padStart(2, '0');
            const seconds = String(now.getSeconds()).padStart(2, '0');
            
            document.getElementById('sidebarTime').innerHTML = `${dayName}, ${date} ${monthName} ${year}<br><b>${hours}:${minutes}:${seconds} WIB</b>`;
        }
        setInterval(updateRealtimeTime, 1000);
        updateRealtimeTime();

        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('open');
            document.getElementById('sidebarOverlay').classList.toggle('show');
        }

        function processLoadedData(rawData) {
            fullData = rawData.sort((a, b) => a.NAMA_RAK.localeCompare(b.NAMA_RAK) || parseInt(a.NOSHELF) - parseInt(b.NOSHELF) || parseInt(a.KIRIKANAN) - parseInt(b.KIRIKANAN));
            
            document.getElementById('rakSelect').innerHTML = '<option value="">Pilih...</option>';
            document.getElementById('shelfStart').innerHTML = '<option value="">Pilih...</option>';
            document.getElementById('shelfEnd').innerHTML = '<option value="">Pilih...</option>';
            
            populateFilters();
            checkFilter();
        }

        function handleOfflineJson(input) {
            const file = input.files[0];
            if (!file) return;

            const reader = new FileReader();
            const loader = document.getElementById('loader');
            const statusData = document.getElementById('statusData');
            
            loader.style.display = 'block';
            reader.onload = function(e) {
                try {
                    const result = JSON.parse(e.target.result);
                    if (result && result.data) {
                        processLoadedData(result.data);
                        statusData.innerText = `Menggunakan : ${file.name} (${result.data.length} item loaded)`;
                        statusData.style.color = "var(--success)";
                        alert("Data offline berhasil dipasang! Silakan lanjut ke menu PILIH.");
                        switchTab(1); 
                    } else {
                        alert("Format file JSON salah atau field 'data' tidak ditemukan.");
                    }
                } catch (err) {
                    alert("Gagal membaca file. Pastikan file berformat JSON valid!");
                }
                loader.style.display = 'none';
                input.value = ""; 
            };
            reader.readAsText(file);
        }

        function switchTab(idx) {
            const tabs = document.querySelectorAll('.tab-content');
            const btns = document.querySelectorAll('.sidebar-menu .tab-btn');
            
            tabs.forEach((t, i) => {
                if (i === idx) {
                    t.classList.add('active');
                    setTimeout(() => t.classList.add('fade-in'), 10);
                } else {
                    t.classList.remove('fade-in');
                    t.classList.remove('active');
                }
            });
            
            btns.forEach((b, i) => b.classList.toggle('active', i === idx));
            if(idx === 3) renderTable();
            
            const sidebar = document.getElementById('sidebar');
            if(sidebar.classList.contains('open')) {
                toggleSidebar();
            }
        }

        function checkFilter() {
            const isSelected = document.getElementById('rakSelect').value !== "";
            document.getElementById('btn2').disabled = document.getElementById('btn3').disabled = document.getElementById('btn4').disabled = !isSelected;
        }

        function confirmFilter() { switchTab(2); }

        function getFilteredData() {
            const rak = document.getElementById('rakSelect').value;
            const start = parseInt(document.getElementById('shelfStart').value);
            const end = parseInt(document.getElementById('shelfEnd').value);
            return fullData.filter(i => (rak === "" || i.NAMA_RAK === rak) && (isNaN(start) || parseInt(i.NOSHELF) >= start) && (isNaN(end) || parseInt(i.NOSHELF) <= end));
        }

        function populateFilters() {
            if (fullData.length === 0) return;
            const raks = [...new Set(fullData.map(i => i.NAMA_RAK))].sort();
            const shelves = [...new Set(fullData.map(i => parseInt(i.NOSHELF)))].filter(n => !isNaN(n)).sort((a,b) => a-b);
            raks.forEach(r => document.getElementById('rakSelect').innerHTML += `<option value="${r}">${r}</option>`);
            shelves.forEach(s => {
                document.getElementById('shelfStart').innerHTML += `<option value="${s}">${s}</option>`;
                document.getElementById('shelfEnd').innerHTML += `<option value="${s}">${s}</option>`;
            });
        }

        function toggleScanner() {
            const reader = document.getElementById('reader');
            if (reader.style.display === 'none' || reader.style.display === '') {
                reader.style.display = 'block';
                if (!html5QrcodeScanner) {
                    html5QrcodeScanner = new Html5QrcodeScanner("reader", { 
                        fps: 10, 
                        qrbox: {width: 250, height: 250},
                        rememberLastUsedCamera: true,
                        supportedScanTypes: [Html5QrcodeScanType.SCAN_TYPE_CAMERA]
                    }, false);
                }
                html5QrcodeScanner.render(onScanSuccess, onScanFailure);
                
                setTimeout(() => {
                    const selectCam = document.getElementById('html5-qrcode-select-camera');
                    const btnStart = document.getElementById('html5-qrcode-button-camera-start');
                    
                    if (selectCam && selectCam.options.length > 0) {
                        for (let i = 0; i < selectCam.options.length; i++) {
                            const optText = selectCam.options[i].text.toLowerCase();
                            if (optText.includes('back') || optText.includes('belakang') || optText.includes('environment') || optText.includes('rear')) {
                                selectCam.selectedIndex = i;
                                break;
                            }
                        }
                    }
                    if (btnStart) {
                        btnStart.click();
                    }
                }, 100);
            } else {
                reader.style.display = 'none';
                if (html5QrcodeScanner) {
                    html5QrcodeScanner.clear();
                }
            }
        }

        function onScanSuccess(decodedText, decodedResult) {
            let numericText = decodedText.replace(/[^0-9]/g, '');
            if (numericText) {
                document.getElementById('searchInput').value = numericText;
                toggleScanner();
                searchAction();
            } else {
                alert("Barcode tidak valid. Hanya angka yang diperbolehkan.");
            }
        }

        function onScanFailure(error) {
        }

        function searchAction() {
            const search = document.getElementById('searchInput').value.trim();
            if (!search) {
                alert("Masukkan angka pencarian terlebih dahulu.");
                return;
            }
            const filtered = getFilteredData().filter(i => i.PLUMD.includes(search) || i.BARCD.includes(search));
            const uniqueResults = []; const seen = new Set();
            filtered.forEach(i => { if(!seen.has(i.PLUMD)) { uniqueResults.push(i); seen.add(i.PLUMD); } });

            if(uniqueResults.length === 0) alert("Data tidak ditemukan.");
            else if(uniqueResults.length === 1) openPopup(uniqueResults[0]);
            else {
                document.getElementById('searchResultContainer').style.display = 'block';
                const tbody = document.getElementById('searchResultTable');
                tbody.innerHTML = ""; 
                
                uniqueResults.forEach(item => {
                    const tr = document.createElement('tr');
                    
                    const tdDesc = document.createElement('td');
                    tdDesc.innerText = item.DESC2;
                    
                    const tdBtn = document.createElement('td');
                    const btn = document.createElement('button');
                    btn.style.background = "var(--accent)";
                    btn.style.padding = "5px 10px";
                    btn.innerText = "Input";
                    
                    btn.addEventListener('click', () => openPopup(item));
                    
                    tdBtn.appendChild(btn);
                    tr.appendChild(tdDesc);
                    tr.appendChild(tdBtn);
                    tbody.appendChild(tr);
                });
            }
        }

        function openPopup(item) { 
            window.currentItem = item; 
            document.getElementById('popText').innerText = "Input Stok SO : " + item.DESC2; 
            
            const pop = document.getElementById('popup');
            pop.classList.add('show'); 
            setTimeout(() => pop.classList.add('pop-in'), 10);
        }
        
        function closePopup() { 
            const pop = document.getElementById('popup');
            pop.classList.remove('pop-in');
            setTimeout(() => pop.classList.remove('show'), 300);
        }

        async function updateDbStok(plumd, newStok) {
            try {
                const formData = new FormData();
                formData.append('action', 'save');
                formData.append('plumd', plumd);
                formData.append('stok', newStok);

                await fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                });
            } catch (e) {}
        }

        async function simpanStok() { 
            let val = parseInt(document.getElementById('stokInput').value) || 0;
            let currentStok = parseInt(dataInputan.get(window.currentItem.PLUMD)) || 0;
            let totalStok = currentStok + val;
            
            dataInputan.set(window.currentItem.PLUMD, totalStok); 
            await updateDbStok(window.currentItem.PLUMD, totalStok);
            resetForm();
        }

        async function kurangStok() {
            let currentStok = parseInt(dataInputan.get(window.currentItem.PLUMD)) || 0;
            let inputMinus = parseInt(document.getElementById('stokInput').value) || 0;
            let totalStok = currentStok - inputMinus;
            
            dataInputan.set(window.currentItem.PLUMD, totalStok);
            await updateDbStok(window.currentItem.PLUMD, totalStok);
            resetForm();
        }

        function resetForm() {
            document.getElementById('stokInput').value = ""; 
            document.getElementById('searchInput').value = ""; 
            document.getElementById('searchResultContainer').style.display = 'none'; 
            closePopup();
        }

        function renderTable() {
            const filtered = getFilteredData();
            const uniqueMap = new Map();
            filtered.forEach(i => { if(!uniqueMap.has(i.PLUMD)) uniqueMap.set(i.PLUMD, i); });
            
            const sortedData = Array.from(uniqueMap.values()).sort((a, b) => {
                return a.NAMA_RAK.localeCompare(b.NAMA_RAK) || 
                       (parseInt(a.NOSHELF) - parseInt(b.NOSHELF)) || 
                       (parseInt(a.KIRIKANAN) - parseInt(b.KIRIKANAN));
            });

            document.getElementById('tableInput').innerHTML = sortedData.map(i => `<tr><td>${i.NAMA_RAK.substring(0,6)}-${i.NOSHELF}-${i.KIRIKANAN}</td><td>${i.PLUMD}</td><td>${i.DESC2}</td><td>${parseFloat(i.PRICE).toLocaleString('id-ID')}</td><td>${i.QTY}</td><td>${dataInputan.get(i.PLUMD) ?? ""}</td></tr>`).join('');
        }

        function calculateSelisih() {
            const container = document.getElementById('hasilProses'); container.innerHTML = "";
            currentResults = { plus: [], minus: [], belum: [] };
            const uniqueMap = new Map();
            getFilteredData().forEach(i => { if(!uniqueMap.has(i.PLUMD)) uniqueMap.set(i.PLUMD, i); });

            uniqueMap.forEach((item, plumd) => {
                let qtySys = parseInt(item.QTY) || 0;
                if(!dataInputan.has(plumd)) { if(qtySys !== 0) currentResults.belum.push(item); }
                else {
                    let selisih = parseInt(dataInputan.get(plumd)) - qtySys;
                    if(selisih > 0) currentResults.plus.push({...item, selisih});
                    else if(selisih < 0) currentResults.minus.push({...item, selisih});
                }
            });
            container.innerHTML += createTable("DAFTAR PLUS (+)", currentResults.plus, true, false);
            container.innerHTML += createTable("DAFTAR MINUS (-)", currentResults.minus, true, false);
            container.innerHTML += createTable("DAFTAR BELUM INPUT STOK", currentResults.belum, false, true);
        }

        function createTable(title, data, isSelisih, isBelum) {
            if(data.length === 0) return "";
            return `<div class="selisih-title">${title}</div><div class="table-container"><table><thead><tr><th>PLU</th><th>Deskripsi</th><th>Harga</th>${isBelum ? '<th>Stok LPP</th>' : ''}${isSelisih ? '<th>Selisih</th>' : ''}</tr></thead><tbody>${data.map(i => `<tr><td>${i.PLUMD}</td><td>${i.DESC2}</td><td>${parseInt(i.PRICE).toLocaleString()}</td>${isBelum ? `<td>${i.QTY}</td>` : ''}${isSelisih ? `<td>${i.selisih > 0 ? '+' : ''}${i.selisih}</td>` : ''}</tr>`).join('')}</tbody></table></div>`;
        }

        function copyAllResults() {
            let text = "HASIL STOCK OPNAME\n";
            
            if(currentResults.plus.length > 0) {
                text += `\n--- DAFTAR PLUS (+) ---\n`;
                text += `PLU | Deskripsi | Harga | Selisih\n`;
                currentResults.plus.forEach(i => {
                    text += `${i.PLUMD} | ${i.DESC2} | ${parseInt(i.PRICE).toLocaleString()} | +${i.selisih}\n`;
                });
            }
            
            if(currentResults.minus.length > 0) {
                text += `\n--- DAFTAR MINUS (-) ---\n`;
                text += `PLU | Deskripsi | Harga | Selisih\n`;
                currentResults.minus.forEach(i => {
                    text += `${i.PLUMD} | ${i.DESC2} | ${parseInt(i.PRICE).toLocaleString()} | ${i.selisih}\n`;
                });
            }
            
            if(currentResults.belum.length > 0) {
                text += `\n--- DAFTAR BELUM INPUT STOK ---\n`;
                text += `PLU | Deskripsi | Harga | Stok LPP\n`;
                currentResults.belum.forEach(i => {
                    text += `${i.PLUMD} | ${i.DESC2} | ${parseInt(i.PRICE).toLocaleString()} | ${i.QTY}\n`;
                });
            }
            
            const textArea = document.createElement("textarea");
            textArea.value = text;
            textArea.style.position = "fixed";
            textArea.style.left = "-9999px";
            document.body.appendChild(textArea);
            textArea.select();
            document.execCommand('copy');
            document.body.removeChild(textArea);
            alert("Semua data (Plus, Minus, & Belum Input) berhasil disalin sesuai format tabel!");
        }

        async function resetUserProgress() {
            if (confirm("Apakah Anda yakin ingin menghapus SEMUA hasil progres inputan stok untuk akun Anda? Tindakan ini tidak dapat dibatalkan.")) {
                try {
                    const formData = new FormData();
                    formData.append('action', 'reset');

                    const response = await fetch(window.location.href, {
                        method: 'POST',
                        body: formData
                    });
                    const result = await response.json();
                    
                    if (result.success) {
                        dataInputan.clear();
                        document.getElementById('hasilProses').innerHTML = "";
                        alert("Semua progres inputan Anda berhasil direset!");
                        switchTab(1);
                    } else {
                        alert("Gagal meriset data. Coba beberapa saat lagi.");
                    }
                } catch (e) {
                    alert("Terjadi kesalahan koneksi saat meriset data.");
                }
            }
        }
    </script>
</body>
</html>
