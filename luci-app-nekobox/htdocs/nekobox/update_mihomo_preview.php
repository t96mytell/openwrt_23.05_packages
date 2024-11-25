<?php

ini_set('memory_limit', '256M');

function logMessage($message) {
    $logFile = '/tmp/mihomo_prerelease_update.log';  
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND);
}

function writeVersionToFile($version) {
    $versionFile = '/etc/neko/core/mihomo_version.txt';
    $result = file_put_contents($versionFile, $version);
    if ($result === false) {
        logMessage("无法写入版本文件: $versionFile");
    }
}

$repo_owner = "MetaCubeX";
$repo_name = "mihomo";
$api_url = "https://api.github.com/repos/$repo_owner/$repo_name/releases";

$curl_command = "curl -s -H 'User-Agent: PHP' " . escapeshellarg($api_url);
$response = shell_exec($curl_command);

if ($response === false || empty($response)) {
    die("GitHub API 请求失败。请检查网络连接或稍后重试。");
}

$data = json_decode($response, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    die("解析 GitHub API 响应时出错: " . json_last_error_msg());
}

$latest_prerelease = null;
foreach ($data as $release) {
    if (isset($release['prerelease']) && $release['prerelease'] == true) {
        $latest_prerelease = $release;
        break;
    }
}

if ($latest_prerelease === null) {
    die("没有找到最新的预览版！");
}

$latest_version = $latest_prerelease['tag_name'] ?? '';
$assets = $latest_prerelease['assets'] ?? [];

if (empty($latest_version)) {
    echo "未找到最新版本信息。";
    exit;
}

echo "最新版本: " . htmlspecialchars($latest_version) . "\n";

$current_arch = trim(shell_exec("uname -m"));
$base_version = ltrim($latest_version, 'v');  

$download_url = '';
$asset_found = false;

foreach ($assets as $asset) {
    if ($current_arch === 'x86_64' && strpos($asset['name'], 'linux-amd64-alpha') !== false && strpos($asset['name'], '.gz') !== false) {
        $download_url = $asset['browser_download_url'];
        $asset_found = true;
        break;
    }
    if ($current_arch === 'aarch64' && strpos($asset['name'], 'linux-arm64-alpha') !== false && strpos($asset['name'], '.gz') !== false) {
        $download_url = $asset['browser_download_url'];
        $asset_found = true;
        break;
    }
    if ($current_arch === 'armv7l' && strpos($asset['name'], 'linux-armv7l-alpha') !== false && strpos($asset['name'], '.gz') !== false) {
        $download_url = $asset['browser_download_url'];
        $asset_found = true;
        break;
    }
}

if (!$asset_found) {
    die("未找到适合架构的预览版下载链接！");
}

echo "下载链接: $download_url\n";

$temp_file = '/tmp/mihomo_prerelease.gz';
exec("wget -O '$temp_file' '$download_url'", $output, $return_var);

if ($return_var === 0) {
    exec("gzip -d -c '$temp_file' > '/tmp/mihomo-linux-$current_arch'", $output, $return_var);

    if ($return_var === 0) {
        $install_path = '/usr/bin/mihomo';
        exec("mv '/tmp/mihomo-linux-$current_arch' '$install_path'", $output, $return_var);

        if ($return_var === 0) {
            exec("chmod 0755 '$install_path'", $output, $return_var);

            if ($return_var === 0) {
                echo "更新完成！当前版本: $latest_version";
                writeVersionToFile($latest_version);
            } else {
                echo "设置权限失败！";
            }
        } else {
            echo "移动文件失败！";
        }
    } else {
        echo "解压失败！";
    }
} else {
    echo "下载失败！";
}

if (file_exists($temp_file)) {
    unlink($temp_file);
}
?>
