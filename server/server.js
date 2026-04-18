import express  from 'express';
import http     from 'http';
import { Server } from 'socket.io';
import cors     from 'cors';
import admin    from 'firebase-admin';
import dotenv   from 'dotenv';
import { readFileSync } from 'fs';

dotenv.config();

// ─── Firebase Admin SDK ───────────────────────────────────────────────────────
const serviceAccount = JSON.parse(readFileSync('./serviceAccountKey.json', 'utf8'));

admin.initializeApp({
  credential: admin.credential.cert(serviceAccount),
});

const fcm = admin.messaging();

// ─── Express + Socket.io setup ────────────────────────────────────────────────
const app    = express();
const server = http.createServer(app);

const ALLOWED_ORIGINS = (
  process.env.ALLOWED_ORIGINS ||
  'http://localhost:5173,http://localhost:5174,http://localhost:5175'
).split(',');

const io = new Server(server, {
  cors: { origin: ALLOWED_ORIGINS, methods: ['GET', 'POST'] },
  pingTimeout:  30000,
  pingInterval: 10000,
});

app.use(cors({ origin: ALLOWED_ORIGINS }));
app.use(express.json());

// ─── In-memory user map: firebase_uid → Set<socketId> ───────────────────────
const onlineUsers = new Map();

function addUser(uid, socketId) {
  if (!onlineUsers.has(uid)) onlineUsers.set(uid, new Set());
  onlineUsers.get(uid).add(socketId);
}
function removeUser(uid, socketId) {
  const s = onlineUsers.get(uid);
  if (!s) return;
  s.delete(socketId);
  if (s.size === 0) onlineUsers.delete(uid);
}
function getUserSockets(uid) {
  return onlineUsers.get(uid) ?? new Set();
}
function emitToUser(uid, event, payload) {
  for (const sid of getUserSockets(uid)) io.to(sid).emit(event, payload);
}

// ─── Socket.io connection ─────────────────────────────────────────────────────
io.on('connection', (socket) => {
  const uid = socket.handshake.auth?.uid;

  if (!uid) {
    console.warn('[Socket] Connection without UID — disconnecting');
    socket.disconnect();
    return;
  }

  console.log(`[Socket] ✅  ${uid} connected (${socket.id})`);
  addUser(uid, socket.id);
  socket.join(`user:${uid}`);

  // Emit current online count to all clients
  io.emit('users:count', { count: onlineUsers.size });

  socket.on('disconnect', () => {
    console.log(`[Socket] ❌  ${uid} disconnected (${socket.id})`);
    removeUser(uid, socket.id);
    io.emit('users:count', { count: onlineUsers.size });
  });

  socket.on('ping:check', (cb) => {
    if (typeof cb === 'function') cb({ status: 'ok', ts: Date.now() });
  });
});

// ─── Shared notification builder ──────────────────────────────────────────────
/**
 * @param {object}  opts
 * @param {string}  opts.toUid          - recipient firebase_uid (or null for broadcast)
 * @param {string}  opts.type           - notification type
 * @param {string}  opts.title
 * @param {string}  opts.body
 * @param {string}  [opts.priority]     - 'normal' | 'high' | 'emergency'
 * @param {object}  [opts.data]
 * @param {string}  [opts.fcmToken]     - if provided, sends FCM push
 * @param {boolean} [opts.broadcast]    - if true, emit to ALL connected clients
 * @param {Array}   [opts.fcmTokens]    - array of FCM tokens for multi-device FCM broadcast
 */
