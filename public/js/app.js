document.querySelector('[data-nav-toggle]')?.addEventListener('click',()=>document.querySelector('[data-nav]')?.classList.toggle('open'));
const adminNavToggle=document.querySelector('[data-admin-nav-toggle]'),adminSidebar=document.querySelector('.admin-sidebar');
if(adminNavToggle&&adminSidebar){
    adminNavToggle.addEventListener('click',()=>{const open=adminSidebar.classList.toggle('open');adminNavToggle.setAttribute('aria-expanded',String(open))});
    adminSidebar.querySelectorAll('a').forEach((link)=>link.addEventListener('click',()=>{adminSidebar.classList.remove('open');adminNavToggle.setAttribute('aria-expanded','false')}));
}
document.querySelectorAll('[data-dashboard-period]').forEach((select)=>select.addEventListener('change',()=>{
    document.querySelectorAll('[data-dashboard-period]').forEach((peer)=>{if(peer!==select)peer.value=select.value});
    document.querySelectorAll('[data-dashboard-period-label]').forEach((label)=>{label.textContent=`(${select.value})`});
}));

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

const bulkUpload=document.querySelector('[data-bulk-upload]');
if(bulkUpload){
    const form=bulkUpload.querySelector('[data-bu-form]'),input=bulkUpload.querySelector('[data-bu-file-input]'),dropzone=bulkUpload.querySelector('[data-bu-dropzone]'),fileName=bulkUpload.querySelector('[data-bu-file-name]'),fileMeta=bulkUpload.querySelector('[data-bu-file-meta]'),fileState=bulkUpload.querySelector('[data-bu-file-state]'),summarySize=bulkUpload.querySelector('[data-bu-summary-size]'),summaryFile=bulkUpload.querySelector('[data-bu-summary-file]'),summaryStatus=bulkUpload.querySelector('[data-bu-summary-status]'),steps=[...bulkUpload.querySelectorAll('[data-bu-step]')],mapRows=[...bulkUpload.querySelectorAll('[data-bu-map-row]')],mapButtons=[...bulkUpload.querySelectorAll('[data-bu-reset-map]')],showUnmapped=bulkUpload.querySelector('[data-bu-show-unmapped]');
    const formatSize=(bytes)=>{if(!bytes)return '—';const units=['B','KB','MB','GB'];let size=bytes,index=0;while(size>=1024&&index<units.length-1){size/=1024;index++}return `${size>=10||index===0?Math.round(size):size.toFixed(1)} ${units[index]}`};
    const setFile=(selected)=>{const chosen=selected?.[0];if(!chosen)return;if(input){try{const transfer=new DataTransfer();transfer.items.add(chosen);input.files=transfer.files}catch(error){}}if(fileName)fileName.textContent=chosen.name;if(fileMeta)fileMeta.textContent=`${formatSize(chosen.size)} · Ready to map columns`;if(fileState){fileState.textContent='File selected';fileState.classList.add('is-success')}if(summaryFile)summaryFile.textContent=chosen.name;if(summarySize)summarySize.textContent=formatSize(chosen.size);if(summaryStatus)summaryStatus.textContent='File selected';const first=steps.find((step)=>step.dataset.buStep==='1'),second=steps.find((step)=>step.dataset.buStep==='2');if(first){first.classList.remove('is-active');first.classList.add('is-complete');const circle=first.querySelector('.bu-step-circle');if(circle&&!circle.querySelector('.ui-icon'))circle.innerHTML='<svg class="ui-icon" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m5 12 4 4L19 6"/></svg>'}if(second){second.classList.add('is-active')}};
    input?.addEventListener('change',()=>setFile(input.files));
    ['dragenter','dragover'].forEach((eventName)=>dropzone?.addEventListener(eventName,(event)=>{event.preventDefault();dropzone.classList.add('is-dragging')}));
    ['dragleave','drop'].forEach((eventName)=>dropzone?.addEventListener(eventName,(event)=>{event.preventDefault();dropzone.classList.remove('is-dragging')}));
    dropzone?.addEventListener('drop',(event)=>setFile(event.dataTransfer?.files));
    const setMapIcon=(icon,mapped)=>{if(icon)icon.innerHTML=mapped?'<path d="m5 12 4 4L19 6"/>':'<path d="M20 11a8 8 0 0 0-14.7-4L3 10m0-4v4h4M4 13a8 8 0 0 0 14.7 4L21 14m0 4v-4h-4/>'};
    const updateMapRow=(row)=>{const select=row.querySelector('[data-bu-map-select]'),status=row.querySelector('[data-bu-map-status]'),mapped=!!select&&select.value!=='Unmapped';if(!select||!status)return;row.dataset.mapped=String(mapped);status.classList.toggle('is-mapped',mapped);status.classList.toggle('is-unmapped',!mapped);status.lastChild.textContent=mapped?' Mapped':' Unmapped';setMapIcon(status.querySelector('.ui-icon'),mapped);if(showUnmapped?.dataset.active==='true')row.classList.toggle('is-filtered-out',mapped)};
    mapRows.forEach((row)=>row.querySelector('[data-bu-map-select]')?.addEventListener('change',()=>updateMapRow(row)));
    const defaultTargets=Object.fromEntries(mapRows.map((row)=>{const select=row.querySelector('[data-bu-map-select]');return [row.querySelector('td')?.textContent.trim(),select?.dataset.defaultTarget||'Unmapped']}));
    const applyMapping=(mode)=>{mapRows.forEach((row)=>{const select=row.querySelector('[data-bu-map-select]');if(!select)return;const source=row.querySelector('td')?.textContent.trim();if(mode==='reset')select.value=select.dataset.defaultTarget||'Unmapped';if(mode==='auto'&&select.value==='Unmapped'){const aliases={'Description':'Short Description','Weight (kg)':'Weight','GTIN':'GTIN / Barcode','Price (EUR)':'Selling Price','Compare Price':'Compare At Price','Status':'Product Status','Stock':'Stock Quantity','SKU':'SKU / Barcode'};const target=aliases[source]||defaultTargets[source];if([...select.options].some((option)=>option.value===target))select.value=target}updateMapRow(row)})};
    bulkUpload.querySelector('[data-bu-auto-map]')?.addEventListener('click',()=>applyMapping('auto'));
    mapButtons.forEach((button)=>button.addEventListener('click',()=>applyMapping('reset')));
    showUnmapped?.addEventListener('click',()=>{const active=showUnmapped.dataset.active==='true';showUnmapped.dataset.active=String(!active);showUnmapped.innerHTML=active?'<svg class="ui-icon" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 5h16l-6.5 7.2V18l-3 1v-6.8z"/></svg> Show Unmapped <b>(2)</b>':'<svg class="ui-icon" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m5 12 4 4L19 6"/></svg> Show All';mapRows.forEach(updateMapRow)});
    bulkUpload.querySelectorAll('[data-bu-save-mapping]').forEach((button)=>button.addEventListener('click',()=>{const mapping=mapRows.map((row)=>row.querySelector('[data-bu-map-select]')?.value||'Unmapped');try{localStorage.setItem('emerald-rozalia-bulk-mapping',JSON.stringify(mapping))}catch(error){}const original=button.innerHTML;button.textContent='Mapping Template Saved';setTimeout(()=>{button.innerHTML=original},1800)}));
    bulkUpload.querySelector('[data-bu-download-sample]')?.addEventListener('click',()=>{const csv='Product Name,SKU,Category,Collection,Price (EUR),Compare Price,Stock,Description,Images,Status,Weight (kg),Tags,GTIN\nEmerald Signature Cap,ERCAP-GRN-001,Caps,Men\'s Collection,29.90,39.90,245,Premium quality cap.,cap1.jpg,Published,0.25,green|premium,8901122334457\n';const link=document.createElement('a');link.href=URL.createObjectURL(new Blob([csv],{type:'text/csv;charset=utf-8'}));link.download='emerald-rozalia-product-upload-template.csv';document.body.appendChild(link);link.click();link.remove();setTimeout(()=>URL.revokeObjectURL(link.href),1000)});
    bulkUpload.querySelector('[data-bu-back]')?.addEventListener('click',()=>bulkUpload.scrollIntoView({behavior:'smooth',block:'start'}));
    form?.addEventListener('submit',()=>{if(fileState&&!input?.files?.length){fileState.textContent='Choose a file before importing';fileState.classList.remove('is-success')}});
}

