// Cart functionality using localStorage

// Initialize cart
let cart = JSON.parse(localStorage.getItem('cart')) || [];

// Update cart count in navbar
function updateCartCount() {
    const count = cart.reduce((total, item) => total + item.quantity, 0);
    document.getElementById('cart-count').textContent = count;
}

// Add item to cart
function addToCart(noteId, title, price) {
    const existingItem = cart.find(item => item.id == noteId);
    if (existingItem) {
        existingItem.quantity++;
    } else {
        cart.push({
            id: noteId,
            title: title,
            price: parseFloat(price),
            quantity: 1
        });
    }
    localStorage.setItem('cart', JSON.stringify(cart));
    updateCartCount();
    alert('Item added to cart!');
    // Optional: redirect to cart page
    // window.location.href = 'cart.php';
}

// Remove item from cart
function removeFromCart(noteId) {
    cart = cart.filter(item => item.id != noteId);
    localStorage.setItem('cart', JSON.stringify(cart));
    updateCartCount();
}

// Get cart items
function getCartItems() {
    return cart;
}

// Clear cart
function clearCart() {
    cart = [];
    localStorage.removeItem('cart');
    updateCartCount();
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', updateCartCount);