async function sendNotification({
  toUid,
  type,
  title,
  body,
  priority = 'normal',
  data = {},
  fcmToken,
  broadcast = false,
  fcmTokens = [],
}) {
  const payload = {
    id:        data.alertId ?? data.campaignId ?? `notif_${Date.now()}_${Math.random().toString(36).slice(2, 7)}`,
    type,
    title,
    body,
    priority,
    data,
    timestamp: new Date().toISOString(),
    read:      false,
  };

  // 1. Real-time via Socket.io ───────────────────────────────────────────────
  if (broadcast) {
    io.emit('notification:new', payload);
    console.log(`[Notify] 📢  Broadcast → all (${io.sockets.sockets.size} connected)`);
  } else if (toUid) {
    emitToUser(toUid, 'notification:new', payload);
    console.log(`[Notify] 📡  Socket → ${toUid} | ${type}`);
  }

  // 2. FCM push — single token ───────────────────────────────────────────────
  const fcmMessage = (token) => ({
    token,
    notification: { title, body },
    data: {
      type,
      priority,
      ...Object.fromEntries(
        Object.entries(data).map(([k, v]) => [k, String(v)])
      ),
    },
    android: {
      priority: priority === 'emergency' || priority === 'high' ? 'high' : 'normal',
      notification: {
        channelId:    priority === 'emergency' ? 'lifelink_emergency' : 'lifelink_alerts',
        sound:        'default',
        color:        priority === 'emergency' ? '#dc2626' : '#b91c1c',
        defaultVibrateTimings: priority === 'emergency',
      },
    },
    apns: {
      payload: {
        aps: {
          sound:              'default',
          badge:              1,
          'interruption-level': priority === 'emergency' ? 'critical' : 'active',
        },
      },
    },
    webpush: {
      headers: { Urgency: priority === 'emergency' ? 'very-high' : 'high' },
      notification: {
        title,
        body,
        icon:               '/icons/lifelink-192.png',
        badge:              '/icons/badge-72.png',
        requireInteraction: priority === 'emergency' || priority === 'high',
        vibrate:            priority === 'emergency' ? [200, 100, 200, 100, 200] : [100],
        tag:                payload.id,
        data:               { url: '/', type, priority },
      },
      fcmOptions: { link: '/' },
    },
  });

  // Single-token FCM
  if (fcmToken) {
    try {
      await fcm.send(fcmMessage(fcmToken));
      console.log(`[Notify] 📲  FCM → ${toUid}`);
    } catch (err) {
      console.error(`[Notify] FCM error for ${toUid}:`, err.code ?? err.message);
      // Auto-clear stale/invalid tokens so they stop causing errors
      if (err.code === 'messaging/invalid-argument' || err.code === 'messaging/registration-token-not-registered') {
        try {
          await fetch(`${process.env.API_BASE || 'http://localhost/lifelink-backend/api'}/users/save_fcm_token.php`, {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify({ uid: toUid, fcm_token: '' }),
          });
          console.log(`[Notify] 🗑️  Cleared stale FCM token for ${toUid}`);
        } catch (clearErr) {
          console.warn(`[Notify] Could not clear token for ${toUid}:`, clearErr.message);
        }
      }
      // Don't throw — FCM failure shouldn't block socket delivery
    }
  }

  // Multi-token FCM broadcast (for campaigns / emergency alerts)
  if (fcmTokens.length > 0) {
    const validTokens = fcmTokens.filter(Boolean);
    if (validTokens.length > 0) {
      try {
        const response = await fcm.sendEachForMulticast({
          tokens:       validTokens,
          notification: { title, body },
          data: {
            type,
            priority,
            ...Object.fromEntries(
              Object.entries(data).map(([k, v]) => [k, String(v)])
            ),
          },
          android:  fcmMessage(validTokens[0]).android,
          apns:     fcmMessage(validTokens[0]).apns,
          webpush:  fcmMessage(validTokens[0]).webpush,
        });
        console.log(`[Notify] 📲  FCM multicast: ${response.successCount} sent, ${response.failureCount} failed`);
        // Remove failed tokens from DB
        if (response.failureCount > 0) {
          response.responses.forEach((r, i) => {
            if (!r.success && (r.error?.code === 'messaging/invalid-argument' || r.error?.code === 'messaging/registration-token-not-registered')) {
              console.log(`[Notify] 🗑️  Stale multicast token removed: ${validTokens[i].slice(0, 20)}...`);
              fetch(`${process.env.API_BASE || 'http://localhost/lifelink-backend/api'}/users/clear_fcm_token.php`, {
                method:  'POST',
                headers: { 'Content-Type': 'application/json' },
                body:    JSON.stringify({ fcm_token: validTokens[i] }),
              }).catch(() => {});
            }
          });
        }
      } catch (err) {
        console.error('[Notify] FCM multicast error:', err.message);
      }
    }
  }

  return payload;
}

