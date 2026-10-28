{{-- TOP HEADER (Fixed: Will not shrink) --}}
<div class="shrink-0 flex flex-col gap-4 mb-2">
    <div class="flex justify-between items-center">
        <div>
            <h3 class="text-xl font-bold text-[#3E2723]">Current Order</h3>
            <p class="text-xs font-medium text-[#795548] mt-0.5" x-text="(n => n + (n === 1 ? ' item' : ' items'))(cart.reduce((sum, i) => sum + i.quantity, 0))"></p>
        </div>

        <button type="button" x-show="cart.length > 0" @click="window.confirmAction({ title: 'Clear this order?', text: 'Everything in the cart is removed.', confirmText: 'Clear order', callback: () => resetCart() })" class="w-11 h-11 rounded-full bg-red-50 text-red-600 hover:bg-red-600 hover:text-white transition-colors flex items-center justify-center" title="Clear order" aria-label="Clear order">
            <x-lucide-trash-2 class="w-4 h-4" />
        </button>
    </div>

    <div class="flex bg-[#FDF8F5] p-1 rounded-xl border border-[#F0E6D2]/50">
        <button type="button" @click="orderType = 'dine_in'" :aria-pressed="orderType === 'dine_in' ? 'true' : 'false'" class="flex-1 min-h-[44px] rounded-lg text-xs font-bold transition flex items-center justify-center gap-2" :class="orderType === 'dine_in' ? 'bg-white text-[#3E2723] shadow-sm' : 'text-[#795548] hover:text-[#3E2723]'">
            <x-lucide-utensils class="w-3.5 h-3.5" />
            <span>Dine In</span>
        </button>
        <button type="button" @click="orderType = 'takeaway'" :aria-pressed="orderType === 'takeaway' ? 'true' : 'false'" class="flex-1 min-h-[44px] rounded-lg text-xs font-bold transition flex items-center justify-center gap-2" :class="orderType === 'takeaway' ? 'bg-white text-[#3E2723] shadow-sm' : 'text-[#795548] hover:text-[#3E2723]'">
            <x-lucide-shopping-bag class="w-3.5 h-3.5" />
            <span>Take Away</span>
        </button>
    </div>
</div>

{{-- AI Pairing Suggestion --}}
<div x-show="suggestion"
     x-transition:enter="transition ease-out duration-[400ms]"
     x-transition:enter-start="opacity-0 -translate-y-1"
     x-transition:enter-end="opacity-100 translate-y-0"
     x-transition:leave="transition ease-in duration-300"
     x-transition:leave-start="opacity-100 translate-y-0"
     x-transition:leave-end="opacity-0 -translate-y-1"
     class="shrink-0 mb-3 p-3 rounded-xl border border-amber-200 bg-amber-50 flex items-center gap-3" style="display: none;">
    <div class="w-8 h-8 rounded-full flex items-center justify-center shrink-0" :class="suggestion?.reason === 'free_wifi' ? 'bg-blue-100' : 'bg-amber-100'">
        <x-lucide-wifi class="w-4 h-4 text-blue-700" x-show="suggestion?.reason === 'free_wifi'" />
        <x-lucide-message-circle class="w-4 h-4 text-amber-700" x-show="suggestion?.reason !== 'free_wifi'" />
    </div>
    {{-- Labelled and quoted so it reads as a line to say, not a note about the
         products. A cashier glancing at this mid-order needs to know instantly
         that it is speakable as-is. --}}
    <div class="flex-1 min-w-0">
        <span class="block text-xs font-bold uppercase tracking-wide text-amber-800 mb-0.5"
              x-text="suggestion?.reason === 'free_wifi' ? 'Say to customer · free Wi-Fi' : 'Say to customer'"></span>
        {{-- Real quote characters, not HTML entities: x-text sets textContent,
             so an entity would show up literally as &ldquo; on screen. English
             and Tagalog both shown; the cashier says whichever fits. --}}
        <p class="text-xs font-bold text-amber-900 leading-snug" x-text="suggestion && '“' + suggestion.message + '”'"></p>
        <p x-show="suggestion?.message_tl" class="text-xs font-medium italic text-amber-900 leading-snug mt-1" lang="tl" x-text="suggestion && '“' + suggestion.message_tl + '”'"></p>
    </div>
    <button type="button" @click="addSuggestion()" class="shrink-0 min-h-[44px] lg:min-h-[36px] bg-amber-600 hover:bg-amber-700 text-white text-xs font-bold uppercase tracking-wide px-4 rounded-full transition">Add</button>
    <button type="button" @click="dismissSuggestion()" aria-label="Dismiss suggestion" class="shrink-0 w-11 h-11 lg:w-8 lg:h-8 -mr-2 flex items-center justify-center rounded-full text-amber-700 hover:text-amber-900 hover:bg-amber-100 transition">
        <x-lucide-x class="w-4 h-4" />
    </button>
