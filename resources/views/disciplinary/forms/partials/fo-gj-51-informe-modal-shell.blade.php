@props([
    'prefillWorkerName' => null,
    'prefillWorkerDocument' => null,
    'openPdfUploadModal' => false,
    'pdfIframeName' => 'fo51_pdf_iframe_modal',
])

@php
    $prefillWorkerName = $prefillWorkerName ?? null;
    $prefillWorkerDocument = $prefillWorkerDocument ?? null;
@endphp

<div
    class="fixed inset-0 z-[60] flex items-center justify-center p-2 sm:p-4"
    role="dialog"
    aria-modal="true"
    aria-labelledby="fo51-main-modal-title"
    wire:key="fo51-informe-modal">
    <div class="absolute inset-0 bg-black/50 dark:bg-black/60" wire:click="closeFo51Modal" aria-hidden="true"></div>

    <div
        class="relative flex h-[calc(100dvh-1rem)] max-h-[calc(100dvh-1rem)] w-full max-w-5xl flex-col overflow-hidden rounded-xl bg-slate-50 shadow-2xl ring-1 ring-slate-200 sm:h-[calc(100dvh-2rem)] sm:max-h-[calc(100dvh-2rem)] dark:bg-dash-ink dark:ring-white/15">
        <div class="flex shrink-0 items-start justify-between gap-3 border-b border-slate-200 bg-slate-50 px-3 py-2.5 dark:border-white/10 dark:bg-dash-ink sm:px-5 sm:py-3">
            <div class="min-w-0">
                <p class="text-[10px] font-semibold uppercase tracking-widest text-slate-500 dark:text-dash-muted sm:text-xs">Disciplinarios · FO-GJ-51</p>
                <h2 id="fo51-main-modal-title" class="mt-0.5 text-base font-bold text-slate-900 dark:text-white sm:text-lg">Informe disciplinario</h2>
            </div>
            <button type="button" wire:click="closeFo51Modal"
                class="shrink-0 rounded-md p-2 text-slate-500 hover:bg-slate-100 hover:text-slate-900 dark:text-slate-400 dark:hover:bg-white/10 dark:hover:text-white"
                aria-label="Cerrar">
                ✕
            </button>
        </div>

        <div class="flex min-h-0 flex-1 flex-col overflow-hidden">
            @error('fo51_action')
                <div class="mx-3 mt-2 shrink-0 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-800 ring-1 ring-red-200 dark:bg-red-500/15 dark:text-red-200 dark:ring-red-500/30 sm:mx-5">
                    {{ $message }}
                </div>
            @enderror

            @include('disciplinary.forms.partials.fo-gj-51-informe-body', [
                'prefillWorkerName' => $prefillWorkerName,
                'prefillWorkerDocument' => $prefillWorkerDocument,
                'openPdfUploadModal' => $openPdfUploadModal,
                'pdfIframeName' => $pdfIframeName,
                'embedInModal' => true,
                'operacionesReviewers' => $operacionesReviewers ?? collect(),
            ])
        </div>
    </div>
</div>
