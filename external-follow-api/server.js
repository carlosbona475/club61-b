/**
 * External Follow API (standalone service)
 * Run: node server.js
 */
"use strict";

const http = require("http");
const fs = require("fs");
const path = require("path");
const { URL } = require("url");

const PORT = process.env.PORT ? Number(process.env.PORT) : 8787;
const DATA_DIR = path.join(__dirname, "data");
const DATA_FILE = path.join(DATA_DIR, "follows.json");

function ensureDataFile() {
  if (!fs.existsSync(DATA_DIR)) fs.mkdirSync(DATA_DIR, { recursive: true });
  if (!fs.existsSync(DATA_FILE)) {
    fs.writeFileSync(
      DATA_FILE,
      JSON.stringify({ follows: {}, users: [] }, null, 2),
      "utf8"
    );
  }
}

function readStore() {
  ensureDataFile();
  try {
    const raw = fs.readFileSync(DATA_FILE, "utf8");
    const parsed = JSON.parse(raw);
    return {
      follows: parsed && parsed.follows ? parsed.follows : {},
      users: Array.isArray(parsed && parsed.users) ? parsed.users : [],
    };
  } catch (_) {
    return { follows: {}, users: [] };
  }
}

function writeStore(store) {
  ensureDataFile();
  fs.writeFileSync(DATA_FILE, JSON.stringify(store, null, 2), "utf8");
}

function send(res, status, payload) {
  res.writeHead(status, {
    "Content-Type": "application/json; charset=utf-8",
    "Access-Control-Allow-Origin": "*",
    "Access-Control-Allow-Methods": "GET,POST,DELETE,OPTIONS",
    "Access-Control-Allow-Headers": "Content-Type,Authorization",
  });
  res.end(JSON.stringify(payload));
}

function collectBody(req) {
  return new Promise((resolve, reject) => {
    let data = "";
    req.on("data", (chunk) => {
      data += chunk.toString("utf8");
      if (data.length > 1024 * 1024) {
        reject(new Error("Payload too large"));
      }
    });
    req.on("end", () => resolve(data));
    req.on("error", reject);
  });
}

function uniquePush(arr, value) {
  if (!arr.includes(value)) arr.push(value);
}

function removeValue(arr, value) {
  const idx = arr.indexOf(value);
  if (idx >= 0) arr.splice(idx, 1);
}

function normalizeUser(user) {
  return {
    id: String(user.id || "").trim(),
    username: String(user.username || "").trim(),
    avatar: String(user.avatar || "").trim(),
  };
}

const server = http.createServer(async (req, res) => {
  if (!req.url) return send(res, 400, { ok: false, error: "Invalid URL" });
  const url = new URL(req.url, "http://localhost");
  const pathname = url.pathname;
  const method = req.method || "GET";

  if (method === "OPTIONS") return send(res, 204, { ok: true });

  // Health
  if (pathname === "/health" && method === "GET") {
    return send(res, 200, { ok: true, service: "external-follow-api" });
  }

  // Upsert users (optional helper for search quality)
  if (pathname === "/users/upsert" && method === "POST") {
    try {
      const store = readStore();
      const raw = await collectBody(req);
      const body = raw ? JSON.parse(raw) : {};
      const users = Array.isArray(body.users) ? body.users.map(normalizeUser) : [];
      const valid = users.filter((u) => u.id && u.username);
      const map = {};
      store.users.forEach((u) => {
        if (u && u.id) map[u.id] = normalizeUser(u);
      });
      valid.forEach((u) => {
        map[u.id] = u;
      });
      store.users = Object.values(map);
      writeStore(store);
      return send(res, 200, { ok: true, users: store.users.length });
    } catch (e) {
      return send(res, 400, { ok: false, error: "Invalid JSON body" });
    }
  }

  // Search users
  if (pathname === "/users/search" && method === "GET") {
    const q = String(url.searchParams.get("q") || "").trim().toLowerCase();
    const limit = Math.max(1, Math.min(50, Number(url.searchParams.get("limit") || 20)));
    const store = readStore();
    let users = store.users.slice();
    if (q) {
      users = users.filter(
        (u) =>
          String(u.username || "").toLowerCase().includes(q) ||
          String(u.id || "").toLowerCase().includes(q)
      );
    }
    return send(res, 200, { ok: true, users: users.slice(0, limit) });
  }

  // Get following list
  if (pathname === "/follow/list" && method === "GET") {
    const followerId = String(url.searchParams.get("followerId") || "").trim();
    if (!followerId) return send(res, 400, { ok: false, error: "Missing followerId" });
    const store = readStore();
    const following = Array.isArray(store.follows[followerId]) ? store.follows[followerId] : [];
    return send(res, 200, { ok: true, followerId, following });
  }

  // Follow
  if (pathname === "/follow" && method === "POST") {
    try {
      const raw = await collectBody(req);
      const body = raw ? JSON.parse(raw) : {};
      const followerId = String(body.followerId || "").trim();
      const followeeId = String(body.followeeId || "").trim();
      if (!followerId || !followeeId) {
        return send(res, 400, { ok: false, error: "followerId and followeeId are required" });
      }
      if (followerId === followeeId) {
        return send(res, 400, { ok: false, error: "Cannot follow yourself" });
      }
      const store = readStore();
      if (!Array.isArray(store.follows[followerId])) store.follows[followerId] = [];
      uniquePush(store.follows[followerId], followeeId);
      writeStore(store);
      return send(res, 200, {
        ok: true,
        followerId,
        followeeId,
        following: store.follows[followerId],
      });
    } catch (_) {
      return send(res, 400, { ok: false, error: "Invalid JSON body" });
    }
  }

  // Unfollow
  if (pathname === "/follow" && method === "DELETE") {
    try {
      const raw = await collectBody(req);
      const body = raw ? JSON.parse(raw) : {};
      const followerId = String(body.followerId || "").trim();
      const followeeId = String(body.followeeId || "").trim();
      if (!followerId || !followeeId) {
        return send(res, 400, { ok: false, error: "followerId and followeeId are required" });
      }
      const store = readStore();
      if (!Array.isArray(store.follows[followerId])) store.follows[followerId] = [];
      removeValue(store.follows[followerId], followeeId);
      writeStore(store);
      return send(res, 200, {
        ok: true,
        followerId,
        followeeId,
        following: store.follows[followerId],
      });
    } catch (_) {
      return send(res, 400, { ok: false, error: "Invalid JSON body" });
    }
  }

  return send(res, 404, { ok: false, error: "Not found" });
});

server.listen(PORT, () => {
  // eslint-disable-next-line no-console
  console.log("external-follow-api running on http://localhost:" + PORT);
});

