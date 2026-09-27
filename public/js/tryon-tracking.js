(() => {
  'use strict';
  const studio=document.querySelector('[data-try-studio]');
  if(!studio)return;
  let meta={};
  try{meta=JSON.parse(studio.dataset.tryMeta||'{}')||{};}catch{meta={};}
  const selector=studio.querySelector('[data-try-product-select]');
  const upload=studio.querySelector('[data-face-upload]');
  let startedAt=0;
  let activeProduct='';
  let lastSent=0;
  const modelViewer=studio.querySelector('[data-try-model]');
  const modelNote=studio.querySelector('[data-try-model-note]');
  const facePreview=studio.querySelector('[data-face-preview]');
  const hatOverlay=studio.querySelector('[data-hat-overlay]');
  const empty=studio.querySelector('[data-try-empty]');
  let mode='2d';

  const syncModel=()=>{
    const product=selector?.value||'';
    const item=meta[product]||{};
    const model=item.model||'';
    studio.querySelectorAll('[data-try-mode="3d"]').forEach(button=>{button.disabled=!model;button.title=model?'Interactive 3D model':'No published 3D model for this product';});
    if(modelViewer){
      if(model){modelViewer.src=model;modelViewer.alt=(selector?.selectedOptions?.[0]?.text||'Product')+' interactive 3D model';}
      else{modelViewer.removeAttribute('src');if(mode==='3d')mode='2d';}
    }
    renderMode();
  };
  const renderMode=()=>{
    const is3d=mode==='3d' && !!modelViewer?.getAttribute('src');
    if(modelViewer)modelViewer.hidden=!is3d;
    if(modelNote)modelNote.hidden=!is3d;
    if(facePreview)facePreview.hidden=is3d || !upload?.files?.[0];
    if(hatOverlay && is3d)hatOverlay.hidden=true;
    if(empty)empty.hidden=is3d || !!upload?.files?.[0];
    studio.querySelectorAll('[data-try-mode]').forEach(button=>button.classList.toggle('is-active',button.dataset.tryMode===(is3d?'3d':'2d')));
  };
  studio.querySelectorAll('[data-try-mode]').forEach(button=>button.addEventListener('click',()=>{if(button.disabled)return;mode=button.dataset.tryMode||'2d';renderMode();send(false);}));


  const device=()=>{
    const ua=navigator.userAgent||'';
    if(/iPhone|iPad|iPod/i.test(ua))return 'ios_app';
    if(/Android/i.test(ua))return 'android_app';
    if(matchMedia?.('(max-width: 760px)').matches)return 'mobile_ar';
    return 'desktop_web';
  };

  const send=async(force=false)=>{
    if(!startedAt||!activeProduct)return;
    const item=meta[activeProduct];
    if(!item?.visit)return;
    const seconds=Math.max(1,Math.round((Date.now()-startedAt)/1000));
    if(!force&&seconds-lastSent<5)return;
    lastSent=seconds;
    try{
      await fetch(item.visit,{
        method:'POST',credentials:'same-origin',keepalive:true,
        headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':studio.dataset.csrf||''},
        body:JSON.stringify({device:device(),converted:false,session_seconds:seconds}),
      });
    }catch{}
  };

  const begin=()=>{
    activeProduct=selector?.value||'';
    if(!activeProduct||!meta[activeProduct])return;
    if(!startedAt)startedAt=Date.now();
    send(true);
  };

  upload?.addEventListener('change',()=>{if(upload.files?.[0])begin();});
  selector?.addEventListener('change',()=>{send(true);activeProduct=selector.value||'';startedAt=upload?.files?.[0]?Date.now():0;lastSent=0;syncModel();if(startedAt)send(true);});
  studio.querySelectorAll('[data-hat-size],[data-hat-x],[data-hat-y],[data-hat-rotate],[data-try-view]').forEach(control=>control.addEventListener('input',()=>send(false)));
  studio.querySelectorAll('[data-try-view]').forEach(control=>control.addEventListener('click',()=>send(false)));
  window.addEventListener('pagehide',()=>send(true));
  syncModel();
  setInterval(()=>send(false),10000);
})();
