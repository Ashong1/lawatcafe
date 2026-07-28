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

{{-- AI Pairing Suggestion --}}
<div x-show="suggestion" x-transition class="shrink-0 mb-3 p-3 rounded-xl border border-amber-200 bg-amber-50 flex items-center gap-3" style="display: none;">
    <div class="w-8 h-8 rounded-full bg-amber-100 flex items-center justify-center shrink-0">
        <x-lucide-sparkles class="w-4 h-4 text-amber-700" />
    </div>
    <p class="flex-1 text-[11px] font-bold text-amber-900 leading-snug" x-text="suggestion && suggestion.message"></p>
    <button type="button" @click="addSuggestion()" class="shrink-0 bg-amber-600 hover:bg-amber-700 text-white text-[10px] font-black uppercase tracking-widest px-3 py-1.5 rounded-full transition">Add</button>
    <button type="button" @click="dismissSuggestion()" class="shrink-0 w-6 h-6 flex items-center justify-center text-amber-700/50 hover:text-amber-900 transition">
        <x-lucide-x class="w-3.5 h-3.5" />
    </button>
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
                    <h4 @click="editNote(index)" class="font-bold text-sm text-[#3E2723] truncate pr-2 flex items-center cursor-pointer hover:text-amber-800 transition-colors" title="Click to add note">
                        <span x-text="cartItem.name"></span>
                        <span x-show="cartItem.variant" class="ml-1.5 text-[9px] font-black uppercase tracking-widest px-1.5 py-0.5 rounded-full shrink-0" :class="cartItem.variant === 'Hot' ? 'bg-amber-100 text-amber-800' : 'bg-blue-100 text-blue-800'" x-text="cartItem.variant"></span>
                    </h4>
                    <p x-show="cartItem.note" x-text="cartItem.note" @click="editNote(index)" class="text-[9px] text-amber-700 font-bold italic truncate cursor-pointer"></p>
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
    <div x-show="freeWifiMinAmount > 0" x-transition class="p-3 rounded-xl border flex flex-col gap-2 transition-all" :class="grandTotal >= freeWifiMinAmount ? 'bg-[#F3F9FF] border-[#E3F2FD]' : 'bg-[#FAFAFA] border-[#F0E6D2]'" style="display: none;">
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

    <div class="grid grid-cols-2 gap-3">
        <div>
            <label class="block text-[9px] font-black text-[#8D6E63] uppercase tracking-[0.2em] mb-1.5">Payment Method</label>
            <select x-model="paymentMethod" class="w-full py-2 px-2 border border-[#F0E6D2] rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3E2723] bg-[#FAFAFA] transition-all text-[11px] font-bold text-[#3E2723]">
                <option value="Cash">Cash</option>
                <option value="GCash">GCash</option>
                <option value="Card">Credit/Debit</option>
            </select>
        </div>
        <div>
            <label class="block text-[9px] font-black text-[#8D6E63] uppercase tracking-[0.2em] mb-1.5">Amount Tendered (₱)</label>
            <input type="number" x-model.number="amountTendered" class="w-full py-2 px-3 border border-[#F0E6D2] rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3E2723] bg-[#FAFAFA] transition-all text-sm font-bold text-[#3E2723]" placeholder="0.00">
        </div>
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
