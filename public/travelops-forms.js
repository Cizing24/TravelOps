const formatAmount = new Intl.NumberFormat('zh-TW', { maximumFractionDigits: 2 });

for (const form of document.querySelectorAll('form[data-sale-summary]')) {
    const summary = form.querySelector('[data-summary]');
    const product = form.querySelector('select[name="product_id"]');
    const method = form.querySelector('select[name="method"]');
    const quantity = form.elements.quantity;
    const total = summary.querySelector('[data-total]');
    const hint = summary.querySelector('[data-summary-hint]');

    for (const option of product?.options ?? []) {
        if (option.dataset.name) {
            option.textContent = option.dataset.name + (option.disabled ? ' · 已售完' : '');
        }
    }

    function updateSummary() {
        const option = product?.selectedOptions[0];
        const selection = product ? option?.dataset : form.dataset;
        const price = Number(selection?.price);
        const available = Number(selection?.available);
        const count = quantity.valueAsNumber;
        const hasProduct = Number.isFinite(price);
        const hasQuantity = Number.isInteger(count) && count >= 1 && count <= 10;

        if (product) {
            summary.querySelector('[data-product]').textContent = selection?.name ?? '尚未選擇';
            summary.querySelector('[data-unit]').textContent = hasProduct ? 'NT$ ' + formatAmount.format(price / 100) : '—';
            summary.querySelector('[data-stock]').textContent = hasProduct ? available + ' 張' : '—';
            summary.querySelector('[data-method]').textContent = method.selectedOptions[0].textContent;
        }

        summary.querySelector('[data-quantity]').textContent = hasQuantity ? count + ' 張' : '—';
        total.textContent = hasProduct && hasQuantity ? 'NT$ ' + formatAmount.format(price * count / 100) : '—';
        hint.textContent = hasProduct && hasQuantity && count > available ? '張數超過畫面顯示的可售量，請確認張數。' : '';
    }

    updateSummary();
    summary.hidden = false;
    form.addEventListener('input', updateSummary);
    form.addEventListener('change', updateSummary);
    window.addEventListener('pageshow', updateSummary);
}

for (const form of document.querySelectorAll('form[data-pending]')) {
    const button = form.querySelector('button[type="submit"]');
    const label = button.textContent;
    form.addEventListener('submit', (event) => {
        if (button.disabled) {
            event.preventDefault();
            return;
        }

        button.disabled = true;
        button.textContent = form.dataset.pending;
        form.querySelector('[data-submit-status]').textContent = form.dataset.pending;
    });
    window.addEventListener('pageshow', () => {
        button.disabled = false;
        button.textContent = label;
        form.querySelector('[data-submit-status]').textContent = '';
    });
}
