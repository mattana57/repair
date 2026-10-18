#!/bin/bash
set -euo pipefail
if [ "$(id -u)" -ne 0 ]; then echo 'กรุณารัน sudo bash install_media.sh'; exit 1; fi
project_dir="$(cd -- "$(dirname -- "$0")" && pwd)"
php_bin="$(command -v php)"
"$php_bin" -r 'foreach (["mysqli","curl","fileinfo"] as $e) { if (!extension_loaded($e)) { fwrite(STDERR,"Missing PHP extension: $e\n"); exit(1); } }'
if [[ "$project_dir" == *[\'\"\%$'\n'$'\r']* ]]; then echo 'Unsupported project path'; exit 1; fi
command -v apache2ctl >/dev/null
command -v a2enconf >/dev/null
media_dir="$project_dir/uploads"
# ใช้ uploads เดิมโดยตรง ไม่สร้างโฟลเดอร์เก็บไฟล์ย่อย
install -d -o www-data -g www-data -m 0755 "$media_dir"
config_path='/etc/apache2/conf-available/mbs-repair-media.conf'
backup_file="$(mktemp)"
cron_temp="$(mktemp)"
had_config=0; was_enabled=0
if [ -f "$config_path" ]; then cp -p -- "$config_path" "$backup_file"; had_config=1; fi
if [ -e /etc/apache2/conf-enabled/mbs-repair-media.conf ]; then was_enabled=1; fi
trap 'rm -f "$backup_file" "$cron_temp"' EXIT
# ปิด URL เฉพาะชื่อไฟล์ใหม่ (48 ตัวฐานสิบหก) ไม่ปิดรูปเก่าทั้ง uploads
# คงการป้องกันที่เก็บย่อยของชุดก่อน หากมีไฟล์สำรองค้างอยู่
printf '<Directory "%s">\n    <FilesMatch "^[a-f0-9]{48}$">\n        Require all denied\n    </FilesMatch>\n    <Files ".mbs_media_guard_ready">\n        Require all denied\n    </Files>\n</Directory>\n<Directory "%s/repair_media">\n    Require all denied\n</Directory>\n' "$media_dir" "$media_dir" > "$config_path"
a2enconf mbs-repair-media
if ! apache2ctl configtest; then
 if [ "$had_config" -eq 1 ]; then cp -p -- "$backup_file" "$config_path"; else rm -f -- "$config_path"; fi
 if [ "$was_enabled" -eq 0 ]; then a2disconf mbs-repair-media; fi
 echo 'Apache config ไม่ผ่าน คืนค่า config เดิมแล้ว'; exit 1
fi
systemctl reload apache2
# รวมไฟล์จากที่เก็บสองแบบก่อนหน้า โดยไม่เขียนทับข้อมูลที่ต่างกันและไม่ลบต้นฉบับ
for old_dir in "$project_dir/uploads/repair_media" /var/lib/mbs-repair-media; do
 [ -d "$old_dir" ] || continue
 for old_file in "$old_dir"/*; do
  [ -f "$old_file" ] || continue
  media_name="$(basename -- "$old_file")"
  [[ "$media_name" =~ ^[a-f0-9]{48}$ ]] || continue
  if [ -e "$media_dir/$media_name" ]; then
   if ! cmp -s -- "$old_file" "$media_dir/$media_name"; then echo 'พบชื่อไฟล์ซ้ำแต่ข้อมูลต่างกัน หยุดโดยไม่เขียนทับ'; exit 1; fi
  else cp -p -- "$old_file" "$media_dir/$media_name"; fi
  chown www-data:www-data "$media_dir/$media_name"
  chmod 0600 "$media_dir/$media_name"
 done
done
printf 'Apache protection installed\n' > "$media_dir/.mbs_media_guard_ready"
chown root:www-data "$media_dir/.mbs_media_guard_ready"
chmod 0640 "$media_dir/.mbs_media_guard_ready"
touch /var/log/mbs-repair-media.log
chown www-data:www-data /var/log/mbs-repair-media.log
chmod 0640 /var/log/mbs-repair-media.log
printf '* * * * * www-data %s '\''%s/repair_media_worker.php'\'' >> /var/log/mbs-repair-media.log 2>&1\n' "$php_bin" "$project_dir" > "$cron_temp"
install -o root -g root -m 0644 "$cron_temp" /etc/cron.d/mbs-repair-media
systemctl enable --now cron
printf 'ติดตั้งแล้ว รูปใหม่และวิดีโอทั้งหมดเก็บโดยตรงที่: %s\n' "$media_dir"
printf 'โปรดทดสอบว่า URL ไฟล์ใหม่โดยตรงได้ HTTP 403 แต่เปิดผ่านหน้ารายละเอียดใบงานได้ตามสิทธิ์ และรูปเก่ายังเปิดได้ตามเดิม\n'
