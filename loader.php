<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Connection Status</title>
  <style>
    body { margin: 0; background: transparent; font-family: system-ui, sans-serif; }

    .status-box {
      position: fixed;
      left: 16px;
      bottom: 16px;
      background: #333;
      color: #fff;
      padding: 12px 16px;
      border-radius: 8px;
      font-size: 14px;
      display: none;
      align-items: center;
      gap: 12px;
      box-shadow: 0 4px 12px rgba(0,0,0,0.25);
      z-index: 9999;
      animation: fadeIn 0.3s ease;
    }

    .status-box.success { background: #2e7d32; }
    .status-box.error { background: #c62828; }

    button.refresh-btn {
      background: rgba(255,255,255,0.1);
      border: 1px solid rgba(255,255,255,0.25);
      color: #fff;
      padding: 4px 10px;
      border-radius: 6px;
      cursor: pointer;
      font-size: 13px;
    }
    button.refresh-btn:hover { background: rgba(255,255,255,0.2); }

    @keyframes fadeIn {
      from { opacity: 0; transform: translateY(20px); }
      to { opacity: 1; transform: translateY(0); }
    }
  </style>
</head>
<body>

  <div id="statusBox" class="status-box"></div>

  <script>
    const statusBox = document.getElementById('statusBox');
    let hideTimeout;

    function showStatus(message, type = 'error', withRefresh = false) {
      clearTimeout(hideTimeout);
      statusBox.className = 'status-box ' + type;
      statusBox.innerHTML = '';

      const msg = document.createElement('span');
      msg.textContent = message;
      statusBox.appendChild(msg);

      if (withRefresh) {
        const btn = document.createElement('button');
        btn.textContent = 'Refresh';
        btn.className = 'refresh-btn';
        btn.onclick = () => location.reload();
        statusBox.appendChild(btn);
      }

      statusBox.style.display = 'flex';

      if (type === 'success') {
        hideTimeout = setTimeout(() => {
          statusBox.style.display = 'none';
        }, 4000);
      }
    }

    function handleOnline() {
      showStatus('Your internet connection was restored.', 'success');
    }

    function handleOffline() {
      showStatus('You are currently offline.', 'error', true);
    }

    window.addEventListener('online', handleOnline);
    window.addEventListener('offline', handleOffline);

    // Initial state
    if (!navigator.onLine) {
      handleOffline();
    }
  </script>
</body>
</html>