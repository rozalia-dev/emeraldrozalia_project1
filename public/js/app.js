document.querySelector('[data-nav-toggle]')?.addEventListener('click',()=>document.querySelector('[data-nav]')?.classList.toggle('open'));
const adminNavToggle=document.querySelector('[data-admin-nav-toggle]'),adminSidebar=document.querySelector('.admin-sidebar');
if(adminNavToggle&&adminSidebar){
    adminNavToggle.addEventListener('click',()=>{const open=adminSidebar.classList.toggle('open');adminNavToggle.setAttribute('aria-expanded',String(open))});
    adminSidebar.querySelectorAll('a').forEach((link)=>link.addEventListener('click',()=>{adminSidebar.classList.remove('open');adminNavToggle.setAttribute('aria-expanded','false')}));
}

const spin=document.querySelector('[data-spin-viewer]');
if(spin){
    let frames=[];
    try{frames=JSON.parse(spin.dataset.images||'[]')}catch(error){frames=[]}
    const image=spin.querySelector('[data-spin-image]')||spin.querySelector('img');
    const initial=image?.getAttribute('src')||'';
    const status=spin.querySelector('[data-spin-status]');
    let index=0,startX=null;
    const frameUrl=(frame)=>{if(typeof frame==='object'&&frame)frame=frame.url||frame.path||'';if(!frame)return '';return /^(https?:)?\//.test(frame)?frame:'/storage/'+String(frame).replace(/^storage\//,'')};
    const render=()=>{if(!image||!frames.length)return;const url=frameUrl(frames[index]);if(url)image.src=url;if(status)status.textContent=`360° frame ${index+1} of ${frames.length}`};
    const step=(amount)=>{if(!frames.length)return;index=(index+amount+frames.length)%frames.length;render()};
    if(frames.length&&image){image.setAttribute('data-spin-image','');spin.setAttribute('aria-label','360 degree product viewer');spin.tabIndex=0;}
    spin.addEventListener('pointerdown',(event)=>{if(!frames.length)return;startX=event.clientX;spin.setPointerCapture?.(event.pointerId)});
    spin.addEventListener('pointermove',(event)=>{if(startX===null)return;const delta=event.clientX-startX;if(Math.abs(delta)>=14){step(delta>0?-1:1);startX=event.clientX}});
    spin.addEventListener('pointerup',()=>{startX=null});
    spin.addEventListener('pointercancel',()=>{startX=null});
    spin.addEventListener('keydown',(event)=>{if(event.key==='ArrowRight'){event.preventDefault();step(1)}if(event.key==='ArrowLeft'){event.preventDefault();step(-1)}if(event.key==='Home'){event.preventDefault();index=0;render()}if(event.key==='End'){event.preventDefault();index=frames.length-1;render()}});
    document.querySelectorAll('[data-view-mode]').forEach((button)=>button.addEventListener('click',()=>{const mode=button.dataset.viewMode;if(mode==='360'){render();spin.classList.add('is-360')}else if(mode==='gallery'){if(image&&initial)image.src=initial;spin.classList.remove('is-360')}}));
    render();
}

const file=document.querySelector('[data-face-upload]'),face=document.querySelector('[data-face-preview]'),hat=document.querySelector('[data-hat-overlay]');
if(file&&face&&hat){file.addEventListener('change',()=>{const f=file.files?.[0];if(f){face.src=URL.createObjectURL(f);face.hidden=false}});document.querySelector('[data-hat-size]')?.addEventListener('input',e=>hat.style.width=e.target.value+'%');document.querySelector('[data-hat-x]')?.addEventListener('input',e=>hat.style.left=(e.target.value-(parseFloat(hat.style.width)||25)/2)+'%');document.querySelector('[data-hat-y]')?.addEventListener('input',e=>hat.style.top=e.target.value+'%')}

const tryStudio=document.querySelector('[data-try-studio]');
if(tryStudio){
    let assets={};try{assets=JSON.parse(tryStudio.dataset.tryAssets||'{}')}catch(error){assets={}}
    const selector=tryStudio.querySelector('[data-try-product-select]'),selected=tryStudio.querySelector('[data-try-selected]'),overlay=tryStudio.querySelector('[data-hat-overlay]'),missing=tryStudio.querySelector('[data-try-asset-missing]'),empty=tryStudio.querySelector('[data-try-empty]'),canvas=tryStudio.querySelector('[data-try-canvas]'),size=tryStudio.querySelector('[data-hat-size]'),x=tryStudio.querySelector('[data-hat-x]'),y=tryStudio.querySelector('[data-hat-y]'),rotate=tryStudio.querySelector('[data-hat-rotate]'),sizeValue=tryStudio.querySelector('[data-hat-size-value]'),rotateValue=tryStudio.querySelector('[data-hat-rotate-value]');
    const updateAsset=()=>{const list=assets[selector?.value]||[];if(selected)selected.textContent=selector?.selectedOptions?.[0]?.textContent||'No product selected';if(overlay&&list[0]){overlay.src=list[0];overlay.hidden=false;if(missing)missing.hidden=true}else{if(overlay){overlay.removeAttribute('src');overlay.hidden=true}if(missing)missing.hidden=false}};
    const updateFit=()=>{if(!overlay)return;const width=Number(size?.value||85),left=Number(x?.value||50)-width/2;overlay.style.width=width+'%';overlay.style.left=left+'%';overlay.style.top=(y?.value||15)+'%';overlay.style.transform=`rotate(${rotate?.value||0}deg)`;if(sizeValue)sizeValue.textContent=width+'%';if(rotateValue)rotateValue.textContent=(rotate?.value||0)+'°'};
    selector?.addEventListener('change',updateAsset);[size,x,y,rotate].forEach((input)=>input?.addEventListener('input',updateFit));
    tryStudio.querySelector('[data-face-upload]')?.addEventListener('change',()=>{if(empty)empty.hidden=!!face?.src});
    tryStudio.querySelector('[data-try-reset]')?.addEventListener('click',()=>{if(face){face.removeAttribute('src');face.hidden=true}if(empty)empty.hidden=false;if(size)size.value=85;if(x)x.value=50;if(y)y.value=15;if(rotate)rotate.value=0;updateFit()});
    tryStudio.querySelectorAll('[data-try-view]').forEach((button)=>button.addEventListener('click',()=>canvas?.classList.toggle('show-background',button.dataset.tryView==='background')));
    updateAsset();updateFit();
}

const checkout=document.querySelector('[data-checkout]');
if(checkout){
    const shipping=checkout.querySelector('[data-shipping-select]');
    const shippingOutput=checkout.querySelector('[data-checkout-shipping]');
    const totalOutput=checkout.querySelector('[data-checkout-total]');
    const subtotal=Number(checkout.dataset.subtotal||0);
    const format=(value)=>'€'+Number(value||0).toFixed(2);
    const updateCheckoutTotal=()=>{
        const option=shipping?.selectedOptions?.[0];
        let delivery=Number(option?.dataset.price||0);
        const freeOver=option?.dataset.freeOver;
        if(freeOver!==undefined&&freeOver!==''&&subtotal>=Number(freeOver))delivery=0;
        if(shippingOutput)shippingOutput.textContent=format(delivery);
        if(totalOutput)totalOutput.textContent=format(subtotal+delivery);
    };
    shipping?.addEventListener('change',updateCheckoutTotal);
    updateCheckoutTotal();
}
