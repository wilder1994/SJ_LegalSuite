{{-- FO-GJ-51 · solo pantalla interactiva (no PDF).
     El layout Letter se conserva; en móvil el modal usa zoom/pan (fo51-letter-zoom).
     No apilar grillas aquí: rompería la sensación de formato oficial.
     Opcional legado: clase fo51-stack-on-narrow en .fo51-interactive reactiva apilado. --}}
<style>
    /* Mejoras táctiles sin alterar display de tablas / grillas oficiales */
    @media (max-width: 767px) {
        .fo51-interactive .fo51-chk {
            width: 1.15rem;
            height: 1.15rem;
        }

        .fo51-interactive .fo51-in,
        .fo51-interactive select.fo51-in {
            font-size: 14px;
        }

        .fo51-interactive textarea.fo51-in {
            min-height: 7rem;
            font-size: 14px !important;
        }

        .fo51-interactive .fo51-signature-capture-btn,
        .fo51-interactive .fo51-signature-capture-link {
            min-height: 40px;
        }

        .fo51-interactive .fo51-helper-note {
            font-size: 12px !important;
            padding: 0 12px !important;
        }
    }

    /* Legado: apilado solo si se marca explícitamente (no usar con Letter zoom) */
    @media (max-width: 767px) {
        .fo51-interactive.fo51-stack-on-narrow .fo51-block-personal tr {
            display: block;
            width: 100%;
        }

        .fo51-interactive.fo51-stack-on-narrow .fo51-block-personal td.fo51-personal-cell {
            display: block;
            width: 100% !important;
            box-sizing: border-box;
        }

        .fo51-interactive.fo51-stack-on-narrow .fo51-block-faults tbody tr,
        .fo51-interactive.fo51-stack-on-narrow .fo51-block-faults tbody td {
            display: block;
            width: 100% !important;
        }
    }
</style>
