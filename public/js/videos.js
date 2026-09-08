(() => {
    'use strict';
    const root = document.querySelector('[data-video-dashboard]');
    if (!root) return;
    const $ = (selector, parent = root) => parent.querySelector(selector);
    const $$ = (selector, parent = root) => [...parent.querySelectorAll(selector)];
    const records = new Map(JSON.parse($('#vd-video-data').textContent).map(video => [String(video.id), video]));
    const dialog = $('#vd-dialog'), form = $('#vd-form'), settings = $('#vd-settings-form'), infoDialog = $('#vd-info-dialog');
    const filesInput = $('#vd-files');
    let selected = null, editing = null, busy = false, queuedFiles = [];
    const notice = (message, error = false) => {
        const node = $('#vd-notice'); node.hidden = false; node.textContent = message; node.classList.toggle('is-error',error);
    };
    const formError = message => { const node = $('#vd-form-errors'); node.textContent = message; node.hidden = !message; if (message) node.scrollIntoView({block:'nearest'}); };
    const element = (tag, text, className) => {
        const node = document.createElement(tag);
        if (text !== undefined) node.textContent = text;
        if (className) node.className = className;
        return node;
    };
    const duration = seconds => {
        const total = Math.round(Number(seconds) || 0);
        return Math.floor(total/60).toString().padStart(2,'0')+':'+(total%60).toString().padStart(2,'0');
    };
    const localDate = value => {
        if (!value) return '';
        const date = new Date(value);
        if (!Number.isFinite(date.getTime())) return '';
        return new Date(date.getTime()-date.getTimezoneOffset()*60000).toISOString().slice(0,16);
    };
    const setFields = (target, data) => {
        for (const [key,value] of Object.entries(data)) {
            const input = target.elements.namedItem(key);
            if (!input || input.type === 'file') continue;
            if (input.type === 'checkbox') input.checked = Boolean(value);
            else input.value = value ?? '';
        }
    };
    const post = async (url, body) => {
        const response = await fetch(url,{method:'POST',credentials:'same-origin',headers:{'Accept':'application/json','X-CSRF-TOKEN':root.dataset.csrf},body});
        let payload;
        try { payload = await response.json(); } catch (_) { throw new Error('Your session may have expired. Refresh the page and try again.'); }
        if (!response.ok) throw new Error(payload.errors ? Object.values(payload.errors).flat().join('\n') : payload.message || 'The request could not be completed.');
        return payload;
    };
    const xhrUpload = (url, data, onProgress) => new Promise((resolve,reject) => {
        const xhr = new XMLHttpRequest();
        xhr.open('POST',url); xhr.timeout = 180000;
        xhr.setRequestHeader('Accept','application/json');
        xhr.setRequestHeader('X-CSRF-TOKEN',root.dataset.csrf);
        xhr.upload.onprogress = event => { if (event.lengthComputable) onProgress(event.loaded/event.total*100); };
        xhr.onload = () => {
            let result;
            try { result = JSON.parse(xhr.responseText); } catch (_) { reject(new Error('The server could not accept this upload. Check your session and the 20 MB file limit.')); return; }
            if (xhr.status >= 200 && xhr.status < 300) resolve(result);
            else reject(new Error(result.errors ? Object.values(result.errors).flat().join('\n') : result.message || 'Upload failed.'));
        };
        xhr.onerror = () => reject(new Error('Network error. Check your connection and try again.'));
        xhr.ontimeout = () => reject(new Error('Upload timed out. Check the library before retrying.'));
        xhr.send(data);
    });
    const refresh = (id, message, resetFilters = false) => {
        if (id) sessionStorage.setItem('vd-selected',String(id));
        sessionStorage.setItem('vd-message',message);
        if (resetFilters) window.location.assign(root.dataset.storeUrl);
        else window.location.reload();
    };
    const selectVideo = id => {
        selected = records.get(String(id));
        if (!selected) return;
        $$('[data-video-row]').forEach(row => row.classList.toggle('is-selected',row.dataset.videoRow === String(id)));
        $$('#vd-edit-fields input, #vd-edit-fields select, #vd-edit-fields textarea, #vd-edit-fields button[type=submit]').forEach(node => node.disabled = false);
        $('#vd-editor-hint').textContent = 'Editing '+selected.title+'. Changes take effect after you save.';
        setFields(settings,selected);
        const preview = $('#vd-preview'); preview.replaceChildren();
        if (selected.playback) {
            const video = document.createElement('video');
            video.controls = true; video.playsInline = true; video.preload = 'metadata'; video.src = selected.playback;
            if (selected.poster) video.poster = selected.poster;
            if (selected.captions) {
                const track = document.createElement('track');
                track.kind = 'captions'; track.src = selected.captions; track.srclang = selected.caption_language || 'en'; track.label = (selected.caption_language || 'en').toUpperCase(); track.default = true;
                video.append(track);
            }
            video.addEventListener('error',() => notice('This browser could not play the video. Upload an H.264 MP4 or WebM replacement for broader compatibility.',true));
            preview.append(video);
        } else if (selected.embed) {
            const frame = document.createElement('iframe');
            frame.src = selected.embed; frame.title = selected.title; frame.allowFullscreen = true;
            frame.allow = 'fullscreen; picture-in-picture'; frame.referrerPolicy = 'strict-origin-when-cross-origin';
            preview.append(frame);
        } else preview.append(element('span','This legacy video has no supported playback URL. Edit it to upload a replacement.'));
        $('#vd-preview-title').textContent = selected.title;
        $('#vd-preview-meta').textContent = [selected.sku,selected.platform,selected.resolution,duration(selected.duration)].filter(Boolean).join(' · ');
        $('#vd-uuid').textContent = selected.uuid;
        $('#vd-copy-uuid').disabled = $('#vd-audit').disabled = $('#vd-fullscreen').disabled = $('#vd-edit-selected').disabled = false;
        const measured = selected.platform === 'Website';
        $('#vd-detail-views').textContent = measured ? selected.views.toLocaleString() : 'Not connected';
        $('#vd-detail-time').textContent = measured ? duration(selected.seconds) : '—';
        $('#vd-detail-average').textContent = measured && selected.views ? duration(selected.seconds/selected.views) : '—';
        sessionStorage.setItem('vd-selected',String(id));
    };
    const platformFields = () => {
        const external = form.elements.platform.value !== 'Website';
        $('#vd-upload-fields').hidden = external;
        filesInput.disabled = external;
        filesInput.required = !external && !editing && queuedFiles.length === 0;
        $('#vd-external-field').hidden = !external;
        form.elements.external_url.required = external;
        form.elements.external_url.disabled = !external;
        const scheduled = form.elements.status.value === 'scheduled';
        $('#vd-publish-field').hidden = !scheduled;
        form.elements.publish_at.required = scheduled;
    };
    const setFiles = incoming => {
        const accepted = [...incoming];
        if (accepted.length > (editing ? 1 : 10)) { formError(editing ? 'Choose one replacement video.' : 'Choose up to 10 files per batch.'); return; }
        const invalid = accepted.find(file => file.size > 20*1024*1024 || !/\.(mp4|webm|mov)$/i.test(file.name));
        if (invalid) { formError(invalid.name+': use an MP4, WebM or MOV file up to 20 MB.'); queuedFiles = []; filesInput.value = ''; platformFields(); return; }
        queuedFiles = accepted; formError('');
        const listing = $('#vd-file-list'); listing.replaceChildren();
        queuedFiles.forEach(file => listing.append(element('div',file.name+' · '+(file.size/1048576).toFixed(1)+' MB')));
        if (!editing && accepted.length && !form.elements.title.value) form.elements.title.value = accepted[0].name.replace(/\.[^.]+$/,'').replace(/[-_]/g,' ');
        platformFields();
    };
    const openEditor = (video = null, incoming = null) => {
        if (busy) return;
        editing = video; form.reset(); queuedFiles = []; filesInput.value = '';
        $('#vd-file-list').replaceChildren(); $('#vd-progress').hidden = true; $('#vd-upload-status').textContent = ''; formError('');
        filesInput.multiple = !video;
        $('#vd-dialog-title').textContent = video ? 'Edit Video' : 'Upload Video';
        $('#vd-submit').textContent = video ? 'Save Changes' : 'Save Video';
        if (video) { setFields(form,video); form.elements.publish_at.value = localDate(video.publish_at); }
        else {
            form.elements.category.value = new URLSearchParams(location.search).get('category') || 'product';
            form.elements.product_id.value = new URLSearchParams(location.search).get('product_id') || '';
            form.elements.visibility.value = 'public';
            form.elements.gallery.checked = true;
        }
        if (incoming) setFiles(incoming);
        platformFields(); dialog.showModal();
        if (incoming?.length) form.elements.product_id.focus();
    };
    $$('[data-new-video]').forEach(button => button.addEventListener('click',() => openEditor()));
    $$('[data-select-video]').forEach(button => button.addEventListener('click',() => { selectVideo(button.dataset.selectVideo); $('#vd-preview').scrollIntoView({behavior:'smooth',block:'center'}); }));
    $$('[data-edit-video]').forEach(button => button.addEventListener('click',() => openEditor(records.get(button.dataset.editVideo))));
    $('#vd-edit-selected').addEventListener('click',() => { if (selected) openEditor(selected); });
    $$('[data-close-dialog]').forEach(button => button.addEventListener('click',() => { if (!busy) dialog.close(); }));
    dialog.addEventListener('cancel',event => { if (busy) event.preventDefault(); });
    $('#vd-platform').addEventListener('change',platformFields);
    $('#vd-status').addEventListener('change',platformFields);
    filesInput.addEventListener('change',() => setFiles(filesInput.files));
    [$('#vd-file-drop'),$('#vd-quick-drop')].forEach(zone => {
        ['dragenter','dragover'].forEach(event => zone.addEventListener(event,e => { e.preventDefault(); zone.classList.add('is-dragging'); }));
        ['dragleave','drop'].forEach(event => zone.addEventListener(event,() => zone.classList.remove('is-dragging')));
        zone.addEventListener('drop',event => {
            event.preventDefault(); if (busy) return;
            if (zone.id === 'vd-quick-drop') openEditor(null,event.dataTransfer.files);
            else setFiles(event.dataTransfer.files);
        });
    });
    const inspectFile = (file, makePoster) => new Promise(resolve => {
        const video = document.createElement('video'), url = URL.createObjectURL(file);
        let finished = false, timer;
        const finish = result => {
            if (finished) return; finished = true; clearTimeout(timer);
            video.removeAttribute('src'); video.load(); URL.revokeObjectURL(url); resolve(result);
        };
        const result = {};
        timer = setTimeout(() => finish(result),7000);
        video.muted = true; video.preload = 'auto';
        video.onloadedmetadata = () => {
            if (Number.isFinite(video.duration)) result.duration = Math.min(86400,video.duration);
            if (video.videoWidth && video.videoHeight) result.resolution = video.videoWidth+' x '+video.videoHeight;
            if (!makePoster) { finish(result); return; }
            video.currentTime = Math.min(1,(video.duration || 1)/2);
        };
        video.onseeked = () => {
            try {
                const canvas = document.createElement('canvas');
                canvas.width = Math.min(640,video.videoWidth); canvas.height = Math.round(canvas.width*video.videoHeight/video.videoWidth);
                canvas.getContext('2d').drawImage(video,0,0,canvas.width,canvas.height);
                canvas.toBlob(blob => { if (blob) result.poster = blob; finish(result); },'image/jpeg',0.85);
            } catch (_) { finish(result); }
        };
        video.onerror = () => finish(result);
        video.src = url;
    });
    form.addEventListener('submit', async event => {
        event.preventDefault(); if (busy || !form.reportValidity()) return;
        const external = form.elements.platform.value !== 'Website';
        if (!external && !editing && !queuedFiles.length) { formError('Choose a video file.'); return; }
        const original = new FormData(form);
        for (const key of ['gallery','allow_download']) original.set(key,form.elements[key].checked ? '1' : '0');
        if (form.elements.status.value === 'scheduled') original.set('publish_at',new Date(form.elements.publish_at.value).toISOString());
        else original.delete('publish_at');
        original.delete('file');
        if (!original.get('poster')?.size) original.delete('poster');
        if (!original.get('captions')?.size) original.delete('captions');
        const queue = external || !queuedFiles.length ? [null] : [...queuedFiles];
        busy = true; $('#vd-submit').disabled = true; $$('[data-close-dialog]').forEach(button => button.disabled = true);
        formError(''); const progress = $('#vd-progress'); progress.hidden = false; progress.value = 0;
        let saved = 0, latestId = editing?.id;
        try {
            for (let index=0; index<queue.length; index++) {
                const file = queue[index], data = new FormData();
                for (const [key,value] of original) data.append(key,value);
                if (editing) data.set('_method','PATCH');
                if (file) {
                    data.set('file',file);
                    if (queue.length > 1) data.set('title',file.name.replace(/\.[^.]+$/,'').replace(/[-_]/g,' ').slice(0,160));
                    $('#vd-upload-status').textContent = 'Preparing '+file.name+'…';
                    const details = await inspectFile(file,$('#vd-auto-poster').checked && !original.has('poster'));
                    if (details.duration) data.set('duration',String(details.duration));
                    if (details.resolution) data.set('resolution',details.resolution);
                    if (details.poster) data.set('poster',details.poster,'thumbnail.jpg');
                }
                $('#vd-upload-status').textContent = 'Saving video '+(index+1)+' of '+queue.length+'…';
                const response = await xhrUpload(editing ? editing.update_url : root.dataset.storeUrl,data,percent => progress.value = (index+percent/100)/queue.length*100);
                latestId = response.video.id; saved++;
                if (file) queuedFiles = queuedFiles.filter(queued => queued !== file);
            }
            refresh(latestId,saved+' video'+(saved===1?'':'s')+' saved successfully.',!editing);
        } catch (error) {
            formError((saved ? saved+' videos were saved. Only the remaining files will be retried.\n' : '')+error.message);
            if (saved) {
                $('#vd-file-list').replaceChildren(...queuedFiles.map(file => element('div',file.name)));
                platformFields();
            }
        } finally {
            busy = false; $('#vd-submit').disabled = false; $$('[data-close-dialog]').forEach(button => button.disabled = false);
            $('#vd-upload-status').textContent = saved ? saved+' saved.' : '';
        }
    });
    settings.addEventListener('submit',async event => {
        event.preventDefault(); if (!selected || busy) return;
        busy = true;
        const data = new FormData();
        for (const key of ['title','product_id','category','status','publish_at','platform','external_url','visibility','description','seo_title','tags','caption_language','duration','resolution']) {
            if (selected[key] !== null && selected[key] !== '') data.set(key,String(selected[key]));
        }
        for (const [key,value] of new FormData(settings)) data.set(key,value);
        for (const key of ['gallery','allow_download']) data.set(key,settings.elements[key].checked?'1':'0');
        data.set('_method','PATCH');
        try { await post(selected.update_url,data); refresh(selected.id,'Video settings saved.'); }
        catch (error) { notice(error.message,true); $('#vd-notice').scrollIntoView({block:'center'}); }
        finally { busy = false; }
    });
    const checkedIds = () => $$('.vd-row-check:checked').map(input => input.value);
    const updateSelection = () => {
        const ids = checkedIds(); $('#vd-selected-count').textContent = ids.length+' selected'; $('#vd-bulkbar').hidden = !ids.length;
        const all = $('#vd-check-all'); all.checked = ids.length>0 && ids.length === $$('.vd-row-check').length; all.indeterminate = ids.length>0 && !all.checked;
    };
    $('#vd-check-all').addEventListener('change',event => { $$('.vd-row-check').forEach(input => input.checked = event.target.checked); updateSelection(); });
    $$('.vd-row-check').forEach(input => input.addEventListener('change',updateSelection));
    const bulkAction = async (action,ids) => {
        if (busy || !ids.length) return;
        if (!window.confirm(action === 'delete' ? 'Delete '+ids.length+' video(s)? Uploaded files and playback metrics will be removed. This cannot be undone.' : (action==='publish'?'Publish ':'Move to draft: ')+ids.length+' video(s)? Existing privacy settings will be preserved.')) return;
        busy = true; const data = new FormData(); data.set('action',action); ids.forEach(id => data.append('ids[]',id));
        try { await post(root.dataset.bulkUrl,data); refresh(null,'Selected videos updated.'); }
        catch (error) { notice(error.message,true); }
        finally { busy = false; }
    };
    $$('[data-bulk-action]').forEach(button => button.addEventListener('click',() => bulkAction(button.dataset.bulkAction,checkedIds())));
    $$('[data-delete-video]').forEach(button => button.addEventListener('click',() => bulkAction('delete',[button.dataset.deleteVideo])));
    $('[data-page-size]').addEventListener('change',() => $('#vd-filters').requestSubmit());
    $('#vd-fullscreen').addEventListener('click',async () => {
        try { if (!document.fullscreenElement) await $('#vd-preview').requestFullscreen(); else await document.exitFullscreen(); }
        catch (_) { notice('Full screen is not available in this browser. Use the player’s full screen control.'); }
    });
    $('#vd-copy-uuid').addEventListener('click',async () => {
        if (!selected) return;
        try { await navigator.clipboard.writeText(selected.uuid); notice('Video UUID copied.'); }
        catch (_) { notice('Copy this UUID: '+selected.uuid); }
    });
    const showInfo = (title, nodes) => { $('#vd-info-title').textContent = title; $('#vd-info-body').replaceChildren(...nodes); infoDialog.showModal(); };
    $$('[data-close-info]').forEach(button => button.addEventListener('click',() => infoDialog.close()));
    $$('[data-guidelines]').forEach(button => button.addEventListener('click',() => showInfo('Upload Guidelines',[
        element('h3','Files and browser support'),
        element('p','Upload MP4, WebM or MOV files up to 20 MB each. H.264 MP4 gives the broadest browser support. MOV playback depends on the browser and codec. Choose up to 10 files for a sequential bulk upload.'),
        element('h3','Larger videos and external platforms'),
        element('p','Add an existing public YouTube or Vimeo URL for larger videos. Embedding does not upload to those platforms or change their privacy settings. External view counts and watch time are not connected.'),
        element('h3','Thumbnails and captions'),
        element('p','The browser can create a thumbnail from an uploaded video. You can also upload a JPG, PNG or WebP poster up to 2 MB and WebVTT captions up to 512 KB. Transcoding, GIF generation and automatic captions are not included.'),
        element('h3','Publishing and access'),
        element('p','Draft videos and future scheduled videos are visible only to administrators. Private videos remain private even after publishing. Public videos appear in the product gallery when enabled and the product is active. Schedules use the time zone of your browser.'),
        element('h3','Website analytics'),
        element('p','Website views count once per browser session, video and day. Watch time is collected during playback. Administrator previews are excluded. Metrics are operational estimates, not provider-certified analytics.')
    ])));
    $('#vd-audit').addEventListener('click',async () => {
        if (!selected) return;
        try {
            const response = await fetch(selected.audit_url,{headers:{Accept:'application/json'}});
            if (!response.ok) throw new Error('Could not load the audit history.');
            const data = await response.json();
            const nodes = [element('p','UUID: '+data.uuid)];
            for (const entry of data.entries) {
                const node = element('article',undefined,'vd-audit-entry');
                node.append(element('strong',entry.action),element('small',new Date(entry.created_at).toLocaleString()+' · User '+(entry.user_id ?? 'system')));
                const before = entry.before?.metadata || {}, after = entry.after?.metadata || {};
                const changed = [...new Set([...Object.keys(before),...Object.keys(after)])].filter(key => JSON.stringify(before[key])!==JSON.stringify(after[key]));
                if (changed.length) node.append(element('p','Changed: '+changed.join(', ')));
                nodes.push(node);
            }
            if (!data.entries.length) nodes.push(element('p','No audit entries are recorded for this legacy video yet.'));
            showInfo('Video Audit Log',nodes);
        } catch (error) { notice(error.message,true); }
    });
    const donut = $('#vd-donut'), counts = JSON.parse(donut.dataset.counts), total = counts.reduce((a,b)=>a+b,0);
    if (total) {
        const colors = ['#005b32','#0066db','#ff9d00','#fb4b26','#8c4bb4','#55969a']; let angle=0;
        const segments = counts.map((count,index) => { const start=angle; angle += count/total*360; return colors[index]+' '+start+'deg '+angle+'deg'; });
        donut.style.background = 'conic-gradient('+segments.join(',')+')';
    }
    const clock = () => $('#vd-clock').textContent = new Intl.DateTimeFormat('en-IE',{hour:'2-digit',minute:'2-digit',timeZone:'Europe/Dublin'}).format(new Date());
    clock(); setInterval(clock,30000);
    const restore = sessionStorage.getItem('vd-selected');
    if (records.has(restore)) selectVideo(restore);
    else if (records.size) selectVideo(records.keys().next().value);
    else {
        $$('#vd-edit-fields input, #vd-edit-fields select, #vd-edit-fields textarea, #vd-edit-fields button[type=submit]').forEach(node => node.disabled = true);
        $('#vd-fullscreen').disabled = $('#vd-edit-selected').disabled = true;
    }
    const message = sessionStorage.getItem('vd-message');
    if (message) { notice(message); sessionStorage.removeItem('vd-message'); }
})();