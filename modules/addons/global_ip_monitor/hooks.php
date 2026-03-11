<?php
/**
 * Global IP Monitor
 * WHMCS Addon Module - Hooks
 *
 * @version 1.0.0
 */

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

use WHMCS\Database\Capsule;

function gipm_get_real_ip()
{
    $ip = '';

    if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
        $ip = $_SERVER['HTTP_CF_CONNECTING_IP'];
    }
    elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $parts = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        $ip = trim($parts[0]);
    }
    elseif (!empty($_SERVER['HTTP_X_REAL_IP'])) {
        $ip = $_SERVER['HTTP_X_REAL_IP'];
    }
    elseif (!empty($_SERVER['REMOTE_ADDR'])) {
        $ip = $_SERVER['REMOTE_ADDR'];
    }

    $validated = filter_var(trim($ip), FILTER_VALIDATE_IP);
    return $validated ?: ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
}

add_hook('UserLogin', 1, function ($vars) {
    try {
        $clientId = 0;

        if (!empty($vars['user']) && is_object($vars['user'])) {
            $user = $vars['user'];
            if (property_exists($user, 'clients') && $user->clients instanceof \Illuminate\Database\Eloquent\Collection) {
                $firstClient = $user->clients->first();
                if ($firstClient) {
                    $clientId = $firstClient->id;
                }
            }
            if ($clientId === 0 && property_exists($user, 'email')) {
                $client = Capsule::table('tblclients')->where('email', $user->email)->first();
                if ($client) {
                    $clientId = $client->id;
                }
            }
        }

        if ($clientId === 0 && isset($_SESSION['uid'])) {
            $clientId = (int)$_SESSION['uid'];
        }

        if ($clientId === 0 && !empty($vars['userid'])) {
            $clientId = (int)$vars['userid'];
        }

        $ipAddress = gipm_get_real_ip();
        $port = isset($_SERVER['REMOTE_PORT']) ? (int)$_SERVER['REMOTE_PORT'] : null;
        $userAgent = isset($_SERVER['HTTP_USER_AGENT']) ? mb_substr($_SERVER['HTTP_USER_AGENT'], 0, 512) : '';

        $hostname = @gethostbyaddr($ipAddress);
        if ($hostname === false || $hostname === $ipAddress) {
            $hostname = null;
        }

        Capsule::table('mod_ip_monitor_logs')->insert([
            'client_id' => $clientId,
            'ip_address' => $ipAddress,
            'port' => $port,
            'hostname' => $hostname,
            'user_agent' => $userAgent,
            'login_type' => 'client_area',
            'login_at' => date('Y-m-d H:i:s'),
        ]);

    }
    catch (\Exception $e) {
        logActivity('Global IP Monitor Error: ' . $e->getMessage());
    }
});

add_hook('AdminLogin', 1, function ($vars) {
    try {
        $adminId = !empty($vars['adminid']) ? (int)$vars['adminid'] : 0;
        $ipAddress = gipm_get_real_ip();
        $port = isset($_SERVER['REMOTE_PORT']) ? (int)$_SERVER['REMOTE_PORT'] : null;
        $userAgent = isset($_SERVER['HTTP_USER_AGENT']) ? mb_substr($_SERVER['HTTP_USER_AGENT'], 0, 512) : '';

        $hostname = @gethostbyaddr($ipAddress);
        if ($hostname === false || $hostname === $ipAddress) {
            $hostname = null;
        }

        Capsule::table('mod_ip_monitor_logs')->insert([
            'client_id' => 0,
            'ip_address' => $ipAddress,
            'port' => $port,
            'hostname' => $hostname,
            'user_agent' => $userAgent,
            'login_type' => 'admin_' . $adminId,
            'login_at' => date('Y-m-d H:i:s'),
        ]);

    }
    catch (\Exception $e) {
        logActivity('Global IP Monitor Error: ' . $e->getMessage());
    }
});

add_hook('DailyCronJob', 1, function ($vars) {
    try {
        $setting = Capsule::table('tbladdonmodules')
            ->where('module', 'global_ip_monitor')
            ->where('setting', 'log_retention_days')
            ->first();

        $retentionDays = ($setting && $setting->value > 0) ? (int)$setting->value : 365;

        if ($retentionDays > 0) {
            $cutoffDate = date('Y-m-d H:i:s', strtotime("-{$retentionDays} days"));
            $deleted = Capsule::table('mod_ip_monitor_logs')
                ->where('login_at', '<', $cutoffDate)
                ->delete();

            if ($deleted > 0) {
                logActivity("Global IP Monitor: {$deleted} kayıt temizlendi.");
            }
        }
    }
    catch (\Exception $e) {
        logActivity('Global IP Monitor Error: ' . $e->getMessage());
    }
});
