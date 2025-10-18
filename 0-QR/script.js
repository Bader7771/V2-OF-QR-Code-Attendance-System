const COOLDOWN_SECONDS = 5;
const lastScanTimestamps = {};
let scanningPaused = false;

function getTodayDate() {
  return new Date().toISOString().split('T')[0];
}

async function recordScan(employeeId) {
  const now = Date.now();
  const lastScan = lastScanTimestamps[employeeId] || 0;
  const timeSinceLast = (now - lastScan) / 1000;

  if (timeSinceLast < COOLDOWN_SECONDS) {
    const wait = Math.ceil(COOLDOWN_SECONDS - timeSinceLast);
    startCountdown(wait);
    return `⏳ Please wait ${wait} second(s) before scanning again.`;
  }

  lastScanTimestamps[employeeId] = now;

  try {
    const response = await fetch('handle_scan.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({ employeeId })
    });

    const data = await response.json();
    if (data.success) {
      flashEffect();
    }
    return data.message;
  } catch (error) {
    return `❌ Error: ${error.message}`;
  }
}

async function onScanSuccess(decodedText, decodedResult) {
  if (scanningPaused) return;

  const resultMessage = await recordScan(decodedText);
  document.getElementById("result").innerText = resultMessage;


  scanningPaused = true;
  startCountdown(COOLDOWN_SECONDS);

  setTimeout(() => {
    scanningPaused = false;
  }, COOLDOWN_SECONDS * 1000);
}


function flashEffect() {
  const body = document.body;
  body.style.transition = 'background 0.3s';
  body.style.background = '#dfffdc'; 
  setTimeout(() => {
    body.style.background = '';
  }, 300);
}


function startCountdown(seconds) {
  const countdownEl = document.getElementById("countdown");
  let remaining = seconds;
  countdownEl.innerText = `⏳ Cooldown: ${remaining}s`;

  const interval = setInterval(() => {
    remaining--;
    if (remaining <= 0) {
      countdownEl.innerText = "";
      clearInterval(interval);
    } else {
      countdownEl.innerText = `⏳ Cooldown: ${remaining}s`;
    }
  }, 1000);
}


const qrCodeScanner = new Html5Qrcode("qr-reader");

qrCodeScanner.start(
  { facingMode: "environment" },
  { fps: 10, qrbox: 250 },
  onScanSuccess,
  (errorMessage) => {
   
  }
).catch(err => {
  document.getElementById("result").innerText = `Camera error: ${err}`;
});
