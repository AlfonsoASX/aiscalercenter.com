<?php
declare(strict_types=1);

require_once __DIR__ . '/lib/app_routing.php';
require_once __DIR__ . '/modules/tools/bootstrap.php';

ensureToolsSessionStarted();
clearToolsServerAuth();
unset($_SESSION['aiscaler_tool_launches'], $_SESSION['aiscaler_tool_browsers']);

header('Location: ' . appLoginUrl(), true, 302);
exit;
