import { FaceLandmarker, FilesetResolver } from 'https://cdn.jsdelivr.net/npm/@mediapipe/tasks-vision@0.10.22/+esm';

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
  let mode='2d', landmarker=null, stream=null, raf=0, lastVideoTime=-1, startedAt=0, activeProduct='', lastSent=0;

  const setStatus=(message,state='')=>{if(status){status.textContent=message;status.dataset.state=state;}};
  const device=()=>/iPhone|iPad|iPod/i.test(navigator.userAgent||'')?'ios_app':/Android/i.test(navigator.userAgent||'')?'android_app':matchMedia?.('(max-width:760px)').matches?'mobile_ar':'desktop_web';
  const send=async(force=false)=>{
    if(!startedAt||!activeProduct)return;
    const item=meta[activeProduct]; if(!item?.visit)return;
    const seconds=Math.max(1,Math.round((Date.now()-startedAt)/1000)); if(!force&&seconds-lastSent<5)return; lastSent=seconds;
    try{await fetch(item.visit,{method:'POST',credentials:'same-origin',keepalive:true,headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':studio.dataset.csrf||''},body:JSON.stringify({device:device(),converted:false,session_seconds:seconds})});}catch{}
  };
  const begin=()=>{activeProduct=selector?.value||'';if(!activeProduct||!meta[activeProduct])return;if(!startedAt)startedAt=Date.now();send(true);};

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
      const vision=await FilesetResolver.forVisionTasks('https://cdn.jsdelivr.net/npm/@mediapipe/tasks-vision@0.10.22/wasm');
      landmarker=await FaceLandmarker.createFromOptions(vision,{baseOptions:{modelAssetPath:'https://storage.googleapis.com/mediapipe-models/face_landmarker/face_landmarker/float16/latest/face_landmarker.task',delegate:'GPU'},runningMode,numFaces:1,outputFaceBlendshapes:false,outputFacialTransformationMatrixes:false});
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
    if(raf)cancelAnimationFrame(raf);raf=0;stream?.getTracks().forEach(t=>t.stop());stream=null;if(video){video.srcObject=null;video.hidden=true;}if(cameraStart)cameraStart.hidden=false;if(cameraStop)cameraStop.hidden=true;renderMode();
  };
  const startCamera=async()=>{
    if(!navigator.mediaDevices?.getUserMedia){setStatus('Live camera is not supported in this browser. Upload a photo instead.','warn');return;}
    try{stopCamera();const detector=await ensureVision('VIDEO');stream=await navigator.mediaDevices.getUserMedia({video:{facingMode:'user',width:{ideal:1280},height:{ideal:720}},audio:false});video.srcObject=stream;await video.play();video.hidden=false;if(face)face.hidden=true;if(empty)empty.hidden=true;if(cameraStart)cameraStart.hidden=true;if(cameraStop)cameraStop.hidden=false;begin();setStatus('Camera active — finding face landmarks…');landmarker=detector;liveLoop();}
    catch(e){stopCamera();setStatus('Camera permission was unavailable. Upload a photo or use manual controls.','warn');}
  };

  cameraStart?.addEventListener('click',startCamera);cameraStop?.addEventListener('click',()=>{stopCamera();setStatus('Camera stopped.');});
  upload?.addEventListener('change',()=>{stopCamera();const f=upload.files?.[0];if(!f)return;begin();setStatus('Analyzing face landmarks…');if(face?.complete&&face.naturalWidth)detectPhoto();else face?.addEventListener('load',detectPhoto,{once:true});});
  selector?.addEventListener('change',()=>{send(true);activeProduct=selector.value||'';startedAt=(stream||upload?.files?.[0])?Date.now():0;lastSent=0;syncModel();if(startedAt)send(true);});
  studio.querySelectorAll('[data-try-mode]').forEach(b=>b.addEventListener('click',()=>{if(b.disabled)return;mode=b.dataset.tryMode||'2d';renderMode();send(false);}));
  studio.querySelectorAll('[data-hat-size],[data-hat-x],[data-hat-y],[data-hat-rotate],[data-try-view]').forEach(c=>c.addEventListener('input',()=>send(false)));
  window.addEventListener('pagehide',()=>{send(true);stopCamera();});
  syncModel();setInterval(()=>send(false),10000);
}
