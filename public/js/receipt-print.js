window.addEventListener('load', async () => {
    if (document.fonts) await document.fonts.ready;
    window.print();
}, { once: true });
