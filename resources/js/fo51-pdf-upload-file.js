/**
 * Zona de carga PDF FO-GJ-51: click, drag/drop, paste + preview PDF.js (scroll real).
 * No altera el POST (input file) ni el pipeline HTML→PDF del servidor.
 *
 * @param {{ maxBytes?: number, inputId?: string }} opts
 */
window.fo51PdfUploadFileZone = function fo51PdfUploadFileZone(opts = {}) {
    const maxBytes = opts.maxBytes ?? 15 * 1024 * 1024;
    const inputId = opts.inputId ?? 'pdf_upload_informe_file';

    return {
        maxBytes,
        inputId,
        dragging: false,
        fileName: '',
        fileSizeLabel: '',
        previewUrl: null,
        error: '',
        lightboxOpen: false,
        rendering: false,
        renderError: '',
        pageCount: 0,
        /** @type {File|null} */
        currentFile: null,
        /** @type {{ cancelled: boolean }|null} */
        _renderSignal: null,
        _renderGeneration: 0,

        get hasFile() {
            return Boolean(this.fileName);
        },

        init() {
            this.$watch('previewUrl', (url, prev) => {
                if (prev && prev !== url) {
                    URL.revokeObjectURL(prev);
                }
            });

            this.$el.addEventListener(
                'alpine:destroying',
                () => {
                    this.cancelRender();
                    this.clearPreviewCanvases();
                    if (this.previewUrl) {
                        URL.revokeObjectURL(this.previewUrl);
                        this.previewUrl = null;
                    }
                },
                { once: true },
            );
        },

        cancelRender() {
            if (this._renderSignal) {
                this._renderSignal.cancelled = true;
            }
            this._renderSignal = null;
        },

        clearPreviewCanvases() {
            const viewer = window.fo51PdfJsScrollViewer;
            if (!viewer) {
                return;
            }
            viewer.clearPdfPages(this.$refs.inlinePages);
            viewer.clearPdfPages(this.$refs.lightboxPages);
        },

        async loadViewer() {
            if (window.fo51PdfJsScrollViewer?.renderPdfPages) {
                return window.fo51PdfJsScrollViewer;
            }
            await import('./fo51-pdfjs-scroll-viewer.js');

            return window.fo51PdfJsScrollViewer;
        },

        async renderInto(refName) {
            if (!this.currentFile) {
                return;
            }

            const container = this.$refs[refName];
            const scrollHost = this.$refs[refName === 'inlinePages' ? 'inlineScroll' : 'lightboxScroll'];
            if (!container) {
                return;
            }

            this.cancelRender();
            const signal = { cancelled: false };
            this._renderSignal = signal;
            const generation = ++this._renderGeneration;
            this.rendering = true;
            this.renderError = '';

            try {
                const viewer = await this.loadViewer();
                await this.$nextTick();
                if (generation !== this._renderGeneration || signal.cancelled) {
                    return;
                }

                const fitWidth = Math.max(
                    160,
                    (scrollHost?.clientWidth || container.clientWidth || 320) - 24,
                );
                const result = await viewer.renderPdfPages(this.currentFile, container, {
                    fitWidth,
                    signal,
                });

                if (generation !== this._renderGeneration || signal.cancelled) {
                    return;
                }

                this.pageCount = result.pageCount;
                this.renderError = '';
            } catch (err) {
                if (generation !== this._renderGeneration || signal.cancelled) {
                    return;
                }

                const painted = window.fo51PdfJsScrollViewer?.countRenderedPages?.(container) ?? 0;
                if (painted > 0) {
                    // Preview usable; no alarmar al usuario por fallos posteriores (p. ej. destroy).
                    this.pageCount = painted;
                    this.renderError = '';
                    console.warn('Preview PDF usable pese a aviso interno:', err);
                } else {
                    this.renderError = 'No se pudo previsualizar el PDF. Puede abrirlo en pestaña o continuar el envío.';
                    console.error(err);
                }
            } finally {
                if (generation === this._renderGeneration) {
                    this.rendering = false;
                    if (this._renderSignal === signal) {
                        this._renderSignal = null;
                    }
                }
            }
        },
        async openLightbox() {
            if (!this.currentFile) {
                return;
            }
            this.lightboxOpen = true;
            await this.$nextTick();
            await this.renderInto('lightboxPages');
        },

        closeLightbox() {
            this.lightboxOpen = false;
            const viewer = window.fo51PdfJsScrollViewer;
            viewer?.clearPdfPages(this.$refs.lightboxPages);
        },

        openInNewTab() {
            if (!this.previewUrl) {
                return;
            }
            window.open(this.previewUrl, '_blank', 'noopener,noreferrer');
        },

        inputEl() {
            return document.getElementById(this.inputId);
        },

        formatBytes(bytes) {
            if (bytes < 1024) {
                return `${bytes} B`;
            }
            if (bytes < 1024 * 1024) {
                return `${(bytes / 1024).toFixed(1)} KB`;
            }

            return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
        },

        isPdfFile(file) {
            if (!file) {
                return false;
            }
            const type = String(file.type || '').toLowerCase();
            const name = String(file.name || '').toLowerCase();
            if (type === 'application/pdf' || type === 'application/x-pdf') {
                return true;
            }

            return name.endsWith('.pdf');
        },

        openPicker() {
            this.inputEl()?.click();
        },

        onInputChange(event) {
            const file = event.target.files?.[0] ?? null;
            this.applyFile(file, { keepInput: true });
        },

        onDrop(event) {
            this.dragging = false;
            const file = event.dataTransfer?.files?.[0] ?? null;
            this.applyFile(file);
        },

        handleWindowPaste(event) {
            const tag = String(event.target?.tagName || '').toLowerCase();
            if (['input', 'textarea', 'select'].includes(tag) || event.target?.isContentEditable) {
                return;
            }

            const items = event.clipboardData?.items;
            if (!items) {
                return;
            }

            let file = null;
            for (const item of items) {
                if (item.kind !== 'file') {
                    continue;
                }
                const candidate = item.getAsFile();
                if (candidate && this.isPdfFile(candidate)) {
                    file = candidate;
                    break;
                }
            }

            if (!file) {
                return;
            }

            event.preventDefault();
            this.applyFile(file);
        },

        /**
         * @param {File|null} file
         * @param {{ keepInput?: boolean }} options
         */
        async applyFile(file, options = {}) {
            this.error = '';
            this.renderError = '';

            if (!file) {
                if (!options.keepInput) {
                    this.clearFile();
                }

                return;
            }

            if (!this.isPdfFile(file)) {
                this.error = 'Solo se admite un archivo PDF.';

                return;
            }

            if (file.size > this.maxBytes) {
                this.error = `El PDF supera el máximo de ${this.formatBytes(this.maxBytes)}.`;

                return;
            }

            const input = this.inputEl();
            if (input && !options.keepInput) {
                const dt = new DataTransfer();
                dt.items.add(file);
                input.files = dt.files;
            }

            if (this.previewUrl) {
                URL.revokeObjectURL(this.previewUrl);
            }

            this.cancelRender();
            this.clearPreviewCanvases();
            this.lightboxOpen = false;
            this.currentFile = file;
            this.fileName = file.name || 'informe.pdf';
            this.fileSizeLabel = this.formatBytes(file.size);
            this.previewUrl = URL.createObjectURL(file);
            this.pageCount = 0;

            await this.$nextTick();
            await this.renderInto('inlinePages');
        },

        clearFile() {
            this.cancelRender();
            this.clearPreviewCanvases();
            const input = this.inputEl();
            if (input) {
                input.value = '';
            }
            if (this.previewUrl) {
                URL.revokeObjectURL(this.previewUrl);
            }
            this.previewUrl = null;
            this.currentFile = null;
            this.fileName = '';
            this.fileSizeLabel = '';
            this.error = '';
            this.renderError = '';
            this.lightboxOpen = false;
            this.pageCount = 0;
            this.rendering = false;
        },

        changeFile() {
            this.lightboxOpen = false;
            this.openPicker();
        },
    };
};
