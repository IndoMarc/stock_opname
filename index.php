<?php
session_start();

$accounts = [
    'CIF',
    'SSL',
    'SJL',
    'SCB',
    'SCG'
];

$roleNames = [
    'CIF' => 'Chief Of Store',
    'SSL' => 'Store Senior Leader',
    'SJL' => 'Store Junior Leader',
    'SCB' => 'Store Crew Boy',
    'SCG' => 'Store Crew Girl'
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
    <title>Stock Opname</title>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body { font-family: sans-serif; background-color: #f8f9fa; display: flex; flex-direction: column; justify-content: center; align-items: center; height: 100vh; margin: 0; }
        .login-logo { width: 180px; height: auto; margin-bottom: 15px; }
        .login-box { background: #fff; padding: 25px; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); width: 100%; max-width: 320px; text-align: center; box-sizing: border-box; }
        .login-box h3 { margin-top: 0; color: #2c3e50; margin-bottom: 20px; font-size: 18px; }
        .account-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px; }
        .btn-account { padding: 14px 10px; background: #ffffff; color: #2c3e50; border: 2px solid #e0e6ed; border-radius: 8px; font-weight: bold; cursor: pointer; font-size: 15px; transition: all 0.2s ease; display: flex; align-items: center; justify-content: center; box-shadow: 0 2px 4px rgba(0,0,0,0.02); }
        .btn-account:hover { background: #3498db; color: white; border-color: #3498db; transform: translateY(-2px); box-shadow: 0 4px 8px rgba(52, 152, 219, 0.25); }
        .btn-account.cif { grid-column: 1 / -1; }
        .login-footer { margin-top: 15px; font-size: 13px; color: #7f8c8d; font-weight: 500; }
    </style>
</head>
<body>
    <img src="indomaret.PNG" alt="Logo Indomaret" class="login-logo">
    
    <div class="login-box">
        <h3>Pilih Akun Stock Opname</h3>
        <form method="POST" class="account-grid">
            <?php foreach ($accounts as $acc): ?>
                <button type="submit" name="username" value="<?php echo htmlspecialchars($acc); ?>" class="btn-account <?php echo $acc === 'CIF' ? 'cif' : ''; ?>">
                    <?php echo htmlspecialchars($acc); ?>
                </button>
            <?php endforeach; ?>
        </form>
    </div>

    <div class="login-footer">~ m.h.r ~</div>

    <?php if(isset($login_error)): ?>
    <script>
        Swal.fire({
            icon: 'error',
            title: 'Gagal Login',
            text: '<?php echo $login_error; ?>'
        });
    </script>
    <?php endif; ?>
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
$sidebarDisplayName = isset($roleNames[$currentUser]) ? $roleNames[$currentUser] : $currentUser;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    if ($_POST['action'] === 'save') {
        try {
            $plumd = $_POST['plumd'];
            $stok = (int)$_POST['stok'];
            
            $stmt = $pdo->prepare("INSERT INTO stok_fisik_user (plumd, username, stok_fisik) VALUES (:plumd, :username, :stok) ON DUPLICATE KEY UPDATE stok_fisik = :stok");
            $stmt->execute(['plumd' => $plumd, 'username' => $currentUser, 'stok' => $stok]);
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }
    
    if ($_POST['action'] === 'reset') {
        try {
            $stmt = $pdo->prepare("DELETE FROM stok_fisik_user WHERE username = :username");
            $stmt->execute(['username' => $currentUser]);
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }

    if ($_POST['action'] === 'upload_hasil') {
        try {
            $items = isset($_POST['items']) ? json_decode($_POST['items'], true) : [];
            
            if (empty($items)) {
                echo json_encode(['success' => false, 'message' => 'Tidak ada data selisih untuk di-upload.']);
                exit;
            }

            $stmt = $pdo->prepare("INSERT INTO hasil_selisih_so (plumd, selisih) VALUES (:plumd, :selisih)");
            
            $pdo->beginTransaction();
            foreach ($items as $item) {
                $stmt->execute([
                    'plumd'   => $item['plumd'],
                    'selisih' => (int)$item['selisih']
                ]);
            }
            $pdo->commit();

            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }

    if ($_POST['action'] === 'get_user_uploaded_items') {
        try {
            $stmt = $pdo->query("SELECT h1.* FROM hasil_selisih_so h1 INNER JOIN (SELECT plumd, MAX(id) as max_id FROM hasil_selisih_so GROUP BY plumd) h2 ON h1.id = h2.max_id ORDER BY h1.plumd ASC");
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'data' => $rows]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }

    if ($_POST['action'] === 'update_selisih_item') {
        try {
            $id = (int)$_POST['id'];
            $newSelisih = (int)$_POST['selisih'];

            $stmt = $pdo->prepare("UPDATE hasil_selisih_so SET selisih = :selisih WHERE id = :id");
            $stmt->execute(['selisih' => $newSelisih, 'id' => $id]);
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }

    if ($_POST['action'] === 'delete_uploaded_item') {
        try {
            $id = (int)$_POST['id'];

            $stmt = $pdo->prepare("DELETE FROM hasil_selisih_so WHERE id = :id");
            $stmt->execute(['id' => $id]);
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }

    if ($_POST['action'] === 'reset_all_uploaded_items') {
        try {
            $pdo->exec("TRUNCATE TABLE hasil_selisih_so");
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }

    if ($_POST['action'] === 'get_database_results') {
        try {
            $stmt = $pdo->query("SELECT h1.* FROM hasil_selisih_so h1 INNER JOIN (SELECT plumd, MAX(id) as max_id FROM hasil_selisih_so GROUP BY plumd) h2 ON h1.id = h2.max_id ORDER BY h1.created_at DESC");
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'data' => $rows]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }
}

$savedData = [];
$stmt = $pdo->prepare("SELECT plumd, stok_fisik FROM stok_fisik_user WHERE username = :username");
$stmt->execute(['username' => $currentUser]);
$savedData = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>STOCK OPNAME - <?php echo htmlspecialchars($currentUser); ?></title>
    <script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
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
        .close-sidebar { background: transparent; border: none; cursor: pointer; display: flex; align-items: center; justify-content: center; padding: 6px; border-radius: 50%; transition: background 0.2s; }
        .close-sidebar:hover { background: rgba(255,255,255,0.08); }
        .close-sidebar svg { stroke: #95a5a6; width: 20px; height: 20px; transition: stroke 0.2s; }
        .close-sidebar:hover svg { stroke: #fff; }
        
        .sidebar-menu { flex: 1; padding: 20px 0; overflow-y: auto; }
        .tab-btn { width: 90%; text-align: left; padding: 12px 15px; margin: 6px auto; cursor: pointer; background: transparent; border: none; font-size: 13px; font-weight: 600; color: #a0aec0; display: flex; align-items: center; gap: 10px; border-radius: 8px; transition: all 0.2s; box-sizing: border-box; }
        .tab-btn svg { width: 18px; height: 18px; fill: currentColor; flex-shrink: 0; }
        .tab-btn:hover:not(:disabled) { color: #fff; background: rgba(255,255,255,0.04); }
        .tab-btn.active { color: #fff; background: var(--accent); box-shadow: 0 4px 12px rgba(52, 152, 219, 0.3); }
        .tab-btn:disabled { opacity: 0.25; cursor: not-allowed; }
        
        .sub-menu-container { display: none; background: rgba(0,0,0,0.2); padding: 5px 0; border-radius: 8px; width: 90%; margin: 0 auto; }
        .sub-menu-container.show { display: block; }
        .sub-tab-btn { width: 90%; text-align: left; padding: 10px 15px 10px 35px; margin: 4px auto; cursor: pointer; background: transparent; border: none; font-size: 12px; font-weight: 600; color: #a0aec0; display: flex; align-items: center; gap: 8px; border-radius: 6px; transition: all 0.2s; box-sizing: border-box; }
        .sub-tab-btn svg { width: 15px; height: 15px; fill: currentColor; flex-shrink: 0; }
        .sub-tab-btn:hover { color: #fff; background: rgba(255,255,255,0.08); }
        .sub-tab-btn.active { color: #fff; background: #2980b9; }

        .arrow-icon { margin-left: auto; transition: transform 0.3s; }
        .arrow-icon.rotate { transform: rotate(180deg); }

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
        
        .text-right { text-align: right; }
        
        .btn-action-edit { background-color: #f39c12; color: white; border: none; padding: 4px 8px; border-radius: 4px; cursor: pointer; font-size: 10px; margin-right: 4px; }
        .btn-action-delete { background-color: #e74c3c; color: white; border: none; padding: 4px 8px; border-radius: 4px; cursor: pointer; font-size: 10px; }
        .btn-action-edit:hover { background-color: #d35400; }
        .btn-action-delete:hover { background-color: #c0392b; }
        .action-cell { white-space: nowrap; text-align: center; }

        .row-highlight {
            background-color: #fff3cd !important;
            transition: background-color 0.5s ease;
        }
    </style>
</head>
<body>

    <div id="loader"><div class="loader-content"><div class="spinner"></div>Sedang memuat data ...</div></div>

    <div id="sidebarOverlay" class="sidebar-overlay" onclick="toggleSidebar()"></div>

    <header class="main-header">
        <button class="menu-toggle" onclick="toggleSidebar()">
            <svg viewBox="0 0 24 24"><path d="M3 18h18v-2H3v2zm0-5h18v-2H3v2zm0-7v2h18V6H3z"/></svg>
        </button>
        <div class="header-title-container">
            <h2>STOCK OPNAME</h2>
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
                <span><?php echo htmlspecialchars($sidebarDisplayName); ?></span>
            </div>
            <button class="close-sidebar" onclick="toggleSidebar()" title="Tutup Menu">
                <svg viewBox="0 0 24 24" fill="none" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="18" y1="6" x2="6" y2="18"></line>
                    <line x1="6" y1="6" x2="18" y2="18"></line>
                </svg>
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
            <button class="tab-btn" id="btn3" onclick="switchTab(3)" disabled>
                <svg viewBox="0 0 24 24"><path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/></svg>
                Input Qty SO
            </button>
            <button class="tab-btn" id="btn4" onclick="switchTab(4)" disabled>
                <svg viewBox="0 0 24 24"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-2 10h-4v4h-2v-4H7v-2h4V7h2v4h4v2z"/></svg>
                Hitung Selisih SO
            </button>
            <button class="tab-btn" id="btn2" onclick="switchTab(2)" disabled>
                <svg viewBox="0 0 24 24"><path d="M4 14h4v-4H4v4zm0 5h4v-4H4v4zM4 9h4V5H4v4zm5 5h12v-4H9v4zm0 5h12v-4H9v4zM9 5v4h12V5H9z"/></svg>
                Hasil Akhir SO
            </button>
            
            <button class="tab-btn" id="btnMore" onclick="toggleSubMenu()">
                <svg viewBox="0 0 24 24"><path d="M4 6h16v2H4zm0 5h16v2H4zm0 5h16v2H4z"/></svg>
                Database MySQL
                <svg class="arrow-icon" id="arrowIcon" width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M7 10l5 5 5-5z"/></svg>
            </button>
            
            <div class="sub-menu-container" id="subMenuContainer">
                <button class="sub-tab-btn" id="btn6" onclick="switchTab(6)">
                    <svg viewBox="0 0 24 24"><path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/></svg>
                    Upload Satuan PLU
                </button>
                <button class="sub-tab-btn" id="btn7" onclick="switchTab(7)">
                    <svg viewBox="0 0 24 24"><path d="M4 6h16v2H4zm0 4h16v2H4zm0 4h16v2H4zm0 4h10v2H4z"/></svg>
                    Upload Banyak PLU
                </button>
                <button class="sub-tab-btn" id="btn5" onclick="switchTab(5)">
                    <svg viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-6h2v6zm0-8h-2V7h2v2z"/></svg>
                    List Item Yg Sudah Di SO
                </button>
            </div>
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
                <label>Dari Shelfing</label> <select id="shelfStart" onchange="checkFilter()"><option value="">-- Semua --</option></select>
                <label>Sampai Shelfing</label> <select id="shelfEnd" onchange="checkFilter()"><option value="">-- Semua --</option></select>
                <button class="btn-cari" onclick="confirmFilter()">Simpan & Lanjut</button>
            </div>
        </div>

        <div id="tab3" class="tab-content">
            <div id="lastInputContainer" class="last-item-box" style="display: none;">
                <div class="last-item-title">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/></svg>
                    Item terakhir yang di input
                </div>
                <div id="lastItemDesc" class="last-item-desc">-</div>
                <div class="last-item-detail">
                    <span>PLU : <b id="lastItemPlu">-</b></span>
                    <span>Qty Input : <span id="lastItemStok" class="last-item-stok">0</span></span>
                </div>
            </div>

            <div class="filter-section">
                <div style="display: flex; gap: 5px; margin-bottom: 10px;">
                    <input type="text" id="searchInput" inputmode="numeric" pattern="[0-8]*" oninput="this.value = this.value.replace(/[^0-9]/g, ''); searchAction();" placeholder="Ketik PLU atau Barcode" style="margin-bottom: 0;">
                    <button onclick="toggleScanner()" style="background: var(--primary); padding: 12px; border: none; border-radius: 5px; cursor: pointer; display: flex; align-items: center; justify-content: center; width: auto;">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 7V5a2 2 0 0 1 2-2h2"></path><path d="M17 3h2a2 2 0 0 1 2 2v2"></path><path d="M21 17v2a2 2 0 0 1-2 2h-2"></path><path d="M7 21H5a2 2 0 0 1-2-2v-2"></path><rect x="7" y="7" width="10" height="10"></rect></svg>
                    </button>
                </div>
                <div id="reader" style="display: none; margin-top: 10px;"></div>
            </div>
            <div id="searchResultContainer" class="table-container">
                <table><thead><tr><th>Modis</th><th>PLU</th><th>Deskripsi</th><th>Input</th></tr></thead><tbody id="searchResultTable"></tbody></table>
            </div>
        </div>

        <div id="tab4" class="tab-content">
            <div class="filter-section" style="display: flex; gap: 5px;">
                <button class="btn-cari" onclick="calculateSelisih()">Proses</button>
            </div>
            <div id="hasilProses"></div>
        </div>

        <div id="tab2" class="tab-content">
            <div class="filter-section" style="display: flex; gap: 5px; margin-bottom: 10px;">
                <button class="btn-cari" style="background-color: #27ae60;" onclick="copyAllResults()">Salin Hasil Selisih</button>
                <button class="btn-cari" style="background-color: var(--danger);" onclick="resetUserProgress()">Reset Inputan Qty SO</button>
                <button class="btn-cari" style="background-color: #f39c12;" onclick="uploadToMysql()">Upload Ke MySQL</button>
            </div>
            <div class="table-container">
                <table>
                    <thead><tr><th>Modis</th><th>PLU</th><th>Deskripsi</th><th>Stok LPP</th><th>Stok Fisik</th><th>Selisih</th></tr></thead>
                    <tbody id="tableInput"></tbody>
                </table>
            </div>
        </div>

        <div id="tab6" class="tab-content">
            <div class="filter-section">
                <h3 style="margin-top:0; color: var(--primary);">Upload Satuan PLU</h3>
                <label>Input PLU</label>
                <input type="text" id="directPluInput" inputmode="numeric" placeholder="Ketik PLU">
                
                <label>Input Selisih (+ atau -)</label>
                <input type="number" id="directSelisihInput" placeholder="Contoh : 5 atau -3">
                
                <button class="btn-cari" style="background-color: var(--accent); margin-top: 5px;" onclick="processDirectItemInput()">Proses</button>
            </div>
            <div id="directResultInfo" class="last-item-box" style="display: none;">
                <div class="last-item-title">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/></svg>
                    Item Berhasil Terproses
                </div>
                <div id="directItemDesc" class="last-item-desc">-</div>
                <div class="last-item-detail">
                    <span>PLU : <b id="directItemPlu">-</b></span>
                    <span>Selisih : <span id="directItemSelisih" class="last-item-stok">0</span></span>
                </div>
            </div>
        </div>

        <div id="tab7" class="tab-content">
            <div class="filter-section">
                <h3 style="margin-top:0; color: var(--primary);">Upload Banyak PLU</h3>
                <label>Ketik Atau Paste Data Item ( PLU Selisih )</label>
                <textarea id="bulkDataInput" rows="10" placeholder="Contoh : &#10;20134253 -1&#10;10000073 -2&#10;10040122 +1"></textarea>
                <button class="btn-cari" style="background-color: var(--accent); margin-top: 5px;" onclick="processBulkItemInput()">Proses</button>
            </div>
        </div>

        <div id="tab5" class="tab-content">
            <div class="filter-section" style="display: flex; gap: 10px;">
                <button class="btn-cari" style="background-color: #3498db; flex: 1;" onclick="loadUploadedItems()">Lihat Item Database</button>
                <button class="btn-cari" style="background-color: #27ae60; flex: 1;" onclick="copyUploadedItemsTable()">Salin Isi Database</button>
                <button class="btn-cari" style="background-color: #e74c3c; flex: 1;" onclick="resetUploadedItems()">Reset Isi Database</button>
            </div>
            <div id="uploadedItemsContainer"></div>
        </div>
    </div>

    <footer class="main-footer">
        <div class="footer-text">~ m.h.r ~</div>
    </footer>

    <div id="popup">
        <p id="popText" style="margin-top:0; font-weight:bold;"></p>
        <input type="text" id="stokInput" inputmode="numeric" pattern="[0-8]*" oninput="this.value = this.value.replace(/[^0-9]/g, '')" placeholder="Input Qty SO">
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
        let currentUploadedData = { listPlus: [], listMinus: [], grandTotalPlus: 0, grandTotalMinus: 0 };
        let isSubMenuUnlocked = false;

        function showAlert(message, isSuccess = true, duration = 3000) {
            Swal.fire({
                toast: true,
                position: 'top-end',
                icon: isSuccess ? 'success' : 'error',
                title: message,
                showConfirmButton: false,
                timer: duration,
                timerProgressBar: true
            });
        }

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

        async function toggleSubMenu() {
            const subContainer = document.getElementById('subMenuContainer');
            const arrowIcon = document.getElementById('arrowIcon');

            if (!subContainer.classList.contains('show')) {
                if (!isSubMenuUnlocked) {
                    const { value: pass } = await Swal.fire({
                        title: 'Akses Dibatasi',
                        text: 'Masukkan kode akses untuk membuka :',
                        input: 'password',
                        inputPlaceholder: 'Kode Akses',
                        showCancelButton: true,
                        confirmButtonText: 'Submit',
                        cancelButtonText: 'Batal'
                    });

                    if (pass === "@@@@") {
                        isSubMenuUnlocked = true;
                    } else if (pass !== undefined) {
                        Swal.fire('Gagal', 'Kode akses salah !', 'error');
                        return;
                    } else {
                        return;
                    }
                }
            }

            subContainer.classList.toggle('show');
            arrowIcon.classList.toggle('rotate');
        }

        function processLoadedData(rawData) {
            fullData = rawData.sort((a, b) => a.NAMA_RAK.localeCompare(b.NAMA_RAK) || parseInt(a.NOSHELF) - parseInt(b.NOSHELF) || parseInt(a.KIRIKANAN) - parseInt(b.KIRIKANAN));
            
            document.getElementById('rakSelect').innerHTML = '<option value="">-- Pilih --</option>';
            document.getElementById('shelfStart').innerHTML = '<option value="">-- Semua --</option>';
            document.getElementById('shelfEnd').innerHTML = '<option value="">-- Semua --</option>';
            populateFilters();
            
            localStorage.setItem('so_full_data', JSON.stringify(fullData));
            loadSavedFilter();
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
                        Swal.fire('Berhasil', 'Data stok berhasil dipasang ! Silakan lanjut ke menu pilih modis ...', 'success');
                        switchTab(1); 
                    } else {
                        Swal.fire('Error', "Format file JSON salah atau field 'data' tidak ditemukan.", 'error');
                    }
                } catch (err) {
                    Swal.fire('Error', 'Gagal membaca file. Pastikan file berformat JSON valid!', 'error');
                }
                loader.style.display = 'none';
                input.value = ""; 
            };
            reader.readAsText(file);
        }

        async function switchTab(idx) {
            if (idx === 5 || idx === 6 || idx === 7) {
                if (!isSubMenuUnlocked) {
                    const { value: pass } = await Swal.fire({
                        title: 'Akses Dibatasi',
                        text: 'Masukkan kode akses Menu Lainnya:',
                        input: 'password',
                        inputPlaceholder: 'Kode Akses',
                        showCancelButton: true,
                        confirmButtonText: 'Submit',
                        cancelButtonText: 'Batal'
                    });

                    if (pass === "@@@@") {
                        isSubMenuUnlocked = true;
                    } else if (pass !== undefined) {
                        Swal.fire('Gagal', 'Kode akses salah!', 'error');
                        return;
                    } else {
                        return;
                    }
                }
            }

            const tabs = document.querySelectorAll('.tab-content');
            const mainBtns = document.querySelectorAll('.sidebar-menu > .tab-btn');
            const subBtns = document.querySelectorAll('.sub-tab-btn');
            
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
            
            mainBtns.forEach((b) => {
                let btnId = parseInt(b.id.replace('btn', ''));
                b.classList.toggle('active', btnId === idx);
            });

            subBtns.forEach((sb) => {
                let subId = parseInt(sb.id.replace('btn', ''));
                sb.classList.toggle('active', subId === idx);
            });

            const btnMore = document.getElementById('btnMore');
            if (idx === 5 || idx === 6 || idx === 7) {
                btnMore.classList.add('active');
                document.getElementById('subMenuContainer').classList.add('show');
                document.getElementById('arrowIcon').classList.add('rotate');
            } else {
                btnMore.classList.remove('active');
            }
            
            if(idx === 2) renderTable();
            if(idx === 3) {
                checkLastInputDisplay();
                searchAction();
            }
            
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
                Swal.fire('Peringatan', 'Barcode tidak valid. Hanya angka yang diperbolehkan.', 'warning');
            }
        }

        function onScanFailure(error) {}

        function searchAction() {
            const search = document.getElementById('searchInput').value.trim();
            const modisData = getFilteredData();
            
            let filtered = modisData;
            if (search !== "") {
                filtered = modisData.filter(i => (i.PLUMD && i.PLUMD.includes(search)) || (i.BARCD && i.BARCD.includes(search)));
            }

            const uniqueResults = []; 
            const seen = new Set();
            
            filtered.forEach(i => { 
                if(!seen.has(i.PLUMD)) { 
                    uniqueResults.push(i); 
                    seen.add(i.PLUMD); 
                } 
            });

            const tbody = document.getElementById('searchResultTable');
            tbody.innerHTML = "";

            if (search !== "" && uniqueResults.length === 1) {
                openPopup(uniqueResults[0]);
            }

            if (uniqueResults.length === 0) {
                tbody.innerHTML = `<tr><td colspan="4" style="text-align:center; color:#888;">Data tidak ditemukan.</td></tr>`;
            } else {
                uniqueResults.forEach(item => {
                    const tr = document.createElement('tr');
                    
                    const tdModis = document.createElement('td');
                    tdModis.innerText = `${item.NAMA_RAK.substring(0,6)}-${item.NOSHELF}-${item.KIRIKANAN}`;
                    
                    const tdPlu = document.createElement('td');
                    tdPlu.innerText = item.PLUMD;

                    const tdDesc = document.createElement('td');
                    tdDesc.innerText = item.DESC2;
                    
                    const tdBtn = document.createElement('td');
                    const btn = document.createElement('button');
                    btn.style.background = "var(--accent)";
                    btn.style.padding = "5px 10px";
                    btn.innerText = "Input";
                    
                    btn.addEventListener('click', () => openPopup(item));
                    
                    tdBtn.appendChild(btn);
                    tr.appendChild(tdModis);
                    tr.appendChild(tdPlu);
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

        async function updateDbStok(plumd, newStok) {
            try {
                const formData = new FormData();
                formData.append('action', 'save');
                formData.append('plumd', plumd);
                formData.append('stok', newStok);

                const res = await fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                });
                return await res.json();
            } catch (e) {
                return { success: false, message: e.message };
            }
        }

        async function prosesTambahQuery() {
            let rawVal = document.getElementById('querySalesInput').value;
            let queryVal = parseInt(rawVal);
            if (isNaN(queryVal) || queryVal <= 0) {
                showAlert('Masukkan jumlah query yang valid!', false);
                return;
            }

            let currentStok = parseInt(dataInputan.get(currentQueryPlumd)) || 0;
            let totalStok = currentStok + queryVal;

            let listTarget = currentResults[currentQueryType] || [];
            let currentIndex = listTarget.findIndex(i => i.PLUMD === currentQueryPlumd);
            let nextPlumd = null;

            if (currentIndex !== -1 && currentIndex + 1 < listTarget.length) {
                nextPlumd = listTarget[currentIndex + 1].PLUMD;
            }

            let result = await updateDbStok(currentQueryPlumd, totalStok);

            if (result && result.success) {
                dataInputan.set(currentQueryPlumd, totalStok);
                
                showAlert('Query berhasil ditambahkan!', true, 3000);
                closeQueryPopup();
                calculateSelisih();

                if (nextPlumd) {
                    setTimeout(() => {
                        let nextRow = document.querySelector(`tr[data-plumd="${nextPlumd}"]`);
                        if (nextRow) {
                            nextRow.classList.add('row-highlight');
                            nextRow.scrollIntoView({ behavior: 'smooth', block: 'center' });
                            setTimeout(() => {
                                nextRow.classList.remove('row-highlight');
                            }, 3500);
                        }
                    }, 100);
                }
            } else {
                showAlert('Gagal menyimpan ke database! Data batal ditambahkan.', false);
            }
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
            let rawVal = document.getElementById('stokInput').value;
            let val = parseInt(rawVal);
            if (isNaN(val) || val <= 0) {
                showAlert('Jumlah stok tidak boleh kosong atau 0!', false);
                return;
            }

            let currentStok = parseInt(dataInputan.get(window.currentItem.PLUMD)) || 0;
            let totalStok = currentStok + val;
            
            let result = await updateDbStok(window.currentItem.PLUMD, totalStok);
            
            if (result && result.success) {
                dataInputan.set(window.currentItem.PLUMD, totalStok); 
                updateLastInputDisplay(window.currentItem, totalStok);
                showAlert('Stok berhasil ditambahkan!', true);
                resetForm();
            } else {
                showAlert('Gagal menyimpan ke database! Data batal ditambahkan.', false);
            }
        }

        async function kurangStok() {
            let currentStok = parseInt(dataInputan.get(window.currentItem.PLUMD)) || 0;
            let rawVal = document.getElementById('stokInput').value;
            let inputMinus = parseInt(rawVal);
            if (isNaN(inputMinus) || inputMinus <= 0) {
                showAlert('Jumlah stok pengurangan tidak valid!', false);
                return;
            }

            let totalStok = currentStok - inputMinus;
            
            let result = await updateDbStok(window.currentItem.PLUMD, totalStok);

            if (result && result.success) {
                dataInputan.set(window.currentItem.PLUMD, totalStok);
                updateLastInputDisplay(window.currentItem, totalStok);
                showAlert('Stok berhasil dikurangi!', true);
                resetForm();
            } else {
                showAlert('Gagal memperbarui ke database! Data batal dikurangi.', false);
            }
        }

        function resetForm() {
            document.getElementById('stokInput').value = ""; 
            document.getElementById('searchInput').value = ""; 
            searchAction();
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

            document.getElementById('tableInput').innerHTML = sortedData.map(i => {
                let qtyVal = parseInt(i.QTY) || 0;
                let npbVal = parseInt(i.NPB) || 0;
                let stokLpp = qtyVal + npbVal;
                let isInputted = dataInputan.has(i.PLUMD);
                let stokFisikVal = dataInputan.get(i.PLUMD);
                
                let selisihStr = "";
                if (isInputted) {
                    let stokFisik = parseInt(stokFisikVal) || 0;
                    let diff = stokFisik - stokLpp;
                    selisihStr = diff > 0 ? `+${diff}` : `${diff}`;
                } else if (stokLpp !== 0) {
                    selisihStr = `-${stokLpp}`;
                }

                return `<tr data-plumd="${i.PLUMD}"><td>${i.NAMA_RAK.substring(0,6)}-${i.NOSHELF}-${i.KIRIKANAN}</td><td>${i.PLUMD}</td><td>${i.DESC2}</td><td>${stokLpp}</td><td>${stokFisikVal ?? ""}</td><td>${selisihStr}</td></tr>`;
            }).join('');
        }

        function calculateSelisih() {
            const container = document.getElementById('hasilProses'); container.innerHTML = "";
            currentResults = { plus: [], minus: [], belum: [] };
            const uniqueMap = new Map();
            getFilteredData().forEach(i => { if(!uniqueMap.has(i.PLUMD)) uniqueMap.set(i.PLUMD, i); });

            uniqueMap.forEach((item, plumd) => {
                let qtySys = (parseInt(item.QTY) || 0) + (parseInt(item.NPB) || 0);
                if(!dataInputan.has(plumd)) { 
                    if(qtySys !== 0) currentResults.belum.push({...item, stokLpp: qtySys}); 
                }
                else {
                    let selisih = parseInt(dataInputan.get(plumd)) - qtySys;
                    if(selisih > 0) currentResults.plus.push({...item, selisih, stokLpp: qtySys});
                    else if(selisih < 0) currentResults.minus.push({...item, selisih, stokLpp: qtySys});
                    else if(selisih === 0 && qtySys !== 0) {
                    }
                }
            });
            container.innerHTML += createTable("LIST ITEM PLUS (+)", currentResults.plus, true, false, 'plus');
            container.innerHTML += createTable("LIST ITEM MINUS (-)", currentResults.minus, true, false, 'minus');
            container.innerHTML += createTable("LIST ITEM BELUM INPUT SO", currentResults.belum, false, true, 'belum');
        }

        function createTable(title, data, isSelisih, isBelum, type) {
            if(data.length === 0) return "";
            return `<div class="selisih-title">${title}</div><div class="table-container"><table><thead><tr><th>PLU</th><th>Deskripsi</th><th>Stok LPP</th>${isSelisih ? '<th>Selisih</th>' : ''}<th>Query</th></tr></thead><tbody>${data.map(i => `<tr data-plumd="${i.PLUMD}"><td>${i.PLUMD}</td><td>${i.DESC2}</td><td>${i.stokLpp}</td>${isSelisih ? `<td>${i.selisih > 0 ? '+' : ''}${i.selisih}</td>` : ''}<td><button style="background:var(--accent); padding:4px 8px; font-size:10px;" onclick="openQueryPopup('${i.PLUMD}', '${i.DESC2.replace(/'/g, "\\'")}', '${type}')">Input</button></td></tr>`).join('')}</tbody></table></div>`;
        }

        function copyAllResults() {
            if (currentResults.plus.length === 0 && currentResults.minus.length === 0 && currentResults.belum.length === 0) {
                Swal.fire('Peringatan', "Silakan buka menu 'Hitung Selisih SO' lalu klik tombol 'Proses' terlebih dahulu !", 'warning');
                return;
            }

            const rakSelect = document.getElementById('rakSelect');
            const namaModis = rakSelect.options[rakSelect.selectedIndex] ? rakSelect.options[rakSelect.selectedIndex].text : '';

            let text = "```\n";
            text += `HASIL SO ( ${namaModis} )\n`;
            
            text += `\nLIST ITEM PLUS (+)\n`;
            text += `-------------------------\n`;
            if(currentResults.plus.length > 0) {
                currentResults.plus.forEach(i => {
                    text += `${i.PLUMD} ${i.DESC2} (+${i.selisih})\n`;
                });
            }

            text += `\nLIST ITEM MINUS (-)\n`;
            text += `-------------------------\n`;
            if(currentResults.minus.length > 0 || currentResults.belum.length > 0) {
                currentResults.minus.forEach(i => {
                    text += `${i.PLUMD} ${i.DESC2} (${i.selisih})\n`;
                });
                
                currentResults.belum.forEach(i => {
                    let qtySys = (parseInt(i.QTY) || 0) + (parseInt(i.NPB) || 0);
                    text += `${i.PLUMD} ${i.DESC2} (-${qtySys})\n`;
                });
            }
            text += "```";
            
            const textArea = document.createElement("textarea");
            textArea.value = text;
            textArea.style.position = "fixed";
            textArea.style.left = "-9999px";
            document.body.appendChild(textArea);
            textArea.select();
            document.execCommand('copy');
            document.body.removeChild(textArea);
            Swal.fire('Berhasil', 'Semua data berhasil disalin !', 'success');
        }

        async function uploadToMysql() {
            if (currentResults.plus.length === 0 && currentResults.minus.length === 0 && currentResults.belum.length === 0) {
                Swal.fire('Peringatan', "Silakan buka menu 'Hitung Selisih SO' lalu klik tombol 'Proses' terlebih dahulu !", 'warning');
                return;
            }

            const payloadItems = [];

            currentResults.plus.forEach(i => {
                payloadItems.push({ plumd: i.PLUMD, selisih: i.selisih });
            });

            currentResults.minus.forEach(i => {
                payloadItems.push({ plumd: i.PLUMD, selisih: i.selisih });
            });

            currentResults.belum.forEach(i => {
                let qtySys = (parseInt(i.QTY) || 0) + (parseInt(i.NPB) || 0);
                payloadItems.push({ plumd: i.PLUMD, selisih: -qtySys });
            });

            if (payloadItems.length === 0) {
                Swal.fire('Informasi', 'Tidak ada item selisih yang ditemukan untuk di-upload.', 'info');
                return;
            }

            const confirmRes = await Swal.fire({
                title: 'Konfirmasi Upload',
                text: `Apakah kamu yakin ingin meng-upload ${payloadItems.length} data hasil selisih ke MySQL database?`,
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Ya, Upload!',
                cancelButtonText: 'Batal'
            });

            if (!confirmRes.isConfirmed) return;

            const loader = document.getElementById('loader');
            loader.style.display = 'block';

            try {
                const formData = new FormData();
                formData.append('action', 'upload_hasil');
                formData.append('items', JSON.stringify(payloadItems));

                const response = await fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();

                if (result.success) {
                    Swal.fire('Berhasil', 'Berhasil meng-upload data hasil selisih ke database MySQL!', 'success');
                } else {
                    Swal.fire('Gagal', 'Gagal meng-upload data: ' + (result.message || 'Terjadi kesalahan server'), 'error');
                }
            } catch (e) {
                Swal.fire('Error', 'Terjadi kesalahan koneksi saat meng-upload data ke MySQL.', 'error');
            } finally {
                loader.style.display = 'none';
            }
        }

        async function processDirectItemInput() {
            const inputPlu = document.getElementById('directPluInput').value.trim();
            const rawSelisih = document.getElementById('directSelisihInput').value.trim();

            if (!inputPlu) {
                showAlert('Ketik PLU item terlebih dahulu!', false);
                return;
            }

            if (rawSelisih === "" || isNaN(parseInt(rawSelisih))) {
                showAlert('Masukkan jumlah selisih (+ atau -) yang valid!', false);
                return;
            }

            const selisihVal = parseInt(rawSelisih);

            if (fullData.length === 0) {
                showAlert('Data stok (JSON) belum di-load! Silakan input file JSON terlebih dahulu di menu awal.', false);
                return;
            }

            const foundItem = fullData.find(item => item.PLUMD === inputPlu);

            if (!foundItem) {
                showAlert(`Gagal! Item PLU ${inputPlu} TIDAK DITEMUKAN di data stok.`, false);
                document.getElementById('directResultInfo').style.display = 'none';
                return;
            }

            const loader = document.getElementById('loader');
            loader.style.display = 'block';

            try {
                const payload = [{ plumd: inputPlu, selisih: selisihVal }];
                const formData = new FormData();
                formData.append('action', 'upload_hasil');
                formData.append('items', JSON.stringify(payload));

                const response = await fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();

                if (result.success) {
                    showAlert(`Berhasil! Item PLU ${inputPlu} terproses dan tersimpan.`, true);
                    
                    document.getElementById('directItemDesc').innerText = foundItem.DESC2 || '-';
                    document.getElementById('directItemPlu').innerText = inputPlu;
                    document.getElementById('directItemSelisih').innerText = (selisihVal > 0 ? '+' : '') + selisihVal;
                    document.getElementById('directResultInfo').style.display = 'block';

                    document.getElementById('directPluInput').value = "";
                    document.getElementById('directSelisihInput').value = "";
                } else {
                    showAlert('Gagal memproses item: ' + (result.message || 'Terjadi kesalahan server'), false);
                }
            } catch (e) {
                showAlert('Terjadi kesalahan koneksi saat memproses data item.', false);
            } finally {
                loader.style.display = 'none';
            }
        }

        async function processBulkItemInput() {
            const rawText = document.getElementById('bulkDataInput').value.trim();

            if (!rawText) {
                showAlert('Ketik atau tempel data item terlebih dahulu!', false);
                return;
            }

            if (fullData.length === 0) {
                showAlert('Data stok (JSON) belum di-load! Silakan input file JSON terlebih dahulu di menu awal.', false);
                return;
            }

            const validPlumdSet = new Set(fullData.map(item => item.PLUMD));

            const lines = rawText.split('\n');
            const payloadItems = [];
            let notFoundCount = 0;
            let invalidFormatCount = 0;

            lines.forEach(line => {
                const trimmed = line.trim();
                if (!trimmed) return;

                const parts = trimmed.split(/\s+/);
                if (parts.length >= 2) {
                    const plumd = parts[0].trim();
                    const selisih = parseInt(parts[1].trim());

                    if (plumd && !isNaN(selisih)) {
                        if (validPlumdSet.has(plumd)) {
                            payloadItems.push({ plumd: plumd, selisih: selisih });
                        } else {
                            notFoundCount++;
                        }
                    } else {
                        invalidFormatCount++;
                    }
                } else {
                    invalidFormatCount++;
                }
            });

            if (payloadItems.length === 0) {
                let errorMsg = 'Gagal! Tidak ada PLU yang cocok dengan data stok.';
                if (invalidFormatCount > 0) {
                    errorMsg += ' Periksa kembali format teks.';
                }
                showAlert(errorMsg, false);
                return;
            }

            const loader = document.getElementById('loader');
            loader.style.display = 'block';

            try {
                const formData = new FormData();
                formData.append('action', 'upload_hasil');
                formData.append('items', JSON.stringify(payloadItems));

                const response = await fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();

                if (result.success) {
                    let msg = `Berhasil! ${payloadItems.length} item ter-upload.`;
                    let details = [];
                    if (notFoundCount > 0) details.push(`${notFoundCount} PLU tidak ditemukan di stok`);
                    if (invalidFormatCount > 0) details.push(`${invalidFormatCount} format salah`);
                    
                    if (details.length > 0) {
                        msg += ` (` + details.join(', ') + ` diabaikan)`;
                    }
                    
                    showAlert(msg, true, 5000);
                    document.getElementById('bulkDataInput').value = "";
                } else {
                    showAlert('Gagal memproses bulk: ' + (result.message || 'Terjadi kesalahan server'), false);
                }
            } catch (e) {
                showAlert('Terjadi kesalahan koneksi saat memproses data bulk.', false);
            } finally {
                loader.style.display = 'none';
            }
        }

        function formatRupiah(number) {
            return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(number);
        }

        async function editUploadedItem(id, plumd, currentSelisih) {
            const { value: val } = await Swal.fire({
                title: 'Edit Selisih',
                text: `Edit nilai selisih untuk PLU ${plumd}:`,
                input: 'number',
                inputValue: currentSelisih,
                showCancelButton: true,
                confirmButtonText: 'Simpan',
                cancelButtonText: 'Batal'
            });

            if (val === undefined) return;
            let newSelisih = parseInt(val);
            if (isNaN(newSelisih)) {
                Swal.fire('Peringatan', 'Masukkan angka selisih yang valid!', 'warning');
                return;
            }

            const loader = document.getElementById('loader');
            loader.style.display = 'block';

            try {
                const formData = new FormData();
                formData.append('action', 'update_selisih_item');
                formData.append('id', id);
                formData.append('selisih', newSelisih);

                const response = await fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();

                if (result.success) {
                    Swal.fire('Berhasil', 'Selisih item berhasil diperbarui!', 'success');
                    loadUploadedItems();
                } else {
                    Swal.fire('Gagal', 'Gagal memperbarui selisih: ' + (result.message || 'Terjadi kesalahan server'), 'error');
                }
            } catch (e) {
                Swal.fire('Error', 'Terjadi kesalahan koneksi saat merubah data selisih.', 'error');
            } finally {
                loader.style.display = 'none';
            }
        }

        async function deleteUploadedItem(id, plumd) {
            const confirmRes = await Swal.fire({
                title: 'Konfirmasi Hapus',
                text: `Apakah kamu yakin ingin menghapus item PLU ${plumd} dari database?`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Ya, Hapus!',
                cancelButtonText: 'Batal'
            });

            if (!confirmRes.isConfirmed) return;

            const loader = document.getElementById('loader');
            loader.style.display = 'block';

            try {
                const formData = new FormData();
                formData.append('action', 'delete_uploaded_item');
                formData.append('id', id);

                const response = await fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();

                if (result.success) {
                    Swal.fire('Berhasil', 'Item berhasil dihapus dari database!', 'success');
                    loadUploadedItems();
                } else {
                    Swal.fire('Gagal', 'Gagal menghapus item: ' + (result.message || 'Terjadi kesalahan server'), 'error');
                }
            } catch (e) {
                Swal.fire('Error', 'Terjadi kesalahan koneksi saat menghapus item.', 'error');
            } finally {
                loader.style.display = 'none';
            }
        }

        async function resetUploadedItems() {
            const confirmRes = await Swal.fire({
                title: 'Reset Semua Data Tabel?',
                text: 'Apakah kamu yakin ingin MENGHAPUS SEMUA ISI TABEL data item yang di-upload? Tindakan ini tidak dapat dibatalkan!',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                confirmButtonText: 'Ya, Reset!',
                cancelButtonText: 'Batal'
            });

            if (!confirmRes.isConfirmed) return;

            const loader = document.getElementById('loader');
            loader.style.display = 'block';

            try {
                const formData = new FormData();
                formData.append('action', 'reset_all_uploaded_items');

                const response = await fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();

                if (result.success) {
                    Swal.fire('Berhasil', 'Semua data di tabel berhasil di-reset!', 'success');
                    currentUploadedData = { listPlus: [], listMinus: [], grandTotalPlus: 0, grandTotalMinus: 0 };
                    document.getElementById('uploadedItemsContainer').innerHTML = `<div class="status-info">Belum ada data item yang di-upload.</div>`;
                } else {
                    Swal.fire('Gagal', 'Gagal mereset data: ' + (result.message || 'Terjadi kesalahan server'), 'error');
                }
            } catch (e) {
                Swal.fire('Error', 'Terjadi kesalahan koneksi saat mereset isi tabel.', 'error');
            } finally {
                loader.style.display = 'none';
            }
        }

        function copyUploadedItemsTable() {
            if (currentUploadedData.listPlus.length === 0 && currentUploadedData.listMinus.length === 0) {
                Swal.fire('Peringatan', "Silakan klik tombol 'Lihat Item Yg Di Upload' terlebih dahulu sebelum menyalin data!", 'warning');
                return;
            }

            let text = "```\n";
            text += "LIST ITEM YANG DI SO\n";

            text += "\nLIST ITEM PLUS (+)\n";
            text += "--------------------------------------------------\n";
            if (currentUploadedData.listPlus.length > 0) {
                currentUploadedData.listPlus.forEach(i => {
                    text += `${i.plumd} | ${i.desc} | Harga: ${formatRupiah(i.harga)} | Selisih: +${i.selisih} | Total: +${formatRupiah(i.totalHarga)}\n`;
                });
                text += `\nGRAND TOTAL PLUS : +${formatRupiah(currentUploadedData.grandTotalPlus)}\n`;
            } else {
                text += "Tidak ada item plus.\n";
            }

            text += "\nLIST ITEM MINUS (-)\n";
            text += "--------------------------------------------------\n";
            if (currentUploadedData.listMinus.length > 0) {
                currentUploadedData.listMinus.forEach(i => {
                    text += `${i.plumd} | ${i.desc} | Harga: ${formatRupiah(i.harga)} | Selisih: ${i.selisih} | Total: -${formatRupiah(Math.abs(i.totalHarga))}\n`;
                });
                text += `\nGRAND TOTAL MINUS : -${formatRupiah(Math.abs(currentUploadedData.grandTotalMinus))}\n`;
            } else {
                text += "Tidak ada item minus.\n";
            }
            text += "```";

            const textArea = document.createElement("textarea");
            textArea.value = text;
            textArea.style.position = "fixed";
            textArea.style.left = "-9999px";
            document.body.appendChild(textArea);
            textArea.select();
            document.execCommand('copy');
            document.body.removeChild(textArea);
            Swal.fire('Berhasil', 'Isi data tabel berhasil disalin ke clipboard!', 'success');
        }

        async function loadUploadedItems() {
            const container = document.getElementById('uploadedItemsContainer');
            container.innerHTML = "";

            const loader = document.getElementById('loader');
            loader.style.display = 'block';

            try {
                const formData = new FormData();
                formData.append('action', 'get_user_uploaded_items');

                const response = await fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();

                if (result.success) {
                    const rows = result.data || [];
                    if (rows.length === 0) {
                        currentUploadedData = { listPlus: [], listMinus: [], grandTotalPlus: 0, grandTotalMinus: 0 };
                        container.innerHTML = `<div class="status-info">Belum ada data item yang di-upload.</div>`;
                        return;
                    }

                    const mapFullData = new Map();
                    fullData.forEach(item => {
                        let price = parseFloat(item.HARGA || item.HARGA_JUAL || item.HRGJL || item.PRICE || 0);
                        mapFullData.set(item.PLUMD, {
                            desc: item.DESC2 || '-',
                            harga: price
                        });
                    });

                    const listPlus = [];
                    const listMinus = [];

                    rows.forEach(r => {
                        const selisih = parseInt(r.selisih) || 0;
                        const itemInfo = mapFullData.get(r.plumd) || { desc: '-', harga: 0 };
                        const harga = itemInfo.harga;
                        const totalHarga = harga * selisih;

                        const itemObj = {
                            id: r.id,
                            plumd: r.plumd,
                            desc: itemInfo.desc,
                            harga: harga,
                            selisih: selisih,
                            totalHarga: totalHarga
                        };

                        if (selisih > 0) {
                            listPlus.push(itemObj);
                        } else if (selisih < 0) {
                            listMinus.push(itemObj);
                        }
                    });

                    let grandTotalPlus = 0;
                    let grandTotalMinus = 0;

                    let html = "";

                    if (listPlus.length > 0) {
                        html += `<div class="selisih-title">LIST ITEM PLUS (+)</div>`;
                        html += `<div class="table-container"><table><thead><tr><th>PLU</th><th>Deskripsi</th><th class="text-right">Harga Normal</th><th>Selisih</th><th class="text-right">Total</th><th class="action-cell">Aksi</th></tr></thead><tbody>`;
                        listPlus.forEach(i => {
                            grandTotalPlus += i.totalHarga;
                            let formattedHarga = formatRupiah(i.harga);
                            let formattedTotal = "+" + formatRupiah(i.totalHarga);
                            html += `<tr data-plumd="${i.plumd}"><td>${i.plumd}</td><td>${i.desc}</td><td class="text-right">${formattedHarga}</td><td>+${i.selisih}</td><td class="text-right" style="color:#27ae60; font-weight:bold;">${formattedTotal}</td><td class="action-cell"><button class="btn-action-edit" onclick="editUploadedItem(${i.id}, '${i.plumd}', ${i.selisih})">Edit</button><button class="btn-action-delete" onclick="deleteUploadedItem(${i.id}, '${i.plumd}')">Hapus</button></td></tr>`;
                        });
                        html += `<tr style="background:#f2f2f2; font-weight:bold;"><td colspan="4" class="text-right">GRAND TOTAL PLUS :</td><td class="text-right" style="color:#27ae60;">+${formatRupiah(grandTotalPlus)}</td><td></td></tr>`;
                        html += `</tbody></table></div>`;
                    } else {
                        html += `<div class="selisih-title">LIST ITEM PLUS (+)</div><div class="status-info">Tidak ada item plus.</div>`;
                    }

                    if (listMinus.length > 0) {
                        html += `<div class="selisih-title">LIST ITEM MINUS (-)</div>`;
                        html += `<div class="table-container"><table><thead><tr><th>PLU</th><th>Deskripsi</th><th class="text-right">Harga Normal</th><th>Selisih</th><th class="text-right">Total</th><th class="action-cell">Aksi</th></tr></thead><tbody>`;
                        listMinus.forEach(i => {
                            grandTotalMinus += i.totalHarga;
                            let formattedHarga = formatRupiah(i.harga);
                            let formattedTotal = "-" + formatRupiah(Math.abs(i.totalHarga));
                            html += `<tr data-plumd="${i.plumd}"><td>${i.plumd}</td><td>${i.desc}</td><td class="text-right">${formattedHarga}</td><td>${i.selisih}</td><td class="text-right" style="color:#e74c3c; font-weight:bold;">${formattedTotal}</td><td class="action-cell"><button class="btn-action-edit" onclick="editUploadedItem(${i.id}, '${i.plumd}', ${i.selisih})">Edit</button><button class="btn-action-delete" onclick="deleteUploadedItem(${i.id}, '${i.plumd}')">Hapus</button></td></tr>`;
                        });
                        html += `<tr style="background:#f2f2f2; font-weight:bold;"><td colspan="4" class="text-right">GRAND TOTAL MINUS :</td><td class="text-right" style="color:#e74c3c;">-${formatRupiah(Math.abs(grandTotalMinus))}</td><td></td></tr>`;
                        html += `</tbody></table></div>`;
                    } else {
                        html += `<div class="selisih-title">LIST ITEM MINUS (-)</div><div class="status-info">Tidak ada item minus.</div>`;
                    }

                    currentUploadedData = {
                        listPlus: listPlus,
                        listMinus: listMinus,
                        grandTotalPlus: grandTotalPlus,
                        grandTotalMinus: grandTotalMinus
                    };

                    container.innerHTML = html;
                } else {
                    Swal.fire('Gagal', 'Gagal mengambil data: ' + (result.message || 'Terjadi kesalahan server'), 'error');
                }
            } catch (e) {
                Swal.fire('Error', 'Terjadi kesalahan koneksi saat mengambil data item di-upload.', 'error');
            } finally {
                loader.style.display = 'none';
            }
        }

        async function resetUserProgress() {
            const confirmRes = await Swal.fire({
                title: 'Reset Inputan?',
                text: 'Apakah kamu yakin ingin menghapus SEMUA hasil progres inputan stok untuk akun ini ? Tindakan ini tidak dapat dibatalkan ...',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                confirmButtonText: 'Ya, Reset!',
                cancelButtonText: 'Batal'
            });

            if (confirmRes.isConfirmed) {
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
                        Swal.fire('Berhasil', 'Semua progres inputan kamu berhasil direset !', 'success');
                        switchTab(1);
                    } else {
                        Swal.fire('Gagal', 'Gagal meriset data, Coba beberapa saat lagi ...', 'error');
                    }
                } catch (e) {
                    Swal.fire('Error', 'Terjadi kesalahan koneksi saat meriset data ...', 'error');
                }
            }
        }

        window.addEventListener('DOMContentLoaded', () => {
            const savedOfflineData = localStorage.getItem('so_full_data');
            if (savedOfflineData) {
                const parsed = JSON.parse(savedOfflineData);
                fullData = parsed;
                populateFilters();
                loadSavedFilter();
                
                const statusData = document.getElementById('statusData');
                if (statusData) {
                    statusData.innerText = `Menggunakan data tersimpan (${fullData.length} item loaded)`;
                    statusData.style.color = "var(--success)";
                }
            }
        });
    </script>
</body>
</html>