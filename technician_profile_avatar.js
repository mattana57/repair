(() => {
    'use strict';
    const modal=document.getElementById('avatarPreviewConfirmModal'),image=document.getElementById('avatarCropImage'),layer=document.getElementById('dragTouchLayer'),mask=document.getElementById('cropCircleMask'),input=document.getElementById('profileAvatarInput');
    if(!modal||!image||!layer||!mask||!input)return;
    let x=0,y=0,scale=1,min=1,url='',busy=false,overflow='',loadVersion=0,ignoredCloseEvents=0;
    const pointers=new Map();let drag=null;
    const notify=text=>window.Swal?Swal.fire({icon:'error',text,confirmButtonColor:'#2563eb'}):Promise.resolve(window.alert(text));
    function closeMenus(){document.querySelectorAll('.tech-avatar-actions[open],.tech-mobile-menu[open]').forEach(n=>n.removeAttribute('open'));}
    function apply(){const size=mask.getBoundingClientRect().width;min=Math.max(size/image.naturalWidth,size/image.naturalHeight);scale=Math.max(min,Math.min(scale,min*8));const maxX=Math.max(0,(image.naturalWidth*scale-size)/2),maxY=Math.max(0,(image.naturalHeight*scale-size)/2);x=Math.max(-maxX,Math.min(maxX,x));y=Math.max(-maxY,Math.min(maxY,y));image.style.transform=`translate(calc(-50% + ${x}px),calc(-50% + ${y}px)) scale(${scale})`;}
    function cleanup(){loadVersion++;pointers.clear();drag=null;input.value='';if(url){URL.revokeObjectURL(url);url='';}image.removeAttribute('src');document.body.style.overflow=overflow;}
    window.tpaOpenPicker=()=>{if(busy)return;document.querySelectorAll('.tech-avatar-actions[open]').forEach(n=>n.removeAttribute('open'));input.click();};
    window.tpaPreview=async field=>{
        const file=field.files?.[0];if(!file||busy)return;
        if(!['image/jpeg','image/png','image/webp','image/gif'].includes(file.type)||file.size>5*1024*1024){field.value='';await notify('กรุณาใช้รูป JPG, PNG, WEBP หรือ GIF ขนาดไม่เกิน 5 MB');return;}
        const version=++loadVersion;if(url)URL.revokeObjectURL(url);url=URL.createObjectURL(file);
        image.onload=()=>{
            if(version!==loadVersion)return;
            if(image.naturalWidth>6000||image.naturalHeight>6000||image.naturalWidth*image.naturalHeight>16000000){cleanup();notify('กรุณาใช้รูปขนาดไม่เกิน 6,000 พิกเซลต่อด้าน และไม่เกิน 16 ล้านพิกเซล');return;}
            closeMenus();overflow=document.body.style.overflow;document.body.style.overflow='hidden';modal.showModal();
            image.style.width=image.naturalWidth+'px';image.style.height=image.naturalHeight+'px';image.style.maxWidth='none';image.style.maxHeight='none';
            const size=mask.getBoundingClientRect().width;x=0;y=0;scale=Math.max(size/image.naturalWidth,size/image.naturalHeight);apply();
        };
        image.onerror=()=>{if(version!==loadVersion)return;cleanup();notify('อ่านรูปภาพไม่สำเร็จ กรุณาเลือกไฟล์ใหม่');};image.src=url;
    };
    window.tpaCancel=()=>{if(busy)return;cleanup();if(modal.open){ignoredCloseEvents++;modal.close();}};
    modal.addEventListener('cancel',e=>{if(busy)e.preventDefault();});modal.addEventListener('close',()=>{if(ignoredCloseEvents>0){ignoredCloseEvents--;return;}cleanup();});
    function gesture(){const pts=Array.from(pointers.values());drag=pts.length>1?{distance:Math.hypot(pts[0].x-pts[1].x,pts[0].y-pts[1].y),scale}:{x:pts[0]?.x,y:pts[0]?.y,imageX:x,imageY:y};}
    layer.addEventListener('pointerdown',e=>{if(busy)return;e.preventDefault();pointers.set(e.pointerId,{x:e.clientX,y:e.clientY});layer.setPointerCapture(e.pointerId);gesture();});
    layer.addEventListener('pointermove',e=>{if(!pointers.has(e.pointerId)||busy)return;e.preventDefault();pointers.set(e.pointerId,{x:e.clientX,y:e.clientY});const pts=Array.from(pointers.values());if(pts.length>1){const distance=Math.hypot(pts[0].x-pts[1].x,pts[0].y-pts[1].y);if(drag.distance>0)scale=drag.scale*distance/drag.distance;}else{x=drag.imageX+pts[0].x-drag.x;y=drag.imageY+pts[0].y-drag.y;}apply();});
    for(const event of ['pointerup','pointercancel','lostpointercapture'])layer.addEventListener(event,e=>{pointers.delete(e.pointerId);gesture();});
    layer.addEventListener('wheel',e=>{e.preventDefault();if(busy)return;scale*=Math.exp(-e.deltaY*.0015);apply();},{passive:false});
    window.addEventListener('resize',()=>{if(modal.open&&image.naturalWidth)apply();});
    window.tpaSave=()=>{
        if(busy||!modal.open||!image.naturalWidth)return;
        busy=true;modal.querySelectorAll('button').forEach(b=>b.disabled=true);
        try{
            const canvas=document.createElement('canvas');canvas.width=canvas.height=600;const ctx=canvas.getContext('2d');if(!ctx)throw new Error('ประมวลผลรูปไม่สำเร็จ');
            const ratio=600/mask.getBoundingClientRect().width;ctx.fillStyle='#fff';ctx.fillRect(0,0,600,600);ctx.translate(300+x*ratio,300+y*ratio);ctx.scale(scale*ratio,scale*ratio);ctx.drawImage(image,-image.naturalWidth/2,-image.naturalHeight/2);
            canvas.toBlob(blob=>{
                try{if(!blob)throw new Error('ประมวลผลรูปไม่สำเร็จ');const transfer=new DataTransfer();transfer.items.add(new File([blob],'avatar_cropped.jpg',{type:'image/jpeg'}));input.files=transfer.files;document.getElementById('profileAvatarForm').submit();}
                catch(error){busy=false;modal.querySelectorAll('button').forEach(b=>b.disabled=false);notify(error.message);}
            },'image/jpeg',.9);
        }catch(error){busy=false;modal.querySelectorAll('button').forEach(b=>b.disabled=false);notify(error.message);}
    };
    window.tpaDelete=async()=>{
        if(busy)return;closeMenus();let confirmed;
        if(window.Swal){const result=await Swal.fire({title:'ลบรูปโปรไฟล์?',text:'ต้องการลบรูปโปรไฟล์และเปลี่ยนกลับเป็นรูปเริ่มต้นใช่หรือไม่?',icon:'warning',showCancelButton:true,confirmButtonColor:'#ef4444',confirmButtonText:'ยืนยัน ลบรูป',cancelButtonText:'ยกเลิก'});confirmed=result.isConfirmed;}
        else confirmed=window.confirm('ลบรูปโปรไฟล์และเปลี่ยนกลับเป็นรูปเริ่มต้นหรือไม่?');
        if(confirmed&&!busy){busy=true;document.getElementById('tpaDeleteForm').submit();}
    };
})();
