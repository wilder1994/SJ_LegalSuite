/**
 * Zoom / fit / pan viewport for FO-GJ-51 letter sheet on screen (not Dompdf).
 * Keeps the official Letter layout; scale only affects the sheet inside the viewport.
 */
window.fo51LetterZoom = function fo51LetterZoom() {
    return {
        scale: 1,
        fitScale: 1,
        mode: 'fit',
        minScale: 0.4,
        maxScale: 1.75,
        step: 0.1,
        sheetW: 816,
        sheetH: 1056,
        _ro: null,

        init() {
            this.$nextTick(() => {
                this.measure();
                this.fitWidth();
                this._ro = typeof ResizeObserver !== 'undefined'
                    ? new ResizeObserver(() => {
                        if (this.mode === 'fit') {
                            this.fitWidth();
                        } else {
                            this.measure();
                            this.refreshFitScaleOnly();
                        }
                    })
                    : null;
                if (this._ro && this.$refs.fo51Viewport) {
                    this._ro.observe(this.$refs.fo51Viewport);
                }
                window.addEventListener('resize', () => {
                    if (this.mode === 'fit') {
                        this.fitWidth();
                    } else {
                        this.refreshFitScaleOnly();
                    }
                });
            });
        },

        measure() {
            const sheet = this.$refs.fo51LetterSheet;
            if (! sheet) {
                return;
            }
            // Natural size before transform
            this.sheetW = sheet.offsetWidth || 816;
            this.sheetH = sheet.offsetHeight || 1056;
        },

        refreshFitScaleOnly() {
            const viewport = this.$refs.fo51Viewport;
            if (! viewport) {
                return;
            }
            this.measure();
            const available = Math.max(viewport.clientWidth - 16, 120);
            this.fitScale = this.sheetW > available
                ? Math.max(available / this.sheetW, this.minScale)
                : 1;
        },

        fitWidth() {
            this.mode = 'fit';
            this.refreshFitScaleOnly();
            this.scale = this.fitScale;
        },

        zoomIn() {
            this.mode = 'manual';
            this.scale = Math.min(this.maxScale, Math.round((this.scale + this.step) * 100) / 100);
        },

        zoomOut() {
            this.mode = 'manual';
            this.scale = Math.max(this.minScale, Math.round((this.scale - this.step) * 100) / 100);
        },

        percentLabel() {
            return `${Math.round(this.scale * 100)}%`;
        },

        spacerStyle() {
            return {
                width: `${Math.ceil(this.sheetW * this.scale)}px`,
                height: `${Math.ceil(this.sheetH * this.scale)}px`,
                position: 'relative',
            };
        },

        sheetStyle() {
            return {
                transform: `scale(${this.scale})`,
                transformOrigin: 'top left',
            };
        },
    };
};
