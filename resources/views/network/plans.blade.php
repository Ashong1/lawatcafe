@extends('layouts.admin')
@section('title', 'Wi-Fi Plans Management')

@section('content')
<style>
    /* Hide number input spinners for a cleaner look */
    input::-webkit-outer-spin-button,
    input::-webkit-inner-spin-button {
        -webkit-appearance: none;
        margin: 0;
    }
    input[type=number] {
        -moz-appearance: textfield;
    }
    
    /* 100% FOOLPROOF OVERRIDE FOR TAILWIND FORMS PLUGIN */
    .modal-clean-input, .modal-clean-input:focus {
        border: none !important;
        box-shadow: none !important;
        outline: none !important;
        --tw-ring-color: transparent !important;
        --tw-ring-shadow: 0 0 transparent !important;
        --tw-border-opacity: 0 !important;
    }
</style>

<div class="bg-[#FDF8F5] min-h-screen -m-6 p-6 md:p-8 text-[#4A3B32]" 
     style="font-family: 'Montserrat', sans-serif;"
     x-data="wifiPlans()">
    
    <div class="max-w-6xl mx-auto">
        <div class="mb-8 border-b border-[#E6D5C3] pb-6 flex flex-col md:flex-row md:items-end justify-between gap-4">
            <div>
                <h2 class="flex items-center gap-3 text-[#3E2723]">
                    <span class="text-3xl md:text-4xl tracking-wide font-bold pr-1" style="font-family: 'Dancing Script', cursive;">Lawa't</span>
                    <span class="text-lg md:text-xl font-bold tracking-[0.2em] uppercase mt-2">Wi-Fi Plans</span>
                </h2>
                <p class="text-sm text-[#8D6E63] mt-2 font-medium tracking-wide">Configure pricing tiers and session durations for your guest network.</p>
            </div>
        </div>

        <div class="bg-white p-6 md:p-8 rounded-3xl shadow-sm border border-[#F0E6D2]">
            
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6 gap-4">
                <div>
                    <h3 class="text-sm font-black text-[#3E2723] uppercase tracking-widest flex items-center gap-2">
                        <x-lucide-list-checks class="w-5 h-5 text-amber-600" />
                        Active Pricing Tiers
                    </h3>
                    <p class="text-xs text-[#6D4C41] mt-1 font-medium italic">Manage how much customers pay for specific Wi-Fi durations.</p>
                </div>
                
                <button type="button" @click="openModalForAdd()" 
                        class="bg-[#3E2723] hover:bg-[#271815] text-white px-6 py-3 rounded-2xl font-black text-[10px] uppercase tracking-widest transition shadow-lg active:scale-95 flex items-center gap-3">
                    <x-lucide-plus class="w-4 h-4" />
                    Add New Tier
                </button>
            </div>

            <form action="{{ route('admin.settings.store.update') }}" method="POST">
                @csrf
                
                {{-- Hidden Inputs for the Form to save Complimentary Data --}}
                <input type="hidden" name="free_wifi_min_amount" :value="freeMinAmount">
                <input type="hidden" name="free_wifi_duration" :value="freeDuration">

                <div class="overflow-x-auto rounded-2xl border border-[#F0E6D2] mb-8">
                    <table class="w-full text-left border-collapse bg-[#FAFAFA]">
                        <thead>
                            <tr class="border-b border-[#F0E6D2] bg-[#FDF8F5] text-[10px] font-black text-[#8D6E63] uppercase tracking-widest">
                                <th class="py-4 px-6">Tier</th>
                                <th class="py-4 px-6">Price / Trigger</th>
                                <th class="py-4 px-6">Allocated Speed</th>
                                <th class="py-4 px-6">Duration (Minutes)</th>
                                <th class="py-4 px-6">Formatted Display</th>
                                <th class="py-4 px-6 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#F0E6D2] bg-white text-sm font-medium text-[#4A3B32]">
                            {{-- Complimentary Tier (Modal Editable) --}}
                            <tr class="hover:bg-amber-50/30 transition-colors bg-amber-50/10">
                                <td class="py-4 px-6">
                                    <span class="px-2.5 py-1 bg-amber-100 text-amber-800 border border-amber-200 rounded-md text-[10px] font-black uppercase tracking-wide">
                                        Complimentary
                                    </span>
                                </td>
                                <td class="py-4 px-6">
                                    <div class="flex flex-col">
                                        <span class="font-bold text-[#3E2723] text-sm" x-text="'Min. Order: ₱' + freeMinAmount"></span>
                                        <span class="text-[9px] text-amber-600 font-bold uppercase mt-1">Auto-Provisioned</span>
                                    </div>
                                </td>
                                <td class="py-4 px-6">
                                    <div class="flex flex-col">
                                        <div class="flex items-center gap-1 text-[9px] font-bold text-blue-600 uppercase">
                                            <x-lucide-arrow-down class="w-2.5 h-2.5" />
                                            <span>{{ \App\Models\Setting::get('bw_free_down', 2) }} Mbps</span>
                                        </div>
                                        <div class="flex items-center gap-1 text-[9px] font-bold text-amber-600 uppercase mt-0.5">
                                            <x-lucide-arrow-up class="w-2.5 h-2.5" />
                                            <span>{{ \App\Models\Setting::get('bw_free_up', 1) }} Mbps</span>
                                        </div>
                                    </div>
                                </td>
                                <td class="py-4 px-6">
                                    <span class="font-medium text-[#4A3B32] text-sm" x-text="freeDuration + ' mins'"></span>
                                </td>
                                <td class="py-4 px-6">
                                    <span class="text-xs font-bold text-amber-600 uppercase tracking-tight italic" 
                                          x-text="formatDuration(freeDuration)"></span>
                                </td>
                                <td class="py-4 px-6 text-right">
                                    <button type="button" @click="openModalForComplimentary()" title="Edit Complimentary Rules" aria-label="Edit Complimentary Rules"
                                            class="p-2 text-amber-700 hover:text-amber-900 hover:bg-amber-100/50 rounded-xl transition-all">
                                        <x-lucide-pencil class="w-4 h-4" />
                                    </button>
                                </td>
                            </tr>

                            <template x-for="(plan, index) in plans" :key="plan.id">
                                <tr class="hover:bg-amber-50/20 transition-colors">
                                    <td class="py-4 px-6">
                                        <span class="px-2.5 py-1 bg-[#FAFAFA] border border-[#F0E6D2] rounded-md text-[10px] font-black text-[#8D6E63] uppercase tracking-wide"
                                              x-text="'Tier ' + (index + 1)"></span>
                                    </td>
                                    <td class="py-4 px-6 font-black text-[#3E2723]" x-text="'₱' + plan.price"></td>
                                    <td class="py-4 px-6">
                                        <div class="flex flex-col">
                                            <div class="flex items-center gap-1 text-[9px] font-bold text-blue-600 uppercase">
                                                <x-lucide-arrow-down class="w-2.5 h-2.5" />
                                                <span x-text="(index === 0 ? '{{ \App\Models\Setting::get('bw_free_down', 2) }}' : '{{ \App\Models\Setting::get('bw_premium_down', 10) }}') + ' Mbps'"></span>
                                            </div>
                                            <div class="flex items-center gap-1 text-[9px] font-bold text-amber-600 uppercase mt-0.5">
                                                <x-lucide-arrow-up class="w-2.5 h-2.5" />
                                                <span x-text="(index === 0 ? '{{ \App\Models\Setting::get('bw_free_up', 1) }}' : '{{ \App\Models\Setting::get('bw_premium_up', 5) }}') + ' Mbps'"></span>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="py-4 px-6 text-[#8D6E63]" x-text="plan.minutes + ' mins'"></td>
                                    <td class="py-4 px-6">
                                        <span class="text-xs font-bold text-amber-600 uppercase tracking-tight italic" 
                                              x-text="formatDuration(plan.minutes)"></span>
                                    </td>
                                    <td class="py-4 px-6 text-right">
                                        <div class="flex items-center justify-end gap-2">
                                            <button type="button" @click="openModalForEdit(plan, index)" title="Edit Tier" aria-label="Edit Tier"
                                                    class="p-2 text-amber-700 hover:text-amber-900 hover:bg-amber-100/50 rounded-xl transition-all">
                                                <x-lucide-pencil class="w-4 h-4" />
                                            </button>
                                            <button type="button" @click="removePlan(index)" title="Remove Tier" aria-label="Remove Tier"
                                                    class="p-2 text-red-400 hover:text-red-600 hover:bg-red-50 rounded-xl transition-all">
                                                <x-lucide-trash-2 class="w-4 h-4" />
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </template>

                            <template x-if="plans.length === 0">
                                <tr>
                                    <td colspan="6" class="py-16 text-center">
                                        <div class="flex flex-col items-center justify-center">
                                            <div class="w-16 h-16 bg-[#FAFAFA] rounded-full flex items-center justify-center border border-[#F0E6D2] mb-4">
                                                <x-lucide-wifi class="w-8 h-8 text-amber-200" />
                                            </div>
                                            <p class="text-xs font-black text-[#6D4C41] uppercase tracking-widest">No plans defined yet</p>
                                            <p class="text-[11px] text-[#D7CCC8] mt-1 font-medium">Click "Add New Tier" to populate pricing structures.</p>
                                        </div>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>

                <div class="grid grid-cols-1 xl:grid-cols-12 gap-6 pt-8 border-t border-[#F0E6D2] mt-6">
                    
                    <div class="xl:col-span-8 flex flex-row items-start bg-amber-50/50 p-5 md:p-6 rounded-3xl border border-amber-100/50 shadow-sm h-full">
                        <div class="w-12 h-12 bg-amber-100 rounded-2xl flex items-center justify-center shrink-0 mr-5 mt-0.5">
                            <x-lucide-lightbulb class="w-6 h-6 text-amber-600" />
                        </div>
                        <div class="flex-1 min-w-0">
                            <h4 class="text-sm font-black text-amber-900 uppercase tracking-widest mb-2">Strategy Note</h4>
                            <p class="text-sm text-amber-800 font-medium leading-relaxed">
                                The system uses the <span class="font-bold underline decoration-amber-500/30 underline-offset-4">lowest priced plan</span> as the default for manual batch voucher generation. Ensure durations are balanced to encourage higher-tier purchases.
                            </p>
                        </div>
                    </div>

                    <div class="xl:col-span-4 flex items-center justify-end h-full">
                        <button type="submit" 
                                class="w-full h-full min-h-[70px] px-6 py-4 bg-[#3E2723] hover:bg-[#271815] text-white rounded-2xl font-black text-xs md:text-sm uppercase tracking-[0.15em] transition shadow-xl shadow-amber-900/20 active:scale-95 flex items-center justify-center gap-3">
                            <x-lucide-save class="w-5 h-5 shrink-0" />
                            <span>Save Configuration</span>
                        </button>
                    </div>
                </div>

                <input type="hidden" name="voucher_durations" :value="jsonOutput">
            </form>
        </div>
    </div>

    <x-modal-shell show="modalOpen" max-width="md" labelled-by="plan-modal-title">
            <div class="bg-[#FDF8F5] border-b border-[#F0E6D2] px-6 py-5 flex items-center justify-between">
                <h3 id="plan-modal-title" class="text-sm font-black text-[#3E2723] uppercase tracking-widest flex items-center gap-2">
                    <x-lucide-wifi class="w-4 h-4 text-amber-600" />
                    <span x-text="isEditingComplimentary ? 'Modify Complimentary Tier' : (isEditMode ? 'Modify Pricing Tier' : 'Add New Pricing Tier')"></span>
                </h3>
                <button type="button" @click="modalOpen = false" aria-label="Close dialog" class="text-[#6D4C41] hover:text-[#3E2723] transition-colors">
                    <x-lucide-x class="w-5 h-5" />
                </button>
            </div>

            <div class="p-6 space-y-5">
                <div>
                    <label for="plan-price" class="block text-[10px] font-black text-[#6D4C41] uppercase tracking-widest ml-1 mb-1.5"
                           x-text="isEditingComplimentary ? 'Minimum Order Amount (₱)' : 'Price (₱)'"></label>
                    <div class="flex items-center bg-[#FAFAFA] border border-gray-200 rounded-xl focus-within:border-[#3E2723] focus-within:ring-1 focus-within:ring-[#3E2723] transition-all group overflow-hidden">
                        <span class="pl-4 pr-1 text-xl font-black text-[#6D4C41] group-focus-within:text-[#3E2723] transition-colors select-none pointer-events-none">₱</span>
                        <input type="number" id="plan-price" x-model="modalData.price"
                               class="modal-clean-input flex-1 py-3.5 pr-4 pl-0 bg-transparent text-xl font-black text-[#3E2723] w-full"
                               placeholder="20">
                    </div>
                </div>

                <div>
                    <label for="plan-minutes" class="block text-[10px] font-black text-[#6D4C41] uppercase tracking-widest ml-1 mb-1.5">Duration</label>
                    <div class="flex items-center bg-[#FAFAFA] border border-gray-200 rounded-xl focus-within:border-[#3E2723] focus-within:ring-1 focus-within:ring-[#3E2723] transition-all group overflow-hidden">
                        <input type="number" id="plan-minutes" x-model="modalData.minutes"
                               class="modal-clean-input flex-1 py-3.5 pl-5 pr-2 bg-transparent text-xl font-black text-[#3E2723] w-full"
                               placeholder="60">
                        <span class="pr-5 text-[10px] font-black text-[#6D4C41] uppercase tracking-widest select-none pointer-events-none">Mins</span>
                    </div>
                    <div class="flex justify-end mt-1.5 mr-1">
                        <span class="text-[10px] font-bold text-amber-600 uppercase tracking-tight italic" 
                              x-text="formatDuration(modalData.minutes)"></span>
                    </div>
                </div>
            </div>

            <div class="bg-[#FAFAFA] border-t border-[#F0E6D2] px-6 py-4 flex justify-end gap-3">
                <button type="button" @click="modalOpen = false"
                        class="px-5 py-3 bg-white border border-[#F0E6D2] hover:bg-gray-50 rounded-xl text-[10px] font-black text-[#8D6E63] uppercase tracking-widest transition active:scale-95">
                    Cancel
                </button>
                <button type="button" @click="saveModalData()"
                        class="px-5 py-3 bg-[#3E2723] hover:bg-[#271815] text-white rounded-xl text-[10px] font-black text-[#8D6E63] uppercase tracking-widest transition shadow-md active:scale-95">
                    Save Tier
                </button>
            </div>
    </x-modal-shell>

