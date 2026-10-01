@extends('layouts.admin')
@section('title', 'Staff Management')

@section('content')
@php
    $onlyStaff = count($assignableRoles) === 1;
    $roleHelp = [
        'staff' => 'Register, orders, kitchen display, and selling Wi-Fi codes.',
        'admin' => 'Everything staff can do, plus reports, inventory, Wi-Fi settings and staff accounts.',
    ];
@endphp
<div x-data="accountManager()" class="bg-[#FDF8F5] min-h-screen -m-4 sm:-m-6 lg:-m-8 p-4 sm:p-6 lg:p-8 text-[#4A3B32]" style="font-family: 'Montserrat', sans-serif;">
    <div class="max-w-7xl mx-auto">
        <div class="lk-page-head mb-8 border-b border-[#E6D5C3] pb-6">
            <h2 class="flex items-center gap-3 text-[#3E2723]">
                <span class="lk-brand text-3xl md:text-4xl tracking-wide font-bold pr-1" style="font-family: 'Dancing Script', cursive;">Lawa't</span>
                <span class="text-lg md:text-xl font-bold tracking-wide uppercase mt-2">Staff Accounts</span>
            </h2>
            <p class="lk-page-desc text-sm text-[#795548] mt-2 font-medium">Add someone and they get an email to choose their own password. Change details or remove people who have left.</p>
        </div>

    <div class="bg-white p-6 md:p-8 rounded-[2rem] shadow-sm border border-[#F0E6D2]">

        <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-4">
            <div>
                <h3 class="text-sm font-bold text-[#3E2723] uppercase tracking-wide">Accounts</h3>
                <p class="text-xs text-[#6D4C41] mt-1 font-medium">Everyone who can sign in to the system.</p>
            </div>

            <button @click="openAddModal()"
                    class="w-full md:w-auto justify-center bg-[#3E2723] hover:bg-[#271815] text-white px-6 py-3 rounded-2xl font-bold text-xs uppercase tracking-wide transition shadow-lg active:scale-95 flex items-center gap-3">
                <x-lucide-user-plus class="w-4 h-4" />
                <span>{{ $onlyStaff ? 'Add Staff Member' : 'Add Someone' }}</span>
            </button>
        </div>

        <div class="overflow-x-auto">
            <table class="lk-stack w-full text-left border-collapse">
                <thead>
                    <tr class="text-[#795548] text-xs uppercase tracking-wide border-b border-[#F0E6D2]">
                        <th class="pb-4 px-4 font-bold">Name</th>
                        <th class="pb-4 px-4 font-bold">Signs in as</th>
                        <th class="pb-4 px-4 font-bold">Role</th>
                        <th class="pb-4 px-4 font-bold">Status</th>
                        <th class="pb-4 px-4 font-bold text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="text-sm">
                    @forelse($users as $user)
                        <tr class="border-b border-[#FAFAFA] group hover:bg-[#FDF8F5]/50 transition-colors">
                            <td class="py-4 px-4 w-full sm:w-auto">
                                <div class="flex items-center gap-2">
                                    <span class="font-extrabold text-[#3E2723]">{{ $user->name }}</span>
                                    @if(auth()->id() === $user->id)
                                        <span class="px-1.5 py-0.5 bg-amber-100 text-amber-800 rounded text-xs font-bold uppercase tracking-wider">You</span>
                                    @endif
                                </div>
                            </td>
                            <td class="py-4 px-4 text-xs">
                                @if($user->username)
                                    <span class="font-bold text-[#3E2723]">{{ $user->username }}</span>
                                @endif
                                <span class="block text-[#795548] font-mono break-all">{{ $user->email }}</span>
                            </td>
                            <td class="py-4 px-4">
                                <span class="px-2.5 py-1 rounded-lg text-xs font-bold uppercase tracking-wide border {{ $user->isAdminOrAbove() ? 'bg-amber-100 text-[#3E2723] border-amber-200' : 'bg-gray-100 text-gray-600 border-gray-200' }}">
                                    {{ $user->roleLabel() }}
                                </span>
                            </td>
                            <td class="py-4 px-4 text-xs">
                                @if($user->isPendingInvite())
                                    <span class="inline-flex items-center gap-1.5 font-bold text-amber-700">
                                        <x-lucide-mail class="w-3.5 h-3.5" /> Waiting to set a password
                                    </span>
                                    <span class="block text-[#6D4C41]">Invited {{ $user->created_at->diffForHumans() }}</span>
                                @else
                                    <span class="inline-flex items-center gap-1.5 font-bold text-green-700">
                                        <x-lucide-check-circle class="w-3.5 h-3.5" /> Active
                                    </span>
                                    <span class="block text-[#6D4C41]">Since {{ $user->created_at->format('M d, Y') }}</span>
                                @endif
                            </td>
                            <td class="py-4 px-4 text-right w-full sm:w-auto">
                                <div class="flex justify-end flex-wrap gap-2">
                                    @if($user->isPendingInvite())
                                        <form action="{{ route('accounts.send-link', $user) }}" method="POST">
                                            @csrf
                                            <button type="submit" class="min-h-[40px] px-3 rounded-xl text-xs font-bold text-amber-800 bg-amber-50 border border-amber-200 hover:bg-amber-100 inline-flex items-center gap-1.5">
                                                <x-lucide-send class="w-3.5 h-3.5" /> Resend
                                            </button>
                                        </form>
                                        <button type="button" @click="showInviteLink({{ Js::from($user->name) }}, {{ Js::from($inviteLinks[$user->id] ?? '') }})"
                                                class="min-h-[40px] px-3 rounded-xl text-xs font-bold text-[#3E2723] bg-[#FDF8F5] border border-[#F0E6D2] hover:bg-[#F0E6D2] inline-flex items-center gap-1.5">
                                            <x-lucide-link class="w-3.5 h-3.5" /> Copy link
                                        </button>
                                    @endif

                                    <button @click="openEditModal({{ Js::from($user->only('id', 'name', 'username', 'email', 'role')) }}, {{ $user->isPendingInvite() ? 'true' : 'false' }})"
                                            class="p-2 text-amber-700 hover:text-amber-900 hover:bg-amber-100 rounded-xl transition-all"
                                            title="Edit" aria-label="Edit {{ $user->name }}">
                                        <x-lucide-pencil class="w-4 h-4" />
                                    </button>

                                    @if(auth()->id() !== $user->id)
                                    <form action="{{ route('accounts.destroy', $user->id) }}" method="POST" id="delete-form-{{ $user->id }}" class="inline">
                                        @csrf
                                        @method('DELETE')
                                        <button type="button"
                                                @click="window.confirmAction({
                                                    title: 'Remove ' + {{ Js::from($user->name) }} + '?',
                                                    text: 'They will no longer be able to sign in. Their past sales and shifts stay in the reports.',
                                                    icon: 'warning',
                                                    confirmText: 'Yes, remove',
                                                    callback: () => document.getElementById('delete-form-{{ $user->id }}').submit()
                                                })"
                                                class="p-2 text-red-400 hover:text-red-600 hover:bg-red-50 rounded-xl transition-all"
                                                title="Remove" aria-label="Remove {{ $user->name }}">
                                            <x-lucide-trash-2 class="w-4 h-4" />
                                        </button>
                                    </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="py-16 text-center text-[#6D4C41]">
                            <x-lucide-users class="w-12 h-12 mb-4 mx-auto opacity-20" />
                            <p class="font-bold text-sm text-[#3E2723]">No staff accounts yet</p>
                            <p class="text-xs mt-1">Tap "{{ $onlyStaff ? 'Add Staff Member' : 'Add Someone' }}" to invite your first barista.</p>
                        </td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if($removed->isNotEmpty())
        <details class="mt-6 bg-white rounded-2xl border border-[#F0E6D2] group">
            <summary class="cursor-pointer list-none flex items-center justify-between gap-3 px-5 min-h-[56px] text-sm font-bold text-[#3E2723]">
                <span>Removed ({{ $removed->count() }})</span>
                <x-lucide-chevron-down class="w-4 h-4 text-[#795548] transition group-open:rotate-180" />
            </summary>
            <div class="px-5 pb-4">
                <p class="text-xs text-[#6D4C41] mb-3">People who left. They can't sign in, but their names stay on their past sales and shifts.</p>
                <ul class="divide-y divide-[#F0E6D2]">
                    @foreach($removed as $person)
                        <li class="py-3 flex items-center justify-between gap-3">
                            <div class="min-w-0">
                                <p class="text-sm font-bold text-[#3E2723] truncate">{{ $person->name }}</p>
                                <p class="text-xs text-[#6D4C41] truncate">{{ $person->username ?? $person->email }} · removed {{ $person->deactivated_at->diffForHumans() }}</p>
                            </div>
                            <form action="{{ route('accounts.restore', $person) }}" method="POST" class="shrink-0">
                                @csrf
                                <button type="submit" class="min-h-[40px] px-3 rounded-xl text-xs font-bold text-[#3E2723] bg-[#FDF8F5] border border-[#F0E6D2] hover:bg-[#F0E6D2] inline-flex items-center gap-1.5">
                                    <x-lucide-rotate-ccw class="w-3.5 h-3.5" /> Restore
                                </button>
                            </form>
                        </li>
                    @endforeach
                </ul>
            </div>
        </details>
    @endif

    <x-modal-shell show="isModalOpen" max-width="xl" panel-class="p-5 sm:p-8 border-t-8 border-[#3E2723]" labelled-by="account-modal-title">
            <h2 id="account-modal-title" class="text-xl sm:text-2xl font-bold text-[#3E2723] mb-1 tracking-tight" x-text="isEditing ? 'Edit ' + (formData.name || 'account') : '{{ $onlyStaff ? 'Add a staff member' : 'Add someone' }}'"></h2>
            <p class="text-xs text-[#795548] mb-6 font-medium" x-text="isEditing ? 'Change their details. To change a password, email them a link below.' : 'They\'ll get an email to choose their own password. You never need to know it.'"></p>

            <form :action="formAction" method="POST" class="space-y-5" @submit="submitting = true">
                @csrf
                <template x-if="isEditing">
                    <input type="hidden" name="_method" value="PUT">
                </template>
                <input type="hidden" name="_editing_id" :value="formData.id">

                <div>
                    <label for="account-name" class="block text-xs font-bold text-[#3E2723] uppercase mb-2 tracking-wide">Full name</label>
                    <input id="account-name" type="text" name="name" x-model="formData.name" @input="suggestUsername()" required autocomplete="off" placeholder="e.g. Ana Santos"
                           class="w-full bg-[#FDF8F5] border-2 @error('name') border-red-500 @else border-[#F0E6D2] @enderror rounded-xl px-4 py-3 text-base sm:text-sm font-bold focus:outline-none focus:border-[#3E2723] transition-all">
                    <x-field-error name="name" />
                </div>

                <div>
                    <label for="account-username" class="block text-xs font-bold text-[#3E2723] uppercase mb-2 tracking-wide">Username</label>
                    <input id="account-username" type="text" name="username" x-model="formData.username" @input="usernameTouched = true; formData.username = formData.username.toLowerCase().replace(/\s+/g, '')"
                           :required="!isEditing" minlength="3" maxlength="30" autocapitalize="none" autocomplete="off" spellcheck="false" placeholder="e.g. ana"
                           class="w-full bg-[#FDF8F5] border-2 @error('username') border-red-500 @else border-[#F0E6D2] @enderror rounded-xl px-4 py-3 text-base sm:text-sm font-bold font-mono focus:outline-none focus:border-[#3E2723] transition-all">
                    <p class="text-xs text-[#6D4C41] mt-1.5">What they type to sign in. Small letters and numbers, no spaces.</p>
                    <x-field-error name="username" />
                </div>

                <div>
                    <label for="account-email" class="block text-xs font-bold text-[#3E2723] uppercase mb-2 tracking-wide">Email</label>
                    <input id="account-email" type="email" name="email" x-model="formData.email" required autocapitalize="none" autocomplete="off" spellcheck="false" placeholder="ana@gmail.com"
                           class="w-full bg-[#FDF8F5] border-2 @error('email') border-red-500 @else border-[#F0E6D2] @enderror rounded-xl px-4 py-3 text-base sm:text-sm font-bold focus:outline-none focus:border-[#3E2723] transition-all">
                    <p class="text-xs text-[#6D4C41] mt-1.5" x-show="!isEditing">The invite goes here. They can also sign in with it.</p>
                    <x-field-error name="email" />
                </div>

                @if($onlyStaff)
                    <input type="hidden" name="role" value="staff">
                @else
                    <fieldset>
                        <legend class="block text-xs font-bold text-[#3E2723] uppercase mb-2 tracking-wide">What they can do</legend>
                        <div class="space-y-2">
                            @foreach($assignableRoles as $value => $label)
                                <label class="flex items-start gap-3 p-3 rounded-xl border-2 cursor-pointer transition"
                                       :class="formData.role === '{{ $value }}' ? 'border-[#3E2723] bg-[#FDF8F5]' : 'border-[#F0E6D2]'">
                                    <input type="radio" name="role" value="{{ $value }}" x-model="formData.role" class="mt-0.5 text-[#3E2723] focus:ring-[#3E2723]">
                                    <span>
                                        <span class="block text-sm font-bold text-[#3E2723]">{{ $label }}</span>
                                        <span class="block text-xs text-[#6D4C41] mt-0.5">{{ $roleHelp[$value] ?? '' }}</span>
                                    </span>
                                </label>
                            @endforeach
                        </div>
                        <x-field-error name="role" />
                    </fieldset>
                @endif

                <div x-show="!isEditing" class="rounded-xl bg-amber-50 border border-amber-200 p-4 text-xs text-amber-900">
                    <p class="font-bold mb-1.5">What happens next</p>
                    <ol class="list-decimal ml-4 space-y-0.5">
                        <li>We email <span class="font-bold" x-text="formData.email || 'them'"></span> a link.</li>
                        <li>They open it on the shop Wi-Fi and choose a password.</li>
                        <li>They sign in as <span class="font-bold font-mono" x-text="formData.username || 'their username'"></span>.</li>
                    </ol>
                    <p class="mt-2">No internet right now? The email goes out once it's back, or use <span class="font-bold">Copy link</span> in the list.</p>
                </div>

                <div class="flex gap-3 pt-2">
                    <button type="button" @click="closeModal()" class="flex-1 py-4 bg-[#FDF8F5] text-[#795548] rounded-xl font-bold text-xs uppercase tracking-wide hover:bg-[#F0E6D2] transition">Cancel</button>
                    <x-submit-button label="Save" x-show="isEditing" />
                    <x-submit-button label="Send invite" loading-label="Sending..." x-show="!isEditing" />
                </div>
            </form>

            {{-- Separate form: forms can't nest. --}}
            <form x-show="isEditing" :action="'/accounts/' + formData.id + '/send-link'" method="POST" class="mt-5 pt-5 border-t border-[#F0E6D2]">
                @csrf
                <p class="text-xs font-bold text-[#3E2723] uppercase tracking-wide mb-1" x-text="editingPending ? 'Invite' : 'Password'"></p>
                <p class="text-xs text-[#6D4C41] mb-3" x-text="editingPending ? 'They haven\'t chosen a password yet.' : 'Forgot it? Email them a link to choose a new one. Their current password keeps working until they do.'"></p>
                <button type="submit" class="min-h-[44px] w-full sm:w-auto px-4 rounded-xl text-xs font-bold uppercase tracking-wide text-amber-800 bg-amber-50 border border-amber-200 hover:bg-amber-100 inline-flex items-center justify-center gap-2">
                    <x-lucide-send class="w-4 h-4" /> <span x-text="editingPending ? 'Send the invite again' : 'Email a new-password link'"></span>
                </button>
            </form>
    </x-modal-shell>
</div>

<script>
    document.addEventListener('alpine:init', () => {
        const blank = { id: null, name: '', username: '', email: '', role: 'staff' };
        const taken = @js($users->pluck('username')->filter()->values());
        const old = @js(session()->hasOldInput() ? [
            'id' => old('_editing_id') ?: null,
            'name' => old('name', ''),
            'username' => old('username', ''),
            'email' => old('email', ''),
            'role' => old('role', 'staff'),
        ] : null);

        Alpine.data('accountManager', () => ({
            // Reopens with what was typed after a validation error, so a typo
            // doesn't mean filling the whole form in again.
            isModalOpen: {{ $errors->any() ? 'true' : 'false' }},
            isEditing: !!(old && old.id),
            editingPending: false,
            submitting: false,
            usernameTouched: !!(old && old.username),
            formAction: old && old.id ? `/accounts/${old.id}` : @js(route('accounts.store')),
            formData: old ? { ...blank, ...old } : { ...blank },

            openAddModal() {
                this.isEditing = false;
                this.usernameTouched = false;
                this.formAction = @js(route('accounts.store'));
                this.formData = { ...blank };
                this.submitting = false;
                this.isModalOpen = true;
                this.$nextTick(() => document.getElementById('account-name')?.focus());
            },

            openEditModal(user, pending) {
                this.isEditing = true;
                this.editingPending = pending;
                this.usernameTouched = true;
                this.formAction = `/accounts/${user.id}`;
                this.formData = { ...blank, ...user, username: user.username || '' };
                // Older accounts have no username yet: offer one.
                if (!this.formData.username) { this.usernameTouched = false; this.suggestUsername(); }
                this.submitting = false;
                this.isModalOpen = true;
            },

            // First name, lower-cased, accents and symbols dropped; a number
            // added if someone already has it. Stops once the owner types their own.
            suggestUsername() {
                if (this.usernameTouched) return;
                const first = (this.formData.name || '').trim().split(/\s+/)[0] || '';
                const base = first.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase().replace(/[^a-z0-9]/g, '');
                if (base.length < 3) { this.formData.username = base; return; }
                let candidate = base, n = 2;
                while (taken.includes(candidate)) candidate = base + n++;
                this.formData.username = candidate;
            },

            // Fallback when the email can't get out: the owner sends the link
            // another way (Messenger, text).
            showInviteLink(name, link) {
                Swal.fire({
                    title: 'Invite link for ' + name,
                    html: 'Send this to them by text or Messenger. It works once, for {{ \App\Services\AccountInviteService::LINK_DAYS }} days, on the shop Wi-Fi.',
                    input: 'text',
                    inputValue: link,
                    inputAttributes: { readonly: true },
                    confirmButtonText: 'Copy',
                    showCancelButton: true,
                    cancelButtonText: 'Close',
                    confirmButtonColor: '#3E2723',
                    preConfirm: () => {
                        const input = Swal.getInput();
                        input.select();
                        // navigator.clipboard needs HTTPS; the shop uses plain HTTP.
                        const ok = navigator.clipboard && window.isSecureContext
                            ? navigator.clipboard.writeText(link).then(() => true, () => document.execCommand('copy'))
                            : document.execCommand('copy');
                        return Promise.resolve(ok).then((copied) => {
                            if (!copied) { Swal.showValidationMessage('Couldn\'t copy. Press and hold the link to copy it.'); return false; }
                        });
                    },
                }).then((r) => { if (r.isConfirmed) Swal.fire({ icon: 'success', title: 'Link copied', timer: 1500, showConfirmButton: false }); });
            },

            closeModal() {
                this.isModalOpen = false;
            }
        }))
    });
</script>
@endsection