const mediaManager=document.querySelector('[data-media-manager]');
if(mediaManager){
    const cards=[...mediaManager.querySelectorAll('[data-mm-card]')],tabs=[...mediaManager.querySelectorAll('[data-mm-tab]')],search=mediaManager.querySelector('[data-mm-search]'),empty=mediaManager.querySelector('[data-mm-filter-empty]'),visibleCount=mediaManager.querySelector('[data-mm-visible-count]'),editor=mediaManager.querySelector('[data-mm-editor]'),fileInput=mediaManager.querySelector('[data-mm-file-input]'),dropzone=mediaManager.querySelector('[data-mm-dropzone]'),fileName=mediaManager.querySelector('[data-mm-file-name]');
    let activeTab='all';
    const selectEditor=(trigger)=>{
        if(!editor||!trigger)return;
        const id=trigger.dataset.mediaId;
        const source=trigger.dataset.mediaUpdateUrl?trigger:mediaManager.querySelector(`[data-mm-select-media][data-media-id="${id}"][data-media-update-url]`);
        if(!source)return;
        editor.action=source.dataset.mediaUpdateUrl;
        const type=editor.querySelector('[data-mm-editor-type]'),order=editor.querySelector('[data-mm-editor-order]'),active=editor.querySelector('[data-mm-editor-active]'),alt=editor.querySelector('[data-mm-editor-alt]');
        if(type)type.value=source.dataset.mediaType||'image';
        if(order)order.value=source.dataset.mediaOrder||'0';
        if(active)active.value=source.dataset.mediaActive||'0';
        if(alt)alt.value=source.dataset.mediaAlt||'';
        cards.forEach((card)=>card.classList.toggle('is-selected',card.querySelector(`[data-media-id="${id}"]`)!==null));
        editor.closest('.mm-tool-card')?.scrollIntoView({behavior:'smooth',block:'nearest'});
        alt?.focus();
    };
    const filterCards=()=>{
        const term=(search?.value||'').trim().toLowerCase();
        let visible=0;
        cards.forEach((card)=>{const matchesTab=activeTab==='all'||card.dataset.mmType===activeTab;const matchesSearch=!term||(card.dataset.mmSearch||'').includes(term);const matches=matchesTab&&matchesSearch;card.hidden=!matches;if(matches)visible++});
        if(visibleCount)visibleCount.textContent=`(${visible})`;
        if(empty)empty.hidden=!cards.length||visible>0;
    };
    tabs.forEach((tab)=>tab.addEventListener('click',()=>{activeTab=tab.dataset.mmTab||'all';tabs.forEach((item)=>{const active=item===tab;item.classList.toggle('is-active',active);item.setAttribute('aria-selected',String(active))});filterCards()}));
    search?.addEventListener('input',filterCards);
    mediaManager.querySelectorAll('[data-mm-view]').forEach((button)=>button.addEventListener('click',()=>{const list=button.dataset.mmView==='list';mediaManager.classList.toggle('mm-list-view',list);mediaManager.querySelectorAll('[data-mm-view]').forEach((item)=>item.classList.toggle('is-active',item===button))}));
    mediaManager.querySelectorAll('[data-mm-select-media]').forEach((button)=>button.addEventListener('click',()=>selectEditor(button)));
    mediaManager.querySelector('[data-mm-action="select-first"]')?.addEventListener('click',()=>selectEditor(mediaManager.querySelector('[data-mm-card]:not([hidden]) [data-media-update-url]')));
    mediaManager.querySelector('[data-mm-show-filters]')?.addEventListener('click',()=>mediaManager.querySelector('.mm-filter-bar')?.scrollIntoView({behavior:'smooth',block:'nearest'}));
    const setSelectedFile=(file)=>{if(!file)return;try{const transfer=new DataTransfer();transfer.items.add(file);if(fileInput)fileInput.files=transfer.files}catch(error){}if(fileName)fileName.textContent=file.name;if(dropzone)dropzone.classList.remove('is-dragging')};
    fileInput?.addEventListener('change',()=>setSelectedFile(fileInput.files?.[0]));
    ['dragenter','dragover'].forEach((eventName)=>dropzone?.addEventListener(eventName,(event)=>{event.preventDefault();dropzone.classList.add('is-dragging')}));
    ['dragleave','drop'].forEach((eventName)=>dropzone?.addEventListener(eventName,(event)=>{event.preventDefault();dropzone.classList.remove('is-dragging')}));
    dropzone?.addEventListener('drop',(event)=>setSelectedFile(event.dataTransfer?.files?.[0]));
    filterCards();
}

