<?php include_once("header.php")?>
<?php require("utilities.php")?>

<div class="container">

<h2 class="my-3">My listings</h2>

<?php

// Show cancel flash messages if present
if (session_status() === PHP_SESSION_NONE) session_start();
if (!empty($_SESSION['delete_success'])) {
  echo '<div class="alert alert-success">' . htmlspecialchars($_SESSION['delete_success']) . '</div>';
  unset($_SESSION['delete_success']);
}
if (!empty($_SESSION['delete_error'])) {
  echo '<div class="alert alert-danger">' . htmlspecialchars($_SESSION['delete_error']) . '</div>';
  unset($_SESSION['delete_error']);
}
?>

<?php
  // This page is for showing a user the auction listings they've made.
  // It will be pretty similar to browse.php, except there is no search bar.
  // This can be started after browse.php is working with a database.
  // Feel free to extract out useful functions from browse.php and put them in
  // the shared "utilities.php" where they can be shared by multiple files.
  
// Insert Statistics Overview Component
function renderStatsOverview($auctions) {
    $activeCount = 0;
    $endedCount = 0;
    $upcomingCount = 0;
    $cancelledCount = 0;
    $totalBids = 0;
    
    foreach ($auctions as $auction) {
        switch ($auction['state']) {
            case 'ongoing': $activeCount++; break;
            case 'finished': 
            case 'expired': $endedCount++; break;
            case 'not-started': $upcomingCount++; break;
            case 'cancelled': $cancelledCount++; break;
        }
        $totalBids += $auction['bidCount'];
    }
    
    $stats = [
        ['label' => 'Active Auctions', 'value' => $activeCount, 'icon' => 'clock', 'color' => 'success'],
        ['label' => 'Ended Auctions', 'value' => $endedCount, 'icon' => 'check-circle', 'color' => 'secondary'],
        ['label' => 'Upcoming', 'value' => $upcomingCount, 'icon' => 'package', 'color' => 'info'],
        ['label' => 'Cancelled', 'value' => $cancelledCount, 'icon' => 'x-circle', 'color' => 'danger'],
        ['label' => 'Total Bids', 'value' => $totalBids, 'icon' => 'trending-up', 'color' => 'primary']
    ];
    
    echo '<div class="row mb-4">';
    foreach ($stats as $stat) {
        echo '
        <div class="col-sm-6 col-lg-3 mb-3">
            <div class="card">
                <div class="card-body d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="card-subtitle text-muted">' . htmlspecialchars($stat['label']) . '</h6>
                        <h3 class="card-title">' . intval($stat['value']) . '</h3>
                    </div>
                    <div class="text-' . $stat['color'] . '">
                        <i data-feather="' . $stat['icon'] . '"></i>
                    </div>
                </div>
            </div>
        </div>';
    }
    echo '</div>';
}

  
  // Check user is logged in and is a seller
  if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    echo '<div class="alert alert-warning">Please <a href="login.php">sign in</a> to view your listings.</div>';
  } elseif (!isset($_SESSION['account_type']) || $_SESSION['account_type'] !== 'seller') {
    echo '<div class="alert alert-info">This page is for sellers only. Your account is not a seller.</div>';
  } else {
    $sellerId = $_SESSION['user_id'];

    require_once 'database.php';

    // Insert filter bar component function - indent one level within the else block
    function renderFilterBar($filterStatus, $sortBy) {
        echo '
        <div class="card mb-4">
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-2">
                        <div class="input-group">
                            <span class="input-group-text"><i data-feather="filter"></i></span>
                            <select class="form-select" id="filterStatus" onchange="applyFilters()">
                                <option value="all" ' . ($filterStatus === 'all' ? 'selected' : '') . '>All Auctions</option>
                                <option value="not-started" ' . ($filterStatus === 'not-started' ? 'selected' : '') . '>Not Started</option>
                                <option value="ongoing" ' . ($filterStatus === 'ongoing' ? 'selected' : '') . '>Ongoing</option>
                                <option value="finished" ' . ($filterStatus === 'finished' ? 'selected' : '') . '>Finished</option>
                                <option value="cancelled" ' . ($filterStatus === 'cancelled' ? 'selected' : '') . '>Cancelled</option>
                                <option value="expired" ' . ($filterStatus === 'expired' ? 'selected' : '') . '>Expired</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-6 mb-2">
                        <div class="input-group">
                            <span class="input-group-text"><i data-feather="arrow-up-down"></i></span>
                            <select class="form-select" id="sortBy" onchange="applyFilters()">
                                <option value="endDate" ' . ($sortBy === 'endDate' ? 'selected' : '') . '>End Date</option>
                                <option value="bidCount" ' . ($sortBy === 'bidCount' ? 'selected' : '') . '>Most Bids</option>
                                <option value="currentBid" ' . ($sortBy === 'currentBid' ? 'selected' : '') . '>Highest Bid</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>
        </div>';
    }
    // 拍卖卡片组件函数 - 与过滤栏同级缩进
    function renderAuctionCard($auction) {
    // 状态映射
    // 直接使用数据库状态
    $status = $auction['state'];

    // 状态颜色映射（基于数据库状态）
    $statusColors = [
        'not-started' => 'info',
        'ongoing' => 'success',
        'finished' => 'secondary',
        'cancelled' => 'danger',
        'expired' => 'warning'
    ];

    $colorClass = $statusColors[$status] ?? 'secondary';
    
    // 计算剩余时间
    $timeRemaining = getTimeRemaining($auction['endDate']);
        
    echo '
    <div class="col-lg-4 col-md-6 mb-4" data-status="' . $status . '" 
          data-end-date="' . strtotime($auction['endDate']) . '"
          data-bid-count="' . $auction['bidCount'] . '"
          data-current-bid="' . $auction['currentBid'] . '">
        <div class="card h-100 auction-card">
            <!-- 添加图片显示 -->
            <div class="card-img-top position-relative" style="height: 200px; overflow: hidden;">
                
                <div class="position-absolute top-0 end-0 m-2">
                    <span class="badge bg-' . $colorClass . '">' . ucfirst(str_replace('-', ' ', $status)) . '</span>
                </div>
            </div>
            <div class="card-body d-flex flex-column">
                <h5 class="card-title">' . htmlspecialchars($auction['itemName']) . '</h5>
                
                <p class="card-text text-muted small">Auction ID: ' . intval($auction['auctionId']) . '</p>
                
                ' . ($status === 'not-started' ? '
                <div class="mb-3">
                    <small class="text-muted">Starting Bid</small>
                    <div class="h5 text-dark">$' . number_format($auction['startingPrice'], 2) . '</div>
                    ' . ($auction['reservePrice'] > 0 ? '<small class="text-muted">Reserve: $' . number_format($auction['reservePrice'], 2) . '</small>' : '') . '
                </div>' : '
                <div class="mb-3">
                    <small class="text-muted">Current Bid</small>
                    <div class="h5 text-dark">$' . number_format($auction['currentBid'], 2) . '</div>
                    ' . ($auction['reservePrice'] > 0 ? '<small class="text-muted">Reserve: $' . number_format($auction['reservePrice'], 2) . '</small>' : '') . '
                </div>') . '
                
                <div class="d-flex justify-content-between text-muted small mb-3">
                    <div class="d-flex align-items-center">
                        <i data-feather="clock" class="me-1"></i>
                        <span>' . htmlspecialchars($timeRemaining) . '</span>
                    </div>
                    <div class="d-flex align-items-center">
                        <i data-feather="trending-up" class="me-1"></i>
                        <span>' . intval($auction['bidCount']) . ' bids</span>
                    </div>
                </div>
                
                <p class="card-text small text-muted mb-3">
                    Start: ' . htmlspecialchars($auction['startDate']) . '<br>
                    End: ' . htmlspecialchars($auction['endDate']) . '
                </p>
                
                <div class="mt-auto d-flex gap-2">
                    <a href="listing.php?auctionId=' . $auction['auctionId'] . '" class="btn btn-outline-primary flex-fill d-flex align-items-center justify-content-center">
                        <i data-feather="eye" class="me-1"></i>View
                    </a>
                    <a href="edit_auction.php?edit=' . $auction['auctionId'] . '" class="btn btn-outline-secondary flex-fill d-flex align-items-center justify-content-center">
                        <i data-feather="edit" class="me-1"></i>Edit
                    </a>';
                    
    if ($auction['state'] !== 'cancelled' && $auction['state'] !== 'finished' && $auction['state'] !== 'expired') {
        echo '
                    <form method="POST" action="cancel_auction.php" class="d-inline flex-fill" onsubmit="return confirm(\'Cancel this auction? This cannot be undone.\');">
                        <input type="hidden" name="auctionId" value="' . $auction['auctionId'] . '">
                        <button type="submit" class="btn btn-outline-danger w-100 d-flex align-items-center justify-content-center">
                            <i data-feather="x" class="me-1"></i>Cancel
                        </button>
                    </form>';
    }
    
    echo '
                </div>
            </div>
        </div>
    </div>';
}

    // 剩余时间计算函数 - 与拍卖卡片函数同级
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

    // 修改后的主逻辑 - 在else块内缩进一级
    $filterStatus = $_GET['filter'] ?? 'all';
    $sortBy = $_GET['sort'] ?? 'endDate';


    // Fetch auctions for this seller, join to Item for the item name
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
        
        //  使用mysqli_stmt_get_result获取结果集
        $result = mysqli_stmt_get_result($stmt);
        
        $allAuctions = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $allAuctions[] = $row;
        }
        
        $hasAny = count($allAuctions) > 0;
        
        // 显示统计概览
        if ($hasAny) {
            renderStatsOverview($allAuctions);
        }
        
        // 显示过滤栏
        renderFilterBar($filterStatus, $sortBy);
        
        // 显示拍卖列表
        echo '<div class="row" id="auctions-container">';
        
        if ($hasAny) {
            // 应用过滤和排序
            $filteredAuctions = array_filter($allAuctions, function($auction) use ($filterStatus) {
                if ($filterStatus === 'all') return true;
                return $auction['state'] === $filterStatus;
            });
            
            // 应用排序
            usort($filteredAuctions, function($a, $b) use ($sortBy) {
                switch($sortBy) {
                    case 'endDate':
                        return strtotime($a['endDate']) - strtotime($b['endDate']);
                    case 'bidCount':
                        return $b['bidCount'] - $a['bidCount'];
                    case 'currentBid':
                        return $b['currentBid'] - $a['currentBid'];
                    default:
                        return 0;
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

<?php include_once("footer.php")?>