// ─── Auth middleware ──────────────────────────────────────────────────────────
function requireSecret(req, res, next) {
  const secret = req.headers['x-lifelink-secret'];
  if (!secret || secret !== process.env.INTERNAL_SECRET) {
    return res.status(401).json({ error: 'Unauthorized' });
  }
  next();
}

// ═════════════════════════════════════════════════════════════════════════════
//  EXISTING ENDPOINTS (unchanged)
// ═════════════════════════════════════════════════════════════════════════════

app.post('/notify', requireSecret, async (req, res) => {
  const { toUid, type, title, body, data, fcmToken } = req.body;
  if (!toUid || !type || !title || !body)
    return res.status(400).json({ error: 'toUid, type, title, body are required' });
  try {
    const payload = await sendNotification({ toUid, type, title, body, data, fcmToken });
    return res.json({ success: true, payload });
  } catch (err) {
    return res.status(500).json({ error: 'Notification failed', detail: err });
  }
});

app.post('/notify/request-accepted', requireSecret, async (req, res) => {
  const { requesterUid, donorName, bloodGroup, donorFcmToken, requestId } = req.body;
  if (!requesterUid || !donorName)
    return res.status(400).json({ error: 'requesterUid and donorName required' });
  try {
    const payload = await sendNotification({
      toUid:    requesterUid,
      type:     'request_accepted',
      priority: 'high',
      title:    '🩸 Donor Found!',
      body:     `${donorName} has accepted your ${bloodGroup} blood request. They are on their way!`,
      data:     { requestId: String(requestId ?? ''), donorName, bloodGroup },
      fcmToken: donorFcmToken,
    });
    return res.json({ success: true, payload });
  } catch (err) {
    return res.status(500).json({ error: 'Failed', detail: err });
  }
});

app.post('/notify/request-declined', requireSecret, async (req, res) => {
  const { requesterUid, donorName, bloodGroup, fcmToken, requestId } = req.body;
  try {
    const payload = await sendNotification({
      toUid:    requesterUid,
      type:     'request_declined',
      priority: 'normal',
      title:    'Request Update',
      body:     `${donorName} could not fulfill your ${bloodGroup} request. We are looking for another donor.`,
      data:     { requestId: String(requestId ?? ''), donorName, bloodGroup },
      fcmToken,
    });
    return res.json({ success: true, payload });
  } catch (err) {
    return res.status(500).json({ error: 'Failed', detail: err });
  }
});

app.post('/notify/new-request-nearby', requireSecret, async (req, res) => {
  const { donors, bloodGroup, location, requesterName, requestId } = req.body;
  if (!Array.isArray(donors) || donors.length === 0)
    return res.status(400).json({ error: 'donors array required' });

  const results = await Promise.allSettled(
    donors.map(({ uid, fcmToken }) =>
      sendNotification({
        toUid:    uid,
        type:     'new_request_nearby',
        priority: 'high',
        title:    `🆘 Urgent: ${bloodGroup} Blood Needed`,
        body:     `${requesterName} near ${location} urgently needs ${bloodGroup} blood. Can you help?`,
        data:     { requestId: String(requestId ?? ''), bloodGroup, location },
        fcmToken,
      })
    )
  );

  const sent   = results.filter(r => r.status === 'fulfilled').length;
  const failed = results.filter(r => r.status === 'rejected').length;
  return res.json({ success: true, sent, failed });
});

