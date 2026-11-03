<?php

declare(strict_types=1);

// Installer dari patch lama dinonaktifkan agar tidak membuat draft di SQLite.
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}
fwrite(STDERR, "Installer SQLite untuk Penawaran Service sudah diganti. Gunakan: php INSTALL_SERVICE_APPHUB.php\n");
exit(1);
