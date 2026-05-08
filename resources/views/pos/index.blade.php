{{-- Dynamically load the layout based on the user's role --}}
@extends(auth()->user()->role === 'admin' ? 'layouts.admin' : 'layouts.staff')

@section('title', 'POS Register')

@section('content')
<div x-data="posSystem()" class="bg-[#FDF8F5] -m-6 p-6 min-h-screen flex items-start gap-6 text-[#4A3B32]" style="font-family: 'Montserrat', sans-serif;">

    <div class="flex-1 bg-white p-6 md:p-8 rounded-[2rem] shadow-sm flex flex-col h-[calc(100vh-3rem)] overflow-hidden border border-[#F0E6D2]">
        
        <div class="flex flex-col md:flex-row justify-between items-center mb-8 gap-4 shrink-0">
            
            <div class="flex items-center gap-3 text-[#3E2723] hidden lg:flex shrink-0">
                <span class="text-4xl tracking-wide font-bold pr-1" style="font-family: 'Dancing Script', cursive;">Lawa't</span>
                <span class="text-lg font-bold tracking-[0.2em] uppercase mt-2">POS</span>
            </div>

            <div class="relative w-full max-w-md">
                <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-[#8D6E63]">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                </div>
                <input type="text" x-model="searchQuery" placeholder="Search menu..." class="w-full pl-11 pr-4 py-3 bg-[#FAFAFA] border border-[#F0E6D2] rounded-full focus:outline-none focus:ring-2 focus:ring-[#3E2723] transition-all text-sm font-medium placeholder-[#A1887F] text-[#3E2723]">
            </div>
            
            {{-- ONLY show these sensitive buttons if the user is an Admin --}}
            @if(auth()->user()->role === 'admin')
            <div class="flex gap-3 shrink-0">
                <a href="{{ route('network.sessions') }}" class="bg-[#FAFAFA] hover:bg-[#F0E6D2] text-[#8D6E63] hover:text-[#3E2723] px-5 py-3 rounded-full font-bold transition text-xs tracking-wider inline-flex items-center border border-[#F0E6D2]">
                    Active Sessions
                </a>
                <a href="{{ route('sales.export') }}" class="bg-[#3E2723] hover:bg-[#271815] text-white px-5 py-3 rounded-full font-bold transition shadow-md shadow-[#3E2723]/20 text-xs tracking-wider inline-flex items-center">
                    Export Sales
                </a>
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

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 overflow-y-auto pb-6 pr-2">
            <template x-for="item in filteredProducts" :key="item.id">
                <div class="bg-white p-5 rounded-[2rem] border border-[#F0E6D2] shadow-sm hover:shadow-md hover:border-[#D7CCC8] transition-all flex flex-col group h-full">
                    
                    <div class="h-32 w-full bg-[#FDF8F5] rounded-2xl mb-4 flex items-center justify-center text-4xl group-hover:scale-105 transition-transform duration-300 border border-[#F0E6D2] shrink-0">
                        <span x-text="item.type === 'wifi' ? '📶' : (item.category === 'Pastries' ? '🥐' : '☕')"></span>
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

    <div class="w-96 bg-white p-6 md:p-8 rounded-[2rem] shadow-sm border border-[#F0E6D2] flex flex-col sticky top-6 h-[calc(100vh-3rem)] shrink-0">
        
        <div class="flex justify-between items-center mb-6">
            <h3 class="text-2xl font-bold text-[#3E2723]">Cart</h3>
            <button type="button" x-show="cart.length > 0" @click="resetCart()" class="text-xs text-red-400 hover:text-red-600 font-bold uppercase tracking-wider transition">Clear All</button>
        </div>

        <div class="flex bg-[#FAFAFA] border border-[#F0E6D2] rounded-full p-1 mb-6">
            <button class="flex-1 py-2 rounded-full text-xs font-bold bg-[#3E2723] text-white shadow-sm transition">Dine in</button>
            <button class="flex-1 py-2 rounded-full text-xs font-bold text-[#8D6E63] hover:text-[#3E2723] transition">Take away</button>
        </div>

        <div class="flex-1 overflow-y-auto space-y-4 pr-2 mb-6">
            <template x-for="(cartItem, index) in cart" :key="cartItem.id + '-' + index">
                <div class="flex gap-4 items-center bg-white group">
                    <div class="w-12 h-12 bg-[#FDF8F5] border border-[#F0E6D2] rounded-xl flex items-center justify-center text-xl shrink-0">
                        <span x-text="cartItem.type === 'wifi' ? '📶' : '☕'"></span>
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
                <span class="text-5xl mb-4 opacity-30">🛒</span>
                Your cart is empty.
            </div>
        </div>

        <div class="pt-4 border-t border-[#F0E6D2] mt-auto">
            <div class="space-y-3 mb-6">
                <div class="flex justify-between text-sm text-[#8D6E63] font-medium">
                    <span>Items</span>
                    <span x-text="'₱' + total.toFixed(2)"></span>
                </div>
                <div class="flex justify-between text-sm text-[#8D6E63] font-medium">
                    <span>Discounts</span>
                    <span>- ₱0.00</span>
                </div>
                <div class="flex justify-between text-xl font-bold text-[#3E2723] pt-2 border-t border-[#FDF8F5]">
                    <span>Total</span>
                    <span x-text="'₱' + total.toFixed(2)"></span>
                </div>
            </div>

            <button type="button" @click="submitCheckout()" :disabled="cart.length === 0 || isProcessing" class="w-full bg-[#3E2723] hover:bg-[#271815] text-white py-4 rounded-full font-bold uppercase tracking-widest transition shadow-lg shadow-[#3E2723]/20 disabled:opacity-50 disabled:shadow-none disabled:cursor-not-allowed flex justify-center items-center text-sm">
                <span x-text="isProcessing ? 'Processing...' : 'Place Order'" :class="{ 'animate-pulse': isProcessing }"></span>
            </button>
        </div>
    </div>

    <div x-show="showModal" class="fixed inset-0 bg-black/40 backdrop-blur-sm flex items-center justify-center z-50" style="display: none;">
        <div @click.away="resetCart()" class="bg-white rounded-[2rem] shadow-2xl p-8 max-w-sm w-full text-center border-t-8 border-[#3E2723]">
            <div class="w-20 h-20 bg-[#E8F5E9] rounded-full flex items-center justify-center mx-auto mb-6 text-[#2E7D32]">
                <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path></svg>
            </div>
            
            <h2 class="text-2xl font-bold text-[#3E2723] mb-2">Order Placed!</h2>
            <p class="text-sm text-[#8D6E63] mb-8">Payment completed for ₱<span x-text="total.toFixed(2)"></span>.</p>

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
                <button type="button" @click="resetCart()" class="flex-1 py-4 bg-[#FAFAFA] border border-[#F0E6D2] rounded-full text-[#8D6E63] hover:bg-[#FDF8F5] font-bold transition">New Order</button>
                <button type="button" class="flex-1 py-4 bg-[#3E2723] text-white rounded-full hover:bg-[#271815] font-bold transition shadow-md">Print Receipt</button>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('posSystem', () => ({
            menuItems: {!! json_encode($products ?? []) !!},
            
            wifiAddons: [
                { id: 'w1', name: '1 Hour Wi-Fi', price: 20.00, type: 'wifi', category: 'Wi-Fi', duration: 60 },
                { id: 'w2', name: '3 Hours Wi-Fi', price: 50.00, type: 'wifi', category: 'Wi-Fi', duration: 180 },
                { id: 'w3', name: 'Whole Day Wi-Fi', price: 100.00, type: 'wifi', category: 'Wi-Fi', duration: 1440 }
            ],
            
            cart: [],
            showModal: false,
            isProcessing: false,
            generatedCode: '',
            
            searchQuery: '',
            selectedCategory: 'All',
            
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

            get total() {
                return this.cart.reduce((sum, item) => sum + (Number(item.price) * item.quantity), 0);
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
                            total_amount: this.total,
                            cart: this.cart
                        })
                    });

                    let errorData = response.ok ? null : await response.json();
                    
                    if (!response.ok) {
                        alert('Server Error: ' + (errorData.message || 'Failed to process transaction.'));
                        return;
                    }

                    let data = await response.json();

                    if (data.success) {
                        this.generatedCode = data.hasWifi ? data.generatedCode : '';
                        this.showModal = true;
                    } else {
                        alert('Transaction failed. Please try again.');
                    }
                } catch (error) {
                    alert('Network error. Check console.');
                } finally {
                    this.isProcessing = false;
                }
            },

            resetCart() {
                this.cart = [];
                this.showModal = false;
                this.generatedCode = '';
                this.searchQuery = '';
            }
        }))
    })
</script>
@endsection