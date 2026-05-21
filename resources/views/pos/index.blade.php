{{-- Dynamically load the layout based on the user's role --}}
@extends(auth()->user()->role === 'admin' ? 'layouts.admin' : 'layouts.staff')

@section('title', 'POS Register')

@section('content')
<div x-data="posSystem()" class="bg-[#FDF8F5] -mx-8 -my-8 h-[calc(100vh-3.5rem)] flex items-stretch text-[#4A3B32] overflow-hidden" style="font-family: 'Montserrat', sans-serif;">

    {{-- Menu Area (Left) --}}
    <div class="flex-1 p-8 flex flex-col overflow-hidden">
        <div class="flex-1 bg-white p-6 md:p-8 rounded-2xl shadow-sm flex flex-col h-full overflow-hidden border border-[#F0E6D2]">
            
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
                
                {{-- ONLY show these sensitive buttons if the user is an Admin --}}
                @if(auth()->user()->role === 'admin')
                <div class="flex gap-3 shrink-0">
                    <a href="{{ route('network.sessions') }}" class="bg-[#FAFAFA] hover:bg-[#F0E6D2] text-[#8D6E63] hover:text-[#3E2723] px-5 py-3 rounded-full font-bold transition text-xs tracking-wider inline-flex items-center border border-[#F0E6D2] gap-2" title="Active Sessions">
                        <x-lucide-wifi class="w-4 h-4" />
                        <span>Sessions</span>
                    </a>
                    <a href="{{ route('sales.export') }}" class="bg-[#3E2723] hover:bg-[#271815] text-white px-5 py-3 rounded-full font-bold transition shadow-md shadow-[#3E2723]/20 text-xs tracking-wider inline-flex items-center gap-2" title="Export Sales">
                        <x-lucide-download class="w-4 h-4" />
                        <span>Export</span>
                    </a>
                </div>
                @endif

                @if($activeShift)
                <div class="flex gap-3 shrink-0" x-data="{ showShiftModal: false, endingCash: '' }">
                    <button type="button" @click="showShiftModal = true" class="bg-amber-600 hover:bg-amber-700 text-white px-5 py-3 rounded-full font-bold transition shadow-md shadow-amber-600/20 text-xs tracking-wider inline-flex items-center gap-2">
                        <x-lucide-lock class="w-4 h-4" />
                        <span>End Shift</span>
                    </button>

                    <!-- Shift Modal -->
                    <div x-show="showShiftModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/50" @keydown.escape.window="showShiftModal = false">
                        <div class="bg-white rounded-2xl p-6 w-96 shadow-xl" @click.outside="showShiftModal = false">
                            <h3 class="text-xl font-bold text-[#3E2723] mb-4">Close Shift</h3>
                            <p class="text-sm text-[#8D6E63] mb-4">Make sure you have counted the drawer.</p>
                            <form action="{{ route('shift.end', $activeShift->id) }}" method="POST">
                                @csrf
                                <div class="mb-4">
                                    <label class="block text-sm font-medium text-[#3E2723] mb-1">Ending Cash</label>
                                    <input type="number" step="0.01" name="ending_cash" x-model="endingCash" class="w-full px-4 py-2 border border-[#F0E6D2] rounded-lg focus:ring-[#3E2723] focus:border-[#3E2723]" required placeholder="0.00">
                                </div>
                                <div class="flex justify-end gap-3 mt-6">
                                    <button type="button" @click="showShiftModal = false" class="px-4 py-2 text-[#8D6E63] hover:text-[#3E2723] font-medium transition">Cancel</button>
                                    <button type="submit" class="px-4 py-2 bg-amber-600 hover:bg-amber-700 text-white rounded-lg font-bold transition">Confirm Close</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                @endif
            </div>

            <div class="flex gap-3 mb-6 overflow-x-auto pb-2 scrollbar-hide shrink-0">
                <template x-for="category in categories" :key="category">
                    <button @click="selectedCategory = category" 
                            class="px-6 py-2.5 rounded-full text-sm font-bold transition-all whitespace-nowrap border"
                            :class="selectedCategory === category ? 'bg-[#3E2723] text-white border-[#3E2723] shadow-md' : 'bg-[#FAFAFA] text-[#8D6E63] border-[#F0E6D2] hover:bg-[#FDF8F5]'">
                        <span x-text="category"></span>
                    </button>
                </template>
            </div>

            <h3 class="text-xl font-bold text-[#3E2723] mb-4 capitalize shrink-0" x-text="selectedCategory + ' Menu'"></h3>

            <div class="flex-1 overflow-y-auto pb-6 pr-2">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <template x-for="item in filteredProducts" :key="item.id">
                        <div class="bg-white p-5 rounded-2xl border border-[#F0E6D2] shadow-sm hover:shadow-md hover:border-[#D7CCC8] transition-all flex flex-col group h-full">
                            
                            <div class="h-24 w-full bg-[#FDF8F5] rounded-xl mb-4 flex items-center justify-center group-hover:bg-white transition-colors duration-300 border border-[#F0E6D2] shrink-0">
                                <template x-if="item.type === 'wifi'">
                                    <x-lucide-wifi class="w-8 h-8 text-amber-800/20" />
                                </template>
                                <template x-if="item.type !== 'wifi' && item.category === 'Pastries'">
                                    <x-lucide-cookie class="w-8 h-8 text-amber-800/20" />
                                </template>
                                <template x-if="item.type !== 'wifi' && item.category !== 'Pastries'">
                                    <x-lucide-coffee class="w-8 h-8 text-amber-800/20" />
                                </template>
                            </div>

                            <div class="flex justify-between items-start mb-1 shrink-0">
                                <h4 class="font-bold text-[#3E2723] text-base leading-tight pr-2" x-text="item.name"></h4>
                                <span class="font-black text-[#8D6E63] text-base shrink-0" x-text="'₱' + Number(item.price).toFixed(2)"></span>
                            </div>
                            
                            <p class="text-xs text-[#A1887F] font-medium mb-4 line-clamp-2 flex-1" x-text="item.type === 'wifi' ? 'Seamless high-speed internet access.' : 'Freshly prepared for your enjoyment.'"></p>

                            <div class="mt-auto shrink-0">
                                <button type="button" @click="addToCart(item)" class="w-full block bg-[#FAFAFA] hover:bg-[#3E2723] border border-[#F0E6D2] hover:border-[#3E2723] text-[#8D6E63] hover:text-white font-bold py-3 rounded-full transition-all text-sm tracking-wide active:scale-95">
                                    Add to Cart
                                </button>
                            </div>
                        </div>
                    </template>
                    
                    <div x-show="filteredProducts.length === 0" class="col-span-full text-center py-12 text-[#A1887F] text-sm font-medium">
                        <span class="block text-4xl mb-3 opacity-50">🔍</span>
                        No items found for this category or search.
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Cart Sidebar (Right) --}}
    <div class="w-[400px] bg-white border-l border-[#F0E6D2] flex flex-col h-full shrink-0 shadow-[-4px_0_20px_rgba(0,0,0,0.02)]">
        
        <div class="p-6 md:p-8 flex flex-col h-full overflow-hidden">
            <div class="flex justify-between items-center mb-6 shrink-0">
                <h3 class="text-2xl font-bold text-[#3E2723]">Cart</h3>
                <button type="button" x-show="cart.length > 0" @click="resetCart()" class="text-[#8D6E63] hover:text-red-600 transition p-2 hover:bg-red-50 rounded-lg" title="Clear All">
                    <x-lucide-trash-2 class="w-5 h-5" />
                </button>
            </div>

        <div class="flex bg-[#FAFAFA] border border-[#F0E6D2] rounded-full p-1 mb-6">
            <button @click="orderType = 'dine_in'" class="flex-1 py-2 rounded-full text-xs font-bold transition flex items-center justify-center gap-2" :class="orderType === 'dine_in' ? 'bg-[#3E2723] text-white shadow-sm' : 'text-[#8D6E63] hover:text-[#3E2723]'">
                <x-lucide-utensils class="w-3.5 h-3.5" />
                <span>Dine In</span>
            </button>
            <button @click="orderType = 'take_away'" class="flex-1 py-2 rounded-full text-xs font-bold transition flex items-center justify-center gap-2" :class="orderType === 'take_away' ? 'bg-[#3E2723] text-white shadow-sm' : 'text-[#8D6E63] hover:text-[#3E2723]'">
                <x-lucide-shopping-bag class="w-3.5 h-3.5" />
                <span>Take Away</span>
            </button>
        </div>

        <div class="flex-1 overflow-y-auto space-y-4 pr-2 mb-6">
            <template x-for="(cartItem, index) in cart" :key="cartItem.id + '-' + index">
                <div class="flex gap-4 items-center bg-white group">
                    <div class="w-12 h-12 bg-[#FDF8F5] border border-[#F0E6D2] rounded-xl flex items-center justify-center shrink-0">
                        <template x-if="cartItem.type === 'wifi'">
                            <x-lucide-wifi class="w-6 h-6 text-amber-800/30" />
                        </template>
                        <template x-if="cartItem.type !== 'wifi'">
                            <x-lucide-coffee class="w-6 h-6 text-amber-800/30" />
                        </template>
                    </div>
                    
                    <div class="flex-1">
                        <h4 class="font-bold text-sm text-[#3E2723] line-clamp-1" x-text="cartItem.name"></h4>
                        <div class="flex justify-between items-center mt-2">
                            <p class="font-black text-sm text-[#8D6E63]" x-text="'₱' + (Number(cartItem.price) * cartItem.quantity).toFixed(2)"></p>
                            
                            <div class="flex items-center bg-[#FAFAFA] border border-[#F0E6D2] rounded-full px-2 py-1">
                                <button type="button" @click="removeFromCart(index)" class="w-6 h-6 flex items-center justify-center text-[#8D6E63] hover:text-[#3E2723] font-bold transition">-</button>
                                <span class="w-6 text-center text-xs font-bold text-[#3E2723]" x-text="cartItem.quantity"></span>
                                <button type="button" @click="addToCart(cartItem)" class="w-6 h-6 flex items-center justify-center text-[#8D6E63] hover:text-[#3E2723] font-bold transition">+</button>
                            </div>
                        </div>
                    </div>
                </div>
            </template>
            
            <div x-show="cart.length === 0" class="flex flex-col items-center justify-center h-full text-[#A1887F] text-sm font-medium">
                <x-lucide-shopping-cart class="w-12 h-12 mb-4 opacity-20" />
                Your cart is empty.
            </div>
        </div>

        <div class="pt-4 border-t border-[#F0E6D2] mt-auto">
            
            <div class="mb-4">
                <label class="block text-[10px] font-black text-[#8D6E63] uppercase tracking-[0.2em] mb-2">Discount</label>
                <div class="flex gap-2">
                    <button type="button" @click="discountType = 'none'; discountAmount = 0" class="flex-1 py-2 rounded-lg text-xs font-bold transition border" :class="discountType === 'none' ? 'bg-[#3E2723] text-white border-[#3E2723]' : 'bg-[#FAFAFA] text-[#8D6E63] border-[#F0E6D2] hover:bg-[#FDF8F5]'">None</button>
                    <button type="button" @click="discountType = 'senior'; discountAmount = 0.20" class="flex-1 py-2 rounded-lg text-xs font-bold transition border" :class="discountType === 'senior' ? 'bg-[#3E2723] text-white border-[#3E2723]' : 'bg-[#FAFAFA] text-[#8D6E63] border-[#F0E6D2] hover:bg-[#FDF8F5]'">Senior/PWD (20%)</button>
                </div>
            </div>

            <div class="mb-4">
                <label class="block text-[10px] font-black text-[#8D6E63] uppercase tracking-[0.2em] mb-2">Amount Tendered (₱)</label>
                <input type="number" x-model.number="amountTendered" class="w-full p-3 border border-[#F0E6D2] rounded-xl focus:outline-none focus:ring-2 focus:ring-[#3E2723] bg-[#FAFAFA] transition-all text-sm font-bold text-[#3E2723]" placeholder="Enter amount">
            </div>

            <div class="space-y-3 mb-6">
                <div class="flex justify-between text-sm text-[#8D6E63] font-medium">
                    <span>Subtotal</span>
                    <span x-text="'₱' + subtotal.toFixed(2)"></span>
                </div>
                <div class="flex justify-between text-sm text-[#8D6E63] font-medium">
                    <span>Discount</span>
                    <span class="text-red-500" x-text="'- ₱' + calculatedDiscount.toFixed(2)"></span>
                </div>
                <div class="flex justify-between text-sm text-[#8D6E63] font-medium">
                    <span>VAT (12% inc)</span>
                    <span x-text="'₱' + vatAmount.toFixed(2)"></span>
                </div>
                <div class="flex justify-between text-xl font-bold text-[#3E2723] pt-2 border-t border-[#FDF8F5]">
                    <span>Total</span>
                    <span x-text="'₱' + grandTotal.toFixed(2)"></span>
                </div>
                <div class="flex justify-between text-sm font-bold text-green-600 pt-2 border-t border-[#FDF8F5]" x-show="amountTendered > 0">
                    <span>Change</span>
                    <span x-text="'₱' + Math.max(0, amountTendered - grandTotal).toFixed(2)"></span>
                </div>
            </div>

            <button type="button" @click="submitCheckout()" :disabled="cart.length === 0 || isProcessing || (amountTendered > 0 && amountTendered < grandTotal)" class="w-full bg-[#3E2723] hover:bg-[#271815] text-white py-4 rounded-full font-bold uppercase tracking-widest transition shadow-lg shadow-[#3E2723]/20 disabled:opacity-50 disabled:shadow-none disabled:cursor-not-allowed flex justify-center items-center text-sm gap-3">
                <template x-if="!isProcessing">
                    <x-lucide-send class="w-5 h-5" />
                </template>
                <template x-if="isProcessing">
                    <x-lucide-loader-2 class="w-5 h-5 animate-spin" />
                </template>
                <span x-text="isProcessing ? 'Processing...' : (amountTendered > 0 && amountTendered < grandTotal ? 'Insufficient Funds' : 'Place Order')"></span>
            </button>
        </div>
    </div>

    <!-- Success Modal -->
    <div x-show="showModal" class="fixed inset-0 bg-black/40 backdrop-blur-sm flex items-center justify-center z-[60]" style="display: none;">
        <div @click.away="resetCart()" class="bg-white rounded-2xl shadow-2xl p-8 max-w-sm w-full text-center border-t-8 border-[#3E2723]">
            <div class="w-20 h-20 bg-[#E8F5E9] rounded-full flex items-center justify-center mx-auto mb-6 text-[#2E7D32]">
                <x-lucide-check class="w-10 h-10" />
            </div>
            
            <h2 class="text-2xl font-bold text-[#3E2723] mb-2">Order Placed!</h2>
            <p class="text-sm text-[#8D6E63] mb-2">Payment completed for ₱<span x-text="grandTotal.toFixed(2)"></span>.</p>
            <p class="text-sm font-bold text-green-600 mb-8" x-show="amountTendered > 0">Change: ₱<span x-text="Math.max(0, amountTendered - grandTotal).toFixed(2)"></span></p>

            <template x-if="hasWifi">
                <div class="border border-[#E3F2FD] rounded-2xl p-6 mb-8 bg-[#F3F9FF]">
                    <p class="text-xs text-[#1565C0] uppercase tracking-widest mb-2 font-bold">Generated Wi-Fi Access</p>
                    <p class="text-3xl font-mono font-black tracking-widest text-[#0D47A1]" x-text="generatedCode"></p>
                </div>
            </template>
            
            <template x-if="!hasWifi">
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
        </div>
    </div>

    <!-- Shift Modal -->
    @if(!$activeShift)
    <div class="fixed inset-0 bg-black/60 backdrop-blur-md flex items-center justify-center z-[70]">
        <div class="bg-white rounded-2xl shadow-2xl p-8 max-w-sm w-full border-t-8 border-amber-600">
            <div class="w-16 h-16 bg-amber-50 rounded-full flex items-center justify-center mx-auto mb-6 text-amber-700">
                <x-lucide-lock class="w-8 h-8" />
            </div>
            
            <h2 class="text-2xl font-bold text-[#3E2723] mb-2 text-center">Shift Closed</h2>
            <p class="text-sm text-[#8D6E63] mb-8 text-center">You must open a cash drawer shift to process transactions.</p>

            <form action="{{ route('shift.start') }}" method="POST">
                @csrf
                <div class="mb-6">
                    <label class="block text-[10px] font-black text-[#8D6E63] uppercase tracking-[0.2em] mb-2 text-center">Starting Float / Cash</label>
                    <input type="number" name="starting_cash" required min="0" step="0.01" class="w-full p-4 border-2 border-[#F0E6D2] rounded-xl focus:outline-none focus:border-amber-600 bg-[#FAFAFA] transition-all text-center text-2xl font-bold text-[#3E2723]" placeholder="0.00">
                </div>
                <button type="submit" class="w-full py-4 bg-amber-600 text-white rounded-full hover:bg-amber-700 font-bold uppercase tracking-widest transition shadow-lg text-xs flex items-center justify-center gap-2">
                    <x-lucide-unlock class="w-4 h-4" />
                    <span>Open Shift</span>
                </button>
            </form>
        </div>
    </div>
    @endif
