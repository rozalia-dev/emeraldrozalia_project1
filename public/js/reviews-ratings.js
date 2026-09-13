document.addEventListener('DOMContentLoaded', () => {
  const root = document.querySelector('[data-review-source]');
  if (!root) return;

  const reduceMotion = window.matchMedia?.('(prefers-reduced-motion: reduce)').matches;
  const formatNumber = (value) => new Intl.NumberFormat('en-IE', { maximumFractionDigits: 1 }).format(value);

  root.querySelectorAll('[data-rr-metric] .rr-kpi-val').forEach((valueNode) => {
    const match = valueNode.textContent.trim().match(/^([\d,]+(?:\.\d+)?)([\s\S]*)$/);
    if (!match) return;
    const target = Number(match[1].replaceAll(',', ''));
    if (!Number.isFinite(target) || reduceMotion) return;
    const suffix = match[2];
    const started = performance.now();
    const duration = 520;
    const tick = (now) => {
      const progress = Math.min(1, (now - started) / duration);
      const eased = 1 - ((1 - progress) ** 3);
      valueNode.textContent = formatNumber(target * eased) + suffix;
      if (progress < 1) window.requestAnimationFrame(tick);
    };
    valueNode.textContent = '0' + suffix;
    window.requestAnimationFrame(tick);
  });

  const selected = () => [...root.querySelectorAll('[data-rr-select]:checked')];
  const bulkCount = root.querySelector('[data-rr-bulk-count]');
  const selectAll = root.querySelector('[data-rr-select-all]');
  const updateSelection = () => {
    const count = selected().length;
    if (bulkCount) bulkCount.textContent = count + ' selected';
    if (selectAll) {
      const boxes = [...root.querySelectorAll('[data-rr-select]')];
      selectAll.checked = boxes.length > 0 && count === boxes.length;
      selectAll.indeterminate = count > 0 && count < boxes.length;
    }
  };
  root.querySelectorAll('[data-rr-select]').forEach((box) => box.addEventListener('change', updateSelection));
  selectAll?.addEventListener('change', () => {
    root.querySelectorAll('[data-rr-select]').forEach((box) => { box.checked = selectAll.checked; });
    updateSelection();
  });
  root.querySelector('[data-rr-bulk-submit]')?.closest('form')?.addEventListener('submit', (event) => {
    if (selected().length === 0) {
      event.preventDefault();
      window.alert('Select at least one review before applying a bulk action.');
    }
  });
  updateSelection();

  root.querySelectorAll('.rr-toggle').forEach((toggle) => {
    const checkbox = toggle.querySelector('input[type="checkbox"]');
    if (!checkbox) return;
    toggle.setAttribute('role', 'switch');
    toggle.setAttribute('tabindex', '0');
    toggle.setAttribute('aria-checked', checkbox.checked ? 'true' : 'false');
    const sync = () => {
      toggle.classList.toggle('active', checkbox.checked);
      toggle.setAttribute('aria-checked', checkbox.checked ? 'true' : 'false');
    };
    toggle.addEventListener('click', (event) => {
      if (event.target === checkbox) {
        sync();
        return;
      }
      checkbox.checked = !checkbox.checked;
      sync();
    });
    toggle.addEventListener('keydown', (event) => {
      if (event.key !== 'Enter' && event.key !== ' ') return;
      event.preventDefault();
      checkbox.checked = !checkbox.checked;
      sync();
    });
    checkbox.addEventListener('change', sync);
    sync();
  });

  const dialog = document.querySelector('[data-rr-dialog]');
  const fields = {
    title: dialog?.querySelector('[data-rr-detail-title]'),
    customer: dialog?.querySelector('[data-rr-detail-customer]'),
    product: dialog?.querySelector('[data-rr-detail-product]'),
    rating: dialog?.querySelector('[data-rr-detail-rating]'),
    status: dialog?.querySelector('[data-rr-detail-status]'),
    date: dialog?.querySelector('[data-rr-detail-date]'),
    uuid: dialog?.querySelector('[data-rr-detail-uuid]'),
    comment: dialog?.querySelector('[data-rr-detail-comment]')
  };
  const openDialog = (payload) => {
    if (!dialog) return;
    Object.entries(fields).forEach(([key, node]) => {
      if (node) node.textContent = payload[key] ?? '—';
    });
    if (dialog.showModal) dialog.showModal();
    else dialog.setAttribute('open', '');
  };
  root.querySelectorAll('[data-rr-view]').forEach((button) => {
    button.addEventListener('click', () => {
      try {
        openDialog(JSON.parse(atob(button.dataset.rrView)));
      } catch {
        window.alert('The review details could not be loaded.');
      }
    });
  });
  dialog?.querySelectorAll('[data-rr-dialog-close]').forEach((button) => {
    button.addEventListener('click', () => {
      if (dialog.close) dialog.close();
      else dialog.removeAttribute('open');
    });
  });
  dialog?.addEventListener('click', (event) => {
    if (event.target === dialog) dialog.close?.();
  });

  const guidelinesDialog = document.querySelector('[data-rr-guidelines-dialog]');
  root.querySelector('[data-rr-guidelines]')?.addEventListener('click', () => {
    if (guidelinesDialog?.showModal) guidelinesDialog.showModal();
    else guidelinesDialog?.setAttribute('open', '');
  });
  guidelinesDialog?.querySelectorAll('[data-rr-guidelines-close]').forEach((button) => {
    button.addEventListener('click', () => {
      if (guidelinesDialog.close) guidelinesDialog.close();
      else guidelinesDialog.removeAttribute('open');
    });
  });
  guidelinesDialog?.addEventListener('click', (event) => {
    if (event.target === guidelinesDialog) guidelinesDialog.close?.();
  });
});
