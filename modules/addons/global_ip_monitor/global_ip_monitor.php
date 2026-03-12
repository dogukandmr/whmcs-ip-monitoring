<?php
/**
 * Global IP Monitor
 * WHMCS Addon Module
 *
 * @author HazalHost
 * @version 1.0.0
 */

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

use WHMCS\Database\Capsule;

function global_ip_monitor_config()
{
    return [
        'name' => 'Global IP Monitor',
        'description' => 'Müşteri ve admin girişlerinde IP adresi, port, hostname ve tarayıcı bilgilerini otomatik olarak loglar. WHMCS yalnızca son IP'yi gösterir; bu modül tüm giriş geçmişini kaydeder ve yöneticiye raporlar.',
        'version' => '1.0.0',
        'author' => 'HazalHost',
        'language' => 'turkish',
        'fields' => [
            'log_retention_days' => [
                'FriendlyName' => 'Log Saklama Süresi (Gün)',
                'Type' => 'text',
                'Size' => '10',
                'Default' => '365',
                'Description' => 'Loglar kaç gün saklanacak? (0 = sınırsız)',
            ],
        ],
    ];
}

function global_ip_monitor_activate()
{
    try {
        Capsule::statement("
            CREATE TABLE IF NOT EXISTS `mod_ip_monitor_logs` (
                `id`         INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
                `client_id`  INT(10) UNSIGNED NOT NULL DEFAULT '0',
                `ip_address` VARCHAR(45)      NOT NULL DEFAULT '',
                `port`       SMALLINT(5)      UNSIGNED NULL DEFAULT NULL,
                `hostname`   VARCHAR(255)     NULL DEFAULT NULL,
                `user_agent` VARCHAR(512)     NULL DEFAULT NULL,
                `login_type` VARCHAR(50)      NOT NULL DEFAULT 'client_area',
                `login_at`   DATETIME         NOT NULL,
                PRIMARY KEY  (`id`),
                KEY `idx_client`   (`client_id`),
                KEY `idx_ip`       (`ip_address`),
                KEY `idx_login_at` (`login_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");

        return [
            'status' => 'success',
            'description' => 'Global IP Monitor başarıyla aktif edildi.',
        ];
    }
    catch (\Exception $e) {
        return [
            'status' => 'error',
            'description' => 'Hata: ' . $e->getMessage(),
        ];
    }
}

function global_ip_monitor_deactivate()
{
    try {
        Capsule::schema()->dropIfExists('mod_ip_monitor_logs');

        return [
            'status' => 'success',
            'description' => 'Global IP Monitor devre dışı bırakıldı.',
        ];
    }
    catch (\Exception $e) {
        return [
            'status' => 'error',
            'description' => 'Hata: ' . $e->getMessage(),
        ];
    }
}

function global_ip_monitor_output($vars)
{
    $modulelink = $vars['modulelink'];
    $action = isset($_GET['action']) ? $_GET['action'] : 'list';

    if ($action === 'ajax_logs') {
        global_ip_monitor_ajax_logs();
        return;
    }

    if ($action === 'client_detail') {
        global_ip_monitor_client_detail($vars);
        return;
    }

    global_ip_monitor_main_page($vars);
}

function global_ip_monitor_main_page($vars)
{
    $modulelink = $vars['modulelink'];

    $totalLogs = Capsule::table('mod_ip_monitor_logs')->count();
    $uniqueIps = Capsule::table('mod_ip_monitor_logs')->distinct('ip_address')->count('ip_address');
    $uniqueClients = Capsule::table('mod_ip_monitor_logs')->distinct('client_id')->count('client_id');
    $todayLogs = Capsule::table('mod_ip_monitor_logs')
        ->whereDate('login_at', '=', date('Y-m-d'))
        ->count();

    echo '<link rel="stylesheet" href="../modules/addons/global_ip_monitor/assets/css/style.css">';
    echo '<div class="ip-monitor-wrapper">';

    echo '<div class="ip-monitor-header">';
    echo '<h2><i class="fas fa-globe"></i> Global IP Monitor</h2>';
    echo '<p class="text-muted">Toplam ' . number_format($totalLogs) . ' giriş kaydı izleniyor.</p>';
    echo '</div>';

    echo '<div class="ip-monitor-stats">';
    echo '<div class="stat-card"><div class="stat-number">' . number_format($totalLogs) . '</div><div class="stat-label">Toplam Kayıt</div></div>';
    echo '<div class="stat-card"><div class="stat-number">' . number_format($uniqueClients) . '</div><div class="stat-label">Tekil Müşteri</div></div>';
    echo '<div class="stat-card"><div class="stat-number">' . number_format($uniqueIps) . '</div><div class="stat-label">Tekil IP</div></div>';
    echo '<div class="stat-card"><div class="stat-number">' . number_format($todayLogs) . '</div><div class="stat-label">Bugünkü Girişler</div></div>';
    echo '</div>';

    echo '<div class="ip-monitor-filters">';
    echo '<div class="filter-row">';
    echo '<div class="filter-item"><label>Müşteri ID:</label><input type="text" id="filter_client_id" class="form-control" placeholder="Müşteri ID"></div>';
    echo '<div class="filter-item"><label>IP Adresi:</label><input type="text" id="filter_ip" class="form-control" placeholder="IP Adresi"></div>';
    echo '<div class="filter-item"><label>Başlangıç:</label><input type="date" id="filter_date_from" class="form-control"></div>';
    echo '<div class="filter-item"><label>Bitiş:</label><input type="date" id="filter_date_to" class="form-control"></div>';
    echo '<div class="filter-item filter-buttons">';
    echo '<button class="btn btn-primary" id="btn_filter"><i class="fas fa-search"></i> Filtrele</button>';
    echo '<button class="btn btn-default" id="btn_reset"><i class="fas fa-times"></i> Sıfırla</button>';
    echo '</div>';
    echo '</div>';
    echo '</div>';

    echo '<div class="ip-monitor-table-wrap">';
    echo '<table id="ip_monitor_table" class="table table-striped table-hover" style="width:100%">';
    echo '<thead><tr><th>ID</th><th>Müşteri</th><th>IP Adresi</th><th>Port</th><th>Hostname</th><th>Giriş Tipi</th><th>Tarih</th><th>İşlem</th></tr></thead>';
    echo '<tbody></tbody>';
    echo '</table>';
    echo '</div>';

    echo '</div>';

    echo '<script>var moduleLink = "' . $modulelink . '";</script>';
    echo '<script src="../modules/addons/global_ip_monitor/assets/js/monitor.js"></script>';
}

function global_ip_monitor_client_detail($vars)
{
    $modulelink = $vars['modulelink'];
    $clientId = isset($_GET['client_id']) ? (int)$_GET['client_id'] : 0;

    if (!$clientId) {
        echo '<div class="alert alert-danger">Geçersiz müşteri ID.</div>';
        return;
    }

    $client = Capsule::table('tblclients')->where('id', $clientId)->first();

    if (!$client) {
        echo '<div class="alert alert-danger">Müşteri bulunamadı.</div>';
        return;
    }

    $logs = Capsule::table('mod_ip_monitor_logs')
        ->where('client_id', $clientId)
        ->orderBy('login_at', 'desc')
        ->get();

    $uniqueIps = Capsule::table('mod_ip_monitor_logs')
        ->where('client_id', $clientId)
        ->distinct('ip_address')
        ->count('ip_address');

    echo '<link rel="stylesheet" href="../modules/addons/global_ip_monitor/assets/css/style.css">';
    echo '<div class="ip-monitor-wrapper">';
    echo '<a href="' . $modulelink . '" class="btn btn-default" style="margin-bottom:15px;"><i class="fas fa-arrow-left"></i> Geri Dön</a>';

    echo '<div class="ip-monitor-header">';
    echo '<h2><i class="fas fa-user"></i> ' . htmlspecialchars($client->firstname . ' ' . $client->lastname) . '</h2>';
    echo '<p class="text-muted">' . htmlspecialchars($client->email) . ' &bull; ' . count($logs) . ' giriş &bull; ' . $uniqueIps . ' tekil IP</p>';
    echo '</div>';

    echo '<div class="ip-monitor-table-wrap">';
    echo '<table class="table table-striped table-hover datatable" style="width:100%">';
    echo '<thead><tr><th>IP Adresi</th><th>Port</th><th>Hostname</th><th>User Agent</th><th>Giriş Tipi</th><th>Tarih</th></tr></thead>';
    echo '<tbody>';

    foreach ($logs as $log) {
        echo '<tr>';
        echo '<td><strong>' . htmlspecialchars($log->ip_address) . '</strong></td>';
        echo '<td>' . ($log->port ?: '-') . '</td>';
        echo '<td>' . htmlspecialchars($log->hostname ?: '-') . '</td>';
        echo '<td><small>' . htmlspecialchars(mb_substr($log->user_agent ?: '-', 0, 80)) . '</small></td>';
        echo '<td>' . htmlspecialchars($log->login_type) . '</td>';
        echo '<td>' . date('d.m.Y H:i:s', strtotime($log->login_at)) . '</td>';
        echo '</tr>';
    }

    echo '</tbody></table></div>';
    echo '</div>';
}

function global_ip_monitor_ajax_logs()
{
    header('Content-Type: application/json');

    $draw = isset($_GET['draw']) ? (int)$_GET['draw'] : 1;
    $start = isset($_GET['start']) ? (int)$_GET['start'] : 0;
    $length = isset($_GET['length']) ? (int)$_GET['length'] : 25;
    $search = isset($_GET['search']['value']) ? $_GET['search']['value'] : '';
    $orderCol = isset($_GET['order'][0]['column']) ? (int)$_GET['order'][0]['column'] : 6;
    $orderDir = isset($_GET['order'][0]['dir']) ? $_GET['order'][0]['dir'] : 'desc';

    $filterClientId = isset($_GET['filter_client_id']) ? trim($_GET['filter_client_id']) : '';
    $filterIp = isset($_GET['filter_ip']) ? trim($_GET['filter_ip']) : '';
    $filterDateFrom = isset($_GET['filter_date_from']) ? trim($_GET['filter_date_from']) : '';
    $filterDateTo = isset($_GET['filter_date_to']) ? trim($_GET['filter_date_to']) : '';

    $columns = ['l.id', 'c.firstname', 'l.ip_address', 'l.port', 'l.hostname', 'l.login_type', 'l.login_at', ''];

    $query = Capsule::table('mod_ip_monitor_logs as l')
        ->leftJoin('tblclients as c', 'l.client_id', '=', 'c.id')
        ->select([
        'l.id', 'l.client_id', 'l.ip_address', 'l.port',
        'l.hostname', 'l.user_agent', 'l.login_type', 'l.login_at',
        Capsule::raw("CONCAT(COALESCE(c.firstname,''), ' ', COALESCE(c.lastname,'')) as client_name"),
        'c.email as client_email',
    ]);

    $totalRecords = Capsule::table('mod_ip_monitor_logs')->count();

    if (!empty($filterClientId)) {
        $query->where('l.client_id', $filterClientId);
    }
    if (!empty($filterIp)) {
        $query->where('l.ip_address', 'LIKE', '%' . $filterIp . '%');
    }
    if (!empty($filterDateFrom)) {
        $query->whereDate('l.login_at', '>=', $filterDateFrom);
    }
    if (!empty($filterDateTo)) {
        $query->whereDate('l.login_at', '<=', $filterDateTo);
    }
    if (!empty($search)) {
        $query->where(function ($q) use ($search) {
            $q->where('l.ip_address', 'LIKE', '%' . $search . '%')
                ->orWhere('c.firstname', 'LIKE', '%' . $search . '%')
                ->orWhere('c.lastname', 'LIKE', '%' . $search . '%')
                ->orWhere('c.email', 'LIKE', '%' . $search . '%')
                ->orWhere('l.hostname', 'LIKE', '%' . $search . '%');
        });
    }

    $filteredRecords = $query->count();

    $orderColumn = isset($columns[$orderCol]) && $columns[$orderCol] ? $columns[$orderCol] : 'l.login_at';
    $query->orderBy($orderColumn, $orderDir === 'asc' ? 'asc' : 'desc');

    $logs = $query->offset($start)->limit($length)->get();

    $adminCache = [];

    $data = [];
    foreach ($logs as $log) {
        $mlink = 'addonmodules.php?module=global_ip_monitor';

        if (strpos($log->login_type, 'admin_') === 0) {
            $adminId = (int)str_replace('admin_', '', $log->login_type);
            if (!isset($adminCache[$adminId])) {
                $adminRow = Capsule::table('tbladmins')->where('id', $adminId)->first();
                $adminCache[$adminId] = $adminRow ? $adminRow->username : 'ID#' . $adminId;
            }
            $displayName = 'Admin: ' . $adminCache[$adminId];
            $subLine = '';
            $actionBtn = '<span class="label label-default">ADMIN</span>';
        }
        else {
            $displayName = trim($log->client_name) ?: '—';
            $subLine = '<br><small class="text-muted">' . htmlspecialchars($log->client_email ?: '') . '</small>';
            $actionBtn = '<a href="clientssummary.php?userid=' . $log->client_id . '" class="btn btn-xs btn-default"><i class="fas fa-user"></i></a>';
        }

        $data[] = [
            $log->id,
            '<a href="' . $mlink . '&action=client_detail&client_id=' . $log->client_id . '">' . htmlspecialchars($displayName) . '</a>' . $subLine,
            '<strong>' . htmlspecialchars($log->ip_address) . '</strong>',
            $log->port ?: '-',
            htmlspecialchars($log->hostname ?: '-'),
            $log->login_type,
            date('d.m.Y H:i:s', strtotime($log->login_at)),
            $actionBtn,
        ];
    }

    echo json_encode([
        'draw' => $draw,
        'recordsTotal' => $totalRecords,
        'recordsFiltered' => $filteredRecords,
        'data' => $data,
    ]);
    exit;
}
