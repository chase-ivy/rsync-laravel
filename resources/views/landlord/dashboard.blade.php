@extends('layouts.public', ['css' => 'resources/css/landlord-dashboard.css', 'js' => 'resources/js/landlord-dashboard.js'])

@section('title', 'Dashboard · RSYNC')

@section('fonts')
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@700;800&family=DM+Sans:wght@400;500&display=swap" rel="stylesheet">
<link href="https://unpkg.com/boxicons@latest/css/boxicons.min.css" rel="stylesheet">
@endsection

{{-- Addresses the page script talks to (the old page posted everything to itself). --}}
@section('body_attrs')
data-images-url="{{ \App\Support\RoomPhotos::baseUrl() }}"
data-url-delete-room="{{ route('landlord.rooms.delete') }}"
data-url-room-images="{{ route('landlord.rooms.images') }}"
data-url-delete-image="{{ route('landlord.rooms.images.delete') }}"
data-url-assign="{{ route('landlord.tenants.assign') }}"
data-url-remove="{{ route('landlord.tenants.remove') }}"
data-url-room-tenants="{{ route('landlord.rooms.tenants') }}"
data-url-applicants="{{ route('landlord.rooms.applicants') }}"
data-url-verify="{{ route('landlord.applications.verify') }}"
data-url-reject="{{ route('landlord.applications.reject') }}"
@endsection

@section('content')
<nav>
    <span>RS<em>Y</em>NC</span>
    <div class="nav-actions">
        <a href="{{ route('account') }}">Account</a>
        <a href="{{ route('landlord.maintenance') }}">Maintenance</a>
        <a href="{{ route('logout') }}">Logout</a>
    </div>
</nav>
@include('partials.verify-banner')

<div class="wrap">
    <div class="top-bar">
        <h1>Room Management</h1>
        <button class="btn btn-dark" onclick="document.getElementById('addModal').classList.add('show')">+ Add Room</button>
    </div>

    @if ($msg)<div class="toast {{ $isErr ? 'err' : '' }}">{{ $msg }}</div>@endif
    @if ($uploadStatus)<div class="toast">{{ $uploadStatus }}</div>@endif

    <div class="tbl-wrap">
    <table>
        <thead><tr><th>Images</th><th>Room</th><th>Price</th><th>Occupants</th><th>Status</th><th>Tenants</th><th>Actions</th></tr></thead>
        <tbody>
        @foreach ($rooms as $row)
            @php
                $max = (int) ($row->max_occupants ?? 1);
                $cur = (int) $row->current_occupants;
                $pct = $max > 0 ? round($cur / $max * 100) : 0;
                $full = $cur >= $max;
                $rTen = $tenantMap[$row->room_id] ?? collect();
            @endphp
        <tr>
            <td>
                @if ($row->images->isNotEmpty())
                <div class="img-strip">
                    @foreach ($row->images as $img)<img src="{{ \App\Support\RoomPhotos::url($img->filename) }}" onerror="this.style.display='none'" title="{{ $img->sort_order == 0 ? 'Cover' : 'Image ' . ($img->sort_order + 1) }}">@endforeach
                </div>
                @else<span class="no-img">No images</span>@endif
            </td>
            <td><strong>{{ $row->room_name }}</strong></td>
            <td>₱{{ number_format($row->price) }}</td>
            <td>
                <div class="bar-wrap">
                    <span>{{ $cur }}/{{ $max }}</span>
                    <div class="bar"><div class="bar-fill {{ $full ? 'full' : '' }}" style="width:{{ $pct }}%"></div></div>
                </div>
            </td>
            <td><span class="badge {{ $row->status === 'Available' ? 'badge-av' : 'badge-oc' }}">{{ $row->status }}</span></td>
            <td>
                @if ($rTen->isNotEmpty())
                    @foreach ($rTen as $t)<span class="t-chip">· {{ $t->user->name }} <span style="opacity:.55">({{ $t->start_date->format('Y-m-d') }}–{{ $t->end_date->format('Y-m-d') }})</span></span>@endforeach
                @else<span style="color:var(--ink3)">—</span>@endif
            </td>
            <td style="white-space:nowrap">
                <button class="btn btn-green btn-sm" onclick="openAssign({{ $row->room_id }})">Assign</button>
                {{-- Room details travel as data-* attributes (safe for quotes and line breaks in the text). --}}
                <button class="btn btn-amber btn-sm" onclick="openEdit(this)"
                        data-id="{{ $row->room_id }}" data-name="{{ $row->room_name }}" data-price="{{ $row->price }}"
                        data-desc="{{ $row->description }}" data-max="{{ $max }}">Edit</button>
                <button class="btn btn-red btn-sm"   onclick="openRemove({{ $row->room_id }})">Remove Tenant</button>
                <button class="btn btn-ghost btn-sm" onclick="openApplicants({{ $row->room_id }})">Applicants</button>
                <button class="btn btn-ghost btn-sm" onclick="delRoom({{ $row->room_id }})">Delete</button>
            </td>
        </tr>
        @endforeach
        </tbody>
    </table>
    </div>
</div>

