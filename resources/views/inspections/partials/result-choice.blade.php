<div class="check-item" data-result-item>
    <div class="check-row"><strong>{{ $label }}</strong>
        <div class="result-item-controls">
            <div class="result-choice" role="group" aria-label="Hasil pemeriksaan">
                <label class="choice-ok"><input type="radio" name="{{ $name }}" value="OK" @checked($value === 'OK') required><span>OK</span></label>
                <label class="choice-ng"><input type="radio" name="{{ $name }}" value="NG" @checked($value === 'NG') required><span>NG</span></label>
            </div>
            <label class="evidence-field item-evidence"><span>Foto bukti <b data-evidence-required hidden>* wajib jika NG</b></span><input type="file" name="{{ $evidenceName }}" accept="image/jpeg,image/png,image/webp" data-evidence-file><small>JPG / PNG / WebP, maks. 5 MB</small></label>
        </div>
    </div>
</div>
