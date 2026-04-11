/*!
 * follow-overlay-module.js
 * Overlay follow/search/personalized-feed system (no backend changes in main app).
 *
 * Optional external API:
 *   window.FOLLOW_OVERLAY_API_BASE = "https://your-follow-api.example.com";
 */
(function () {
  "use strict";

  var MODULE_KEY = "__FOLLOW_OVERLAY_MODULE__";
  if (window[MODULE_KEY]) return;
  window[MODULE_KEY] = true;

  var CFG = {
    apiBase: (window.FOLLOW_OVERLAY_API_BASE || "").replace(/\/+$/, ""),
    postSelector: ".post, .post-block, article[data-post-id]",
    storageKey: "fo.overlay.relationships.v1",
    storageUsersKey: "fo.overlay.users.v1",
  };

  var state = {
    currentUserId: "",
    followingSet: {},
    usersCache: {},
    feedFilterFollowingOnly: false,
    observer: null,
  };

  function jsonParse(raw, fallback) {
    try {
      var p = JSON.parse(raw);
      return p && typeof p === "object" ? p : fallback;
    } catch (_) {
      return fallback;
    }
  }

  function storageGet(key, fallback) {
    try {
      var raw = localStorage.getItem(key);
      return raw ? jsonParse(raw, fallback) : fallback;
    } catch (_) {
      return fallback;
    }
  }

  function storageSet(key, value) {
    try {
      localStorage.setItem(key, JSON.stringify(value));
    } catch (_) {}
  }

  function ensureStyles() {
    if (document.getElementById("fo-overlay-styles")) return;
    var style = document.createElement("style");
    style.id = "fo-overlay-styles";
    style.textContent =
      ".fo-follow-btn{display:inline-flex;align-items:center;justify-content:center;border:1px solid #333;background:#151515;color:#fff;border-radius:999px;padding:7px 14px;font:600 12px/1.2 system-ui,-apple-system,Segoe UI,Roboto,sans-serif;cursor:pointer;transition:all .16s ease}" +
      ".fo-follow-btn:hover{border-color:#555}" +
      ".fo-follow-btn.following{background:#1f3f28;border-color:#2f6b3f;color:#b9f5c6}" +
      ".fo-search-wrap{position:fixed;right:12px;top:66px;z-index:2147483000;width:min(340px,calc(100vw - 24px));background:#0f0f0f;border:1px solid #232323;border-radius:12px;box-shadow:0 10px 30px rgba(0,0,0,.4);padding:10px}" +
      ".fo-search-title{color:#d9d9d9;font:700 12px/1 system-ui,-apple-system,Segoe UI,Roboto,sans-serif;margin-bottom:8px;letter-spacing:.04em}" +
      ".fo-search-input{width:100%;height:36px;border-radius:10px;border:1px solid #303030;background:#181818;color:#fff;padding:0 12px;outline:none}" +
      ".fo-search-list{max-height:280px;overflow:auto;margin-top:8px;display:grid;gap:6px}" +
      ".fo-search-item{display:flex;align-items:center;justify-content:space-between;gap:8px;background:#141414;border:1px solid #252525;border-radius:10px;padding:8px 10px}" +
      ".fo-search-name{color:#f2f2f2;font:500 12px/1.2 system-ui,-apple-system,Segoe UI,Roboto,sans-serif;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}" +
      ".fo-feed-toggle{position:fixed;left:12px;top:66px;z-index:2147483000;height:34px;border-radius:999px;border:1px solid #2d2d2d;background:#121212;color:#fff;padding:0 12px;font:600 12px/1 system-ui,-apple-system,Segoe UI,Roboto,sans-serif;cursor:pointer}" +
      ".fo-feed-toggle.on{background:#243148;border-color:#425e91;color:#d8e5ff}" +
      ".fo-post-hidden{display:none !important}";
    document.head.appendChild(style);
  }

  function getCurrentUserId() {
    var body = document.body;
    var fromData =
      (body && body.getAttribute("data-user-id")) ||
      (body && body.getAttribute("data-current-user-id")) ||
      "";
    if (fromData) return fromData;

    var meta =
      document.querySelector('meta[name="user-id"]') ||
      document.querySelector('meta[name="current-user-id"]');
    if (meta && meta.getAttribute("content")) return meta.getAttribute("content");

    try {
      var ss = sessionStorage.getItem("user_id");
      if (ss) return ss;
    } catch (_) {}

    return "overlay-current-user";
  }

  function parseUserIdFromHref(href) {
    if (!href) return "";
    var m = href.match(/[?&](?:id|user_id)=([^&]+)/i);
    return m ? decodeURIComponent(m[1]) : "";
  }

  function discoverUsersFromDOM() {
    var users = {};

    var anchors = document.querySelectorAll('a[href*="profile/view.php?id="], a[data-user-id]');
    for (var i = 0; i < anchors.length; i++) {
      var a = anchors[i];
      var uid = a.getAttribute("data-user-id") || parseUserIdFromHref(a.getAttribute("href") || "");
      if (!uid) continue;
      var username = (a.textContent || "").trim().replace(/^@/, "") || uid;
      users[uid] = users[uid] || { id: uid, username: username, avatar: "" };
      if (username && username.length <= 60) users[uid].username = username;
    }

    var nameEls = document.querySelectorAll(".post-head-name,[data-author-name]");
    for (var j = 0; j < nameEls.length; j++) {
      var el = nameEls[j];
      var nearestPost = el.closest(CFG.postSelector);
      if (!nearestPost) continue;
      var uid2 = nearestPost.getAttribute("data-user-id") || "";
      if (!uid2) continue;
      users[uid2] = users[uid2] || { id: uid2, username: uid2, avatar: "" };
      var nm = (el.textContent || "").trim().replace(/^@/, "");
      if (nm) users[uid2].username = nm;
    }

    return users;
  }

  var api = (function () {
    function endpoint(path) {
      return CFG.apiBase + path;
    }

    async function fetchJSON(path, opts) {
      var r = await fetch(endpoint(path), opts || {});
      if (!r.ok) throw new Error("API " + r.status);
      return await r.json();
    }

    function localStoreGet() {
      return storageGet(CFG.storageKey, { follows: {} });
    }

    function localStoreSet(val) {
      storageSet(CFG.storageKey, val);
    }

    function localGetFollowing(followerId) {
      var s = localStoreGet();
      var list = Array.isArray(s.follows[followerId]) ? s.follows[followerId] : [];
      return list.slice();
    }

    function localFollow(followerId, followeeId) {
      var s = localStoreGet();
      if (!Array.isArray(s.follows[followerId])) s.follows[followerId] = [];
      if (s.follows[followerId].indexOf(followeeId) < 0) s.follows[followerId].push(followeeId);
      localStoreSet(s);
      return s.follows[followerId].slice();
    }

    function localUnfollow(followerId, followeeId) {
      var s = localStoreGet();
      if (!Array.isArray(s.follows[followerId])) s.follows[followerId] = [];
      s.follows[followerId] = s.follows[followerId].filter(function (id) {
        return id !== followeeId;
      });
      localStoreSet(s);
      return s.follows[followerId].slice();
    }

    return {
      async getFollowing(followerId) {
        if (CFG.apiBase) {
          var d = await fetchJSON("/follow/list?followerId=" + encodeURIComponent(followerId));
          return Array.isArray(d.following) ? d.following : [];
        }
        return localGetFollowing(followerId);
      },
      async follow(followerId, followeeId) {
        if (CFG.apiBase) {
          var d = await fetchJSON("/follow", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ followerId: followerId, followeeId: followeeId }),
          });
          return Array.isArray(d.following) ? d.following : [];
        }
        return localFollow(followerId, followeeId);
      },
      async unfollow(followerId, followeeId) {
        if (CFG.apiBase) {
          var d = await fetchJSON("/follow", {
            method: "DELETE",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ followerId: followerId, followeeId: followeeId }),
          });
          return Array.isArray(d.following) ? d.following : [];
        }
        return localUnfollow(followerId, followeeId);
      },
      async searchUsers(query, localUsersObj) {
        if (CFG.apiBase) {
          var d = await fetchJSON("/users/search?q=" + encodeURIComponent(query) + "&limit=20");
          return Array.isArray(d.users) ? d.users : [];
        }
        var users = Object.values(localUsersObj || {});
        var q = String(query || "").trim().toLowerCase();
        if (!q) return users.slice(0, 20);
        return users
          .filter(function (u) {
            return (
              String(u.username || "").toLowerCase().indexOf(q) >= 0 ||
              String(u.id || "").toLowerCase().indexOf(q) >= 0
            );
          })
          .slice(0, 20);
      },
    };
  })();

  function setFollowingList(ids) {
    state.followingSet = {};
    (ids || []).forEach(function (id) {
      state.followingSet[id] = true;
    });
  }

  function isFollowing(userId) {
    return !!state.followingSet[userId];
  }

  function createFollowButton(userId) {
    var btn = document.createElement("button");
    btn.type = "button";
    btn.className = "fo-follow-btn" + (isFollowing(userId) ? " following" : "");
    btn.setAttribute("data-fo-follow-btn", "1");
    btn.setAttribute("data-fo-user-id", userId);
    btn.textContent = isFollowing(userId) ? "Seguindo" : "Seguir";
    return btn;
  }

  function renderFollowButtons() {
    var profileUserId =
      parseUserIdFromHref(location.href) ||
      (document.body && document.body.getAttribute("data-profile-user-id")) ||
      "";

    if (!profileUserId || profileUserId === state.currentUserId) return;

    if (document.querySelector('[data-fo-follow-btn][data-fo-user-id="' + profileUserId + '"]')) return;

    var anchor =
      document.querySelector(".profile-header, .profile-top, h1, h2, .post-head-name") ||
      document.body.firstElementChild;
    if (!anchor || !anchor.parentNode) return;

    var btn = createFollowButton(profileUserId);
    anchor.parentNode.insertBefore(btn, anchor.nextSibling);
  }

  function updateAllFollowButtons() {
    var all = document.querySelectorAll("[data-fo-follow-btn]");
    for (var i = 0; i < all.length; i++) {
      var b = all[i];
      var uid = b.getAttribute("data-fo-user-id") || "";
      var following = isFollowing(uid);
      b.classList.toggle("following", following);
      b.textContent = following ? "Seguindo" : "Seguir";
    }
  }

  function getPostUserId(post) {
    if (!(post instanceof Element)) return "";
    return (
      post.getAttribute("data-user-id") ||
      parseUserIdFromHref(
        ((post.querySelector('a[href*="profile/view.php?id="]') || {}).getAttribute || function () {
          return "";
        }).call(post.querySelector('a[href*="profile/view.php?id="]'), "href")
      ) ||
      ""
    );
  }

  function applyFeedFilter() {
    var posts = document.querySelectorAll(CFG.postSelector);
    for (var i = 0; i < posts.length; i++) {
      var p = posts[i];
      var uid = getPostUserId(p);
      var keep = true;
      if (state.feedFilterFollowingOnly) {
        keep = !uid || uid === state.currentUserId || isFollowing(uid);
      }
      p.classList.toggle("fo-post-hidden", !keep);
    }
  }

  function renderFeedToggle() {
    if (document.querySelector("[data-fo-feed-toggle]")) return;
    var btn = document.createElement("button");
    btn.type = "button";
    btn.className = "fo-feed-toggle" + (state.feedFilterFollowingOnly ? " on" : "");
    btn.setAttribute("data-fo-feed-toggle", "1");
    btn.textContent = state.feedFilterFollowingOnly ? "Feed: Seguindo" : "Feed: Todos";
    document.body.appendChild(btn);
  }

  function renderSearchOverlay() {
    if (document.querySelector("[data-fo-search-wrap]")) return;
    var wrap = document.createElement("section");
    wrap.className = "fo-search-wrap";
    wrap.setAttribute("data-fo-search-wrap", "1");
    wrap.innerHTML =
      '<div class="fo-search-title">PESSOAS</div>' +
      '<input class="fo-search-input" data-fo-search-input="1" type="search" placeholder="Buscar usuários..." />' +
      '<div class="fo-search-list" data-fo-search-list="1"></div>';
    document.body.appendChild(wrap);
  }

  async function renderSearchResults(query) {
    var list = document.querySelector("[data-fo-search-list]");
    if (!list) return;
    var users = await api.searchUsers(query, state.usersCache);
    list.innerHTML = "";
    users
      .filter(function (u) {
        return u && u.id && u.id !== state.currentUserId;
      })
      .forEach(function (u) {
        var item = document.createElement("div");
        item.className = "fo-search-item";
        var name = document.createElement("div");
        name.className = "fo-search-name";
        name.textContent = "@" + (u.username || u.id);
        item.appendChild(name);
        item.appendChild(createFollowButton(u.id));
        list.appendChild(item);
      });
  }

  function debounce(fn, wait) {
    var t = null;
    return function () {
      var args = arguments;
      clearTimeout(t);
      t = setTimeout(function () {
        fn.apply(null, args);
      }, wait);
    };
  }

  function bindEvents() {
    document.addEventListener(
      "click",
      async function (e) {
        var target = e.target;
        if (!(target instanceof Element)) return;

        var followBtn = target.closest("[data-fo-follow-btn]");
        if (followBtn) {
          e.preventDefault();
          var uid = followBtn.getAttribute("data-fo-user-id") || "";
          if (!uid) return;
          if (isFollowing(uid)) {
            setFollowingList(await api.unfollow(state.currentUserId, uid));
          } else {
            setFollowingList(await api.follow(state.currentUserId, uid));
          }
          updateAllFollowButtons();
          applyFeedFilter();
          return;
        }

        var toggle = target.closest("[data-fo-feed-toggle]");
        if (toggle) {
          e.preventDefault();
          state.feedFilterFollowingOnly = !state.feedFilterFollowingOnly;
          toggle.classList.toggle("on", state.feedFilterFollowingOnly);
          toggle.textContent = state.feedFilterFollowingOnly ? "Feed: Seguindo" : "Feed: Todos";
          applyFeedFilter();
        }
      },
      true
    );

    var searchInput = document.querySelector("[data-fo-search-input]");
    if (searchInput) {
      var onSearch = debounce(function (value) {
        renderSearchResults(value || "");
      }, 220);
      searchInput.addEventListener("input", function () {
        onSearch(searchInput.value);
      });
      onSearch("");
    }
  }

  function hydrateUserCache() {
    state.usersCache = storageGet(CFG.storageUsersKey, {});
    var discovered = discoverUsersFromDOM();
    Object.keys(discovered).forEach(function (id) {
      state.usersCache[id] = discovered[id];
    });
    storageSet(CFG.storageUsersKey, state.usersCache);
  }

  function setupObserver() {
    if (state.observer) return;
    state.observer = new MutationObserver(function (mutations) {
      var changed = false;
      for (var i = 0; i < mutations.length; i++) {
        if (mutations[i].addedNodes && mutations[i].addedNodes.length) {
          changed = true;
          break;
        }
      }
      if (!changed) return;
      hydrateUserCache();
      renderFollowButtons();
      updateAllFollowButtons();
      applyFeedFilter();
    });
    state.observer.observe(document.body, { childList: true, subtree: true });
  }

  async function init() {
    ensureStyles();
    state.currentUserId = getCurrentUserId();
    hydrateUserCache();
    setFollowingList(await api.getFollowing(state.currentUserId));

    renderFollowButtons();
    renderFeedToggle();
    renderSearchOverlay();
    updateAllFollowButtons();
    applyFeedFilter();
    bindEvents();
    setupObserver();
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init, { once: true });
  } else {
    init();
  }
})();

