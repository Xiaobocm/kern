<?php
session_start();
date_default_timezone_set('Asia/Shanghai');

$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'flag_db';

$conn = new mysqli($db_host, $db_user, $db_pass);
if ($conn->connect_error) {
    die('数据库连接失败: ' . $conn->connect_error);
}

$conn->query("CREATE DATABASE IF NOT EXISTS $db_name");
$conn->select_db($db_name);

$tables = [
    "CREATE TABLE IF NOT EXISTS flags (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        content TEXT,
        tags VARCHAR(255),
        target_date DATE NOT NULL,
        completed BOOLEAN DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )",
    "CREATE TABLE IF NOT EXISTS goals (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        description TEXT,
        progress INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )",
    "CREATE TABLE IF NOT EXISTS settings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        setting_key VARCHAR(255) UNIQUE NOT NULL,
        setting_value TEXT,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )"
];
foreach ($tables as $sql) {
    $conn->query($sql);
}

$conn->query("INSERT IGNORE INTO settings (setting_key, setting_value) VALUES 
    ('backgrounds', '[]'), 
    ('current_background', ''),
    ('theme', 'light'),
    ('language', 'zh')");

function getSetting($key) {
    global $conn;
    $res = $conn->query("SELECT setting_value FROM settings WHERE setting_key='$key'");
    if ($row = $res->fetch_assoc()) {
        return $row['setting_value'];
    }
    return null;
}

function setSetting($key, $value) {
    global $conn;
    $value = $conn->real_escape_string($value);
    $conn->query("INSERT INTO settings (setting_key, setting_value) VALUES ('$key', '$value') 
                  ON DUPLICATE KEY UPDATE setting_value='$value'");
}

function getDeviceType() {
    $ua = $_SERVER['HTTP_USER_AGENT'];
    if (strpos($ua, 'Mobile') !== false) return '手机';
    if (strpos($ua, 'iPad') !== false || strpos($ua, 'Tablet') !== false) return '平板';
    return '电脑';
}

function timeToEndOfDay() {
    $diff = strtotime('tomorrow 00:00:00') - time();
    $h = floor($diff / 3600);
    $m = floor(($diff % 3600) / 60);
    return "{$h}h {$m}m";
}

function getFlagsByDate($date, $includeFuture = true) {
    global $conn;
    $date = $conn->real_escape_string($date);
    $sql = "SELECT * FROM flags WHERE target_date = '$date'";
    if (!$includeFuture) {
        $sql .= " AND target_date <= CURDATE()";
    }
    $sql .= " ORDER BY completed, id DESC";
    return $conn->query($sql);
}

function getFlagStats($date, $includeFuture = true) {
    global $conn;
    $date = $conn->real_escape_string($date);
    $sql = "SELECT COUNT(*) as total, SUM(completed) as done FROM flags WHERE target_date = '$date'";
    if (!$includeFuture) {
        $sql .= " AND target_date <= CURDATE()";
    }
    $res = $conn->query($sql)->fetch_assoc();
    $total = $res['total'] ?: 0;
    $done = $res['done'] ?: 0;
    $percent = $total > 0 ? round($done / $total * 100, 1) : 0;
    return ['total' => $total, 'done' => $done, 'percent' => $percent, 'unpercent' => 100 - $percent];
}

function getMonthlyStats($year, $month) {
    global $conn;
    $start = "$year-$month-01";
    $end = date('Y-m-t', strtotime($start));
    $res = $conn->query("SELECT target_date, COUNT(*) as total, SUM(completed) as done 
                         FROM flags WHERE target_date BETWEEN '$start' AND '$end' GROUP BY target_date");
    $stats = [];
    while ($row = $res->fetch_assoc()) {
        $stats[$row['target_date']] = $row;
    }
    return $stats;
}

$langs = json_decode(file_get_contents('lang.txt'), true);
if (!$langs) {
    die('语言包加载失败，请检查 lang.txt 文件');
}

$allLangs = array_keys($langs);
$langCode = $_COOKIE['lang'] ?? getSetting('language') ?: 'zh';
$theme = $_COOKIE['theme'] ?? getSetting('theme') ?: 'light';
$L = $langs[$langCode] ?? $langs['zh'];

if (isset($_GET['ajax'])) {
    $action = $_GET['action'] ?? '';
    if ($action == 'get_day_flags') {
        $date = $_GET['date'] ?? date('Y-m-d');
        $flags = getFlagsByDate($date, true);
        $output = [];
        while ($f = $flags->fetch_assoc()) {
            $output[] = [
                'id' => $f['id'],
                'title' => $f['title'],
                'content' => $f['content'],
                'tags' => $f['tags'],
                'completed' => (bool)$f['completed']
            ];
        }
        header('Content-Type: application/json');
        echo json_encode($output);
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['ajax'])) {
    $action = $_POST['action'] ?? '';
    if ($action == 'set_theme') {
        $theme = $_POST['theme'] ?? 'light';
        setSetting('theme', $theme);
        setcookie('theme', $theme, time() + 31536000, '/');
        echo json_encode(['status' => 'ok']);
        exit;
    }
    if ($action == 'set_lang') {
        $lang = $_POST['lang'] ?? 'zh';
        setSetting('language', $lang);
        setcookie('lang', $lang, time() + 31536000, '/');
        echo json_encode(['status' => 'ok']);
        exit;
    }
}

$message = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST' && !isset($_POST['ajax'])) {
    $action = $_POST['action'] ?? '';
    if ($action == 'add_flag') {
        $title = trim($_POST['title'] ?? '');
        if ($title) {
            $content = $_POST['content'] ?? '';
            $tags = $_POST['tags'] ?? '';
            $date = $_POST['target_date'] ?? date('Y-m-d');
            $conn->query("INSERT INTO flags (title, content, tags, target_date) VALUES ('$title', '$content', '$tags', '$date')");
            $message = $L['save'] . ' ' . ($L['success'] ?? '');
        }
    } elseif ($action == 'edit_flag') {
        $id = (int)$_POST['id'];
        $title = $_POST['title'];
        $content = $_POST['content'];
        $tags = $_POST['tags'];
        $completed = isset($_POST['completed']) ? 1 : 0;
        $conn->query("UPDATE flags SET title='$title', content='$content', tags='$tags', completed='$completed' WHERE id=$id");
        $message = $L['save'] . ' ' . ($L['success'] ?? '');
    } elseif ($action == 'toggle_complete') {
        $id = (int)$_POST['id'];
        $conn->query("UPDATE flags SET completed = NOT completed WHERE id=$id");
        $message = $L['save'] . ' ' . ($L['success'] ?? '');
    } elseif ($action == 'delete_flag') {
        $id = (int)$_POST['id'];
        $conn->query("DELETE FROM flags WHERE id=$id");
        $message = $L['delete'] . ' ' . ($L['success'] ?? '');
    } elseif ($action == 'save_goal') {
        $id = (int)$_POST['id'];
        $title = $_POST['title'];
        $desc = $_POST['description'];
        $progress = (int)$_POST['progress'];
        if ($id > 0) {
            $conn->query("UPDATE goals SET title='$title', description='$desc', progress='$progress' WHERE id=$id");
        } else {
            $conn->query("INSERT INTO goals (title, description, progress) VALUES ('$title', '$desc', '$progress')");
        }
        $message = $L['save'] . ' ' . ($L['success'] ?? '');
    } elseif ($action == 'upload_bg') {
        if (isset($_FILES['bg_image']) && $_FILES['bg_image']['error'] == 0) {
            $dir = 'uploads/backgrounds/';
            if (!is_dir($dir)) mkdir($dir, 0777, true);
            $ext = pathinfo($_FILES['bg_image']['name'], PATHINFO_EXTENSION);
            $filename = uniqid() . '.' . $ext;
            move_uploaded_file($_FILES['bg_image']['tmp_name'], $dir . $filename);
            $list = json_decode(getSetting('backgrounds'), true) ?: [];
            $list[] = $dir . $filename;
            setSetting('backgrounds', json_encode($list));
            if (!getSetting('current_background')) {
                setSetting('current_background', $dir . $filename);
            }
            $message = $L['upload'] . ' ' . ($L['success'] ?? '');
        }
    } elseif ($action == 'switch_bg') {
        $index = (int)$_POST['index'];
        $list = json_decode(getSetting('backgrounds'), true) ?: [];
        if (isset($list[$index])) {
            setSetting('current_background', $list[$index]);
            $message = $L['switch'] . ' ' . ($L['success'] ?? '');
        }
    } elseif ($action == 'delete_bg') {
        $index = (int)$_POST['index'];
        $list = json_decode(getSetting('backgrounds'), true) ?: [];
        if (isset($list[$index])) {
            $file = $list[$index];
            if (file_exists($file)) unlink($file);
            unset($list[$index]);
            $list = array_values($list);
            setSetting('backgrounds', json_encode($list));
            if (getSetting('current_background') == $file) {
                setSetting('current_background', $list[0] ?? '');
            }
            $message = $L['delete'] . ' ' . ($L['success'] ?? '');
        }
    } elseif ($action == 'backup') {
        $backupDir = 'backup/';
        if (!is_dir($backupDir)) mkdir($backupDir, 0777, true);
        $filename = $backupDir . 'backup_' . date('Ymd_His') . '.sql';
        $command = "mysqldump -u $db_user -p$db_pass $db_name > $filename 2>&1";
        exec($command, $output, $return);
        if (file_exists($filename) && filesize($filename) > 0) {
            $message = $L['backup_success'] . ': ' . basename($filename);
        } else {
            $message = $L['backup_fail'];
        }
    } elseif ($action == 'scan') {
        $message = ($L['scan'] ?? 'Scan') . ' ' . ($L['success'] ?? '');
    }
}

$page = $_GET['page'] ?? 'home';
$selectedDate = $_GET['date'] ?? date('Y-m-d');
$viewType = $_GET['view'] ?? 'day';
$includeFuture = isset($_GET['include_future']) ? (bool)$_GET['include_future'] : true;
$currentBg = getSetting('current_background');
$bgStyle = $currentBg ? "background-image: url('$currentBg'); background-size: cover; background-position: center;" : '';

$goal = $conn->query("SELECT * FROM goals LIMIT 1")->fetch_assoc();

$langNames = [
    'zh' => '中文',
    'zh-hk' => '中文(繁體)',
    'en' => 'English',
    'ja' => '日本語',
    'ko' => '한국어'
];
?>
<!DOCTYPE html>
<html lang="<?php echo $langCode; ?>" data-theme="<?php echo $theme; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.5, user-scalable=yes">
    <title><?php echo $L['app_name']; ?></title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --bg-primary: rgba(255,255,255,0.55);
            --bg-secondary: rgba(255,255,255,0.25);
            --text-primary: #1c1c1e;
            --text-secondary: #3a3a3c;
            --border-color: rgba(255,255,255,0.3);
            --shadow-color: rgba(0,0,0,0.1);
            --card-bg: rgba(255,255,255,0.2);
            --input-bg: rgba(255,255,255,0.3);
            --accent: #007aff;
            --accent-hover: #005bbf;
            --success: #34c759;
            --danger: #ff3b30;
            --blur: 20px;
            --calendar-size: 60px;
            transition: background 0.3s, color 0.3s, border-color 0.3s, box-shadow 0.3s;
        }
        [data-theme="dark"] {
            --bg-primary: rgba(30,30,30,0.6);
            --bg-secondary: rgba(20,20,20,0.4);
            --text-primary: #f0f0f0;
            --text-secondary: #b0b0b0;
            --border-color: rgba(255,255,255,0.1);
            --shadow-color: rgba(0,0,0,0.4);
            --card-bg: rgba(40,40,40,0.3);
            --input-bg: rgba(50,50,50,0.4);
            --accent: #0a84ff;
            --accent-hover: #409cff;
        }
        @media (prefers-color-scheme: dark) {
            [data-theme="system"] {
                --bg-primary: rgba(30,30,30,0.6);
                --bg-secondary: rgba(20,20,20,0.4);
                --text-primary: #f0f0f0;
                --text-secondary: #b0b0b0;
                --border-color: rgba(255,255,255,0.1);
                --shadow-color: rgba(0,0,0,0.4);
                --card-bg: rgba(40,40,40,0.3);
                --input-bg: rgba(50,50,50,0.4);
                --accent: #0a84ff;
                --accent-hover: #409cff;
            }
        }
        * { margin:0; padding:0; box-sizing:border-box; font-family: -apple-system, BlinkMacSystemFont, "SF Pro Display", "Segoe UI", Roboto, sans-serif; }
        body {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            background: #e9ecf0;
            <?php echo $bgStyle; ?>
            background-attachment: fixed;
            padding: 16px;
            transition: background 0.3s;
        }
        [data-theme="dark"] body { background-color: #1c1c1e; }
        [data-theme="system"] body { background-color: #e9ecf0; }
        @media (prefers-color-scheme: dark) {
            [data-theme="system"] body { background-color: #1c1c1e; }
        }
        .glass {
            background: var(--bg-primary);
            backdrop-filter: blur(var(--blur));
            -webkit-backdrop-filter: blur(var(--blur));
            border-radius: 20px;
            border: 1px solid var(--border-color);
            box-shadow: 0 8px 32px var(--shadow-color);
            padding: 20px;
            margin-bottom: 16px;
            transition: background 0.3s, border 0.3s, box-shadow 0.3s;
        }
        .glass-card {
            background: var(--card-bg);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border-radius: 16px;
            border: 1px solid var(--border-color);
            padding: 16px;
            margin-bottom: 12px;
            transition: background 0.3s, border 0.3s;
        }
        .container { max-width: 1200px; margin: 0 auto; width:100%; flex:1; }
        nav {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 8px;
            margin-bottom: 20px;
        }
        nav .brand {
            font-weight: 600;
            font-size: 1.2rem;
            margin-right: 12px;
            color: var(--text-primary);
            transition: color 0.3s;
        }
        nav a, nav button {
            text-decoration: none;
            padding: 8px 16px;
            border-radius: 30px;
            background: var(--bg-secondary);
            backdrop-filter: blur(8px);
            border: 1px solid var(--border-color);
            color: var(--text-primary);
            font-weight: 500;
            font-size: 14px;
            transition: all 0.3s;
            cursor: pointer;
            font-family: inherit;
        }
        nav a:hover, nav a.active, nav button:hover {
            background: var(--accent);
            color: #fff;
            border-color: var(--accent);
        }
        nav .actions {
            display: flex;
            gap: 6px;
            margin-left: auto;
            flex-wrap: wrap;
            align-items: center;
        }
        nav .actions select {
            padding: 6px 12px;
            border-radius: 30px;
            border: 1px solid var(--border-color);
            background: var(--bg-secondary);
            backdrop-filter: blur(4px);
            color: var(--text-primary);
            font-size: 13px;
            cursor: pointer;
            transition: all 0.3s;
            outline: none;
        }
        nav .actions select:hover {
            background: var(--accent);
            color: #fff;
            border-color: var(--accent);
        }
        nav .actions select option {
            background: #fff;
            color: #000;
        }
        .theme-buttons {
            display: flex;
            gap: 4px;
            background: var(--bg-secondary);
            border-radius: 30px;
            padding: 4px;
            border: 1px solid var(--border-color);
        }
        .theme-buttons button {
            border: none;
            background: transparent;
            padding: 4px 10px;
            border-radius: 30px;
            font-size: 13px;
            color: var(--text-secondary);
            cursor: pointer;
            transition: all 0.2s;
        }
        .theme-buttons button.active {
            background: var(--accent);
            color: #fff;
        }
        .theme-buttons button:hover {
            background: var(--accent);
            color: #fff;
        }
        h1, h2, h3 { font-weight: 600; color: var(--text-primary); letter-spacing: -0.3px; transition: color 0.3s; }
        .flex { display: flex; flex-wrap: wrap; gap: 12px; align-items: center; }
        .flex-between { justify-content: space-between; }
        .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
        .grid-3 { display: grid; grid-template-columns: repeat(auto-fill, minmax(180px,1fr)); gap: 12px; }
        .btn {
            display: inline-block; padding: 6px 14px; border-radius: 30px; border: 1px solid var(--border-color);
            background: var(--bg-secondary); backdrop-filter: blur(4px);
            color: var(--text-primary); font-weight: 500; cursor: pointer; transition: all 0.3s;
            text-decoration: none; font-size: 14px;
        }
        .btn-primary { background: var(--accent); color: #fff; border-color: var(--accent); }
        .btn-success { background: var(--success); color: #fff; border-color: var(--success); }
        .btn-danger { background: var(--danger); color: #fff; border-color: var(--danger); }
        .btn:hover { opacity: 0.8; transform: scale(0.97); }
        .form-group { margin-bottom: 14px; }
        .form-group label { display: block; margin-bottom: 4px; font-weight: 500; color: var(--text-secondary); font-size: 14px; transition: color 0.3s; }
        .form-control {
            width: 100%; padding: 10px 14px; border-radius: 12px; border: 1px solid var(--border-color);
            background: var(--input-bg); backdrop-filter: blur(4px);
            font-size: 15px; color: var(--text-primary);
            transition: all 0.3s;
        }
        .form-control:focus { outline: none; border-color: var(--accent); box-shadow: 0 0 0 3px rgba(0,122,255,0.2); }
        .flag-item {
            display: flex; flex-wrap: wrap; align-items: center; gap: 10px;
            padding: 12px 0; border-bottom: 1px solid var(--border-color);
            transition: border-color 0.3s;
        }
        .flag-item:last-child { border-bottom: none; }
        .flag-title { font-weight: 600; flex: 1; min-width: 120px; color: var(--text-primary); transition: color 0.3s; }
        .flag-meta { font-size: 14px; color: var(--text-secondary); transition: color 0.3s; }
        .completed .flag-title { text-decoration: line-through; opacity: 0.6; }
        .stat-number { font-size: 2.2rem; font-weight: 700; color: var(--text-primary); transition: color 0.3s; }
        .stat-label { font-size: 0.85rem; color: var(--text-secondary); transition: color 0.3s; }
        .pie-container { max-width: 280px; margin: 16px auto; }
        .calendar {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 8px;
            margin-top: 12px;
        }
        .calendar-day {
            aspect-ratio: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            border-radius: 12px;
            background: var(--card-bg);
            font-size: 16px;
            color: var(--text-primary);
            min-height: var(--calendar-size);
            transition: all 0.3s;
            position: relative;
            cursor: pointer;
        }
        .calendar-day:hover {
            transform: scale(1.02);
            background: var(--bg-secondary);
        }
        .calendar-day .day-number {
            font-weight: 500;
        }
        .calendar-day .status-symbol {
            font-size: 20px;
            line-height: 1;
            margin-top: 2px;
        }
        .calendar-day.has-flag-all-done {
            background: rgba(52, 199, 89, 0.25);
            border: 1px solid var(--success);
        }
        .calendar-day.has-flag-partial {
            background: rgba(255, 149, 0, 0.2);
            border: 1px solid #ff9500;
        }
        .calendar-day.today {
            border: 2px solid var(--accent);
        }
        .calendar-day .count-badge {
            font-size: 11px;
            opacity: 0.7;
            position: absolute;
            bottom: 2px;
            right: 4px;
        }
        .device-badge {
            position: fixed; bottom: 16px; right: 16px;
            background: var(--bg-secondary); backdrop-filter: blur(10px);
            border: 1px solid var(--border-color);
            color: var(--text-primary);
            padding: 4px 14px; border-radius: 30px;
            font-size: 13px; z-index: 999;
            transition: all 0.3s;
        }
        .progress-bar {
            height: 8px; border-radius: 20px; background: var(--card-bg); overflow: hidden; margin-top: 8px;
            transition: background 0.3s;
        }
        .progress-bar .fill {
            height: 100%; border-radius: 20px; background: linear-gradient(90deg, var(--accent), var(--success));
            transition: width 0.3s, background 0.3s;
        }
        .daily-flags-container {
            margin-top: 20px;
            padding-top: 16px;
            border-top: 1px solid var(--border-color);
        }
        .daily-flags-container h4 {
            margin-bottom: 12px;
        }
        @media (max-width: 768px) {
            body { padding: 10px; }
            .grid-2 { grid-template-columns: 1fr; }
            .glass { padding: 14px; }
            nav .brand { font-size: 1rem; }
            nav a, nav button { padding: 6px 12px; font-size: 13px; }
            .stat-number { font-size: 1.8rem; }
            .calendar-day { min-height: 50px; font-size: 14px; }
            .theme-buttons button { padding: 2px 8px; font-size: 12px; }
        }
        @media (max-width: 480px) {
            .flag-item { flex-direction: column; align-items: stretch; }
            .calendar-day { min-height: 44px; font-size: 13px; }
            .calendar-day .status-symbol { font-size: 16px; }
            .theme-buttons { flex-wrap: wrap; }
        }
    </style>
</head>
<body>
<div class="container">
    <nav>
        <span class="brand" data-lang="app_name"><?php echo $L['app_name']; ?></span>
        <a href="?page=home" class="<?php echo $page=='home'?'active':''; ?>" data-lang="today"><?php echo $L['today']; ?></a>
        <a href="?page=add" class="<?php echo $page=='add'?'active':''; ?>" data-lang="add"><?php echo $L['add']; ?></a>
        <a href="?page=stats" class="<?php echo $page=='stats'?'active':''; ?>" data-lang="statistics"><?php echo $L['statistics']; ?></a>
        <a href="?page=settings" class="<?php echo $page=='settings'?'active':''; ?>" data-lang="settings"><?php echo $L['settings']; ?></a>
        <div class="actions">
            <div class="theme-buttons" id="themeButtons">
                <button data-theme="light" class="<?php echo $theme=='light'?'active':''; ?>" title="<?php echo $L['theme_light']; ?>">☀️</button>
                <button data-theme="dark" class="<?php echo $theme=='dark'?'active':''; ?>" title="<?php echo $L['theme_dark']; ?>">🌙</button>
                <button data-theme="system" class="<?php echo $theme=='system'?'active':''; ?>" title="<?php echo $L['theme_system']; ?>">💻</button>
            </div>
            <select id="langSelect">
                <?php foreach ($allLangs as $code): ?>
                <option value="<?php echo $code; ?>" <?php echo $code==$langCode?'selected':''; ?>>
                    <?php echo $langNames[$code] ?? $code; ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
    </nav>

    <?php if ($message): ?>
    <div class="glass" style="background: var(--bg-secondary); border-color: var(--accent);">
        <?php echo htmlspecialchars($message); ?>
    </div>
    <?php endif; ?>

    <?php if ($page == 'home'): ?>
    <div class="glass">
        <div class="flex flex-between">
            <h2><span data-lang="today"><?php echo $L['today']; ?></span> · <?php echo $selectedDate; ?></h2>
            <div style="font-size:15px; color:var(--text-secondary);">
                <span data-lang="remaining_today"><?php echo $L['remaining_today']; ?></span>: <?php echo timeToEndOfDay(); ?>
            </div>
        </div>
        <?php if ($goal): ?>
        <div class="glass-card" style="margin-top:12px;">
            <div class="flex flex-between">
                <div><strong><?php echo htmlspecialchars($goal['title']); ?></strong> <span style="color:var(--text-secondary); font-size:14px;"><span data-lang="progress"><?php echo $L['progress']; ?></span> <?php echo $goal['progress']; ?>%</span></div>
                <a href="?page=goal" class="btn" data-lang="edit"><?php echo $L['edit']; ?></a>
            </div>
            <div class="progress-bar"><div class="fill" style="width:<?php echo $goal['progress']; ?>%;"></div></div>
            <div style="margin-top:6px; font-size:14px; color:var(--text-secondary);"><?php echo nl2br(htmlspecialchars($goal['description'] ?? '')); ?></div>
        </div>
        <?php endif; ?>

        <?php 
        $stats = getFlagStats($selectedDate, $includeFuture);
        $total = $stats['total']; $done = $stats['done']; $percent = $stats['percent']; $unpercent = $stats['unpercent'];
        ?>
        <div class="grid-2" style="margin:16px 0;">
            <div class="glass-card"><div class="stat-label" data-lang="total"><?php echo $L['total']; ?></div><div class="stat-number"><?php echo $total; ?></div></div>
            <div class="glass-card"><div class="stat-label" data-lang="completed"><?php echo $L['completed']; ?></div><div class="stat-number" style="color:var(--success);"><?php echo $done; ?></div></div>
            <div class="glass-card"><div class="stat-label" data-lang="completion_rate"><?php echo $L['completion_rate']; ?></div><div class="stat-number"><?php echo $percent; ?>%</div></div>
            <div class="glass-card"><div class="stat-label" data-lang="uncompleted"><?php echo $L['uncompleted']; ?></div><div class="stat-number" style="color:var(--danger);"><?php echo $total - $done; ?></div></div>
        </div>

        <div class="flex flex-between" style="margin-bottom:12px;">
            <div class="flex">
                <a href="?page=home&date=<?php echo date('Y-m-d', strtotime('-1 day', strtotime($selectedDate))); ?>" class="btn">‹</a>
                <a href="?page=home&date=<?php echo date('Y-m-d'); ?>" class="btn" data-lang="today"><?php echo $L['today']; ?></a>
                <a href="?page=home&date=<?php echo date('Y-m-d', strtotime('+1 day', strtotime($selectedDate))); ?>" class="btn">›</a>
            </div>
            <label style="font-size:14px; color:var(--text-secondary);">
                <input type="checkbox" <?php echo $includeFuture?'checked':''; ?> 
                       onchange="location.href='?page=home&date=<?php echo $selectedDate; ?>&include_future='+this.checked">
                <span data-lang="include_future"><?php echo $L['include_future']; ?></span>
            </label>
        </div>

        <?php 
        $flags = getFlagsByDate($selectedDate, $includeFuture);
        if ($flags && $flags->num_rows > 0): 
        ?>
            <?php while ($f = $flags->fetch_assoc()): ?>
            <div class="flag-item <?php echo $f['completed']?'completed':''; ?>">
                <div style="flex:1;">
                    <div class="flag-title"><?php echo htmlspecialchars($f['title']); ?></div>
                    <div class="flag-meta"><?php echo nl2br(htmlspecialchars($f['content'])); ?></div>
                    <div class="flag-meta">#<?php echo htmlspecialchars($f['tags']); ?></div>
                </div>
                <div class="flex" style="gap:6px;">
                    <form method="POST" style="display:inline;">
                        <input type="hidden" name="action" value="toggle_complete">
                        <input type="hidden" name="id" value="<?php echo $f['id']; ?>">
                        <?php if ($f['completed']): ?>
                            <button class="btn btn-primary" title="<?php echo $L['undo']; ?>">○</button>
                        <?php else: ?>
                            <button class="btn btn-success" title="<?php echo $L['mark_done']; ?>">✔</button>
                        <?php endif; ?>
                    </form>
                    <a href="?page=edit&id=<?php echo $f['id']; ?>&date=<?php echo $selectedDate; ?>" class="btn" data-lang="edit"><?php echo $L['edit']; ?></a>
                    <form method="POST" style="display:inline;" onsubmit="return confirm('<?php echo $L['delete']; ?>?');">
                        <input type="hidden" name="action" value="delete_flag">
                        <input type="hidden" name="id" value="<?php echo $f['id']; ?>">
                        <button class="btn btn-danger" data-lang="delete"><?php echo $L['delete']; ?></button>
                    </form>
                </div>
            </div>
            <?php endwhile; ?>
        <?php else: ?>
            <p style="color:var(--text-secondary);" data-lang="no_flags"><?php echo $L['no_flags']; ?></p>
        <?php endif; ?>

        <div style="margin-top:20px; padding-top:16px; border-top:1px solid var(--border-color); font-size:13px; color:var(--text-secondary);">
            <span data-lang="device"><?php echo $L['device']; ?></span>: <?php echo getDeviceType(); ?> · UA: <?php echo htmlspecialchars($_SERVER['HTTP_USER_AGENT']); ?>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($page == 'add'): ?>
    <div class="glass">
        <h2><span data-lang="add"><?php echo $L['add']; ?></span> Flag</h2>
        <form method="POST">
            <input type="hidden" name="action" value="add_flag">
            <div class="form-group"><label data-lang="title"><?php echo $L['title']; ?> *</label><input class="form-control" name="title" required></div>
            <div class="form-group"><label data-lang="content"><?php echo $L['content']; ?></label><textarea class="form-control" name="content" rows="3"></textarea></div>
            <div class="form-group"><label data-lang="tags"><?php echo $L['tags']; ?></label><input class="form-control" name="tags" placeholder="work, study"></div>
            <div class="form-group"><label data-lang="target_date"><?php echo $L['target_date']; ?></label><input class="form-control" type="date" name="target_date" value="<?php echo date('Y-m-d'); ?>"></div>
            <button class="btn btn-primary" type="submit" data-lang="save"><?php echo $L['save']; ?></button>
        </form>
    </div>
    <?php endif; ?>

    <?php if ($page == 'edit'): 
        $editId = (int)$_GET['id'];
        $editData = $conn->query("SELECT * FROM flags WHERE id=$editId")->fetch_assoc();
        if ($editData):
    ?>
    <div class="glass">
        <h2><span data-lang="edit"><?php echo $L['edit']; ?></span> Flag</h2>
        <form method="POST">
            <input type="hidden" name="action" value="edit_flag">
            <input type="hidden" name="id" value="<?php echo $editId; ?>">
            <div class="form-group"><label data-lang="title"><?php echo $L['title']; ?></label><input class="form-control" name="title" value="<?php echo htmlspecialchars($editData['title']); ?>" required></div>
            <div class="form-group"><label data-lang="content"><?php echo $L['content']; ?></label><textarea class="form-control" name="content" rows="3"><?php echo htmlspecialchars($editData['content']); ?></textarea></div>
            <div class="form-group"><label data-lang="tags"><?php echo $L['tags']; ?></label><input class="form-control" name="tags" value="<?php echo htmlspecialchars($editData['tags']); ?>"></div>
            <div class="form-group"><label><input type="checkbox" name="completed" <?php echo $editData['completed']?'checked':''; ?>> <span data-lang="completed"><?php echo $L['completed']; ?></span></label></div>
            <button class="btn btn-primary" type="submit" data-lang="save"><?php echo $L['save']; ?></button>
            <a href="?page=home&date=<?php echo $_GET['date'] ?? date('Y-m-d'); ?>" class="btn" data-lang="cancel"><?php echo $L['cancel']; ?></a>
        </form>
    </div>
    <?php else: ?>
    <div class="glass"><p>Flag not found</p></div>
    <?php endif; ?>
    <?php endif; ?>

    <?php if ($page == 'stats'): ?>
    <div class="glass">
        <h2><span data-lang="statistics"><?php echo $L['statistics']; ?></span></h2>
        <div class="flex" style="margin:12px 0;">
            <a href="?page=stats&view=day" class="btn <?php echo $viewType=='day'?'btn-primary':''; ?>" data-lang="day"><?php echo $L['day']; ?></a>
            <a href="?page=stats&view=month" class="btn <?php echo $viewType=='month'?'btn-primary':''; ?>" data-lang="month"><?php echo $L['month']; ?></a>
            <a href="?page=stats&view=year" class="btn <?php echo $viewType=='year'?'btn-primary':''; ?>" data-lang="year"><?php echo $L['year']; ?></a>
            <span style="margin-left:auto;">
                <label><input type="checkbox" <?php echo $includeFuture?'checked':''; ?> onchange="location.href='?page=stats&view=<?php echo $viewType; ?>&include_future='+this.checked"> <span data-lang="include_future"><?php echo $L['include_future']; ?></span></label>
            </span>
            <form method="POST" style="display:inline;"><input type="hidden" name="action" value="scan"><button class="btn" type="submit" data-lang="scan">🔄 <?php echo $L['scan']; ?></button></form>
        </div>
        <?php
        if ($viewType == 'day') {
            $stats = getFlagStats($selectedDate, $includeFuture);
            $total = $stats['total']; $done = $stats['done']; $percent = $stats['percent']; $unpercent = $stats['unpercent'];
        } elseif ($viewType == 'month') {
            $year = date('Y'); $month = date('m');
            if (isset($_GET['ym'])) { list($year, $month) = explode('-', $_GET['ym']); }
            $monthStats = getMonthlyStats($year, $month);
            $total = 0; $done = 0;
            foreach ($monthStats as $d) { $total += $d['total']; $done += $d['done']; }
            $percent = $total > 0 ? round($done / $total * 100, 1) : 0;
            $unpercent = 100 - $percent;
        } else {
            $year = date('Y');
            if (isset($_GET['year'])) $year = $_GET['year'];
            $res = $conn->query("SELECT COUNT(*) as total, SUM(completed) as done FROM flags WHERE YEAR(target_date) = $year".($includeFuture?"":" AND target_date <= CURDATE()"));
            $row = $res->fetch_assoc();
            $total = $row['total'] ?: 0; $done = $row['done'] ?: 0;
            $percent = $total > 0 ? round($done / $total * 100, 1) : 0;
            $unpercent = 100 - $percent;
        }
        ?>
        <div class="grid-2">
            <div class="glass-card"><div class="stat-label" data-lang="total"><?php echo $L['total']; ?></div><div class="stat-number"><?php echo $total; ?></div></div>
            <div class="glass-card"><div class="stat-label" data-lang="completed"><?php echo $L['completed']; ?></div><div class="stat-number" style="color:var(--success);"><?php echo $done; ?></div></div>
            <div class="glass-card"><div class="stat-label" data-lang="completion_rate"><?php echo $L['completion_rate']; ?></div><div class="stat-number"><?php echo $percent; ?>%</div></div>
            <div class="glass-card"><div class="stat-label" data-lang="uncompleted"><?php echo $L['uncompleted']; ?></div><div class="stat-number" style="color:var(--danger);"><?php echo $total - $done; ?></div></div>
        </div>
        <div class="pie-container"><canvas id="statsPieChart"></canvas></div>

        <?php if ($viewType == 'month'): ?>
        <div style="margin-top:24px;">
            <div class="flex flex-between">
                <h3><?php echo $year.' <span data-lang="year">'.$L['year'].'</span> '.$month.'<span data-lang="month">'.$L['month'].'</span>'; ?></h3>
                <div>
                    <a href="?page=stats&view=month&ym=<?php echo date('Y-m', strtotime("$year-$month-01 -1 month")); ?>" class="btn">‹</a>
                    <a href="?page=stats&view=month&ym=<?php echo date('Y-m'); ?>" class="btn" data-lang="today"><?php echo $L['today']; ?></a>
                    <a href="?page=stats&view=month&ym=<?php echo date('Y-m', strtotime("$year-$month-01 +1 month")); ?>" class="btn">›</a>
                </div>
            </div>
            <div class="calendar" id="monthCalendar">
                <?php 
                $daysInMonth = date('t', strtotime("$year-$month-01"));
                $firstDay = date('w', strtotime("$year-$month-01"));
                $today = date('Y-m-d');
                for ($i=0; $i<$firstDay; $i++) echo '<div class="calendar-day"></div>';
                for ($d=1; $d<=$daysInMonth; $d++):
                    $dateStr = "$year-$month-".str_pad($d,2,'0',STR_PAD_LEFT);
                    $stat = $monthStats[$dateStr] ?? null;
                    $has = !is_null($stat);
                    $isToday = $dateStr == $today;
                    $allDone = $has && $stat['done'] == $stat['total'];
                    $partial = $has && $stat['done'] < $stat['total'];
                    $class = 'calendar-day';
                    if ($isToday) $class .= ' today';
                    if ($allDone) $class .= ' has-flag-all-done';
                    elseif ($partial) $class .= ' has-flag-partial';
                ?>
                <div class="<?php echo $class; ?>" data-date="<?php echo $dateStr; ?>" onclick="loadDayFlags('<?php echo $dateStr; ?>')">
                    <span class="day-number"><?php echo $d; ?></span>
                    <?php if ($has): ?>
                        <span class="status-symbol"><?php echo $allDone ? '✔' : '○'; ?></span>
                        <span class="count-badge"><?php echo $stat['done'].'/'.$stat['total']; ?></span>
                    <?php endif; ?>
                </div>
                <?php endfor; ?>
            </div>
            <div style="margin-top:12px; font-size:14px; color:var(--text-secondary);">
                <span style="display:inline-block; margin-right:16px;">✔ <span data-lang="all_done"><?php echo $L['all_done']; ?></span></span>
                <span style="display:inline-block; margin-right:16px;">○ <span data-lang="partial"><?php echo $L['partial']; ?></span></span>
                <span style="display:inline-block;">空白 = 无目标</span>
            </div>

            <div class="daily-flags-container" id="dailyFlagsContainer">
                <h4 data-lang="today"><?php echo $L['today']; ?></h4>
                <div id="dailyFlagsList">
                    <p style="color:var(--text-secondary);" data-lang="click_date_tip"><?php echo $L['click_date_tip']; ?></p>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php
        $tagStats = [];
        $sql = "SELECT tags FROM flags";
        if (!$includeFuture) $sql .= " WHERE target_date <= CURDATE()";
        $res = $conn->query($sql);
        while ($row = $res->fetch_assoc()) {
            $tags = explode(',', $row['tags']);
            foreach ($tags as $t) { $t = trim($t); if ($t) $tagStats[$t] = ($tagStats[$t] ?? 0) + 1; }
        }
        arsort($tagStats);
        ?>
        <?php if (count($tagStats) > 0): ?>
        <div style="margin-top:20px;">
            <h3 data-lang="tag_ranking"><?php echo $L['tag_ranking']; ?></h3>
            <div class="grid-3">
                <?php foreach ($tagStats as $tag => $count): ?>
                <div class="glass-card" style="text-align:center;">
                    <div><?php echo htmlspecialchars($tag); ?></div>
                    <div class="stat-number" style="font-size:1.8rem;"><?php echo $count; ?></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
    <script>
        new Chart(document.getElementById('statsPieChart'), {
            type: 'pie',
            data: {
                labels: ['<?php echo $L['completed']; ?>', '<?php echo $L['uncompleted']; ?>'],
                datasets: [{
                    data: [<?php echo $done; ?>, <?php echo $total - $done; ?>],
                    backgroundColor: ['#34c759', '#ff3b30'],
                    borderColor: 'rgba(255,255,255,0.3)',
                    borderWidth: 2
                }]
            },
            options: { responsive: true, plugins: { legend: { position: 'bottom' } } }
        });

        function loadDayFlags(date) {
            fetch('?ajax=1&action=get_day_flags&date=' + date)
                .then(res => res.json())
                .then(data => {
                    const container = document.getElementById('dailyFlagsList');
                    if (data.length === 0) {
                        container.innerHTML = '<p style="color:var(--text-secondary);" data-lang="no_flags_tip"><?php echo $L['no_flags_tip']; ?></p>';
                        applyLanguage(currentLang);
                    } else {
                        let html = '';
                        data.forEach(f => {
                            const completed = f.completed ? 'completed' : '';
                            const symbol = f.completed ? '○' : '✔';
                            const btnClass = f.completed ? 'btn-primary' : 'btn-success';
                            html += `
                                <div class="flag-item ${completed}">
                                    <div style="flex:1;">
                                        <div class="flag-title">${escapeHtml(f.title)}</div>
                                        <div class="flag-meta">${escapeHtml(f.content)}</div>
                                        <div class="flag-meta">#${escapeHtml(f.tags)}</div>
                                    </div>
                                    <div style="font-size:20px;">${symbol}</div>
                                </div>
                            `;
                        });
                        container.innerHTML = html;
                    }
                    document.querySelector('#dailyFlagsContainer h4').innerText = date + ' 的 Flag';
                });
        }

        function escapeHtml(text) {
            if (!text) return '';
            return text.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
        }

        document.addEventListener('DOMContentLoaded', function() {
            const today = '<?php echo date('Y-m-d'); ?>';
            const todayEl = document.querySelector(`.calendar-day[data-date="${today}"]`);
            if (todayEl) loadDayFlags(today);
        });
    </script>
    <?php endif; ?>

    <?php if ($page == 'goal'): ?>
    <div class="glass">
        <h2 data-lang="big_goal"><?php echo $L['big_goal']; ?></h2>
        <form method="POST">
            <input type="hidden" name="action" value="save_goal">
            <input type="hidden" name="id" value="<?php echo $goal['id'] ?? 0; ?>">
            <div class="form-group"><label data-lang="title"><?php echo $L['title']; ?></label><input class="form-control" name="title" value="<?php echo htmlspecialchars($goal['title'] ?? ''); ?>" required></div>
            <div class="form-group"><label data-lang="description"><?php echo $L['description']; ?></label><textarea class="form-control" name="description" rows="3"><?php echo htmlspecialchars($goal['description'] ?? ''); ?></textarea></div>
            <div class="form-group"><label data-lang="progress"><?php echo $L['progress']; ?> (0-100)</label><input class="form-control" type="number" name="progress" min="0" max="100" value="<?php echo $goal['progress'] ?? 0; ?>"></div>
            <button class="btn btn-primary" type="submit" data-lang="save"><?php echo $L['save']; ?></button>
        </form>
        <?php if ($goal): ?>
        <div style="margin-top:20px;">
            <div class="progress-bar"><div class="fill" style="width:<?php echo $goal['progress']; ?>%;"></div></div>
            <div style="text-align:center; margin-top:6px;"><?php echo $goal['progress']; ?>%</div>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if ($page == 'settings'): ?>
    <div class="glass">
        <h2 data-lang="settings"><?php echo $L['settings']; ?></h2>
        <div style="margin-bottom:24px;">
            <h3 data-lang="background"><?php echo $L['background']; ?></h3>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="upload_bg">
                <div class="flex"><input type="file" name="bg_image" accept="image/*" required><button class="btn btn-primary" type="submit" data-lang="upload"><?php echo $L['upload']; ?></button></div>
            </form>
            <?php $bgList = json_decode(getSetting('backgrounds'), true) ?: []; $current = getSetting('current_background'); ?>
            <div class="grid-3" style="margin-top:12px;">
                <?php foreach ($bgList as $i => $bg): ?>
                <div class="glass-card" style="text-align:center; <?php echo $bg==$current?'border:2px solid var(--accent);':''; ?>">
                    <img src="<?php echo $bg; ?>" style="width:100%; height:80px; object-fit:cover; border-radius:8px;">
                    <div class="flex" style="justify-content:center; margin-top:8px;">
                        <form method="POST" style="display:inline;"><input type="hidden" name="action" value="switch_bg"><input type="hidden" name="index" value="<?php echo $i; ?>"><button class="btn btn-primary" type="submit" data-lang="switch"><?php echo $L['switch']; ?></button></form>
                        <form method="POST" style="display:inline;" onsubmit="return confirm('<?php echo $L['delete']; ?>?');"><input type="hidden" name="action" value="delete_bg"><input type="hidden" name="index" value="<?php echo $i; ?>"><button class="btn btn-danger" type="submit" data-lang="delete"><?php echo $L['delete']; ?></button></form>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <div style="margin-bottom:24px;">
            <h3>💾 <span data-lang="backup"><?php echo $L['backup']; ?></span></h3>
            <form method="POST"><input type="hidden" name="action" value="backup"><button class="btn btn-primary" type="submit" data-lang="backup"><?php echo $L['backup']; ?></button></form>
            <div style="margin-top:6px; font-size:13px; color:var(--text-secondary);">backup/ 目录</div>
        </div>
        <div>
            <h3 data-lang="device"><?php echo $L['device']; ?></h3>
            <div class="glass-card"><?php echo getDeviceType(); ?> · <?php echo htmlspecialchars($_SERVER['HTTP_USER_AGENT']); ?></div>
        </div>
    </div>
    <?php endif; ?>

    <div style="text-align:center; padding:16px 0; opacity:0.5; font-size:12px; color:var(--text-secondary);">
        <span data-lang="app_name"><?php echo $L['app_name']; ?></span> · <?php echo date('Y'); ?>
    </div>
</div>
<div class="device-badge"><?php echo getDeviceType(); ?></div>

<script>
var LANG_DATA = <?php echo json_encode($langs); ?>;
var currentLang = localStorage.getItem('lang') || '<?php echo $langCode; ?>';
var currentTheme = localStorage.getItem('theme') || '<?php echo $theme; ?>';

function applyTheme(theme) {
    document.documentElement.setAttribute('data-theme', theme);
    localStorage.setItem('theme', theme);
    document.querySelectorAll('#themeButtons button').forEach(btn => {
        btn.classList.toggle('active', btn.dataset.theme === theme);
    });
    fetch('', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'ajax=1&action=set_theme&theme=' + encodeURIComponent(theme)
    });
}

function applyLanguage(lang) {
    localStorage.setItem('lang', lang);
    document.querySelectorAll('[data-lang]').forEach(function(el) {
        var key = el.getAttribute('data-lang');
        if (LANG_DATA[lang] && LANG_DATA[lang][key] !== undefined) {
            el.innerText = LANG_DATA[lang][key];
        }
    });
    document.documentElement.lang = lang;
    document.getElementById('langSelect').value = lang;
    fetch('', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'ajax=1&action=set_lang&lang=' + encodeURIComponent(lang)
    });
}

document.querySelectorAll('#themeButtons button').forEach(btn => {
    btn.addEventListener('click', function() {
        var theme = this.dataset.theme;
        currentTheme = theme;
        applyTheme(theme);
    });
});

document.getElementById('langSelect').addEventListener('change', function() {
    var lang = this.value;
    currentLang = lang;
    applyLanguage(lang);
});

document.addEventListener('DOMContentLoaded', function() {
    var storedTheme = localStorage.getItem('theme');
    if (storedTheme) {
        currentTheme = storedTheme;
        applyTheme(currentTheme);
    } else {
        applyTheme('<?php echo $theme; ?>');
    }
    var storedLang = localStorage.getItem('lang');
    if (storedLang) {
        currentLang = storedLang;
        applyLanguage(currentLang);
    } else {
        applyLanguage('<?php echo $langCode; ?>');
    }
});
</script>
</body>
</html>
<?php $conn->close(); ?>
