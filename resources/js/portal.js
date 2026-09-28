// The guest portal's script: Alpine and SweetAlert only. The staff app's
// bundle also carries axios, which no portal page uses.
import Alpine from 'alpinejs';
import Swal from 'sweetalert2';

window.Alpine = Alpine;
window.Swal = Swal;

Alpine.start();
