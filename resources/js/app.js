import '../css/app.css';

import ApexCharts from 'apexcharts';

/** Global para gráficas en Blade/Alpine (incl. `wire:navigate`: `@stack('scripts')` no re-ejecuta el vendor). */
window.ApexCharts = ApexCharts;

import './bootstrap';
import './echo-notification-bell';
import './notification-bell-sound';
import './fo51-municipality-combobox';
import './fo51-employee-combobox';
import './bulk-import-progress';
import './disciplinary-agenda-composer';
import './agenda-attachment-lightbox';
import './fo51-evidence-tiles';
import './fo51-pdf-upload-faults';
import './fo51-pdf-upload-file';
import './fo51-letter-zoom';
import './informe-pdf-preview-lightbox';
import './worker-signature-pad';
import './home-command-center';
import './disciplinary-dashboard';
import { registerApexChartsLivewireHooks } from './apex-charts-lifecycle';

registerApexChartsLivewireHooks();

function setupDisciplinaryColombiaMap() {
    const el = document.getElementById('disciplinary-colombia-map');
    if (!el || el.dataset.colombiaMapMounted === '1') {
        return;
    }
    import('./disciplinary-colombia-map.js')
        .then((m) => {
            const live = document.getElementById('disciplinary-colombia-map');
            if (!live || !live.isConnected || live.dataset.colombiaMapMounted === '1') {
                return;
            }
            return m.mountDisciplinaryColombiaMap(live);
        })
        .catch((err) => {
            console.error('[disciplinary-colombia-map]', err);
        });
}

function remountDisciplinaryColombiaMapFromCache() {
    const el = document.getElementById('disciplinary-colombia-map');
    if (!el) {
        return;
    }
    if (typeof window.__disciplinaryColombiaMapTeardown === 'function') {
        window.__disciplinaryColombiaMapTeardown();
        window.__disciplinaryColombiaMapTeardown = null;
    }
    el.dataset.colombiaMapMounted = '0';
    delete el.dataset.colombiaMapMounting;
    delete el.__disciplinaryColombiaLeafletMap;
    setupDisciplinaryColombiaMap();
}

document.addEventListener('DOMContentLoaded', () => {
    setupDisciplinaryColombiaMap();
});
document.addEventListener('livewire:navigated', () => {
    setupDisciplinaryColombiaMap();
});
document.addEventListener('livewire:navigating', () => {
    if (typeof window.__disciplinaryColombiaMapTeardown === 'function') {
        window.__disciplinaryColombiaMapTeardown();
        window.__disciplinaryColombiaMapTeardown = null;
    }
    document.querySelectorAll('[data-pins]').forEach((el) => {
        if (typeof el.__disciplinaryColombiaMapTeardown === 'function') {
            el.__disciplinaryColombiaMapTeardown();
        }
    });
});

/** BFCache restore (p. ej. mismo URL tras cambio de tema): Leaflet queda inválido si no se remonta. */
window.addEventListener('pageshow', (ev) => {
    if (!ev.persisted) {
        return;
    }
    remountDisciplinaryColombiaMapFromCache();
});

/** Tras imágenes/fuentes: el contenedor puede medir distinto que en el primer frame. */
window.addEventListener('load', () => {
    const el = document.getElementById('disciplinary-colombia-map');
    const map = el?.__disciplinaryColombiaLeafletMap;
    if (!map || typeof map.invalidateSize !== 'function') {
        return;
    }
    const fix = () => {
        try {
            map.invalidateSize(true);
        } catch {
            //
        }
    };
    requestAnimationFrame(fix);
    setTimeout(fix, 50);
    setTimeout(fix, 200);
});
