/**
 * Main application client script (Pure Vanilla JS, zero libraries)
 */
document.addEventListener('DOMContentLoaded', () => {
    const btnAjaxTest = document.getElementById('btn-ajax-test');
    const toast = document.getElementById('ajax-toast');
    const serverTimeEl = document.getElementById('server-time');

    if (btnAjaxTest && toast) {
        btnAjaxTest.addEventListener('click', async () => {
            const originalText = btnAjaxTest.innerHTML;
            btnAjaxTest.disabled = true;
            btnAjaxTest.innerHTML = 'Testing Ping via Fetch API...';

            const startTime = performance.now();

            try {
                const response = await fetch('/api/ping', {
                    headers: {
                        'Accept': 'application/json'
                    }
                });

                const latency = Math.round(performance.now() - startTime);

                if (!response.ok) {
                    throw new Error(`HTTP Error: ${response.status}`);
                }

                const data = await response.json();

                if (serverTimeEl && data.current_time) {
                    serverTimeEl.textContent = data.current_time;
                }

                toast.textContent = `Live Fetch API Success (${latency}ms) - DB Time: ${data.current_time}`;
                toast.classList.remove('hidden');
                toast.style.borderColor = 'rgba(16, 185, 129, 0.4)';
                toast.style.color = '#6ee7b7';
            } catch (err) {
                toast.textContent = `Fetch Failed: ${err.message}`;
                toast.classList.remove('hidden');
                toast.style.borderColor = 'rgba(239, 68, 68, 0.4)';
                toast.style.color = '#fca5a5';
            } finally {
                btnAjaxTest.disabled = false;
                btnAjaxTest.innerHTML = originalText;

                setTimeout(() => {
                    toast.classList.add('hidden');
                }, 5000);
            }
        });
    }
});
