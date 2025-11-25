<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Premium Auctions</title>

    <link
        rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css"
    >
</head>
<body class="bg-gray-50">

<header class="bg-white border-b border-gray-200">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4">
        <h1 class="text-xl font-semibold">Premium Auctions</h1>
    </div>
    </header>

<main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="mb-6">
        <a href="mylistings.php" class="inline-flex items-center text-sm text-gray-600 hover:text-gray-800">
            &larr;&nbsp;Back to My listings
        </a>
    </div>
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
        <!-- Left Column - Image Gallery -->
        <div>
            <div class="bg-white rounded-lg overflow-hidden aspect-square border border-gray-200">
                <img
                    src="<?= htmlspecialchars($photoUrl) ?>"
                    alt="<?= htmlspecialchars($title) ?>"
                    class="w-full h-full object-cover"
                >
            </div>
        </div>

        <!-- Right Column - Auction Details -->
        <div class="space-y-6">
            <div>
                <h1 class="text-2xl font-semibold"><?= htmlspecialchars($title) ?></h1>
            </div>

            <!-- AuctionTimer (PHP-only countdown) -->
            <?php if ($displayStatus === 'finished'): ?>
                <div class="bg-green-50 border border-green-200 rounded-lg p-4">
                    <div class="flex items-center gap-2">
                        <span class="text-green-900 font-semibold">Auction Ended</span>
                    </div>
                    <p class="text-green-700 mt-2">This auction has successfully concluded.</p>
                </div>
            <?php elseif ($displayStatus === 'cancelled'): ?>
                <div class="bg-red-50 border border-red-200 rounded-lg p-4">
                    <div class="flex items-center gap-2">
                        <span class="text-red-900 font-semibold">Auction Cancelled</span>
                    </div>
                    <p class="text-red-700 mt-2">This auction has been cancelled by the seller.</p>
                    <?php if (isset($_SESSION['account_type']) && $_SESSION['account_type'] === 'seller'): ?>
                        <form method="post" class="mt-4 inline-block">
                            <input type="hidden" name="relist" value="1">
                            <button type="submit" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded shadow">
                                Relist Auction
                            </button>
                        </form>
                    <?php endif; ?>
                    <?php if (!empty($error)): ?>
                        <p class="text-sm text-red-600 mt-3"><?= htmlspecialchars($error) ?></p>
                    <?php endif; ?>
                </div>
            <?php elseif ($displayStatus === 'expired'): ?>
                <div class="bg-gray-100 border border-gray-300 rounded-lg p-4">
                    <div class="flex items-center gap-2">
                        <span class="text-gray-900 font-semibold">Auction Expired</span>
                    </div>
                    <p class="text-gray-700 mt-2">This auction has expired without meeting the reserve price.</p>
                </div>
            <?php elseif ($displayStatus === 'not-started'): ?>
                <div id="auction-countdown" data-ts="<?= $auctionStartTime ?>" data-end="<?= $auctionEndTime ?>" data-phase="start" class="bg-blue-50 border rounded-lg p-4">
                    <div class="flex items-center gap-2 mb-3">
                        <span class="cd-label text-blue-900 font-semibold">Auction Starting In</span>
                    </div>
                    <div class="grid grid-cols-4 gap-2">
                        <div class="bg-white rounded-lg p-3 text-center border border-blue-200">
                            <div class="text-2xl text-blue-900 cd-days"><?= (int) floor(max(0, ($auctionStartTime - time()))/86400) ?></div>
                            <div class="text-blue-700">Days</div>
                        </div>
                        <div class="bg-white rounded-lg p-3 text-center border border-blue-200">
                            <div class="text-2xl text-blue-900 cd-hours"><?= (int) floor((max(0, ($auctionStartTime - time()))%86400)/3600) ?></div>
                            <div class="text-blue-700">Hours</div>
                        </div>
                        <div class="bg-white rounded-lg p-3 text-center border border-blue-200">
                            <div class="text-2xl text-blue-900 cd-mins"><?= (int) floor((max(0, ($auctionStartTime - time()))%3600)/60) ?></div>
                            <div class="text-blue-700">Mins</div>
                        </div>
                        <div class="bg-white rounded-lg p-3 text-center border border-blue-200">
                            <div class="text-2xl text-blue-900 cd-secs"><?= (int) (max(0, ($auctionStartTime - time()))%60) ?></div>
                            <div class="text-blue-700">Secs</div>
                        </div>
                    </div>
                </div>
                <?php if (isset($_SESSION['account_type']) && $_SESSION['account_type'] === 'seller' && isset($_SESSION['user_id']) && $_SESSION['user_id'] == $sellerId): ?>
                    <form method="post" class="inline-block" onsubmit="return confirm('Are you sure you want to cancel this auction? This cannot be undone.');">
                        <input type="hidden" name="cancel_auction" value="1">
                        <button type="submit" class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white text-sm font-medium rounded shadow">
                            Cancel Auction
                        </button>
                    </form>
                    <?php if (!empty($error)): ?>
                        <p class="text-sm text-red-600 mt-3"><?= htmlspecialchars($error) ?></p>
                    <?php endif; ?>
                <?php endif; ?>
            <?php elseif ($displayStatus === 'ongoing'): ?>
                <div id="auction-countdown" data-ts="<?= $auctionEndTime ?>" data-end="<?= $auctionEndTime ?>" data-phase="end" class="bg-amber-50 border rounded-lg p-4">
                    <div class="flex items-center gap-2 mb-3">
                        <span class="text-amber-900 font-semibold">Auction Ending In</span>
                    </div>
                    <div class="grid grid-cols-4 gap-2">
                        <div class="bg-white rounded-lg p-3 text-center border border-amber-200">
                            <div class="text-2xl text-amber-900 cd-days"><?= (int) floor(max(0, ($auctionEndTime - time()))/86400) ?></div>
                            <div class="text-amber-700">Days</div>
                        </div>
                        <div class="bg-white rounded-lg p-3 text-center border border-amber-200">
                            <div class="text-2xl text-amber-900 cd-hours"><?= (int) floor((max(0, ($auctionEndTime - time()))%86400)/3600) ?></div>
                            <div class="text-amber-700">Hours</div>
                        </div>
                        <div class="bg-white rounded-lg p-3 text-center border border-amber-200">
                            <div class="text-2xl text-amber-900 cd-mins"><?= (int) floor((max(0, ($auctionEndTime - time()))%3600)/60) ?></div>
                            <div class="text-amber-700">Mins</div>
                        </div>
                        <div class="bg-white rounded-lg p-3 text-center border border-amber-200">
                            <div class="text-2xl text-amber-900 cd-secs"><?= (int) (max(0, ($auctionEndTime - time()))%60) ?></div>
                            <div class="text-amber-700">Secs</div>
                        </div>
                    </div>
                </div>
                <?php if (isset($_SESSION['account_type']) && $_SESSION['account_type'] === 'seller' && isset($_SESSION['user_id']) && $_SESSION['user_id'] == $sellerId): ?>
                    <form method="post" class="inline-block mt-4" onsubmit="return confirm('Are you sure you want to cancel this auction? This cannot be undone.');">
                        <input type="hidden" name="cancel_auction" value="1">
                        <button type="submit" class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white text-sm font-medium rounded shadow">
                            Cancel Auction
                        </button>
                    </form>
                    <?php if (!empty($error)): ?>
                        <p class="text-sm text-red-600 mt-3"><?= htmlspecialchars($error) ?></p>
                    <?php endif; ?>
                <?php endif; ?>
            <?php endif; ?>

            <!-- Current Bid summary (all states) -->
            <div class="bg-white rounded-lg border border-gray-200 p-6 space-y-4">

                <div class="flex items-baseline justify-between">
                    <span class="text-gray-600">Starting Price</span>

                    <div class="flex items-baseline gap-2">
                        <span class="text-2xl text-gray-900">
                            $<?= number_format($startingPrice, 2) ?>
                        </span>
                        <span class="text-gray-500">USD</span>
                    </div>
                </div>

                <?php if (count($bidHistory) > 0): ?>
                    <div class="flex items-baseline justify-between pt-4 border-t border-gray-200">
                        <span class="text-gray-600">Current Bid</span>

                        <div class="flex items-baseline gap-2">
                            <span class="text-3xl">
                                $<?= number_format($currentBid, 2) ?>
                            </span>
                            <span class="text-gray-500">USD</span>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (!empty($reservePrice) && $reservePrice > 0): ?>
                    <div class="flex items-baseline justify-between">
                        <span class="text-gray-600">Reserve Price</span>

                        <div class="flex items-baseline gap-2">
                            <span class="text-2xl text-gray-900">$<?= number_format($reservePrice, 2) ?></span>
                            <span class="text-gray-500">USD</span>
                        </div>
                    </div>
                <?php endif; ?>

                <p class="text-gray-500">
                    <?php if (count($bidHistory) === 0): ?>
                        No bids yet
                    <?php else: ?>
                        <?= count($bidHistory) ?> bids
                    <?php endif; ?>
                </p>

            </div>

            <!-- ItemDetails -->
            <div class="bg-white rounded-lg border border-gray-200 p-6">
                <div class="flex items-center gap-2 mb-6">
                    <h3 class="text-lg font-semibold text-gray-800">Item Details</h3>
                </div>
                <div class="space-y-6">
                    <div>
                        <h4 class="text-gray-700 mb-2">Condition</h4>
                        <div class="inline-block bg-green-50 text-green-700 px-4 py-2 rounded-lg border border-green-200">
                            <?= htmlspecialchars($item_condition ?: 'Unknown') ?>
                        </div>
                    </div>
                    <div>
                        <h4 class="text-gray-700 mb-3">Description</h4>
                        <p class="text-gray-600 leading-relaxed">
                            <?= nl2br(htmlspecialchars($description)) ?>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bid History -->
    <?php if (in_array($displayStatus, ['ongoing', 'finished', 'expired'], true)): ?>
        <div class="mt-12">
            <div class="bg-white rounded-lg border border-gray-200">
                <div class="p-6 border-b border-gray-200">
                    <div class="flex items-center gap-2">
                        <h2 class="text-lg font-semibold">Bid History</h2>
                    </div>
                </div>
                <div class="divide-y divide-gray-200">
                    <?php foreach ($bidHistory as $index => $bid): ?>
                        <div class="p-4 flex items-center justify-between <?= $index === 0 ? 'bg-blue-50' : '' ?>">
                            <div class="flex items-center gap-4">
                                <div class="w-10 h-10 rounded-full flex items-center justify-center
                                    <?= $index === 0 ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-700' ?>">
                                    <?= strtoupper(substr($bid['bidder'], 0, 1)) ?>
                                </div>
                                <div>
                                    <div class="flex items-center gap-2">
                                        <span class="<?= $index === 0 ? 'text-blue-900 font-semibold' : 'text-gray-900' ?>">
                                            <?= htmlspecialchars($bid['bidder']) ?>
                                        </span>
                                        <?php if ($index === 0): ?>
                                            <span class="bg-blue-600 text-white px-2 py-0.5 rounded text-xs">
                                                Leading
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <p class="text-gray-500 text-sm"><?= timeAgoSimple($bid['timestamp']) ?></p>
                                </div>
                            </div>
                            <div class="<?= $index === 0 ? 'text-blue-900 font-semibold' : 'text-gray-900' ?>">
                                $<?= number_format($bid['amount'], 2) ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>
