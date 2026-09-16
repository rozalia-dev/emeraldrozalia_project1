(() => {
    const scheduler = document.querySelector('[data-appointment-scheduler]');
    if (!scheduler) return;

    const form = document.querySelector('#contact-form');
    const daysEl = scheduler.querySelector('[data-schedule-days]');
    const monthLabel = scheduler.querySelector('[data-schedule-month-label]');
    const prev = scheduler.querySelector('[data-schedule-prev]');
    const next = scheduler.querySelector('[data-schedule-next]');
    const summary = scheduler.querySelector('[data-schedule-summary]');
    const apply = scheduler.querySelector('[data-schedule-apply]');
    const dateInput = form?.querySelector('[data-schedule-date-input]');
    const timeInput = form?.querySelector('[data-schedule-time-input]');
    const typeInput = form?.querySelector('[data-schedule-type-input]');
    const formSummary = form?.querySelector('[data-schedule-form-summary]');
    const timeButtons = [...scheduler.querySelectorAll('[data-schedule-time]')];
    const typeButtons = [...scheduler.querySelectorAll('[data-schedule-meeting-type]')];
    const availabilityUrl = scheduler.dataset.availabilityUrl || '/appointments/availability';
    const initialMonth = scheduler.dataset.contactMonth || new Date().toISOString().slice(0, 7);
    const [initialYear, initialMonthNumber] = initialMonth.split('-').map(Number);
    let cursor = new Date(initialYear, initialMonthNumber - 1, 1, 12, 0, 0);
    let selectedDate = dateInput?.value || '';
    let selectedTime = timeInput?.value || '';
    let selectedType = typeInput?.value || '';
    let currentAvailability = null;
    let requestSerial = 0;

    const pad = (value) => String(value).padStart(2, '0');
    const monthKey = (date) => `${date.getFullYear()}-${pad(date.getMonth() + 1)}`;
    const dateLabel = (value) => new Intl.DateTimeFormat(undefined, {
        weekday: 'short', day: 'numeric', month: 'short', year: 'numeric',
    }).format(new Date(`${value}T12:00:00`));
    const typeLabel = (value) => ({
        in_person: 'In person — Emerald Rozalia office',
        microsoft_teams: 'Microsoft Teams',
        google_meet: 'Google Meet',
    }[value] || value);

    const setSummary = (message, state = '') => {
        if (!summary) return;
        summary.textContent = message;
        if (state) summary.dataset.state = state;
        else delete summary.dataset.state;
    };

    const renderSummary = () => {
        if (selectedDate && selectedTime && selectedType) {
            setSummary(`Selected: ${dateLabel(selectedDate)} at ${selectedTime} (Irish Time) · ${typeLabel(selectedType)}.`, 'selected');
            return;
        }
        setSummary('Choose a meeting type, an available date and one of the 13:00–14:00 Irish-time slots.');
    };

    const invalidateAppliedMeeting = () => {
        if (dateInput) dateInput.value = '';
        if (timeInput) timeInput.value = '';
        if (typeInput) typeInput.value = '';
        if (formSummary) {
            formSummary.hidden = true;
            formSummary.textContent = '';
        }
    };

    const reasonLabel = (day) => {
        if (!day) return 'Unavailable';
        if (day.holiday) return `${day.holiday} — unavailable`;
        return ({
            weekend: 'Weekend — unavailable',
            fully_booked: 'Fully booked — 4 appointments already scheduled',
            past: 'No remaining appointment slots',
        }[day.reason] || 'Unavailable');
    };

    const syncTimes = () => {
        const day = currentAvailability?.days?.[selectedDate];
        const slotMap = new Map((day?.slots || []).map((slot) => [slot.time, !!slot.available]));
        timeButtons.forEach((button) => {
            const available = !!selectedDate && slotMap.get(button.dataset.scheduleTime) === true;
            button.disabled = !available;
            button.setAttribute('aria-pressed', String(button.dataset.scheduleTime === selectedTime && available));
        });
        if (selectedTime && slotMap.get(selectedTime) !== true) selectedTime = '';
    };

    const bindDates = () => {
        daysEl?.querySelectorAll('[data-schedule-date]').forEach((button) => button.addEventListener('click', () => {
            selectedDate = button.dataset.scheduleDate || '';
            selectedTime = '';
            invalidateAppliedMeeting();
            daysEl.querySelectorAll('[data-schedule-date]').forEach((item) => {
                const selected = item === button;
                item.classList.toggle('is-selected', selected);
                item.setAttribute('aria-pressed', String(selected));
            });
            syncTimes();
            renderSummary();
        }));
    };

    const renderCalendar = () => {
        if (!daysEl || !currentAvailability) return;
        const year = cursor.getFullYear();
        const month = cursor.getMonth();
        const daysInMonth = new Date(year, month + 1, 0).getDate();
        const leadingDays = (new Date(year, month, 1).getDay() + 6) % 7;
        if (monthLabel) monthLabel.textContent = new Intl.DateTimeFormat(undefined, { month: 'long', year: 'numeric' }).format(cursor);
        if (prev) prev.disabled = monthKey(cursor) <= initialMonth;
        daysEl.replaceChildren();

        for (let index = 0; index < leadingDays; index++) {
            const spacer = document.createElement('span');
            spacer.className = 'contact-date-spacer';
            spacer.setAttribute('aria-hidden', 'true');
            daysEl.appendChild(spacer);
        }

        for (let day = 1; day <= daysInMonth; day++) {
            const date = new Date(year, month, day, 12, 0, 0);
            const key = `${year}-${pad(month + 1)}-${pad(day)}`;
            const availability = currentAvailability.days?.[key];
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'contact-date-button';
            button.dataset.scheduleDate = key;
            button.textContent = String(day);
            const basicLabel = new Intl.DateTimeFormat(undefined, {
                weekday: 'long', day: 'numeric', month: 'long', year: 'numeric',
            }).format(date);
            button.disabled = !availability?.available;
            button.setAttribute('aria-label', availability?.available
                ? `${basicLabel}. ${availability.remaining} appointment slot${availability.remaining === 1 ? '' : 's'} remaining.`
                : `${basicLabel}. ${reasonLabel(availability)}.`);
            button.title = availability?.available
                ? `${availability.remaining} of 4 appointment slots remaining`
                : reasonLabel(availability);
            button.setAttribute('aria-pressed', String(key === selectedDate));
            if (key === selectedDate && availability?.available) button.classList.add('is-selected');
            daysEl.appendChild(button);
        }
        bindDates();
        syncTimes();
    };

    const loadAvailability = async (force = false) => {
        const serial = ++requestSerial;
        const requestedMonth = monthKey(cursor);
        if (monthLabel) monthLabel.textContent = 'Checking availability…';
        if (daysEl) {
            daysEl.setAttribute('aria-busy', 'true');
            daysEl.querySelectorAll('button').forEach((button) => { button.disabled = true; });
        }
        try {
            const url = new URL(availabilityUrl, window.location.origin);
            url.searchParams.set('month', requestedMonth);
            if (force) url.searchParams.set('_', String(Date.now()));
            const response = await fetch(url, {
                credentials: 'same-origin',
                headers: { 'Accept': 'application/json' },
                cache: force ? 'no-store' : 'default',
            });
            const payload = await response.json().catch(() => ({}));
            if (!response.ok) throw new Error(payload.message || 'Unable to load appointment availability.');
            if (serial !== requestSerial || payload.month !== requestedMonth) return false;
            currentAvailability = payload;
            const selectedDay = currentAvailability.days?.[selectedDate];
            if (selectedDate && !selectedDay?.available) {
                selectedDate = '';
                selectedTime = '';
                invalidateAppliedMeeting();
            }
            renderCalendar();
            renderSummary();
            return true;
        } catch (error) {
            if (serial !== requestSerial) return false;
            currentAvailability = null;
            if (daysEl) daysEl.replaceChildren();
            if (monthLabel) monthLabel.textContent = new Intl.DateTimeFormat(undefined, { month: 'long', year: 'numeric' }).format(cursor);
            setSummary(error.message || 'Appointment availability could not be loaded. Please try again.', 'error');
            return false;
        } finally {
            if (serial === requestSerial) daysEl?.removeAttribute('aria-busy');
        }
    };

    prev?.addEventListener('click', async () => {
        if (prev.disabled) return;
        cursor.setMonth(cursor.getMonth() - 1);
        selectedDate = '';
        selectedTime = '';
        invalidateAppliedMeeting();
        await loadAvailability();
    });

    next?.addEventListener('click', async () => {
        cursor.setMonth(cursor.getMonth() + 1);
        selectedDate = '';
        selectedTime = '';
        invalidateAppliedMeeting();
        await loadAvailability();
    });

    timeButtons.forEach((button) => button.addEventListener('click', () => {
        if (button.disabled) return;
        selectedTime = button.dataset.scheduleTime || '';
        invalidateAppliedMeeting();
        timeButtons.forEach((item) => item.setAttribute('aria-pressed', String(item === button)));
        renderSummary();
    }));

    typeButtons.forEach((button) => button.addEventListener('click', () => {
        selectedType = button.dataset.scheduleMeetingType || '';
        invalidateAppliedMeeting();
        typeButtons.forEach((item) => item.setAttribute('aria-pressed', String(item === button)));
        renderSummary();
    }));

    apply?.addEventListener('click', async () => {
        if (!selectedType || !selectedDate || !selectedTime) {
            setSummary('Choose a meeting type, date and time before scheduling your appointment.', 'error');
            return;
        }

        const originalText = apply.textContent;
        apply.disabled = true;
        apply.textContent = 'CHECKING AVAILABILITY…';
        const refreshed = await loadAvailability(true);
        const day = currentAvailability?.days?.[selectedDate];
        const slot = day?.slots?.find((item) => item.time === selectedTime);
        if (!refreshed || !day?.available || !slot?.available) {
            selectedTime = '';
            syncTimes();
            setSummary('That slot is no longer available. Please choose another available time.', 'error');
            apply.disabled = false;
            apply.textContent = originalText;
            return;
        }

        if (dateInput) dateInput.value = selectedDate;
        if (timeInput) timeInput.value = selectedTime;
        if (typeInput) typeInput.value = selectedType;
        if (formSummary) {
            formSummary.hidden = false;
            formSummary.textContent = `Appointment requested for ${dateLabel(selectedDate)} at ${selectedTime} (Irish Time) · ${typeLabel(selectedType)}.`;
        }
        setSummary('Appointment added to your message. Complete the form to submit the request.', 'selected');
        apply.disabled = false;
        apply.textContent = originalText;
        form?.scrollIntoView({ behavior: 'smooth', block: 'start' });
        form?.querySelector('[name="name"]')?.focus();
    });

    typeButtons.forEach((button) => button.setAttribute('aria-pressed', String(button.dataset.scheduleMeetingType === selectedType)));
    renderSummary();
    loadAvailability();
})();
