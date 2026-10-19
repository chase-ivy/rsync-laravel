// RSYNC landlord dashboard (was the <script> at the bottom of landlord_dashboard.php).
// Same windows and steps as before. Changes: each action has its own address (data-url-* on <body>),
// requests carry Laravel's CSRF token, and names/emails are escaped before they are shown.

const page = document.body.dataset;

window.closeModal = function (id) { document.getElementById(id).classList.remove('show'); };

// ── SHARED POST (answers with JSON) ──
function post(url, body) {
    return fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
        },
        body,
    }).then(r => r.json().then(data => (r.ok ? data : { status: 'error', message: data.message || 'Request failed.' })));
}

function esc(s) {
    return String(s ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

// ── ADD GRID ──
function buildAddGrid() {
    const g = document.getElementById('add_grid'); g.innerHTML = '';
    for (let i = 0; i < 8; i++) g.appendChild(makeSlot('add_grid', i, i === 0));
}
buildAddGrid();

// ── EDIT: open modal, load existing images ──
window.openEdit = function (btn) {
    const r = btn.dataset;
    document.getElementById('edit_id').value = r.id;
    document.getElementById('edit_name').value = r.name;
    document.getElementById('edit_price').value = r.price;
    document.getElementById('edit_desc').value = r.desc;
    document.getElementById('edit_max').value = r.max;

    document.getElementById('existing_grid').innerHTML = '<span class="no-existing">Loading…</span>';
    document.getElementById('edit_grid').innerHTML = '';
    document.getElementById('editModal').classList.add('show');

    loadExistingImages(r.id);
};

function loadExistingImages(roomId) {
    post(page.urlRoomImages, 'room_id=' + roomId).then(imgs => renderExistingImages(imgs, roomId));
}

function renderExistingImages(imgs, roomId) {
    const grid = document.getElementById('existing_grid');
    grid.innerHTML = '';

    if (!imgs.length) {
        grid.innerHTML = '<span class="no-existing">No images yet.</span>';
    } else {
        imgs.forEach((img, i) => {
            const item = document.createElement('div');
            item.className = 'existing-item' + (i === 0 ? ' is-cover' : '');
            item.id = 'eimg_' + img.image_id;

            const im = document.createElement('img');
            im.src = page.imagesUrl + '/' + img.filename;
            im.onerror = () => { im.src = 'https://placehold.co/76x76?text=?'; };

            const del = document.createElement('button');
            del.type = 'button';
            del.className = 'del-img';
            del.title = 'Delete this image';
            del.innerHTML = '✕';
            del.onclick = () => deleteExistingImage(img.image_id, roomId);

            item.appendChild(im);
            item.appendChild(del);

            if (i === 0) {
                const tag = document.createElement('span');
                tag.className = 'cover-tag'; tag.textContent = 'COVER';
                item.appendChild(tag);
            }

            grid.appendChild(item);
        });
    }

    // Rebuild upload slots for remaining capacity
    const used = imgs.length;
    const slots = Math.max(0, 8 - used);
    const lbl = document.getElementById('slots_left_label');
    lbl.textContent = slots > 0 ? '(' + slots + ' slot' + (slots !== 1 ? 's' : '') + ' remaining)' : '(room full — 8/8)';

    const editGrid = document.getElementById('edit_grid');
    editGrid.innerHTML = '';
    // First new upload becomes cover if no existing images
    for (let i = 0; i < slots; i++) editGrid.appendChild(makeSlot('edit_grid', i, used === 0 && i === 0));
}

function deleteExistingImage(imageId, roomId) {
    if (!confirm('Remove this image from the room?')) return;
    post(page.urlDeleteImage, 'image_id=' + imageId + '&room_id=' + roomId).then(res => {
        if (res.status === 'deleted') {
            loadExistingImages(roomId);
        } else {
            alert(res.message || 'Could not delete image.');
        }
    });
}

// A new room needs at least one photo (the server checks this too).
window.checkAddPhotos = function (form) {
    const hasPhoto = [...form.querySelectorAll('input[type=file]')].some(i => i.files.length > 0);
    if (!hasPhoto) {
        alert('Please add at least one photo of the room. The first photo is the cover.');
        return false;
    }
    return true;
};

// ── GENERIC SLOT BUILDER ──
function makeSlot(gridId, i, isCover) {
    const slot = document.createElement('div');
    slot.className = 'img-slot';

    const lbl = document.createElement('label');
    lbl.htmlFor = gridId + '_s' + i;
    lbl.innerHTML = isCover
        ? '📷<span>Cover</span>'
        : '<span style="font-size:17px;line-height:1">+</span><span>' + (i + 1) + '</span>';

    const inp = document.createElement('input');
    inp.type = 'file'; inp.name = 'images[]'; inp.id = gridId + '_s' + i; inp.accept = 'image/*';
    inp.addEventListener('change', function () {
        if (this.files[0]) {
            const r = new FileReader();
            r.onload = e => {
                lbl.classList.add('filled');
                lbl.innerHTML = '<img src="' + e.target.result + '">';
                if (isCover) {
                    const t = document.createElement('span');
                    t.className = 'new-cover-tag'; t.textContent = 'COVER';
                    slot.appendChild(t);
                }
            };
            r.readAsDataURL(this.files[0]);
        }
    });

    slot.appendChild(lbl); slot.appendChild(inp);
    return slot;
}

// ── ASSIGN ──
window.openAssign = function (rid) {
    document.getElementById('ar_id').value = rid;
    const today = new Date(), end = new Date(today); end.setMonth(end.getMonth() + 1);
    document.getElementById('ar_start').value = today.toISOString().split('T')[0];
    document.getElementById('ar_end').value = end.toISOString().split('T')[0];
    document.getElementById('assignModal').classList.add('show');
};

window.assignTenant = function () {
    post(page.urlAssign, 'room_id=' + document.getElementById('ar_id').value
        + '&user_id=' + document.getElementById('ar_user').value
        + '&start_date=' + document.getElementById('ar_start').value
        + '&end_date=' + document.getElementById('ar_end').value)
        .then(d => { if (d.status === 'error') alert(d.message); else location.reload(); });
};

// ── REMOVE TENANT ──
window.openRemove = function (rid) {
    const sel = document.getElementById('rm_tid');
    sel.innerHTML = '<option>Loading…</option>';
    document.getElementById('rm_info').textContent = '';
    document.getElementById('removeModal').classList.add('show');
    post(page.urlRoomTenants, 'room_id=' + rid).then(list => {
        if (!list.length) { sel.innerHTML = '<option value="">No active tenants</option>'; return; }
        sel.innerHTML = list.map(t => `<option value="${t.tenant_id}" data-s="${t.start_date}" data-e="${t.end_date}">${esc(t.name)} (${t.start_date}–${t.end_date})</option>`).join('');
    });
};
document.getElementById('rm_tid').addEventListener('change', function () {
    const o = this.options[this.selectedIndex];
    document.getElementById('rm_info').textContent = o.dataset.s ? 'Contract: ' + o.dataset.s + ' → ' + o.dataset.e : '';
});

window.removeTenant = function () {
    const id = document.getElementById('rm_tid').value;
    if (!id) { alert('No tenant selected.'); return; }
    if (!confirm('Remove this tenant?')) return;
    post(page.urlRemove, 'tenant_id=' + id).then(d => { if (d.status === 'error') alert(d.message); location.reload(); });
};

window.delRoom = function (id) {
    if (!confirm('Delete this room? This cannot be undone.')) return;
    post(page.urlDeleteRoom, 'room_id=' + id).then(d => { if (d.status === 'error') alert(d.message); location.reload(); });
};

// ── APPLICANTS ──
let currentApplicantsRoomId = null;

window.openApplicants = function (roomId) {
    currentApplicantsRoomId = roomId;
    document.getElementById('applicantsModal').classList.add('show');
    post(page.urlApplicants, 'room_id=' + roomId).then(apps => renderApplicants(apps));
};

function renderApplicants(apps) {
    const list = document.getElementById('applicants_list');
    if (!apps.length) {
        list.innerHTML = '<p style="text-align:center;color:var(--ink3);padding:20px;">No applicants yet.</p>';
        return;
    }
    list.innerHTML = apps.map(app => `
        <div style="padding:12px;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center;">
            <div>
                <strong>${esc(app.name)}</strong>
                <div style="font-size:12px;color:var(--ink3);">${esc(app.email)}</div>
                <div style="font-size:11px;color:var(--ink3);margin-top:4px;">Applied: ${app.applied_at ? new Date(app.applied_at.replace(' ', 'T')).toLocaleDateString() : '—'}</div>
                <div style="margin-top:5px;">
                    <span class="badge ${app.status === 'Verified' ? 'badge-av' : app.status === 'Rejected' ? 'badge-oc' : ''}" style="background:${app.status === 'Pending' ? 'rgba(251,191,36,.2)' : app.status === 'Verified' ? 'rgba(5,150,105,.15)' : 'rgba(220,38,38,.1)'};color:${app.status === 'Pending' ? '#b45309' : app.status === 'Verified' ? 'var(--green)' : 'var(--red)'}">${esc(app.status)}</span>
                </div>
            </div>
            <div style="display:flex;gap:6px;">
                ${app.status === 'Pending' ? `
                    <button class="btn btn-green btn-sm" onclick="verifyApplicant(${app.application_id},${app.user_id})">Verify</button>
                    <button class="btn btn-red btn-sm" onclick="rejectApplicant(${app.application_id})">Reject</button>
                ` : `<span style="font-size:11px;color:var(--ink3);">No action</span>`}
            </div>
        </div>
    `).join('');
}

window.verifyApplicant = function (appId, userId) {
    if (!confirm('Verify this applicant?')) return;
    post(page.urlVerify, 'application_id=' + appId + '&user_id=' + userId + '&room_id=' + currentApplicantsRoomId).then(res => {
        if (res.status === 'success') post(page.urlApplicants, 'room_id=' + currentApplicantsRoomId).then(apps => renderApplicants(apps));
        else alert(res.message || 'Error verifying applicant.');
    });
};

window.rejectApplicant = function (appId) {
    if (!confirm('Reject this applicant?')) return;
    post(page.urlReject, 'application_id=' + appId + '&room_id=' + currentApplicantsRoomId).then(res => {
        if (res.status === 'success') post(page.urlApplicants, 'room_id=' + currentApplicantsRoomId).then(apps => renderApplicants(apps));
        else alert(res.message || 'Error rejecting applicant.');
    });
};
