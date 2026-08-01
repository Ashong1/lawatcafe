@extends('layouts.admin')
@section('title', 'Staff Management')

@section('content')
<div x-data="accountManager()" class="bg-[#FDF8F5] min-h-screen -m-6 p-6 md:p-8 text-[#4A3B32]" style="font-family: 'Montserrat', sans-serif;">
    <div class="max-w-7xl mx-auto">
        <div class="mb-8 border-b border-[#E6D5C3] pb-6">
            <h2 class="text-3xl font-black text-[#3E2723] tracking-wider uppercase italic" style="font-family: 'Dancing Script', cursive;">Lawa't <span class="font-sans not-italic font-bold text-[#4A3B32] text-2xl tracking-[0.2em]">STAFF MANAGEMENT</span></h2>
            <p class="text-sm text-[#8D6E63] mt-2 font-medium">Control system access, assign roles, and edit admin accounts.</p>
        </div>

    <div class="bg-white p-6 md:p-8 rounded-[2rem] shadow-sm border border-[#F0E6D2]">
        
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-4">
            <div>
                <h3 class="text-sm font-black text-[#3E2723] uppercase tracking-widest">Active Accounts</h3>
                <p class="text-xs text-[#6D4C41] mt-1 font-medium italic">System users with authenticated access to the dashboard.</p>
            </div>
            
            <button @click="openAddModal()" 
                    class="bg-[#3E2723] hover:bg-[#271815] text-white px-6 py-3 rounded-2xl font-black text-[10px] uppercase tracking-widest transition shadow-lg active:scale-95 flex items-center gap-3">
                <x-lucide-plus class="w-4 h-4" />
                <span>Add Staff Member</span>
            </button>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="text-[#8D6E63] text-[10px] uppercase tracking-[0.2em] border-b border-[#F0E6D2]">
                        <th class="pb-4 px-4 font-black">Name</th>
                        <th class="pb-4 px-4 font-black">Email Address</th>
                        <th class="pb-4 px-4 font-black">Role / Access</th>
                        <th class="pb-4 px-4 font-black hidden md:table-cell">Joined Date</th>
                        <th class="pb-4 px-4 font-black text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="text-sm">
                    @forelse($users as $user)
                        <tr class="border-b border-[#FAFAFA] group hover:bg-[#FDF8F5]/50 transition-colors {{ auth()->id() === $user->id ? 'bg-amber-50/10' : '' }}">
                            <td class="py-4 px-4">
                                <div class="flex items-center gap-2">
                                    <span class="font-extrabold text-[#3E2723]">{{ $user->name }}</span>
                                    @if(auth()->id() === $user->id)
                                        <span class="px-1.5 py-0.5 bg-amber-100 text-amber-800 rounded text-[9px] font-black uppercase tracking-wider">You</span>
                                    @endif
                                </div>
                                <span class="text-[10px] text-[#6D4C41] font-medium md:hidden">Joined {{ $user->created_at->format('M d, Y') }}</span>
                            </td>
                            <td class="py-4 px-4 text-[#8D6E63] font-medium font-mono text-xs">
                                {{ $user->email }}
                            </td>
                            <td class="py-4 px-4">
                                @if($user->role === 'admin')
                                    <span class="px-2.5 py-1 bg-amber-100 text-[#3E2723] border border-amber-200 rounded-lg text-[10px] font-black uppercase tracking-widest">
                                        System Admin
                                    </span>
                                @else
                                    <span class="px-2.5 py-1 bg-gray-100 text-gray-600 border border-gray-200 rounded-lg text-[10px] font-black uppercase tracking-widest">
                                        Barista
                                    </span>
                                @endif
                            </td>
                            <td class="py-4 px-4 text-[#6D4C41] font-bold text-xs hidden md:table-cell">
                                {{ $user->created_at->format('M d, Y') }}
                            </td>
                            <td class="py-4 px-4 text-right">
                                <div class="flex justify-end gap-3">
                                    <button @click="openEditModal({{ $user }})"
                                            class="p-2 text-amber-700 hover:text-amber-900 hover:bg-amber-100 rounded-xl transition-all"
                                            title="Edit Staff" aria-label="Edit Staff">
                                        <x-lucide-pencil class="w-4 h-4" />
                                    </button>
                                    
                                    @if(auth()->id() !== $user->id)
                                    <form action="{{ route('accounts.destroy', $user->id) }}" method="POST" id="delete-form-{{ $user->id }}" class="inline">
                                        @csrf
                                        @method('DELETE')
                                        <button type="button" 
                                                @click="window.confirmAction({
                                                    title: 'Remove Staff?',
                                                    text: 'Are you sure you want to remove {{ $user->name }}\'s access?',
                                                    icon: 'error',
                                                    confirmText: 'Yes, Remove Access',
                                                    callback: () => document.getElementById('delete-form-{{ $user->id }}').submit()
                                                })"
                                                class="p-2 text-red-400 hover:text-red-600 hover:bg-red-50 rounded-xl transition-all"
                                                title="Remove Access" aria-label="Remove Access">
                                            <x-lucide-trash-2 class="w-4 h-4" />
                                        </button>
                                    </form>
                                    @else
                                        <button class="p-2 text-gray-300 cursor-not-allowed opacity-30" title="Cannot delete active account" aria-label="Cannot delete active account" disabled>
                                            <x-lucide-trash-2 class="w-4 h-4" />
                                        </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="py-20 text-center text-[#6D4C41]">
                            <x-lucide-users class="w-12 h-12 mb-4 mx-auto opacity-20" />
                            <p class="font-bold uppercase tracking-widest text-xs">No staff accounts found.</p>
                        </td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Account Modal -->
    <x-modal-shell show="isModalOpen" max-width="xl" panel-class="p-8 border-t-8 border-[#3E2723]" labelled-by="account-modal-title">
            <h2 id="account-modal-title" class="text-2xl font-black text-[#3E2723] mb-2 uppercase tracking-tight" x-text="modalTitle"></h2>
            <p class="text-xs text-[#8D6E63] mb-8 font-medium">Configure authentication and system permission levels.</p>

            <form :action="formAction" method="POST" class="space-y-6" @submit="submitting = true">
                @csrf
                <template x-if="isEditing">
                    <input type="hidden" name="_method" value="PUT">
                </template>

                <div>
                    <label for="account-name" class="block text-[10px] font-black text-[#3E2723] uppercase mb-2 tracking-widest">Full Name</label>
                    <input id="account-name" type="text" name="name" x-model="formData.name" required class="w-full bg-[#FDF8F5] border-2 @error('name') border-red-500 @enderror rounded-xl px-4 py-3 text-sm font-bold focus:outline-none focus:border-[#3E2723] transition-all">
                    <x-field-error name="name" />
                </div>

                <div>
                    <label for="account-email" class="block text-[10px] font-black text-[#3E2723] uppercase mb-2 tracking-widest">Email Address</label>
                    <input id="account-email" type="email" name="email" x-model="formData.email" required class="w-full bg-[#FDF8F5] border-2 @error('email') border-red-500 @enderror rounded-xl px-4 py-3 text-sm font-bold focus:outline-none focus:border-[#3E2723] transition-all">
                    <x-field-error name="email" />
                </div>

                <div>
                    <label for="account-role" class="block text-[10px] font-black text-[#3E2723] uppercase mb-2 tracking-widest">Account Role</label>
                    <select id="account-role" name="role" x-model="formData.role" class="w-full bg-[#FDF8F5] border-2 @error('role') border-red-500 @enderror rounded-xl px-4 py-3 text-sm font-bold focus:outline-none focus:border-[#3E2723] transition-all">
                        @foreach($assignableRoles as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    <x-field-error name="role" />
                </div>

                <div>
                    <label for="account-password" class="block text-[10px] font-black text-[#3E2723] uppercase mb-2 tracking-widest">Password <span x-show="isEditing" class="text-[9px] text-amber-600 font-bold lowercase">(leave blank to keep current)</span></label>
                    <input id="account-password" type="password" name="password" :required="!isEditing" class="w-full bg-[#FDF8F5] border-2 @error('password') border-red-500 @enderror rounded-xl px-4 py-3 text-sm font-bold focus:outline-none focus:border-[#3E2723] transition-all">
                    <x-field-error name="password" />
                </div>

                <div class="flex gap-3 pt-4">
                    <button type="button" @click="closeModal()" class="flex-1 py-4 bg-[#FDF8F5] text-[#8D6E63] rounded-xl font-bold text-xs uppercase tracking-widest hover:bg-[#F0E6D2] transition">Cancel</button>
                    <x-submit-button label="Save Account" />
                </div>
            </form>
    </x-modal-shell>
</div>

<script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('accountManager', () => ({
            // Reopens itself on a validation-failure redirect so the
            // per-field errors above are actually visible.
            isModalOpen: {{ $errors->any() ? 'true' : 'false' }},
            isEditing: false,
            submitting: false,
            modalTitle: 'Add New Staff',
            formAction: '{{ route('accounts.store') }}',
            formData: { id: null, name: '', email: '', role: 'staff' },

            openAddModal() {
                this.isEditing = false;
                this.modalTitle = 'Add New Staff';
                this.formAction = '{{ route('accounts.store') }}';
                this.formData = { id: null, name: '', email: '', role: 'staff' };
                this.submitting = false;
                this.isModalOpen = true;
            },

            openEditModal(user) {
                this.isEditing = true;
                this.modalTitle = 'Edit Account';
                this.formAction = `/accounts/${user.id}`;
                this.formData = { ...user };
                this.submitting = false;
                this.isModalOpen = true;
            },

            closeModal() {
                this.isModalOpen = false;
            }
        }))
    });
</script>
@endsection