</div>

{{-- min-h-0 lets this flex-1 list shrink and scroll instead of pushing checkout off screen. --}}
<div class="flex-1 min-h-0 overflow-y-auto pr-2 my-2 [&::-webkit-scrollbar]:w-1.5 [&::-webkit-scrollbar-track]:bg-transparent [&::-webkit-scrollbar-thumb]:bg-[#E0D4C3] [&::-webkit-scrollbar-thumb]:rounded-full">
    <div class="flex flex-col">
        <template x-for="(cartItem, index) in cart" :key="cartItem.id + '-' + cartItem.variant">

            <div class="flex gap-3 items-center bg-white group shrink-0 min-h-[5rem] py-2 border-b border-[#FDF8F5] last:border-0"
                 x-transition:enter="transition ease-out duration-300"
                 x-transition:enter-start="opacity-0 -translate-x-2"
                 x-transition:enter-end="opacity-100 translate-x-0"
                 x-transition:leave="transition ease-in duration-200"
                 x-transition:leave-start="opacity-100 translate-x-0"
                 x-transition:leave-end="opacity-0 translate-x-2">
                <div class="w-10 h-10 bg-[#FDF8F5] border border-[#F0E6D2] rounded-lg flex items-center justify-center shrink-0 overflow-hidden">
                    <template x-if="cartItem.image">
                        <img :src="cartItem.image" alt="" class="w-full h-full object-cover" loading="lazy">
                    </template>
                    <template x-if="cartItem.type === 'wifi'">
                        <x-lucide-wifi class="w-5 h-5 text-amber-800/30" />
                    </template>
                    <template x-if="cartItem.type !== 'wifi' && !cartItem.image">
                        <x-lucide-coffee class="w-5 h-5 text-amber-800/30" />
                    </template>
                </div>

                <div class="flex-1 min-w-0">
                    <h4 @click="editNote(index)" tabindex="0" role="button" @keydown.enter="editNote(index)" @keydown.space.prevent="editNote(index)" class="font-bold text-sm text-[#3E2723] truncate pr-2 flex items-center cursor-pointer hover:text-amber-800 transition-colors" title="Tap to add a note">
                        <span x-text="cartItem.name"></span>
                        <span x-show="cartItem.variant" class="ml-1.5 text-xs font-bold uppercase tracking-wide px-1.5 py-0.5 rounded-full shrink-0" :class="cartItem.variant === 'Hot' ? 'bg-amber-100 text-amber-800' : 'bg-blue-100 text-blue-800'" x-text="cartItem.variant"></span>
                    </h4>
                    <p x-show="cartItem.note" x-text="cartItem.note" @click="editNote(index)" tabindex="0" role="button" @keydown.enter="editNote(index)" @keydown.space.prevent="editNote(index)" class="text-xs text-amber-700 font-bold italic truncate cursor-pointer"></p>
                    <div class="flex justify-between items-center mt-0.5">
                        <p class="font-bold text-xs text-[#795548]" x-text="'₱' + (Number(cartItem.price) * cartItem.quantity).toFixed(2)"></p>

                        <div class="flex items-center bg-[#FAFAFA] border border-[#F0E6D2] rounded-full px-1 py-0.5 shrink-0">
                            <button type="button" @click="removeFromCart(index)" aria-label="Decrease quantity" class="w-11 h-11 lg:w-8 lg:h-8 rounded-full flex items-center justify-center text-[#795548] hover:text-[#3E2723] hover:bg-[#F0E6D2] transition"><x-lucide-minus class="w-4 h-4" /></button>
                            <span class="w-6 text-center text-sm font-bold tabular-nums transition-all duration-200"
                                  :class="flashKey === (cartItem.id + '-' + cartItem.variant) ? 'text-amber-600 scale-125' : 'text-[#3E2723] scale-100'"
                                  x-text="cartItem.quantity"></span>
                            <button type="button" @click="addToCart(cartItem)" aria-label="Increase quantity" class="w-11 h-11 lg:w-8 lg:h-8 rounded-full flex items-center justify-center text-[#795548] hover:text-[#3E2723] hover:bg-[#F0E6D2] transition"><x-lucide-plus class="w-4 h-4" /></button>
                        </div>
                    </div>
                </div>
            </div>
        </template>

        <div x-show="cart.length === 0" class="flex flex-col items-center justify-center py-10 text-[#6D4C41] text-sm font-medium">
            <x-lucide-shopping-cart class="w-10 h-10 mb-3 opacity-20" />
            Your cart is empty.
        </div>
    </div>
