<?php
// My Review page for sellers
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/header.php';
require_once __DIR__ . '/database.php';

// Restrict access to sellers only
if (($_SESSION['account_type'] ?? '') !== 'seller') {
	header('Location: browse.php');
	exit;
}

$sellerId = $_SESSION['user_id'] ?? null;
if (!$sellerId) {
	echo '<div class="max-w-5xl mx-auto px-4 py-10"><p class="text-red-600">No seller session found.</p></div>';
	exit;
}

// Fetch reviews joined with Auction -> Item for this seller
$reviews = [];
$stmt = $connection->prepare(
	'SELECT r.auctionId, r.comment, r.rate, r.date, i.name AS item_name
	 FROM Review r
	 JOIN Auction a ON r.auctionId = a.auctionId
	 JOIN Item i ON a.itemId = i.itemId
	 WHERE r.sellerId = ?
	 ORDER BY r.date DESC'
);
if ($stmt) {
	$stmt->bind_param('i', $sellerId);
	if ($stmt->execute()) {
		$res = $stmt->get_result();
		while ($row = $res->fetch_assoc()) {
			$reviews[] = $row;
		}
	}
	$stmt->close();
}

$totalRatings = count($reviews);
$averageRating = 0;
if ($totalRatings > 0) {
	$sum = 0;
	foreach ($reviews as $r) { $sum += (int)$r['rate']; }
	$averageRating = $sum / $totalRatings;
}

$ratingCounts = [];
for ($star = 5; $star >= 1; $star--) {
	$ratingCounts[$star] = 0;
}
foreach ($reviews as $r) {
	$rt = (int)$r['rate'];
	if ($rt >= 1 && $rt <= 5) $ratingCounts[$rt]++;
}

function formatDate($date) { return date('M j, Y', strtotime($date)); }
?>

<!-- Tailwind (loaded after header) -->
<script src="https://cdn.tailwindcss.com"></script>

<main class="max-w-5xl mx-auto px-4 py-10 sm:px-6 lg:px-8">
	<h1 class="text-3xl font-semibold text-gray-900 mb-8">My Reviews</h1>

	<!-- Rating Summary -->
	<div class="bg-white rounded-xl shadow-sm border border-slate-200 p-8 mb-10">
		<div class="flex items-center gap-2 mb-8">
			<span class="text-slate-600 text-2xl">📈</span>
			<h2 class="text-gray-900 text-xl font-semibold">Overall Performance</h2>
		</div>

		<div class="grid grid-cols-1 md:grid-cols-5 gap-8">
			<!-- Average Rating -->
			<div class="md:col-span-2 flex flex-col items-center justify-center p-8 bg-gradient-to-br from-amber-50 to-orange-50 rounded-xl border border-amber-200">
				<div class="text-6xl font-bold text-gray-900 mb-3">
					<?= number_format($averageRating, 1) ?>
				</div>
				<div class="flex items-center gap-1 mb-3">
					<?php for ($i = 1; $i <= 5; $i++): ?>
						<?php if ($i <= round($averageRating)): ?>
							<span class="text-amber-500 text-2xl">★</span>
						<?php else: ?>
							<span class="text-slate-300 text-2xl">★</span>
						<?php endif; ?>
					<?php endfor; ?>
				</div>
				<p class="text-gray-600 text-sm"><?= $totalRatings ?> total reviews</p>
			</div>

			<!-- Distribution -->
			<div class="md:col-span-3 space-y-4">
				<?php foreach ($ratingCounts as $star => $count): $percentage = $totalRatings ? ($count / $totalRatings) * 100 : 0; ?>
					<div class="flex items-center gap-4">
						<div class="flex items-center gap-2 w-20">
							<span class="text-gray-700 text-sm"><?= $star ?></span>
							<span class="text-amber-500">★</span>
						</div>
						<div class="flex-1 h-3 bg-slate-100 rounded-full overflow-hidden">
							<div class="h-full bg-gradient-to-r from-amber-400 to-amber-500" style="width: <?= $percentage ?>%"></div>
						</div>
						<span class="text-gray-600 w-10 text-right text-sm"><?= $count ?></span>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
	</div>

	<!-- Ratings List -->
	<div class="space-y-6">
		<?php if ($totalRatings === 0): ?>
			<div class="bg-white rounded-xl border border-slate-200 p-6">
				<p class="text-gray-600">No reviews yet.</p>
			</div>
		<?php endif; ?>
		<?php foreach ($reviews as $r): ?>
			<div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 hover:shadow-md transition-shadow">
				<div class="flex items-start justify-between mb-4 pb-4 border-b border-slate-100">
					<div class="flex items-start gap-3">
						<div class="bg-slate-100 p-2 rounded-lg text-slate-600 text-xl">📦</div>
						<div>
							<h3 class="text-gray-900 font-medium"><?= htmlspecialchars($r['item_name']) ?></h3>
							<p class="text-gray-500 text-sm mt-1"><?= formatDate($r['date']) ?></p>
						</div>
					</div>
					<div class="flex items-center gap-1 bg-slate-50 px-3 py-2 rounded-lg">
						<?php for ($i = 1; $i <= 5; $i++): ?>
							<?php if ($i <= (int)$r['rate']): ?>
								<span class="text-amber-500 text-xl">★</span>
							<?php else: ?>
								<span class="text-slate-300 text-xl">★</span>
							<?php endif; ?>
						<?php endfor; ?>
					</div>
				</div>
				<p class="text-gray-700 leading-relaxed italic">"<?= htmlspecialchars($r['comment']) ?>"</p>
				<div class="mt-4 flex items-center gap-2">
					<div class="w-8 h-8 bg-slate-200 rounded-full flex items-center justify-center">
						<span class="text-slate-600 text-sm">A</span>
					</div>
					<span class="text-gray-500 text-sm">Anonymous Buyer</span>
				</div>
			</div>
		<?php endforeach; ?>
	</div>
</main>

</body>
</html>
