/**
 * Visor PDF.js de solo lectura (scroll DOM propio).
 * Alcance: modal «Cargar informe PDF» FO-GJ-51. No toca HtmlLetterPdfGenerator/Dompdf.
 */
import * as pdfjsLib from 'pdfjs-dist';
import pdfWorkerSrc from 'pdfjs-dist/build/pdf.worker.min.mjs?url';

pdfjsLib.GlobalWorkerOptions.workerSrc = pdfWorkerSrc;

/**
 * @param {HTMLElement|null} container
 */
export function clearPdfPages(container) {
    if (!container) {
        return;
    }
    container.replaceChildren();
}

/**
 * @param {HTMLElement|null} container
 */
export function countRenderedPages(container) {
    if (!container) {
        return 0;
    }

    return container.querySelectorAll('canvas').length;
}

/**
 * Renderiza todas las páginas del PDF en `container` (scroll lo aporta el padre overflow-y-auto).
 *
 * @param {File|Blob|ArrayBuffer|Uint8Array} source
 * @param {HTMLElement} container
 * @param {{ fitWidth?: number, signal?: { cancelled: boolean } }} [options]
 * @returns {Promise<{ pageCount: number }>}
 */
export async function renderPdfPages(source, container, options = {}) {
    if (!container) {
        throw new Error('Contenedor de preview PDF no disponible.');
    }

    clearPdfPages(container);

    let data;
    if (source instanceof ArrayBuffer) {
        data = source.slice(0);
    } else if (ArrayBuffer.isView(source)) {
        data = source.buffer.slice(source.byteOffset, source.byteOffset + source.byteLength);
    } else if (source instanceof Blob) {
        data = await source.arrayBuffer();
    } else {
        throw new Error('Fuente PDF no válida.');
    }

    const loadingTask = pdfjsLib.getDocument({ data, useSystemFonts: true });
    const pdf = await loadingTask.promise;
    const signal = options.signal ?? { cancelled: false };
    const fitWidth = Math.max(120, Number(options.fitWidth) || container.clientWidth || 320);
    const pageCount = pdf.numPages;
    let painted = 0;

    try {
        for (let pageNumber = 1; pageNumber <= pageCount; pageNumber += 1) {
            if (signal.cancelled) {
                break;
            }

            const page = await pdf.getPage(pageNumber);
            if (signal.cancelled) {
                break;
            }

            const baseViewport = page.getViewport({ scale: 1 });
            const scale = Math.min(2.5, fitWidth / baseViewport.width);
            const viewport = page.getViewport({ scale });

            const canvas = document.createElement('canvas');
            canvas.className = 'mx-auto block max-w-full bg-white shadow-sm';
            canvas.width = Math.floor(viewport.width);
            canvas.height = Math.floor(viewport.height);
            canvas.setAttribute('aria-label', `Página ${pageNumber} de ${pageCount}`);

            const context = canvas.getContext('2d', { alpha: false });
            if (!context) {
                throw new Error('No se pudo crear el contexto de dibujo del PDF.');
            }

            await page.render({
                canvasContext: context,
                viewport,
                canvas,
            }).promise;

            if (signal.cancelled) {
                break;
            }

            const wrap = document.createElement('div');
            wrap.className = 'border-b border-slate-200 pb-2 last:border-b-0 dark:border-white/10';
            wrap.appendChild(canvas);
            container.appendChild(wrap);
            painted += 1;
        }
    } finally {
        try {
            await pdf.destroy();
        } catch {
            // destroy() no debe tumbar un preview ya pintado
        }
    }

    if (signal.cancelled) {
        clearPdfPages(container);

        return { pageCount: 0 };
    }

    if (painted === 0) {
        throw new Error('El PDF no produjo páginas visibles.');
    }

    return { pageCount: painted };
}

window.fo51PdfJsScrollViewer = {
    clearPdfPages,
    countRenderedPages,
    renderPdfPages,
};
