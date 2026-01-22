let CITY_CENTERS = {};
let map;

function searchCity(city) {
    const input = city || document.getElementById("search").value.trim().toLowerCase();
    const coords = CITY_CENTERS[input];
    if (coords) {
      map.flyTo({ center: coords, zoom: 10, speed: 0.7 });
    } else {
      alert("City not found.");
    }
}

document.addEventListener("DOMContentLoaded", async () => {
  // ===== DYNAMIC DATA FETCHING =====
  let CLUSTER_CITIES = [];
  let SIGN_DATA = [];

  try {
    const [citiesRes, signsRes] = await Promise.all([
      fetch(plugin_vars.cities_url),
      fetch(plugin_vars.signs_url),
    ]);
    CLUSTER_CITIES = await citiesRes.json();
    SIGN_DATA = await signsRes.json();

    CITY_CENTERS = Object.fromEntries(
      CLUSTER_CITIES.map((c) => [c.name.toLowerCase(), c.coords])
    );
  } catch (error) {
    console.error("Error fetching map data:", error);
    // Handle the error appropriately, e.g., show a message to the user
    return;
  }
  
  // ===== MAP SETUP =====
  map = new maplibregl.Map({
    container: "map",
    style: {
      version: 8,
      glyphs: "https://fonts.openmaptiles.org/{fontstack}/{range}.pbf",
      sources: {
        osm: {
          type: "raster",
          tiles: [
            "https://a.tile.openstreetmap.org/{z}/{x}/{y}.png",
            "https://b.tile.openstreetmap.org/{z}/{x}/{y}.png",
            "https://c.tile.openstreetmap.org/{z}/{x}/{y}.png",
          ],
          tileSize: 256,
          attribution: "&copy; OpenStreetMap contributors",
        },
      },
      layers: [{ id: "osm", type: "raster", source: "osm" }],
    },
    center: [-98.35, 39.5],
    zoom: 3.49,
  });

  map.addControl(new maplibregl.NavigationControl());

  // ===== DATA SOURCES =====
  const cityClusterGeoJSON = {
    type: "FeatureCollection",
    features: CLUSTER_CITIES.map((c) => ({
      type: "Feature",
      geometry: { type: "Point", coordinates: c.coords },
      properties: {
        name: c.name,
        count: c.count,
        img: c.img,
        venue: c.venue,
      },
    })),
  };

  const signGeoJSON = {
    type: "FeatureCollection",
    features: SIGN_DATA.map((s) => ({
      type: "Feature",
      geometry: { type: "Point", coordinates: [s.coords[1], s.coords[0]] },
      properties: {
        city: s.city,
        venue: s.venue,
        title: s.title,
        img: s.img,
        href: s.href,
      },
    })),
  };

  // ===== MAP LOAD =====
  map.on("load", () => {
    const pinPaths = {
      "convenience-store":
        plugin_vars.asset_path + "pins/convenience-store.svg",
      entertainment: plugin_vars.asset_path + "pins/entertainment.svg",
      default: plugin_vars.asset_path + "pins/default.svg",
      selected: plugin_vars.asset_path + "pins/selected.svg",
      transit: plugin_vars.asset_path + "pins/transit.svg",
      "grocery-store": plugin_vars.asset_path + "pins/grocery-store.svg",
      health: plugin_vars.asset_path + "pins/health.svg",
      retail: plugin_vars.asset_path + "pins/retail.svg",
      financial: plugin_vars.asset_path + "pins/financial.svg",
    };

    // Dynamically register all pins with MapLibre
    Object.entries(pinPaths).forEach(([key, url]) => {
      const img = new Image(28, 28);
      img.onload = () => map.addImage(`pin-${key}`, img);
      img.src = url;
    });

    // Optional fallback handler if an icon is missing
    map.on("styleimagemissing", (e) => {
      const id = e.id;
      if (id.startsWith("pin-")) {
        console.warn(`⚠️ Missing icon ${id}, using default pin`);
        map.addImage(id, map.getImage("pin-default"));
      }
    });

    // --- City Bubbles (DMA clusters) ---
    map.addSource("cityClusters", {
      type: "geojson",
      data: cityClusterGeoJSON,
      cluster: true,
      clusterRadius: 28,
      clusterMaxZoom: 7,
      clusterProperties: {
        // totalCount = sum of all "count" values inside cluster
        totalCount: ["+", ["get", "count"]],
      },
    });

    map.addLayer({
      id: "cluster-bubbles",
      type: "circle",
      source: "cityClusters",
      filter: ["has", "point_count"], // ⭐ clustered features only
      paint: {
        "circle-color": "#ff3e86",
        "circle-radius": [
          "step",
          ["get", "totalCount"], // ⭐ summed cluster count
          18, // < 18 signs
          25, // 50–149 signs
          24,
          150,
          25, // 150–299 signs
          300,
          25, // 300+
        ],
        "circle-stroke-color": "#fff",
        "circle-stroke-width": 2,
        "circle-opacity": 0.9,
      },
    });
    map.addLayer({
      id: "unclustered-bubbles",
      type: "circle",
      source: "cityClusters",
      filter: ["!", ["has", "point_count"]], // ⭐ NOT clusters
      paint: {
        "circle-color": "#ff3e86",
        "circle-radius": 25,
        "circle-stroke-color": "#fff",
        "circle-stroke-width": 2,
        "circle-opacity": 0.9,
      },
    });

    map.addLayer({
      id: "cluster-count",
      type: "symbol",
      source: "cityClusters",
      filter: ["has", "point_count"],
      layout: {
        "text-field": ["get", "totalCount"], // ⭐ sum
        "text-size": 16,
        "text-font": ["Open Sans Regular"],
      },
      paint: {
        "text-color": "#fff",
        "text-halo-color": "#ff3e86",
        "text-halo-width": 1.5,
      },
    });
    map.addLayer({
      id: "unclustered-count",
      type: "symbol",
      source: "cityClusters",
      filter: ["!", ["has", "point_count"]],
      layout: {
        "text-field": ["get", "count"],
        "text-size": 14,
        "text-font": ["Open Sans Regular"],
      },
      paint: {
        "text-color": "#fff",
        "text-halo-color": "#ff3e86",
        "text-halo-width": 1.5,
      },
    });

    // --- Signs (Local Billboard Clusters) ---
    map.addSource("signs", {
      type: "geojson",
      data: signGeoJSON,
      cluster: true,
      clusterMaxZoom: 18,
      clusterRadius: 40,
    });

    map.addLayer({
      id: "sign-clusters",
      type: "circle",
      source: "signs",
      filter: ["has", "point_count"],
      paint: {
        "circle-color": "#fca5d3",
        "circle-radius": ["step", ["get", "point_count"], 10, 10, 16, 30, 24],
        "circle-opacity": 0.9,
      },
      layout: { visibility: "none" },
    });

    map.addLayer({
      id: "sign-count",
      type: "symbol",
      source: "signs",
      filter: ["has", "point_count"],
      layout: {
        "text-field": ["get", "point_count_abbreviated"],
        "text-size": 12,
        "text-font": ["Open Sans Regular"],
        visibility: "none",
      },
      paint: {
        "text-color": "#fff",
        "text-halo-color": "#f472b6",
        "text-halo-width": 1.5,
      },
    });
    map.addLayer({
      id: "sign-points",
      type: "symbol",
      source: "signs",
      filter: ["!", ["has", "point_count"]],
      layout: {
        "icon-image": [
          "match",
          ["get", "venue"],
          "convenience-store",
          "pin-convenience-store",
          "entertainment",
          "pin-entertainment",
          "transit",
          "pin-transit",
          "grocery-store",
          "pin-grocery-store",
          "health",
          "pin-health",
          "retail",
          "pin-retail",
          "financial",
          "pin-financial",
          /* default fallback: */
          "pin-default",
        ],
        "icon-size": 1,
        "icon-allow-overlap": true,
        visibility: "none",
      },
    });

    // ===== POPUP LOGIC FOR CITY CLUSTERS =====
    let activePopup = null;
    let popupLocked = false;

    function showClusterPreview(feature) {
      if (activePopup) {
        activePopup.remove();
        activePopup = null;
      }

      const coords = feature.geometry.coordinates.slice();
      const cityName = feature.properties.name;
      const city = CLUSTER_CITIES.find(
        (c) => c.name.toLowerCase() === cityName.toLowerCase()
      );
      if (!city) return;

      const title = `${city.venue} in ${city.name}`;
      const popupHTML = `
                <div class="popup">
                  <img src="${city.img}" alt="${city.venue}">
                  <strong>${city.venue} in ${city.name}</strong>
                  <a href="https://marketplace.blipbillboards.com/register" target="_blank" class="cta">Log in to get started</a>
                </div>
              `;

      activePopup = new maplibregl.Popup({
        offset: 25,
        closeButton: false,
        closeOnClick: false,
      })
        .setLngLat(coords)
        .setHTML(popupHTML)
        .addTo(map);
    }

    // Hover → preview
    map.on("mouseenter", "cluster-bubbles", (e) => {
      if (popupLocked) return;
      map.getCanvas().style.cursor = "pointer";
      showClusterPreview(e.features[0]);
    });

    // Mouseleave → hide
    map.on("mouseleave", "cluster-bubbles", () => {
      map.getCanvas().style.cursor = "";
      if (!popupLocked && activePopup) {
        activePopup.remove();
        activePopup = null;
      }
    });

    // Click → lock popup
    map.on("click", "cluster-bubbles", (e) => {
      showClusterPreview(e.features[0]);
      popupLocked = true;
    });

    // Click elsewhere → close popup
    map.on("click", (e) => {
      const features = map.queryRenderedFeatures(e.point, {
        layers: ["cluster-bubbles"],
      });
      if (features.length === 0 && popupLocked && activePopup) {
        activePopup.remove();
        activePopup = null;
        popupLocked = false;
      }
    });
    let selectedSignId = null;

    map.on("click", "sign-points", (e) => {
      const feature = e.features[0];
      const id =
        feature.id ||
        feature.properties.id ||
        Math.random().toString(36).substring(2);
      selectedSignId = id;

      // 🧩 Update the layer so the selected pin uses the "pin-selected" image
      map.setLayoutProperty("sign-points", "icon-image", [
        "case",
        ["==", ["id"], selectedSignId],
        "pin-selected",
        [
          "match",
          ["get", "venue"],
          "convenience-store",
          "pin-convenience-store",
          "entertainment",
          "pin-entertainment",
          "transit",
          "pin-transit",
          "grocery-store",
          "pin-grocery-store",
          "health",
          "pin-health",
          "retail",
          "pin-retail",
          "financial",
          "pin-financial",
          "pin-default",
        ],
      ]);

      // === 4️⃣ Show the popup at the same time ===
      const p = feature.properties;
      new maplibregl.Popup({ offset: 20 })
        .setLngLat(feature.geometry.coordinates)
        .setHTML(
          `
    <div class="popup">
      <img src="${p.img}" alt="${p.title}">
      <strong>${p.title}</strong><br/>
      <a href="${p.href}" target="_blank" class="cta">Log in to get started</a>
    </div>
  `
        )

        .addTo(map);
    });

    // ===== ZOOM SWITCH BETWEEN LEVELS =====
    const ZOOM_SWITCH = 9;
    function safeSetVisibility(id, visibility) {
      if (map.getLayer(id))
        map.setLayoutProperty(id, "visibility", visibility);
    }

    map.on("zoom", () => {
      const zoom = map.getZoom();
      const showSigns = zoom > ZOOM_SWITCH;
      //   console.log("Zoom:", map.getZoom(), "=> showSigns:", showSigns);

      safeSetVisibility("cluster-bubbles", showSigns ? "none" : "visible");
      safeSetVisibility("city-count", showSigns ? "none" : "visible");
      safeSetVisibility(
        "unclustered-bubbles",
        showSigns ? "none" : "visible"
      );
      safeSetVisibility("unclustered-count", showSigns ? "none" : "visible");
      safeSetVisibility("sign-clusters", showSigns ? "visible" : "none");
      safeSetVisibility("sign-count", showSigns ? "visible" : "none");
      safeSetVisibility("sign-points", showSigns ? "visible" : "none");
    });
  });

  // ===== CITY SEARCH =====
  

  document.getElementById("search").addEventListener("keydown", (e) => {
    if (e.key === "Enter") {
        e.preventDefault();
        searchCity(document.getElementById("search").value.trim().toLowerCase());
    }
  });

  /* === Autocomplete integration with existing search box === */

  const searchInput = document.getElementById("search");
  const suggestionsList = document.getElementById("suggestions");

  // Pull city names from your cluster data
  const cityNames = CLUSTER_CITIES.map((c) => c.name);

  // Typing handler
  searchInput.addEventListener("input", () => {
    const query = searchInput.value.trim().toLowerCase();
    suggestionsList.innerHTML = "";

    if (!query) return;

    const matches = cityNames.filter((city) =>
      city.toLowerCase().startsWith(query)
    );

    matches.slice(0, 5).forEach((city) => {
      const li = document.createElement("li");
      li.innerHTML = `<strong>${city.substr(
        0,
        query.length
      )}</strong>${city.substr(query.length)}`;
      li.addEventListener("click", () => {
        searchInput.value = city;
        suggestionsList.innerHTML = "";
        searchCity(city.toLowerCase()); // ✅ triggers your existing zoom function
      });
      suggestionsList.appendChild(li);
    });
  });

  // Hide suggestions when clicking outside
  document.addEventListener("click", (e) => {
    if (!document.querySelector(".search-box").contains(e.target)) {
      suggestionsList.innerHTML = "";
    }
  });
});


