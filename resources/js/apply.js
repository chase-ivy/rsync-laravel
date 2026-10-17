// "Apply" for a room — shared by the home page and the room page.
// Same steps and messages as the old applyForRoom(); the server answers {status, message}.
export function submitApplication(roomId, roomName) {
    const page = document.body.dataset;

    if (!confirm('Apply for ' + roomName + '?')) {
        return;
    }

    fetch(page.applyUrl, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'Accept': 'application/json',
            // Laravel refuses form posts without this token (protects against forged requests).
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
        },
        body: 'apply_for_room=1&room_id=' + encodeURIComponent(roomId),
    })
        .then(r => r.json())
        .then(data => {
            if (data.status === 'success') {
                alert('Application submitted successfully! You will be notified once the landlord reviews your application.');
                window.location.href = page.dashboardUrl;
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(() => alert('An error occurred. Please try again.'));
}