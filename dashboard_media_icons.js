// ใช้จำนวนที่ PHP อ่านจาก repair_attachments ไม่อนุมานชนิดไฟล์จากชื่อผู้แจ้ง
function dashboardMediaIcons(row) {
    let image = Number(row.attachment_image_count || 0) > 0;
    let video = Number(row.attachment_video_count || 0) > 0;
    for (const key of ['image_path', 'image_before']) {
        const value = String(row[key] || '').trim();
        if (!value || value === '-' || value.toLowerCase() === 'null') continue;
        if (/\.(mp4|mov|webm|m4v|avi|3gp)$/i.test(value.split(/[?#]/, 1)[0])) video = true;
        else image = true;
    }
    return (image ? '<i class="fas fa-image text-slate-400 ml-1" title="มีรูปภาพแนบ" role="img" aria-label="มีรูปภาพแนบ"></i>' : '')
        + (video ? '<i class="fas fa-video text-slate-400 ml-1" title="มีวิดีโอแนบ" role="img" aria-label="มีวิดีโอแนบ"></i>' : '');
}