</main>

<script>
// Live countdown updater — run after DOM ready and allow client-side switch from start -> end
document.addEventListener('DOMContentLoaded', function(){
    var el = document.getElementById('auction-countdown');
    if (!el) return;

    var phase = el.getAttribute('data-phase') || 'start';

    var startTs = parseInt(el.getAttribute('data-ts') || el.getAttribute('data-start') || '', 10);
    var endTs   = parseInt(el.getAttribute('data-end') || '', 10);
    if (isNaN(startTs)) startTs = 0; else startTs *= 1000;
    if (isNaN(endTs)) endTs = startTs; else endTs *= 1000;

    var targetTs = (phase === 'start') ? startTs : endTs;

    // Initialize label and classes according to the current phase so text is correct on load
    var label = el.querySelector('.cd-label') || el.querySelector('span.font-semibold');
    if (label) {
        if (phase === 'start') {
            label.textContent = 'Auction Starting In';
            el.classList.add('bg-blue-50');
            el.classList.remove('bg-amber-50');
        } else {
            label.textContent = 'Auction Ending In';
            el.classList.add('bg-amber-50');
            el.classList.remove('bg-blue-50');
        }
    }

    function switchToEnd() {
        phase = 'end';
        targetTs = endTs;
        el.setAttribute('data-phase', 'end');

        var label = el.querySelector('.cd-label') || el.querySelector('span.font-semibold');
        if (label) label.textContent = 'Auction Ending In';

        el.classList.remove('bg-blue-50');
        el.classList.add('bg-amber-50');
    }

    function zeroOut() {
        var nodes = ['.cd-days','.cd-hours','.cd-mins','.cd-secs'];
        nodes.forEach(function(sel){ var n = el.querySelector(sel); if (n) n.textContent = 0; });
    }

    function update(){
        var now = Date.now();

        // If we're counting down to start and start time passed, switch to end countdown
        if (phase === 'start' && startTs > 0 && now >= startTs) {
            if (endTs > now) {
                switchToEnd();
            } else {
                // auction already ended
                zeroOut();
                return;
            }
        }

        var diff = Math.max(0, Math.floor((targetTs - now)/1000));
        var days = Math.floor(diff/86400); diff %= 86400;
        var hours = Math.floor(diff/3600); diff %= 3600;
        var mins = Math.floor(diff/60); var secs = diff % 60;

        var d = el.querySelector('.cd-days'); if (d) d.textContent = days;
        var h = el.querySelector('.cd-hours'); if (h) h.textContent = hours;
        var m = el.querySelector('.cd-mins'); if (m) m.textContent = mins;
        var s = el.querySelector('.cd-secs'); if (s) s.textContent = secs;
    }

    update();
    setInterval(update, 1000);
});
</script>

</body>
</html>
