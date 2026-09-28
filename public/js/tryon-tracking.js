const studio=document.querySelector('[data-try-studio]');
if(studio){
  let meta={};
  try{meta=JSON.parse(studio.dataset.tryMeta||'{}')||{};}catch{}
  const selector=studio.querySelector('[data-try-product-select]');
  const upload=studio.querySelector('[data-face-upload]');
  const video=studio.querySelector('[data-camera-preview]');
  const face=studio.querySelector('[data-face-preview]');
  const overlay=studio.querySelector('[data-hat-overlay]');
  const empty=studio.querySelector('[data-try-empty]');
  const status=studio.querySelector('[data-vision-status]');
  const cameraStart=studio.querySelector('[data-camera-start]');
  const cameraStop=studio.querySelector('[data-camera-stop]');
  const modelViewer=studio.querySelector('[data-try-model]');
  const modelNote=studio.querySelector('[data-try-model-note]');
  const size=studio.querySelector('[data-hat-size]');
  const x=studio.querySelector('[data-hat-x]');
  const y=studio.querySelector('[data-hat-y]');
  const rotate=studio.querySelector('[data-hat-rotate]');
  const resultActions=studio.querySelector('[data-try-result-actions]');
  const downloadButton=studio.querySelector('[data-try-download]');
  const shareButton=studio.querySelector('[data-try-share]');
  const cartForm=studio.querySelector('[data-try-cart-form]');
  const cartButton=studio.querySelector('[data-try-cart]');
  const resultStatus=studio.querySelector('[data-try-result-status]');
  let mode='2d', landmarker=null, visionModule=null, stream=null, raf=0, lastVideoTime=-1, startedAt=0, activeProduct='', lastSent=0;

  const setStatus=(message,state='')=>{if(status){status.textContent=message;status.dataset.state=state;}};
  const device=()=>/iPhone|iPad|iPod/i.test(navigator.userAgent||'')?'ios_app':/Android/i.test(navigator.userAgent||'')?'android_app':matchMedia?.('(max-width:760px)').matches?'mobile_ar':'desktop_web';
  const send=async(force=false)=>{
    if(!startedAt||!activeProduct)return;
    const item=meta[activeProduct]; if(!item?.visit)return;
    const seconds=Math.max(1,Math.round((Date.now()-startedAt)/1000)); if(!force&&seconds-lastSent<5)return; lastSent=seconds;
    try{await fetch(item.visit,{method:'POST',credentials:'same-origin',keepalive:true,headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':studio.dataset.csrf||''},body:JSON.stringify({device:device(),converted:false,session_seconds:seconds})});}catch{}
  };
  const begin=()=>{activeProduct=selector?.value||'';if(!activeProduct||!meta[activeProduct])return;if(!startedAt)startedAt=Date.now();send(true);};
  const resultReady=()=>!!(selector?.value&&overlay?.src&&overlay.complete&&overlay.naturalWidth>0&&(stream||face?.src)&&!overlay.hidden);
  const syncResultActions=()=>{
    const ready=resultReady();
    if(resultActions)resultActions.hidden=!ready;
    if(cartForm&&selector?.value)cartForm.action='/cart/'+encodeURIComponent(selector.value);
    if(cartButton)cartButton.disabled=!selector?.value;
  };
  const snapshot=async()=>{
    if(!resultReady())throw new Error('Start the camera or upload a selfie and wait for the cap to be positioned.');
    const source=stream?video:face,sw=source.videoWidth||source.naturalWidth,sh=source.videoHeight||source.naturalHeight;
    if(!sw||!sh)throw new Error('Preview is not ready yet.');
    const canvas=document.createElement('canvas');canvas.width=sw;canvas.height=sh;const ctx=canvas.getContext('2d');
    if(stream){ctx.save();ctx.translate(sw,0);ctx.scale(-1,1);ctx.drawImage(source,0,0,sw,sh);ctx.restore();}else ctx.drawImage(source,0,0,sw,sh);
    const box=source.getBoundingClientRect(),ob=overlay.getBoundingClientRect();
    const sx=sw/box.width,sy=sh/box.height,cx=(ob.left-box.left+ob.width/2)*sx,cy=(ob.top-box.top+ob.height/2)*sy;
    const angle=(Number(rotate?.value||0))*Math.PI/180;ctx.save();ctx.translate(cx,cy);ctx.rotate(angle);ctx.drawImage(overlay,-ob.width*sx/2,-ob.height*sy/2,ob.width*sx,ob.height*sy);ctx.restore();
    return await new Promise((resolve,reject)=>canvas.toBlob(b=>b?resolve(b):reject(new Error('Could not create preview image.')),'image/png',.95));
  };
  const previewFile=async()=>new File([await snapshot()],'emerald-rozalia-try-on.png',{type:'image/png'});
  downloadButton?.addEventListener('click',async()=>{try{const file=await previewFile(),url=URL.createObjectURL(file),a=document.createElement('a');a.href=url;a.download=file.name;a.click();setTimeout(()=>URL.revokeObjectURL(url),1000);if(resultStatus)resultStatus.textContent='Preview downloaded.';}catch(e){if(resultStatus)resultStatus.textContent=e.message;}});
  shareButton?.addEventListener('click',async()=>{try{const file=await previewFile();if(navigator.share&&(!navigator.canShare||navigator.canShare({files:[file]}))){await navigator.share({title:'Emerald Rozalia Try-On',text:'My Emerald Rozalia virtual try-on',files:[file]});if(resultStatus)resultStatus.textContent='Preview shared.';}else{const url=URL.createObjectURL(file),a=document.createElement('a');a.href=url;a.download=file.name;a.click();setTimeout(()=>URL.revokeObjectURL(url),1000);if(resultStatus)resultStatus.textContent='Sharing is unavailable in this browser, so the preview was downloaded.';}}catch(e){if(e?.name!=='AbortError'&&resultStatus)resultStatus.textContent=e.message;}});
  cartForm?.addEventListener('submit',()=>send(true));

  const renderMode=()=>{
    const is3d=mode==='3d'&&!!modelViewer?.getAttribute('src');
    if(modelViewer)modelViewer.hidden=!is3d;if(modelNote)modelNote.hidden=!is3d;
    if(video)video.hidden=is3d||!stream;if(face)face.hidden=is3d||!!stream||!face.src;
    if(overlay&&is3d)overlay.hidden=true;if(empty)empty.hidden=is3d||!!stream||!!face?.src;
    studio.querySelectorAll('[data-try-mode]').forEach(b=>b.classList.toggle('is-active',b.dataset.tryMode===(is3d?'3d':'2d')));
  };
  const syncModel=()=>{
    const item=meta[selector?.value||'']||{}, model=item.model||'';
    studio.querySelectorAll('[data-try-mode="3d"]').forEach(b=>{b.disabled=!model;b.title=model?'Interactive 3D model':'No published 3D model for this product';});
    if(modelViewer){if(model){modelViewer.src=model;modelViewer.alt=(selector?.selectedOptions?.[0]?.text||'Product')+' interactive 3D model';}else{modelViewer.removeAttribute('src');if(mode==='3d')mode='2d';}}
    renderMode();
  };

  const ensureVision=async(runningMode='IMAGE')=>{
    setStatus('Loading Vision AI…');
    if(!landmarker){
      visionModule ||= await import('https://cdn.jsdelivr.net/npm/@mediapipe/tasks-vision@0.10.22/+esm');
      const vision=await visionModule.FilesetResolver.forVisionTasks('https://cdn.jsdelivr.net/npm/@mediapipe/tasks-vision@0.10.22/wasm');
      landmarker=await visionModule.FaceLandmarker.createFromOptions(vision,{baseOptions:{modelAssetPath:'https://storage.googleapis.com/mediapipe-models/face_landmarker/face_landmarker/float16/latest/face_landmarker.task',delegate:'GPU'},runningMode,numFaces:1,outputFaceBlendshapes:false,outputFacialTransformationMatrixes:false});
    }else await landmarker.setOptions({runningMode});
    return landmarker;
  };

  const applyLandmarks=(marks)=>{
    if(!marks?.length||!overlay?.src)return false;
    const left=marks[234],right=marks[454],eyeL=marks[33],eyeR=marks[263],forehead=marks[10];
    if(!left||!right||!eyeL||!eyeR||!forehead)return false;
    const faceWidth=Math.hypot(right.x-left.x,right.y-left.y);
    const width=Math.max(30,Math.min(125,faceWidth*100*1.42));
    const centerX=((left.x+right.x)/2)*100;
    const top=Math.max(-10,Math.min(70,(forehead.y*100)-(width*.20)));
    const angle=Math.atan2(eyeR.y-eyeL.y,eyeR.x-eyeL.x)*180/Math.PI;
    overlay.style.width=width+'%';overlay.style.left=(centerX-width/2)+'%';overlay.style.top=top+'%';overlay.style.transform='rotate('+angle+'deg)';overlay.hidden=false;
    if(size)size.value=Math.round(Math.max(30,Math.min(160,width)));if(x)x.value=Math.round(centerX);if(y)y.value=Math.round(Math.max(0,Math.min(100,top)));if(rotate)rotate.value=Math.round(Math.max(-30,Math.min(30,angle)));
    studio.querySelector('[data-hat-size-value]')?.replaceChildren(document.createTextNode(Math.round(width)+'%'));
    studio.querySelector('[data-hat-rotate-value]')?.replaceChildren(document.createTextNode(Math.round(angle)+'°'));
    syncResultActions();
    return true;
  };

  const detectPhoto=async()=>{
    if(!face?.src)return;
    try{const detector=await ensureVision('IMAGE');const result=detector.detect(face);setStatus(applyLandmarks(result.faceLandmarks?.[0])?'Face detected — hat positioned automatically.':'Face not detected. Use a clear front-facing photo or manual fit controls.',result.faceLandmarks?.length?'ok':'warn');}
    catch(e){setStatus('Vision AI unavailable. Manual fit controls remain available.','warn');}
  };

  const liveLoop=()=>{
    if(!stream||!video||video.readyState<2){raf=requestAnimationFrame(liveLoop);return;}
    if(video.currentTime!==lastVideoTime){lastVideoTime=video.currentTime;try{const result=landmarker.detectForVideo(video,performance.now());setStatus(applyLandmarks(result.faceLandmarks?.[0])?'Live head tracking active.':'Looking for your face…',result.faceLandmarks?.length?'ok':'');}catch{}}
    raf=requestAnimationFrame(liveLoop);
  };
  const stopCamera=()=>{
    if(raf)cancelAnimationFrame(raf);raf=0;stream?.getTracks().forEach(t=>t.stop());stream=null;if(video){video.srcObject=null;video.hidden=true;}if(cameraStart)cameraStart.hidden=false;if(cameraStop)cameraStop.hidden=true;renderMode();syncResultActions();
  };
  const startCamera=async()=>{
    if(!navigator.mediaDevices?.getUserMedia){setStatus('Live camera is not supported in this browser. Upload a photo instead.','warn');return;}
    try{
      stopCamera();setStatus('Requesting camera permission…');
      stream=await navigator.mediaDevices.getUserMedia({video:{facingMode:'user',width:{ideal:1280},height:{ideal:720}},audio:false});
      video.srcObject=stream;await video.play();video.hidden=false;if(face)face.hidden=true;if(empty)empty.hidden=true;if(cameraStart)cameraStart.hidden=true;if(cameraStop)cameraStop.hidden=false;begin();syncResultActions();
      try{landmarker=await ensureVision('VIDEO');setStatus('Camera active — finding face landmarks…');liveLoop();}
      catch(e){setStatus('Camera active. Vision AI could not load, so manual fit controls remain available.','warn');}
    }
    catch(e){stopCamera();const denied=e?.name==='NotAllowedError'||e?.name==='SecurityError';setStatus(denied?'Camera permission was blocked. Allow camera access in your browser, then try again.':'Camera could not start. Check that another app is not using it, then try again.','warn');}
  };

  cameraStart?.addEventListener('click',startCamera);cameraStop?.addEventListener('click',()=>{stopCamera();setStatus('Camera stopped.');});
  upload?.addEventListener('change',()=>{stopCamera();const f=upload.files?.[0];if(!f)return;begin();setStatus('Analyzing face landmarks…');if(face?.complete&&face.naturalWidth)detectPhoto();else face?.addEventListener('load',detectPhoto,{once:true});});
  selector?.addEventListener('change',()=>{send(true);activeProduct=selector.value||'';startedAt=(stream||upload?.files?.[0])?Date.now():0;lastSent=0;syncModel();syncResultActions();if(startedAt)send(true);});
  studio.querySelectorAll('[data-try-mode]').forEach(b=>b.addEventListener('click',()=>{if(b.disabled)return;mode=b.dataset.tryMode||'2d';renderMode();send(false);}));
  studio.querySelectorAll('[data-hat-size],[data-hat-x],[data-hat-y],[data-hat-rotate],[data-try-view]').forEach(c=>c.addEventListener('input',()=>send(false)));
  window.addEventListener('pagehide',()=>{send(true);stopCamera();});
  overlay?.addEventListener('load',syncResultActions);overlay?.addEventListener('error',()=>{syncResultActions();setStatus('Selected product Try-On image is unavailable. Please choose another product or ask an administrator to re-upload its overlay.','warn');});
  syncModel();syncResultActions();setInterval(()=>send(false),10000);
}
