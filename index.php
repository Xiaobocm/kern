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

$tables = [
    "CREATE TABLE IF NOT EXISTS `flags` (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        content TEXT,
        tags VARCHAR(255),
        target_date DATE NOT NULL,
        completed BOOLEAN DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )",
    "CREATE TABLE IF NOT EXISTS `goals` (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        description TEXT,
        progress INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )",
    "CREATE TABLE IF NOT EXISTS `settings` (
        id INT AUTO_INCREMENT PRIMARY KEY,
        setting_key VARCHAR(255) UNIQUE NOT NULL,
        setting_value TEXT,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )",
    "CREATE TABLE IF NOT EXISTS `focus_sessions` (
        id INT AUTO_INCREMENT PRIMARY KEY,
        flag_id INT DEFAULT NULL,
        duration_seconds INT NOT NULL,
        started_at DATETIME NOT NULL,
        ended_at DATETIME NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (flag_id) REFERENCES `flags`(id) ON DELETE SET NULL
    )",
    "CREATE TABLE IF NOT EXISTS `focus_current` (
        id INT AUTO_INCREMENT PRIMARY KEY,
        flag_id INT DEFAULT NULL,
        total_seconds INT NOT NULL,
        start_time DATETIME NOT NULL,
        paused BOOLEAN DEFAULT 0,
        paused_elapsed INT DEFAULT 0,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (flag_id) REFERENCES `flags`(id) ON DELETE SET NULL
    )",
    "CREATE TABLE IF NOT EXISTS `devices` (
        id INT AUTO_INCREMENT PRIMARY KEY,
        device_id VARCHAR(64) UNIQUE NOT NULL,
        ua VARCHAR(255),
        last_seen DATETIME,
        is_online BOOLEAN DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )"
];
foreach ($tables as $sql) $conn->query($sql);

