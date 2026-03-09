// CloudDrive — Frontend JS
document.addEventListener('DOMContentLoaded', () => {

  // Drag & Drop Upload Zone
  const zone  = document.getElementById('uploadZone');
  const input = document.getElementById('fileInput');
  const list  = document.getElementById('fileList');

  if (zone && input) {
    zone.addEventListener('click', () => input.click());

    zone.addEventListener('dragover', e => {
      e.preventDefault();
      zone.classList.add('drag-over');
    });

    zone.addEventListener('dragleave', () => zone.classList.remove('drag-over'));

    zone.addEventListener('drop', e => {
      e.preventDefault();
      zone.classList.remove('drag-over');
      const dt = new DataTransfer();
      Array.from(e.dataTransfer.files).forEach(f => dt.items.add(f));
      input.files = dt.files;
      renderFileList(input.files);
    });

    input.addEventListener('change', () => renderFileList(input.files));
  }

  function renderFileList(files) {
    if (!list) return;
    list.innerHTML = '';
    Array.from(files).forEach(f => {
      const div = document.createElement('div');
      div.className = 'd-flex justify-content-between align-items-center small text-secondary py-1 border-bottom border-secondary';
      div.innerHTML = `<span>📄 ${escHtml(f.name)}</span><span class="ms-2">${formatBytes(f.size)}</span>`;
      list.appendChild(div);
    });
  }

  // Upload via fetch with progress
  const startBtn = document.getElementById('startUpload');
  const form     = document.getElementById('uploadForm');

  if (startBtn && form) {
    startBtn.addEventListener('click', () => {
      if (!input || !input.files.length) {
        alert('Please select files to upload.'); return;
      }
      const progressWrap = document.getElementById('uploadProgress');
      const bar          = document.getElementById('progressBar');
      const data         = new FormData(form);

      progressWrap.style.display = 'block';
      startBtn.disabled = true;
      startBtn.textContent = 'Uploading…';

      const xhr = new XMLHttpRequest();
      xhr.open('POST', form.action);

      xhr.upload.onprogress = e => {
        if (e.lengthComputable) {
          const pct = Math.round(e.loaded / e.total * 100);
          bar.style.width = pct + '%';
          bar.textContent = pct + '%';
        }
      };

      xhr.onload = () => {
        if (xhr.responseURL) window.location.href = xhr.responseURL;
        else window.location.reload();
      };

      xhr.onerror = () => {
        alert('Upload failed. Please try again.');
        startBtn.disabled = false;
        startBtn.textContent = 'Upload';
      };

      xhr.send(data);
    });
  }

  // Auto-dismiss alerts
  setTimeout(() => {
    document.querySelectorAll('.alert-dismissible').forEach(el => {
      const bsAlert = bootstrap.Alert.getOrCreateInstance(el);
      bsAlert.close();
    });
  }, 5000);
});

function formatBytes(b) {
  if (b < 1024) return b + ' B';
  if (b < 1048576) return (b/1024).toFixed(1) + ' KB';
  if (b < 1073741824) return (b/1048576).toFixed(1) + ' MB';
  return (b/1073741824).toFixed(2) + ' GB';
}

function escHtml(s) {
  return s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
