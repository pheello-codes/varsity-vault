<?php
$page_title = "Shopping Cart";
include 'includes/config.php';
?>

<?php include 'includes/header.php'; ?>

<div class="max-w-4xl mx-auto">
    <h1 class="text-3xl font-bold text-gray-900 mb-8">Shopping Cart</h1>

    <div id="cart-content">
        <!-- Cart items will be loaded here by JavaScript -->
        <div class="text-center py-12">
            <p class="text-gray-500 text-lg">Loading cart...</p>
        </div>
    </div>
</div>

<script>
// Load cart from localStorage and display
function loadCart() {
    const cart = JSON.parse(localStorage.getItem('cart')) || [];
    const cartContent = document.getElementById('cart-content');

    if (cart.length === 0) {
        cartContent.innerHTML = `
            <div class="text-center py-12">
                <svg class="w-16 h-16 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4m0 0L7 13m0 0l-1.1 5H19M7 13l-1.1 5M7 13h10m0 0v8a2 2 0 01-2 2H9a2 2 0 01-2-2v-8z"></path>
                </svg>
                <h3 class="text-xl font-semibold text-gray-900 mb-2">Your cart is empty</h3>
                <p class="text-gray-500 mb-6">Add some notes to get started!</p>
                <a href="index.php" class="bg-blue-600 text-white px-6 py-3 rounded-lg hover:bg-blue-700 transition duration-300">Browse Notes</a>
            </div>
        `;
        return;
    }

    let total = 0;
    let cartHTML = `
        <div class="bg-white rounded-lg shadow-md overflow-hidden mb-6">
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-xl font-semibold">Cart Items</h2>
            </div>
            <div class="divide-y divide-gray-200">
    `;

    cart.forEach((item, index) => {
        const itemTotal = item.price * item.quantity;
        total += itemTotal;

        cartHTML += `
            <div class="p-6 flex items-center justify-between">
                <div class="flex-1">
                    <h3 class="text-lg font-semibold text-gray-900">${item.title}</h3>
                    <p class="text-gray-600">Quantity: ${item.quantity}</p>
                </div>
                <div class="flex items-center space-x-4">
                    <span class="text-xl font-bold text-blue-600">R ${itemTotal.toFixed(2)}</span>
                    <button onclick="removeFromCart(${index})" class="text-red-600 hover:text-red-800">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                        </svg>
                    </button>
                </div>
            </div>
        `;
    });

    cartHTML += `
            </div>
        </div>

        <div class="bg-white rounded-lg shadow-md p-6">
            <div class="flex justify-between items-center text-2xl font-bold mb-6">
                <span>Total:</span>
                <span class="text-blue-600">R ${total.toFixed(2)}</span>
            </div>
            <form method="POST" action="checkout.php">
                <input type="hidden" name="cart_items" value='${JSON.stringify(cart)}'>
                <button type="submit" class="bg-green-600 text-white px-8 py-3 rounded-lg hover:bg-green-700 transition duration-300 w-full text-lg font-semibold">
                    Proceed to Checkout
                </button>
            </form>
        </div>
    `;

    cartContent.innerHTML = cartHTML;
}

// Remove item from cart
function removeFromCart(index) {
    let cart = JSON.parse(localStorage.getItem('cart')) || [];
    cart.splice(index, 1);
    localStorage.setItem('cart', JSON.stringify(cart));
    updateCartCount();
    loadCart();
}

// Load cart when page loads
document.addEventListener('DOMContentLoaded', loadCart);
</script>

<?php include 'includes/footer.php'; ?>