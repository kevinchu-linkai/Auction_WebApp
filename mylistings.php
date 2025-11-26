<?php 
include_once("header.php");
require("utilities.php");

  // This page is for showing a user the auction listings they've made.
  // It will be pretty similar to browse.php, except there is no search bar.
  // This can be started after browse.php is working with a database.
  // Feel free to extract out useful functions from browse.php and put them in
  // the shared "utilities.php" where they can be shared by multiple files.

// 错误报告开启以便调试
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Show cancel flash messages if present
if (session_status() === PHP_SESSION_NONE) session_start();

// Insert Statistics Overview Component
function renderStatsOverview($auctions, $totalMoneyReceived) {
    $activeCount = 0;
    $completedCount = 0;
    $upcomingCount = 0;
    $cancelledCount = 0;
    
    foreach ($auctions as $auction) {
        switch ($auction['state']) {
            case 'ongoing': $activeCount++; break;
            case 'finished': $completedCount++; break;
            case 'not-started': $upcomingCount++; break;
            case 'cancelled': $cancelledCount++; break;
        }
    }
    
    $stats = [
        ['label' => 'Ongoing Auctions', 'value' => $activeCount, 'icon' => 'clock', 'color' => 'green'],
        ['label' => 'Upcoming Auctions', 'value' => $upcomingCount, 'icon' => 'package', 'color' => 'blue'],
        ['label' => 'Cancelled', 'value' => $cancelledCount, 'icon' => 'x-circle', 'color' => 'red'],
        ['label' => 'Completed Auctions', 'value' => $completedCount, 'icon' => 'check-circle', 'color' => 'gray'],
        ['label' => 'Total Money Received', 'value' => '$' . number_format($totalMoneyReceived, 2), 'icon' => 'trending-up', 'color' => 'purple']
    ];
    
    echo '<div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-8">';
    foreach ($stats as $stat) {
        $displayValue = is_numeric($stat['value']) ? intval($stat['value']) : htmlspecialchars($stat['value']);
        echo '
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4">
            <div class="flex justify-between items-center">
                <div>
                    <div class="text-gray-500 text-sm mb-1">' . htmlspecialchars($stat['label']) . '</div>
                    <div class="text-2xl font-bold text-gray-900">' . $displayValue . '</div>
                </div>
                <div class="text-' . $stat['color'] . '-600">
                    <i data-feather="' . $stat['icon'] . '" class="w-6 h-6"></i>
                </div>
            </div>
        </div>';
    }
    echo '</div>';
}

// Insert filter bar component function
function renderFilterBar($filterStatus, $sortBy) {
    echo '
    <div class="bg-white rounded-2xl shadow-lg p-6 border border-gray-100 mb-8">
        <div class="grid grid-cols-1 md:grid-cols-12 gap-4">
            
            <!-- 状态过滤 -->
            <div class="md:col-span-6">
                <div class="relative">
                    <span class="absolute left-4 top-1/2 -translate-y-1/2 text-gray-400">
                        <i data-feather="filter" class="w-5 h-5"></i>
                    </span>
                    <select class="w-full pl-12 pr-4 py-3 border-2 border-gray-200 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-transparent bg-white" 
                            id="filterStatus" onchange="applyFilters()">
                        <option value="all" ' . ($filterStatus === 'all' ? 'selected' : '') . '>All Auctions</option>
                        <option value="not-started" ' . ($filterStatus === 'not-started' ? 'selected' : '') . '>Not Started</option>
                        <option value="ongoing" ' . ($filterStatus === 'ongoing' ? 'selected' : '') . '>Ongoing</option>
                        <option value="finished" ' . ($filterStatus === 'finished' ? 'selected' : '') . '>Finished</option>
                        <option value="cancelled" ' . ($filterStatus === 'cancelled' ? 'selected' : '') . '>Cancelled</option>
                        <option value="expired" ' . ($filterStatus === 'expired' ? 'selected' : '') . '>Expired</option>
                    </select>
                </div>
            </div>

            <!-- 排序选项 -->
            <div class="md:col-span-6">
                <div class="relative">
                    <span class="absolute left-4 top-1/2 -translate-y-1/2 text-gray-400">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4h13M3 8h9m-9 4h6m4 0l4-4m0 0l4 4m-4-4v12" />
                        </svg>
                    </span>
                    <select class="w-full pl-12 pr-4 py-3 border-2 border-gray-200 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-transparent bg-white" 
                            id="sortBy" onchange="applyFilters()">
                        <option value="endDateNewest" ' . ($sortBy === 'endDateNewest' ? 'selected' : '') . '>End Date (Newest)</option>
                        <option value="endDateOldest" ' . ($sortBy === 'endDateOldest' ? 'selected' : '') . '>End Date (Oldest)</option>
                        <option value="bidCount" ' . ($sortBy === 'bidCount' ? 'selected' : '') . '>Most Bids</option>
                    </select>
                </div>
            </div>
        </div>
    </div>';
}

