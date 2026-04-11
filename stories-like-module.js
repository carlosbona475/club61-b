/*!
 * stories-like-module.js
 * Injectable Stories + Likes (pure JS, no backend dependency)
 */
(function () {
  "use strict";

  var MODULE_KEY = "__INJECTABLE_STORIES_LIKES_MODULE__";
  if (window[MODULE_KEY]) return;
  window[MODULE_KEY] = true;

  var config = {
    postSelector: ".post",
    storiesViewedKey: "islm.stories.viewed.v1",
    likesKey: "islm.likes.v1",
  };

  function parseJSON(value, fallback) {
    try {
      var parsed = JSON.parse(value);
      return parsed && typeof parsed === "object" ? parsed : fallback;
    } catch (e) {
      return fallback;
    }
  }

  function safeSetStorage(key, value) {
    try {
      localStorage.setItem(key, JSON.stringify(value));
    } catch (e) {}
  }

  var stylesModule = (function () {
    var injected = false;

    function ensure() {
      if (injected) return;
      injected = true;
      var style = document.createElement("style");
      style.id = "islm-styles";
      style.textContent =
        "/* Stories */" +
        ".islm-stories-wrap{position:fixed;left:0;right:0;top:0;z-index:2147483000;background:rgba(10,10,10,.96);border-bottom:1px solid rgba(255,255,255,.08);padding:10px 8px 8px;backdrop-filter:blur(8px)}" +
        ".islm-stories-scroll{display:flex;gap:12px;overflow-x:auto;overflow-y:hidden;scrollbar-width:none;-webkit-overflow-scrolling:touch}" +
        ".islm-stories-scroll::-webkit-scrollbar{display:none}" +
        ".islm-story{flex:0 0 auto;width:70px;background:none;border:0;color:#fff;padding:0;cursor:pointer;text-align:center}" +
        ".islm-ring{width:64px;height:64px;border-radius:50%;padding:2px;background:linear-gradient(135deg,#f09433,#dc2743,#bc1888,#7b2eff);margin:0 auto}" +
        ".islm-ring.viewed{background:#555}" +
        ".islm-avatar{width:100%;height:100%;border-radius:50%;object-fit:cover;border:2px solid #0a0a0a;background:#222;display:block}" +
        ".islm-name{margin-top:6px;font:500 11px/1.2 system-ui,-apple-system,Segoe UI,Roboto,sans-serif;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}" +
        ".islm-modal{position:fixed;inset:0;background:#000;z-index:2147483640;display:none;opacity:0;transition:opacity .2s ease}" +
        ".islm-modal.open{display:block;opacity:1}" +
        ".islm-top{position:absolute;left:10px;right:10px;top:10px;z-index:3}" +
        ".islm-progress{display:flex;gap:4px}" +
        ".islm-seg{height:3px;flex:1;background:rgba(255,255,255,.35);border-radius:2px;overflow:hidden}" +
        ".islm-fill{height:100%;width:0;background:#fff}" +
        ".islm-head{margin-top:8px;display:flex;align-items:center;gap:8px;color:#fff}" +
        ".islm-head-avatar{width:28px;height:28px;border-radius:50%;object-fit:cover;background:#222}" +
        ".islm-head-name{font:600 13px/1.2 system-ui,-apple-system,Segoe UI,Roboto,sans-serif}" +
        ".islm-head-time{margin-left:2px;opacity:.72;font:500 11px/1.2 system-ui,-apple-system,Segoe UI,Roboto,sans-serif}" +
        ".islm-close{margin-left:auto;background:none;border:0;color:#fff;font-size:30px;line-height:1;cursor:pointer;padding:0 6px}" +
        ".islm-media{position:absolute;inset:0;display:flex;align-items:center;justify-content:center}" +
        ".islm-media img,.islm-media video{max-width:100%;max-height:100%;object-fit:contain;display:block;opacity:0;transform:translateX(12px) scale(.99);transition:opacity .24s ease,transform .24s ease;will-change:opacity,transform}" +
        ".islm-media .ready{opacity:1;transform:translateX(0) scale(1)}" +
        ".islm-nav{position:absolute;top:0;bottom:0;width:34%;background:none;border:0;z-index:2;cursor:pointer}" +
        ".islm-prev{left:0}.islm-next{right:0}" +
        "/* Likes */" +
        ".islm-like-wrap{position:absolute;right:10px;bottom:10px;z-index:20;display:flex;align-items:center;gap:8px}" +
        ".islm-like-btn{border:0;background:rgba(0,0,0,.5);color:#fff;width:40px;height:40px;border-radius:999px;display:grid;place-items:center;cursor:pointer;font-size:20px;line-height:1;backdrop-filter:blur(3px);transition:transform .18s ease,background .18s ease,color .18s ease}" +
        ".islm-like-btn:hover{transform:scale(1.06)}" +
        ".islm-like-btn.liked{color:#ff3b6b;background:rgba(0,0,0,.7)}" +
        ".islm-like-btn.pop{transform:scale(1.25)}" +
        ".islm-like-count{min-width:28px;height:28px;padding:0 8px;border-radius:999px;background:rgba(0,0,0,.5);color:#fff;display:flex;align-items:center;justify-content:center;font:600 12px/1 system-ui,-apple-system,Segoe UI,Roboto,sans-serif}" +
        "@media (min-width:768px){.islm-stories-wrap{max-width:620px;margin:0 auto;left:0;right:0;border-left:1px solid rgba(255,255,255,.08);border-right:1px solid rgba(255,255,255,.08)}}" +
        "@media (prefers-reduced-motion:reduce){.islm-media img,.islm-media video,.islm-like-btn{transition:none!important}}";
      document.head.appendChild(style);
    }

    return { ensure: ensure };
  })();

  var storiesModule = (function () {
    var mockStories = [
      {
        id: "s1",
        username: "alex",
        createdAt: Date.now() - 3 * 60 * 1000,
        avatar:
          "https://images.unsplash.com/photo-1500648767791-00dcc994a43e?auto=format&fit=crop&w=120&q=60",
        items: [
          {
            type: "image",
            src: "https://images.unsplash.com/photo-1519125323398-675f0ddb6308?auto=format&fit=crop&w=1080&q=80",
            duration: 5000,
          },
          {
            type: "video",
            src: "https://interactive-examples.mdn.mozilla.net/media/cc0-videos/flower.mp4",
            duration: 8000,
          },
        ],
      },
      {
        id: "s2",
        username: "sophia",
        createdAt: Date.now() - 45 * 60 * 1000,
        avatar:
          "https://images.unsplash.com/photo-1494790108377-be9c29b29330?auto=format&fit=crop&w=120&q=60",
        items: [
          {
            type: "image",
            src: "https://images.unsplash.com/photo-1521572267360-ee0c2909d518?auto=format&fit=crop&w=1080&q=80",
            duration: 5000,
          },
        ],
      },
      {
        id: "s3",
        username: "maria",
        createdAt: Date.now() - 2 * 60 * 60 * 1000,
        avatar:
          "https://images.unsplash.com/photo-1531123897727-8f129e1688ce?auto=format&fit=crop&w=120&q=60",
        items: [
          {
            type: "image",
            src: "https://images.unsplash.com/photo-1469474968028-56623f02e42e?auto=format&fit=crop&w=1080&q=80",
            duration: 5000,
          },
        ],
      },
    ];

    var state = {
      viewed: {},
      stories: mockStories,
      currentStory: 0,
      currentItem: 0,
      timer: null,
      open: false,
      touchX: 0,
      touchY: 0,
    };

    var refs = {};

    function loadViewed() {
      state.viewed = parseJSON(
        localStorage.getItem(config.storiesViewedKey) || "{}",
        {}
      );
    }

    function saveViewed() {
      safeSetStorage(config.storiesViewedKey, state.viewed);
    }

    function clearTimer() {
      if (state.timer) {
        clearTimeout(state.timer);
        state.timer = null;
      }
    }

    function ensureDOM() {
      if (refs.wrap) return;
      refs.wrap = document.createElement("section");
      refs.wrap.className = "islm-stories-wrap";
      refs.wrap.setAttribute("aria-label", "Stories");
      refs.wrap.innerHTML =
        '<div class="islm-stories-scroll" data-islm-stories-list="1"></div>' +
        '<div class="islm-modal" data-islm-modal="1">' +
        '  <div class="islm-top">' +
        '    <div class="islm-progress" data-islm-progress="1"></div>' +
        '    <div class="islm-head">' +
        '      <img class="islm-head-avatar" data-islm-head-avatar="1" alt="">' +
        '      <span class="islm-head-name" data-islm-head-name="1"></span>' +
        '      <span class="islm-head-time" data-islm-head-time="1"></span>' +
        '      <button type="button" class="islm-close" data-islm-close="1" aria-label="Close">×</button>' +
        "    </div>" +
        "  </div>" +
        '  <div class="islm-media" data-islm-media="1"></div>' +
        '  <button type="button" class="islm-nav islm-prev" data-islm-prev="1" aria-label="Previous"></button>' +
        '  <button type="button" class="islm-nav islm-next" data-islm-next="1" aria-label="Next"></button>' +
        "</div>";
      document.body.appendChild(refs.wrap);
      refs.list = refs.wrap.querySelector("[data-islm-stories-list]");
      refs.modal = refs.wrap.querySelector("[data-islm-modal]");
      refs.progress = refs.wrap.querySelector("[data-islm-progress]");
      refs.media = refs.wrap.querySelector("[data-islm-media]");
      refs.headAvatar = refs.wrap.querySelector("[data-islm-head-avatar]");
      refs.headName = refs.wrap.querySelector("[data-islm-head-name]");
      refs.headTime = refs.wrap.querySelector("[data-islm-head-time]");
    }

    function renderBar() {
      ensureDOM();
      refs.list.innerHTML = "";
      state.stories.forEach(function (story, idx) {
        var btn = document.createElement("button");
        btn.type = "button";
        btn.className = "islm-story";
        btn.setAttribute("data-islm-open-story", String(idx));
        btn.innerHTML =
          '<div class="islm-ring ' +
          (state.viewed[story.id] ? "viewed" : "") +
          '"><img class="islm-avatar" loading="lazy" decoding="async" src="' +
          story.avatar +
          '" alt="' +
          story.username +
          '"></div><div class="islm-name">' +
          story.username +
          "</div>";
        refs.list.appendChild(btn);
      });
    }

    function buildProgress(total, active) {
      refs.progress.innerHTML = "";
      for (var i = 0; i < total; i++) {
        var seg = document.createElement("div");
        seg.className = "islm-seg";
        var fill = document.createElement("div");
        fill.className = "islm-fill";
        fill.setAttribute("data-islm-fill", String(i));
        if (i < active) fill.style.width = "100%";
        seg.appendChild(fill);
        refs.progress.appendChild(seg);
      }
    }

    function animateProgress(index, duration) {
      var fill = refs.progress.querySelector('[data-islm-fill="' + index + '"]');
      if (!fill) return;
      fill.style.transition = "none";
      fill.style.width = "0%";
      requestAnimationFrame(function () {
        fill.style.transition = "width " + duration + "ms linear";
        fill.style.width = "100%";
      });
    }

    function close() {
      clearTimer();
      state.open = false;
      refs.modal.classList.remove("open");
      refs.media.innerHTML = "";
    }

    function relativeTime(ts) {
      var now = Date.now();
      var diff = Math.max(0, now - Number(ts || now));
      if (diff < 60 * 1000) return "agora";
      if (diff < 60 * 60 * 1000) return Math.floor(diff / 60000) + "m";
      if (diff < 24 * 60 * 60 * 1000) return Math.floor(diff / 3600000) + "h";
      return Math.floor(diff / 86400000) + "d";
    }

    function getNextIndices(storyIndex, itemIndex) {
      var story = state.stories[storyIndex];
      if (!story) return null;
      if (itemIndex + 1 < story.items.length) {
        return { storyIndex: storyIndex, itemIndex: itemIndex + 1 };
      }
      if (storyIndex + 1 < state.stories.length) {
        return { storyIndex: storyIndex + 1, itemIndex: 0 };
      }
      return null;
    }

    function preloadNext(storyIndex, itemIndex) {
      var nextRef = getNextIndices(storyIndex, itemIndex);
      if (!nextRef) return;
      var nextStory = state.stories[nextRef.storyIndex];
      var nextItem = nextStory && nextStory.items[nextRef.itemIndex];
      if (!nextItem || !nextItem.src) return;

      if (nextItem.type === "video") {
        var v = document.createElement("video");
        v.preload = "metadata";
        v.src = nextItem.src;
      } else {
        var i = new Image();
        i.loading = "lazy";
        i.decoding = "async";
        i.src = nextItem.src;
      }
    }

    function mountMedia(mediaEl) {
      refs.media.appendChild(mediaEl);
      requestAnimationFrame(function () {
        mediaEl.classList.add("ready");
      });
    }

    function next() {
      var story = state.stories[state.currentStory];
      if (!story) return close();
      if (state.currentItem + 1 < story.items.length) {
        show(state.currentStory, state.currentItem + 1);
      } else if (state.currentStory + 1 < state.stories.length) {
        show(state.currentStory + 1, 0);
      } else {
        close();
      }
    }

    function prev() {
      if (state.currentItem > 0) return show(state.currentStory, state.currentItem - 1);
      if (state.currentStory <= 0) return close();
      var prevStory = state.stories[state.currentStory - 1];
      show(state.currentStory - 1, prevStory.items.length - 1);
    }

    function show(storyIndex, itemIndex) {
      var story = state.stories[storyIndex];
      if (!story) return close();
      var item = story.items[itemIndex];
      if (!item) return close();

      state.currentStory = storyIndex;
      state.currentItem = itemIndex;
      state.open = true;
      state.viewed[story.id] = true;
      saveViewed();
      renderBar();

      refs.modal.classList.add("open");
      refs.headAvatar.src = story.avatar;
      refs.headAvatar.loading = "lazy";
      refs.headAvatar.decoding = "async";
      refs.headName.textContent = story.username;
      if (refs.headTime) refs.headTime.textContent = relativeTime(story.createdAt);
      refs.media.innerHTML = "";
      buildProgress(story.items.length, itemIndex);
      clearTimer();
      preloadNext(storyIndex, itemIndex);

      var duration = Number(item.duration || 5000);
      if (item.type === "video") {
        var video = document.createElement("video");
        video.src = item.src;
        video.autoplay = true;
        video.muted = true;
        video.playsInline = true;
        video.controls = false;
        video.preload = "metadata";
        mountMedia(video);
        video.addEventListener(
          "loadedmetadata",
          function () {
            if (isFinite(video.duration) && video.duration > 0) {
              duration = Math.max(1000, Math.round(video.duration * 1000));
            }
            animateProgress(itemIndex, duration);
          },
          { once: true }
        );
        video.addEventListener("ended", next);
        video.play().catch(function () {});
        state.timer = setTimeout(next, duration + 80);
      } else {
        var img = document.createElement("img");
        img.src = item.src;
        img.alt = story.username + " story";
        img.loading = "lazy";
        img.decoding = "async";
        mountMedia(img);
        animateProgress(itemIndex, duration);
        state.timer = setTimeout(next, duration);
      }
    }

    function bindEvents() {
      document.addEventListener(
        "click",
        function (e) {
          var t = e.target;
          if (!(t instanceof Element)) return;
          var openBtn = t.closest("[data-islm-open-story]");
          if (openBtn) {
            var idx = Number(openBtn.getAttribute("data-islm-open-story"));
            if (!isNaN(idx)) show(idx, 0);
            return;
          }
          if (t.closest("[data-islm-close]")) return close();
          if (t.closest("[data-islm-next]")) return next();
          if (t.closest("[data-islm-prev]")) return prev();
          if (state.open && t === refs.modal) return close();
        },
        true
      );

      document.addEventListener(
        "touchstart",
        function (e) {
          if (!state.open || !e.touches || !e.touches[0]) return;
          state.touchX = e.touches[0].clientX;
          state.touchY = e.touches[0].clientY;
        },
        { capture: true, passive: true }
      );

      document.addEventListener(
        "touchend",
        function (e) {
          if (!state.open || !e.changedTouches || !e.changedTouches[0]) return;
          var dx = e.changedTouches[0].clientX - state.touchX;
          var dy = e.changedTouches[0].clientY - state.touchY;
          if (Math.abs(dy) > 70 && Math.abs(dy) > Math.abs(dx)) close();
        },
        { capture: true, passive: true }
      );

      document.addEventListener(
        "keydown",
        function (e) {
          if (!state.open) return;
          if (e.key === "Escape") close();
          if (e.key === "ArrowRight") next();
          if (e.key === "ArrowLeft") prev();
        },
        true
      );
    }

    function init() {
      loadViewed();
      ensureDOM();
      renderBar();
      bindEvents();
    }

    return {
      init: init,
    };
  })();

  var likesModule = (function () {
    var ATTR_BTN = "data-islm-like-btn";
    var ATTR_COUNT = "data-islm-like-count";
    var ATTR_POST_ID = "data-islm-post-id";
    var likes = {};

    function load() {
      likes = parseJSON(localStorage.getItem(config.likesKey) || "{}", {});
    }

    function save() {
      safeSetStorage(config.likesKey, likes);
    }

    function hash(input) {
      var h = 0;
      for (var i = 0; i < input.length; i++) {
        h = (h << 5) - h + input.charCodeAt(i);
        h |= 0;
      }
      return Math.abs(h).toString(36);
    }

    function ensureEntry(postId) {
      if (!likes[postId]) likes[postId] = { liked: false, count: 0 };
      return likes[postId];
    }

    function resolvePostId(post, index) {
      var existing = post.getAttribute(ATTR_POST_ID);
      if (existing) return existing;
      var id =
        post.getAttribute("data-post-id") ||
        post.id ||
        post.getAttribute("data-id") ||
        "";
      if (!id) {
        id = "post_" + hash((post.textContent || "").slice(0, 120) + "|" + index);
      }
      post.setAttribute(ATTR_POST_ID, id);
      return id;
    }

    function attachToPost(post, index) {
      if (!(post instanceof HTMLElement)) return;
      if (post.querySelector("[" + ATTR_BTN + "]")) return;

      if (window.getComputedStyle(post).position === "static") {
        post.style.position = "relative";
      }

      var postId = resolvePostId(post, index);
      var entry = ensureEntry(postId);

      var wrap = document.createElement("div");
      wrap.className = "islm-like-wrap";
      wrap.innerHTML =
        '<span class="islm-like-count" ' +
        ATTR_COUNT +
        '="1">' +
        String(entry.count || 0) +
        '</span><button type="button" class="islm-like-btn' +
        (entry.liked ? " liked" : "") +
        '" ' +
        ATTR_BTN +
        '="1" data-islm-like-post="' +
        postId +
        '" aria-pressed="' +
        (entry.liked ? "true" : "false") +
        '" aria-label="' +
        (entry.liked ? "Unlike" : "Like") +
        '">' +
        (entry.liked ? "♥" : "♡") +
        "</button>";
      post.appendChild(wrap);
    }

    function attachToAll() {
      var posts = document.querySelectorAll(config.postSelector);
      for (var i = 0; i < posts.length; i++) attachToPost(posts[i], i);
    }

    function attachFromNode(node) {
      if (!(node instanceof Element)) return;
      if (node.matches && node.matches(config.postSelector)) attachToPost(node, -1);
      if (!node.querySelectorAll) return;
      var nested = node.querySelectorAll(config.postSelector);
      for (var i = 0; i < nested.length; i++) attachToPost(nested[i], -1);
    }

    function animate(btn) {
      btn.classList.remove("pop");
      void btn.offsetWidth;
      btn.classList.add("pop");
      setTimeout(function () {
        btn.classList.remove("pop");
      }, 180);
    }

    function updateVisual(post, btn, entry) {
      btn.classList.toggle("liked", !!entry.liked);
      btn.textContent = entry.liked ? "♥" : "♡";
      btn.setAttribute("aria-pressed", entry.liked ? "true" : "false");
      btn.setAttribute("aria-label", entry.liked ? "Unlike" : "Like");
      var countEl = post.querySelector("[" + ATTR_COUNT + "]");
      if (countEl) countEl.textContent = String(entry.count);
    }

    function toggle(post, btn, forceLike) {
      var postId = btn.getAttribute("data-islm-like-post");
      if (!postId) return;
      var entry = ensureEntry(postId);
      var nextLiked = typeof forceLike === "boolean" ? forceLike : !entry.liked;
      if (typeof forceLike === "boolean" && nextLiked === entry.liked) return;

      if (nextLiked) {
        entry.liked = true;
        entry.count += 1;
        animate(btn);
      } else {
        entry.liked = false;
        entry.count = Math.max(0, entry.count - 1);
      }
      save();
      updateVisual(post, btn, entry);
    }

    function bindEvents() {
      document.addEventListener(
        "click",
        function (e) {
          var t = e.target;
          if (!(t instanceof Element)) return;
          var btn = t.closest("[" + ATTR_BTN + "]");
          if (!btn) return;
          var post = btn.closest(config.postSelector);
          if (!post) return;
          e.preventDefault();
          toggle(post, btn);
        },
        true
      );

      document.addEventListener(
        "dblclick",
        function (e) {
          var t = e.target;
          if (!(t instanceof Element)) return;
          var post = t.closest(config.postSelector);
          if (!post) return;
          var btn = post.querySelector("[" + ATTR_BTN + "]");
          if (!btn) return;
          toggle(post, btn, true);
        },
        true
      );
    }

    function init() {
      load();
      attachToAll();
      bindEvents();
    }

    return {
      init: init,
      attachFromNode: attachFromNode,
      attachToAll: attachToAll,
    };
  })();

  var observerModule = (function () {
    var observer = null;

    function init() {
      if (observer) return;
      observer = new MutationObserver(function (mutations) {
        for (var i = 0; i < mutations.length; i++) {
          var added = mutations[i].addedNodes;
          if (!added || !added.length) continue;
          for (var j = 0; j < added.length; j++) {
            likesModule.attachFromNode(added[j]);
          }
        }
      });
      observer.observe(document.body, { childList: true, subtree: true });
    }

    return { init: init };
  })();

  function init() {
    stylesModule.ensure();
    storiesModule.init();
    likesModule.init();
    observerModule.init();
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init, { once: true });
  } else {
    init();
  }
})();

