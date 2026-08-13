/**
 * Selector de faltas FO-GJ-51 para carga PDF:
 * panel desplegable con checkbox por falta + «Guardar» + chips + Otros fijo.
 *
 * @param {{
 *   leftCatalog: string[],
 *   rightCatalog: string[],
 *   initialLeft?: string[],
 *   initialRight?: string[],
 *   otherChecked?: boolean,
 *   otherDetail?: string,
 * }} opts
 */
window.fo51PdfUploadFaultsPicker = function fo51PdfUploadFaultsPicker(opts = {}) {
    const leftCatalog = Array.isArray(opts.leftCatalog) ? opts.leftCatalog : [];
    const rightCatalog = Array.isArray(opts.rightCatalog) ? opts.rightCatalog : [];
    const leftSet = new Set(leftCatalog);
    const rightSet = new Set(rightCatalog);
    const catalog = [...leftCatalog, ...rightCatalog];

    const initialSelected = [];
    for (const label of opts.initialLeft || []) {
        if (typeof label === 'string' && label !== '' && !initialSelected.includes(label)) {
            initialSelected.push(label);
        }
    }
    for (const label of opts.initialRight || []) {
        if (typeof label === 'string' && label !== '' && !initialSelected.includes(label)) {
            initialSelected.push(label);
        }
    }

    return {
        catalog,
        open: false,
        draft: [...initialSelected],
        selected: [...initialSelected],
        otherChecked: Boolean(opts.otherChecked),
        otherDetail: typeof opts.otherDetail === 'string' ? opts.otherDetail : '',

        get selectedLeft() {
            return this.selected.filter((label) => leftSet.has(label));
        },

        get selectedRight() {
            return this.selected.filter((label) => rightSet.has(label));
        },

        get triggerLabel() {
            const n = this.selected.length;
            if (n === 0) {
                return 'Seleccione faltas…';
            }
            if (n === 1) {
                return this.selected[0];
            }

            return `${n} faltas seleccionadas`;
        },

        isDraftChecked(label) {
            return this.draft.includes(label);
        },

        toggleDraft(label) {
            if (this.draft.includes(label)) {
                this.draft = this.draft.filter((item) => item !== label);

                return;
            }
            this.draft = [...this.draft, label];
        },

        openPanel() {
            this.draft = [...this.selected];
            this.open = true;
        },

        closePanel() {
            this.open = false;
            this.draft = [...this.selected];
        },

        togglePanel() {
            if (this.open) {
                this.closePanel();

                return;
            }
            this.openPanel();
        },

        saveDraft() {
            const next = [];
            for (const label of this.draft) {
                if ((leftSet.has(label) || rightSet.has(label)) && !next.includes(label)) {
                    next.push(label);
                }
            }
            this.selected = next;
            this.open = false;
        },

        remove(label) {
            this.selected = this.selected.filter((item) => item !== label);
            this.draft = this.draft.filter((item) => item !== label);
        },
    };
};