const imageManager=document.querySelector('[data-image-manager]');
if(imageManager){
    const rows=[...imageManager.querySelectorAll('[data-im-card]')],selectAll=imageManager.querySelector('[data-im-select-all]'),selectionCount=imageManager.querySelector('[data-im-selection-count]'),bulkForm=imageManager.querySelector('[data-im-bulk-form]'),editor=imageManager.querySelector('[data-im-editor]'),previewFrame=imageManager.querySelector('[data-im-preview-frame]'),previewName=imageManager.querySelector('[data-im-preview-name]'),previewMeta=imageManager.querySelector('[data-im-preview-meta]'),uploadDrawer=imageManager.querySelector('[data-im-upload-drawer]'),fileInput=imageManager.querySelector('[data-im-file-input]'),fileName=imageManager.querySelector('[data-im-file-name]');
    const selectedRows=()=>rows.filter((row)=>row.querySelector('[data-im-select]')?.checked);
    const updateSelection=()=>{const selected=selectedRows();if(selectionCount)selectionCount.textContent=selected.length+' selected';if(selectAll)selectAll.checked=!!rows.length&&selected.length===rows.length};
    const selectImage=(row)=>{
        if(!row||!editor)return;
        rows.forEach((item)=>item.classList.toggle('is-selected',item===row));
        editor.action=row.dataset.imUpdateUrl||editor.action;
        const role=editor.querySelector('[data-im-editor-role]'),order=editor.querySelector('[data-im-editor-order]'),active=editor.querySelector('[data-im-editor-active]'),alt=editor.querySelector('[data-im-editor-alt]');
        if(role)role.value=row.dataset.imRole||'additional';
        if(order)order.value=row.dataset.imOrder||'0';
        if(active)active.value=row.dataset.imActive||'0';
        if(alt)alt.value=row.dataset.imAlt||'';
        if(previewName)previewName.textContent=row.dataset.imName||'Selected image';
        if(previewMeta)previewMeta.textContent=(row.dataset.imDimensions||'Resolution pending')+' · '+(row.dataset.imSize||'Size pending');
        if(previewFrame){
            const oldImage=previewFrame.querySelector('[data-im-preview-image]'),empty=previewFrame.querySelector('[data-im-preview-empty]');
            if(row.dataset.imUrl){
                if(oldImage){oldImage.src=row.dataset.imUrl;oldImage.alt=row.dataset.imAlt||row.dataset.imName||'Selected image';oldImage.hidden=false}
                else{const image=document.createElement('img');image.src=row.dataset.imUrl;image.alt=row.dataset.imAlt||row.dataset.imName||'Selected image';image.dataset.imPreviewImage='';previewFrame.replaceChildren(image)}
                if(empty)empty.hidden=true;
            }else if(oldImage){oldImage.hidden=true}
        }
        editor.closest('.im-tool-card')?.scrollIntoView({behavior:'smooth',block:'nearest'});
        alt?.focus();
    };
    rows.forEach((row)=>{row.querySelector('[data-im-select]')?.addEventListener('change',updateSelection);row.querySelectorAll('[data-im-select-image]').forEach((button)=>button.addEventListener('click',()=>selectImage(row)))});
    selectAll?.addEventListener('change',()=>{rows.forEach((row)=>{const checkbox=row.querySelector('[data-im-select]');if(checkbox)checkbox.checked=selectAll.checked});updateSelection()});
    bulkForm?.addEventListener('submit',(event)=>{const selected=selectedRows();if(!selected.length){event.preventDefault();if(selectionCount)selectionCount.textContent='Select at least one image';return}bulkForm.querySelectorAll('[data-im-bulk-id]').forEach((input)=>input.remove());selected.forEach((row)=>{const input=document.createElement('input');input.type='hidden';input.name='media_ids[]';input.value=row.dataset.imId;input.dataset.imBulkId='';bulkForm.appendChild(input)})});
    imageManager.querySelector('[data-im-select-first]')?.addEventListener('click',()=>selectImage(rows[0]));
    const setUploadOpen=(open)=>{if(!uploadDrawer)return;uploadDrawer.hidden=!open;imageManager.querySelectorAll('[data-im-open-upload]').forEach((button)=>button.setAttribute('aria-expanded',String(open)));if(open)uploadDrawer.querySelector('select')?.focus()};
    imageManager.querySelectorAll('[data-im-open-upload]').forEach((button)=>button.addEventListener('click',()=>setUploadOpen(true)));
    imageManager.querySelector('[data-im-close-upload]')?.addEventListener('click',()=>setUploadOpen(false));
    fileInput?.addEventListener('change',()=>{const files=[...fileInput.files||[]];if(fileName)fileName.textContent=files.length?files.length+' file'+(files.length===1?'':'s')+' selected':'No files selected'});
    imageManager.querySelector('[data-im-optimize]')?.addEventListener('click',(event)=>{const button=event.currentTarget;const original=button.textContent;button.textContent='Optimization policy enabled';setTimeout(()=>{button.textContent=original},1800)});
    imageManager.querySelector('[data-im-settings]')?.addEventListener('click',(event)=>{const button=event.currentTarget;const original=button.textContent;button.textContent='Settings policy is active';setTimeout(()=>{button.textContent=original},1800)});
    if(editor&&!rows.length)editor.querySelector('[data-im-editor-alt]')?.setAttribute('disabled','disabled');
    updateSelection();
}

