<?php
session_start();
date_default_timezone_set('Asia/Shanghai');

$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'flag_db';

$conn = new mysqli($db_host, $db_user, $db_pass);
if ($conn->connect_error) die("数据库连接失败: " . $conn->connect_error);

$conn->query("CREATE DATABASE IF NOT EXISTS `$db_name`");
$conn->select_db($db_name);

$today = date('Y-m-d');

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_flag'])) {
    $title = trim($_POST['title'] ?? '');
    if ($title) {
        $content = $_POST['content'] ?? '';
        $tags = $_POST['tags'] ?? '';
        $conn->query("INSERT INTO `flags` (title, content, tags, target_date) VALUES ('$title', '$content', '$tags', '$today')");
        $message = '添加成功';
    }
}

if (isset($_GET['toggle'])) {
    $id = (int)$_GET['toggle'];
    $conn->query("UPDATE `flags` SET completed = NOT completed WHERE id = $id");
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $conn->query("DELETE FROM `flags` WHERE id = $id");
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

$langs = [
    'zh' => [
        'app_name' => '目标管理', 'today' => '今天', 'add' => '添加',
        'total' => '总额', 'completed' => '完成', 'completion_rate' => '完成率',
        'no_flags' => '今日还没有目标', 'save' => '保存', 'cancel' => '取消',
        'title' => '标题', 'content' => '内容', 'tags' => '标签',
        'settings' => '设置', 'language' => '语言', 'close' => '关闭'
    ],
    'en' => [
        'app_name' => 'Goal Manager', 'today' => 'Today', 'add' => 'Add',
        'total' => 'Total', 'completed' => 'Done', 'completion_rate' => 'Rate',
        'no_flags' => 'No goals today', 'save' => 'Save', 'cancel' => 'Cancel',
        'title' => 'Title', 'content' => 'Content', 'tags' => 'Tags',
        'settings' => 'Settings', 'language' => 'Language', 'close' => 'Close'
    ]
];

$lang_code = $_COOKIE['watch_lang'] ?? 'zh';
$L = $langs[$lang_code] ?? $langs['zh'];

$flags = $conn->query("SELECT * FROM `flags` WHERE target_date = '$today' ORDER BY completed, id DESC");
$stats = $conn->query("SELECT COUNT(*) as total, SUM(completed) as done FROM `flags` WHERE target_date = '$today'")->fetch_assoc();
$total = $stats['total'] ?: 0;
$done = $stats['done'] ?: 0;
$percent = $total > 0 ? round($done / $total * 100, 1) : 0;

?><!DOCTYPE html>
<html lang="<?php echo $lang_code; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?php echo $L['app_name']; ?></title>
    <style>
        * { margin:0; padding:0; box-sizing:border-box; font-family: -apple-system, "SF Pro Display", sans-serif; }
        body {
            background: #000000;
            color: #ffffff;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: flex-start;
            padding: 16px;
        }
        .container { width: 100%; max-width: 400px; margin: 0 auto; }
        .glass {
            background: rgba(255,255,255,0.06);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 20px;
            padding: 16px;
            margin-bottom: 12px;
        }
        .kern-header {
            font-size: 0.75rem;
            font-weight: 300;
            letter-spacing: 4px;
            color: #8e8e93;
            text-align: center;
            padding: 8px 0 4px 0;
            text-transform: uppercase;
        }
        .mini-dashboard {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 8px;
            text-align: center;
        }
        .mini-dashboard .item .number { font-size: 1.8rem; font-weight: 600; color: #ffffff; }
        .mini-dashboard .item .number.green { color: #34c759; }
        .mini-dashboard .item .label { font-size: 0.7rem; color: #8e8e93; margin-top: 2px; }
        .flag-item {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 8px;
            padding: 10px 0;
            border-bottom: 1px solid rgba(255,255,255,0.05);
        }
        .flag-item:last-child { border-bottom: none; }
        .flag-title { font-weight: 500; flex: 1; min-width: 60px; }
        .flag-meta { font-size: 0.8rem; color: #8e8e93; }
        .completed .flag-title { text-decoration: line-through; opacity: 0.5; }
        .btn-group { display: flex; gap: 6px; }
        .btn {
            display: inline-block;
            padding: 6px 14px;
            border-radius: 30px;
            border: none;
            background: rgba(255,255,255,0.12);
            color: #ffffff;
            font-weight: 500;
            font-size: 0.9rem;
            cursor: pointer;
            transition: 0.2s;
            text-decoration: none;
            backdrop-filter: blur(4px);
        }
        .btn-primary { background: #007aff; color: #fff; }
        .btn-success { background: #34c759; color: #fff; }
        .btn-danger { background: #ff3b30; color: #fff; }
        .btn-block { width: 100%; text-align: center; padding: 12px; }
        .form-group { margin-bottom: 12px; }
        .form-group label { display: block; margin-bottom: 4px; font-weight: 500; color: #8e8e93; font-size: 0.8rem; }
        .form-control {
            width: 100%;
            padding: 10px 14px;
            border-radius: 12px;
            border: 1px solid rgba(255,255,255,0.1);
            background: rgba(255,255,255,0.06);
            color: #ffffff;
            font-size: 1rem;
            backdrop-filter: blur(4px);
        }
        .form-control:focus { outline: none; border-color: #007aff; box-shadow: 0 0 0 3px rgba(0,122,255,0.2); }
        .settings-btn {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: rgba(255,255,255,0.08);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 30px;
            padding: 8px 16px;
            color: #fff;
            cursor: pointer;
            z-index: 100;
            font-size: 0.9rem;
        }
        .settings-overlay {
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0,0,0,0.75);
            backdrop-filter: blur(12px);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 200;
            padding: 20px;
        }
        .settings-overlay.active { display: flex; }
        .settings-panel {
            background: #1c1c1e;
            border-radius: 24px;
            padding: 24px;
            max-width: 340px;
            width: 100%;
            box-shadow: 0 20px 60px rgba(0,0,0,0.8);
            border: 1px solid rgba(255,255,255,0.05);
        }
        .settings-panel h3 { margin-bottom: 16px; font-weight: 400; letter-spacing: 1px; color: #ffffff; }
        .settings-panel .setting-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid rgba(255,255,255,0.05);
        }
        .settings-panel .setting-item:last-child { border-bottom: none; }
        .settings-panel select {
            background: rgba(255,255,255,0.08);
            border: none;
            padding: 6px 12px;
            border-radius: 20px;
            color: #fff;
            font-size: 0.9rem;
        }
        .settings-panel select option { background: #1c1c1e; }
        .settings-panel .btn-close {
            margin-top: 16px;
            background: #007aff;
            color: #fff;
            border: none;
            border-radius: 30px;
            padding: 10px;
            width: 100%;
            font-size: 1rem;
            cursor: pointer;
        }
        .text-center { text-align: center; }
        .mt-12 { margin-top: 12px; }
        .color-muted { color: #8e8e93; }
        @media (max-width: 480px) {
            body { padding: 10px; }
            .mini-dashboard .item .number { font-size: 1.5rem; }
        }
    </style>
</head>
<body>
<div class="container">
    <div class="kern-header">Kern</div>

    <div class="glass">
        <div class="mini-dashboard">
            <div class="item"><div class="number"><?php echo $total; ?></div><div class="label"><?php echo $L['total']; ?></div></div>
            <div class="item"><div class="number green"><?php echo $done; ?></div><div class="label"><?php echo $L['completed']; ?></div></div>
            <div class="item"><div class="number"><?php echo $percent; ?>%</div><div class="label"><?php echo $L['completion_rate']; ?></div></div>
        </div>
    </div>

    <div class="glass">
        <?php if ($flags && $flags->num_rows > 0): ?>
            <?php while ($f = $flags->fetch_assoc()): ?>
            <div class="flag-item <?php echo $f['completed']?'completed':''; ?>">
                <div style="flex:1;">
                    <div class="flag-title"><?php echo htmlspecialchars($f['title']); ?></div>
                    <div class="flag-meta"><?php echo nl2br(htmlspecialchars($f['content'])); ?></div>
                    <div class="flag-meta">#<?php echo htmlspecialchars($f['tags']); ?></div>
                </div>
                <div class="btn-group">
                    <a href="?toggle=<?php echo $f['id']; ?>" class="btn <?php echo $f['completed']?'btn-primary':'btn-success'; ?>">
                        <?php echo $f['completed'] ? '○' : '✔'; ?>
                    </a>
                    <a href="?delete=<?php echo $f['id']; ?>" class="btn btn-danger" onclick="return confirm('删除？')">✕</a>
                </div>
            </div>
            <?php endwhile; ?>
        <?php else: ?>
            <p class="text-center color-muted" style="padding:20px 0;"><?php echo $L['no_flags']; ?></p>
        <?php endif; ?>

        <div class="mt-12">
            <button class="btn btn-primary btn-block" onclick="document.getElementById('addForm').style.display = (document.getElementById('addForm').style.display === 'none' ? 'block' : 'none')">
                + <?php echo $L['add']; ?>
            </button>
            <div id="addForm" style="display:none; margin-top:12px;">
                <form method="POST">
                    <input type="hidden" name="add_flag" value="1">
                    <div class="form-group">
                        <label><?php echo $L['title']; ?> *</label>
                        <input class="form-control" name="title" required>
                    </div>
                    <div class="form-group">
                        <label><?php echo $L['content']; ?></label>
                        <textarea class="form-control" name="content" rows="2"></textarea>
                    </div>
                    <div class="form-group">
                        <label><?php echo $L['tags']; ?></label>
                        <input class="form-control" name="tags" placeholder="work, study">
                    </div>
                    <button class="btn btn-success btn-block" type="submit"><?php echo $L['save']; ?></button>
                    <button class="btn btn-block" type="button" onclick="document.getElementById('addForm').style.display='none'"><?php echo $L['cancel']; ?></button>
                </form>
            </div>
        </div>
    </div>

    <div class="text-center mt-12">
        <button class="btn" style="background:rgba(255,255,255,0.06);" onclick="toggleSettings()">⚙ <?php echo $L['settings']; ?></button>
    </div>
</div>

<div class="settings-overlay" id="settingsOverlay">
    <div class="settings-panel">
        <h3><?php echo $L['settings']; ?></h3>
        <div class="setting-item">
            <span><?php echo $L['language']; ?></span>
            <select id="langSelect">
                <option value="zh" <?php echo $lang_code=='zh'?'selected':''; ?>>中文</option>
                <option value="en" <?php echo $lang_code=='en'?'selected':''; ?>>English</option>
            </select>
        </div>
        <button class="btn-close" onclick="toggleSettings()"><?php echo $L['close']; ?></button>
        <div class="text-center color-muted" style="margin-top:12px; font-size:0.7rem;">Kern</div>
    </div>
</div>

<button class="settings-btn" onclick="toggleSettings()">⚙</button>

<script>
document.getElementById('langSelect')?.addEventListener('change', function() {
    document.cookie = 'watch_lang=' + this.value + '; path=/; max-age=31536000';
    location.reload();
});

function toggleSettings() {
    var overlay = document.getElementById('settingsOverlay');
    if (overlay) overlay.classList.toggle('active');
}

document.getElementById('settingsOverlay')?.addEventListener('click', function(e) {
    if (e.target === this) this.classList.remove('active');
});

<?php if (isset($message)): ?>
setTimeout(() => {
    var msgs = document.querySelectorAll('.glass[style*="border-color"]');
    msgs.forEach(el => el.style.display = 'none');
}, 3000);
<?php endif; ?>
</script>
</body>
</html>
<?php $conn->close(); ?>
