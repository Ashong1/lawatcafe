import base from './tailwind.config.js';

/** Tailwind for the guest portal only — see resources/css/portal.css. */
export default {
    ...base,
    content: [
        './resources/views/portal/**/*.blade.php',
        './resources/views/components/agent-chat.blade.php',
        './resources/views/components/partials/agent-chat*.blade.php',
        './resources/views/components/modal-shell.blade.php',
        './resources/views/components/skeleton.blade.php',
    ],
};
