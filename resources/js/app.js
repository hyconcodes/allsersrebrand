document.addEventListener('livewire:init', () => {
    Livewire.hook('request', ({ fail }) => {
        fail(({ status, preventDefault }) => {
            if (status === 419) {
                // CSRF token expired? Refresh the page.
                preventDefault();
                window.location.reload();
            }
        });
    });
});
