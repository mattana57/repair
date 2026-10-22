#!/bin/bash
set -eu
if [ "$(id -u)" -ne 0 ]; then echo 'กรุณารันด้วย sudo bash install_job_sync.sh'; exit 1; fi
job_project_dir="$(cd -- "$(dirname -- "$0")" && pwd)"
case "$job_project_dir" in *"'"*|*"%"*) echo 'ชื่อโฟลเดอร์ไม่รองรับ'; exit 1;; esac
job_php_path="$(command -v php)"
test -f "$job_project_dir/line_job_worker.php"
"$job_php_path" -r 'foreach (["mysqli","curl","mbstring"] as $e) { if (!extension_loaded($e)) {fwrite(STDERR,"Missing PHP extension: ".$e.PHP_EOL); exit(1);} }'
cat > /etc/cron.d/mbs-line-job-sync <<EOF
SHELL=/bin/sh
PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin
* * * * * www-data cd '$job_project_dir' && '$job_php_path' '$job_project_dir/line_job_worker.php' >/dev/null 2>&1
EOF
chmod 644 /etc/cron.d/mbs-line-job-sync
systemctl enable --now cron
echo 'ตั้งส่งซ้ำข้อความที่ค้างเรียบร้อยแล้ว ไม่เปลี่ยนงาน cron เดิม'