$conn->query("INSERT IGNORE INTO `settings` (setting_key, setting_value) VALUES 
    ('backgrounds', '[]'), 
    ('current_background', ''),
    ('theme', 'light'),
    ('language', 'zh'),
    ('api_token', '" . bin2hex(random_bytes(16)) . "'),
    ('device_notification', 'on'),
    ('notification_duration', '3'),
    ('focus_sound', 'on')");

function getSetting($key) {
    global $conn;
    $res = $conn->query("SELECT setting_value FROM `settings` WHERE setting_key='$key'");
    if ($row = $res->fetch_assoc()) return $row['setting_value'];
    return null;
}

function setSetting($key, $value) {
    global $conn;
    $value = $conn->real_escape_string($value);
    $conn->query("INSERT INTO `settings` (setting_key, setting_value) VALUES ('$key', '$value') 
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
    $sql = "SELECT * FROM `flags` WHERE target_date = '$date'";
    if (!$includeFuture) $sql .= " AND target_date <= CURDATE()";
    $sql .= " ORDER BY completed, id DESC";
    return $conn->query($sql);
}

function getFlagStats($date, $includeFuture = true) {
    global $conn;
    $date = $conn->real_escape_string($date);
    $sql = "SELECT COUNT(*) as total, SUM(completed) as done FROM `flags` WHERE target_date = '$date'";
    if (!$includeFuture) $sql .= " AND target_date <= CURDATE()";
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
                         FROM `flags` WHERE target_date BETWEEN '$start' AND '$end' GROUP BY target_date");
    $stats = [];
    while ($row = $res->fetch_assoc()) $stats[$row['target_date']] = $row;
    return $stats;
}

function getFocusStats() {
    global $conn;
    $today = date('Y-m-d');
    $res = $conn->query("SELECT COUNT(*) as count, SUM(duration_seconds) as total_sec 
                         FROM `focus_sessions` WHERE DATE(started_at) = '$today'");
    $todayData = $res->fetch_assoc();
    $totalRes = $conn->query("SELECT COUNT(*) as total_count, SUM(duration_seconds) as total_sec_all FROM `focus_sessions`");
    $totalData = $totalRes->fetch_assoc();
    $recent = $conn->query("SELECT fs.*, f.title as flag_title 
                            FROM `focus_sessions` fs 
                            LEFT JOIN `flags` f ON fs.flag_id = f.id 
                            ORDER BY fs.started_at DESC LIMIT 5");
    return [
        'today_count' => $todayData['count'] ?? 0,
        'today_sec' => $todayData['total_sec'] ?? 0,
        'total_count' => $totalData['total_count'] ?? 0,
        'total_sec' => $totalData['total_sec_all'] ?? 0,
        'recent' => $recent
    ];
}

function registerDevice($device_id, $ua) {
    global $conn;
    $ua = $conn->real_escape_string($ua);
    $now = date('Y-m-d H:i:s');
    $conn->query("INSERT INTO `devices` (device_id, ua, last_seen, is_online) 
                  VALUES ('$device_id', '$ua', '$now', 1) 
                  ON DUPLICATE KEY UPDATE last_seen='$now', is_online=1, ua='$ua'");
}

function getDeviceList() {
    global $conn;
    $res = $conn->query("SELECT * FROM `devices` ORDER BY last_seen DESC");
    $devices = [];
    while ($row = $res->fetch_assoc()) {
        $row['is_online'] = (time() - strtotime($row['last_seen'])) < 30;
        $devices[] = $row;
    }
    return $devices;
}

function getCurrentFocus() {
    global $conn;
    $res = $conn->query("SELECT * FROM `focus_current` LIMIT 1");
    return $res->fetch_assoc();
}

function updateFocusState($flag_id, $total_seconds, $start_time, $paused, $paused_elapsed) {
    global $conn;
    $flag_id_sql = $flag_id ? "'$flag_id'" : "NULL";
    $start_time_sql = $start_time ? "'$start_time'" : "NULL";
    $paused_int = $paused ? 1 : 0;
    $conn->query("DELETE FROM `focus_current`");
    $conn->query("INSERT INTO `focus_current` (flag_id, total_seconds, start_time, paused, paused_elapsed) 
                  VALUES ($flag_id_sql, $total_seconds, $start_time_sql, $paused_int, $paused_elapsed)");
}

function clearFocusState() {
    global $conn;
    $conn->query("DELETE FROM `focus_current`");
}

function getFocusRemaining() {
    $state = getCurrentFocus();
    if (!$state) return null;
    $total = (int)$state['total_seconds'];
    $start = strtotime($state['start_time']);
    $paused = (bool)$state['paused'];
    $paused_elapsed = (int)$state['paused_elapsed'];
    if ($paused) {
        $elapsed = $paused_elapsed;
    } else {
        $elapsed = time() - $start + $paused_elapsed;
    }
    $remaining = max(0, $total - $elapsed);
    return ['remaining' => $remaining, 'total' => $total, 'paused' => $paused, 'flag_id' => $state['flag_id']];
}

function handleApiRequest() {
    global $conn;
    $token = null;
    if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
        if (preg_match('/Bearer\s+(.*)$/i', $_SERVER['HTTP_AUTHORIZATION'], $matches)) {
            $token = $matches[1];
        }
    }
    if (!$token && isset($_GET['token'])) $token = $_GET['token'];
    if (!$token && isset($_POST['token'])) $token = $_POST['token'];
    $validToken = getSetting('api_token');
    if (!$token || $token !== $validToken) {
        http_response_code(401);
        echo json_encode(['status' => 'error', 'message' => 'Invalid or missing token']);
        exit;
    }

    $method = $_SERVER['REQUEST_METHOD'];
    $path = $_GET['api_path'] ?? '';
    $parts = explode('/', trim($path, '/'));
    $resource = $parts[0] ?? '';

    $response = ['status' => 'error', 'message' => 'Not found'];

    if ($resource == 'flags') {
        if ($method == 'GET') {
            $id = $parts[1] ?? null;
            if ($id) {
                $res = $conn->query("SELECT * FROM `flags` WHERE id = " . intval($id));
                if ($row = $res->fetch_assoc()) {
                    $response = ['status' => 'success', 'data' => $row];
                } else {
                    $response = ['status' => 'error', 'message' => 'Flag not found'];
                }
            } else {
                $where = [];
                if (isset($_GET['date'])) {
                    $date = $conn->real_escape_string($_GET['date']);
                    $where[] = "target_date = '$date'";
                }
                if (isset($_GET['date_from'])) {
                    $from = $conn->real_escape_string($_GET['date_from']);
                    $where[] = "target_date >= '$from'";
                }
                if (isset($_GET['date_to'])) {
                    $to = $conn->real_escape_string($_GET['date_to']);
                    $where[] = "target_date <= '$to'";
                }
                if (isset($_GET['status'])) {
                    if ($_GET['status'] == 'completed') {
                        $where[] = "completed = 1";
                    } elseif ($_GET['status'] == 'pending') {
                        $where[] = "completed = 0";
                    }
                }
                if (isset($_GET['tag'])) {
                    $tag = $conn->real_escape_string($_GET['tag']);
                    $where[] = "tags LIKE '%$tag%'";
                }
                $sql = "SELECT * FROM `flags`";
                if (!empty($where)) {
                    $sql .= " WHERE " . implode(' AND ', $where);
                }
                $res = $conn->query($sql);
                $flags = [];
                while ($row = $res->fetch_assoc()) $flags[] = $row;
                $response = ['status' => 'success', 'data' => $flags];
            }
        } elseif ($method == 'POST') {
            $input = json_decode(file_get_contents('php://input'), true);
            if (!$input) {
                $response = ['status' => 'error', 'message' => 'Invalid JSON'];
            } else {
                $title = $conn->real_escape_string($input['title'] ?? '');
                $content = $conn->real_escape_string($input['content'] ?? '');
                $tags = $conn->real_escape_string($input['tags'] ?? '');
                $target_date = $conn->real_escape_string($input['target_date'] ?? date('Y-m-d'));
                if ($title) {
                    $conn->query("INSERT INTO `flags` (title, content, tags, target_date) VALUES ('$title', '$content', '$tags', '$target_date')");
                    $id = $conn->insert_id;
                    $response = ['status' => 'success', 'message' => 'Flag created', 'id' => $id];
                } else {
                    $response = ['status' => 'error', 'message' => 'Title required'];
                }
            }
        } elseif ($method == 'PUT') {
            $id = $parts[1] ?? null;
            if (!$id) {
                $response = ['status' => 'error', 'message' => 'ID required'];
            } else {
                $input = json_decode(file_get_contents('php://input'), true);
                if (!$input) {
                    $response = ['status' => 'error', 'message' => 'Invalid JSON'];
                } else {
                    $fields = [];
                    if (isset($input['title'])) $fields[] = "title='" . $conn->real_escape_string($input['title']) . "'";
                    if (isset($input['content'])) $fields[] = "content='" . $conn->real_escape_string($input['content']) . "'";
                    if (isset($input['tags'])) $fields[] = "tags='" . $conn->real_escape_string($input['tags']) . "'";
                    if (isset($input['target_date'])) $fields[] = "target_date='" . $conn->real_escape_string($input['target_date']) . "'";
                    if (isset($input['completed'])) $fields[] = "completed=" . intval($input['completed']);
                    if (empty($fields)) {
                        $response = ['status' => 'error', 'message' => 'No fields to update'];
                    } else {
                        $conn->query("UPDATE `flags` SET " . implode(', ', $fields) . " WHERE id = " . intval($id));
                        if ($conn->affected_rows > 0) {
                            $response = ['status' => 'success', 'message' => 'Flag updated'];
                        } else {
                            $response = ['status' => 'error', 'message' => 'Flag not found or no changes'];
                        }
                    }
                }
            }
        } elseif ($method == 'DELETE') {
            $id = $parts[1] ?? null;
            if (!$id) {
                $response = ['status' => 'error', 'message' => 'ID required'];
            } else {
                $conn->query("DELETE FROM `flags` WHERE id = " . intval($id));
                if ($conn->affected_rows > 0) {
                    $response = ['status' => 'success', 'message' => 'Flag deleted'];
                } else {
                    $response = ['status' => 'error', 'message' => 'Flag not found'];
                }
            }
        }
    } elseif ($resource == 'stats') {
        if ($method == 'GET') {
            $stats = getFlagStats(date('Y-m-d'), true);
            $response = ['status' => 'success', 'data' => $stats];
        }
    } elseif ($resource == 'focus') {
        if ($method == 'GET') {
            $sub = $parts[1] ?? '';
            if ($sub == 'sessions') {
                $res = $conn->query("SELECT fs.*, f.title as flag_title 
                                     FROM `focus_sessions` fs 
                                     LEFT JOIN `flags` f ON fs.flag_id = f.id 
                                     ORDER BY fs.started_at DESC LIMIT 50");
                $sessions = [];
                while ($row = $res->fetch_assoc()) $sessions[] = $row;
                $response = ['status' => 'success', 'data' => $sessions];
            } elseif ($sub == 'stats') {
                $stats = getFocusStats();
                $response = ['status' => 'success', 'data' => [
                    'today_count' => $stats['today_count'],
                    'today_minutes' => round($stats['today_sec'] / 60, 1),
                    'total_count' => $stats['total_count'],
                    'total_minutes' => round($stats['total_sec'] / 60, 1)
                ]];
            } else {
                $response = ['status' => 'error', 'message' => 'Invalid focus endpoint'];
            }
        }
    } else {
        $response = ['status' => 'error', 'message' => 'Invalid resource'];
    }

    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

$langs = json_decode(file_get_contents('lang.txt'), true);
if (!$langs) die('语言包加载失败，请检查 lang.txt 文件');

$all_langs = array_keys($langs);
$lang_code = $_COOKIE['lang'] ?? getSetting('language') ?: 'zh';
$theme = $_COOKIE['theme'] ?? getSetting('theme') ?: 'light';
$L = $langs[$lang_code] ?? $langs['zh'];

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
    if ($action == 'get_focus_state') {
        $info = getFocusRemaining();
        if ($info === null) {
            echo json_encode(['status' => 'idle']);
        } else {
            echo json_encode([
                'status' => 'running',
                'remaining' => $info['remaining'],
                'total' => $info['total'],
                'paused' => $info['paused'],
                'flag_id' => $info['flag_id']
            ]);
        }
        exit;
    }
    if ($action == 'get_focus_stats') {
        $stats = getFocusStats();
        echo json_encode([
            'today_count' => $stats['today_count'],
            'today_min' => round($stats['today_sec'] / 60, 1),
            'total_count' => $stats['total_count'],
            'total_min' => round($stats['total_sec'] / 60, 1)
        ]);
        exit;
    }
    if ($action == 'get_devices') {
        $devices = getDeviceList();
        header('Content-Type: application/json');
        echo json_encode($devices);
        exit;
    }
    if ($action == 'register_device' || $action == 'heartbeat') {
        $device_id = $_GET['device_id'] ?? null;
        if ($device_id) {
            registerDevice($device_id, $_SERVER['HTTP_USER_AGENT']);
            echo json_encode(['status' => 'ok']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Missing device_id']);
        }
        exit;
    }
    if ($action == 'get_notification_settings') {
        echo json_encode([
            'enabled' => getSetting('device_notification') !== 'off' ? 'on' : 'off',
            'duration' => (int)getSetting('notification_duration') ?: 3,
            'focus_sound' => getSetting('focus_sound') !== 'off' ? 'on' : 'off'
        ]);
        exit;
    }
}

if (isset($_GET['page']) && $_GET['page'] == 'api') {
    handleApiRequest();
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['ajax'])) {
    $action = $_POST['action'] ?? '';
    if ($action == 'set_theme') {
        $theme = $_POST['theme'] ?? 'light';
        setSetting('theme', $theme);
        setcookie('theme', $theme, time()+31536000, '/');
        echo json_encode(['status' => 'ok']);
        exit;
    }
    if ($action == 'set_lang') {
        $lang = $_POST['lang'] ?? 'zh';
        setSetting('language', $lang);
        setcookie('lang', $lang, time()+31536000, '/');
        echo json_encode(['status' => 'ok']);
        exit;
    }
    if ($action == 'start_focus') {
        $flag_id = isset($_POST['flag_id']) && $_POST['flag_id'] !== '' ? (int)$_POST['flag_id'] : null;
        $total_min = (int)$_POST['duration'];
        $total_seconds = $total_min * 60;
        $start_time = date('Y-m-d H:i:s');
        updateFocusState($flag_id, $total_seconds, $start_time, 0, 0);
        echo json_encode(['status' => 'ok']);
        exit;
    }
    if ($action == 'pause_focus') {
        $state = getCurrentFocus();
        if ($state) {
            $total = (int)$state['total_seconds'];
            $start = strtotime($state['start_time']);
            $paused_elapsed = (int)$state['paused_elapsed'];
            $elapsed = time() - $start + $paused_elapsed;
            updateFocusState($state['flag_id'], $total, $state['start_time'], 1, $elapsed);
        }
        echo json_encode(['status' => 'ok']);
        exit;
    }
    if ($action == 'resume_focus') {
        $state = getCurrentFocus();
        if ($state) {
            $total = (int)$state['total_seconds'];
            $paused_elapsed = (int)$state['paused_elapsed'];
            $new_start = date('Y-m-d H:i:s');
            updateFocusState($state['flag_id'], $total, $new_start, 0, $paused_elapsed);
        }
        echo json_encode(['status' => 'ok']);
        exit;
    }
    if ($action == 'reset_focus') {
        clearFocusState();
        echo json_encode(['status' => 'ok']);
        exit;
    }
    if ($action == 'save_focus_complete') {
        $state = getCurrentFocus();
        if ($state) {
            $flag_id = $state['flag_id'];
            $total_seconds = (int)$state['total_seconds'];
            $started = $state['start_time'];
            $ended = date('Y-m-d H:i:s');
            $flag_id_sql = $flag_id ? "'$flag_id'" : "NULL";
            $conn->query("INSERT INTO `focus_sessions` (flag_id, duration_seconds, started_at, ended_at) 
                          VALUES ($flag_id_sql, $total_seconds, '$started', '$ended')");
            clearFocusState();
        }
        echo json_encode(['status' => 'ok']);
        exit;
    }
    if ($action == 'reset_token') {
        $newToken = bin2hex(random_bytes(16));
        setSetting('api_token', $newToken);
        echo json_encode(['status' => 'ok', 'token' => $newToken]);
        exit;
    }
    if ($action == 'save_notification') {
        $enabled = $_POST['enabled'] ?? 'on';
        $duration = (int)$_POST['duration'] ?: 3;
        setSetting('device_notification', $enabled);
        setSetting('notification_duration', $duration);
        echo json_encode(['status' => 'ok']);
        exit;
    }
    if ($action == 'save_focus_sound') {
        $enabled = $_POST['enabled'] ?? 'on';
        setSetting('focus_sound', $enabled);
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
            $conn->query("INSERT INTO `flags` (title, content, tags, target_date) VALUES ('$title', '$content', '$tags', '$date')");
            $message = $L['save'] . ' ' . ($L['success'] ?? '');
        }
    } elseif ($action == 'edit_flag') {
        $id = (int)$_POST['id'];
        $title = $_POST['title'];
        $content = $_POST['content'];
        $tags = $_POST['tags'];
        $completed = isset($_POST['completed']) ? 1 : 0;
        $conn->query("UPDATE `flags` SET title='$title', content='$content', tags='$tags', completed='$completed' WHERE id=$id");
        $message = $L['save'] . ' ' . ($L['success'] ?? '');
    } elseif ($action == 'toggle_complete') {
        $id = (int)$_POST['id'];
        $conn->query("UPDATE `flags` SET completed = NOT completed WHERE id=$id");
        $message = $L['save'] . ' ' . ($L['success'] ?? '');
    } elseif ($action == 'delete_flag') {
        $id = (int)$_POST['id'];
        $conn->query("DELETE FROM `flags` WHERE id=$id");
        $message = $L['delete'] . ' ' . ($L['success'] ?? '');
    } elseif ($action == 'save_goal') {
        $id = (int)$_POST['id'];
        $title = $_POST['title'];
        $desc = $_POST['description'];
        $progress = (int)$_POST['progress'];
        if ($id > 0) {
            $conn->query("UPDATE `goals` SET title='$title', description='$desc', progress='$progress' WHERE id=$id");
        } else {
            $conn->query("INSERT INTO `goals` (title, description, progress) VALUES ('$title', '$desc', '$progress')");
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
            if (!getSetting('current_background')) setSetting('current_background', $dir . $filename);
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
$selected_date = $_GET['date'] ?? date('Y-m-d');
$view_type = $_GET['view'] ?? 'day';
$include_future = isset($_GET['include_future']) ? (bool)$_GET['include_future'] : true;
$current_bg = getSetting('current_background');
$bg_style = $current_bg ? "background-image: url('$current_bg'); background-size: cover; background-position: center;" : '';

$goal = $conn->query("SELECT * FROM `goals` LIMIT 1")->fetch_assoc();

$lang_names = [
    'zh' => '中文',
    'zh-hk' => '中文(繁體)',
    'en' => 'English',
    'ja' => '日本語',
    'ko' => '한국어'
];
?>
<!DOCTYPE html>
<html lang="<?php echo $lang_code; ?>" data-theme="<?php echo $theme; ?>">
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
            <?php echo $bg_style; ?>
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
        .focus-timer {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 20px;
        }
        .focus-ring {
            position: relative;
            width: 200px;
            height: 200px;
        }
        .focus-ring svg {
            transform: rotate(-90deg);
        }
        .focus-ring .bg-circle {
            fill: none;
            stroke: var(--card-bg);
            stroke-width: 8;
        }
        .focus-ring .progress-circle {
            fill: none;
            stroke: var(--accent);
            stroke-width: 8;
            stroke-linecap: round;
            transition: stroke-dashoffset 0.5s ease;
        }
        .focus-time {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            font-size: 3.2rem;
            font-weight: 700;
            color: var(--text-primary);
            letter-spacing: 2px;
        }
        .focus-controls {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            justify-content: center;
        }
        .focus-controls .btn {
            min-width: 80px;
            padding: 10px 20px;
            font-size: 1rem;
        }
        .focus-settings {
            display: flex;
            gap: 16px;
            align-items: center;
            flex-wrap: wrap;
            justify-content: center;
            margin-top: 8px;
        }
        .focus-settings label {
            color: var(--text-secondary);
            font-weight: 500;
        }
        .focus-settings input[type="number"] {
            width: 80px;
            padding: 8px 12px;
            border-radius: 12px;
            border: 1px solid var(--border-color);
            background: var(--input-bg);
            color: var(--text-primary);
            font-size: 1rem;
            text-align: center;
        }
        .focus-settings select {
            padding: 8px 12px;
            border-radius: 12px;
            border: 1px solid var(--border-color);
            background: var(--input-bg);
            color: var(--text-primary);
            font-size: 1rem;
            min-width: 150px;
        }
        .focus-stats-mini {
            display: flex;
            gap: 24px;
            margin-top: 12px;
            justify-content: center;
        }
        .focus-stats-mini .stat-item {
            text-align: center;
        }
        .focus-stats-mini .stat-number {
            font-size: 1.6rem;
        }
        .focus-stats-mini .stat-label {
            font-size: 0.8rem;
        }
        .device-item {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid var(--border-color);
        }
        .device-item .online { color: var(--success); }
        .device-item .offline { color: var(--danger); }
        .device-item .device-name { font-weight: 500; }
        .device-item .device-ua { font-size: 13px; color: var(--text-secondary); }
        .api-doc {
            background: var(--card-bg);
            border-radius: 12px;
            padding: 16px;
            margin: 12px 0;
            font-family: monospace;
            font-size: 14px;
            white-space: pre-wrap;
            word-break: break-word;
        }
        .api-doc code {
            background: rgba(0,0,0,0.1);
            padding: 2px 6px;
            border-radius: 4px;
        }
        .notif-popup {
            position: fixed; top: 20px; right: 20px; z-index: 10000;
            background: var(--bg-primary); backdrop-filter: blur(20px);
            border: 1px solid var(--border-color);
            border-radius: 16px; padding: 16px 24px;
            box-shadow: 0 8px 32px var(--shadow-color);
            color: var(--text-primary);
            font-family: -apple-system, BlinkMacSystemFont, sans-serif;
            animation: slideIn 0.3s ease;
            max-width: 320px;
            pointer-events: none;
        }
        @keyframes slideIn {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
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
            .focus-ring { width: 160px; height: 160px; }
            .focus-time { font-size: 2.6rem; }
        }
        @media (max-width: 480px) {
            .flag-item { flex-direction: column; align-items: stretch; }
            .calendar-day { min-height: 44px; font-size: 13px; }
            .calendar-day .status-symbol { font-size: 16px; }
            .theme-buttons { flex-wrap: wrap; }
            .focus-ring { width: 140px; height: 140px; }
            .focus-time { font-size: 2.2rem; }
            .focus-controls .btn { min-width: 60px; padding: 8px 14px; font-size: 0.9rem; }
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
        <a href="?page=focus" class="<?php echo $page=='focus'?'active':''; ?>" data-lang="focus"><?php echo $L['focus']; ?></a>
        <a href="?page=connect" class="<?php echo $page=='connect'?'active':''; ?>" data-lang="connect"><?php echo $L['connect']; ?></a>
        <a href="?page=settings" class="<?php echo $page=='settings'?'active':''; ?>" data-lang="settings"><?php echo $L['settings']; ?></a>
        <div class="actions">
            <div class="theme-buttons" id="themeButtons">
                <button data-theme="light" class="<?php echo $theme=='light'?'active':''; ?>" title="<?php echo $L['theme_light']; ?>">☀️</button>
                <button data-theme="dark" class="<?php echo $theme=='dark'?'active':''; ?>" title="<?php echo $L['theme_dark']; ?>">🌙</button>
                <button data-theme="system" class="<?php echo $theme=='system'?'active':''; ?>" title="<?php echo $L['theme_system']; ?>">💻</button>
            </div>
            <select id="langSelect">
                <?php foreach ($all_langs as $code): ?>
                <option value="<?php echo $code; ?>" <?php echo $code==$lang_code?'selected':''; ?>>
                    <?php echo $lang_names[$code] ?? $code; ?>
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
            <h2><span data-lang="today"><?php echo $L['today']; ?></span> · <?php echo $selected_date; ?></h2>
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

        <div class="glass-card" style="margin-top:12px; background: var(--accent); color:#fff; border-color:var(--accent);">
            <div class="flex flex-between">
                <div><strong data-lang="focus_quick"><?php echo $L['focus_quick'] ?? '开始专注'; ?></strong> <span style="font-size:14px; opacity:0.8;">25 min</span></div>
                <a href="?page=focus" class="btn" style="background:rgba(255,255,255,0.2); color:#fff; border-color:rgba(255,255,255,0.3);">▶</a>
            </div>
        </div>

        <?php 
        $stats = getFlagStats($selected_date, $include_future);
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
                <a href="?page=home&date=<?php echo date('Y-m-d', strtotime('-1 day', strtotime($selected_date))); ?>" class="btn">‹</a>
                <a href="?page=home&date=<?php echo date('Y-m-d'); ?>" class="btn" data-lang="today"><?php echo $L['today']; ?></a>
                <a href="?page=home&date=<?php echo date('Y-m-d', strtotime('+1 day', strtotime($selected_date))); ?>" class="btn">›</a>
            </div>
            <label style="font-size:14px; color:var(--text-secondary);">
                <input type="checkbox" <?php echo $include_future?'checked':''; ?> 
                       onchange="location.href='?page=home&date=<?php echo $selected_date; ?>&include_future='+this.checked">
                <span data-lang="include_future"><?php echo $L['include_future']; ?></span>
            </label>
        </div>

        <?php 
        $flags = getFlagsByDate($selected_date, $include_future);
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
                    <a href="?page=edit&id=<?php echo $f['id']; ?>&date=<?php echo $selected_date; ?>" class="btn" data-lang="edit"><?php echo $L['edit']; ?></a>
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
        $edit_id = (int)$_GET['id'];
        $edit_data = $conn->query("SELECT * FROM `flags` WHERE id=$edit_id")->fetch_assoc();
        if ($edit_data):
    ?>
    <div class="glass">
        <h2><span data-lang="edit"><?php echo $L['edit']; ?></span> Flag</h2>
        <form method="POST">
            <input type="hidden" name="action" value="edit_flag">
            <input type="hidden" name="id" value="<?php echo $edit_id; ?>">
            <div class="form-group"><label data-lang="title"><?php echo $L['title']; ?></label><input class="form-control" name="title" value="<?php echo htmlspecialchars($edit_data['title']); ?>" required></div>
            <div class="form-group"><label data-lang="content"><?php echo $L['content']; ?></label><textarea class="form-control" name="content" rows="3"><?php echo htmlspecialchars($edit_data['content']); ?></textarea></div>
            <div class="form-group"><label data-lang="tags"><?php echo $L['tags']; ?></label><input class="form-control" name="tags" value="<?php echo htmlspecialchars($edit_data['tags']); ?>"></div>
            <div class="form-group"><label><input type="checkbox" name="completed" <?php echo $edit_data['completed']?'checked':''; ?>> <span data-lang="completed"><?php echo $L['completed']; ?></span></label></div>
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
            <a href="?page=stats&view=day" class="btn <?php echo $view_type=='day'?'btn-primary':''; ?>" data-lang="day"><?php echo $L['day']; ?></a>
            <a href="?page=stats&view=month" class="btn <?php echo $view_type=='month'?'btn-primary':''; ?>" data-lang="month"><?php echo $L['month']; ?></a>
            <a href="?page=stats&view=year" class="btn <?php echo $view_type=='year'?'btn-primary':''; ?>" data-lang="year"><?php echo $L['year']; ?></a>
            <span style="margin-left:auto;">
                <label><input type="checkbox" <?php echo $include_future?'checked':''; ?> onchange="location.href='?page=stats&view=<?php echo $view_type; ?>&include_future='+this.checked"> <span data-lang="include_future"><?php echo $L['include_future']; ?></span></label>
            </span>
            <form method="POST" style="display:inline;"><input type="hidden" name="action" value="scan"><button class="btn" type="submit" data-lang="scan">🔄 <?php echo $L['scan']; ?></button></form>
        </div>
        <?php
        if ($view_type == 'day') {
            $stats = getFlagStats($selected_date, $include_future);
            $total = $stats['total']; $done = $stats['done']; $percent = $stats['percent']; $unpercent = $stats['unpercent'];
        } elseif ($view_type == 'month') {
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
            $res = $conn->query("SELECT COUNT(*) as total, SUM(completed) as done FROM `flags` WHERE YEAR(target_date) = $year".($include_future?"":" AND target_date <= CURDATE()"));
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

        <?php $focusStats = getFocusStats(); ?>
        <div style="margin-top:24px;">
            <h3 data-lang="focus_stats"><?php echo $L['focus_stats'] ?? '专注统计'; ?></h3>
            <div class="grid-2" style="margin-top:12px;">
                <div class="glass-card"><div class="stat-label" data-lang="focus_today_count"><?php echo $L['focus_today_count'] ?? '今日专注次数'; ?></div><div class="stat-number"><?php echo $focusStats['today_count']; ?></div></div>
                <div class="glass-card"><div class="stat-label" data-lang="focus_today_min"><?php echo $L['focus_today_min'] ?? '今日专注分钟'; ?></div><div class="stat-number"><?php echo round($focusStats['today_sec'] / 60, 1); ?></div></div>
                <div class="glass-card"><div class="stat-label" data-lang="focus_total_count"><?php echo $L['focus_total_count'] ?? '累计专注次数'; ?></div><div class="stat-number"><?php echo $focusStats['total_count']; ?></div></div>
                <div class="glass-card"><div class="stat-label" data-lang="focus_total_min"><?php echo $L['focus_total_min'] ?? '累计专注分钟'; ?></div><div class="stat-number"><?php echo round($focusStats['total_sec'] / 60, 1); ?></div></div>
            </div>
            <?php if ($focusStats['recent']->num_rows > 0): ?>
            <div style="margin-top:16px;">
                <h4 data-lang="focus_recent"><?php echo $L['focus_recent'] ?? '最近记录'; ?></h4>
                <?php while ($row = $focusStats['recent']->fetch_assoc()): ?>
                <div class="flag-item" style="border-bottom:1px solid var(--border-color); padding:8px 0;">
                    <div style="flex:1;">
                        <div style="font-weight:500;"><?php echo date('m-d H:i', strtotime($row['started_at'])); ?>  - <?php echo round($row['duration_seconds']/60,1); ?> min</div>
                        <div style="font-size:14px; color:var(--text-secondary);">
                            <?php echo $row['flag_title'] ? '🎯 ' . htmlspecialchars($row['flag_title']) : '📌 ' . ($L['no_flag'] ?? '无关联'); ?>
                        </div>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>
            <?php endif; ?>
        </div>

        <?php if ($view_type == 'month'): ?>
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
        $sql = "SELECT tags FROM `flags`";
        if (!$include_future) $sql .= " WHERE target_date <= CURDATE()";
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

    <?php if ($page == 'focus'): 
        $unfinishedFlags = $conn->query("SELECT id, title FROM `flags` WHERE completed = 0 ORDER BY target_date ASC");
    ?>
    <div class="glass">
        <h2 data-lang="focus"><?php echo $L['focus']; ?></h2>
        <div class="focus-timer">
            <div class="focus-ring">
                <svg width="100%" height="100%" viewBox="0 0 200 200">
                    <circle class="bg-circle" cx="100" cy="100" r="88" />
                    <circle class="progress-circle" id="focusProgressCircle" cx="100" cy="100" r="88"
                            stroke-dasharray="553" stroke-dashoffset="0" />
                </svg>
                <div class="focus-time" id="focusTimeDisplay">25:00</div>
            </div>

            <div class="focus-controls">
                <button class="btn btn-success" id="focusStartBtn" data-lang="focus_start"><?php echo $L['focus_start']; ?></button>
                <button class="btn btn-primary" id="focusPauseBtn" style="display:none;" data-lang="focus_pause"><?php echo $L['focus_pause']; ?></button>
                <button class="btn btn-primary" id="focusResumeBtn" style="display:none;" data-lang="focus_resume"><?php echo $L['focus_resume']; ?></button>
                <button class="btn btn-danger" id="focusResetBtn" data-lang="focus_reset"><?php echo $L['focus_reset']; ?></button>
            </div>

            <div class="focus-settings">
                <label data-lang="focus_duration"><?php echo $L['focus_duration']; ?></label>
                <input type="number" id="focusDurationInput" min="1" max="120" value="25"> <span style="color:var(--text-secondary);">min</span>
                <label data-lang="focus_link_flag"><?php echo $L['focus_link_flag'] ?? '关联Flag'; ?></label>
                <select id="focusFlagSelect">
                    <option value=""><?php echo $L['none'] ?? '无'; ?></option>
                    <?php while ($f = $unfinishedFlags->fetch_assoc()): ?>
                    <option value="<?php echo $f['id']; ?>"><?php echo htmlspecialchars($f['title']); ?></option>
                    <?php endwhile; ?>
                </select>
            </div>

            <div class="focus-stats-mini">
                <div class="stat-item"><div class="stat-number" id="focusTodayCount">0</div><div class="stat-label" data-lang="focus_today_count"><?php echo $L['focus_today_count'] ?? '今日专注'; ?></div></div>
                <div class="stat-item"><div class="stat-number" id="focusTodayMin">0</div><div class="stat-label" data-lang="focus_today_min"><?php echo $L['focus_today_min'] ?? '今日分钟'; ?></div></div>
            </div>
        </div>
    </div>

    <script>
    (function() {
        const displayEl = document.getElementById('focusTimeDisplay');
        const progressCircle = document.getElementById('focusProgressCircle');
        const startBtn = document.getElementById('focusStartBtn');
        const pauseBtn = document.getElementById('focusPauseBtn');
        const resumeBtn = document.getElementById('focusResumeBtn');
        const resetBtn = document.getElementById('focusResetBtn');
        const durationInput = document.getElementById('focusDurationInput');
        const flagSelect = document.getElementById('focusFlagSelect');
        const todayCountEl = document.getElementById('focusTodayCount');
        const todayMinEl = document.getElementById('focusTodayMin');

        let state = { status: 'idle', remaining: 0, total: 0, paused: false, flag_id: null };
        const circumference = 553;

        function fetchState() {
            return fetch('?ajax=1&action=get_focus_state')
                .then(res => res.json())
                .then(data => {
                    if (data.status === 'idle') {
                        state.status = 'idle';
                        if (startBtn.style.display !== 'inline-block') resetUI();
                        setTimeout(pollState, 5000);
                    } else {
                        state.status = 'running';
                        state.remaining = data.remaining;
                        state.total = data.total;
                        state.paused = data.paused;
                        state.flag_id = data.flag_id;
                        updateUI();
                        if (data.remaining <= 0) {
                            completeFocus();
                            setTimeout(pollState, 5000);
                        } else {
                            setTimeout(pollState, 1000);
                        }
                    }
                });
        }

        function pollState() {
            fetchState().catch(() => setTimeout(pollState, 3000));
        }

        function updateUI() {
            if (state.status === 'idle') {
                displayEl.textContent = '25:00';
                progressCircle.style.strokeDashoffset = '0';
                startBtn.style.display = 'inline-block';
                pauseBtn.style.display = 'none';
                resumeBtn.style.display = 'none';
                return;
            }
            const remaining = state.remaining;
            const total = state.total;
            const mins = Math.floor(remaining / 60);
            const secs = remaining % 60;
            displayEl.textContent = `${String(mins).padStart(2, '0')}:${String(secs).padStart(2, '0')}`;
            const progress = total > 0 ? remaining / total : 0;
            progressCircle.style.strokeDashoffset = circumference * (1 - progress);
            progressCircle.style.stroke = progress < 0.2 ? 'var(--danger)' : 'var(--accent)';

            if (state.paused) {
                startBtn.style.display = 'none';
                pauseBtn.style.display = 'none';
                resumeBtn.style.display = 'inline-block';
            } else {
                startBtn.style.display = 'none';
                pauseBtn.style.display = 'inline-block';
                resumeBtn.style.display = 'none';
            }
        }

        function resetUI() {
            state.status = 'idle';
            state.remaining = 0;
            state.total = 0;
            state.paused = false;
            state.flag_id = null;
            displayEl.textContent = '25:00';
            progressCircle.style.strokeDashoffset = '0';
            progressCircle.style.stroke = 'var(--accent)';
            startBtn.style.display = 'inline-block';
            pauseBtn.style.display = 'none';
            resumeBtn.style.display = 'none';
        }

        function sendAction(action, data) {
            const formData = new FormData();
            formData.append('ajax', '1');
            formData.append('action', action);
            if (data) {
                for (let key in data) formData.append(key, data[key]);
            }
            return fetch('', { method: 'POST', body: formData });
        }

        function startFocus() {
            const duration = parseInt(durationInput.value) || 25;
            const flagId = flagSelect.value;
            sendAction('start_focus', { duration: duration, flag_id: flagId })
                .then(() => pollState());
        }

        function pauseFocus() {
            sendAction('pause_focus').then(() => pollState());
        }

        function resumeFocus() {
            sendAction('resume_focus').then(() => pollState());
        }

        function resetFocus() {
            if (confirm('<?php echo $L['focus_reset_confirm'] ?? '确定要重置当前专注吗？'; ?>')) {
                sendAction('reset_focus').then(() => {
                    resetUI();
                    pollState();
                });
            }
        }

        function playSound() {
            if (localStorage.getItem('focus_sound_enabled') !== 'off') {
                try {
                    const ctx = new (window.AudioContext || window.webkitAudioContext)();
                    const osc = ctx.createOscillator();
                    const gain = ctx.createGain();
                    osc.connect(gain);
                    gain.connect(ctx.destination);
                    osc.frequency.value = 800;
                    gain.gain.setValueAtTime(0.3, ctx.currentTime);
                    gain.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + 0.5);
                    osc.start(ctx.currentTime);
                    osc.stop(ctx.currentTime + 0.5);
                } catch(e) {}
            }
        }

        function completeFocus() {
            fetch('', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'ajax=1&action=save_focus_complete'
            }).then(() => {
                playSound();
                if (Notification.permission === 'granted') {
                    new Notification('<?php echo $L['focus_complete'] ?? '专注完成！'; ?>', {
                        body: '<?php echo $L['focus_complete_body'] ?? '恭喜你完成一次专注！'; ?>',
                        icon: 'data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><text y=".9em" font-size="90">🍅</text></svg>'
                    });
                }
                resetUI();
                updateMiniStats();
            });
        }

        function updateMiniStats() {
            fetch('?ajax=1&action=get_focus_stats')
                .then(res => res.json())
                .then(data => {
                    todayCountEl.textContent = data.today_count || 0;
                    todayMinEl.textContent = data.today_min || 0;
                });
        }

        startBtn.addEventListener('click', startFocus);
        pauseBtn.addEventListener('click', pauseFocus);
        resumeBtn.addEventListener('click', resumeFocus);
        resetBtn.addEventListener('click', resetFocus);

        if ('Notification' in window && Notification.permission === 'default') {
            Notification.requestPermission();
        }

        fetch('?ajax=1&action=get_notification_settings')
            .then(res => res.json())
            .then(data => {
                localStorage.setItem('focus_sound_enabled', data.focus_sound || 'on');
            });

        pollState();
        updateMiniStats();
        setInterval(updateMiniStats, 60000);
    })();
    </script>
    <?php endif; ?>

    <?php if ($page == 'connect'): ?>
    <div class="glass">
        <h2 data-lang="connect"><?php echo $L['connect']; ?></h2>
        <p style="color:var(--text-secondary); margin-bottom:16px;" data-lang="connect_desc"><?php echo $L['connect_desc'] ?? '已连接设备列表'; ?></p>
        <div id="deviceList">
            <div class="glass-card" style="text-align:center; padding:20px;">
                <span data-lang="loading"><?php echo $L['loading'] ?? '加载中...'; ?></span>
            </div>
        </div>
        <div style="margin-top:20px;">
            <button class="btn btn-primary" onclick="refreshDevices()" data-lang="refresh"><?php echo $L['refresh'] ?? '刷新'; ?></button>
        </div>

        <div style="margin-top:32px;">
            <h3 data-lang="api_doc"><?php echo $L['api_doc'] ?? 'API 文档'; ?></h3>
            <div class="api-doc">
                <strong>端点：</strong><code>/api</code> (即 <code>?page=api</code>)<br>
                <strong>认证：</strong> 请求头 <code>Authorization: Bearer &lt;token&gt;</code> 或 URL 参数 <code>?token=...</code><br>
                <strong>令牌：</strong> 在设置页面查看或重置。<br><br>
                <strong>Flag 操作：</strong>
                <ul style="margin-left:20px; list-style:disc;">
                    <li><code>GET /api/flags</code> - 获取所有 Flag（支持参数：<code>date</code>, <code>date_from</code>, <code>date_to</code>, <code>status=completed|pending</code>, <code>tag=xxx</code>）</li>
                    <li><code>GET /api/flags/{id}</code> - 获取单个 Flag</li>
                    <li><code>POST /api/flags</code> - 创建 Flag（JSON: <code>{"title":"...", "content":"...", "tags":"...", "target_date":"..."}</code>）</li>
                    <li><code>PUT /api/flags/{id}</code> - 更新 Flag（JSON 字段任意）</li>
                    <li><code>DELETE /api/flags/{id}</code> - 删除 Flag</li>
                </ul>
                <strong>统计与专注：</strong>
                <ul style="margin-left:20px; list-style:disc;">
                    <li><code>GET /api/stats</code> - 获取今日统计</li>
                    <li><code>GET /api/focus/sessions</code> - 获取最近 50 条专注记录</li>
                    <li><code>GET /api/focus/stats</code> - 获取专注统计（今日次数/分钟，总次数/分钟）</li>
                </ul>
                <br>
                <strong>示例（curl）：</strong><br>
                <code>curl -H "Authorization: Bearer YOUR_TOKEN" "http://yourdomain.com/?page=api&api_path=flags?status=pending"</code>
            </div>
        </div>
    </div>

    <script>
        let deviceRefreshInterval = null;

        function refreshDevices() {
            fetch('?ajax=1&action=get_devices')
                .then(res => res.json())
                .then(data => {
                    const container = document.getElementById('deviceList');
                    if (data.length === 0) {
                        container.innerHTML = '<div class="glass-card" style="text-align:center; padding:20px;"><span data-lang="no_devices"><?php echo $L['no_devices'] ?? '暂无设备'; ?></span></div>';
                        return;
                    }
                    let html = '';
                    data.forEach(dev => {
                        const status = dev.is_online ? '<span class="online">● 在线</span>' : '<span class="offline">○ 离线</span>';
                        const ua = dev.ua || 'Unknown';
                        html += `
                            <div class="device-item">
                                <div>
                                    <div class="device-name">${escapeHtml(dev.device_id)}</div>
                                    <div class="device-ua">${escapeHtml(ua)}</div>
                                    <div style="font-size:13px; color:var(--text-secondary);">最后活动：${dev.last_seen}</div>
                                </div>
                                <div style="font-weight:500;">${status}</div>
                            </div>
                        `;
                    });
                    container.innerHTML = html;
                });
        }

        function escapeHtml(text) {
            if (!text) return '';
            return text.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
        }

        document.addEventListener('DOMContentLoaded', function() {
            if (document.getElementById('deviceList')) {
                refreshDevices();
                if (deviceRefreshInterval) clearInterval(deviceRefreshInterval);
                deviceRefreshInterval = setInterval(refreshDevices, 10000);
            }
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

        <div style="margin-bottom:24px;">
            <h3 data-lang="api_token"><?php echo $L['api_token'] ?? 'API 令牌'; ?></h3>
            <div class="glass-card">
                <div style="display:flex; align-items:center; gap:16px; flex-wrap:wrap;">
                    <span style="font-weight:600;"><?php echo $L['current_token'] ?? '当前令牌'; ?>:</span>
                    <code id="apiTokenDisplay" style="background:var(--input-bg); padding:6px 12px; border-radius:8px; word-break:break-all;">
                        <?php echo getSetting('api_token'); ?>
                    </code>
                    <button class="btn btn-primary" onclick="resetApiToken()" data-lang="reset_token"><?php echo $L['reset_token'] ?? '重置令牌'; ?></button>
                </div>
            </div>
        </div>

        <div style="margin-bottom:24px;">
            <h3 data-lang="notification_settings"><?php echo $L['notification_settings'] ?? '设备上线通知'; ?></h3>
            <div class="glass-card">
                <div style="display:flex; align-items:center; gap:16px; flex-wrap:wrap;">
                    <label>
                        <input type="checkbox" id="notifToggle" <?php echo getSetting('device_notification') !== 'off' ? 'checked' : ''; ?>>
                        <span data-lang="enable_notification"><?php echo $L['enable_notification'] ?? '启用通知'; ?></span>
                    </label>
                    <label>
                        <span data-lang="notification_duration"><?php echo $L['notification_duration'] ?? '弹窗秒数'; ?>:</span>
                        <input type="number" id="notifDuration" min="1" max="10" value="<?php echo getSetting('notification_duration') ?: 3; ?>" style="width:60px; padding:4px 8px; border-radius:8px; border:1px solid var(--border-color); background:var(--input-bg); color:var(--text-primary);">
                        <span style="color:var(--text-secondary);">s</span>
                    </label>
                    <button class="btn btn-primary" onclick="saveNotificationSettings()" data-lang="save"><?php echo $L['save']; ?></button>
                </div>
            </div>
        </div>

        <div style="margin-bottom:24px;">
            <h3 data-lang="focus_sound_settings"><?php echo $L['focus_sound_settings'] ?? '专注完成提示音'; ?></h3>
            <div class="glass-card">
                <div style="display:flex; align-items:center; gap:16px; flex-wrap:wrap;">
                    <label>
                        <input type="checkbox" id="focusSoundToggle" <?php echo getSetting('focus_sound') !== 'off' ? 'checked' : ''; ?>>
                        <span data-lang="enable_focus_sound"><?php echo $L['enable_focus_sound'] ?? '启用提示音'; ?></span>
                    </label>
                    <button class="btn btn-primary" onclick="saveFocusSound()" data-lang="save"><?php echo $L['save']; ?></button>
                </div>
            </div>
        </div>

        <div>
            <h3 data-lang="device"><?php echo $L['device']; ?></h3>
            <div class="glass-card"><?php echo getDeviceType(); ?> · <?php echo htmlspecialchars($_SERVER['HTTP_USER_AGENT']); ?></div>
        </div>
    </div>

    <script>
        function resetApiToken() {
            if (!confirm('<?php echo $L['reset_token_confirm'] ?? '确定要重置 API 令牌吗？旧令牌将立即失效。'; ?>')) return;
            fetch('', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'ajax=1&action=reset_token'
            })
            .then(res => res.json())
            .then(data => {
                if (data.status === 'ok') {
                    document.getElementById('apiTokenDisplay').innerText = data.token;
                    alert('<?php echo $L['token_reset_success'] ?? '令牌已重置'; ?>');
                }
            });
        }

        function saveNotificationSettings() {
            const enabled = document.getElementById('notifToggle').checked ? 'on' : 'off';
            const duration = document.getElementById('notifDuration').value || 3;
            fetch('', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'ajax=1&action=save_notification&enabled=' + enabled + '&duration=' + duration
            })
            .then(res => res.json())
            .then(data => {
                if (data.status === 'ok') alert('<?php echo $L['save'] . ' ' . ($L['success'] ?? ''); ?>');
            });
        }

        function saveFocusSound() {
            const enabled = document.getElementById('focusSoundToggle').checked ? 'on' : 'off';
            fetch('', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'ajax=1&action=save_focus_sound&enabled=' + enabled
            })
            .then(res => res.json())
            .then(data => {
                if (data.status === 'ok') alert('<?php echo $L['save'] . ' ' . ($L['success'] ?? ''); ?>');
            });
        }
    </script>
    <?php endif; ?>

    <div style="text-align:center; padding:16px 0; opacity:0.5; font-size:12px; color:var(--text-secondary);">
        <span data-lang="app_name"><?php echo $L['app_name']; ?></span> · <?php echo date('Y'); ?>
    </div>
</div>
<div class="device-badge"><?php echo getDeviceType(); ?></div>

<script>
var LANG_DATA = <?php echo json_encode($langs); ?>;
var currentLang = localStorage.getItem('lang') || '<?php echo $lang_code; ?>';
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

function getNotifiedDevices() {
    try {
        return JSON.parse(localStorage.getItem('notified_devices') || '[]');
    } catch(e) { return []; }
}
function setNotifiedDevices(list) {
    localStorage.setItem('notified_devices', JSON.stringify(list));
}
function getKnownDeviceIds() {
    try {
        return JSON.parse(localStorage.getItem('known_device_ids') || '[]');
    } catch(e) { return []; }
}
function setKnownDeviceIds(list) {
    localStorage.setItem('known_device_ids', JSON.stringify(list));
}

function registerDevice() {
    let deviceId = localStorage.getItem('device_id');
    if (!deviceId) {
        deviceId = 'device_' + Date.now() + '_' + Math.random().toString(36).substr(2, 6);
        localStorage.setItem('device_id', deviceId);
    }
    fetch(`?ajax=1&action=register_device&device_id=${encodeURIComponent(deviceId)}`)
        .then(res => res.json())
        .then(data => {
            if (data.status === 'ok') {
                if (window.heartbeatInterval) clearInterval(window.heartbeatInterval);
                window.heartbeatInterval = setInterval(() => {
                    fetch(`?ajax=1&action=heartbeat&device_id=${encodeURIComponent(deviceId)}`);
                }, 15000);
                fetch('?ajax=1&action=get_notification_settings')
                    .then(res => res.json())
                    .then(setting => {
                        localStorage.setItem('notif_enabled', setting.enabled);
                        localStorage.setItem('notif_duration', setting.duration);
                        localStorage.setItem('focus_sound_enabled', setting.focus_sound || 'on');
                    });
                pollDevices();
                if (window.devicePollInterval) clearInterval(window.devicePollInterval);
                window.devicePollInterval = setInterval(pollDevices, 10000);
            }
        });
}

function pollDevices() {
    fetch('?ajax=1&action=get_devices')
        .then(res => res.json())
        .then(data => {
            const onlineIds = data.filter(d => d.is_online).map(d => d.device_id);
            const knownIds = getKnownDeviceIds();
            let notified = getNotifiedDevices();

            const isFirstLoad = knownIds.length === 0;

            if (!isFirstLoad) {
                const newOnline = data.filter(d => 
                    d.is_online && 
                    !knownIds.includes(d.device_id) && 
                    !notified.includes(d.device_id)
                );
                if (newOnline.length > 0) {
                    const notifEnabled = localStorage.getItem('notif_enabled') !== 'off';
                    if (notifEnabled) {
                        const duration = parseInt(localStorage.getItem('notif_duration')) || 3;
                        newOnline.forEach(dev => {
                            showDeviceNotification(dev, duration);
                            notified.push(dev.device_id);
                        });
                        setNotifiedDevices(notified);
                    }
                }
            }

            const allIds = data.map(d => d.device_id);
            setKnownDeviceIds(allIds);

            const onlineSet = new Set(onlineIds);
            const newNotified = notified.filter(id => onlineSet.has(id));
            if (newNotified.length !== notified.length) {
                setNotifiedDevices(newNotified);
            }
        });
}

function showDeviceNotification(dev, duration) {
    try {
        const ctx = new (window.AudioContext || window.webkitAudioContext)();
        const osc = ctx.createOscillator();
        const gain = ctx.createGain();
        osc.connect(gain);
        gain.connect(ctx.destination);
        osc.frequency.value = 1200;
        gain.gain.setValueAtTime(0.3, ctx.currentTime);
        gain.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + 0.3);
        osc.start(ctx.currentTime);
        osc.stop(ctx.currentTime + 0.3);
    } catch(e) {}

    const existing = document.getElementById('deviceNotif');
    if (existing) existing.remove();
    const notif = document.createElement('div');
    notif.id = 'deviceNotif';
    notif.className = 'notif-popup';
    notif.innerHTML = `
        <div style="display:flex; align-items:center; gap:12px;">
            <span style="font-size:24px;">🔗</span>
            <div>
                <div style="font-weight:600;"><span data-lang="device_online">设备上线</span></div>
                <div style="font-size:14px; color:var(--text-secondary);">${escapeHtml(dev.device_id)}</div>
                <div style="font-size:12px; color:var(--text-secondary);">${escapeHtml(dev.ua || 'Unknown')}</div>
            </div>
        </div>
    `;
    document.body.appendChild(notif);
    setTimeout(() => {
        if (notif.parentNode) {
            notif.style.opacity = '0';
            notif.style.transition = 'opacity 0.5s';
            setTimeout(() => notif.remove(), 500);
        }
    }, duration * 1000);
}

function escapeHtml(text) {
    if (!text) return '';
    return text.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
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
        applyLanguage('<?php echo $lang_code; ?>');
    }
    registerDevice();
});
</script>
</body>
</html>
<?php $conn->close(); ?>
