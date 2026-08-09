-- Runs once, on the first boot of an empty data volume.
-- The application database itself is created by MYSQL_DATABASE; this adds the
-- dedicated schema the test suite refreshes on every run.
CREATE DATABASE IF NOT EXISTS `moneyflex_testing`
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

GRANT ALL PRIVILEGES ON `moneyflex_testing`.* TO 'root'@'%';
FLUSH PRIVILEGES;
