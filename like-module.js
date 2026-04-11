/*!
 * like-module.js
 * Injectable Instagram-like heart system (no backend).
 */
(function () {
  "use strict";

  var MODULE_KEY = "__INJECTABLE_LIKE_MODULE__";
  if (window[MODULE_KEY]) return;
  window[MODULE_KEY] = true;

  var STORAGE_KEY = "injectable.likes.v1";
  var POST_SELECTOR = ".post";
  var ATTR_BTN = "data-ilm-like-btn";
  var ATTR_COUNT = "data-ilm-like-count";
  var ATTR_POST_ID = "data-ilm-post-id";

  var state = {
    likes: {}, // { [postId]: { liked: boolean, count: number } }
    observer: null,
  };

  function parseJson(value, fallback) {
    try {
      var parsed = JSON.parse(value);
      return parsed && typeof parsed === "object" ? parsed : fallback;
    } catch (e) {
      return fallback;
    }
  }

  function loadState() {
    state.likes = parseJson(localStorage.getItem(STORAGE_KEY) || "{}", {});
  }

  function saveState() {
    try {
      localStorage.setItem(STORAGE_KEY, JSON.stringify(state.likes));
    } catch (e) {}
  }

  function ensureStyles() {
    if (document.getElementById("ilm-styles")) return;
    var style = document.createElement("style");
    style.id = "ilm-styles";
    style.textContent =
      ".ilm-like-wrap{position:absolute;right:10px;bottom:10px;z-index:20;display:flex;align-items:center;gap:8px}" +
      ".ilm-like-btn{border:0;background:rgba(0,0,0,.5);color:#fff;width:40px;height:40px;border-radius:999px;display:grid;place-items:center;cursor:pointer;font-size:20px;line-height:1;backdrop-filter:blur(3px);transition:transform .18s ease,background .18s ease,color .18s ease}" +
      ".ilm-like-btn:hover{transform:scale(1.06)}" +
      ".ilm-like-btn.liked{color:#ff3b6b;background:rgba(0,0,0,.7)}" +
      ".ilm-like-btn.pop{transform:scale(1.28)}" +
      ".ilm-like-count{min-width:28px;height:28px;padding:0 8px;border-radius:999px;background:rgba(0,0,0,.5);color:#fff;display:flex;align-items:center;justify-content:center;font:600 12px/1 system-ui,-apple-system,Segoe UI,Roboto,sans-serif}";
    document.head.appendChild(style);
  }

  function getPostId(post, index) {
    var existing = post.getAttribute(ATTR_POST_ID);
    if (existing) return existing;

    var explicit =
      post.getAttribute("data-post-id") ||
      post.id ||
      post.getAttribute("data-id") ||
      "";

    if (!explicit) {
      var basis = (post.textContent || "").slice(0, 120) + "|" + index;
      explicit = "post_" + hash(basis);
    }
    post.setAttribute(ATTR_POST_ID, explicit);
    return explicit;
  }

  function hash(input) {
    var h = 0;
    for (var i = 0; i < input.length; i++) {
      h = (h << 5) - h + input.charCodeAt(i);
      h |= 0;
    }
    return Math.abs(h).toString(36);
  }

  function getLikeEntry(postId) {
    if (!state.likes[postId]) {
      state.likes[postId] = { liked: false, count: 0 };
    }
    return state.likes[postId];
  }

  function ensurePostOverlay(post, idx) {
    if (!(post instanceof HTMLElement)) return;
    if (post.querySelector("[" + ATTR_BTN + "]")) return;

    if (window.getComputedStyle(post).position === "static") {
      post.style.position = "relative";
    }

    var postId = getPostId(post, idx);
    var entry = getLikeEntry(postId);

    var wrap = document.createElement("div");
    wrap.className = "ilm-like-wrap";

    var count = document.createElement("span");
    count.className = "ilm-like-count";
    count.setAttribute(ATTR_COUNT, "1");
    count.textContent = String(entry.count || 0);

    var btn = document.createElement("button");
    btn.type = "button";
    btn.className = "ilm-like-btn" + (entry.liked ? " liked" : "");
    btn.setAttribute(ATTR_BTN, "1");
    btn.setAttribute("data-ilm-post-id", postId);
    btn.setAttribute("aria-pressed", entry.liked ? "true" : "false");
    btn.setAttribute("aria-label", entry.liked ? "Unlike" : "Like");
    btn.textContent = entry.liked ? "♥" : "♡";

    wrap.appendChild(count);
    wrap.appendChild(btn);
    post.appendChild(wrap);
  }

  function renderPosts() {
    var posts = document.querySelectorAll(POST_SELECTOR);
    for (var i = 0; i < posts.length; i++) {
      ensurePostOverlay(posts[i], i);
    }
  }

  function initPostElement(post) {
    if (!(post instanceof HTMLElement)) return;
    ensurePostOverlay(post, -1);
  }

  function processAddedNodes(nodeList) {
    for (var i = 0; i < nodeList.length; i++) {
      var node = nodeList[i];
      if (!(node instanceof Element)) continue;

      if (node.matches && node.matches(POST_SELECTOR)) {
        initPostElement(node);
      }

      if (node.querySelectorAll) {
        var nestedPosts = node.querySelectorAll(POST_SELECTOR);
        for (var j = 0; j < nestedPosts.length; j++) {
          initPostElement(nestedPosts[j]);
        }
      }
    }
  }

  function updateButtonVisual(btn, entry) {
    btn.classList.toggle("liked", !!entry.liked);
    btn.textContent = entry.liked ? "♥" : "♡";
    btn.setAttribute("aria-pressed", entry.liked ? "true" : "false");
    btn.setAttribute("aria-label", entry.liked ? "Unlike" : "Like");
  }

  function updateCountVisual(post, count) {
    var el = post.querySelector("[" + ATTR_COUNT + "]");
    if (el) el.textContent = String(count);
  }

  function animatePop(btn) {
    btn.classList.remove("pop");
    void btn.offsetWidth;
    btn.classList.add("pop");
    setTimeout(function () {
      btn.classList.remove("pop");
    }, 180);
  }

  function toggleLike(post, btn, forceLike) {
    var postId = btn.getAttribute("data-ilm-post-id");
    if (!postId) return;
    var entry = getLikeEntry(postId);

    var nextLiked = typeof forceLike === "boolean" ? forceLike : !entry.liked;
    if (nextLiked === entry.liked && typeof forceLike === "boolean") return;

    if (nextLiked) {
      entry.liked = true;
      entry.count = (entry.count || 0) + 1;
      animatePop(btn);
    } else {
      entry.liked = false;
      entry.count = Math.max(0, (entry.count || 0) - 1);
    }

    saveState();
    updateButtonVisual(btn, entry);
    updateCountVisual(post, entry.count);
  }

  function bindDelegation() {
    document.addEventListener(
      "click",
      function (e) {
        var target = e.target;
        if (!(target instanceof Element)) return;
        var btn = target.closest("[" + ATTR_BTN + "]");
        if (!btn) return;
        var post = btn.closest(POST_SELECTOR);
        if (!post) return;
        e.preventDefault();
        toggleLike(post, btn);
      },
      true
    );

    document.addEventListener(
      "dblclick",
      function (e) {
        var target = e.target;
        if (!(target instanceof Element)) return;
        var post = target.closest(POST_SELECTOR);
        if (!post) return;
        var btn = post.querySelector("[" + ATTR_BTN + "]");
        if (!btn) return;
        toggleLike(post, btn, true);
      },
      true
    );
  }

  function createPostObserver(targetRoot) {
    if (!(targetRoot instanceof Node)) return null;

    return new MutationObserver(function (mutations) {
      for (var i = 0; i < mutations.length; i++) {
        var added = mutations[i].addedNodes;
        if (!added || !added.length) continue;
        processAddedNodes(added);
      }
    });
  }

  function setupObserver() {
    if (state.observer) return;
    state.observer = createPostObserver(document.body);
    if (!state.observer) return;
    state.observer.observe(document.body, { childList: true, subtree: true });
  }

  function init() {
    ensureStyles();
    loadState();
    renderPosts();
    bindDelegation();
    setupObserver();
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init, { once: true });
  } else {
    init();
  }

  window.InjectedLikeObserver = {
    create: createPostObserver,
    initPost: initPostElement,
  };
})();