const contactScheduler=document.querySelector('[data-contact-scheduler]');
if(contactScheduler){
    const contactDays=contactScheduler.querySelector('[data-schedule-days]'),contactMonthLabel=contactScheduler.querySelector('[data-schedule-month-label]'),contactPrev=contactScheduler.querySelector('[data-schedule-prev]'),contactNext=contactScheduler.querySelector('[data-schedule-next]'),contactSummary=contactScheduler.querySelector('[data-schedule-summary]'),contactApply=contactScheduler.querySelector('[data-schedule-apply]'),contactForm=document.querySelector('#contact-form'),contactDateInput=contactForm?.querySelector('[data-schedule-date-input]'),contactTimeInput=contactForm?.querySelector('[data-schedule-time-input]'),contactFormSummary=contactForm?.querySelector('[data-schedule-form-summary]');
    const contactMonthValue=contactScheduler.dataset.contactMonth||new Date().toISOString().slice(0,7),contactTodayValue=contactScheduler.dataset.contactToday||new Date().toISOString().slice(0,10);
    const [contactYear,contactMonth]=contactMonthValue.split('-').map(Number),contactStart=new Date(contactYear,contactMonth-1,1,12,0,0);
    let contactCursor=new Date(contactStart),contactSelectedDate='',contactSelectedTime='';
    const contactPad=(value)=>String(value).padStart(2,'0');
    const contactKey=(date)=>`${date.getFullYear()}-${contactPad(date.getMonth()+1)}-${contactPad(date.getDate())}`;
    const contactMonthKey=(date)=>`${date.getFullYear()}-${contactPad(date.getMonth()+1)}`;
    const contactDateLabel=(value)=>new Intl.DateTimeFormat(undefined,{weekday:'short',day:'numeric',month:'short',year:'numeric'}).format(new Date(`${value}T12:00:00`));
    const contactRenderSummary=()=>{
        if(contactSelectedDate&&contactSelectedTime){
            contactSummary.textContent=`Selected: ${contactDateLabel(contactSelectedDate)} at ${contactSelectedTime} (Irish Time).`;
            contactSummary.dataset.state='selected';
        }else{
            contactSummary.textContent='Choose a date and time, then add it to your message.';
            delete contactSummary.dataset.state;
        }
    };
    const contactInvalidateAppliedMeeting=()=>{
        const form=document.querySelector('#contact-form');
        const dateInput=form?.querySelector('[data-schedule-date-input]');
        const timeInput=form?.querySelector('[data-schedule-time-input]');
        const formSummary=form?.querySelector('[data-schedule-form-summary]');
        if(dateInput)dateInput.value='';
        if(timeInput)timeInput.value='';
        if(formSummary){formSummary.hidden=true;formSummary.textContent=''}
    };
    const contactBindDates=()=>contactDays?.querySelectorAll('[data-schedule-date]').forEach((button)=>button.addEventListener('click',()=>{
        contactSelectedDate=button.dataset.scheduleDate||'';
        contactInvalidateAppliedMeeting();
        contactDays.querySelectorAll('[data-schedule-date]').forEach((item)=>{const selected=item===button;item.classList.toggle('is-selected',selected);item.setAttribute('aria-pressed',String(selected))});
        contactRenderSummary();
    }));
    const contactRenderCalendar=()=>{
        if(!contactDays)return;
        const year=contactCursor.getFullYear(),month=contactCursor.getMonth(),daysInMonth=new Date(year,month+1,0).getDate(),leadingDays=(new Date(year,month,1).getDay()+6)%7;
        if(contactMonthLabel)contactMonthLabel.textContent=new Intl.DateTimeFormat(undefined,{month:'long',year:'numeric'}).format(contactCursor);
        if(contactPrev)contactPrev.disabled=contactMonthKey(contactCursor)<=contactMonthValue;
        contactDays.replaceChildren();
        for(let index=0;index<leadingDays;index++){const spacer=document.createElement('span');spacer.className='contact-date-spacer';spacer.setAttribute('aria-hidden','true');contactDays.appendChild(spacer)}
        for(let day=1;day<=daysInMonth;day++){
            const date=new Date(year,month,day,12,0,0),key=contactKey(date),button=document.createElement('button');
            button.type='button';button.className='contact-date-button';button.dataset.scheduleDate=key;button.textContent=String(day);button.setAttribute('aria-label',new Intl.DateTimeFormat(undefined,{weekday:'long',day:'numeric',month:'long',year:'numeric'}).format(date));button.setAttribute('aria-pressed',String(key===contactSelectedDate));
            if(key<contactTodayValue||date.getDay()===0||date.getDay()===6)button.disabled=true;
            if(key===contactSelectedDate)button.classList.add('is-selected');
            contactDays.appendChild(button);
        }
        contactBindDates();
    };
    contactPrev?.addEventListener('click',()=>{if(contactPrev.disabled)return;contactCursor.setMonth(contactCursor.getMonth()-1);contactRenderCalendar()});
    contactNext?.addEventListener('click',()=>{contactCursor.setMonth(contactCursor.getMonth()+1);contactRenderCalendar()});
    contactScheduler.querySelectorAll('[data-schedule-time]').forEach((button)=>button.addEventListener('click',()=>{
        contactSelectedTime=button.dataset.scheduleTime||'';
        contactInvalidateAppliedMeeting();
        contactScheduler.querySelectorAll('[data-schedule-time]').forEach((item)=>item.setAttribute('aria-pressed',String(item===button)));
        contactRenderSummary();
    }));
    contactApply?.addEventListener('click',()=>{
        if(!contactSelectedDate||!contactSelectedTime){
            contactSummary.textContent='Choose both a date and time before scheduling your meeting.';contactSummary.dataset.state='error';return;
        }
        const form=document.querySelector('#contact-form');
        const dateInput=form?.querySelector('[data-schedule-date-input]');
        const timeInput=form?.querySelector('[data-schedule-time-input]');
        const formSummary=form?.querySelector('[data-schedule-form-summary]');
        if(dateInput)dateInput.value=contactSelectedDate;
        if(timeInput)timeInput.value=contactSelectedTime;
        if(formSummary){formSummary.hidden=false;formSummary.textContent=`Meeting requested for ${contactDateLabel(contactSelectedDate)} at ${contactSelectedTime} (Irish Time).`}
        contactSummary.textContent='Meeting time added to your message. Complete the form to send your request.';contactSummary.dataset.state='selected';
        form?.scrollIntoView({behavior:'smooth',block:'start'});form?.querySelector('[name="name"]')?.focus();
    });
    contactRenderCalendar();contactRenderSummary();
}

