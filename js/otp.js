let timer = null;
let lockTimer = null;
let statusEl = null;

function setStatus(type) {

    if (!statusEl) return;

    if (type === "LOCKED") {

        statusEl.textContent = "Status: LOCKED";
        statusEl.className = "fw-bold text-danger";

    } else if (type === "EXPIRED") {

        statusEl.textContent = "Status: EXPIRED";
        statusEl.className = "fw-bold text-warning";

    } else {

        statusEl.textContent = "Status: ACTIVE";
        statusEl.className = "fw-bold text-success";
    }
}

async function startOTPCountdown() {

    const countdownEl = document.getElementById("countdown");
    const resendBtn = document.getElementById("resendBtn");

    statusEl = document.getElementById("otpStatus");

    if (!countdownEl || !resendBtn) return;

    try {

        const otpExpireTime = parseInt(window.otpExpireTime || 0);

        const lockedUntil = parseInt(window.otpLockedUntil || 0);

        const now = Math.floor(Date.now() / 1000);

        clearInterval(lockTimer);
        clearInterval(timer);

        // LOCKED
        if (lockedUntil > now) {

            setStatus("LOCKED");

            function updateLock() {

                const current = Math.floor(Date.now() / 1000);

                let timeLeft = lockedUntil - current;

                if (timeLeft <= 0) {

                    countdownEl.textContent = "00:00";

                    resendBtn.disabled = false;

                    clearInterval(lockTimer);

                    startOTPCountdown();

                    return;
                }

                let m = Math.floor(timeLeft / 60);

                let s = timeLeft % 60;

                countdownEl.textContent =
                    ` ${String(m).padStart(2,'0')}:${String(s).padStart(2,'0')}`;

                resendBtn.disabled = true;
            }

            updateLock();

            lockTimer = setInterval(updateLock, 1000);

            return;
        }

        // NORMAL TIMER
        function update() {

            setStatus("ACTIVE");

            const now = Math.floor(Date.now() / 1000);

            let timeLeft = otpExpireTime - now;

            if (timeLeft <= 0) {

                setStatus("EXPIRED");

                countdownEl.textContent = "00:00";

                resendBtn.disabled = false;

                clearInterval(timer);

                return;
            }

            let m = Math.floor(timeLeft / 60);

            let s = timeLeft % 60;

            countdownEl.textContent =
                `${String(m).padStart(2,'0')}:${String(s).padStart(2,'0')}`;

            resendBtn.disabled = true;
        }

        update();

        timer = setInterval(update, 1000);

    } catch (err) {

        console.error(err);
    }
}

function resendOTP() {

    const resendBtn = document.getElementById("resendBtn");
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || "";

    resendBtn.disabled = true;

    fetch('resend_otp', {
        method: 'POST',
        headers: {
            'X-CSRF-Token': csrfToken
        }
    })

    .then(res => res.json())

    .then(data => {

        if (data.success) {

            if (data.expires_at) {
                window.otpExpireTime = parseInt(data.expires_at, 10);
            }

            Swal.fire({
                icon: 'success',
                title: 'OTP Sent',
                text: 'Check your phone',
                timer: 1500,
                showConfirmButton: false
            });

            startOTPCountdown();

        } else {

            Swal.fire({
                icon: 'warning',
                title: 'Resend Failed',
                text: data.message || "Please try again later"
            });
        }
    })

    .catch(err => {

        console.error(err);

        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'Network or server error'
        });
    });
}

function verifyOTP() {

    let otp = document.getElementById('otpInput').value;
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || "";

if (!otp || otp.length !== 6) {

    Swal.fire({
        icon: 'warning',
        title: 'Invalid OTP',
        text: 'Enter a 6-digit code'
    });

    return;
}

    fetch('verify_otp', {

        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-CSRF-Token': csrfToken
        },
        body: 'otp=' + encodeURIComponent(otp)
    })

    .then(res => res.json())

    .then(data => {

        if (data.success) {

            Swal.fire({
                icon: 'success',
                title: 'Verified!',
                text: 'Registration successful',
                timer: 1500,
                showConfirmButton: false
            }).then(() => {

                window.location.href = "login";
            });

        } else {

            Swal.fire({
                icon: 'error',
                title: 'Invalid OTP',
                text: data.message || "Please try again"
            });
        }
    })

    .catch(() => {

        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'Something went wrong'
        });
    });
}

let otpStarted = false;

document.addEventListener("DOMContentLoaded", function () {

    const modal = document.getElementById("otpModal");

    if (!modal) return;

    function startOnce() {

        if (otpStarted) return;

        otpStarted = true;

        startOTPCountdown();
    }

    modal.addEventListener("shown.bs.modal", startOnce);

    // fallback
    setTimeout(startOnce, 300);
});
