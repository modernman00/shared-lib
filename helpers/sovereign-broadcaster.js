/**
 * SovereignBroadcaster Client SDK
 * 
 * Engineered by the Advanced Technology Group (ATG) under Olutobi (Deloitte) and Victor (CTO).
 * High-performance, zero-npm, drop-in replacement for Pusher JS.
 * Connects directly to SovereignBroadcaster PHP SSE endpoints with auto-reconnect,
 * HMAC channel authentication, and presence tracking.
 */
(function(global) {
  'use strict';

  class SovereignChannel {
    constructor(name, broadcaster) {
      this.name = name;
      this.broadcaster = broadcaster;
      this.callbacks = new Map();
      this.members = new Map();
      this.subscribed = false;
    }

    bind(event, callback) {
      if (!this.callbacks.has(event)) {
        this.callbacks.set(event, []);
      }
      this.callbacks.get(event).push(callback);
      return this;
    }

    unbind(event, callback) {
      if (!this.callbacks.has(event)) return this;
      if (!callback) {
        this.callbacks.delete(event);
      } else {
        const filtered = this.callbacks.get(event).filter(cb => cb !== callback);
        this.callbacks.set(event, filtered);
      }
      return this;
    }

    emit(event, data) {
      const cbs = this.callbacks.get(event);
      if (cbs && cbs.length > 0) {
        cbs.forEach(cb => {
          try {
            cb(data);
          } catch (e) {
            console.error(`[SovereignBroadcaster] Error in event listener for ${event}:`, e);
          }
        });
      }
    }
  }

  class SovereignBroadcasterClient {
    constructor(options = {}) {
      this.endpoint = options.endpoint || '/api/broadcasting/events';
      this.authEndpoint = options.authEndpoint || '/api/broadcasting/auth';
      this.headers = options.headers || {};
      this.channels = new Map();
      this.eventSource = null;
      this.socketId = this._generateSocketId();
      this.lastEventId = 0;
      this.reconnectAttempts = 0;
      this.maxReconnectDelay = 15000;
      this.isConnected = false;

      // Extract CSRF token from document if available
      if (!this.headers['X-CSRF-TOKEN']) {
        const metaCsrf = document.querySelector('meta[name="csrf-token"]');
        if (metaCsrf && metaCsrf.content) {
          this.headers['X-CSRF-TOKEN'] = metaCsrf.content;
        }
      }
    }

    _generateSocketId() {
      if (typeof crypto !== 'undefined' && typeof crypto.getRandomValues === 'function') {
        const arr = new Uint32Array(2);
        crypto.getRandomValues(arr);
        return `${arr[0]}.${arr[1]}`;
      }
      const rand1 = Math.floor(Math.random() * 1000000);
      const rand2 = Math.floor(Math.random() * 1000000);
      return `${rand1}.${rand2}`;
    }

    subscribe(channelName) {
      if (typeof channelName !== 'string' || !/^[A-Za-z0-9_.:@-]{1,191}$/.test(channelName)) {
        throw new Error('[SovereignBroadcaster] Invalid channel identifier');
      }
      if (this.channels.has(channelName)) {
        return this.channels.get(channelName);
      }

      const channel = new SovereignChannel(channelName, this);
      this.channels.set(channelName, channel);

      // If private or presence channel, perform authorization first
      if (channelName.startsWith('private-') || channelName.startsWith('presence-')) {
        this._authorizeChannel(channelName)
          .then(authData => {
            channel.authData = authData || {};
            channel.subscribed = true;
            this._connectSse(channelName);
            channel.emit('pusher:subscription_succeeded', authData);
          })
          .catch(err => {
            console.error(`[SovereignBroadcaster] Authorization failed for ${channelName}:`, err);
            channel.emit('pusher:subscription_error', err);
          });
      } else {
        channel.subscribed = true;
        this._connectSse(channelName);
        channel.emit('pusher:subscription_succeeded', {});
      }

      return channel;
    }

    unsubscribe(channelName) {
      if (!this.channels.has(channelName)) return;
      const channel = this.channels.get(channelName);
      channel.subscribed = false;
      this.channels.delete(channelName);

      if (channel._eventSource) {
        channel._eventSource.close();
      }
    }

    _authorizeChannel(channelName) {
      return fetch(this.authEndpoint, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          ...this.headers
        },
        body: JSON.stringify({
          channel_name: channelName,
          socket_id: this.socketId
        })
      }).then(res => {
        if (!res.ok) {
          throw new Error(`HTTP ${res.status} auth failure`);
        }
        return res.json();
      });
    }

    _connectSse(channelName) {
      const channel = this.channels.get(channelName);
      if (!channel || typeof window === 'undefined' || typeof EventSource === 'undefined') return;

      const origin = (window.location && window.location.origin) ? window.location.origin : 'http://localhost';
      const url = new URL(this.endpoint, origin);
      url.searchParams.set('channel', channelName);
      url.searchParams.set('socket_id', this.socketId);
      if (channel.authData && channel.authData.auth) {
        url.searchParams.set('auth', String(channel.authData.auth));
      }
      if (channel.authData && channel.authData.channel_data) {
        url.searchParams.set('channel_data', String(channel.authData.channel_data));
      }
      if (this.lastEventId > 0) {
        url.searchParams.set('last_event_id', String(this.lastEventId));
      }

      const es = new EventSource(url.toString());
      channel._eventSource = es;

      es.onopen = () => {
        this.isConnected = true;
        this.reconnectAttempts = 0;
      };

      es.onmessage = (e) => {
        if (e.lastEventId) {
          this.lastEventId = parseInt(e.lastEventId, 10);
        }
        try {
          const parsed = JSON.parse(e.data);
          const eventName = parsed.event || 'message';
          const payload = parsed.data !== undefined ? parsed.data : parsed;
          channel.emit(eventName, payload);
        } catch (err) {
          channel.emit('message', e.data);
        }
      };

      es.onerror = () => {
        this.isConnected = false;
        es.close();

        // Exponential backoff reconnect
        if (channel.subscribed) {
          this.reconnectAttempts++;
          const delay = Math.min(1000 * Math.pow(1.5, this.reconnectAttempts), this.maxReconnectDelay);
          setTimeout(() => {
            if (channel.subscribed) {
              this._connectSse(channelName);
            }
          }, delay);
        }
      };
    }

    disconnect() {
      this.channels.forEach(ch => {
        ch.subscribed = false;
        if (ch._eventSource) {
          ch._eventSource.close();
        }
      });
      this.channels.clear();
      this.isConnected = false;
    }
  }

  // Export globally for browser and modular environments
  if (typeof module !== 'undefined' && module.exports) {
    module.exports = { SovereignBroadcasterClient };
  } else {
    global.SovereignBroadcasterClient = SovereignBroadcasterClient;
    // Optional Pusher alias for seamless drop-in compatibility
    if (!global.Pusher) {
      global.Pusher = SovereignBroadcasterClient;
    }
  }
})(typeof window !== 'undefined' ? window : this);