const pagesScreen=document.querySelector('[data-pages-screen]');
if(pagesScreen){
    const dialog=pagesScreen.querySelector('[data-page-dialog]'),form=pagesScreen.querySelector('[data-page-form]'),blockList=pagesScreen.querySelector('[data-builder-list]'),emptyState=pagesScreen.querySelector('[data-builder-empty]'),blockCount=pagesScreen.querySelector('[data-builder-count]'),sectionsInput=pagesScreen.querySelector('[data-page-sections]'),dialogTitle=pagesScreen.querySelector('[data-page-dialog-title]'),methodInput=pagesScreen.querySelector('[data-page-method]');
    let sections=[];
    const defaultPage=(template='standard')=>({id:null,title:'',slug:'',intro:'',body:'',status:'draft',locale:'en',template,scheduled_for:'',meta_title:'',meta_description:'',meta_keywords:'',show_in_footer:false,indexable:true,login_required:false,visibility:'public',country_restriction:'',devices:['desktop','tablet','mobile'],sections:[]});
    const decodePayload=(encoded)=>{if(!encoded)return null;try{return JSON.parse(atob(encoded))}catch(error){return null}};
    const field=(name)=>form?.querySelector(`[data-page-field="${name}"]`);
    const setField=(name,value)=>{const input=field(name);if(!input)return;if(input.type==='checkbox')input.checked=!!value;else input.value=value??''};
    const getField=(name)=>{const input=field(name);return input?.type==='checkbox'?!!input.checked:(input?.value??'')};
    const renderBlocks=()=>{
        if(!blockList)return;
        blockList.replaceChildren();
        sections.forEach((section,index)=>{
            const row=document.createElement('div');row.className='pages-block';
            const number=document.createElement('span');number.className='pages-block-index';number.textContent=String(index+1);
            const fields=document.createElement('div');fields.className='pages-block-fields';
            const label=document.createElement('input');label.type='text';label.value=section.label||'';label.placeholder='Section label';label.setAttribute('aria-label',`Section ${index+1} label`);label.addEventListener('input',()=>{section.label=label.value;syncSections()});
            const content=document.createElement('textarea');content.value=section.settings?.content||'';content.placeholder='Section content';content.setAttribute('aria-label',`Section ${index+1} content`);content.addEventListener('input',()=>{section.settings={...(section.settings||{}),content:content.value};syncSections()});
            fields.append(label,content);
            const remove=document.createElement('button');remove.type='button';remove.className='pages-block-remove';remove.innerHTML='&times;';remove.title='Remove section';remove.setAttribute('aria-label',`Remove section ${index+1}`);remove.addEventListener('click',()=>{sections.splice(index,1);renderBlocks()});
            row.append(number,fields,remove);blockList.appendChild(row);
        });
        if(emptyState)emptyState.hidden=sections.length>0;
        if(blockCount)blockCount.textContent=`${sections.length} block${sections.length===1?'':'s'}`;
        syncSections();
    };
    const syncSections=()=>{if(sectionsInput)sectionsInput.value=JSON.stringify(sections.map((section,index)=>({type:section.type||'content',label:section.label||'Content block',settings:section.settings||{},visible:section.visible!==false,sort_order:index}))) };
    const openEditor=(payload,isNew=false)=>{
        if(!dialog||!form)return;
        const page={...defaultPage(),...(payload||{})};
        sections=Array.isArray(page.sections)?page.sections.map((section)=>({...section,settings:section.settings||{}})):[];
        form.action=isNew?pagesScreen.dataset.createUrl:`${pagesScreen.dataset.updateBase}/${page.id}`;
        if(methodInput)methodInput.value=isNew?'POST':'PUT';
        if(dialogTitle)dialogTitle.textContent=isNew?'Create New Page':'Edit Page';
        ['title','slug','intro','body','status','locale','template','scheduled_for','meta_title','meta_description','meta_keywords','visibility','country_restriction'].forEach((name)=>setField(name,page[name]));
        ['navigation_visible','show_in_footer','indexable','login_required'].forEach((name)=>setField(name,page[name]));
        form.querySelectorAll('[data-page-device]').forEach((input)=>{input.checked=(page.devices||[]).includes(input.value)});
        renderBlocks();
        dialog.showModal();
        field('title')?.focus();
    };
    const initial=decodePayload(pagesScreen.dataset.pageInit);
    pagesScreen.querySelectorAll('[data-page-edit]').forEach((button)=>button.addEventListener('click',()=>openEditor(decodePayload(button.dataset.pageEdit),false)));
    pagesScreen.querySelectorAll('[data-page-new]').forEach((button)=>button.addEventListener('click',()=>openEditor(defaultPage('standard'),true)));
    pagesScreen.querySelectorAll('[data-page-new-template]').forEach((button)=>button.addEventListener('click',()=>openEditor(defaultPage(button.dataset.pageNewTemplate||'standard'),true)));
    pagesScreen.querySelectorAll('[data-builder-add]').forEach((button)=>button.addEventListener('click',()=>{sections.push({type:button.dataset.builderAdd||'content',label:button.textContent.trim(),settings:{content:''},visible:true});renderBlocks()}));
    pagesScreen.querySelectorAll('[data-page-dialog-close]').forEach((button)=>button.addEventListener('click',()=>dialog?.close()));
    dialog?.addEventListener('click',(event)=>{if(event.target===dialog)dialog.close()});
    form?.addEventListener('submit',()=>syncSections());
    pagesScreen.querySelector('[data-page-filter-toggle]')?.addEventListener('click',()=>{const drawer=pagesScreen.querySelector('[data-page-filter-drawer]');if(drawer)drawer.hidden=!drawer.hidden});
    pagesScreen.querySelector('[data-pages-select-all]')?.addEventListener('change',(event)=>pagesScreen.querySelectorAll('[data-page-select]').forEach((input)=>input.checked=event.currentTarget.checked));
    pagesScreen.querySelectorAll('[data-copy-uuid]').forEach((button)=>button.addEventListener('click',async()=>{try{await navigator.clipboard.writeText(button.dataset.copyUuid||'');const original=button.innerHTML;button.textContent='Copied';setTimeout(()=>{button.innerHTML=original},1300)}catch(error){button.title='UUID: '+button.dataset.copyUuid}}));
    if(initial)renderBlocks();
}