// 拍卖卡片组件函数
function renderAuctionCard($auction) {
    $status = $auction['state'];
    $statusColors = [
        'not-started' => 'bg-blue-100 text-blue-700',
        'ongoing' => 'bg-green-100 text-green-700', 
        'finished' => 'bg-gray-100 text-gray-700',
        'cancelled' => 'bg-red-100 text-red-700',
        'expired' => 'bg-yellow-100 text-yellow-700'
    ];

    $statusClass = $statusColors[$status] ?? 'bg-gray-100 text-gray-700';
    $statusLabel = ucfirst(str_replace('-', ' ', $status));
    $timeRemaining = getTimeRemaining($auction['endDate']);

    echo '
    <div class="bg-white rounded-2xl shadow-lg border border-gray-100 overflow-hidden hover:shadow-xl transition-all" 
            data-status="' . $status . '" data-end-date="' . strtotime($auction['endDate']) . '"
            data-bid-count="' . $auction['bidCount'] . '"
            data-current-bid="' . $auction['currentBid'] . '">
        
        <!-- 卡片头部 -->
        <div class="p-6 border-b border-gray-100">
            <div class="flex justify-between items-start mb-3">
                <h3 class="text-xl font-semibold text-gray-900">' . htmlspecialchars($auction['itemName']) . '</h3>
                <span class="px-3 py-1 rounded-full text-xs font-medium ' . $statusClass . '">' . $statusLabel . '</span>
            </div>
            <p class="text-gray-600 text-sm">Auction ID: ' . intval($auction['auctionId']) . '</p>
        </div>

        <!-- 价格信息 -->
        <div class="p-6">
            ' . ($status === 'not-started' ? '
            <div class="mb-4">
                <div class="text-sm text-gray-500 mb-1">Starting Bid</div>
                <div class="text-2xl font-bold text-gray-900">$' . number_format($auction['startingPrice'], 2) . '</div>
                ' . ($auction['reservePrice'] > 0 ? '<div class="text-xs text-gray-500 mt-1">Reserve: $' . number_format($auction['reservePrice'], 2) . '</div>' : '') . '
            </div>' : '
            <div class="mb-4">
                <div class="text-sm text-gray-500 mb-1">Current Bid</div>
                <div class="text-2xl font-bold text-green-600">$' . number_format($auction['currentBid'], 2) . '</div>
                ' . ($auction['reservePrice'] > 0 ? '<div class="text-xs text-gray-500 mt-1">Reserve: $' . number_format($auction['reservePrice'], 2) . '</div>' : '') . '
            </div>') . '

            <!-- 时间信息 -->
            <div class="flex justify-between items-center text-sm text-gray-600 mb-4">
                <div class="flex items-center gap-1">
                    <i data-feather="clock" class="w-4 h-4"></i>
                    <span>' . htmlspecialchars($timeRemaining) . '</span>
                </div>
                <div class="flex items-center gap-1">
                    <i data-feather="trending-up" class="w-4 h-4"></i>
                    <span>' . intval($auction['bidCount']) . ' bids</span>
                </div>
            </div>

            <!-- 日期信息 -->
            <div class="text-xs text-gray-500 space-y-1 mb-4">
                <div class="flex justify-between">
                    <span>Start:</span>
                    <span>' . htmlspecialchars($auction['startDate']) . '</span>
                </div>
                <div class="flex justify-between">
                    <span>End:</span>
                    <span>' . htmlspecialchars($auction['endDate']) . '</span>
                </div>
            </div>

            <!-- 操作按钮 -->
            <div class="flex gap-3">
                <a href="listing.php?auctionId=' . $auction['auctionId'] . '&from=mylistings" 
                    class="flex-1 bg-gradient-to-r from-purple-500 to-blue-500 text-white text-center py-2.5 rounded-xl hover:shadow-lg transition-all font-medium">
                    View
                </a>
                <a href="edit_auction.php?edit=' . $auction['auctionId'] . '" 
                    class="flex-1 bg-gray-100 text-gray-700 text-center py-2.5 rounded-xl hover:bg-gray-200 transition-all font-medium">
                    Edit
                </a>
                <form method="POST" action="delete_auction.php" class="flex-1" onsubmit="return confirm(\'Permanently delete this auction and item? This cannot be undone!\');">
                    <input type="hidden" name="auctionId" value="' . $auction['auctionId'] . '">
                    <button type="submit" class="w-full bg-red-100 text-red-700 py-2.5 rounded-xl hover:bg-red-200 transition-all font-medium">
                        Delete
                    </button>
                </form>
            </div>
        </div>
    </div>';
}

// 剩余时间计算函数
function getTimeRemaining($endDate) {
    $now = time();
    $end = strtotime($endDate);
    $diff = $end - $now;
    
    if ($diff < 0) return 'Ended';
    
    $days = floor($diff / (60 * 60 * 24));
    $hours = floor(($diff % (60 * 60 * 24)) / (60 * 60));
    
    if ($days > 0) return $days . 'd ' . $hours . 'h';
    return $hours . 'h';
}
?>

<!-- 添加整体样式容器 -->
<div class="min-h-screen bg-gradient-to-br from-purple-50 via-white to-blue-50 p-6">
<div class="max-w-7xl mx-auto">

