import './echo';
import './video-autoplay';

window.guardFileSize = (input, maxKb) => {
    for (const file of (input.files || [])) {
        if (file.size > maxKb * 1024) {
            window.dispatchEvent(new CustomEvent('toast', {
                detail: {
                    type: 'warning',
                    title: 'File Too Large',
                    message: `"${file.name}" must be ${maxKb / 1024}MB or less.`,
                },
            }));
            input.value = '';
            return;
        }
    }
};

document.addEventListener('livewire:init', () => {
    Livewire.hook('request', ({ fail }) => {
        fail(({ status, preventDefault }) => {
            if (status === 419) {
                preventDefault();
                window.location.reload();
            }
        });
    });
});
