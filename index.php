<?php
session_start();

$accounts = [
    'admin',
    'CIF',
    'SSL',
    'SJL',
    'SCB',
    'SCG'
];

if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: index.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['username'])) {
    $user = $_POST['username'];
    
    if (in_array($user, $accounts)) {
        $_SESSION['username'] = $user;
        header("Location: index.php");
        exit;
    } else {
        $login_error = "NIK tidak terdaftar !";
    }
}

if (!isset($_SESSION['username'])) {
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Akun SO</title>
    <style>
        body { font-family: sans-serif; background-color: #f8f9fa; display: flex; flex-direction: column; justify-content: center; align-items: center; height: 100vh; margin: 0; }
        .login-logo { width: 180px; height: auto; margin-bottom: 15px; }
        .login-box { background: #fff; padding: 25px; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); width: 100%; max-width: 320px; text-align: center; box-sizing: border-box; }
        .login-box h3 { margin-top: 0; color: #2c3e50; margin-bottom: 20px; font-size: 18px; }
        .account-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px; }
        .btn-account { padding: 14px 10px; background: #ffffff; color: #2c3e50; border: 2px solid #e0e6ed; border-radius: 8px; font-weight: bold; cursor: pointer; font-size: 15px; transition: all 0.2s ease; display: flex; align-items: center; justify-content: center; box-shadow: 0 2px 4px rgba(0,0,0,0.02); }
        .btn-account:hover { background: #3498db; color: white; border-color: #3498db; transform: translateY(-2px); box-shadow: 0 4px 8px rgba(52, 152, 219, 0.25); }
        .btn-account.admin { background: #fdf2f2; color: #e74c3c; border-color: #f8d7da; }
        .btn-account.admin:hover { background: #e74c3c; color: white; border-color: #e74c3c; box-shadow: 0 4px 8px rgba(231, 76, 60, 0.25); }
        .error { color: #e74c3c; font-size: 14px; margin-bottom: 10px; }
        .login-footer { margin-top: 15px; font-size: 13px; color: #7f8c8d; font-weight: 500; }
    </style>
</head>
<body>
    <img src="indomaret.PNG" alt="Logo Indomaret" class="login-logo">
    
    <div class="login-box">
        <h3>Pilih Akun Stock Opname</h3>
        <?php if(isset($login_error)) echo "<div class='error'>$login_error</div>"; ?>
        <form method="POST" class="account-grid">
            <?php foreach ($accounts as $acc): ?>
                <button type="submit" name="username" value="<?php echo htmlspecialchars($acc); ?>" class="btn-account <?php echo $acc === 'admin' ? 'admin' : ''; ?>">
                    <?php echo htmlspecialchars($acc); ?>
                </button>
            <?php endforeach; ?>
        </form>
    </div>

    <div class="login-footer">~ m.h.r ~</div>
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
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4", $dbuser, $dbpass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_TIMEOUT => 5
    ]);
} catch (PDOException $e) {
    die("Koneksi gagal: " . $e->getMessage());
}

$currentUser = $_SESSION['username'];
$displayName = $currentUser;
$isAdmin = ($currentUser === 'admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    if ($_POST['action'] === 'save' && !$isAdmin) {
        $plumd = $_POST['plumd'];
        $stok = (int)$_POST['stok'];
        
        $stmt = $pdo->prepare("INSERT INTO stok_fisik_user (plumd, username, stok_fisik) VALUES (:plumd, :username, :stok) ON DUPLICATE KEY UPDATE stok_fisik = :stok");
        $stmt->execute(['plumd' => $plumd, 'username' => $currentUser, 'stok' => $stok]);
        echo json_encode(['success' => true]);
        exit;
    }
    
    if ($_POST['action'] === 'reset' && !$isAdmin) {
        $stmt = $pdo->prepare("DELETE FROM stok_fisik_user WHERE username = :username");
        $stmt->execute(['username' => $currentUser]);
        echo json_encode(['success' => true]);
        exit;
    }

    if ($_POST['action'] === 'upload_hasil_selisih' && !$isAdmin) {
        $itemsRaw = $_POST['items'] ?? '[]';
        $itemsArray = json_decode($itemsRaw, true);
        
        if (is_array($itemsArray) && count($itemsArray) > 0) {
            $pdo->beginTransaction();
            try {
                $stmt = $pdo->prepare("INSERT INTO hasil_selisih_so (username, modis, plumd, deskripsi, harga, selisih) VALUES (:username, :modis, :plumd, :deskripsi, :harga, :selisih)");
                foreach ($itemsArray as $item) {
                    $stmt->execute([
                        'username' => $currentUser,
                        'modis' => $item['modis'],
                        'plumd' => $item['plumd'],
                        'deskripsi' => $item['deskripsi'],
                        'harga' => (float)$item['harga'],
                        'selisih' => (int)$item['selisih']
                    ]);
                }
                $pdo->commit();
                echo json_encode(['success' => true, 'message' => count($itemsArray) . ' data hasil selisih berhasil diupload ke database.']);
            } catch (Exception $e) {
                $pdo->rollBack();
                echo json_encode(['success' => false, 'message' => 'Gagal mengupload data: ' . $e->getMessage()]);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Tidak ada data hasil untuk diupload.']);
        }
        exit;
    }

    if ($_POST['action'] === 'get_database_results') {
        try {
            $stmt = $pdo->query("SELECT h1.* FROM hasil_selisih_so h1 INNER JOIN (SELECT username, modis, plumd, MAX(id) as max_id FROM hasil_selisih_so GROUP BY username, modis, plumd) h2 ON h1.id = h2.max_id ORDER BY h1.username ASC, h1.modis ASC, h1.created_at DESC");
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'data' => $rows]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }

    if ($_POST['action'] === 'admin_get_stok_fisik' && $isAdmin) {
        try {
            $stmt = $pdo->query("SELECT username, plumd, stok_fisik FROM stok_fisik_user ORDER BY username ASC");
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'data' => $rows]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }

    if ($_POST['action'] === 'admin_truncate_table' && $isAdmin) {
        $table = $_POST['table'];
        if (in_array($table, ['stok_fisik_user', 'hasil_selisih_so'])) {
            try {
                $pdo->exec("TRUNCATE TABLE $table");
                echo json_encode(['success' => true, 'message' => "Seluruh isi tabel $table berhasil dikosongkan!"]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Gagal meriset tabel: ' . $e->getMessage()]);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Tabel tidak valid.']);
        }
        exit;
    }
}

$savedData = [];
if (!$isAdmin) {
    $stmt = $pdo->prepare("SELECT plumd, stok_fisik FROM stok_fisik_user WHERE username = :username");
    $stmt->execute(['username' => $currentUser]);
    $savedData = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
}
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
        
        .btn-header-external { display: flex; align-items: center; justify-content: center; background: transparent; border: none; color: var(--primary); padding: 5px; border-radius: 4px; transition: background 0.2s, color 0.2s; cursor: pointer; text-decoration: none; }
        .btn-header-external:hover { background: #f0f0f0; color: var(--accent); }
        .btn-header-external svg { width: 20px; height: 20px; fill: currentColor; }
        
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
        
        .btn-sidebar-logout { display: flex; align-items: center; justify-content: center; gap: 8px; width: 90%; margin: 0 auto 12px auto; padding: 10px 15px; background: var(--danger); color: white; text-decoration: none; border-radius: 6px; font-weight: bold; font-size: 13px; transition: background 0.2s; box-sizing: border-box; }
        .btn-sidebar-logout:hover { background: #c0392b; }
        .btn-sidebar-logout svg { width: 16px; height: 16px; fill: currentColor; }
        
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
        
        .last-item-box { background: #eef7ed; border: 1px solid #c3e6cb; border-radius: 10px; padding: 12px 15px; margin-bottom: 10px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); }
        .last-item-title { font-size: 11px; font-weight: bold; color: #27ae60; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 4px; display: flex; align-items: center; gap: 5px; }
        .last-item-desc { font-size: 14px; font-weight: bold; color: var(--primary); margin-bottom: 2px; word-break: break-word; }
        .last-item-detail { font-size: 12px; color: #555; display: flex; justify-content: space-between; align-items: center; }
        .last-item-stok { font-weight: bold; color: #27ae60; background: #d4edda; padding: 2px 8px; border-radius: 4px; font-size: 13px; }

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

        #queryPopup { display: none; position: fixed; top: 25%; left: 50%; transform: translate(-50%, -20px) scale(0.9); opacity: 0; background: white; padding: 20px; border-radius: 15px; box-shadow: 0 10px 30px rgba(0,0,0,0.2); z-index: 10001; width: 85%; max-width: 400px; transition: opacity 0.3s ease, transform 0.3s ease; }
        #queryPopup.show { display: block; }
        #queryPopup.pop-in { opacity: 1; transform: translate(-50%, 0) scale(1); }
        .btn-query-action { background-color: var(--success); width: 100%; margin-top: 5px; }
        .group-header { background-color: #34495e; color: #fff; padding: 8px 12px; font-weight: bold; font-size: 13px; margin-top: 20px; border-radius: 5px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
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
        <a href="https://indomaret.wasmer.app/" class="btn-header-external" title="Buka Link">
            <svg viewBox="0 0 24 24"><path d="M19 19H5V5h7V3H5c-1.11 0-2 .9-2 2v14c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2v-7h-2v7zM14 3v2h3.59l-9.83 9.83 1.41 1.41L19 6.41V10h2V3h-7z"/></svg>
        </a>
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
            <?php if ($isAdmin): ?>
                <button class="tab-btn active" id="btn6" onclick="switchTab(6)">
                    <svg viewBox="0 0 24 24"><path d="M4 14h4v-4H4v4zm0 5h4v-4H4v4zM4 9h4V5H4v4zm5 5h12v-4H9v4zm0 5h12v-4H9v4zM9 5v4h12V5H9z"/></svg>
                    Tabel Stok Fisik User
                </button>
                <button class="tab-btn" id="btn7" onclick="switchTab(7)">
                    <svg viewBox="0 0 24 24"><path d="M4 15h16v-2H4v2zm0 4h16v-2H4v2zm0-8h16V9H4v2zm0-6v2h16V5H4z"/></svg>
                    Tabel Hasil Selisih SO
                </button>
            <?php else: ?>
                <button class="tab-btn active" id="btn0" onclick="switchTab(0)">
                    <svg viewBox="0 0 24 24"><path d="M14 2H6c-1.1 0-1.99.9-1.99 2L4 20c0 1.1.89 2 1.99 2H18c1.1 0 2-.9 2-2V8l-6-6zm2 16H8v-2h8v2zm0-4H8v-2h8v2zm-3-5V3.5L18.5 9H13z"/></svg>
                    Input Data Stok
                </button>
                <button class="tab-btn" id="btn1" onclick="switchTab(1)">
                    <svg viewBox="0 0 24 24"><path d="M10 18h4v-2h-4v2zM3 6v2h18V6H3zm3 7h12v-2H6v2z"/></svg>
                    Pilih Modis SO
                </button>
                <button class="tab-btn" id="btn2" onclick="switchTab(2)" disabled>
                    <svg viewBox="0 0 24 24"><path d="M4 14h4v-4H4v4zm0 5h4v-4H4v4zM4 9h4V5H4v4zm5 5h12v-4H9v4zm0 5h12v-4H9v4zM9 5v4h12V5H9z"/></svg>
                    Daftar Listing Rak
                </button>
                <button class="tab-btn" id="btn3" onclick="switchTab(3)" disabled>
                    <svg viewBox="0 0 24 24"><path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/></svg>
                    Input Stok SO
                </button>
                <button class="tab-btn" id="btn4" onclick="switchTab(4)" disabled>
                    <svg viewBox="0 0 24 24"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-2 10h-4v4h-2v-4H7v-2h4V7h2v4h4v2z"/></svg>
                    Hitung Selisih SO
                </button>
            <?php endif; ?>
        </div>
        
        <a href="?logout=true" class="btn-sidebar-logout">
            <svg viewBox="0 0 24 24"><path d="M17 7l-1.41 1.41L18.17 11H8v2h10.17l-2.58 2.58L17 17l5-5zM4 5h8V3H4c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h8v-2H4V5z"/></svg>
            Logout
        </a>
        <div class="sidebar-footer">
            <div id="sidebarTime" class="sidebar-time"></div>
        </div>
    </div>

    <div class="content-container">
        <?php if ($isAdmin): ?>
            <div id="tab6" class="tab-content active fade-in">
                <div class="filter-section">
                    <label for="adminStokFilter" style="font-weight: bold; color: var(--primary);">Pilih NIK :</label>
                    <select id="adminStokFilter" onchange="renderFilteredAdminStokTable()"></select>
                    <div style="display: flex; gap: 5px; margin-top: 10px; justify-content: space-between;">
                        <button class="btn-cari" style="background-color: var(--accent); width: auto;" onclick="loadAdminStokFisik()">Refresh Data</button>
                        <button class="btn-cari" style="background-color: var(--danger); width: auto;" onclick="adminTruncateTable('stok_fisik_user')">Reset Isi Database</button>
                    </div>
                </div>
                <div id="adminStokFisikContainer"></div>
            </div>

            <div id="tab7" class="tab-content">
                <div class="filter-section">
                    <label for="adminHasilFilter" style="font-weight: bold; color: var(--primary);">Pilih NIK :</label>
                    <select id="adminHasilFilter" onchange="renderFilteredAdminHasilTable()"></select>
                    <div style="display: flex; gap: 5px; margin-top: 10px; justify-content: space-between;">
                        <button class="btn-cari" style="background-color: var(--accent); width: auto;" onclick="loadAdminHasilSelisih()">Refresh Data</button>
                        <button class="btn-cari" style="background-color: var(--danger); width: auto;" onclick="adminTruncateTable('hasil_selisih_so')">Reset Isi Database</button>
                    </div>
                </div>
                <div id="adminHasilSelisihContainer"></div>
            </div>
        <?php else: ?>
            <div id="tab0" class="tab-content active fade-in">
                <div class="filter-section">
                    <h3 style="margin-top:0; color: var(--primary); text-align:center;">Sambung ke Wifi "anak" lalu ( <a href="http://192.168.137.1:3000/so_hp.html" target="_blank">Klik Disini !</a> ) untuk download data stok</h3>
                    <p style="font-size:12px; color:#666; text-align:center; margin-bottom:15px;"></p>
                    <button style="display: none;" class="btn-download" onclick="downloadDataSO()">Download Data Server (JSON)</button>
                    <div style="border-top: 1px dashed #ccc; margin: 15px 0;"></div>
                    <input type="file" id="fileJsonInput" accept=".json" style="display:none;" onchange="handleOfflineJson(this)">
                    <button class="btn-upload" onclick="document.getElementById('fileJsonInput').click()">=> Input Data Stok disini <=</button>
                    <div id="statusData" class="status-info">Silakan input file .json yg sudah di download</div>
                </div>
            </div>

            <div id="tab1" class="tab-content">
                <div class="filter-section">
                    <label>Pilih Modis</label> <select id="rakSelect" onchange="checkFilter()"><option value="">-- Pilih --</option></select>
                    <label>Dari Shelfing</label> <select id="shelfStart" onchange="checkFilter()"><option value="">-- Pilih --</option></select>
                    <label>Sampai Shelfing</label> <select id="shelfEnd" onchange="checkFilter()"><option value="">-- Pilih --</option></select>
                    <button class="btn-cari" onclick="confirmFilter()">Simpan & Lanjut</button>
                </div>
            </div>

            <div id="tab2" class="tab-content">
                <div class="table-container">
                    <table>
                        <thead><tr><th>Modis</th><th>PLU</th><th>Deskripsi</th><th>Harga</th><th>Stok LPP</th><th>Stok Fisik</th></tr></thead>
                        <tbody id="tableInput"></tbody>
                    </table>
                </div>
            </div>

            <div id="tab3" class="tab-content">
                <div id="lastInputContainer" class="last-item-box" style="display: none;">
                    <div class="last-item-title">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/></svg>
                        Item Terakhir Yg Di Input
                    </div>
                    <div id="lastItemDesc" class="last-item-desc">-</div>
                    <div class="last-item-detail">
                        <span>PLU : <b id="lastItemPlu">-</b></span>
                        <span>Qty Input : <span id="lastItemStok" class="last-item-stok">0</span></span>
                    </div>
                </div>

                <div class="filter-section">
                    <div style="display: flex; gap: 5px; margin-bottom: 10px;">
                        <input type="text" id="searchInput" inputmode="numeric" pattern="[0-8]*" oninput="this.value = this.value.replace(/[^0-9]/g, '')" placeholder="Ketik PLU atau Barcode" style="margin-bottom: 0;">
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

            <div id="tab4" class="tab-content">
                <div class="filter-section" style="display: flex; gap: 5px;">
                    <button class="btn-cari" onclick="calculateSelisih()">Proses</button>
                    <button class="btn-cari" style="background-color: #27ae60;" onclick="copyAllResults()">Salin</button>
                    <button class="btn-cari" style="background-color: #e67e22;" onclick="uploadResultsToDb()">Upload</button>
                    <button class="btn-cari" style="background-color: var(--danger);" onclick="resetUserProgress()">Reset</button>
                </div>
                <div id="hasilProses"></div>
            </div>
        <?php endif; ?>
    </div>

    <footer class="main-footer">
        <div class="footer-text">~ m.h.r ~</div>
    </footer>

    <div id="popup">
        <p id="popText" style="margin-top:0; font-weight:bold;"></p>
        <input type="text" id="stokInput" inputmode="numeric" pattern="[0-8]*" oninput="this.value = this.value.replace(/[^0-9]/g, '')" placeholder="Input Stok SO">
        <div class="popup-action-group">
            <button class="btn-kurang" onclick="kurangStok()">Kurang</button>
            <button class="btn-tambah" onclick="simpanStok()">Tambah</button>
        </div>
        <button style="background: #95a5a6; width: 100%; margin-top: 5px; padding: 12px; border:none; border-radius:5px; color:white; font-weight:bold;" onclick="closePopup()">Tutup</button>
    </div>

    <div id="queryPopup">
        <p id="queryPopText" style="margin-top:0; font-weight:bold;"></p>
        <input type="text" id="querySalesInput" inputmode="numeric" pattern="[0-8]*" oninput="this.value = this.value.replace(/[^0-9]/g, '')" placeholder="Input Query Sales">
        <button class="btn-query-action" onclick="prosesTambahQuery()">Tambah</button>
        <button style="background: #95a5a6; width: 100%; margin-top: 5px; padding: 12px; border:none; border-radius:5px; color:white; font-weight:bold;" onclick="closeQueryPopup()">Tutup</button>
    </div>

    <script>
        let fullData = [], dataInputan = new Map(Object.entries(<?php echo json_encode($savedData); ?>)), currentResults = { plus: [], minus: [], belum: [] };
        let html5QrcodeScanner;
        let currentQueryPlumd = '';
        let currentQueryType = '';
        let databaseRowsGlobal = [];
        let adminStokGlobal = [];
        let adminHasilGlobal = [];
        const isAdmin = <?php echo $isAdmin ? 'true' : 'false'; ?>;

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
            
            if(!isAdmin) {
                document.getElementById('rakSelect').innerHTML = '<option value="">Pilih...</option>';
                document.getElementById('shelfStart').innerHTML = '<option value="">Pilih...</option>';
                document.getElementById('shelfEnd').innerHTML = '<option value="">Pilih...</option>';
                populateFilters();
            }
            
            localStorage.setItem('so_full_data', JSON.stringify(fullData));
            if(!isAdmin) loadSavedFilter();
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
                        alert("Data stok berhasil dipasang ! Silakan lanjut ke menu pilih modis ...");
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
            
            tabs.forEach((t) => {
                let tabId = parseInt(t.id.replace('tab', ''));
                if (tabId === idx) {
                    t.classList.add('active');
                    setTimeout(() => t.classList.add('fade-in'), 10);
                } else {
                    t.classList.remove('fade-in');
                    t.classList.remove('active');
                }
            });
            
            btns.forEach((b) => {
                let btnId = parseInt(b.id.replace('btn', ''));
                b.classList.toggle('active', btnId === idx);
            });
            
            if(!isAdmin && idx === 2) renderTable();
            if(!isAdmin && idx === 3) checkLastInputDisplay();
            if(isAdmin && idx === 6) loadAdminStokFisik();
            if(isAdmin && idx === 7) loadAdminHasilSelisih();
            
            const sidebar = document.getElementById('sidebar');
            if(sidebar.classList.contains('open')) {
                toggleSidebar();
            }
        }

        function checkFilter() {
            const isSelected = document.getElementById('rakSelect').value !== "";
            document.getElementById('btn2').disabled = document.getElementById('btn3').disabled = document.getElementById('btn4').disabled = !isSelected;
        }

        function confirmFilter() { 
            const rak = document.getElementById('rakSelect').value;
            const start = document.getElementById('shelfStart').value;
            const end = document.getElementById('shelfEnd').value;
            
            localStorage.setItem('so_rak', rak);
            localStorage.setItem('so_shelf_start', start);
            localStorage.setItem('so_shelf_end', end);
            
            switchTab(3); 
        }

        function loadSavedFilter() {
            const savedRak = localStorage.getItem('so_rak');
            const savedStart = localStorage.getItem('so_shelf_start');
            const savedEnd = localStorage.getItem('so_shelf_end');
            
            if (savedRak) {
                document.getElementById('rakSelect').value = savedRak;
                document.getElementById('shelfStart').value = savedStart || "";
                document.getElementById('shelfEnd').value = savedEnd || "";
                checkFilter();
                switchTab(3);
            }
        }

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

        function onScanFailure(error) {}

        function searchAction() {
            const search = document.getElementById('searchInput').value.trim();
            if (!search) {
                alert("Masukkan angka pencarian terlebih dahulu !");
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
            document.getElementById('popText').innerText = "Produk : " + item.DESC2; 
            
            const pop = document.getElementById('popup');
            pop.classList.add('show'); 
            setTimeout(() => pop.classList.add('pop-in'), 10);
        }
        
        function closePopup() { 
            const pop = document.getElementById('popup');
            pop.classList.remove('pop-in');
            setTimeout(() => pop.classList.remove('show'), 300);
        }

        function openQueryPopup(plumd, desc, type) {
            currentQueryPlumd = plumd;
            currentQueryType = type;
            document.getElementById('queryPopText').innerText = "Produk : " + desc;
            document.getElementById('querySalesInput').value = "";
            const qpop = document.getElementById('queryPopup');
            qpop.style.display = 'block';
            setTimeout(() => qpop.classList.add('pop-in'), 10);
        }

        function closeQueryPopup() {
            const qpop = document.getElementById('queryPopup');
            qpop.classList.remove('pop-in');
            setTimeout(() => qpop.style.display = 'none', 300);
        }

        async function prosesTambahQuery() {
            let queryVal = parseInt(document.getElementById('querySalesInput').value) || 0;
            if (queryVal <= 0) {
                alert("Masukkan jumlah query yang valid.");
                return;
            }

            let currentStok = parseInt(dataInputan.get(currentQueryPlumd)) || 0;
            let totalStok = currentStok + queryVal;

            dataInputan.set(currentQueryPlumd, totalStok);
            await updateDbStok(currentQueryPlumd, totalStok);

            closeQueryPopup();
            calculateSelisih();
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

        function updateLastInputDisplay(item, totalStok) {
            const lastData = {
                desc: item.DESC2,
                plumd: item.PLUMD,
                stok: totalStok
            };
            localStorage.setItem('so_last_input', JSON.stringify(lastData));
            checkLastInputDisplay();
        }

        function checkLastInputDisplay() {
            const container = document.getElementById('lastInputContainer');
            const savedLast = localStorage.getItem('so_last_input');
            if (savedLast) {
                try {
                    const parsed = JSON.parse(savedLast);
                    document.getElementById('lastItemDesc').innerText = parsed.desc;
                    document.getElementById('lastItemPlu').innerText = parsed.plumd;
                    document.getElementById('lastItemStok').innerText = parsed.stok;
                    container.style.display = 'block';
                } catch(e) {}
            } else {
                container.style.display = 'none';
            }
        }

        async function simpanStok() { 
            let val = parseInt(document.getElementById('stokInput').value) || 0;
            let currentStok = parseInt(dataInputan.get(window.currentItem.PLUMD)) || 0;
            let totalStok = currentStok + val;
            
            dataInputan.set(window.currentItem.PLUMD, totalStok); 
            await updateDbStok(window.currentItem.PLUMD, totalStok);
            updateLastInputDisplay(window.currentItem, totalStok);
            resetForm();
        }

        async function kurangStok() {
            let currentStok = parseInt(dataInputan.get(window.currentItem.PLUMD)) || 0;
            let inputMinus = parseInt(document.getElementById('stokInput').value) || 0;
            let totalStok = currentStok - inputMinus;
            
            dataInputan.set(window.currentItem.PLUMD, totalStok);
            await updateDbStok(window.currentItem.PLUMD, totalStok);
            updateLastInputDisplay(window.currentItem, totalStok);
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
                if(!dataInputan.has(plumd)) { 
                    if(qtySys !== 0) currentResults.belum.push(item); 
                }
                else {
                    let selisih = parseInt(dataInputan.get(plumd)) - qtySys;
                    if(selisih > 0) currentResults.plus.push({...item, selisih});
                    else if(selisih < 0) currentResults.minus.push({...item, selisih});
                    else if(selisih === 0 && qtySys !== 0) {
                    }
                }
            });
            container.innerHTML += createTable("DAFTAR PLUS (+)", currentResults.plus, true, false, 'plus');
            container.innerHTML += createTable("DAFTAR MINUS (-)", currentResults.minus, true, false, 'minus');
            container.innerHTML += createTable("DAFTAR BELUM INPUT SO", currentResults.belum, false, true, 'belum');
        }

        function createTable(title, data, isSelisih, isBelum, type) {
            if(data.length === 0) return "";
            return `<div class="selisih-title">${title}</div><div class="table-container"><table><thead><tr><th>PLU</th><th>Deskripsi</th><th>Harga</th>${isBelum ? '<th>Stok LPP</th>' : ''}${isSelisih ? '<th>Selisih</th>' : ''}<th>Query</th></tr></thead><tbody>${data.map(i => `<tr><td>${i.PLUMD}</td><td>${i.DESC2}</td><td>${parseInt(i.PRICE).toLocaleString()}</td>${isBelum ? `<td>${i.QTY}</td>` : ''}${isSelisih ? `<td>${i.selisih > 0 ? '+' : ''}${i.selisih}</td>` : ''}<td><button style="background:var(--accent); padding:4px 8px; font-size:10px;" onclick="openQueryPopup('${i.PLUMD}', '${i.DESC2.replace(/'/g, "\\'")}', '${type}')">Input</button></td></tr>`).join('')}</tbody></table></div>`;
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
            
            if(currentResults.minus.length > 0 || currentResults.belum.length > 0) {
                text += `\n--- DAFTAR MINUS (-) ---\n`;
                text += `PLU | Deskripsi | Harga | Selisih\n`;
                
                currentResults.minus.forEach(i => {
                    text += `${i.PLUMD} | ${i.DESC2} | ${parseInt(i.PRICE).toLocaleString()} | ${i.selisih}\n`;
                });
                
                currentResults.belum.forEach(i => {
                    let qtySys = parseInt(i.QTY) || 0;
                    text += `${i.PLUMD} | ${i.DESC2} | ${parseInt(i.PRICE).toLocaleString()} | -${qtySys}\n`;
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
            alert("Semua data berhasil disalin! Bagian 'Belum Input' otomatis masuk ke kelompok 'Daftar Minus' pada hasil salinan.");
        }

        async function uploadResultsToDb() {
            if (currentResults.plus.length === 0 && currentResults.minus.length === 0 && currentResults.belum.length === 0) {
                alert("Silakan tekan tombol 'Proses' terlebih dahulu untuk memuat data hasil.");
                return;
            }

            if (!confirm("Apakah Anda yakin ingin mengupload semua hasil perhitungan ini ke database?")) {
                return;
            }

            const itemsToUpload = [];

            currentResults.plus.forEach(i => {
                itemsToUpload.push({
                    modis: `${i.NAMA_RAK.substring(0,6)}-${i.NOSHELF}-${i.KIRIKANAN}`,
                    plumd: i.PLUMD,
                    deskripsi: i.DESC2,
                    harga: i.PRICE,
                    selisih: parseInt(i.selisih)
                });
            });

            currentResults.minus.forEach(i => {
                itemsToUpload.push({
                    modis: `${i.NAMA_RAK.substring(0,6)}-${i.NOSHELF}-${i.KIRIKANAN}`,
                    plumd: i.PLUMD,
                    deskripsi: i.DESC2,
                    harga: i.PRICE,
                    selisih: parseInt(i.selisih)
                });
            });

            currentResults.belum.forEach(i => {
                let qtySys = parseInt(i.QTY) || 0;
                itemsToUpload.push({
                    modis: `${i.NAMA_RAK.substring(0,6)}-${i.NOSHELF}-${i.KIRIKANAN}`,
                    plumd: i.PLUMD,
                    deskripsi: i.DESC2,
                    harga: i.PRICE,
                    selisih: -qtySys
                });
            });

            const loader = document.getElementById('loader');
            loader.style.display = 'block';

            try {
                const formData = new FormData();
                formData.append('action', 'upload_hasil_selisih');
                formData.append('items', JSON.stringify(itemsToUpload));

                const response = await fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                loader.style.display = 'none';

                if (result.success) {
                    alert(result.message);
                } else {
                    alert(result.message);
                }
            } catch (e) {
                loader.style.display = 'none';
                alert("Terjadi kesalahan jaringan saat mengupload data.");
            }
        }

        async function loadAdminStokFisik() {
            const container = document.getElementById('adminStokFisikContainer');
            const selectFilter = document.getElementById('adminStokFilter');
            container.innerHTML = "";
            const loader = document.getElementById('loader');
            loader.style.display = 'block';

            try {
                const formData = new FormData();
                formData.append('action', 'admin_get_stok_fisik');

                const response = await fetch(window.location.href, { method: 'POST', body: formData });
                const result = await response.json();
                loader.style.display = 'none';

                if (result.success && result.data.length > 0) {
                    adminStokGlobal = result.data;
                    let users = [...new Set(adminStokGlobal.map(item => item.username))].sort();
                    
                    selectFilter.innerHTML = "";
                    users.forEach(u => {
                        selectFilter.innerHTML += `<option value="${u}">${u}</option>`;
                    });
                    
                    renderFilteredAdminStokTable();
                } else {
                    selectFilter.innerHTML = "<option value=''>-- Kosong --</option>";
                    container.innerHTML = "<div class='status-info'>Tabel stok_fisik_user kosong.</div>";
                }
            } catch (e) {
                loader.style.display = 'none';
                alert("Gagal memuat data dari database.");
            }
        }

        function renderFilteredAdminStokTable() {
            const container = document.getElementById('adminStokFisikContainer');
            const selectedUser = document.getElementById('adminStokFilter').value;
            container.innerHTML = "";

            if(!selectedUser) return;

            const filtered = adminStokGlobal.filter(item => item.username === selectedUser);

            if (filtered.length === 0) {
                container.innerHTML = "<div class='status-info'>Tidak ada data.</div>";
                return;
            }

            let tableHtml = `<div class="table-container"><table><thead><tr><th>User NIK</th><th>PLU</th><th>Stok Fisik</th></tr></thead><tbody>`;
            filtered.forEach(i => {
                tableHtml += `<tr><td>${i.username}</td><td>${i.plumd}</td><td>${i.stok_fisik}</td></tr>`;
            });
            tableHtml += `</tbody></table></div>`;
            container.innerHTML = tableHtml;
        }

        async function loadAdminHasilSelisih() {
            const container = document.getElementById('adminHasilSelisihContainer');
            const selectFilter = document.getElementById('adminHasilFilter');
            container.innerHTML = "";
            const loader = document.getElementById('loader');
            loader.style.display = 'block';

            try {
                const formData = new FormData();
                formData.append('action', 'get_database_results');

                const response = await fetch(window.location.href, { method: 'POST', body: formData });
                const result = await response.json();
                loader.style.display = 'none';

                if (result.success && result.data.length > 0) {
                    adminHasilGlobal = result.data;
                    let users = [...new Set(adminHasilGlobal.map(item => item.username))].sort();

                    selectFilter.innerHTML = "";
                    users.forEach(u => {
                        selectFilter.innerHTML += `<option value="${u}">${u}</option>`;
                    });

                    renderFilteredAdminHasilTable();
                } else {
                    selectFilter.innerHTML = "<option value=''>-- Kosong --</option>";
                    container.innerHTML = "<div class='status-info'>Tabel hasil_selisih_so kosong.</div>";
                }
            } catch (e) {
                loader.style.display = 'none';
                alert("Gagal memuat data dari database.");
            }
        }

        function renderFilteredAdminHasilTable() {
            const container = document.getElementById('adminHasilSelisihContainer');
            const selectedUser = document.getElementById('adminHasilFilter').value;
            container.innerHTML = "";

            if(!selectedUser) return;

            const filtered = adminHasilGlobal.filter(item => item.username === selectedUser);

            if (filtered.length === 0) {
                container.innerHTML = "<div class='status-info'>Tidak ada data.</div>";
                return;
            }

            let tableHtml = `<div class="table-container"><table><thead><tr><th>Modis</th><th>PLU</th><th>Deskripsi</th><th>Harga</th><th>Selisih</th></tr></thead><tbody>`;
            filtered.forEach(i => {
                let formattedSelisih = i.selisih > 0 ? `+${i.selisih}` : i.selisih;
                let colorStyle = i.selisih > 0 ? 'color:green; font-weight:bold;' : 'color:red; font-weight:bold;';
                tableHtml += `<tr><td>${i.modis}</td><td>${i.plumd}</td><td>${i.deskripsi}</td><td>${parseInt(i.harga).toLocaleString('id-ID')}</td><td style="${colorStyle}">${formattedSelisih}</td></tr>`;
            });
            tableHtml += `</tbody></table></div>`;
            container.innerHTML = tableHtml;
        }

        async function adminTruncateTable(tableName) {
            if (!confirm(`PERINGATAN! Apakah Anda yakin ingin MENGHAPUS SEMUA data di tabel ${tableName}? Tindakan ini tidak dapat dibatalkan.`)) {
                return;
            }
            const loader = document.getElementById('loader');
            loader.style.display = 'block';

            try {
                const formData = new FormData();
                formData.append('action', 'admin_truncate_table');
                formData.append('table', tableName);

                const response = await fetch(window.location.href, { method: 'POST', body: formData });
                const result = await response.json();
                loader.style.display = 'none';

                alert(result.message);
                if (tableName === 'stok_fisik_user') loadAdminStokFisik();
                if (tableName === 'hasil_selisih_so') loadAdminHasilSelisih();
            } catch (e) {
                loader.style.display = 'none';
                alert("Terjadi kesalahan koneksi saat mengosongkan database.");
            }
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
                        localStorage.removeItem('so_rak');
                        localStorage.removeItem('so_shelf_start');
                        localStorage.removeItem('so_shelf_end');
                        localStorage.removeItem('so_last_input');
                        checkLastInputDisplay();
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

        window.addEventListener('DOMContentLoaded', () => {
            const savedOfflineData = localStorage.getItem('so_full_data');
            if (savedOfflineData) {
                const parsed = JSON.parse(savedOfflineData);
                fullData = parsed;
                if(!isAdmin) {
                    populateFilters();
                    loadSavedFilter();
                }
                
                const statusData = document.getElementById('statusData');
                if (statusData) {
                    statusData.innerText = `Menggunakan data tersimpan (${fullData.length} item loaded)`;
                    statusData.style.color = "var(--success)";
                }
            }
            if(isAdmin) {
                loadAdminStokFisik();
            }
        });
    </script>
</body>
</html>