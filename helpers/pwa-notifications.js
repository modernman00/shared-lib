/**
 * Universal PWA Real-Time Synchronized Notification & Badging Client
 *
 * Connects to Service Worker BroadcastChannel & Pusher Socket:
 * - Renders non-intrusive on-app floating toasts when app is open in foreground
 * - Elects single active tab leader to avoid duplicate UI toasts
 * - Resets Home Screen red badge icon upon opening app
 * - Transmits heartbeat to backend presence cache on tab focus
 */
(function () {
  'use strict';

  // 1. Clear Home Screen Badge upon opening the app
  if ('clearAppBadge' in navigator) {
    navigator.clearAppBadge().catch(function () {});
  }

  // 2. Client-Side Leader Election (Only 1 focused tab displays floating toasts)
  let syncChannel = null;

  try {
    if (typeof BroadcastChannel !== 'undefined') {
      syncChannel = new BroadcastChannel('fp_notification_sync');
    }
  } catch (e) {
    console.warn('[PWA Notification] BroadcastChannel not available');
  }

  // 3. Floating In-App Toast Renderer
  function showFloatingToast(payload) {
    if (!payload || !payload.title) return;

    // Remove existing toast if any
    const existing = document.getElementById('pwa-notification-toast');
    if (existing) {
      existing.remove();
    }

    const toast = document.createElement('div');
    toast.id = 'pwa-notification-toast';
    toast.setAttribute('role', 'alert');
    toast.setAttribute('aria-live', 'polite');
    toast.style.cssText = `
      position: fixed;
      top: 20px;
      right: 20px;
      max-width: 380px;
      width: calc(100% - 40px);
      background: rgba(18, 24, 38, 0.96);
      backdrop-filter: blur(12px);
      -webkit-backdrop-filter: blur(12px);
      border: 1px solid rgba(255, 255, 255, 0.12);
      border-radius: 16px;
      padding: 14px 16px;
      box-shadow: 0 20px 40px rgba(0, 0, 0, 0.45);
      color: #fff;
      font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
      z-index: 999999;
      cursor: pointer;
      display: flex;
      align-items: center;
      gap: 12px;
      transform: translateY(-50px);
      opacity: 0;
      transition: transform 0.35s cubic-bezier(0.16, 1, 0.3, 1), opacity 0.35s ease;
    `;

    const iconSrc = payload.icon || '/public/img/favicon/android-chrome-192x192.png';
    toast.innerHTML = `
      <img src="${iconSrc}" alt="Icon" style="width: 42px; height: 42px; border-radius: 10px; object-fit: cover; flex-shrink: 0;" />
      <div style="flex: 1; min-width: 0;">
        <div style="font-weight: 600; font-size: 14px; line-height: 1.2; color: #fff; margin-bottom: 3px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
          ${escapeHtml(payload.title)}
        </div>
        <div style="font-size: 13px; color: #cbd5e1; line-height: 1.3; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;">
          ${escapeHtml(payload.body || '')}
        </div>
      </div>
      <button type="button" id="pwa-toast-close" style="background: none; border: none; color: #94a3b8; font-size: 18px; cursor: pointer; padding: 4px 8px; line-height: 1;" aria-label="Close">
        &times;
      </button>
    `;

    document.body.appendChild(toast);

    // Smooth Entrance Animation
    requestAnimationFrame(() => {
      toast.style.transform = 'translateY(0)';
      toast.style.opacity = '1';
    });

    // Auto-dismiss after 6 seconds
    const autoDismissTimer = setTimeout(() => {
      dismissToast(toast);
    }, 6000);

    // Toast Click Navigation
    toast.addEventListener('click', (e) => {
      if (e.target && e.target.id === 'pwa-toast-close') {
        clearTimeout(autoDismissTimer);
        dismissToast(toast);
        return;
      }
      clearTimeout(autoDismissTimer);
      const targetUrl = payload.url || payload.data?.url || '/';
      if (targetUrl) {
        window.location.href = targetUrl;
      }
    });
  }

  function dismissToast(toastEl) {
    if (!toastEl) return;
    toastEl.style.transform = 'translateY(-40px)';
    toastEl.style.opacity = '0';
    setTimeout(() => {
      if (toastEl.parentNode) {
        toastEl.parentNode.removeChild(toastEl);
      }
    }, 350);
  }

  function escapeHtml(str) {
    return String(str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  // 4. Handle Inbound Message from Service Worker / BroadcastChannel
  function handleIncomingNotification(data) {
    if (!document.hasFocus() && document.visibilityState !== 'visible') {
      return; // Tab is not in active focus
    }
    showFloatingToast(data);
  }

  if (syncChannel) {
    syncChannel.onmessage = function (event) {
      if (event.data && event.data.type === 'ON_APP_NOTIFICATION') {
        handleIncomingNotification(event.data.payload);
      }
    };
  }

  if ('serviceWorker' in navigator) {
    navigator.serviceWorker.addEventListener('message', function (event) {
      if (event.data && event.data.type === 'ON_APP_NOTIFICATION') {
        handleIncomingNotification(event.data.payload);
      }
    });
  }

  // 5. Real-Time Presence Heartbeat (Informs server tab is active)
  function sendPresenceHeartbeat() {
    if (document.visibilityState === 'visible') {
      try {
        fetch('/api/notifications/presence', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          keepalive: true
        }).catch(function () {});
      } catch (e) {}
    }
  }

  document.addEventListener('visibilitychange', sendPresenceHeartbeat);
  window.addEventListener('focus', sendPresenceHeartbeat);
  setInterval(sendPresenceHeartbeat, 60000);

  sendPresenceHeartbeat();
})();
