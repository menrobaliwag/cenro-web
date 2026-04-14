(() => {
  'use strict';

  const onDomReady = (fn) => {
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', fn, { once: true });
    } else {
      fn();
    }
  };

  onDomReady(() => {
    // =========================
    // Deadline scope UI
    // =========================
    const scopeSelect = document.getElementById('scopeSelect');
    const barangayBox = document.getElementById('barangayBox');
    if (scopeSelect && barangayBox) {
      const sync = () => {
        barangayBox.style.display = scopeSelect.value === 'barangay' ? 'block' : 'none';
      };
      scopeSelect.addEventListener('change', sync);
      sync();
    }

    // =========================
    // Cleanup: file previews
    // =========================
    const attendance = document.getElementById('attendanceFile');
    const previewWrap = document.getElementById('attendancePreviewWrap');
    const previewName = document.getElementById('attendancePreviewName');
    if (attendance && previewWrap && previewName) {
      attendance.addEventListener('change', () => {
        const file = attendance.files && attendance.files[0];
        if (!file) {
          previewWrap.classList.add('d-none');
          previewName.textContent = '';
          return;
        }
        previewName.textContent = file.name;
        previewWrap.classList.remove('d-none');
      });
    }

    const photosInput = document.getElementById('photosInput');
    const thumbs = document.getElementById('photoThumbs');
    if (photosInput && thumbs) {
      photosInput.addEventListener('change', () => {
        thumbs.innerHTML = '';
        const files = Array.from(photosInput.files || []);
        files.slice(0, 12).forEach((file) => {
          const col = document.createElement('div');
          col.className = 'col-6 col-md-3';

          const card = document.createElement('div');
          card.className = 'ux-card p-2';

          const img = document.createElement('img');
          img.className = 'img-fluid rounded-3';
          img.alt = file.name;
          img.src = URL.createObjectURL(file);
          img.onload = () => URL.revokeObjectURL(img.src);

          card.appendChild(img);
          col.appendChild(card);
          thumbs.appendChild(col);
        });
      });
    }

    // =========================
    // Cleanup: view / edit modals
    // =========================
    const viewModal = document.getElementById('viewCleanupModal');
    const editModal = document.getElementById('editCleanupModal');
    const photoModalEl = document.getElementById('photoPreviewModal');
    const loading = document.getElementById('cleanupLoading');
    const content = document.getElementById('cleanupContent');

    const el = (id) => document.getElementById(id);

    function escText(s) {
      return String(s ?? '').replace(/[&<>"']/g, (m) => (
        { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[m]
      ));
    }

    function safeUrl(path) {
      if (!path) return '';
      try {
        return encodeURI(String(path));
      } catch {
        return String(path);
      }
    }

    function fmtDate(d) {
      if (!d) return '—';
      const [y, m, day] = String(d).split('-').map(Number);
      if (!y || !m || !day) return String(d);
      const dt = new Date(y, m - 1, day);
      return dt.toLocaleDateString(undefined, { year: 'numeric', month: 'long', day: 'numeric' });
    }

    function fmtTime(t) {
      if (!t) return '—';
      const parts = String(t).split(':');
      const hh = Number(parts[0] ?? 0);
      const mm = Number(parts[1] ?? 0);
      if (Number.isNaN(hh) || Number.isNaN(mm)) return String(t);

      let hour = hh % 12;
      if (hour === 0) hour = 12;
      const ampm = hh >= 12 ? 'PM' : 'AM';
      return `${hour}:${String(mm).padStart(2, '0')} ${ampm}`;
    }

    function statusBadge(st) {
      const s = st || 'Submitted';
      let cls = 'bg-info';
      if (s === 'Approved') cls = 'bg-success';
      else if (s === 'For Revision') cls = 'bg-danger';
      else if (s === 'Draft') cls = 'bg-secondary';
      else if (s === 'Submitted') cls = 'bg-warning text-dark';
      return `<span class="badge ${cls}">${escText(s)}</span>`;
    }

    viewModal?.addEventListener('show.bs.modal', async (event) => {
      if (!loading || !content) return;

      const btn = event.relatedTarget;
      const id = btn?.dataset?.id;

      // reset UI
      loading.hidden = false;
      content.hidden = true;

      el('vBarangay') && (el('vBarangay').textContent = '—');
      el('vTitle') && (el('vTitle').textContent = '—');
      el('vDate') && (el('vDate').textContent = '—');
      el('vTime') && (el('vTime').textContent = '—');
      el('vParticipants') && (el('vParticipants').textContent = '—');
      el('vStatus') && (el('vStatus').textContent = '—');
      el('vVenue') && (el('vVenue').textContent = '—');
      el('vAttendance') && (el('vAttendance').innerHTML = '—');
      el('vDesc') && (el('vDesc').textContent = '—');

      el('vGallery') && (el('vGallery').innerHTML = '');
      el('vNoPhotos') && (el('vNoPhotos').hidden = true);
      el('vPhotoCount') && (el('vPhotoCount').textContent = '0 file(s)');

      if (!id) {
        loading.hidden = true;
        content.hidden = false;
        el('vTitle') && (el('vTitle').textContent = 'No ID found');
        el('vDesc') && (el('vDesc').textContent = 'Yung button na nag-open ng modal dapat may data-id="...".');
        return;
      }

      try {
        const res = await fetch((window.BASE_URL || '') + '/modules/iec/cleanup_view.php?id=' + encodeURIComponent(id));
        const data = await res.json();
        if (!data.success) throw new Error(data.message || 'Failed');

        const cleanup = data.cleanup || {};

        el('vBarangay') && (el('vBarangay').textContent = cleanup.barangay || '—');
        el('vTitle') && (el('vTitle').textContent = cleanup.activity_title || '—');
        el('vDate') && (el('vDate').textContent = fmtDate(cleanup.activity_date));
        el('vTime') && (el('vTime').textContent = fmtTime(cleanup.activity_time));
        el('vVenue') && (el('vVenue').textContent = cleanup.venue || '—');
        el('vParticipants') && (el('vParticipants').textContent = String(cleanup.participants ?? 0));
        el('vStatus') && (el('vStatus').innerHTML = statusBadge(cleanup.status));
        el('vDesc') && (el('vDesc').textContent = cleanup.description || '—');

        if (cleanup.attendance_path) {
          const url = safeUrl(cleanup.attendance_path);
          const label = String(cleanup.attendance_type || 'file').toUpperCase();
          const name = cleanup.attendance_name || '';
          if (el('vAttendance')) {
            el('vAttendance').innerHTML = `
              <a class="btn btn-outline-secondary btn-sm" href="${url}" target="_blank" rel="noopener">
                <i class="fa fa-paperclip me-1"></i>${escText(label)}
              </a>
              ${name ? `<div class="text-muted small mt-1">${escText(name)}</div>` : ``}
            `;
          }
        } else if (el('vAttendance')) {
          el('vAttendance').innerHTML = '<span class="text-muted small">No attendance proof uploaded.</span>';
        }

        const photos = Array.isArray(data.photos) ? data.photos : [];
        el('vPhotoCount') && (el('vPhotoCount').textContent = `${photos.length} file(s)`);

        if (photos.length === 0) {
          el('vNoPhotos') && (el('vNoPhotos').hidden = false);
        } else {
          const wrap = el('vGallery');
          if (wrap) {
            photos.forEach((p, idx) => {
              const path = p.file_path || p.photo_path || '';
              const name = p.file_name || p.photo_name || `Photo ${idx + 1}`;
              if (!path) return;

              const url = safeUrl(path);

              const col = document.createElement('div');
              col.className = 'col-6 col-md-3';
              col.innerHTML = `
                <button type="button" class="btn p-0 w-100 text-start border rounded-3 overflow-hidden shadow-sm">
                  <img src="${url}" alt="${escText(name)}" class="iec-gallery-img">
                  <div class="p-2 small text-truncate">${escText(name)}</div>
                </button>
              `;

              col.querySelector('button')?.addEventListener('click', () => {
                el('pTitle') && (el('pTitle').textContent = name);
                el('pImg') && (el('pImg').src = url);

                if (photoModalEl && window.bootstrap?.Modal) {
                  window.bootstrap.Modal.getOrCreateInstance(photoModalEl).show();
                }
              });

              wrap.appendChild(col);
            });
          }
        }

        loading.hidden = true;
        content.hidden = false;
      } catch (e) {
        loading.hidden = true;
        content.hidden = false;
        el('vTitle') && (el('vTitle').textContent = 'Error loading details');
        el('vDesc') && (el('vDesc').textContent = e?.message || String(e));
      }
    });

    editModal?.addEventListener('show.bs.modal', async (event) => {
      const btn = event.relatedTarget;
      const id = btn?.dataset?.id;
      if (!id) {
        alert('No ID found. Add data-id="..." to the Edit button.');
        return;
      }

      try {
        const res = await fetch((window.BASE_URL || '') + '/modules/iec/cleanup_view.php?id=' + encodeURIComponent(id));
        const data = await res.json();

        if (!data.success) {
          alert(data.message || 'Failed to load cleanup data');
          return;
        }

        const cleanup = data.cleanup || {};

        el('editCleanupId') && (el('editCleanupId').value = cleanup.id || id);
        el('editTitle') && (el('editTitle').value = cleanup.activity_title || '');
        el('editDate') && (el('editDate').value = cleanup.activity_date || '');
        el('editTime') && (el('editTime').value = String(cleanup.activity_time || '').slice(0, 5));
        el('editVenue') && (el('editVenue').value = cleanup.venue || '');
        el('editParticipants') && (el('editParticipants').value = String(cleanup.participants ?? 0));
        el('editStatus') && (el('editStatus').value = cleanup.status || 'Submitted');
        el('editDesc') && (el('editDesc').value = cleanup.description || '');
      } catch {
        alert('Error loading cleanup info');
      }
    });

    // =========================
    // Delete modal fillers
    // =========================
    document.getElementById('deleteCleanupModal')?.addEventListener('show.bs.modal', (event) => {
      const btn = event.relatedTarget;
      if (!btn) return;

      const id = btn.dataset.id || '';
      const title = btn.dataset.title || '—';
      const date = btn.dataset.date || '';

      el('delCleanupId') && (el('delCleanupId').value = id);
      el('delCleanupTitle') && (el('delCleanupTitle').textContent = title);

      const meta = [];
      if (id) meta.push(`ID #${id}`);
      if (date) meta.push(`Date: ${date}`);
      el('delCleanupMeta') && (el('delCleanupMeta').textContent = meta.join(' • '));

      const cb = document.getElementById('confirmDeleteCleanup');
      if (cb) cb.checked = false;
    });

    document.getElementById('editBoardDocModal')?.addEventListener('show.bs.modal', (event) => {
      const btn = event.relatedTarget;
      if (!btn) return;
      el('editDocId') && (el('editDocId').value = btn.dataset.id || '');
      el('editDocTitle') && (el('editDocTitle').value = btn.dataset.title || '');
      el('editDocType') && (el('editDocType').value = btn.dataset.type || 'Others');
      el('editDateIssued') && (el('editDateIssued').value = btn.dataset.date || '');
      el('editNotes') && (el('editNotes').value = btn.dataset.notes || '');
      el('editBarangay') && (el('editBarangay').value = btn.dataset.barangay || '');
    });

    document.getElementById('deleteBoardDocModal')?.addEventListener('show.bs.modal', (event) => {
      const btn = event.relatedTarget;
      if (!btn) return;
      el('deleteDocId') && (el('deleteDocId').value = btn.dataset.id || '');
      el('deleteDocTitle') && (el('deleteDocTitle').textContent = btn.dataset.title || '');
    });

    document.getElementById('editGeneralFileModal')?.addEventListener('show.bs.modal', (event) => {
      const btn = event.relatedTarget;
      if (!btn) return;
      el('editGenId') && (el('editGenId').value = btn.dataset.id || '');
      el('editGenTitle') && (el('editGenTitle').value = btn.dataset.title || '');
      el('editGenCategory') && (el('editGenCategory').value = btn.dataset.category || 'Others');
      el('editGenStatus') && (el('editGenStatus').value = btn.dataset.status || 'Pending');
      el('editGenRemarks') && (el('editGenRemarks').value = btn.dataset.remarks || '');
      el('editGenBarangay') && (el('editGenBarangay').value = btn.dataset.barangay || '');
    });

    document.getElementById('deleteGeneralFileModal')?.addEventListener('show.bs.modal', (event) => {
      const btn = event.relatedTarget;
      if (!btn) return;
      el('deleteGenId') && (el('deleteGenId').value = btn.dataset.id || '');
      el('deleteGenTitle') && (el('deleteGenTitle').textContent = btn.dataset.title || '');
    });

    document.getElementById('editMinutesModal')?.addEventListener('show.bs.modal', (event) => {
      const btn = event.relatedTarget;
      if (!btn) return;
      el('editMinId') && (el('editMinId').value = btn.dataset.id || '');
      el('editMinQuarter') && (el('editMinQuarter').value = btn.dataset.quarter || 'Q1');
      el('editMinYear') && (el('editMinYear').value = btn.dataset.year || String(new Date().getFullYear()));
      el('editMinMeetingDate') && (el('editMinMeetingDate').value = btn.dataset.meeting_date || '');
      el('editMinPreparedBy') && (el('editMinPreparedBy').value = btn.dataset.prepared_by || '');
      el('editMinStatus') && (el('editMinStatus').value = btn.dataset.status || 'Submitted');
      el('editMinBarangay') && (el('editMinBarangay').value = btn.dataset.barangay || '');
    });

    document.getElementById('deleteMinutesModal')?.addEventListener('show.bs.modal', (event) => {
      const btn = event.relatedTarget;
      if (!btn) return;
      el('delMinId') && (el('delMinId').value = btn.dataset.id || '');
      el('delMinTitle') && (el('delMinTitle').textContent = btn.dataset.title || '');
    });
  });
})();
