/*!
 * stories-module.js
 * Injectable Instagram-like Stories (pure JS, Shadow DOM, no backend).
 */
(function () {
  "use strict";

  var MODULE_KEY = "__INJECTABLE_STORIES_MODULE__";
  if (window[MODULE_KEY]) return;
  window[MODULE_KEY] = true;

  var STORAGE_VIEWED_KEY = "injectable.stories.viewed.v1";

  var mockStories = [
    {
      id: "s1",
      username: "alex",
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
    {
      id: "s4",
      username: "jordan",
      avatar:
        "https://images.unsplash.com/photo-1506794778202-cad84cf45f1d?auto=format&fit=crop&w=120&q=60",
      items: [
        {
          type: "video",
          src: "https://interactive-examples.mdn.mozilla.net/media/cc0-videos/flower.webm",
          duration: 7000,
        },
      ],
    },
  ];

  var state = {
    stories: mockStories,
    viewed: {},
    currentStoryIdx: 0,
    currentItemIdx: 0,
    timer: null,
    isOpen: false,
    touchStartX: 0,
    touchStartY: 0,
  };

  var host;
  var root;
  var refs = {};

  function loadViewed() {
    try {
      var raw = localStorage.getItem(STORAGE_VIEWED_KEY);
      if (!raw) return;
      var parsed = JSON.parse(raw);
      if (parsed && typeof parsed === "object") state.viewed = parsed;
    } catch (e) {}
  }

  function saveViewed() {
    try {
      localStorage.setItem(STORAGE_VIEWED_KEY, JSON.stringify(state.viewed));
    } catch (e) {}
  }

  function createBase() {
    host = document.createElement("div");
    host.id = "injectable-stories-host";
    document.documentElement.appendChild(host);
    root = host.attachShadow({ mode: "open" });

    root.innerHTML =
      '<style>' +
      ':host{all:initial}' +
      '.is-wrap{position:fixed;top:0;left:0;right:0;z-index:2147483000;font-family:system-ui,-apple-system,Segoe UI,Roboto,sans-serif;pointer-events:none}' +
      '.bar{pointer-events:auto;margin:0;background:rgba(10,10,10,.96);border-bottom:1px solid rgba(255,255,255,.08);padding:10px 8px 8px;overflow:hidden}' +
      '.scroll{display:flex;gap:12px;overflow-x:auto;overflow-y:hidden;scrollbar-width:none;-webkit-overflow-scrolling:touch}' +
      '.scroll::-webkit-scrollbar{display:none}' +
      '.story{background:none;border:0;color:#fff;flex:0 0 auto;width:70px;padding:0;cursor:pointer;text-align:center}' +
      '.ring{width:64px;height:64px;border-radius:50%;padding:2px;margin:0 auto;background:linear-gradient(135deg,#f09433,#dc2743,#bc1888,#7b2eff)}' +
      '.ring.viewed{background:#555}' +
      '.avatar{width:100%;height:100%;border-radius:50%;object-fit:cover;border:2px solid #0a0a0a;background:#1a1a1a;display:block}' +
      '.name{margin-top:6px;font-size:11px;line-height:1.2;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}' +
      '.modal{position:fixed;inset:0;background:#000;display:none;pointer-events:auto;touch-action:pan-y;opacity:0;transition:opacity .2s ease}' +
      '.modal.open{display:block;opacity:1}' +
      '.top{position:absolute;top:10px;left:10px;right:10px;z-index:3}' +
      '.progress{display:flex;gap:4px}' +
      '.pseg{height:3px;background:rgba(255,255,255,.35);flex:1;border-radius:2px;overflow:hidden}' +
      '.pfill{height:100%;width:0;background:#fff}' +
      '.head{margin-top:8px;display:flex;align-items:center;gap:8px;color:#fff}' +
      '.head .avatar-sm{width:28px;height:28px;border-radius:50%;object-fit:cover;background:#222}' +
      '.uname{font-size:13px;font-weight:600}' +
      '.close{margin-left:auto;background:none;border:0;color:#fff;font-size:30px;line-height:1;cursor:pointer;padding:0 6px}' +
      '.media-wrap{position:absolute;inset:0;display:flex;align-items:center;justify-content:center}' +
      '.media-wrap img,.media-wrap video{max-width:100%;max-height:100%;object-fit:contain;display:block}' +
      '.nav{position:absolute;top:0;bottom:0;width:34%;background:transparent;border:0;cursor:pointer;z-index:2}' +
      '.nav.prev{left:0}.nav.next{right:0}' +
      '@media (min-width:768px){.bar{max-width:620px;margin:0 auto;border-left:1px solid rgba(255,255,255,.08);border-right:1px solid rgba(255,255,255,.08)}}' +
      "</style>" +
      '<div class="is-wrap">' +
      '  <div class="bar"><div class="scroll" id="scroll"></div></div>' +
      '  <div class="modal" id="modal">' +
      '    <div class="top">' +
      '      <div class="progress" id="progress"></div>' +
      '      <div class="head"><img class="avatar-sm" id="headAvatar" alt=""><span class="uname" id="headName"></span><button class="close" id="closeBtn" aria-label="Close">×</button></div>' +
      "    </div>" +
      '    <div class="media-wrap" id="mediaWrap"></div>' +
      '    <button class="nav prev" id="prevBtn" aria-label="Previous"></button>' +
      '    <button class="nav next" id="nextBtn" aria-label="Next"></button>' +
      "  </div>" +
      "</div>";

    refs.scroll = root.getElementById("scroll");
    refs.modal = root.getElementById("modal");
    refs.progress = root.getElementById("progress");
    refs.mediaWrap = root.getElementById("mediaWrap");
    refs.headAvatar = root.getElementById("headAvatar");
    refs.headName = root.getElementById("headName");
    refs.closeBtn = root.getElementById("closeBtn");
    refs.prevBtn = root.getElementById("prevBtn");
    refs.nextBtn = root.getElementById("nextBtn");
  }

  function renderBar() {
    refs.scroll.innerHTML = "";
    state.stories.forEach(function (story, idx) {
      var btn = document.createElement("button");
      btn.className = "story";
      btn.type = "button";
      btn.setAttribute("data-story-idx", String(idx));
      btn.innerHTML =
        '<div class="ring ' +
        (state.viewed[story.id] ? "viewed" : "") +
        '"><img class="avatar" src="' +
        story.avatar +
        '" alt="' +
        story.username +
        '"></div><div class="name">' +
        story.username +
        "</div>";
      refs.scroll.appendChild(btn);
    });
  }

  function clearTimer() {
    if (state.timer) {
      clearTimeout(state.timer);
      state.timer = null;
    }
  }

  function closeModal() {
    clearTimer();
    state.isOpen = false;
    refs.modal.classList.remove("open");
    refs.mediaWrap.innerHTML = "";
  }

  function buildProgress(total, activeIdx) {
    refs.progress.innerHTML = "";
    for (var i = 0; i < total; i++) {
      var seg = document.createElement("div");
      seg.className = "pseg";
      var fill = document.createElement("div");
      fill.className = "pfill";
      fill.dataset.idx = String(i);
      if (i < activeIdx) fill.style.width = "100%";
      seg.appendChild(fill);
      refs.progress.appendChild(seg);
    }
  }

  function animateActiveProgress(idx, duration) {
    var current = refs.progress.querySelector('.pfill[data-idx="' + idx + '"]');
    if (!current) return;
    current.style.transition = "none";
    current.style.width = "0%";
    requestAnimationFrame(function () {
      current.style.transition = "width " + duration + "ms linear";
      current.style.width = "100%";
    });
  }

  function goNext() {
    var story = state.stories[state.currentStoryIdx];
    if (!story) return closeModal();
    var nextItem = state.currentItemIdx + 1;
    if (nextItem < story.items.length) {
      showItem(state.currentStoryIdx, nextItem);
      return;
    }
    var nextStory = state.currentStoryIdx + 1;
    if (nextStory < state.stories.length) {
      showItem(nextStory, 0);
      return;
    }
    closeModal();
  }

  function goPrev() {
    var prevItem = state.currentItemIdx - 1;
    if (prevItem >= 0) return showItem(state.currentStoryIdx, prevItem);
    var prevStory = state.currentStoryIdx - 1;
    if (prevStory < 0) return closeModal();
    var lastItem = state.stories[prevStory].items.length - 1;
    showItem(prevStory, lastItem);
  }

  function showItem(storyIdx, itemIdx) {
    var story = state.stories[storyIdx];
    if (!story) return closeModal();
    var item = story.items[itemIdx];
    if (!item) return closeModal();

    state.currentStoryIdx = storyIdx;
    state.currentItemIdx = itemIdx;
    state.isOpen = true;
    state.viewed[story.id] = true;
    saveViewed();
    renderBar();

    refs.modal.classList.add("open");
    refs.headAvatar.src = story.avatar;
    refs.headName.textContent = story.username;
    refs.mediaWrap.innerHTML = "";

    buildProgress(story.items.length, itemIdx);
    clearTimer();

    var duration = Number(item.duration || 5000);
    if (item.type === "video") {
      var video = document.createElement("video");
      video.src = item.src;
      video.autoplay = true;
      video.muted = true;
      video.playsInline = true;
      video.controls = false;
      refs.mediaWrap.appendChild(video);
      video.addEventListener(
        "loadedmetadata",
        function () {
          if (isFinite(video.duration) && video.duration > 0) {
            duration = Math.max(1000, Math.round(video.duration * 1000));
            animateActiveProgress(itemIdx, duration);
          } else {
            animateActiveProgress(itemIdx, duration);
          }
        },
        { once: true }
      );
      video.addEventListener("ended", goNext);
      video.play().catch(function () {});
      state.timer = setTimeout(goNext, duration + 80);
    } else {
      var img = document.createElement("img");
      img.src = item.src;
      img.alt = story.username + " story";
      refs.mediaWrap.appendChild(img);
      animateActiveProgress(itemIdx, duration);
      state.timer = setTimeout(goNext, duration);
    }
  }

  function bindEvents() {
    refs.scroll.addEventListener("click", function (e) {
      var btn = e.target && e.target.closest("[data-story-idx]");
      if (!btn) return;
      var idx = Number(btn.getAttribute("data-story-idx"));
      if (!isNaN(idx)) showItem(idx, 0);
    });

    refs.closeBtn.addEventListener("click", closeModal);
    refs.nextBtn.addEventListener("click", goNext);
    refs.prevBtn.addEventListener("click", goPrev);

    refs.modal.addEventListener("click", function (e) {
      if (e.target === refs.modal) closeModal();
    });

    refs.modal.addEventListener("touchstart", function (e) {
      if (!e.touches || !e.touches[0]) return;
      state.touchStartX = e.touches[0].clientX;
      state.touchStartY = e.touches[0].clientY;
    });

    refs.modal.addEventListener("touchend", function (e) {
      if (!e.changedTouches || !e.changedTouches[0]) return;
      var dx = e.changedTouches[0].clientX - state.touchStartX;
      var dy = e.changedTouches[0].clientY - state.touchStartY;
      if (Math.abs(dy) > 70 && Math.abs(dy) > Math.abs(dx)) closeModal();
    });

    document.addEventListener("keydown", function (e) {
      if (!state.isOpen) return;
      if (e.key === "Escape") closeModal();
      if (e.key === "ArrowRight") goNext();
      if (e.key === "ArrowLeft") goPrev();
    });
  }

  function init() {
    loadViewed();
    createBase();
    renderBar();
    bindEvents();
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init, { once: true });
  } else {
    init();
  }
})();