const pageBuilder=document.querySelector('[data-page-builder]');
if(pageBuilder){
    const form=pageBuilder.querySelector('[data-page-form]'),list=pageBuilder.querySelector('[data-builder-list]'),empty=pageBuilder.querySelector('[data-builder-empty]'),sectionInput=pageBuilder.querySelector('[data-page-sections]'),countLabels=[...pageBuilder.querySelectorAll('[data-builder-count]')],titleInput=pageBuilder.querySelector('[data-builder-field="title"]'),slugInput=pageBuilder.querySelector('[data-builder-field="slug"]');
    let sections=[];
    const decode=(value)=>{try{return JSON.parse(atob(value||''))||[]}catch(error){return[]}};
    const labels={hero:'Hero section',content:'Rich content',gallery:'Media gallery',cta:'Call to action',form:'Enquiry form'};
    const sync=()=>{const payload=sections.map((section,index)=>({type:section.type||'content',label:section.label||labels[section.type]||'Content block',settings:section.settings||{},visible:section.visible!==false,sort_order:index}));if(sectionInput)sectionInput.value=JSON.stringify(payload);countLabels.forEach((item)=>{item.textContent=`${payload.length} block${payload.length===1?'':'s'}`})};
    const move=(index,delta)=>{const next=index+delta;if(next<0||next>=sections.length)return;[sections[index],sections[next]]=[sections[next],sections[index]];render()};
    const render=()=>{
        if(!list)return;
        list.replaceChildren();
        sections.forEach((section,index)=>{
            const block=document.createElement('article');block.className='page-builder-block';
            const header=document.createElement('div');header.className='page-builder-block-heading';
            const title=document.createElement('strong');title.textContent=`${String(index+1).padStart(2,'0')} · ${labels[section.type]||'Content block'}`;
            const tools=document.createElement('div');
            [['↑','Move section up',()=>move(index,-1)],['↓','Move section down',()=>move(index,1)]].forEach(([text,label,handler])=>{const button=document.createElement('button');button.type='button';button.className='page-builder-block-tool';button.textContent=text;button.title=label;button.setAttribute('aria-label',label);button.disabled=(text==='↑'&&index===0)||(text==='↓'&&index===sections.length-1);button.addEventListener('click',handler);tools.appendChild(button)});
            const remove=document.createElement('button');remove.type='button';remove.className='page-builder-block-remove';remove.textContent='Remove';remove.addEventListener('click',()=>{sections.splice(index,1);render()});tools.appendChild(remove);header.append(title,tools);
            const fields=document.createElement('div');fields.className='page-builder-block-fields';
            const label=document.createElement('label');label.textContent='Section label';const labelInput=document.createElement('input');labelInput.value=section.label||labels[section.type]||'Content block';labelInput.addEventListener('input',()=>{section.label=labelInput.value;sync()});label.appendChild(labelInput);
            const content=document.createElement('label');content.textContent='Section content';const contentInput=document.createElement('textarea');contentInput.rows=3;contentInput.value=section.settings?.content||'';contentInput.placeholder='Add content for this section...';contentInput.addEventListener('input',()=>{section.settings={...(section.settings||{}),content:contentInput.value};sync()});content.appendChild(contentInput);
            const visible=document.createElement('label');visible.className='page-builder-block-visible';const visibleInput=document.createElement('input');visibleInput.type='checkbox';visibleInput.checked=section.visible!==false;visibleInput.addEventListener('change',()=>{section.visible=visibleInput.checked;sync()});visible.append(visibleInput,document.createTextNode(' Visible'));fields.append(label,content,visible);block.append(header,fields);list.appendChild(block);
        });
        if(empty)empty.hidden=sections.length>0;
        sync();
    };
    pageBuilder.querySelectorAll('[data-builder-add]').forEach((button)=>button.addEventListener('click',()=>{const type=button.dataset.builderAdd||'content';sections.push({type,label:labels[type]||'Content block',settings:{content:''},visible:true});render();list?.lastElementChild?.scrollIntoView({behavior:'smooth',block:'nearest'})}));
    titleInput?.addEventListener('input',()=>{if(!slugInput||slugInput.dataset.edited==='true')return;slugInput.value=titleInput.value.toLowerCase().trim().replace(/[^a-z0-9]+/g,'-').replace(/^-+|-+$/g,'').slice(0,180)});
    slugInput?.addEventListener('input',()=>{slugInput.dataset.edited='true'});
    form?.addEventListener('submit',()=>sync());
    sections=decode(pageBuilder.dataset.builderInitial);
    render();
}