app.post('/notify/donation-complete', requireSecret, async (req, res) => {
  const { donorUid, requesterName, points, fcmToken } = req.body;
  try {
    const payload = await sendNotification({
      toUid:    donorUid,
      type:     'donation_complete',
      priority: 'normal',
      title:    '🏅 Donation Confirmed!',
      body:     `${requesterName} confirmed your donation. You earned ${points} points. Thank you!`,
      data:     { points: String(points ?? 100) },
      fcmToken,
    });
    return res.json({ success: true, payload });
  } catch (err) {
    return res.status(500).json({ error: 'Failed', detail: err });
  }
});

// ═════════════════════════════════════════════════════════════════════════════
//  NEW ENDPOINTS v2
// ═════════════════════════════════════════════════════════════════════════════

/**
 * POST /notify/new-campaign
 * Called by PHP create_campaign.php after inserting a new campaign into DB.
 *
 * Body: {
 *   campaignId:   number,
 *   campaignName: string,
 *   organizedBy:  string,
 *   bloodGroup:   string,
 *   venue:        string,
 *   status:       'Upcoming' | 'Active',
 *   fcmTokens:    string[]   ← all active user FCM tokens from PHP
 * }
 */
app.post('/notify/new-campaign', requireSecret, async (req, res) => {
  const {
    campaignId,
    campaignName,
    organizedBy,
    bloodGroup,
    venue,
    status,
    fcmTokens = [],
  } = req.body;

  if (!campaignId || !campaignName) {
    return res.status(400).json({ error: 'campaignId and campaignName are required' });
  }

  try {
    const payload = await sendNotification({
      type:      'new_campaign',
      priority:  'normal',
      title:     `📣 New Campaign: ${campaignName}`,
      body:      `${organizedBy} is hosting a ${bloodGroup} blood drive at ${venue}. Status: ${status}. Tap to register!`,
      data:      {
        campaignId:   String(campaignId),
        campaignName,
        organizedBy,
        bloodGroup,
        venue,
        status,
      },
      broadcast: true,          // real-time socket → all connected users
      fcmTokens,                // FCM push → offline users
    });

    console.log(`[Campaign] 📣  Notified ${fcmTokens.length} FCM tokens`);
    return res.json({
      success:   true,
      payload,
      fcmSent:   fcmTokens.length,
      socketHit: io.sockets.sockets.size,
    });
  } catch (err) {
    console.error('[/notify/new-campaign] Error:', err);
    return res.status(500).json({ error: 'Failed', detail: err });
  }
});

/**
 * POST /notify/emergency-alert
 * Called by PHP create_emergency_alert.php.
 * HIGH-PRIORITY: displayed with red pulsing banner in the UI.
 *
 * Body: {
 *   alertId:     number,
 *   bloodGroup:  string,
 *   location:    string,
 *   description: string,
 *   donors:      [{ uid, fcmToken }]   ← matching blood-group donors
 *   allFcmTokens?: string[]            ← optional: send FCM to ALL users too
 * }
 */