<!-- ══ ADD MODAL ══ -->
<div class="modal" id="addModal">
<div class="modal-box">
    <button class="close-x" onclick="closeModal('addModal')">✕</button>
    <h3>Add Room</h3>
        <form method="POST" action="{{ route('landlord.rooms.store') }}" enctype="multipart/form-data" onsubmit="return checkAddPhotos(this)"></form>
        @csrf
        <input type="hidden" name="add_room" value="1">
        <div class="field"><label>Room Name</label><input type="text" name="room_name" placeholder="e.g. Room 201" required></div>
        <div class="row2">
            <div class="field"><label>Price / month</label><input type="number" name="price" placeholder="3500" min="0" required></div>
            <div class="field"><label>Max Occupants</label><input type="number" name="max_occupants" value="1" min="1" required></div>
        </div>
      <label>Images — at least 1, up to 8 · first = cover</label>
        <div class="field"><label>Description</label><textarea name="description" placeholder="Describe the room…"></textarea></div>
        <div class="modal-foot">
            <button type="button" class="btn btn-ghost" onclick="closeModal('addModal')">Cancel</button>
            <button type="submit" class="btn btn-dark">Save Room</button>
        </div>
    </form>
</div>
</div>

<!-- ══ EDIT MODAL ══ -->
<div class="modal" id="editModal">
<div class="modal-box">
    <button class="close-x" onclick="closeModal('editModal')">✕</button>
    <h3>Edit Room</h3>
    <form method="POST" action="{{ route('landlord.rooms.update') }}" enctype="multipart/form-data" id="editForm">
        @csrf
        <input type="hidden" name="update_room" value="1">
        <input type="hidden" name="room_id" id="edit_id">

        <div class="field"><label>Room Name</label><input type="text" name="room_name" id="edit_name" required></div>
        <div class="row2">
            <div class="field"><label>Price / month</label><input type="number" name="price" id="edit_price" min="0" required></div>
            <div class="field"><label>Max Occupants</label><input type="number" name="max_occupants" id="edit_max" min="1" required></div>
        </div>

        <!-- Existing images with delete buttons -->
        <div class="existing-imgs">
            <div class="existing-imgs-label">Current Images</div>
            <div class="existing-grid" id="existing_grid">
                <span class="no-existing">Loading…</span>
            </div>
        </div>

        <!-- Upload new images to fill remaining slots -->
        <div class="field" id="new_imgs_field">
            <label>Add More Images <span style="text-transform:none;font-weight:400" id="slots_left_label"></span></label>
            <div class="img-grid" id="edit_grid"></div>
        </div>

        <div class="field"><label>Description</label><textarea name="description" id="edit_desc"></textarea></div>
        <div class="modal-foot">
            <button type="button" class="btn btn-ghost" onclick="closeModal('editModal')">Cancel</button>
            <button type="submit" class="btn btn-dark">Update Room</button>
        </div>
    </form>
</div>
</div>

<!-- ══ ASSIGN MODAL ══ -->
<div class="modal" id="assignModal">
<div class="modal-box">
    <button class="close-x" onclick="closeModal('assignModal')">✕</button>
    <h3>Assign Tenant</h3>
    <input type="hidden" id="ar_id">
    <div class="field"><label>Tenant</label>
        <select id="ar_user">
            @foreach ($tenantUsers as $u)<option value="{{ $u->user_id }}">{{ $u->name }}</option>@endforeach
        </select>
    </div>
    <div class="row2">
        <div class="field"><label>Contract Start</label><input type="date" id="ar_start"></div>
        <div class="field"><label>Contract End</label><input type="date" id="ar_end"></div>
    </div>
    <div class="modal-foot">
        <button class="btn btn-ghost" onclick="closeModal('assignModal')">Cancel</button>
        <button class="btn btn-dark"  onclick="assignTenant()">Assign</button>
    </div>
</div>
</div>

<!-- ══ REMOVE MODAL ══ -->
<div class="modal" id="removeModal">
<div class="modal-box">
    <button class="close-x" onclick="closeModal('removeModal')">✕</button>
    <h3>Remove Tenant</h3>
    <p style="font-size:13px;color:var(--ink3);margin-bottom:14px">Select the tenant to remove from this room.</p>
    <div class="field"><label>Tenant</label><select id="rm_tid"><option>Loading…</option></select></div>
    <div id="rm_info" style="font-size:12px;color:var(--ink3);margin-top:5px"></div>
    <div class="modal-foot">
        <button class="btn btn-ghost" onclick="closeModal('removeModal')">Cancel</button>
        <button class="btn btn-red"   onclick="removeTenant()">Remove</button>
    </div>
</div>
</div>

<!-- ══ APPLICANTS MODAL ══ -->
<div class="modal" id="applicantsModal">
<div class="modal-box">
    <button class="close-x" onclick="closeModal('applicantsModal')">✕</button>
    <h3>Room Applicants</h3>
    <div id="applicants_list" style="margin-top:16px;max-height:400px;overflow-y:auto;">
        <div style="text-align:center;padding:20px;color:var(--ink3);">
            <i class="bx bx-loader-alt bx-spin" style="font-size:24px;"></i>
            <p>Loading applicants…</p>
        </div>
    </div>
</div>
</div>

<footer>
    <div class="foot-logo">RS<em>Y</em>NC</div>
    <p>© 2026 RSYNC. All rights reserved.</p>
</footer>
@endsection