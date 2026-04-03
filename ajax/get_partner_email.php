<?php
/**
 * AJAX Gateway for getting partner email.
 * This file serves as a public endpoint to bypass .htaccess restrictions
 * on the includes/ directory.
 */

// Include the core logic from the protected includes directory
require_once __DIR__ . '/../includes/get_partner_email.php';