app.post('/notify/emergency-alert', requireSecret, async (req, res) => {
  const {
    alertId,
    bloodGroup,
    location,
    description,
    donors = [],
    allFcmTokens = [],
  } = req.body;

  if (!alertId || !bloodGroup || !location) {
    return res.status(400).json({ error: 'alertId, bloodGroup, location required' });
  }

  const title = `🚨 EMERGENCY: ${bloodGroup} Blood Urgently Needed`;
  const body  = `Critical need at ${location}.${description ? ' ' + description : ''} If you can donate, please respond immediately.`;

  try {
    // A — Targeted socket + FCM to matching donors (by blood group)
    const donorResults = await Promise.allSettled(
      donors.map(({ uid, fcmToken }) =>
        sendNotification({
          toUid:    uid,
          type:     'emergency_alert',
          priority: 'emergency',
          title,
          body,
          data: {
            alertId:    String(alertId),
            bloodGroup,
            location,
          },
          fcmToken,
        })
      )
    );

    // B — Broadcast to all users NOT already notified via Part A (donors)
    // Use the same alertId so client-side dedup (seenIds) blocks duplicates for donors
    const donorUids = new Set(donors.map(d => d.uid));
    const broadcastPayload = {
      id:        String(alertId),   // ← same id as Part A so dedup works
      type:      'emergency_alert',
      priority:  'emergency',
      title,
      body,
      data:      { alertId: String(alertId), bloodGroup, location },
      timestamp: new Date().toISOString(),
      read:      false,
    };
    // Emit to all sockets, but skip donor sockets (they already got it in Part A)
    for (const [, socket] of io.sockets.sockets) {
      const socketUid = socket.data?.uid ?? socket.handshake?.auth?.uid;
      if (!donorUids.has(socketUid)) {
        socket.emit('notification:new', broadcastPayload);
      }
    }

    // C — FCM to all users not online (optional, if allFcmTokens provided)
    if (allFcmTokens.length > 0) {
      await sendNotification({
        type:      'emergency_alert',
        priority:  'emergency',
        title,
        body,
        data:      { alertId: String(alertId), bloodGroup, location },
        fcmTokens: allFcmTokens,
      });
    }

    const sent   = donorResults.filter(r => r.status === 'fulfilled').length;
    const failed = donorResults.filter(r => r.status === 'rejected').length;

    console.log(`[Emergency] 🚨  Alert ${alertId} → ${sent} donors notified`);
    return res.json({
      success:       true,
      donorsSent:    sent,
      donorsFailed:  failed,
      socketHit:     io.sockets.sockets.size,
      fcmBroadcast:  allFcmTokens.length,
    });
  } catch (err) {
    console.error('[/notify/emergency-alert] Error:', err);
    return res.status(500).json({ error: 'Failed', detail: err });
  }
});

/**
 * POST /notify/emergency-broadcast  (existing, kept for admin panel manual use)
 */
app.post('/notify/emergency-broadcast', requireSecret, async (req, res) => {
  const { title, body, data, priority = 'high', fcmTokens = [] } = req.body;
  if (!title || !body) return res.status(400).json({ error: 'title and body required' });

  const payload = {
    id:        `broadcast_${Date.now()}`,
    type:      'broadcast',
    priority,
    title,
    body,
    data:      data ?? {},
    timestamp: new Date().toISOString(),
    read:      false,
  };

  io.emit('notification:new', payload);

  if (fcmTokens.length > 0) {
    await sendNotification({
      type: 'broadcast', priority, title, body, data: data ?? {}, fcmTokens,
    });
  }

  return res.json({ success: true, connected: io.sockets.sockets.size });
});

// ─── Utility endpoints ────────────────────────────────────────────────────────
app.get('/online-users', requireSecret, (req, res) => {
  const users = [...onlineUsers.entries()].map(([uid, sockets]) => ({
    uid,
    connections: sockets.size,
  }));
  res.json({ count: users.length, users });
});

app.get('/health', (_req, res) => {
  res.json({
    status:    'ok',
    connected: io.sockets.sockets.size,
    uptime:    process.uptime(),
    time:      new Date().toISOString(),
  });
});

// ─── Start ────────────────────────────────────────────────────────────────────
const PORT = process.env.PORT || 3001;
server.listen(PORT, () => {
  console.log(`\n🩸 LifeLink Notification Server v2`);
  console.log(`   Socket.io + FCM running on port ${PORT}`);
  console.log(`   New endpoints: /notify/new-campaign, /notify/emergency-alert`);
  console.log(`   Allowed origins: ${ALLOWED_ORIGINS.join(', ')}\n`);
});