</div>

{{-- BOTTOM CHECKOUT (Fixed: Will not shrink) --}}
<div class="shrink-0 pt-3 border-t border-[#F0E6D2] flex flex-col gap-3">
    <div x-show="freeWifiMinAmount > 0"
         x-transition:enter="transition ease-out duration-[400ms]"
         x-transition:enter-start="opacity-0 -translate-y-1"
         x-transition:enter-end="opacity-100 translate-y-0"
         x-transition:leave="transition ease-in duration-300"
         x-transition:leave-start="opacity-100 translate-y-0"
         x-transition:leave-end="opacity-0 -translate-y-1"
         class="px-3 py-2.5 rounded-xl border flex flex-col gap-2 transition-all" :class="grandTotal >= freeWifiMinAmount ? 'bg-[#F3F9FF] border-[#E3F2FD]' : 'bg-[#FAFAFA] border-[#F0E6D2]'" style="display: none;">
        <p class="text-xs font-bold flex items-center gap-1.5" :class="grandTotal >= freeWifiMinAmount ? 'text-[#1565C0]' : 'text-[#6D4C41]'">
            <x-lucide-wifi class="w-3.5 h-3.5 shrink-0"/>
            <span x-text="grandTotal >= freeWifiMinAmount
                ? `Free Wi-Fi: this order gets ${freeWifiDuration} mins.`
                : `Free Wi-Fi: spend ₱${(freeWifiMinAmount - grandTotal).toFixed(2)} more for ${freeWifiDuration} mins.`"></span>
        </p>
        <div class="w-full bg-gray-200 rounded-full h-1.5 overflow-hidden" aria-hidden="true">
            <div class="h-full transition-all duration-500"
                 :class="grandTotal >= freeWifiMinAmount ? 'bg-[#1565C0]' : 'bg-[#3E2723]'"
                 :style="`width: ${Math.min(100, (grandTotal / freeWifiMinAmount) * 100)}%`"></div>
        </div>
    </div>

    <div>
        <p class="block text-xs font-bold text-[#795548] uppercase tracking-wide mb-1.5">Discount</p>
        <div class="flex gap-2">
            <button type="button" @click="discountType = 'none'; discountAmount = 0" :aria-pressed="discountType === 'none' ? 'true' : 'false'" class="flex-1 min-h-[44px] lg:min-h-[36px] rounded-md text-xs font-bold transition border" :class="discountType === 'none' ? 'bg-[#3E2723] text-white border-[#3E2723]' : 'bg-[#FAFAFA] text-[#795548] border-[#F0E6D2] hover:bg-[#FDF8F5]'">None</button>
            <button type="button" @click="discountType = 'senior'; discountAmount = 0.20" :aria-pressed="discountType === 'senior' ? 'true' : 'false'" class="flex-1 min-h-[44px] lg:min-h-[36px] rounded-md text-xs font-bold transition border" :class="discountType === 'senior' ? 'bg-[#3E2723] text-white border-[#3E2723]' : 'bg-[#FAFAFA] text-[#795548] border-[#F0E6D2] hover:bg-[#FDF8F5]'">Senior/PWD (20%)</button>
        </div>
    </div>

    <div>
        <p class="block text-xs font-bold text-[#795548] uppercase tracking-wide mb-1.5">Payment</p>
        <div class="flex flex-wrap gap-2" role="radiogroup" aria-label="Payment method">
            <template x-for="method in paymentOptions" :key="method">
                <button type="button" role="radio" :aria-checked="paymentMethod === method ? 'true' : 'false'" @click="choosePayment(method)"
                        class="flex-1 min-w-[4.5rem] min-h-[44px] lg:min-h-[36px] rounded-md text-xs font-bold transition border inline-flex items-center justify-center gap-1"
                        :class="paymentMethod === method ? 'bg-[#3E2723] text-white border-[#3E2723]' : 'bg-[#FAFAFA] text-[#795548] border-[#F0E6D2] hover:bg-[#FDF8F5]'">
                    <template x-if="method !== 'Cash'"><x-lucide-qr-code class="w-3.5 h-3.5" /></template>
                    <span x-text="method"></span>
                </button>
            </template>
        </div>
    </div>

    <div x-show="paymentMethod === 'Cash'">
        <label class="block text-xs font-bold text-[#795548] uppercase tracking-wide mb-1.5">Cash received (₱)</label>
        <input type="number" inputmode="decimal" aria-label="Cash received in pesos" x-model.number="amountTendered" class="w-full py-2 px-3 border border-[#F0E6D2] rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3E2723] bg-[#FAFAFA] transition-all text-base font-bold text-[#3E2723] tabular-nums" placeholder="Blank = exact amount">
    </div>

    <div x-show="paymentMethod !== 'Cash'" x-cloak x-id="['payref']" class="flex gap-2 items-end">
        <div class="flex-1 min-w-0">
            <label class="block text-xs font-bold text-[#795548] uppercase tracking-wide mb-1.5" :for="$id('payref')">Reference no.</label>
            <input :id="$id('payref')" type="text" x-model="paymentReference" autocomplete="off" autocapitalize="characters" spellcheck="false" maxlength="40"
                   class="w-full py-2 px-3 border border-[#F0E6D2] rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3E2723] bg-[#FAFAFA] text-base lg:text-sm font-bold font-mono text-[#3E2723]" placeholder="From their sent screen">
        </div>
        <button type="button" @click="showQrModal = true" class="shrink-0 min-h-[44px] px-3 rounded-lg border border-blue-200 bg-blue-50 text-blue-800 text-xs font-bold inline-flex items-center gap-1.5">
            <x-lucide-scan-line class="w-4 h-4" /> Show QR
        </button>
    </div>

    <div class="space-y-1">
        <div class="flex justify-between text-xs text-[#795548] font-medium">
            <span>Subtotal</span>
            <span x-text="'₱' + subtotal.toFixed(2)"></span>
        </div>
        <div class="flex justify-between text-xs text-[#795548] font-medium">
            <span>Discount</span>
            <span class="text-red-500" x-text="'- ₱' + calculatedDiscount.toFixed(2)"></span>
        </div>
        <div class="flex justify-between text-xs text-[#795548] font-medium">
            <span>VAT (12% inc)</span>
            <span x-text="'₱' + vatAmount.toFixed(2)"></span>
        </div>
        <div class="flex justify-between text-lg font-bold text-[#3E2723] pt-1.5 border-t border-[#FDF8F5] tabular-nums">
            <span>Total</span>
            <span x-text="'₱' + grandTotal.toFixed(2)"></span>
        </div>
        <div class="flex justify-between text-lg font-bold text-green-700 tabular-nums" x-show="paymentMethod === 'Cash' && amountTendered >= grandTotal && amountTendered > 0">
            <span>Change</span>
            <span x-text="'₱' + Math.max(0, amountTendered - grandTotal).toFixed(2)"></span>
        </div>
    </div>

    <button type="button" @click="confirmCheckout()" :disabled="cart.length === 0 || isProcessing || !paymentReady" class="w-full bg-[#3E2723] hover:bg-[#271815] text-white min-h-[48px] py-3 rounded-full font-bold uppercase tracking-wide transition shadow-lg shadow-[#3E2723]/20 disabled:opacity-50 disabled:shadow-none disabled:cursor-not-allowed flex justify-center items-center text-xs gap-2 shrink-0">
        <template x-if="!isProcessing">
            <x-lucide-send class="w-4 h-4" />
        </template>
        <template x-if="isProcessing">
            <x-lucide-loader-2 class="w-4 h-4 animate-spin" />
        </template>
        <span x-text="isProcessing ? 'Processing...' : (paymentMethod !== 'Cash' && !paymentReady ? 'Type the reference no.' : (amountTendered > 0 && amountTendered < grandTotal ? 'Short by ₱' + (grandTotal - amountTendered).toFixed(2) : 'Place Order'))"></span>
    </button>
</div>
