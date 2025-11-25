<?php
/**
 * Edit Auction Page - Entry Point
 * Separates business logic (controller) from presentation (view)
 */

// Load business logic and prepare data
require_once __DIR__ . '/edit_auction_controller.php';

// Render the view
require_once __DIR__ . '/edit_auction_view.php';