<!-- 修改标题部分 -->
<div class="mb-8">
  <h1 class="text-gray-900 mb-3 text-3xl font-semibold">My Auction Listings</h1>
</div>

<?php
if (!empty($_SESSION['delete_success'])) {
  echo '<div class="alert alert-success">' . htmlspecialchars($_SESSION['delete_success']) . '</div>';
  unset($_SESSION['delete_success']);
}
if (!empty($_SESSION['delete_error'])) {
  echo '<div class="alert alert-danger">' . htmlspecialchars($_SESSION['delete_error']) . '</div>';
  unset($_SESSION['delete_error']);
}
  

// Check user is logged in and is a seller
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    echo '<div class="alert alert-warning">Please <a href="login.php">sign in</a> to view your listings.</div>';
} elseif (!isset($_SESSION['account_type']) || $_SESSION['account_type'] !== 'seller') {
    echo '<div class="alert alert-info">This page is for sellers only. Your account is not a seller.</div>';
} else {
    $sellerId = $_SESSION['user_id'];
    require_once 'database.php';

    $filterStatus = $_GET['filter'] ?? 'all';
    $sortBy = $_GET['sort'] ?? 'endDate';

    // Fetch auctions for this seller
    $sql = "SELECT a.auctionId, a.itemId, i.name AS itemName, i.photo, a.startDate, a.endDate, 
                  a.startingPrice, a.reservePrice, a.state,
                  COUNT(b.bidId) as bidCount,
                  COALESCE(MAX(b.bidAmount), a.startingPrice) as currentBid
            FROM Auction a
            JOIN Item i ON a.itemId = i.itemId
            LEFT JOIN Bid b ON a.auctionId = b.auctionId
            WHERE a.sellerId = ?
            GROUP BY a.auctionId
            ORDER BY a.startDate DESC";

    $stmt = mysqli_prepare($connection, $sql);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'i', $sellerId);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        $allAuctions = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $allAuctions[] = $row;
        }
        
        $hasAny = count($allAuctions) > 0;
        
        // Calculate total money received (sum of highest bid per auction)
        $totalMoneyReceived = 0;
        if ($hasAny) {
            $moneyStmt = $connection->prepare(
                "SELECT COALESCE(SUM(maxBid), 0) as totalMoney 
                 FROM (
                     SELECT a.auctionId, MAX(b.bidAmount) as maxBid
                     FROM Auction a
                     LEFT JOIN Bid b ON a.auctionId = b.auctionId
                     WHERE a.sellerId = ? AND a.state = 'finished'
                     GROUP BY a.auctionId
                 ) AS auction_max_bids"
            );
            if ($moneyStmt) {
                mysqli_stmt_bind_param($moneyStmt, 'i', $sellerId);
                mysqli_stmt_execute($moneyStmt);
                mysqli_stmt_bind_result($moneyStmt, $totalMoneyReceived);
                mysqli_stmt_fetch($moneyStmt);
                mysqli_stmt_close($moneyStmt);
            }
            renderStatsOverview($allAuctions, $totalMoneyReceived);
        }
        
        renderFilterBar($filterStatus, $sortBy);
        
        echo '<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6" id="auctions-container">';
        
        if ($hasAny) {
            // 过滤和排序逻辑
            $filteredAuctions = array_filter($allAuctions, function($auction) use ($filterStatus) {
                if ($filterStatus === 'all') return true;
                return $auction['state'] === $filterStatus;
            });
            
            usort($filteredAuctions, function($a, $b) use ($sortBy) {
                switch($sortBy) {
                    case 'endDateNewest':
                        return strtotime($b['endDate']) - strtotime($a['endDate']);
                    case 'endDateOldest':
                        return strtotime($a['endDate']) - strtotime($b['endDate']);
                    case 'bidCount':
                        return $b['bidCount'] - $a['bidCount'];
                    default:
                        return strtotime($b['endDate']) - strtotime($a['endDate']);
                }
            });
            
            foreach ($filteredAuctions as $auction) {
                renderAuctionCard($auction);
            }
        } else {
            echo '<div class="col-12"><div class="alert alert-info">You have not created any listings yet.</div></div>';
        }
        
        echo '</div>';
        mysqli_stmt_close($stmt);
    } else {
        echo '<div class="alert alert-danger">Database error (prepare failed).</div>';
    }
}
?>

<script src="https://cdn.tailwindcss.com"></script>
<script src="https://unpkg.com/feather-icons"></script>
<script>
// 初始化Feather图标
document.addEventListener('DOMContentLoaded', function() {
    feather.replace();
});

function applyFilters() {
    const filterStatus = document.getElementById('filterStatus').value;
    const sortBy = document.getElementById('sortBy').value;
    
    // 重定向到当前页面带上过滤参数
    const url = new URL(window.location.href);
    url.searchParams.set('filter', filterStatus);
    url.searchParams.set('sort', sortBy);
    window.location.href = url.toString();
}
</script>

</div> <!-- 关闭max-w-7xl容器 -->
</div> <!-- 关闭背景渐变容器 -->

<?php include_once("footer.php")?>