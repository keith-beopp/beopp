<html>
<head>
    <title>Enter <?php echo htmlspecialchars($contest['title'] ?? $contest['name'] ?? $contest['slug'] ?? ('Contest #' . $contest['id'])); ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        :root { --max: 10; --thumb: 120px; --gap: 12px; }
        body { font-family: Arial, sans-serif; line-height: 1.4; padding: 16px; }
        h1 { margin-top: 8px; }
        .field { margin: 14px 0; }
        .hint { color: #555; font-size: 0.9em; }
        .error { color: #a00; margin-top: 6px; }
        .dropzone {
            border: 2px dashed #bbb; border-radius: 10px; padding: 18px; text-align: center;
            transition: border-color .15s ease, background .15s ease;
            cursor: pointer; background: #fafafa;
        }
        .dropzone.dragover { border-color: #4a90e2; background: #f0f7ff; }
        .dropzone input[type=file] { display: none; }
        .preview-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(var(--thumb), 1fr));
            gap: var(--gap);
            margin-top: 12px;
        }
        .card {
            border: 1px solid #ddd; border-radius: 10px; padding: 8px; background: #fff;
            display: flex; flex-direction: column; align-items: center; gap: 6px;
            box-shadow: 0 1px 3px rgba(0,0,0,.05);
        }
        .card img { width: 100%; height: var(--thumb); object-fit: cover; border-radius: 6px; }
        .card .meta { font-size: 0.85em; text-align: center; color: #444; }
        .row { display: flex; gap: 8px; align-items: center; justify-content: center; flex-wrap: wrap; }
        .btn { border: 1px solid #ccc; background: #fff; border-radius: 8px; padding: 8px 12px; cursor: pointer; }
        .btn.primary { background: #2f6fed; color: #fff; border-color: #2f6fed; }
        .btn.danger { border-color: #c33; color: #c33; }
        .cover-badge { font-size: 0.72em; color: #fff; background: #2f6fed; padding: 2px 6px; border-radius: 999px; }
        .muted { color: #666; font-size: 0.9em; }
        .form-actions { margin-top: 18px; display: flex; gap: 10px; align-items: center; }
        .req { color: #c00; }
        input[type=text], textarea { width: 100%; max-width: 680px; padding: 8px 10px; border: 1px solid #ccc; border-radius: 8px; }
        textarea { min-height: 120px; }
        @media (min-width: 760px) { .two-col { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; } }
    </style>
</head>
<body>
<?php include __DIR__ . '/../partials/header.php'; ?>

<h1>
  Enter
  <?= htmlspecialchars($contest['title']
        ?? $contest['name']
        ?? $contest['slug']
        ?? ('Contest #' . $contest['id'])) ?>
</h1>

<form id="entryForm" method="POST" enctype="multipart/form-data">
    <div class="two-col">
        <div>
            <div class="field">
                <label>Name <span class="req">*</span><br>
                    <input type="text" name="name" required>
                </label>
            </div>

            <div class="field">
                <label>Short Bio<br>
                    <textarea name="bio" placeholder="Tell voters a bit about this entry..."></textarea>
                </label>
            </div>

            <div class="field">
                <div id="dropzone" class="dropzone" tabindex="0">
                    <strong>Add photos</strong> (up to <span id="maxCount">10</span>)<br>
                    <span class="muted">Drag & drop or click to select. JPG/PNG/WebP, max 10 MB each.</span>
                    <input id="imagesInput" type="file" name="images[]" accept="image/*" multiple>
                </div>
                <div id="fileErrors" class="error" style="display:none;"></div>

                <!-- Which image is the cover (index in current order) -->
                <input type="hidden" name="primary_index" id="primaryIndex" value="0">
                <!-- Per-image captions are aligned with current order -->
                <div id="preview" class="preview-grid" aria-live="polite"></div>
                <div class="hint">Tip: click “Set as cover” on your favorite image. You can also remove images before submitting.</div>
            </div>
        </div>

        <div>
            <div class="field">
                <div class="hint">
                    <strong>How it works</strong><br>
                    • Add up to 10 images.<br>
                    • First image becomes your <em>cover</em>; you can change it.<br>
                    • Captions are optional.<br>
                </div>
            </div>
        </div>
    </div>

    <div class="form-actions">
        <button class="btn primary" type="submit">Submit Entry</button>
        <span class="muted">By submitting, you confirm you have rights to these images.</span>
    </div>
</form>

<script>
(function () {
    // --- Config ---
    const MAX_FILES = 10;
    const MAX_SIZE_BYTES = 10 * 1024 * 1024; // 10 MB
    const ACCEPTED_TYPES = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];

    // --- Elements ---
    const form = document.getElementById('entryForm');
    const dropzone = document.getElementById('dropzone');
    const input = document.getElementById('imagesInput');
    const preview = document.getElementById('preview');
    const fileErrors = document.getElementById('fileErrors');
    const primaryIndexEl = document.getElementById('primaryIndex');
    const maxCount = document.getElementById('maxCount');
    maxCount.textContent = MAX_FILES;

    // --- State ---
    let files = [];            // canonical list of File objects
    let canMirrorToInput = false; // whether assigning input.files works
    let dtForMirror = null;    // DataTransfer for browsers that allow mirroring

    // Feature-detect ability to set input.files
    (function detectMirrorSupport() {
        try {
            const testDT = new DataTransfer();
            const blob = new Blob(['x'], { type: 'text/plain' });
            const testFile = new File([blob], 'x.txt', { type: 'text/plain' });
            testDT.items.add(testFile);
            input.files = testDT.files;
            canMirrorToInput = input.files && input.files.length === 1;
            if (canMirrorToInput) dtForMirror = new DataTransfer();
        } catch (_) {
            canMirrorToInput = false;
        }
        // Clear test assignment to avoid odd states
        input.value = '';
    })();

    function formatBytes(bytes) {
        const units = ['B','KB','MB','GB'];
        let i = 0; let val = bytes;
        while (val >= 1024 && i < units.length-1) { val /= 1024; i++; }
        return val.toFixed(val < 10 ? 1 : 0) + ' ' + units[i];
    }

    function showError(msg) {
        fileErrors.style.display = 'block';
        fileErrors.textContent = msg;
    }
    function clearError() {
        fileErrors.style.display = 'none';
        fileErrors.textContent = '';
    }

    function validateFiles(list) {
        for (const f of list) {
            if (!ACCEPTED_TYPES.includes(f.type)) {
                throw new Error(`“${f.name}” is not a supported image type.`);
            }
            if (f.size > MAX_SIZE_BYTES) {
                throw new Error(`“${f.name}” exceeds the ${formatBytes(MAX_SIZE_BYTES)} limit.`);
            }
        }
    }

    function syncMirrorInput() {
        if (!canMirrorToInput) return;
        dtForMirror = new DataTransfer();
        for (const f of files) dtForMirror.items.add(f);
        input.files = dtForMirror.files; // Safe in supported browsers
    }

    function renderPreview() {
        preview.innerHTML = '';
        files.forEach((file, idx) => {
            const url = URL.createObjectURL(file);

            const card = document.createElement('div');
            card.className = 'card';
            card.dataset.index = idx;

            const topRow = document.createElement('div');
            topRow.className = 'row';
            if (String(idx) === String(primaryIndexEl.value)) {
                const badge = document.createElement('span');
                badge.className = 'cover-badge';
                badge.textContent = 'Cover';
                topRow.appendChild(badge);
            }

            const img = document.createElement('img');
            img.src = url;
            img.alt = file.name;

            const meta = document.createElement('div');
            meta.className = 'meta';
            meta.textContent = `${file.name} • ${formatBytes(file.size)}`;

            const caption = document.createElement('input');
            caption.type = 'text';
            caption.name = 'captions[]';
            caption.placeholder = 'Caption (optional)';
            caption.style.width = '100%';
            caption.dataset.index = idx;

            const row = document.createElement('div');
            row.className = 'row';

            const coverBtn = document.createElement('button');
            coverBtn.type = 'button';
            coverBtn.className = 'btn';
            coverBtn.textContent = 'Set as cover';
            coverBtn.addEventListener('click', () => {
                primaryIndexEl.value = idx;
                renderPreview();
            });

            const removeBtn = document.createElement('button');
            removeBtn.type = 'button';
            removeBtn.className = 'btn danger';
            removeBtn.textContent = 'Remove';
            removeBtn.addEventListener('click', () => {
                files.splice(idx, 1); // remove one
                // Keep primary index in range
                if (files.length === 0) {
                    primaryIndexEl.value = '';
                } else if (Number(primaryIndexEl.value) >= files.length) {
                    primaryIndexEl.value = 0;
                }
                syncMirrorInput();
                renderPreview();
            });

            row.appendChild(coverBtn);
            row.appendChild(removeBtn);

            card.appendChild(topRow);
            card.appendChild(img);
            card.appendChild(meta);
            card.appendChild(caption);
            card.appendChild(row);
            preview.appendChild(card);
        });
    }

    function addFiles(newFiles) {
        clearError();

        const incoming = Array.from(newFiles || []);
        if (!incoming.length) return;

        // enforce max count BEFORE validating (for user clarity)
        if (files.length + incoming.length > MAX_FILES) {
            showError(`You can upload up to ${MAX_FILES} images total.`);
            return;
        }

        try { validateFiles(incoming); }
        catch (e) { showError(e.message); return; }

        files = files.concat(incoming);

        if (files.length > 0 && primaryIndexEl.value === '') {
            primaryIndexEl.value = 0; // default cover to first
        }

        syncMirrorInput();
        renderPreview();
    }

    // Click/keyboard to open picker
    dropzone.addEventListener('click', () => input.click());
    dropzone.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); input.click(); }
    });

    // Drag & drop
    ['dragenter','dragover'].forEach(ev =>
        dropzone.addEventListener(ev, e => { e.preventDefault(); e.stopPropagation(); dropzone.classList.add('dragover'); })
    );
    ['dragleave','drop'].forEach(ev =>
        dropzone.addEventListener(ev, e => { e.preventDefault(); e.stopPropagation(); dropzone.classList.remove('dragover'); })
    );
    dropzone.addEventListener('drop', e => {
        const dropped = Array.from(e.dataTransfer.files || []).filter(f => f.type.startsWith('image/'));
        addFiles(dropped);
    });

    // Native file picker
    input.addEventListener('change', () => {
        const picked = Array.from(input.files || []);
        addFiles(picked);

        // ✅ IMPORTANT FIX:
        // Only reset the picker when we are NOT mirroring files into input.files.
        // If we clear it while mirroring, the browser submits zero files (UPLOAD_ERR_NO_FILE).
        if (!canMirrorToInput) {
            input.value = '';
        }

        // (We don't rely on input.files as the source of truth.)
    });

    // Submit handling:
    // - If we can mirror into the input, let the browser submit normally.
    // - If not, AJAX-submit with FormData and our in-memory files[].
    form.addEventListener('submit', async (e) => {
        clearError();

        if (files.length === 0) {
            e.preventDefault();
            showError('Please add at least one image.');
            return;
        }

        if (canMirrorToInput) {
            // ✅ Extra safety: ensure input.files matches our files[] right at submit time
            syncMirrorInput();
            // allow normal submit
            return;
        }

        // AJAX fallback
        e.preventDefault();
        try {
            const fd = new FormData(form);
            // Remove any stale captions[] added by the DOM (we’ll re-add in order)
            const captions = Array.from(preview.querySelectorAll('input[name="captions[]"]'))
                                  .map(el => el.value || '');
            // Rebuild captions in order (clear existing and append fresh)
            fd.delete('captions[]');
            captions.forEach(c => fd.append('captions[]', c));

            // Append files in order
            files.forEach(f => fd.append('images[]', f));

            const res = await fetch(window.location.href, { method: 'POST', body: fd, credentials: 'same-origin' });

            if (res.redirected) {
                window.location.href = res.url;
                return;
            }

            const text = await res.text();
            // Naive success detection: if server returns a page with a redirect notice or success message,
            // you can customize this. For now, replace the document with server response.
            document.open();
            document.write(text);
            document.close();
        } catch (err) {
            showError('Upload failed. Please try again.');
            console.error(err);
        }
    });
})();
</script>
</body>
</html>