</div>

<script>
function wifiPlans() {
    let rawData = {!! $settings['voucher_durations'] ?: '{}' !!};
    let plansArray = [];
    
    try {
        if (typeof rawData === 'string') {
            rawData = JSON.parse(rawData);
        }
        plansArray = Object.entries(rawData).map(([price, minutes]) => ({
            id: Date.now() + Math.random(), 
            price: price,
            minutes: minutes
        }));
        
        plansArray.sort((a, b) => parseFloat(a.price) - parseFloat(b.price));
    } catch (e) {
        console.error('Failed to parse voucher_durations', e);
        plansArray = [];
    }

    return {
        plans: plansArray,
        jsonOutput: JSON.stringify(rawData),
        
        // --- NEW COMPLIMENTARY STATE VARIABLES ---
        freeMinAmount: {{ $settings['free_wifi_min_amount'] ?? 200 }},
        freeDuration: {{ $settings['free_wifi_duration'] ?? 60 }},
        isEditingComplimentary: false,
        // -----------------------------------------

        // Modal State Machine
        modalOpen: false,
        isEditMode: false,
        editingIndex: null,
        modalData: {
            price: '',
            minutes: ''
        },

        openAddModal() {
            this.isEditMode = false;
            this.isEditingComplimentary = false;
            this.editingIndex = null;
            this.modalData.price = '';
            this.modalData.minutes = '';
            this.modalOpen = true;
        },

        openModalForEdit(plan, index) {
            this.isEditMode = true;
            this.isEditingComplimentary = false;
            this.editingIndex = index;
            this.modalData.price = plan.price;
            this.modalData.minutes = plan.minutes;
            this.modalOpen = true;
        },

        // --- NEW FUNCTION FOR THE COMPLIMENTARY TIER ---
        openModalForComplimentary() {
            this.isEditMode = true;
            this.isEditingComplimentary = true;
            this.editingIndex = null;
            this.modalData.price = this.freeMinAmount; 
            this.modalData.minutes = this.freeDuration;
            this.modalOpen = true;
        },

        saveModalData() {
            // Guard against empty fields
            if (!this.modalData.price || !this.modalData.minutes) return;

            // Check if we are saving the Complimentary Tier
            if (this.isEditingComplimentary) {
                this.freeMinAmount = this.modalData.price;
                this.freeDuration = this.modalData.minutes;
            } 
            // Check if we are editing an existing Paid Tier
            else if (this.isEditMode && this.editingIndex !== null) {
                // Modify record details in place
                this.plans[this.editingIndex].price = this.modalData.price;
                this.plans[this.editingIndex].minutes = this.modalData.minutes;
            } 
            // We are adding a brand new Paid Tier
            else {
                // Inject freshly generated unique element properties
                this.plans.push({
                    id: Date.now() + Math.random(),
                    price: this.modalData.price,
                    minutes: this.modalData.minutes
                });
            }

            this.syncJson();
            this.modalOpen = false;
        },

        removePlan(index) {
            this.plans.splice(index, 1);
            this.syncJson();
        },

        formatDuration(minutes) {
            if (!minutes) return '0 Mins';
            const mins = parseInt(minutes);
            if (isNaN(mins)) return '0 Mins';
            
            if (mins >= 1440) {
                const days = Math.floor(mins / 1440);
                const rem = mins % 1440;
                return rem > 0 ? `${days}d ${Math.floor(rem/60)}h` : `${days} Day${days > 1 ? 's' : ''}`;
            }
            if (mins >= 60) {
                const hrs = Math.floor(mins / 60);
                const rem = mins % 60;
                return rem > 0 ? `${hrs}h ${rem}m` : `${hrs} Hour${hrs > 1 ? 's' : ''}`;
            }
            return `${mins} Minutes`;
        },

        syncJson() {
            let obj = {};
            
            // Clean sort algorithm maps array values flawlessly for json representation
            let sortedForOutput = [...this.plans].sort((a, b) => {
                if (!a.price) return 1;
                if (!b.price) return -1;
                return parseFloat(a.price) - parseFloat(b.price);
            });
            
            sortedForOutput.forEach(p => {
                if (p.price && p.minutes) {
                    obj[p.price] = parseInt(p.minutes) || 0;
                }
            });
            
            this.plans = sortedForOutput;
            this.jsonOutput = JSON.stringify(obj);
        }
    }
}
</script>
@endsection
