(() => {
    const form = document.querySelector('#form-inspeksi form');
    const modeFieldset = form?.querySelector('[data-measurement-mode]');
    if (!form || !modeFieldset) return;

    const modeStatus = modeFieldset.querySelector('[data-measurement-mode-status]');
    const modeRadios = modeFieldset.querySelectorAll('input[name="measurement_mode"]');

    function updateMeasurementMode() {
        const automatic = form.querySelector('input[name="measurement_mode"]:checked')?.value === 'automatic';
        form.querySelectorAll('[data-dimension-measure]').forEach((input) => {
            input.readOnly = automatic;
            input.placeholder = automatic ? 'Baca dari alat ukur' : 'Masukkan nilai';
            const button = input.closest('tr')?.querySelector('[data-measurement-read]');
            if (button) button.hidden = !automatic;
        });
        if (modeStatus) {
            modeStatus.textContent = automatic
                ? 'Mode otomatis aktif. Gunakan tombol Baca alat ukur pada setiap parameter; bila adaptor belum tersedia, pilih mode Manual.'
                : 'Nilai dimensi diisi manual.';
        }
    }

    modeRadios.forEach((radio) => radio.addEventListener('change', updateMeasurementMode));
    form.addEventListener('click', (event) => {
        const button = event.target instanceof Element ? event.target.closest('[data-measurement-read]') : null;
        if (!button) return;
        if (form.querySelector('input[name="measurement_mode"]:checked')?.value !== 'automatic') return;

        const input = button.closest('tr')?.querySelector('[data-dimension-measure]');
        const status = button.closest('td')?.querySelector('[data-measurement-read-status]');
        if (!input || !status) return;

        let settled = false;
        const resetReadButton = () => {
            button.disabled = false;
            button.closest('.measure-input')?.removeAttribute('aria-busy');
        };
        const finish = (value) => {
            if (settled) return;
            settled = true;
            resetReadButton();
            const numericValue = Number(value);
            if ((typeof value !== 'number' && typeof value !== 'string') || value === '' || !Number.isFinite(numericValue)) {
                status.dataset.state = 'error';
                status.textContent = 'Alat ukur mengirim nilai yang bukan angka.';
                return;
            }
            input.value = String(numericValue);
            input.dispatchEvent(new Event('input', { bubbles: true }));
            status.dataset.state = 'success';
            status.textContent = `Nilai diterima: ${input.value} ${input.dataset.unit || ''}`.trim();
        };
        const fail = (error) => {
            if (settled) return;
            settled = true;
            resetReadButton();
            status.dataset.state = 'error';
            status.textContent = error instanceof Error && error.message
                ? `Pembacaan gagal: ${error.message}`
                : 'Pembacaan alat ukur gagal.';
        };
        const request = new CustomEvent('qc:measurement:read', {
            cancelable: true,
            detail: {
                standardId: input.dataset.standardId,
                parameter: input.dataset.parameter,
                unit: input.dataset.unit,
                resolve: finish,
                reject: fail,
            },
        });
        status.hidden = false;
        status.dataset.state = '';
        status.textContent = 'Menunggu nilai dari alat ukur…';
        button.disabled = true;
        button.closest('.measure-input')?.setAttribute('aria-busy', 'true');
        window.dispatchEvent(request);
        if (!request.defaultPrevented) {
            resetReadButton();
            status.dataset.state = 'error';
            status.textContent = 'Adaptor alat ukur belum dikonfigurasi. Pilih Manual atau pasang adaptor Bluetooth/Wi-Fi.';
            return;
        }
    });

    updateMeasurementMode();
})();
