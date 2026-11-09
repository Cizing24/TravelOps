#!/bin/bash
set -e
docker_process_sql <<'SQL'
CREATE DATABASE IF NOT EXISTS travelops_test CHARACTER SET utf8mb4;
GRANT ALL PRIVILEGES ON travelops_test.* TO 'travelops_demo'@'%';
SQL
