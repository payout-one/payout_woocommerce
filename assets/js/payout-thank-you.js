document.addEventListener('DOMContentLoaded', async () => {
    const loadingEl = document.createElement('div');
    loadingEl.classList.add('custom-loading-spinner');

    const blockElement = (el) => {
        el.style.position = 'relative';
        el.appendChild(loadingEl);
    };

    const unblockElement = (el) => {
        el.style.removeProperty('position');
        el.removeChild(loadingEl);
    };

    const orderElement =
        document.querySelector('.woocommerce-order') ??
        document.querySelector('main') ??
        document.querySelector('body');
    orderElement.classList.add('processing');
    blockElement(orderElement);

    const intervalPeriod = 1000; // in miliseconds
    const maxChecks = 8;
    let counter = 0;

    const params = new FormData();
    params.append('action', 'order_payout_status');
    params.append('oid', payout_thank_you_data.order_id);

    const checkStatusInterval = setInterval(async () => {
        const response = await fetch(payout_thank_you_data.ajax_url, {
            method: 'POST',
            body: params
        });

        if (!response.ok) {
            console.log('Cannot retrieve data.');
            return;
        }

        const data = await response.text();

        if (data === 'succeeded' || data === 'processing') {
            clearInterval(checkStatusInterval);
            orderElement.classList.remove('processing');
            orderElement.classList.add('done');
            unblockElement(orderElement);
            return;
        }

        counter++;
        if (counter > maxChecks) {
            clearInterval(checkStatusInterval);
            window.location.replace(payout_thank_you_data.payment_url);
        }
    }, intervalPeriod);
});