</div>

<script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('posSystem', () => ({
            menuItems: {!! json_encode($products ?? []) !!},
            wifiAddons: {!! json_encode($wifiOptions ?? []) !!},
            activeShiftId: {{ $activeShift ? $activeShift->id : 'null' }},
            
            cart: [],
            showModal: false,
            isProcessing: false,
            generatedCode: '',
            saleId: null,
            
            searchQuery: '',
            selectedCategory: 'All',
            orderType: 'dine_in',
            discountType: 'none',
            discountAmount: 0,
            amountTendered: null,
            
            get categories() {
                let cats = ['All'];
                this.menuItems.forEach(item => {
                    if (item.category && !cats.includes(item.category)) cats.push(item.category);
                });
                cats.push('Wi-Fi');
                return cats;
            },

            get filteredProducts() {
                let allProducts = [...this.menuItems, ...this.wifiAddons];
                
                return allProducts.filter(item => {
                    let matchesCategory = this.selectedCategory === 'All' || item.category === this.selectedCategory;
                    let matchesSearch = item.name.toLowerCase().includes(this.searchQuery.toLowerCase());
                    return matchesCategory && matchesSearch;
                });
            },

            get subtotal() {
                return this.cart.reduce((sum, item) => sum + (Number(item.price) * item.quantity), 0);
            },

            get calculatedDiscount() {
                return this.subtotal * this.discountAmount;
            },

            get grandTotal() {
                return this.subtotal - this.calculatedDiscount;
            },

            get vatAmount() {
                // Assuming 12% inclusive VAT
                return this.grandTotal - (this.grandTotal / 1.12);
            },

            get hasWifi() {
                return this.cart.some(item => item.type === 'wifi');
            },

            addToCart(item) {
                let existingItem = this.cart.find(i => i.id === item.id);
                if (existingItem) {
                    existingItem.quantity++;
                } else {
                    this.cart.push({ ...item, price: Number(item.price), quantity: 1 });
                }
            },

            removeFromCart(index) {
                if (this.cart[index].quantity > 1) {
                    this.cart[index].quantity--;
                } else {
                    this.cart.splice(index, 1);
                }
            },

            async submitCheckout() {
                if (this.amountTendered > 0 && this.amountTendered < this.grandTotal) return;
                
                this.isProcessing = true;

                try {
                    let response = await fetch('{{ route("pos.checkout") }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json', 
                            'X-CSRF-TOKEN': '{{ csrf_token() }}' 
                        },
                        body: JSON.stringify({
                            total_amount: this.grandTotal.toFixed(2),
                            amount_received: this.amountTendered || this.grandTotal.toFixed(2),
                            cart: this.cart,
                            order_type: this.orderType,
                            discount_type: this.discountType,
                            discount_amount: this.calculatedDiscount.toFixed(2),
                            shift_id: this.activeShiftId
                        })
                    });

                    let errorData = response.ok ? null : await response.json();
                    
                    if (!response.ok) {
                        Swal.fire({
                            icon: 'error',
                            title: 'Server Error',
                            text: errorData.message || 'Failed to process transaction.',
                            confirmButtonColor: '#3E2723',
                            background: '#FDF8F5',
                            color: '#3E2723',
                            customClass: {
                                popup: 'rounded-[2rem] border-t-8 border-red-600 shadow-2xl',
                                confirmButton: 'px-8 py-3 rounded-full font-bold uppercase tracking-widest text-xs'
                            }
                        });
                        return;
                    }

                    let data = await response.json();

                    if (data.success) {
                        this.generatedCode = data.hasWifi ? data.generatedCode : '';
                        this.saleId = data.sale_id;
                        this.showModal = true;
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Transaction Failed',
                            text: 'Please try again.',
                            confirmButtonColor: '#3E2723',
                            background: '#FDF8F5',
                            color: '#3E2723',
                            customClass: {
                                popup: 'rounded-[2rem] border-t-8 border-red-600 shadow-2xl',
                                confirmButton: 'px-8 py-3 rounded-full font-bold uppercase tracking-widest text-xs'
                            }
                        });
                    }
                } catch (error) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Network Error',
                        text: 'Unable to connect to server. Check console for details.',
                        confirmButtonColor: '#3E2723',
                        background: '#FDF8F5',
                        color: '#3E2723',
                        customClass: {
                            popup: 'rounded-[2rem] border-t-8 border-red-600 shadow-2xl',
                            confirmButton: 'px-8 py-3 rounded-full font-bold uppercase tracking-widest text-xs'
                        }
                    });
                } finally {
                    this.isProcessing = false;
                }
            },

            resetCart() {
                this.cart = [];
                this.showModal = false;
                this.generatedCode = '';
                this.searchQuery = '';
                this.discountType = 'none';
                this.discountAmount = 0;
                this.amountTendered = null;
                this.saleId = null;
            }
        }))
    })
</script>
@endsection