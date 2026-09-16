(() => {
    const existing = document.querySelector('[data-contact-scheduler]');
    const form = document.querySelector('#contact-form');
    if (!existing || !form) return;

    // app.js may already have bound the legacy scheduler. Clone it so this
    // appointment-specific implementation owns the controls without duplicate handlers.
    const scheduler = existing.cloneNode(true);
    existing.replaceWith(scheduler);

    const daysEl = scheduler.querySelector('[data-schedule-days]');
    const monthLabel = scheduler.querySelector('[data-schedule-month-label]');
    const prev = scheduler.querySelector('[data-schedule-prev]');
    const next = scheduler.querySelector('[data-schedule-next]');
    const summary = scheduler.querySelector('[data-schedule-summary]');
    const apply = scheduler.querySelector('[data-schedule-apply]');
    const dateInput = form.querySelector('[data-schedule-date-input]');
    const timeInput = form.querySelector('[data-schedule-time-input]');
    const formSummary = form.querySelector('[data-schedule-form-summary]');
    const timeWrap = scheduler.querySelector('.contact-time-options');
    const availabilityUrl = '/chat-24-7/appointments/availability';

    let modeInput = form.querySelector('input[name="meeting_mode"]');
    if (!modeInput) {
        modeInput = document.createElement('input');
        modeInput.type = 'hidden';
        modeInput.name = 'meeting_mode';
        form.appendChild(modeInput);
    }

    let modePicker = scheduler.querySelector('[data-meeting-modes]');
    if (!modePicker) {
        modePicker = document.createElement('div');
        modePicker.className = 'contact-time-picker contact-meeting-mode';
        modePicker.dataset.meetingModes = '';
        modePicker.innerHTML = '<div class="contact-time-heading"><strong>MEETING OPTION</strong><small>Required</small></div><div class="contact-time-options" data-meeting-mode-options role="group" aria-label="Choose meeting option"></div>';
        scheduler.querySelector('.contact-time-picker')?.after(modePicker);
    }
    const modeWrap = modePicker.querySelector('[data-meeting-mode-options]');

    const now = new Date();
    const todayMonth = new Date(now.getFullYear(), now.getMonth(), 1, 12, 0, 0);
    let cursor = new Date(todayMonth);
    let selectedDate = '';
    let selectedTime = '';
    let selectedMode = '';
    let monthData = null;

    const pad = value => String(value).padStart(2, '0');
    const monthKey = date => `${date.getFullYear()}-${pad(date.getMonth() + 1)}`;
    const dateKey = date => `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;
    const labelForMode = value => ({
        in_person: 'In Person — Our Office',
        microsoft_teams: 'Microsoft Teams',
        google_meet: 'Google Meet',
    })[value] || value;

    const clearApplied = () => {
        if (dateInput) dateInput.value = '';
        if (timeInput) timeInput.value = '';
        modeInput.value = '';
        if (formSummary) {
            formSummary.hidden = true;
            formSummary.textContent = '';
        }
    };

    const updateSummary = () => {
        if (!summary) return;
        if (!selectedDate) {
            summary.textContent = 'Choose an available Monday–Friday date. Bank/public holidays are unavailable.';
            summary.dataset.state = '';
            return;
        }
        const remaining = monthData?.days?.[selectedDate]?.remaining ?? 0;
        const parts = [`${selectedDate}`];
        if (selectedTime) parts.push(selectedTime + ' Irish time');
        if (selectedMode) parts.push(labelForMode(selectedMode));
        parts.push(`${remaining} slot${remaining === 1 ? '' : 's'} remaining that day`);
        summary.textContent = parts.join(' · ');
        summary.dataset.state = 'selected';
    };

    const renderTimes = () => {
        if (!timeWrap) return;
        timeWrap.replaceChildren();
        const available = selectedDate ? (monthData?.days?.[selectedDate]?.available || []) : [];
        ['13:00', '13:15', '13:30', '13:45'].forEach(time => {
            const button = document.createElement('button');
            button.type = 'button';
            button.dataset.scheduleTime = time;
            button.textContent = time;
            button.disabled = !selectedDate || !available.includes(time);
            button.setAttribute('aria-pressed', String(selectedTime === time));
            button.addEventListener('click', () => {
                selectedTime = time;
                clearApplied();
                renderTimes();
                updateSummary();
            });
            timeWrap.appendChild(button);
        });
    };

    const renderModes = modes => {
        if (!modeWrap) return;
        modeWrap.replaceChildren();
        (modes || [
            {value:'in_person', label:'In Person — Our Office'},
            {value:'microsoft_teams', label:'Microsoft Teams'},
            {value:'google_meet', label:'Google Meet'},
        ]).forEach(mode => {
            const button = document.createElement('button');
            button.type = 'button';
            button.dataset.meetingMode = mode.value;
            button.textContent = mode.label;
            button.setAttribute('aria-pressed', String(selectedMode === mode.value));
            button.addEventListener('click', () => {
                selectedMode = mode.value;
                clearApplied();
                renderModes(modes);
                updateSummary();
            });
            modeWrap.appendChild(button);
        });
    };

    const renderCalendar = () => {
        if (!daysEl || !monthData) return;
        daysEl.replaceChildren();
        const year = cursor.getFullYear();
        const month = cursor.getMonth();
        if (monthLabel) monthLabel.textContent = cursor.toLocaleDateString('en-IE', {month:'long', year:'numeric'});
        const first = new Date(year, month, 1, 12, 0, 0);
        const mondayOffset = (first.getDay() + 6) % 7;
        for (let i = 0; i < mondayOffset; i++) {
            const spacer = document.createElement('span');
            spacer.className = 'contact-date-spacer';
            spacer.setAttribute('aria-hidden', 'true');
            daysEl.appendChild(spacer);
        }
        const lastDay = new Date(year, month + 1, 0).getDate();
        for (let day = 1; day <= lastDay; day++) {
            const date = new Date(year, month, day, 12, 0, 0);
            const key = dateKey(date);
            const info = monthData.days?.[key];
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'contact-date-button';
            button.dataset.scheduleDate = key;
            button.textContent = String(day);
            const unavailable = !info || info.closed || info.remaining <= 0;
            button.disabled = unavailable;
            button.title = info?.holiday ? 'Irish bank/public holiday' : (info?.remaining === 0 ? 'Fully booked' : `${info?.remaining || 0} appointment slots available`);
            button.setAttribute('aria-label', `${date.toLocaleDateString('en-IE', {weekday:'long', day:'numeric', month:'long', year:'numeric'})}${unavailable ? ' — unavailable' : ` — ${info.remaining} slots available`}`);
            if (selectedDate === key) button.dataset.selected = 'true';
            button.addEventListener('click', () => {
                selectedDate = key;
                selectedTime = '';
                clearApplied();
                renderCalendar();
                renderTimes();
                updateSummary();
            });
            daysEl.appendChild(button);
        }
        if (prev) prev.disabled = monthKey(cursor) <= monthKey(todayMonth);
    };

    const loadMonth = async () => {
        if (summary) summary.textContent = 'Loading appointment availability…';
        try {
            const response = await fetch(`${availabilityUrl}?month=${encodeURIComponent(monthKey(cursor))}`, {headers:{Accept:'application/json'}, credentials:'same-origin'});
            const payload = await response.json();
            if (!response.ok) throw new Error(payload.message || 'Unable to load appointment availability.');
            monthData = payload;
            if (selectedDate && !monthData.days?.[selectedDate]?.available?.includes(selectedTime)) selectedTime = '';
            renderCalendar();
            renderTimes();
            renderModes(payload.modes);
            updateSummary();
        } catch (error) {
            monthData = null;
            if (summary) {
                summary.textContent = error.message || 'Unable to load appointment availability.';
                summary.dataset.state = 'error';
            }
        }
    };

    prev?.addEventListener('click', () => {
        if (prev.disabled) return;
        cursor = new Date(cursor.getFullYear(), cursor.getMonth() - 1, 1, 12, 0, 0);
        selectedDate = selectedTime = '';
        clearApplied();
        loadMonth();
    });
    next?.addEventListener('click', () => {
        const nextCursor = new Date(cursor.getFullYear(), cursor.getMonth() + 1, 1, 12, 0, 0);
        const max = new Date(todayMonth.getFullYear(), todayMonth.getMonth() + 12, 1, 12, 0, 0);
        if (nextCursor > max) return;
        cursor = nextCursor;
        selectedDate = selectedTime = '';
        clearApplied();
        loadMonth();
    });

    apply?.addEventListener('click', () => {
        if (!selectedDate || !selectedTime || !selectedMode) {
            if (summary) {
                summary.textContent = 'Choose an available date, time, and meeting option before scheduling.';
                summary.dataset.state = 'error';
            }
            return;
        }
        if (dateInput) dateInput.value = selectedDate;
        if (timeInput) timeInput.value = selectedTime;
        modeInput.value = selectedMode;
        if (formSummary) {
            formSummary.hidden = false;
            formSummary.textContent = `Appointment requested: ${selectedDate} at ${selectedTime} (Irish time) · ${labelForMode(selectedMode)}.`;
        }
        if (summary) {
            summary.textContent = 'Appointment added to your message. Complete your contact details and submit the form to reserve it.';
            summary.dataset.state = 'success';
        }
        form.scrollIntoView({behavior:'smooth', block:'start'});
    });

    if (summary) summary.textContent = 'Appointments: Monday–Friday, 13:00–14:00 Irish time, maximum 4 per day. Irish bank/public holidays are unavailable.';
    loadMonth();
})();
