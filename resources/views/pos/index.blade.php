{{-- Dynamically load the layout based on the user's role --}}
@extends(auth()->user()->role === 'admin' ? 'layouts.admin' : 'layouts.staff')

@section('title', 'POS Register')

@section('content')

<style>
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
</style>

<div x-data="posSystem()" class="bg-[#FDF8F5] w-full flex-1 h-full flex items-stretch text-[#4A3B32] overflow-hidden" style="font-family: 'Montserrat', sans-serif;">

    {{-- Menu Area (Left) --}}
    <div class="flex-1 p-8 flex flex-col overflow-hidden">
        <div class="flex-1 bg-white p-6 md:p-8 rounded-2xl shadow-sm flex flex-col overflow-hidden border border-[#F0E6D2]">
            
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
                <div class="absolute inset-0 overflow-y-auto pb-6 pr-2 [&::-webkit-scrollbar]:w-2 [&::-webkit-scrollbar-track]:bg-transparent [&::-webkit-scrollbar-thumb]:bg-[#D7CCC8] [&::-webkit-scrollbar-thumb]:rounded-full">
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-5">
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
    <div x-show="showVariantModal" x-cloak class="fixed inset-0 bg-black/60 backdrop-blur-sm flex items-center justify-center z-[80]" style="display: none;">
        <div @click.away="closeVariantModal()" class="bg-white rounded-2xl shadow-2xl p-8 max-w-sm w-full border-t-8 border-[#3E2723]">
            <h2 class="text-2xl font-black text-[#3E2723] mb-1" x-text="pendingItem?.name"></h2>
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
        </div>
    </div>

    {{-- Cart Sidebar (Right) --}}
    <div class="bg-white p-5 flex flex-col shrink-0 border-l border-[#F0E6D2] shadow-[-10px_0_30px_rgba(62,39,35,0.05)] h-full z-10 overflow-hidden" style="width: 28rem;">
        
        {{-- TOP HEADER (Fixed: Will not shrink) --}}
        <div class="shrink-0 flex flex-col gap-4 mb-2">
            <div class="flex justify-between items-center">
                <div>
                    <h3 class="text-xl font-black text-[#3E2723]">Current Order</h3>
                    <p class="text-xs font-medium text-[#8D6E63] mt-0.5"><span x-text="cart.length"></span> items in cart</p>
                </div>
                
                <button type="button" x-show="cart.length > 0" @click="resetCart()" class="w-9 h-9 rounded-full bg-red-50 text-red-500 hover:bg-red-500 hover:text-white transition-colors flex items-center justify-center shadow-sm" title="Clear All">
                    <x-lucide-trash-2 class="w-4 h-4" />
                </button>
            </div>

            <div class="flex bg-[#FDF8F5] p-1 rounded-xl border border-[#F0E6D2]/50">
                <button @click="orderType = 'dine_in'" class="flex-1 py-2 rounded-lg text-xs font-bold transition flex items-center justify-center gap-2" :class="orderType === 'dine_in' ? 'bg-white text-[#3E2723] shadow-sm' : 'text-[#8D6E63] hover:text-[#3E2723]'">
                    <x-lucide-utensils class="w-3.5 h-3.5" />
                    <span>Dine In</span>
                </button>
                <button @click="orderType = 'takeaway'" class="flex-1 py-2 rounded-lg text-xs font-bold transition flex items-center justify-center gap-2" :class="orderType === 'takeaway' ? 'bg-white text-[#3E2723] shadow-sm' : 'text-[#8D6E63] hover:text-[#3E2723]'">
                    <x-lucide-shopping-bag class="w-3.5 h-3.5" />
                    <span>Take Away</span>
                </button>
            </div>
        </div>

        {{-- CART ITEMS (Scrollable: Takes up remaining space) --}}
        {{-- min-h-0 is the CSS secret that allows flex-1 to scroll properly --}}
        <div class="flex-1 min-h-0 overflow-y-auto pr-2 my-2 [&::-webkit-scrollbar]:w-1.5 [&::-webkit-scrollbar-track]:bg-transparent [&::-webkit-scrollbar-thumb]:bg-[#E0D4C3] [&::-webkit-scrollbar-thumb]:rounded-full">
            <div class="flex flex-col">
                <template x-for="(cartItem, index) in cart" :key="cartItem.id + '-' + index">
                    
                    {{-- Added min-h-[5rem] (80px) to absolutely forbid the browser from squishing the item vertically --}}
                    <div class="flex gap-3 items-center bg-white group shrink-0 min-h-[5rem] py-2 border-b border-[#FDF8F5] last:border-0">
                        <div class="w-10 h-10 bg-[#FDF8F5] border border-[#F0E6D2] rounded-lg flex items-center justify-center shrink-0">
                            <template x-if="cartItem.type === 'wifi'">
                                <x-lucide-wifi class="w-5 h-5 text-amber-800/30" />
                            </template>
                            <template x-if="cartItem.type !== 'wifi'">
                                <x-lucide-coffee class="w-5 h-5 text-amber-800/30" />
                            </template>
                        </div>
                        
                        <div class="flex-1 min-w-0">
                            <h4 class="font-bold text-sm text-[#3E2723] truncate pr-2 flex items-center">
                                <span x-text="cartItem.name"></span>
                                <span x-show="cartItem.variant" class="ml-1.5 text-[9px] font-black uppercase tracking-widest px-1.5 py-0.5 rounded-full shrink-0" :class="cartItem.variant === 'Hot' ? 'bg-amber-100 text-amber-800' : 'bg-blue-100 text-blue-800'" x-text="cartItem.variant"></span>
                            </h4>
                            <div class="flex justify-between items-center mt-0.5">
                                <p class="font-black text-xs text-[#8D6E63]" x-text="'₱' + (Number(cartItem.price) * cartItem.quantity).toFixed(2)"></p>
                                
                                <div class="flex items-center bg-[#FAFAFA] border border-[#F0E6D2] rounded-full px-1 py-0.5 shrink-0">
                                    <button type="button" @click="removeFromCart(index)" class="w-5 h-5 flex items-center justify-center text-[#8D6E63] hover:text-[#3E2723] font-bold transition text-xs">-</button>
                                    <span class="w-5 text-center text-[11px] font-bold text-[#3E2723]" x-text="cartItem.quantity"></span>
                                    <button type="button" @click="addToCart(cartItem)" class="w-5 h-5 flex items-center justify-center text-[#8D6E63] hover:text-[#3E2723] font-bold transition text-xs">+</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </template>
                
                <div x-show="cart.length === 0" class="flex flex-col items-center justify-center py-10 text-[#A1887F] text-sm font-medium">
                    <x-lucide-shopping-cart class="w-10 h-10 mb-3 opacity-20" />
                    Your cart is empty.
                </div>
            </div>
        </div>

        {{-- BOTTOM CHECKOUT (Fixed: Will not shrink) --}}
        <div class="shrink-0 pt-3 border-t border-[#F0E6D2] flex flex-col gap-3">
            <div x-show="freeWifiMinAmount > 0" class="p-3 rounded-xl border flex flex-col gap-2 transition-all" :class="grandTotal >= freeWifiMinAmount ? 'bg-[#F3F9FF] border-[#E3F2FD]' : 'bg-[#FAFAFA] border-[#F0E6D2]'" style="display: none;">
                <div class="flex justify-between items-center">
                    <p class="text-[9px] font-black uppercase tracking-[0.2em]" :class="grandTotal >= freeWifiMinAmount ? 'text-[#1565C0]' : 'text-[#8D6E63]'">
                        <span x-show="grandTotal >= freeWifiMinAmount"><x-lucide-wifi class="w-3 h-3 inline-block mr-1 -mt-0.5"/> Free Wi-Fi Unlocked!</span>
                        <span x-show="grandTotal < freeWifiMinAmount">Free Wi-Fi Promo</span>
                    </p>
                    <p class="text-[9px] font-bold" :class="grandTotal >= freeWifiMinAmount ? 'text-[#1565C0]' : 'text-[#8D6E63]'" x-text="Math.min(100, Math.round((grandTotal / freeWifiMinAmount) * 100)) + '%'"></p>
                </div>
                
                <div class="w-full bg-gray-200 rounded-full h-1.5 overflow-hidden">
                    <div class="h-full transition-all duration-500" 
                         :class="grandTotal >= freeWifiMinAmount ? 'bg-[#1565C0]' : 'bg-[#3E2723]'" 
                         :style="`width: ${Math.min(100, (grandTotal / freeWifiMinAmount) * 100)}%`"></div>
                </div>

                <p class="text-[9px] text-[#A1887F] font-medium text-center">
                    <template x-if="grandTotal >= freeWifiMinAmount">
                        <span x-text="`Order qualifies for ${freeWifiDuration} mins free access.`"></span>
                    </template>
                    <template x-if="grandTotal < freeWifiMinAmount">
                        <span x-text="`Spend ₱${(freeWifiMinAmount - grandTotal).toFixed(2)} more to get ${freeWifiDuration} mins free.`"></span>
                    </template>
                </p>
            </div>

            <div>
                <label class="block text-[9px] font-black text-[#8D6E63] uppercase tracking-[0.2em] mb-1.5">Discount</label>
                <div class="flex gap-2">
                    <button type="button" @click="discountType = 'none'; discountAmount = 0" class="flex-1 py-1.5 rounded-md text-[11px] font-bold transition border" :class="discountType === 'none' ? 'bg-[#3E2723] text-white border-[#3E2723]' : 'bg-[#FAFAFA] text-[#8D6E63] border-[#F0E6D2] hover:bg-[#FDF8F5]'">None</button>
                    <button type="button" @click="discountType = 'senior'; discountAmount = 0.20" class="flex-1 py-1.5 rounded-md text-[11px] font-bold transition border" :class="discountType === 'senior' ? 'bg-[#3E2723] text-white border-[#3E2723]' : 'bg-[#FAFAFA] text-[#8D6E63] border-[#F0E6D2] hover:bg-[#FDF8F5]'">Senior/PWD (20%)</button>
                </div>
            </div>

            <div>
                <label class="block text-[9px] font-black text-[#8D6E63] uppercase tracking-[0.2em] mb-1.5">Amount Tendered (₱)</label>
                <input type="number" x-model.number="amountTendered" class="w-full py-2 px-3 border border-[#F0E6D2] rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3E2723] bg-[#FAFAFA] transition-all text-sm font-bold text-[#3E2723]" placeholder="Enter amount">
            </div>

            <div class="space-y-1">
                <div class="flex justify-between text-xs text-[#8D6E63] font-medium">
                    <span>Subtotal</span>
                    <span x-text="'₱' + subtotal.toFixed(2)"></span>
                </div>
                <div class="flex justify-between text-xs text-[#8D6E63] font-medium">
                    <span>Discount</span>
                    <span class="text-red-500" x-text="'- ₱' + calculatedDiscount.toFixed(2)"></span>
                </div>
                <div class="flex justify-between text-xs text-[#8D6E63] font-medium">
                    <span>VAT (12% inc)</span>
                    <span x-text="'₱' + vatAmount.toFixed(2)"></span>
                </div>
                <div class="flex justify-between text-lg font-bold text-[#3E2723] pt-1.5 border-t border-[#FDF8F5]">
                    <span>Total</span>
                    <span x-text="'₱' + grandTotal.toFixed(2)"></span>
                </div>
                <div class="flex justify-between text-xs font-bold text-green-600 pt-1 border-t border-[#FDF8F5]" x-show="amountTendered > 0">
                    <span>Change</span>
                    <span x-text="'₱' + Math.max(0, amountTendered - grandTotal).toFixed(2)"></span>
                </div>
            </div>

            <button type="button" @click="submitCheckout()" :disabled="cart.length === 0 || isProcessing || (amountTendered > 0 && amountTendered < grandTotal)" class="w-full bg-[#3E2723] hover:bg-[#271815] text-white py-3 rounded-full font-bold uppercase tracking-widest transition shadow-lg shadow-[#3E2723]/20 disabled:opacity-50 disabled:shadow-none disabled:cursor-not-allowed flex justify-center items-center text-xs gap-2 shrink-0">
                <template x-if="!isProcessing">
                    <x-lucide-send class="w-4 h-4" />
                </template>
                <template x-if="isProcessing">
                    <x-lucide-loader-2 class="w-4 h-4 animate-spin" />
                </template>
                <span x-text="isProcessing ? 'Processing...' : (amountTendered > 0 && amountTendered < grandTotal ? 'Insufficient Funds' : 'Place Order')"></span>
            </button>
        </div>
    </div>

    <div x-show="showModal" class="fixed inset-0 bg-black/40 backdrop-blur-sm flex items-center justify-center z-[60]" style="display: none;">
        <div @click.away="resetCart()" class="bg-white rounded-2xl shadow-2xl p-8 max-w-sm w-full text-center border-t-8 border-[#3E2723]">
            <div class="w-20 h-20 bg-[#E8F5E9] rounded-full flex items-center justify-center mx-auto mb-6 text-[#2E7D32]">
                <x-lucide-check class="w-10 h-10" />
            </div>
            
            <h2 class="text-2xl font-bold text-[#3E2723] mb-2">Order Placed!</h2>
            <p class="text-sm text-[#8D6E63] mb-2">Payment completed for ₱<span x-text="grandTotal.toFixed(2)"></span>.</p>
            <p class="text-sm font-bold text-green-600 mb-8" x-show="amountTendered > 0">Change: ₱<span x-text="Math.max(0, amountTendered - grandTotal).toFixed(2)"></span></p>

            <template x-if="checkoutHasWifi">
                <div class="border border-[#E3F2FD] rounded-2xl p-6 mb-8 bg-[#F3F9FF]">
                    <p class="text-xs text-[#1565C0] uppercase tracking-widest mb-2 font-bold">Generated Wi-Fi Access</p>
                    <p class="text-3xl font-mono font-black tracking-widest text-[#0D47A1]" x-text="generatedCode"></p>
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
        </div>
    </div>

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

            <div class="mt-6 text-center">
                <a href="{{ auth()->user()->role === 'admin' ? route('dashboard') : route('staff.dashboard') }}" class="text-[10px] font-black text-[#A1887F] hover:text-[#3E2723] uppercase tracking-[0.2em] transition-all flex items-center justify-center gap-2 group">
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
            showModal: false,
            showVariantModal: false,
            pendingItem: null,
            saleId: null,
            generatedCode: '',
            checkoutHasWifi: false,
            isProcessing: false,
            freeWifiMinAmount: {{ $freeWifiMinAmount ?? 0 }},
            freeWifiDuration: {{ $freeWifiDuration ?? 0 }},

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
            },

            removeFromCart(index) {
                if (this.cart[index].quantity > 1) {
                    this.cart[index].quantity--;
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
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        },
                        body: JSON.stringify({
                            cart: this.cart,
                            total_amount: this.grandTotal,
                            discount_type: this.discountType,
                            discount_amount: this.calculatedDiscount,
                            payment_method: 'Cash',
                            amount_received: this.amountTendered,
                            order_type: this.orderType,
                            shift_id: {{ $activeShift ? $activeShift->id : 'null' }}
                        })
                    });

                    const result = await response.json();
                    
                    if (result.success) {
                        this.saleId = result.sale_id;
                        this.generatedCode = result.generatedCode || '';
                        this.checkoutHasWifi = result.hasWifi || false;
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
            }
        }
    }
</script>

@endsection