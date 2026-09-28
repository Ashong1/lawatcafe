import './bootstrap';

import Alpine from 'alpinejs';
import Swal from 'sweetalert2';
import registerAgentChat from './agent-chat';

window.Alpine = Alpine;
window.Swal = Swal;

registerAgentChat(Alpine);
Alpine.start();
