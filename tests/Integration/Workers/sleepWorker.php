<?php

declare(strict_types=1);

header('X-Powered-By: PHP/7.1.0');
header('X-Custom: Header');
sleep((int)($_REQUEST['sleep'] ?? 0));
echo $_REQUEST['test-key'], ' - ', ($_REQUEST['sleep'] ?? 0);
