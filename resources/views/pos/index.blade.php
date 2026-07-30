{{-- Dynamically load the layout based on the user's role --}}
@extends(auth()->user()->isAdminOrAbove() ? 'layouts.admin' : 'layouts.staff')

@section('title', 'POS Register')

@section('content')

<style>
    /* This locked-viewport / fixed-height-flex-column treatment only applies at `lg` and up
       (matches Tailwind's lg breakpoint, 1024px), where the cart renders as a persistent
       side-by-side sidebar and needs precise height-matching with the menu column.
       Below `lg` the cart is an on-demand bottom-sheet instead (see the Alpine
       `showMobileCart` modal in the body) — the page can use normal document scroll there,
       which also avoids the well-known iOS Safari interaction issues between
       `overflow: hidden` on <body> and `position: fixed` descendants. */
    @media (min-width: 1024px) {
        /* 1. Completely lock the viewport to prevent the native browser scrollbar */
        body, html {
            overflow: hidden !important;
            height: 100vh !important;
        }

        /* 2. Force Laravel layout wrappers to act as full-height flex columns */
        #app, .min-h-screen, body > aside, body > .flex-1 {
            height: 100vh !important;
            display: flex !important;
            flex-direction: column !important;
        }

        /* 3. Strip all layout padding and force the main content to fill the remaining space */
        main {
            overflow: hidden !important;
            padding: 0 !important;
            margin: 0 !important;
            flex: 1 !important;
            display: flex !important;
            flex-direction: column !important;
        }

        /* Override any internal Laravel container paddings (like .py-12 or .max-w-7xl) */
        main > div {
            padding: 0 !important;
            margin: 0 !important;
            max-width: none !important;
            flex: 1 !important;
            display: flex !important;
        }
    }
</style>

<div x-data="posSystem()" class="bg-[#FDF8F5] w-full flex-1 lg:h-full flex items-stretch text-[#4A3B32] lg:overflow-hidden" style="font-family: 'Montserrat', sans-serif;">

    {{-- Menu Area (Left) --}}
    <div class="flex-1 p-4 md:p-8 pb-28 lg:pb-8 flex flex-col lg:overflow-hidden">
        <div class="flex-1 bg-white p-6 md:p-8 rounded-2xl shadow-sm flex flex-col lg:overflow-hidden border border-[#F0E6D2]">
            
            <div class="flex flex-col md:flex-row justify-between items-center mb-8 gap-4 shrink-0">
                
                <div class="flex items-center gap-3 text-[#3E2723] hidden lg:flex shrink-0">
                    <span class="text-3xl tracking-wide font-bold pr-1" style="font-family: 'Dancing Script', cursive;">Lawa't</span>
                    <span class="text-base font-bold tracking-[0.2em] uppercase mt-1">POS</span>
                </div>

                <div class="relative w-full max-w-md">
                    <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-[#8D6E63]">
                        <x-lucide-search class="w-5 h-5 text-gray-400" />
                    </div>
                    <input type="text" x-model="searchQuery" placeholder="Search menu..." class="w-full pl-11 pr-4 py-3 bg-[#FAFAFA] border border-[#F0E6D2] rounded-full focus:outline-none focus:ring-2 focus:ring-[#3E2723] transition-all text-sm font-medium placeholder-[#A1887F] text-[#3E2723]">
                </div>
                
                <div class="flex gap-3 shrink-0 items-center">
                    {{-- History Access --}}
                    <a href="{{ route('pos.history') }}" class="bg-[#FAFAFA] hover:bg-[#F0E6D2] text-[#8D6E63] hover:text-[#3E2723] px-5 py-3 rounded-full font-bold transition text-xs tracking-wider inline-flex items-center border border-[#F0E6D2] gap-2" title="Order History">
                        <x-lucide-history class="w-4 h-4" />
                        <span>History</span>
                    </a>

                    {{-- Admin Actions --}}
                    @if(auth()->user()->isAdminOrAbove())
                    <div class="flex gap-2 shrink-0">
                        <a href="{{ route('network.sessions') }}" class="bg-[#FAFAFA] hover:bg-[#F0E6D2] text-[#8D6E63] hover:text-[#3E2723] px-4 py-3 rounded-full font-bold transition text-xs tracking-wider inline-flex items-center border border-[#F0E6D2] gap-2" title="Active Sessions">
                            <x-lucide-wifi class="w-4 h-4" />
                            <span class="hidden xl:inline">Sessions</span>
                        </a>
                        <a href="{{ route('sales.export') }}" class="bg-[#3E2723] hover:bg-[#271815] text-white px-4 py-3 rounded-full font-bold transition shadow-md shadow-[#3E2723]/20 text-xs tracking-wider inline-flex items-center gap-2" title="Export Sales">
                            <x-lucide-download class="w-4 h-4" />
                            <span class="hidden xl:inline">Export</span>
                        </a>
                    </div>
                    @endif

                    {{-- Shift Management --}}
                    @if($activeShift)
                    <div class="flex gap-2 shrink-0">
                        <button type="button" 
                                @click="recordCashAction()"
                                class="bg-white hover:bg-[#FDF8F5] text-[#3E2723] px-4 py-3 rounded-full font-bold transition border border-[#F0E6D2] text-xs tracking-wider inline-flex items-center gap-2">
                            <x-lucide-banknote class="w-4 h-4" />
                            <span class="hidden xl:inline">Cash</span>
                        </button>
                        <a href="{{ route('shift.closing-report', $activeShift->id) }}" class="bg-amber-600 hover:bg-amber-700 text-white px-5 py-3 rounded-full font-bold transition shadow-md shadow-amber-600/20 text-xs tracking-wider inline-flex items-center gap-2">
                            <x-lucide-lock class="w-4 h-4" />
                            <span>End Shift</span>
                        </a>
                    </div>
                    @endif
                </div>
            </div>

        <div class="flex gap-3 mb-6 overflow-x-auto pb-2 scrollbar-hide shrink-0">
                <template x-for="category in categories" :key="category.name">
                    <button @click="selectedCategory = category.name" 
                            class="px-5 py-2.5 rounded-full text-xs font-black transition-all whitespace-nowrap border flex items-center gap-2 tracking-widest uppercase"
                            :class="selectedCategory === category.name ? 'bg-[#3E2723] text-white border-[#3E2723] shadow-md' : 'bg-[#FAFAFA] text-[#8D6E63] border-[#F0E6D2] hover:bg-[#FDF8F5]'">
                        {{-- Handle dynamic lucide icons in Alpine --}}
                        <div x-show="category.icon === 'layout-grid'"><x-lucide-layout-grid class="w-3.5 h-3.5" /></div>
                        <div x-show="category.icon === 'coffee'"><x-lucide-coffee class="w-3.5 h-3.5" /></div>
                        <div x-show="category.icon === 'cup-soda'"><x-lucide-cup-soda class="w-3.5 h-3.5" /></div>
                        <div x-show="category.icon === 'cookie'"><x-lucide-cookie class="w-3.5 h-3.5" /></div>
                        <div x-show="category.icon === 'beef'"><x-lucide-beef class="w-3.5 h-3.5" /></div>
                        <div x-show="category.icon === 'utensils'"><x-lucide-utensils class="w-3.5 h-3.5" /></div>
                        <div x-show="category.icon === 'wifi'"><x-lucide-wifi class="w-3.5 h-3.5" /></div>
                        <div x-show="category.icon === 'star'"><x-lucide-star class="w-3.5 h-3.5" /></div>
                        <div x-show="category.icon === 'layers'"><x-lucide-layers class="w-3.5 h-3.5" /></div>
                        <span x-text="category.name"></span>
                    </button>
                </template>
            </div>

            <h3 class="text-xl font-bold text-[#3E2723] mb-4 capitalize shrink-0" x-text="selectedCategory + ' Menu'"></h3>

            <div class="relative flex-1 w-full">
                {{-- Below `lg` this is a normal in-flow block (the whole page scrolls naturally,
                     since the body-lock CSS above is also lg-only) — the absolute+inset-0 trick
                     for a precisely bounded internal scroll region only makes sense once the
                     ancestor chain is forced to viewport height, which only happens at `lg:` up. --}}
                <div class="lg:absolute lg:inset-0 overflow-y-auto pb-6 pr-2 [&::-webkit-scrollbar]:w-2 [&::-webkit-scrollbar-track]:bg-transparent [&::-webkit-scrollbar-thumb]:bg-[#D7CCC8] [&::-webkit-scrollbar-thumb]:rounded-full">
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                        <template x-for="item in filteredProducts" :key="item.id">
                            <div class="bg-white p-4 rounded-[1.5rem] shadow-[0_4px_15px_-3px_rgba(62,39,35,0.05)] hover:shadow-[0_10px_25px_-5px_rgba(62,39,35,0.12)] transition-all duration-300 flex flex-col group relative border border-transparent hover:border-[#FDF8F5]">
                                
                                <div class="h-32 w-full bg-[#FDF8F5] rounded-xl mb-4 flex items-center justify-center group-hover:scale-[1.02] transition-transform duration-300 border border-[#F0E6D2]/50 shrink-0 relative overflow-hidden">
                                    {{-- Visual cue based on category --}}
                                    <template x-if="item.type === 'wifi'">
                                        <x-lucide-wifi class="w-10 h-10 text-blue-800/20" />
                                    </template>
                                    <template x-if="item.type !== 'wifi'">
                                        <div class="flex items-center justify-center">
                                            @foreach($dbCategories as $cat)
                                                <div x-show="item.category === '{{ $cat['name'] }}'" style="color: {{ $cat['color'] }}30">
                                                    <x-dynamic-component :component="'lucide-' . $cat['icon']" class="w-10 h-10" />
                                                </div>
                                            @endforeach
                                            {{-- Fallback --}}
                                            <template x-if="!categories.find(c => c.name === item.category)">
                                                <x-lucide-coffee class="w-10 h-10 text-amber-800/20" />
                                            </template>
                                        </div>
                                    </template>

                                    <!-- Out of Stock Overlay -->
                                    <template x-if="item.type === 'product' && !item.inStock">
                                        <div class="absolute inset-0 bg-white/60 backdrop-blur-[2px] flex items-center justify-center z-20">
                                            <x-lucide-slash class="w-12 h-12 text-red-500 opacity-40 rotate-12" />
                                        </div>
                                    </template>
                                </div>

                                <div class="flex flex-col flex-1 relative">
                                    <!-- Status Badges -->
                                    <div class="absolute -top-32 right-0 z-10 flex flex-col gap-1 items-end">
                                        <template x-if="item.type === 'product' && !item.inStock">
                                            <span class="bg-red-500 text-white text-[8px] font-black px-2 py-1 rounded-md uppercase tracking-widest shadow-sm">Out of Stock</span>
                                        </template>
                                        <template x-if="item.type === 'product' && item.inStock && item.isLowStock">
                                            <span class="bg-amber-500 text-white text-[8px] font-black px-2 py-1 rounded-md uppercase tracking-widest shadow-sm animate-pulse">Low Stock</span>
                                        </template>
                                    </div>

                                    <h4 class="font-bold text-[#3E2723] text-base leading-tight mb-1" x-text="item.name"></h4>
                                    <p class="text-[11px] text-[#A1887F] font-medium mb-3 line-clamp-2" x-text="item.type === 'wifi' ? 'Seamless high-speed internet access.' : 'Freshly prepared for your enjoyment.'"></p>
                                    
                                    <div class="mt-auto flex justify-between items-center pt-2">
                                        <div class="flex flex-col">
                                            <span class="text-[10px] uppercase font-black tracking-wider text-[#A1887F]">Price</span>
                                            <span class="font-black text-[#3E2723] text-lg" x-text="'₱' + Number(item.price).toFixed(2)"></span>
                                        </div>
                                        
                                        <button type="button" 
                                                @click="addToCart(item)" 
                                                :disabled="item.type === 'product' && !item.inStock"
                                                class="w-11 h-11 rounded-full bg-[#3E2723] text-white flex items-center justify-center hover:bg-[#271815] transition-all active:scale-90 shadow-md shadow-[#3E2723]/30 disabled:bg-gray-200 disabled:text-gray-400 disabled:shadow-none disabled:cursor-not-allowed">
                                            <x-lucide-plus class="w-5 h-5" />
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </template>
                        
                        <div x-show="filteredProducts.length === 0" class="col-span-full flex flex-col items-center justify-center py-20 text-[#A1887F]">
                            <div class="w-20 h-20 bg-white rounded-full flex items-center justify-center shadow-sm mb-4">
                                <span class="text-3xl opacity-50">🔍</span>
                            </div>
                            <p class="font-bold text-sm">No items found.</p>
                            <p class="text-xs mt-1">Try searching for something else.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Variant Selection Modal -->
    <x-modal-shell show="showVariantModal" max-width="sm" panel-class="p-8 border-t-8 border-[#3E2723]" labelled-by="variant-modal-title">
            <h2 id="variant-modal-title" class="text-2xl font-black text-[#3E2723] mb-1" x-text="pendingItem?.name"></h2>
            <p class="text-sm font-medium text-[#8D6E63] mb-6">Select preparation preference:</p>

            <div class="grid grid-cols-2 gap-4 mb-8">
                <button @click="confirmVariant('Hot')" class="flex flex-col items-center justify-center p-6 border-2 border-[#F0E6D2] rounded-[1.5rem] hover:border-[#3E2723] hover:bg-[#FDF8F5] transition group bg-white shadow-sm">
                    <x-lucide-coffee class="w-12 h-12 text-amber-700 mb-3 group-hover:scale-110 transition" />
                    <span class="font-black text-[#3E2723] uppercase tracking-widest text-sm">Hot</span>
                </button>
                <button @click="confirmVariant('Iced')" class="flex flex-col items-center justify-center p-6 border-2 border-[#F0E6D2] rounded-[1.5rem] hover:border-blue-800 hover:bg-blue-50 transition group bg-white shadow-sm">
                    <x-lucide-snowflake class="w-12 h-12 text-blue-500 mb-3 group-hover:scale-110 transition" />
                    <span class="font-black text-blue-900 uppercase tracking-widest text-sm">Iced</span>
                </button>
            </div>

            <button @click="closeVariantModal()" class="w-full py-3 bg-[#FAFAFA] border border-[#F0E6D2] rounded-full text-[#8D6E63] hover:text-[#3E2723] hover:bg-[#FDF8F5] font-bold transition">
                Cancel
            </button>
    </x-modal-shell>

    {{-- Cart Sidebar (Right) — desktop/tablet-landscape only; below `lg` the cart is a bottom-sheet, see below --}}
    <div class="hidden lg:flex bg-white p-5 flex-col shrink-0 border-l border-[#F0E6D2] shadow-[-10px_0_30px_rgba(62,39,35,0.05)] h-full z-10 overflow-hidden lg:w-[28rem]">
        @include('pos.partials.cart')
    </div>

    {{-- Mobile/Tablet-Portrait Cart Trigger --}}
    <div x-show="cart.length > 0" x-cloak
         x-transition:enter="transition ease-out duration-[400ms]"
         x-transition:enter-start="opacity-0 translate-y-4"
         x-transition:enter-end="opacity-100 translate-y-0"
         x-transition:leave="transition ease-in duration-300"
         x-transition:leave-start="opacity-100 translate-y-0"
         x-transition:leave-end="opacity-0 translate-y-4"
         class="lg:hidden fixed bottom-4 inset-x-4 z-40">
        <button type="button" @click="showMobileCart = true" class="w-full bg-[#3E2723] hover:bg-[#271815] text-white rounded-full py-4 px-6 shadow-2xl flex items-center justify-between font-bold text-sm active:scale-[0.98] transition">
            <span class="flex items-center gap-2">
                <x-lucide-shopping-cart class="w-5 h-5" />
                View Cart (<span x-text="cart.length"></span>)
            </span>
            <span x-text="'₱' + grandTotal.toFixed(2)"></span>
        </button>
    </div>

    {{-- Mobile/Tablet-Portrait Cart Bottom Sheet --}}
    <x-modal-shell show="showMobileCart" position="bottom-sheet" max-width="lg" panel-class="p-5" labelled-by="mobile-cart-title">
        <h3 id="mobile-cart-title" class="sr-only">Current Order</h3>
        @include('pos.partials.cart')
    </x-modal-shell>

    <x-modal-shell show="showModal" max-width="sm" panel-class="p-8 text-center border-t-8 border-[#3E2723]" labelled-by="order-placed-modal-title">
            <div class="w-20 h-20 bg-[#E8F5E9] rounded-full flex items-center justify-center mx-auto mb-6 text-[#2E7D32]">
                <x-lucide-check class="w-10 h-10" />
            </div>

            <h2 id="order-placed-modal-title" class="text-2xl font-bold text-[#3E2723] mb-2">Order Placed!</h2>
            <p class="text-sm text-[#8D6E63] mb-2">Payment completed for ₱<span x-text="grandTotal.toFixed(2)"></span>.</p>
            <p class="text-sm font-bold text-green-600 mb-8" x-show="amountTendered > 0">Change: ₱<span x-text="Math.max(0, amountTendered - grandTotal).toFixed(2)"></span></p>

            <template x-if="checkoutHasWifi">
                <div class="border border-[#E3F2FD] rounded-2xl p-6 mb-8 bg-[#F3F9FF] space-y-3">
                    <p class="text-xs text-[#1565C0] uppercase tracking-widest font-bold">
                        <span x-text="generatedCodes.length > 1 ? 'Generated Wi-Fi Codes — one per device' : 'Generated Wi-Fi Access'"></span>
                    </p>
                    <template x-for="code in generatedCodes" :key="code">
                        <p class="text-3xl font-mono font-black tracking-widest text-[#0D47A1]" x-text="code"></p>
                    </template>
                </div>
            </template>

            <template x-if="!checkoutHasWifi">
                <div class="border border-[#F0E6D2] rounded-2xl p-6 mb-8 bg-[#FDF8F5]">
                    <p class="text-xs text-[#8D6E63] uppercase tracking-wider font-bold">Standard Order</p>
                    <p class="text-sm font-bold text-[#3E2723] mt-1">No network access required.</p>
                </div>
            </template>

            <div class="flex gap-4">
                <button type="button" @click="resetCart()" class="flex-1 py-4 bg-[#FAFAFA] border border-[#F0E6D2] rounded-full text-[#8D6E63] hover:bg-[#FDF8F5] font-bold transition flex items-center justify-center gap-2">
                    <x-lucide-plus-circle class="w-4 h-4" />
                    <span>New Order</span>
                </button>
                <a :href="'/pos/receipt/' + saleId" target="_blank" class="flex-1 py-4 bg-[#3E2723] text-white rounded-full hover:bg-[#271815] font-bold transition shadow-md flex items-center justify-center gap-2">
                    <x-lucide-printer class="w-4 h-4" />
                    <span>Print</span>
                </a>
            </div>
    </x-modal-shell>

    @if(!$activeShift)
    <div class="fixed inset-0 bg-black/60 backdrop-blur-md flex items-center justify-center z-[70]">
        <div class="bg-white rounded-2xl shadow-2xl p-8 max-w-sm w-full border-t-8 border-amber-600">
            <div class="w-16 h-16 bg-amber-50 rounded-full flex items-center justify-center mx-auto mb-6 text-amber-700">
                <x-lucide-lock class="w-8 h-8" />
            </div>
            
            <h2 class="text-2xl font-bold text-[#3E2723] mb-2 text-center">Shift Closed</h2>
            <p class="text-sm text-[#8D6E63] mb-8 text-center">You must open a cash drawer shift to process transactions.</p>

            <form action="{{ route('shift.start') }}" method="POST" x-data="{ submitting: false }" @submit="submitting = true">
                @csrf
                <div class="mb-6">
                    <label class="block text-[10px] font-black text-[#8D6E63] uppercase tracking-[0.2em] mb-2 text-center">Starting Float / Cash</label>
                    <input type="number" name="starting_cash" required min="0" step="0.01" class="w-full p-4 border-2 border-[#F0E6D2] rounded-xl focus:outline-none focus:border-amber-600 bg-[#FAFAFA] transition-all text-center text-2xl font-bold text-[#3E2723]" placeholder="0.00">
                </div>
                <button type="submit" :disabled="submitting" class="w-full py-4 bg-amber-600 text-white rounded-full hover:bg-amber-700 font-bold uppercase tracking-widest transition shadow-lg text-xs flex items-center justify-center gap-2 disabled:opacity-60 disabled:cursor-not-allowed">
                    <template x-if="!submitting">
                        <x-lucide-unlock class="w-4 h-4" />
                    </template>
                    <template x-if="submitting">
                        <x-lucide-loader-2 class="w-4 h-4 animate-spin" />
                    </template>
                    <span x-text="submitting ? 'Opening Shift...' : 'Open Shift'"></span>
                </button>
            </form>

            <div class="mt-6 text-center">
                <a href="{{ route(auth()->user()->isAdminOrAbove() ? 'dashboard' : 'staff.dashboard') }}" class="text-[10px] font-black text-[#A1887F] hover:text-[#3E2723] uppercase tracking-[0.2em] transition-all flex items-center justify-center gap-2 group">
                    <x-lucide-arrow-left class="w-3 h-3 group-hover:-translate-x-1 transition-transform" />
                    <span>Back to Hub</span>
                </a>
            </div>
        </div>
    </div>
    @endif
</div>

<script>
    function posSystem() {
        return {
            searchQuery: '',
            selectedCategory: 'All',
            categories: @js($categories),
            products: @js($products),
            cart: [],
            orderType: 'dine_in',
            discountType: 'none',
            discountAmount: 0,
            amountTendered: 0,
            paymentMethod: 'Cash',
            showModal: false,
            showVariantModal: false,
            showMobileCart: false,
            pendingItem: null,
            saleId: null,
            generatedCodes: [],
            checkoutHasWifi: false,
            isProcessing: false,
            suggestion: null,
            freeWifiMinAmount: {{ $freeWifiMinAmount ?? 0 }},
            freeWifiDuration: {{ $freeWifiDuration ?? 0 }},
            flashKey: null,

            flashItem(key) {
                this.flashKey = key;
                setTimeout(() => { if (this.flashKey === key) this.flashKey = null; }, 300);
            },

            editNote(index) {
                const item = this.cart[index];
                Swal.fire({
                    title: 'Item Note',
                    input: 'text',
                    inputPlaceholder: 'e.g. Oat Milk, Less Sugar...',
                    inputValue: item.note || '',
                    showCancelButton: true,
                    confirmButtonColor: '#3E2723',
                    cancelButtonColor: '#8D6E63',
                    confirmButtonText: 'Save Note'
                }).then((result) => {
                    if (result.isConfirmed) {
                        this.cart[index].note = result.value;
                    }
                });
            },

            get filteredProducts() {
                return this.products.filter(i => {
                    const matchesSearch = i.name.toLowerCase().includes(this.searchQuery.toLowerCase());
                    const matchesCategory = this.selectedCategory === 'All' || i.category === this.selectedCategory;
                    return matchesSearch && matchesCategory;
                });
            },

            get subtotal() {
                return this.cart.reduce((sum, item) => sum + (Number(item.price) * item.quantity), 0);
            },

            get calculatedDiscount() {
                return this.subtotal * this.discountAmount;
            },

            get vatAmount() {
                // Assuming prices are VAT inclusive (12%)
                return (this.subtotal - this.calculatedDiscount) * (12/112);
            },

            get grandTotal() {
                return this.subtotal - this.calculatedDiscount;
            },

            get hasWifi() {
                return this.cart.some(item => item.type === 'wifi');
            },

            addToCart(product) {
                // If it's a product, check total stock availability against current cart
                if (product.type === 'product') {
                    // Check if we can add one more
                    const canAdd = this.checkStock(product, 1);
                    if (!canAdd) return;
                }

                // If it's a Coffee or Tea, ask for variant first
                if (product.type !== 'wifi' && (product.category === 'Coffee' || product.category === 'Tea' || product.category === 'Signature')) {
                    // Check if it's already a variant object from the cart + button
                    if (product.variant) {
                        this.processAdd(product, product.variant);
                    } else {
                        this.pendingItem = product;
                        this.showVariantModal = true;
                    }
                } else {
                    this.processAdd(product, null);
                }
            },

            checkStock(product, additionalQty) {
                if (product.type !== 'product' || !product.requirements) return true;

                // Calculate current usage of each ingredient in the cart
                const currentUsage = {};
                this.cart.forEach(item => {
                    if (item.type === 'product' && item.requirements) {
                        item.requirements.forEach(req => {
                            currentUsage[req.id] = (currentUsage[req.id] || 0) + (req.required * item.quantity);
                        });
                    }
                });

                // Check if adding the new item exceeds any ingredient stock
                for (const req of product.requirements) {
                    const projectedUsage = (currentUsage[req.id] || 0) + (req.required * additionalQty);
                    if (projectedUsage > req.current) {
                        Swal.fire({
                            icon: 'error',
                            title: 'Insufficient Stock',
                            text: `Not enough ${req.name} available. Only ${Math.floor((req.current - (currentUsage[req.id] || 0)) / req.required)} more servings possible.`,
                            confirmButtonColor: '#3E2723'
                        });
                        return false;
                    }
                }
                return true;
            },

            confirmVariant(variant) {
                if (this.pendingItem) {
                    this.processAdd(this.pendingItem, variant);
                }
                this.closeVariantModal();
            },

            closeVariantModal() {
                this.showVariantModal = false;
                this.pendingItem = null;
            },

            processAdd(product, variant) {
                // Match by ID AND Variant so Hot/Iced don't merge
                const existing = this.cart.find(i => i.id === product.id && i.variant === variant);

                if (existing) {
                    existing.quantity++;
                } else {
                    this.cart.push({ ...product, quantity: 1, variant: variant });
                }
                this.flashItem(product.id + '-' + variant);

                this.fetchPairingSuggestion(product);
            },

            async fetchPairingSuggestion(product) {
                // Wi-Fi add-ons aren't real Product rows — nothing to pair.
                if (product.type !== 'product') return;

                try {
                    const response = await fetch('{{ route('pos.suggest-pairing') }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        },
                        body: JSON.stringify({
                            product_id: product.id,
                            cart_product_ids: this.cart.filter(i => i.type === 'product').map(i => i.id),
                        }),
                    });
                    if (!response.ok) return;
                    const data = await response.json();
                    if (data.suggestion) this.suggestion = data.suggestion;
                } catch (error) {
                    // Silent — a missed suggestion should never interrupt order-taking.
                }
            },

            addSuggestion() {
                if (!this.suggestion) return;
                const product = this.products.find(p => p.id === this.suggestion.product_id);
                if (product) this.addToCart(product);
                this.suggestion = null;
            },

            dismissSuggestion() {
                this.suggestion = null;
            },

            removeFromCart(index) {
                if (this.cart[index].quantity > 1) {
                    this.cart[index].quantity--;
                    this.flashItem(this.cart[index].id + '-' + this.cart[index].variant);
                } else {
                    this.cart.splice(index, 1);
                }
            },

            resetCart() {
                this.cart = [];
                this.amountTendered = 0;
                this.discountType = 'none';
                this.discountAmount = 0;
                this.showModal = false;
                this.showMobileCart = false;
                this.checkoutHasWifi = false;
                this.isProcessing = false;
            },

            async submitCheckout() {
                if (this.cart.length === 0) return;
                
                this.isProcessing = true;
                
                try {
                    const response = await fetch('{{ route('pos.checkout') }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        },
                        body: JSON.stringify({
                            cart: this.cart,
                            total_amount: this.grandTotal,
                            discount_type: this.discountType,
                            discount_amount: this.calculatedDiscount,
                            payment_method: this.paymentMethod,
                            amount_received: this.amountTendered || this.grandTotal,
                            order_type: this.orderType,
                            shift_id: {{ $activeShift ? $activeShift->id : 'null' }}
                        })
                    });

                    const result = await response.json();
                    
                    if (result.success) {
                        this.saleId = result.sale_id;
                        this.generatedCodes = result.generatedCodes || [];
                        this.checkoutHasWifi = result.hasWifi || false;
                        this.showMobileCart = false;
                        this.showModal = true;
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Inventory Alert',
                            text: result.message || 'Failed to process order.',
                            confirmButtonColor: '#3E2723'
                        });
                        this.isProcessing = false;
                    }
                } catch (error) {
                    console.error('Checkout error:', error);
                    this.isProcessing = false;
                }
            },

            recordCashAction() {
                Swal.fire({
                    title: 'Cash Action',
                    text: 'Add or remove cash from drawer.',
                    html: `
                        <div class='flex flex-col gap-4 py-4'>
                            <select id='swal-type' class='w-full p-2 border-2 border-[#F0E6D2] rounded-xl focus:border-[#3E2723] outline-none font-bold text-xs'>
                                <option value='pay_in'>Cash Pay-In (+)</option>
                                <option value='pay_out'>Cash Pay-Out (-)</option>
                            </select>
                            <input id='swal-amount' type='number' step='0.01' placeholder='Amount (₱)' class='w-full p-2 border-2 border-[#F0E6D2] rounded-xl focus:border-[#3E2723] outline-none font-bold text-xs'>
                            <input id='swal-reason' type='text' placeholder='Reason (e.g. Change, Ice Purchase)' class='w-full p-2 border-2 border-[#F0E6D2] rounded-xl focus:border-[#3E2723] outline-none font-bold text-xs'>
                        </div>
                    `,
                    showCancelButton: true,
                    confirmButtonText: 'Record Action',
                    confirmButtonColor: '#3E2723',
                    cancelButtonColor: '#8D6E63',
                    preConfirm: () => {
                        const type = document.getElementById('swal-type').value;
                        const amount = document.getElementById('swal-amount').value;
                        const reason = document.getElementById('swal-reason').value;
                        if (!amount || amount <= 0 || !reason) {
                            Swal.showValidationMessage('Please fill all fields correctly');
                            return false;
                        }
                        return { type, amount, reason };
                    }
                }).then((result) => {
                    if (result.isConfirmed) {
                        Swal.fire({
                            title: 'Recording...',
                            allowOutsideClick: false,
                            allowEscapeKey: false,
                            showConfirmButton: false,
                            didOpen: () => Swal.showLoading()
                        });

                        const form = document.createElement('form');
                        form.method = 'POST';
                        form.action = '{{ route('shift.transaction', $activeShift->id ?? 0) }}';

                        const csrf = document.createElement('input');
                        csrf.type = 'hidden';
                        csrf.name = '_token';
                        csrf.value = '{{ csrf_token() }}';
                        form.appendChild(csrf);

                        const typeInput = document.createElement('input');
                        typeInput.type = 'hidden';
                        typeInput.name = 'type';
                        typeInput.value = result.value.type;
                        form.appendChild(typeInput);

                        const amountInput = document.createElement('input');
                        amountInput.type = 'hidden';
                        amountInput.name = 'amount';
                        amountInput.value = result.value.amount;
                        form.appendChild(amountInput);

                        const reasonInput = document.createElement('input');
                        reasonInput.type = 'hidden';
                        reasonInput.name = 'reason';
                        reasonInput.value = result.value.reason;
                        form.appendChild(reasonInput);

                        document.body.appendChild(form);
                        form.submit();
                    }
                });
            }
        }
    }
</script>

@endsection