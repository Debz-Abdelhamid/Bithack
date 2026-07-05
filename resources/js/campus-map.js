import maplibregl from 'maplibre-gl';
import 'maplibre-gl/dist/maplibre-gl.css';

/**
 * Cartographie campus — vanilla-JS port of the legacy Patrimo-BitHack
 * CampusMap.tsx (React/react-map-gl removed, everything else kept as-is):
 * OpenFreeMap bright tiles, UBMA center, zoom 17, pitch 45, the same
 * navigation/geolocate/scale controls, the same custom SVG flag markers with
 * hover tooltips and selected-state styling, and the crosshair picking mode.
 */

const CENTER = { longitude: 7.7198301, latitude: 36.8133517 };
const MARKER_COLOR = '#0f766e';

function markerSvg(building, selected) {
    const w = Math.max(80, building.name.length * 7 + 16);
    const h = 46;

    return `
        <svg width="${w}" height="${h + 8}" viewBox="0 0 ${w} ${h + 8}"
             style="filter: ${selected
                 ? 'drop-shadow(0 6px 18px rgba(0,0,0,0.35))'
                 : 'drop-shadow(0 2px 8px rgba(0,0,0,0.25))'};
                    display:block; transition: all 0.15s ease;
                    transform: scale(${selected ? 1.08 : 1});">
            <rect x="0" y="0" width="${w}" height="${h}" rx="10" fill="${MARKER_COLOR}"></rect>
            ${selected ? `<rect x="2" y="2" width="${w - 4}" height="${h - 4}" rx="8" fill="none" stroke="white" stroke-width="2"></rect>` : ''}
            <polygon points="${w / 2 - 6},${h - 1} ${w / 2 + 6},${h - 1} ${w / 2},${h + 7}" fill="${MARKER_COLOR}"></polygon>
            <text x="${w / 2}" y="${h / 2 - 6}" text-anchor="middle" dominant-baseline="middle"
                  fill="white" font-size="${selected ? 11 : 10}" font-weight="800"
                  font-family="system-ui, sans-serif" letter-spacing="0.8">${escapeHtml(building.name)}</text>
            <text x="${w / 2}" y="${h / 2 + 8}" text-anchor="middle" dominant-baseline="middle"
                  fill="rgba(255,255,255,0.75)" font-size="8.5"
                  font-family="system-ui, sans-serif">${building.rooms.length} rooms</text>
        </svg>`;
}

function tooltipHtml(building) {
    return `
        <div style="font-weight:700;font-size:12px;color:#0f172a;margin-bottom:2px;">${escapeHtml(building.name)}</div>
        <div style="font-size:11px;color:#64748b;">${escapeHtml(building.faculty)} · ${building.rooms.length} rooms</div>`;
}

function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>"']/g, (c) => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;',
    })[c]);
}

function initCampusMap(container) {
    let buildings = JSON.parse(container.dataset.buildings ?? '[]');
    let selectedId = null;
    let picking = false;
    const markers = new Map();

    const map = new maplibregl.Map({
        container,
        style: 'https://tiles.openfreemap.org/styles/bright',
        center: [CENTER.longitude, CENTER.latitude],
        zoom: 17,
        pitch: 45,
        bearing: 0,
        attributionControl: false,
    });

    map.addControl(new maplibregl.GeolocateControl({
        positionOptions: { enableHighAccuracy: true },
        trackUserLocation: true,
    }), 'top-left');
    map.addControl(new maplibregl.NavigationControl({
        showCompass: true,
        visualizePitch: true,
    }), 'top-left');
    map.addControl(new maplibregl.ScaleControl({ unit: 'metric' }), 'bottom-left');

    const tooltip = document.createElement('div');
    tooltip.className = 'campus-tooltip';
    tooltip.style.display = 'none';
    container.appendChild(tooltip);

    function renderMarker(building) {
        if (building.latitude == null || building.longitude == null) {
            return;
        }

        const el = document.createElement('div');
        el.style.cursor = 'pointer';
        el.innerHTML = markerSvg(building, building.id === selectedId);

        el.addEventListener('mouseenter', (event) => {
            tooltip.innerHTML = tooltipHtml(building);
            tooltip.style.display = 'block';
            positionTooltip(event);
        });
        el.addEventListener('mousemove', positionTooltip);
        el.addEventListener('mouseleave', () => {
            tooltip.style.display = 'none';
        });
        el.addEventListener('click', (event) => {
            event.stopPropagation();
            if (picking) return;
            setSelected(building.id);
            window.Livewire?.dispatch('campus-building-selected', { id: building.id });
        });

        const marker = new maplibregl.Marker({ element: el, anchor: 'bottom' })
            .setLngLat([building.longitude, building.latitude])
            .addTo(map);

        markers.set(building.id, { marker, el, building });
    }

    function positionTooltip(event) {
        const rect = container.getBoundingClientRect();
        tooltip.style.left = `${event.clientX - rect.left + 12}px`;
        tooltip.style.top = `${event.clientY - rect.top - 12}px`;
    }

    function setSelected(id) {
        selectedId = id;
        markers.forEach(({ el, building }) => {
            el.innerHTML = markerSvg(building, building.id === selectedId);
        });
    }

    function renderAll() {
        markers.forEach(({ marker }) => marker.remove());
        markers.clear();
        buildings.forEach(renderMarker);
    }

    renderAll();

    map.on('click', (event) => {
        if (!picking) return;
        window.Livewire?.dispatch('campus-coordinates-picked', {
            lat: event.lngLat.lat,
            lng: event.lngLat.lng,
        });
    });

    window.addEventListener('campus-picking-changed', (event) => {
        picking = Boolean(event.detail?.picking ?? event.detail?.[0]?.picking);
        map.getCanvas().style.cursor = picking ? 'crosshair' : '';
    });

    // Selection coming from the side-panel dropdown (covers unplaced buildings)
    window.addEventListener('campus-select-building', (event) => {
        const detail = event.detail?.[0] ?? event.detail ?? {};
        const id = Number(detail.id);
        if (!id) return;

        setSelected(id);

        if (detail.latitude != null && detail.longitude != null) {
            map.flyTo({
                center: [detail.longitude, detail.latitude],
                zoom: Math.max(map.getZoom(), 17),
            });
        }
    });

    window.addEventListener('campus-buildings-updated', (event) => {
        const payload = event.detail?.buildings ?? event.detail?.[0]?.buildings;
        if (Array.isArray(payload)) {
            buildings = payload;
            picking = false;
            map.getCanvas().style.cursor = '';
            renderAll();
        }
    });
}

document.addEventListener('DOMContentLoaded', () => {
    const container = document.getElementById('campus-map');
    if (container) {
        initCampusMap(container);
    }
});
