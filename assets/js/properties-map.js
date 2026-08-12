(function () {
  "use strict";

  var dataEl = document.getElementById("propertiesMapData");
  var mapEl = document.getElementById("propertyMap");
  if (!dataEl || !mapEl || typeof L === "undefined") {
    return;
  }

  var properties = [];
  try {
    properties = JSON.parse(dataEl.textContent || "[]");
  } catch (e) {
    properties = [];
  }

  /* ---------- מיתוג תצוגה: רשימה / מפה ---------- */
  var toggle = document.querySelector("[data-view-toggle]");
  var panels = document.querySelectorAll("[data-view-panel]");
  var mapInitialized = false;
  var map, markers, markerById, previewCard, pinnedId, activeListItem;

  function setView(view) {
    if (!toggle) return;
    toggle.querySelectorAll("[data-view-btn]").forEach(function (btn) {
      var isThis = btn.getAttribute("data-view-btn") === view;
      btn.setAttribute("aria-pressed", String(isThis));
    });
    panels.forEach(function (panel) {
      var isThis = panel.getAttribute("data-view-panel") === view;
      panel.hidden = !isThis;
    });
    if (view === "map" && !mapInitialized) {
      setTimeout(initMap, 0);
    } else if (view === "map" && map) {
      setTimeout(function () {
        map.invalidateSize();
      }, 50);
    }
    var url = new URL(window.location.href);
    if (view === "map") {
      url.searchParams.set("view", "map");
    } else {
      url.searchParams.delete("view");
    }
    history.replaceState(null, "", url.pathname + url.search);
  }

  if (toggle) {
    toggle.querySelectorAll("[data-view-btn]").forEach(function (btn) {
      btn.addEventListener("click", function () {
        setView(btn.getAttribute("data-view-btn"));
      });
    });
    var initialView = new URLSearchParams(window.location.search).get("view");
    if (initialView === "map") {
      setView("map");
    }
  }

  /* ---------- כרטיס תצוגה מקדימה ---------- */
  function createPreviewCard() {
    var card = document.createElement("a");
    card.className = "map-preview-card";
    card.innerHTML =
      '<button type="button" class="map-preview-close" aria-label="סגירה">✕</button>' +
      '<img alt="" loading="lazy">' +
      '<div class="map-preview-body">' +
      "<h3></h3>" +
      '<p class="map-preview-loc"></p>' +
      '<p class="map-preview-price"></p>' +
      '<div class="map-preview-specs"></div>' +
      '<span class="map-preview-cta">צפייה בנכס</span>' +
      "</div>";
    card.querySelector(".map-preview-close").addEventListener("click", function (e) {
      e.preventDefault();
      e.stopPropagation();
      hidePreview(true);
    });
    return card;
  }

  function isMobileLayout() {
    return window.matchMedia("(max-width: 640px)").matches;
  }

  function fillPreview(p) {
    previewCard.href = p.url;
    previewCard.querySelector("img").src = p.image;
    previewCard.querySelector("img").alt = p.title;
    previewCard.querySelector("h3").textContent = p.title;
    previewCard.querySelector(".map-preview-loc").textContent =
      p.city + (p.neighborhood ? " · " + p.neighborhood : "");
    previewCard.querySelector(".map-preview-price").textContent = p.priceLabel;
    var specsHtml = "";
    if (p.rooms) specsHtml += "<span>🛏 " + p.rooms + "</span>";
    if (p.size) specsHtml += "<span>📐 " + p.size + ' מ"ר</span>';
    specsHtml += "<span>" + p.type + "</span>";
    previewCard.querySelector(".map-preview-specs").innerHTML = specsHtml;
  }

  // תיאום ה-left/top כאן פיזי בכוונה: latLngToContainerPoint() מחזיר פיקסלים פיזיים
  // מפינת המפה, בלי קשר לכיווניות המסמך — קואורדינטת מפה, לא טקסט.
  function positionPreview(p) {
    if (isMobileLayout()) {
      previewCard.style.left = previewCard.style.top = previewCard.style.bottom = "";
      return;
    }
    var point = map.latLngToContainerPoint([p.lat, p.lng]);
    var mapRect = mapEl.getBoundingClientRect();
    var cardWidth = 260;
    var left = Math.max(8, Math.min(point.x - cardWidth / 2, mapRect.width - cardWidth - 8));
    var markerVisualHeight = 30;
    var showBelow = point.y - 46 < 200;

    previewCard.style.left = left + "px";
    if (showBelow) {
      previewCard.style.top = point.y + markerVisualHeight / 2 + 10 + "px";
      previewCard.style.bottom = "";
    } else {
      previewCard.style.top = "";
      previewCard.style.bottom = mapRect.height - (point.y - markerVisualHeight - 10) + "px";
    }
  }

  function highlightListItem(id) {
    if (activeListItem) {
      activeListItem.classList.remove("is-active");
    }
    var item = document.querySelector('.map-list-item[data-property-id="' + id + '"]');
    if (item) {
      item.classList.add("is-active");
      activeListItem = item;
    } else {
      activeListItem = null;
    }
  }

  function highlightMarker(id, active) {
    var entry = markerById[id];
    if (!entry) return;
    var el = entry.marker.getElement();
    if (el) {
      var inner = el.querySelector(".map-price-marker");
      if (inner) inner.classList.toggle("is-active", active);
    }
  }

  function showPreview(id, pin) {
    var entry = markerById[id];
    if (!entry) return;
    fillPreview(entry.data);
    previewCard.classList.add("is-visible");
    positionPreview(entry.data);
    highlightListItem(id);
    highlightMarker(id, true);
    if (pin) {
      pinnedId = id;
    }
  }

  function hidePreview(force) {
    if (pinnedId && !force) return;
    previewCard.classList.remove("is-visible");
    if (pinnedId) highlightMarker(pinnedId, false);
    pinnedId = null;
    if (activeListItem) {
      activeListItem.classList.remove("is-active");
      activeListItem = null;
    }
  }

  /* ---------- אתחול מפה ---------- */
  function initMap() {
    mapInitialized = true;
    markerById = {};

    map = L.map(mapEl, { scrollWheelZoom: false });
    L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
      attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
      maxZoom: 19,
    }).addTo(map);

    previewCard = createPreviewCard();
    mapEl.appendChild(previewCard);

    markers = L.markerClusterGroup({
      maxClusterRadius: 50,
      iconCreateFunction: function (cluster) {
        return L.divIcon({
          html: '<div class="map-cluster-icon">' + cluster.getChildCount() + "</div>",
          className: "",
          iconSize: [40, 40],
        });
      },
    });

    var bounds = [];
    properties.forEach(function (p) {
      var icon = L.divIcon({
        html: '<div class="map-price-marker">' + p.priceShort + "</div>",
        className: "",
        iconSize: null,
      });
      var marker = L.marker([p.lat, p.lng], { icon: icon, keyboard: true, alt: p.title });
      marker.on("mouseover", function () {
        if (!isMobileLayout()) showPreview(p.id, false);
      });
      marker.on("mouseout", function () {
        if (!isMobileLayout()) hidePreview(false);
      });
      marker.on("click", function () {
        showPreview(p.id, true);
      });
      markers.addLayer(marker);
      markerById[p.id] = { marker: marker, data: p };
      bounds.push([p.lat, p.lng]);
    });
    map.addLayer(markers);

    map.invalidateSize();
    if (bounds.length) {
      map.fitBounds(bounds, { padding: [40, 40], maxZoom: 14 });
    } else {
      map.setView([32.3215, 34.8532], 12);
    }

    map.on("move zoom", function () {
      if (pinnedId && markerById[pinnedId]) {
        positionPreview(markerById[pinnedId].data);
      }
    });
    map.on("click", function () {
      hidePreview(false);
    });

    /* ---------- סנכרון: רשימה -> מפה ---------- */
    document.querySelectorAll(".map-list-item").forEach(function (item) {
      var id = parseInt(item.getAttribute("data-property-id"), 10);
      item.addEventListener("mouseenter", function () {
        highlightMarker(id, true);
      });
      item.addEventListener("mouseleave", function () {
        if (pinnedId !== id) highlightMarker(id, false);
      });
      item.addEventListener("click", function () {
        var entry = markerById[id];
        if (!entry) return;
        // מציגים את הכרטיס מיד — לא סומכים על callback של zoomToShowLayer,
        // שלא תמיד נקרא כשהסמן כבר גלוי וללא צורך בזום/פאן (תקלה תועדה בבדיקה).
        showPreview(id, true);
        if (typeof markers.zoomToShowLayer === "function") {
          markers.zoomToShowLayer(entry.marker, function () {
            positionPreview(entry.data);
          });
        } else {
          map.setView(entry.marker.getLatLng(), 15);
        }
        setTimeout(function () {
          if (pinnedId === id) positionPreview(entry.data);
        }, 350);
      });
    });
  }
})();
