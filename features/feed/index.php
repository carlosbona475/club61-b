<?php

declare(strict_types=1);

/**
 * Entrada legada (URL inalterada). MVC: routes/web.php → FeedController → FeedService → View.
 */
require_once dirname(__DIR__, 2) . '/bootstrap/web.php';

Club61\Core\Application::runLegacy(__FILE__);
