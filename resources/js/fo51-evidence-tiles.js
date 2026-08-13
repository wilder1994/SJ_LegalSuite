/**
 * Estado Alpine para el modal de evidencias FO-GJ-51 (vista previa local por casilla).
 * @param {string} inputIdPrefix Prefijo de id de inputs (default evidence_in_)
 */
window.evidenceTilesState = function evidenceTilesState(inputIdPrefix = 'evidence_in_') {
    return {
        urls: Array.from({ length: 10 }, () => null),
        inputIdPrefix,

        setPreview(index, event) {
            const file = event.target.files?.[0];
            const next = [...this.urls];
            if (next[index]) {
                URL.revokeObjectURL(next[index]);
            }
            next[index] = file ? URL.createObjectURL(file) : null;
            this.urls = next;
        },

        clear(index) {
            const input = document.getElementById(`${this.inputIdPrefix}${index}`);
            if (input) {
                input.value = '';
            }
            const next = [...this.urls];
            if (next[index]) {
                URL.revokeObjectURL(next[index]);
            }
            next[index] = null;
            this.urls = next;
        },
    };
};
