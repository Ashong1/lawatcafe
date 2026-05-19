@extends('layouts.admin')
@section('title', 'Staff Management')

@section('content')
<div x-data="accountManager()" class="bg-[#FDF8F5] min-h-screen -m-6 p-6 text-[#4A3B32]" style="font-family: 'Montserrat', sans-serif;">
    
    <div class="mb-8 border-b border-[#E6D5C3] pb-4">
        <h2 class="flex items-center gap-3 text-[#3E2723]">
            <span class="text-3xl font-bold pr-1" style="font-family: 'Dancing Script', cursive;">Lawa't</span>
            <span class="text-lg font-bold tracking-[0.2em] uppercase mt-1">Staff Management</span>
        </h2>
        <p class="text-sm text-[#8D6E63] mt-2 font-medium">Control system access, add new baristas, or edit admin accounts.</p>
    </div>

    @if(session('success'))
        <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 4000)" class="mb-6 p-4 bg-green-100 text-green-800 rounded-lg font-bold flex justify-between shadow-sm">
            <span>{{ session('success') }}</span>
            <button @click="show = false">&times;</button>
        </div>
    @endif
    @if(session('error'))
        <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 4000)" class="mb-6 p-4 bg-red-100 text-red-800 rounded-lg font-bold flex justify-between shadow-sm">
            <span>{{ session('error') }}</span>
            <button @click="show = false">&times;</button>
        </div>
    @endif

    <div class="bg-white p-6 md:p-8 rounded-xl shadow-sm border border-[#F0E6D2]">
        
        <div class="flex justify-between items-center mb-6 border-b border-[#F0E6D2] pb-6">
            <div>
                <h3 class="text-lg font-bold text-[#3E2723] uppercase tracking-widest">Active Accounts</h3>
            </div>
            <button @click="openAddModal()" class="bg-[#3E2723] hover:bg-[#271815] text-white px-6 py-3 rounded-lg font-bold transition shadow-md text-sm tracking-widest uppercase">
                + Add Staff Member
            </button>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-[#FDF8F5] text-[#8D6E63] text-xs uppercase tracking-wider border-b border-[#E6D5C3]">
                        <th class="px-6 py-4 font-bold rounded-tl-lg">Name</th>
                        <th class="px-6 py-4 font-bold">Email Address</th>
                        <th class="px-6 py-4 font-bold">Joined Date</th>
                        <th class="px-6 py-4 font-bold rounded-tr-lg text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="text-[#4A3B32] text-sm">
                    @forelse($users as $user)
                        <tr class="border-b border-[#F0E6D2] hover:bg-[#FAFAFA] transition">
                            <td class="px-6 py-4 font-bold text-[#3E2723] text-base">
                                {{ $user->name }}
                                @if(auth()->id() === $user->id)
                                    <span class="ml-2 text-[10px] bg-amber-100 text-amber-800 px-2 py-1 rounded-full uppercase tracking-wider">You</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-[#8D6E63] font-medium">{{ $user->email }}</td>
                            <td class="px-6 py-4 text-[#8D6E63] font-medium">{{ $user->created_at->format('M d, Y') }}</td>
                            <td class="px-6 py-4 text-right">
                                <div class="flex justify-end gap-4 font-bold text-xs uppercase tracking-wider">
                                    <button @click="openEditModal({{ $user }})" class="text-amber-700 hover:text-amber-900 transition">Edit</button>
                                    
                                    <form action="{{ route('accounts.destroy', $user->id) }}" method="POST" onsubmit="return confirm('Are you sure you want to remove {{ $user->name }}\'s access?');" class="inline">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-red-500 hover:text-red-700 transition" {{ auth()->id() === $user->id ? 'disabled class=opacity-50 cursor-not-allowed' : '' }}>Remove</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-6 py-8 text-center text-[#8D6E63] italic">No accounts found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div x-show="isModalOpen" style="display: none;" class="fixed inset-0 bg-black bg-opacity-40 backdrop-blur-sm flex items-center justify-center z-50">
        <div @click.away="closeModal()" class="bg-white rounded-xl shadow-2xl p-8 w-full max-w-md border-t-8 border-[#3E2723]">
            <h2 class="text-2xl font-bold text-[#3E2723] mb-6 uppercase tracking-widest" x-text="modalTitle"></h2>
            
            <form :action="formAction" method="POST">
                @csrf
                <template x-if="isEditing">
                    <input type="hidden" name="_method" value="PUT">
                </template>

                <div class="mb-4">
                    <label class="block text-xs font-bold text-[#8D6E63] uppercase tracking-wider mb-2">Full Name</label>
                    <input type="text" name="name" x-model="formData.name" required class="w-full p-3 border border-[#F0E6D2] rounded-lg focus:outline-none focus:border-[#3E2723] bg-[#FAFAFA]">
                </div>

                <div class="mb-4">
                    <label class="block text-xs font-bold text-[#8D6E63] uppercase tracking-wider mb-2">Email Address</label>
                    <input type="email" name="email" x-model="formData.email" required class="w-full p-3 border border-[#F0E6D2] rounded-lg focus:outline-none focus:border-[#3E2723] bg-[#FAFAFA]">
                </div>

                <div class="mb-8">
                    <label class="block text-xs font-bold text-[#8D6E63] uppercase tracking-wider mb-2">Password <span x-show="isEditing" class="text-[10px] text-amber-600 font-normal lowercase">(leave blank to keep current)</span></label>
                    <input type="password" name="password" :required="!isEditing" class="w-full p-3 border border-[#F0E6D2] rounded-lg focus:outline-none focus:border-[#3E2723] bg-[#FAFAFA]">
                </div>

                <div class="flex gap-3">
                    <button type="button" @click="closeModal()" class="flex-1 py-3 border border-[#D7CCC8] rounded-lg text-[#4A3B32] hover:bg-[#FDF8F5] font-bold transition">Cancel</button>
                    <button type="submit" class="flex-1 py-3 bg-[#3E2723] text-white rounded-lg hover:bg-[#271815] font-bold transition">Save Account</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('accountManager', () => ({
            isModalOpen: false,
            isEditing: false,
            modalTitle: 'Add New Staff',
            formAction: '{{ route('accounts.store') }}',
            formData: { id: null, name: '', email: '' },

            openAddModal() {
                this.isEditing = false;
                this.modalTitle = 'Add New Staff';
                this.formAction = '{{ route('accounts.store') }}';
                this.formData = { id: null, name: '', email: '' };
                this.isModalOpen = true;
            },

            openEditModal(user) {
                this.isEditing = true;
                this.modalTitle = 'Edit Account';
                this.formAction = `/accounts/${user.id}`;
                this.formData = { ...user };
                this.isModalOpen = true;
            },

            closeModal() {
                this.isModalOpen = false;
            }
        }))
    });
</script>
@endsection