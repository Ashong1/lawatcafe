// The guest portal's script: Alpine, SweetAlert and the AI chat. The staff
// app's bundle also carries axios, which no portal page uses.
import Alpine from 'alpinejs';
import Swal from 'sweetalert2';
import registerAgentChat from './agent-chat';

window.Alpine = Alpine;
window.Swal = Swal;

registerAgentChat(Alpine);
Alpine.start();
