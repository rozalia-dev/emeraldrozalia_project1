(() => {
  'use strict';
  document.querySelectorAll('[data-spin-widget]').forEach(widget => {
    let config; try { config=JSON.parse(widget.dataset.config); } catch { return; }
    const frames=config.frames||[], settings=config.settings||{}, stage=widget.querySelector('.sv-stage'), img=widget.querySelector('[data-sv-image]'), range=widget.querySelector('[data-sv-range]'), status=widget.querySelector('.sv-status'), play=widget.querySelector('[data-sv-play]');
    if (!frames.length) { status.textContent='No frames available.'; return; }
    let index=0, drag=null, timer=null, zoom=false, visited=false, engaged=false, visible=false;
    const start=performance.now(), mobileBlocked=!settings.mobile&&matchMedia('(pointer: coarse)').matches;
    const reduce=matchMedia('(prefers-reduced-motion: reduce)').matches;
    const metric=(interaction=false)=>{
      if(widget.dataset.preview==='1'||(!interaction&&visited)||(interaction&&engaged))return;
      if(interaction)engaged=true; visited=true;
      fetch(config.metric,{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':widget.dataset.token},body:JSON.stringify({engaged:interaction,load_ms:Math.min(60000,Math.round(performance.now()-start))})}).catch(()=>{});
    };
    const hotspots=()=>{
      const box=widget.querySelector('.sv-hotspots'); box.replaceChildren();
      // Anchor hotspots to the contained image rectangle, not the entire stage.
      const rect=stage.getBoundingClientRect(), ratio=img.naturalWidth/img.naturalHeight;
      const width=Math.min(rect.width,rect.height*ratio), height=width/ratio;
      if(!Number.isFinite(width))return;
      box.style.cssText=`left:${(rect.width-width)/2}px;top:${(rect.height-height)/2}px;width:${width}px;height:${height}px;transform:scale(${zoom?1.7:1})`;
      if(!settings.hotspots)return;
      (config.hotspots||[]).filter(s=>s.frame===index).forEach(s=>{
        const b=document.createElement('button'); b.type='button'; b.className='sv-hotspot'; b.style.left=s.x+'%';b.style.top=s.y+'%';b.textContent='+';b.setAttribute('aria-label',s.label);b.title=s.label;
        b.addEventListener('click',()=>{status.textContent=s.label;metric(true);});box.append(b);
      });
    };
    const render=()=>{img.src=frames[index];range.value=index;widget.querySelector('.sv-frame').textContent=`${index+1} / ${frames.length}`;hotspots();};
    const stop=()=>{clearInterval(timer);timer=null;play.textContent='Play';play.setAttribute('aria-pressed','false');};
    const move=(delta,interaction=true)=>{index=(index+delta+frames.length)%frames.length;render();if(interaction)metric(true);};
    const run=()=>{if(timer||mobileBlocked)return;timer=setInterval(()=>move(1,false),150);play.textContent='Pause';play.setAttribute('aria-pressed','true');};
    img.addEventListener('load',()=>{hotspots();if(visible)metric();});
    img.addEventListener('error',()=>{stop();status.textContent='This frame could not load. Try another frame or contact the store.';});
    widget.querySelector('[data-sv-prev]').addEventListener('click',()=>{stop();move(-1);});
    widget.querySelector('[data-sv-next]').addEventListener('click',()=>{stop();move(1);});
    play.addEventListener('click',()=>{if(timer)stop();else run();metric(true);});
    range.addEventListener('input',()=>{stop();index=Number(range.value);render();metric(true);});
    stage.addEventListener('pointerdown',e=>{if(mobileBlocked||e.target.closest('button'))return;stop();drag=e.clientX;stage.setPointerCapture(e.pointerId);});
    stage.addEventListener('pointermove',e=>{if(drag===null)return;const d=e.clientX-drag;if(Math.abs(d)>=8){move(d>0?-1:1);drag=e.clientX;}});
    ['pointerup','pointercancel','lostpointercapture'].forEach(event=>stage.addEventListener(event,()=>{drag=null;}));
    stage.addEventListener('keydown',e=>{if(!['ArrowLeft','ArrowRight','Home','End'].includes(e.key)||e.target!==stage)return;e.preventDefault();stop();if(e.key==='Home')index=0;else if(e.key==='End')index=frames.length-1;else index=(index+(e.key==='ArrowLeft'?-1:1)+frames.length)%frames.length;render();metric(true);});
    widget.querySelector('[data-sv-zoom]')?.addEventListener('click',e=>{zoom=!zoom;img.style.transform=`scale(${zoom?1.7:1})`;e.currentTarget.setAttribute('aria-pressed',String(zoom));hotspots();metric(true);});
    widget.querySelector('[data-sv-full]')?.addEventListener('click',async()=>{try{if(document.fullscreenElement)await document.exitFullscreen();else await widget.requestFullscreen();}catch{status.textContent='Fullscreen is unavailable in this browser.';}});
    const observer=new IntersectionObserver(entries=>{visible=entries[0].isIntersecting;if(visible){if(img.complete&&img.naturalWidth)metric();if(settings.auto_rotate&&!reduce)run();}else stop();},{threshold:0.3});observer.observe(widget);
    document.addEventListener('visibilitychange',()=>{if(document.hidden)stop();});
    new ResizeObserver(hotspots).observe(stage);
    if(mobileBlocked){widget.querySelectorAll('.sv-controls button,.sv-controls input').forEach(x=>x.disabled=true);stage.tabIndex=-1;status.textContent='Interactive rotation is available on desktop.';}
    if(!settings.lazy_load)frames.forEach(url=>{const image=new Image();image.src=url;});
  });
})();
