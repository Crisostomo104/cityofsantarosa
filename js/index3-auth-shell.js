(() => {
    const dialog = document.getElementById('loadsssProfileDialog');
    const openButton = document.getElementById('openLoadsssProfile');
    const closeButton = document.getElementById('closeLoadsssProfile');
    if (!dialog || !openButton || !closeButton) return;

    openButton.addEventListener('click', () => dialog.showModal());
    closeButton.addEventListener('click', () => dialog.close());
    dialog.addEventListener('click', event => {
        if (event.target === dialog) dialog.close();
    });
})();
