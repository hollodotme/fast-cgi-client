<?php declare(strict_types=1);

# Announces its process ID before doing the actual work,
# so a test can interrupt exactly this process while it handles the request.
echo 'PID:', getmypid(), "\n";
flush();

sleep( (int)($_REQUEST['sleep'] ?? 5) );

echo $_REQUEST['test-key'] ?? '';
